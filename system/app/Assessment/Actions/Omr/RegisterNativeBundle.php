<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function file_put_contents;
use function hash;
use function is_dir;
use function json_encode;
use function mkdir;
use function preg_match;
use function str_starts_with;

/**
 * Registers an uploaded native bundle into the canonical store and creates a
 * draft template version. The unchanged NativeBundle::load() path verifies
 * hashes and shape; engine identity must match the pinned OMRChecker pin.
 */
final class RegisterNativeBundle
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  array<string, string>  $files  name => raw bytes (template.json, config.json, manifest.json, reference*.svg)
     */
    public function handle(string $templateCode, array $files, User $actor): OmrAnswerSheetTemplateVersion
    {
        if (preg_match('/^[A-Z0-9-]{3,32}$/', $templateCode) !== 1) {
            throw new RuntimeException('Template codes must be 3–32 characters of A-Z, 0-9, or dashes.');
        }

        if (! isset($files['template.json'], $files['config.json'], $files['manifest.json'])) {
            throw new RuntimeException('A native bundle needs template.json, config.json, and manifest.json.');
        }

        $manifest = json_decode($files['manifest.json'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($manifest)) {
            throw new RuntimeException('The bundle manifest must be a JSON object.');
        }

        $pinned = config('omr.omrchecker');
        $engine = $manifest['engine'] ?? [];
        if (($engine['name'] ?? null) !== ($pinned['engine_name'] ?? null)
            || ($engine['commit'] ?? null) !== ($pinned['engine_commit'] ?? null)
            || ($engine['patch_set'] ?? null) !== ($pinned['patch_identity'] ?? null)) {
            throw new RuntimeException('The bundle engine identity does not match the pinned OMRChecker engine.');
        }

        if (($manifest['template_code'] ?? null) !== $templateCode) {
            throw new RuntimeException('The bundle manifest template_code must match the selected code.');
        }

        return DB::transaction(function () use ($templateCode, $files, $actor, $manifest, $engine): OmrAnswerSheetTemplateVersion {
            $template = OmrAnswerSheetTemplate::query()->firstOrCreate(
                ['code' => $templateCode],
                ['name' => $templateCode, 'is_synthetic' => false],
            );

            $nextVersion = ((int) $template->versions()->max('version')) + 1;

            // Normalize identity for the new store slot: the intake IS version
            // $nextVersion of $templateCode regardless of the source manifest.
            $manifest['template_code'] = $templateCode;
            $manifest['template_version'] = $nextVersion;
            $files['manifest.json'] = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $directory = dirname(__DIR__, 2).'/Omr/NativeBundles/'.$templateCode.'/v'.$nextVersion;
            if (is_dir($directory)) {
                throw new RuntimeException("Version v{$nextVersion} already exists for {$templateCode}.");
            }
            if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create the native bundle directory.');
            }

            foreach ($files as $name => $bytes) {
                if (! str_starts_with($name, 'reference') || ! str_ends_with($name, '.svg')) {
                    if (! in_array($name, ['template.json', 'config.json', 'manifest.json'], true)) {
                        throw new RuntimeException("Unexpected bundle file: {$name}.");
                    }
                }
                file_put_contents($directory.'/'.$name, $bytes);
            }

            $bundle = NativeBundle::load($templateCode, $nextVersion);

            $templateHash = hash('sha256', (string) file_get_contents($directory.'/template.json'));
            $existing = OmrAnswerSheetTemplateVersion::query()
                ->where('config_hash', hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))
                ->where('template_json_hash', $templateHash)
                ->exists();
            if ($existing) {
                throw new RuntimeException('An identical bundle version already exists.');
            }

            $derived = $this->manifestDeriver->derive($bundle);
            [$width, $height] = $bundle->pageDimensions(1);
            $identityRegions = $this->identityRegionsPayload($derived['manifest']);

            $version = OmrAnswerSheetTemplateVersion::query()->create([
                'template_id' => $template->id,
                'version' => $nextVersion,
                'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                'page_width' => $width,
                'page_height' => $height,
                'identity_regions_json' => $identityRegions,
                'provider_config_json' => ['driver' => (string) config('omr.provider.driver')],
                'config_hash' => hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'template_json_hash' => $templateHash,
                'native_bundle_version' => (string) ($bundle->manifest['bundle_version'] ?? 'nexiam.omr.native-bundle.v1'),
                'native_bundle_hash' => $bundle->bundleHash,
                'review_manifest_json' => $derived['manifest'],
                'review_manifest_hash' => $derived['hash'],
                'engine_name' => (string) $engine['name'],
                'engine_commit' => (string) $engine['commit'],
                'patch_identity' => (string) $engine['patch_set'],
            ]);

            $this->recordAuditEvent->handle('omr.native_bundle_registered', [
                'template_code' => $templateCode,
                'template_version_id' => $version->id,
                'bundle_hash' => $bundle->bundleHash,
                'file_count' => count($files),
            ], (string) $actor->id);

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function identityRegionsPayload(array $manifest): array
    {
        $byPage = [];
        foreach ((array) ($manifest['fields'] ?? []) as $field) {
            if (! is_array($field) || ! str_starts_with((string) ($field['label'] ?? ''), 'APPLICANT_NUMBER_')) {
                continue;
            }
            $byPage[(int) ($field['page'] ?? 1)][] = $field['region'];
        }

        $union = fn (array $regions): array => [
            'x' => min(array_map(fn (array $region): float => (float) $region['x'], $regions)),
            'y' => min(array_map(fn (array $region): float => (float) $region['y'], $regions)),
            'w' => max(array_map(fn (array $region): float => (float) $region['x'] + $region['w'], $regions)) - min(array_map(fn (array $region): float => (float) $region['x'], $regions)),
            'h' => max(array_map(fn (array $region): float => (float) $region['y'] + $region['h'], $regions)) - min(array_map(fn (array $region): float => (float) $region['y'], $regions)),
        ];

        if (count($byPage) === 1) {
            return ['applicant_number' => $union($byPage[1] ?? [])];
        }

        $payload = [];
        foreach ($byPage as $pageNo => $regions) {
            $payload[(string) $pageNo] = ['applicant_number' => $union($regions)];
        }

        return $payload;
    }
}
