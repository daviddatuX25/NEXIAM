<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

use function count;
use function hash;
use function is_array;
use function json_encode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Saves a staff-authored template as a new draft bundle version.
 *
 * Save boundary: the blueprint (when present) is solved server-side, files
 * are staged as immutable content-addressed artifacts on the private local
 * disk BEFORE any visible version exists, and the database transaction only
 * locks the template row, allocates the version, and publishes the
 * reference. No source-tree writes, no shell cleanup, no client geometry.
 */
final class SaveAuthoredTemplateDraft
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SolveOmrTemplateLayout $solveLayout,
    ) {
    }

    /**
     * @param  array<string, mixed>  $templateJson  authored native template.json content
     * @param  array<string, mixed>|null  $layoutBlueprint  semantic blueprint; when present it is solved server-side and becomes the template
     */
    public function handle(
        string $templateCode,
        string $templateName,
        array $templateJson,
        User $actor,
        ?array $overlay = null,
        bool $skipDuplicateCheck = false,
        ?array $layoutBlueprint = null,
    ): OmrAnswerSheetTemplateVersion {
        if (preg_match('/^[A-Z0-9-]{3,32}$/', $templateCode) !== 1) {
            throw new RuntimeException('Template codes must be 3–32 characters of A-Z, 0-9, or dashes.');
        }

        if (trim($templateName) === '') {
            throw new RuntimeException('A template name is required.');
        }

        $template = OmrAnswerSheetTemplate::query()->firstOrCreate(
            ['code' => $templateCode],
            ['name' => $templateName, 'is_synthetic' => true],
        );

        $nextVersion = ((int) $template->versions()->max('version')) + 1;

        $solvedLayout = null;
        if ($layoutBlueprint !== null) {
            $solvedLayout = $this->solveLayout->handle($layoutBlueprint);
            $templateJson = $solvedLayout->template;
        }

        $pages = $templateJson['pages'] ?? null;
        if (! is_array($pages) || $pages === []) {
            throw new RuntimeException('The authored template needs at least one page.');
        }

        $pageDimensions = array_values((array) ($pages[0]['pageDimensions'] ?? [2060, 3144]));
        $config = $this->defaultConfig([
            (int) ($pageDimensions[0] ?? 2060),
            (int) ($pageDimensions[1] ?? 3144),
        ]);
        $configHash = hash('sha256', json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $templateBytes = json_encode($templateJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $templateHash = hash('sha256', $templateBytes);

        if (! $skipDuplicateCheck) {
            $duplicate = OmrAnswerSheetTemplateVersion::query()
                ->where('config_hash', $configHash)
                ->where('template_json_hash', $templateHash)
                ->exists();
            if ($duplicate) {
                throw new RuntimeException('An identical bundle version already exists.');
            }
        }

        $relativeDir = 'omr-bundles/'.$templateCode.'/v'.$nextVersion;
        $disk = Storage::disk('local');

        $pinned = config('omr.omrchecker');
        $engine = [
            'name' => (string) ($pinned['engine_name'] ?? 'OMRChecker'),
            'commit' => (string) ($pinned['engine_commit'] ?? ''),
            'patch_set' => (string) ($pinned['patch_identity'] ?? ''),
        ];

        $files = [
            'template.json' => $templateBytes,
            'config.json' => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ];

        foreach ($pages as $index => $page) {
            $width = (int) ($page['pageDimensions'][0] ?? 2060);
            $height = (int) ($page['pageDimensions'][1] ?? 3144);
            $files[sprintf('reference-page-%d.svg', $index + 1)] = sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="100%%" height="100%%" fill="#ffffff"/></svg>',
                $width,
                $height,
                $width,
                $height,
            );
        }

        // Marker-bearing versions carry the version-unique fiducial asset in
        // the bundle (content-addressed, hash-covered by the manifest). The
        // worker materializes it next to template.json; a bundle missing it is
        // rejected at load — the engine's exit(31) path can never trigger.
        if ($solvedLayout?->fiducial !== null) {
            $files[$solvedLayout->fiducial['name']] = $solvedLayout->fiducial['bytes'];
        }

        $fileHashes = [];
        foreach ($files as $name => $bytes) {
            $disk->put($relativeDir.'/'.$name, $bytes);
            $fileHashes[$name] = hash('sha256', $bytes);
        }

        $manifest = [
            'bundle_version' => 'nexiam.omr.native-bundle.v1',
            'template_code' => $templateCode,
            'template_version' => $nextVersion,
            'synthetic' => true,
            'engine' => $engine,
            'files' => $fileHashes,
        ];
        $manifestBytes = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $disk->put($relativeDir.'/manifest.json', $manifestBytes);
        $fileHashes['manifest.json'] = hash('sha256', $manifestBytes);

        try {
            // Verify through the unchanged load path: shape, hashes, geometry.
            $bundle = NativeBundle::load($templateCode, $nextVersion);
            $derived = $this->manifestDeriver->derive($bundle);
        } catch (Throwable $exception) {
            $disk->deleteDirectory($relativeDir);

            throw $exception;
        }

        try {
            $version = DB::transaction(function () use ($template, $nextVersion, $bundle, $derived, $actor, $overlay, $solvedLayout, $configHash, $templateHash, $engine, $templateName, $templateCode, $pages): OmrAnswerSheetTemplateVersion {
                // Row lock + re-check: the staged version number must still be
                // the next one. The DB unique (template_id, version) constraint
                // is the hard backstop.
                $locked = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail($template->id);
                $currentMax = (int) $locked->versions()->max('version');
                if ($currentMax + 1 !== $nextVersion) {
                    throw new RuntimeException('Concurrent save detected; this draft was not saved. Please retry.');
                }

                [$width, $height] = $bundle->pageDimensions(1);

                $version = OmrAnswerSheetTemplateVersion::query()->create([
                    'template_id' => $template->id,
                    'version' => $nextVersion,
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'page_width' => $width,
                    'page_height' => $height,
                    'identity_regions_json' => $this->identityRegionsPayload($derived['manifest']),
                    'provider_config_json' => ['driver' => (string) config('omr.provider.driver')],
                    'config_hash' => $configHash,
                    'template_json_hash' => $templateHash,
                    'print_overlay_json' => is_array($overlay) ? $overlay : null,
                    'layout_blueprint_json' => $solvedLayout?->normalizedBlueprint,
                    'layout_blueprint_hash' => $solvedLayout?->blueprintHash,
                    'layout_solver_version' => $solvedLayout !== null ? OmrTemplateLayoutSolver::SOLVER_VERSION : null,
                    'native_bundle_json' => [
                        'template' => $bundle->template,
                        'config' => $bundle->config,
                        'manifest' => $bundle->manifest,
                        'files' => $bundle->fileHashes,
                    ],
                    'native_bundle_hash' => $bundle->bundleHash,
                    'review_manifest_json' => $derived['manifest'],
                    'review_manifest_hash' => $derived['hash'],
                    'engine_name' => $engine['name'],
                    'engine_commit' => $engine['commit'],
                    'patch_identity' => $engine['patch_set'],
                ]);

                $this->recordAuditEvent->handle('omr.authored_template_saved', [
                    'template_code' => $templateCode,
                    'template_version_id' => $version->id,
                    'bundle_hash' => $bundle->bundleHash,
                    'page_count' => count($pages),
                    'layout_solver_version' => $solvedLayout !== null ? OmrTemplateLayoutSolver::SOLVER_VERSION : null,
                ], (string) $actor->id);

                return $version;
            });
        } catch (Throwable $exception) {
            // No visible version: the staged artifacts must not linger as a
            // published reference. Best-effort cleanup; the retention sweep
            // backstops abandoned staged objects.
            $disk->deleteDirectory($relativeDir);

            throw $exception;
        }

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfig(array $pageDimensions = [2060, 3144]): array
    {
        [$width, $height] = $pageDimensions;

        return [
            'dimensions' => [
                'display_width' => $width,
                'display_height' => $height,
                'processing_width' => $width,
                'processing_height' => $height,
            ],
            'alignment_params' => [
                'auto_align' => true,
                'match_col' => 5,
                'max_steps' => 30,
                'stride' => 8,
                'thickness' => 2,
            ],
            'threshold_params' => [
                'GAMMA_LOW' => 0.9,
                'MIN_GAP' => 30,
                'MIN_JUMP' => 50,
                'PAGE_TYPE' => 'NORMAL',
                'GLOBAL_PAGE_THRESHOLD_WHITE' => 200,
                'GLOBAL_PAGE_THRESHOLD_BLACK' => 100,
            ],
            'outputs' => [
                'show_image_level' => 0,
                'save_image_level' => 0,
                'save_detections' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function identityRegionsPayload(array $manifest): array
    {
        foreach ($manifest['fields'] ?? [] as $field) {
            if (($field['field_type'] ?? null) === 'QTYPE_INT'
                && str_starts_with((string) ($field['label'] ?? ''), 'APPLICANT_NUMBER')) {
                return ['name' => (string) $field['label']];
            }
        }

        return [];
    }
}
