<?php

namespace App\Console\Commands\Assessment;

use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrPresetSourceAdapter;
use App\Assessment\Omr\NativeBundle;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Printing\BundleRenderer;
use App\Printing\RenderBundlePdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use function abs;
use function array_map;
use function count;
use function file_put_contents;
use function hash;
use function in_array;
use function is_array;
use function json_encode;
use function max;
use function sort;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Semantic parity checklist: old solver builder vs canonical preset path.
 *
 * Compares every old-builder-authored version (layout_blueprint_json NOT
 * NULL) against the canonical preset adapter's normalized physical
 * transform from the ACTUAL rendered-PDF page box. Byte-identical hashes
 * across render paths are explicitly NOT required; only repeated canonical
 * runs must hash identically. Writes a durable parity status consumed by
 * the cutover gate.
 */
final class OmrCanonicalParityCommand extends Command
{
    protected $signature = 'omr:canonical-parity {--template= : limit to one template code} {--json : machine-readable output}';

    protected $description = 'Verify semantic parity between the old solver builder and the canonical preset path';

    public const STATUS_PATH = 'omr-canonical/parity-status.json';

    private const NORMALIZED_TOLERANCE_PX = 1.5;

    public function handle(OmrPresetSourceAdapter $preset, RenderBundlePdf $pdf, BundleRenderer $renderer): int
    {
        $versions = OmrAnswerSheetTemplateVersion::query()
            ->whereNotNull('layout_blueprint_json')
            ->when($this->option('template'), fn ($query, $code) => $query->whereHas('template', fn ($t) => $t->where('code', $code)))
            ->with('template')
            ->orderBy('template_id')
            ->orderBy('version')
            ->get();

        if ($versions->isEmpty()) {
            $this->error('No old-builder-authored versions found to compare.');

            return self::FAILURE;
        }

        $results = [];
        $allPassed = true;
        foreach ($versions as $version) {
            $result = $this->parityFor($preset, $pdf, $renderer, $version);
            $results[] = $result;
            $allPassed = $allPassed && $result['passed'];
            $failing = array_values(array_filter($result['checks'], static fn (array $c): bool => ! $c['passed']));
            $line = sprintf(
                '%s v%d: %s (%d blocks, %d checks%s)',
                $version->template->code,
                $version->version,
                $result['passed'] ? 'PASS' : 'FAIL',
                $result['block_count'],
                count($result['checks']),
                $result['passed'] ? '' : ' — '.($failing[0]['message'] ?? ''),
            );
            $result['passed'] ? $this->info($line) : $this->error($line);
        }

        $status = [
            'contract' => 'omr-canonical-parity.v1',
            'passed' => $allPassed,
            'checked_at' => now()->toIso8601String(),
            'versions' => array_map(static fn (array $result): array => [
                'template_code' => $result['template_code'],
                'version' => $result['version'],
                'passed' => $result['passed'],
                'deterministic_hash' => $result['deterministic_hash'],
            ], $results),
        ];
        Storage::disk((string) config('omr.canonical.storage.disk', 'local'))->put(self::STATUS_PATH, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $this->newLine();
        $allPassed
            ? $this->info('Semantic parity checklist PASSED for '.$versions->count().' version(s).')
            : $this->error('Semantic parity checklist FAILED — cutover stays blocked.');

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{template_code:string,version:int,passed:bool,block_count:int,deterministic_hash:string,checks:list<array{check:string,passed:bool,message:string}>} */
    private function parityFor(OmrPresetSourceAdapter $preset, RenderBundlePdf $pdf, BundleRenderer $renderer, OmrAnswerSheetTemplateVersion $version): array
    {
        $checks = [];
        $bundleTemplate = (array) ((array) $version->native_bundle_json)['template'];
        $oldPages = (array) ($bundleTemplate['pages'] ?? []);
        $paper = $renderer->paper();

        $tempT = tempnam(sys_get_temp_dir(), 'nexiam-parity-t-');
        $tempPdf = tempnam(sys_get_temp_dir(), 'nexiam-parity-pdf-');
        $templatePath = $tempT . '.json';
        $pdfPath = $tempPdf . '.pdf';
        rename((string) $tempT, $templatePath);
        rename((string) $tempPdf, $pdfPath);
        file_put_contents($templatePath, json_encode($bundleTemplate, JSON_THROW_ON_ERROR));
        file_put_contents($pdfPath, $pdf->renderTemplate($bundleTemplate));

        try {
            $run = fn (): ?\App\Assessment\Omr\Canonical\OmrSourceIngestionResult => $preset->convert((string) $templatePath, [
                'pdf_path' => (string) $pdfPath,
                'width_mm' => $paper['width'],
                'height_mm' => $paper['height'],
            ]);

            $first = $run();
            $second = $run();
        } finally {
            @unlink((string) $templatePath);
            @unlink((string) $pdfPath);
        }

        $canonicalBlocks = (array) ($first?->metadata['field_blocks'] ?? []);
        $newPage = (array) ($first?->metadata['new_page'] ?? []);
        $pageCountMatches = $first !== null && count($oldPages) === $first->pageCount();

        $checks[] = [
            'check' => 'page_count',
            'passed' => $pageCountMatches,
            'message' => $pageCountMatches ? 'Canonical page count matches the solver output.' : 'Canonical page count mismatch.',
        ];

        $firstPage = $oldPages[0] ?? [];
        $oldDims = (array) ($firstPage['pageDimensions'] ?? $bundleTemplate['pageDimensions'] ?? []);
        $oldBlocks = (array) ($firstPage['fieldBlocks'] ?? []);
        $checks[] = $this->labelsCheck($oldBlocks, $canonicalBlocks);
        foreach ($oldBlocks as $name => $old) {
            $target = $canonicalBlocks[$name] ?? null;
            if (! is_array($old) || ! is_array($target)) {
                $checks[] = ['check' => "block:$name", 'passed' => false, 'message' => 'Canonical transform lost a field block.'];

                continue;
            }
            $checks[] = $this->normalizedGeometryCheck((string) $name, $old, $target, $oldDims, $newPage);
        }

        $firstHash = hash('sha256', json_encode($first?->metadata['field_blocks'] ?? [], JSON_UNESCAPED_SLASHES));
        $secondHash = hash('sha256', json_encode($second?->metadata['field_blocks'] ?? [], JSON_UNESCAPED_SLASHES));
        $settingsStable = ($first?->settingsHash ?? null) === ($second?->settingsHash ?? null);
        $deterministicHash = $firstHash;
        $checks[] = [
            'check' => 'deterministic_repeat',
            'passed' => $firstHash === $secondHash && $settingsStable,
            'message' => 'Repeated canonical runs produce identical fieldBlocks and settings hashes.',
        ];

        return [
            'template_code' => $version->template->code,
            'version' => (int) $version->version,
            'passed' => ! in_array(false, array_map(static fn (array $check): bool => $check['passed'], $checks), true),
            'block_count' => count($oldBlocks),
            'deterministic_hash' => $deterministicHash,
            'checks' => $checks,
        ];
    }

    /** @param array<string,mixed> $oldBlocks @param array<string,mixed> $newBlocks */
    private function labelsCheck(array $oldBlocks, array $newBlocks): array
    {
        $oldLabels = [];
        $newLabels = [];
        foreach ($oldBlocks as $block) {
            foreach ((array) (($block ?? [])['fieldLabels'] ?? []) as $label) {
                $oldLabels = [...$oldLabels, ...NativeBundle::expandLabel((string) $label)];
            }
        }
        foreach ($newBlocks as $block) {
            foreach ((array) (($block ?? [])['fieldLabels'] ?? []) as $label) {
                $newLabels = [...$newLabels, ...NativeBundle::expandLabel((string) $label)];
            }
        }
        sort($oldLabels);
        sort($newLabels);

        return [
            'check' => 'labels_and_options',
            'passed' => $oldLabels === $newLabels,
            'message' => 'Expanded question labels and option sets are identical.',
        ];
    }

    /** @param array<string,mixed> $old @param array<string,mixed> $target @param array{0:mixed,1:mixed} $oldDims @param array<string,mixed> $newPage */
    private function normalizedGeometryCheck(string $name, array $old, array $target, array $oldDims, array $newPage): array
    {
        $toleranceX = self::NORMALIZED_TOLERANCE_PX / max(1, (int) ($newPage['width'] ?? 1));
        $toleranceY = self::NORMALIZED_TOLERANCE_PX / max(1, (int) ($newPage['height'] ?? 1));
        $oldOrigin = (array) ($old['origin'] ?? [0, 0]);
        $newOrigin = (array) ($target['origin'] ?? [0, 0]);
        $dx = abs($oldOrigin[0] / max(1, (int) $oldDims[0]) - $newOrigin[0] / max(1, (int) ($newPage['width'] ?? 1)));
        $dy = abs($oldOrigin[1] / max(1, (int) $oldDims[1]) - $newOrigin[1] / max(1, (int) ($newPage['height'] ?? 1)));

        return [
            'check' => "normalized_region:$name",
            'passed' => $dx <= $toleranceX && $dy <= $toleranceY,
            'message' => sprintf('%s normalized origin within %.2f target px (dx=%.5f, dy=%.5f).', $name, self::NORMALIZED_TOLERANCE_PX, $dx, $dy),
        ];
    }
}
