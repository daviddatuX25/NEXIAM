<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_map;
use function count;
use function hash;
use function json_encode;
use function sprintf;
use function str_starts_with;

/**
 * Registers a native bundle in the institution-owned private store and creates
 * a draft template version. NativeBundle::loadFromDirectory verifies its
 * hashes and shape; engine identity must match the pinned OMRChecker pin.
 */
final class RegisterNativeBundle
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly MaterialOwnerAccess $institutionAccess,
    ) {}

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
        if (($manifest['synthetic'] ?? false) !== false) {
            throw new RuntimeException('A native registration cannot change synthetic bundle provenance.');
        }

        $manifest['synthetic'] = false;
        $institutionId = $this->institutionAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);

        return DB::transaction(function () use ($templateCode, $files, $actor, $manifest, $engine, $institutionId): OmrAnswerSheetTemplateVersion {
            $template = OmrAnswerSheetTemplate::query()->firstOrCreate(
                ['institution_id' => $institutionId, 'code' => $templateCode],
                ['name' => $templateCode, 'is_synthetic' => false],
            );
            if (! $template->wasRecentlyCreated && (bool) $template->is_synthetic) {
                throw new RuntimeException('Native bundle provenance must match the existing template.');
            }

            $nextVersion = ((int) $template->versions()->max('version')) + 1;
            $relativeDir = sprintf('omr-bundles/institutions/%d/templates/%d/v%d', $institutionId, $template->id, $nextVersion);
            $disk = Storage::disk('local');
            $manifest['template_code'] = $templateCode;
            $manifest['template_version'] = $nextVersion;
            $manifest['synthetic'] = false;
            $files['manifest.json'] = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $templatePayload = json_decode($files['template.json'], true, 512, JSON_THROW_ON_ERROR);
            $markerAsset = null;
            if (is_array($templatePayload) && is_array($templatePayload['pages'] ?? null)) {
                foreach ($templatePayload['pages'] as $page) {
                    if (is_array($page['markers'] ?? null) && is_string($page['markers']['asset_name'] ?? null)) {
                        $markerAsset = $page['markers']['asset_name'];
                        break;
                    }
                }
            } elseif (is_array($templatePayload) && is_array($templatePayload['markers'] ?? null) && is_string($templatePayload['markers']['asset_name'] ?? null)) {
                $markerAsset = $templatePayload['markers']['asset_name'];
            }
            if ($markerAsset !== null && preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $markerAsset) !== 1) {
                throw new RuntimeException('The native bundle declares an unsafe marker asset name.');
            }

            foreach ($files as $name => $bytes) {
                $isReference = preg_match('/^reference[^\/\\\\]*\.svg$/', $name) === 1;
                if (! $isReference && ! in_array($name, ['template.json', 'config.json', 'manifest.json', $markerAsset], true)) {
                    throw new RuntimeException("Unexpected bundle file: {$name}.");
                }
                $disk->put($relativeDir.'/'.$name, $bytes);
            }

            $bundle = NativeBundle::loadFromDirectory($disk->path($relativeDir), $templateCode, $nextVersion);
            $registrationMode = isset($bundle->manifest['registration_mode'])
                ? OmrRegistrationMode::tryFrom((string) $bundle->manifest['registration_mode'])
                : OmrRegistrationMode::FiducialAnchored;
            $qrMode = isset($bundle->manifest['qr_mode'])
                ? OmrQrMode::tryFrom((string) $bundle->manifest['qr_mode'])
                : OmrQrMode::Signed;
            if ($registrationMode === null || $qrMode === null) {
                throw new RuntimeException('The native bundle has an unsupported paper policy.');
            }

            $templateHash = hash('sha256', (string) $disk->get($relativeDir.'/template.json'));
            $existing = OmrAnswerSheetTemplateVersion::query()
                ->whereHas('template', static fn ($query) => $query->where('institution_id', $institutionId))
                ->where('config_hash', (string) $bundle->fileHashes['config.json'])
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
                'registration_mode' => $registrationMode,
                'qr_mode' => $qrMode,
                'page_count' => $bundle->pageCount(),
                'page_width' => $width,
                'page_height' => $height,
                'identity_regions_json' => $identityRegions,
                'provider_config_json' => ['driver' => (string) config('omr.provider.driver')],
                'config_hash' => (string) $bundle->fileHashes['config.json'],
                'template_json_hash' => $templateHash,
                'native_bundle_version' => (string) ($bundle->manifest['bundle_version'] ?? 'nexiam.omr.native-bundle.v1'),
                'native_bundle_hash' => $bundle->bundleHash,
                'native_bundle_json' => [
                    'relative_dir' => $relativeDir,
                    'template' => $bundle->template,
                    'config' => $bundle->config,
                    'manifest' => $bundle->manifest,
                    'files' => $bundle->fileHashes,
                ],
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
                'registration_mode' => $registrationMode->value,
                'qr_mode' => $qrMode->value,
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
