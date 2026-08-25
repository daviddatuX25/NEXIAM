<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_filter;
use function array_values;
use function base_path;
use function config;
use function count;
use function file_get_contents;
use function hash;
use function hash_file;
use function is_array;
use function is_file;
use function json_encode;
use function sprintf;

final class OmrCanonicalCompileException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [])
    {
        parent::__construct($errorCode.': '.$message);
    }
}

final class OmrCanonicalNativeBundleCompiler
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly CanonicalNativePageRenderer $renderer,
    ) {
    }

    /**
     * @param array<string, mixed> $authoringManifest
     * @return array{version:OmrAnswerSheetTemplateVersion,bundle:NativeBundle,relative_dir:string}
     */
    public function compile(OmrTemplateAuthoringDraft $draft, OmrMarkerProfile $profile, array $authoringManifest): array
    {
        $authoring = (array) $draft->authoring_json;
        $artifactSet = (array) (($authoring['canonical']['artifact_set'] ?? null) ?: []);
        if ($artifactSet !== []) {
            $templateModel = OmrAnswerSheetTemplate::query()->firstOrCreate(
                ['code' => $draft->code],
                ['name' => $draft->name, 'is_synthetic' => false],
            );
            $nextVersion = ((int) $templateModel->versions()->lockForUpdate()->max('version')) + 1;
            if ((int) ($artifactSet['candidate_version'] ?? 0) !== $nextVersion) {
                throw new OmrCanonicalCompileException('compile_candidate_stale', 'The materialized candidate is stale; return to mapping and rematerialize.');
            }

            return $this->promoteMaterializedCandidate($draft, $profile, $authoringManifest, $templateModel, $nextVersion, $artifactSet);
        }
        $mapping = (array) ($authoring['mapping'] ?? []);
        $fieldBlocks = (array) ($mapping['field_blocks'] ?? []);
        if ($fieldBlocks === []) {
            throw new OmrCanonicalCompileException('compile_gate_missing', 'A saved mapping is required before compile.');
        }
        $canonical = (array) ($authoring['canonical'] ?? []);
        $settings = (array) ($canonical['settings'] ?? []);
        $paperWidthMm = (float) ($settings['paper_width_mm'] ?? 216);
        $paperHeightMm = (float) ($settings['paper_height_mm'] ?? 330);
        $paperWidth = OmrPageDimensionMath::pixelsFromMillimetres($paperWidthMm);
        $paperHeight = OmrPageDimensionMath::pixelsFromMillimetres($paperHeightMm);
        $insets = (array) (($authoring['marker']['insets_mm'] ?? null) ?: ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]);
        $frameWidth = $paperWidth - OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['left'] ?? 12)) - OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['right'] ?? 12));
        $frameHeight = $paperHeight - OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['top'] ?? 12)) - OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['bottom'] ?? 14));
        $insetsPx = [
            'top' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['top'] ?? 12)),
            'right' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['right'] ?? 12)),
            'bottom' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['bottom'] ?? 14)),
            'left' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insets['left'] ?? 12)),
        ];
        $insetPx = max($insetsPx);
        if ($frameWidth <= 0 || $frameHeight <= 0) {
            throw new OmrCanonicalCompileException('marker_geometry_invalid', 'Marker insets leave no registration frame.');
        }
        $bubbleDimensions = $this->bubbleDimensions($fieldBlocks);
        $pages = [];
        $pageCount = count((array) ($authoring['pages'] ?? [])) ?: 1;
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pageBlocks = array_filter($fieldBlocks, static function (mixed $block) use ($pageNo): bool {
                return ! is_array($block) || ! isset($block['page_no']) || (int) $block['page_no'] === $pageNo;
            });
            $pages[] = [
                'page_no' => $pageNo,
                'pageDimensions' => [$frameWidth, $frameHeight],
                'fieldBlocks' => $pageBlocks,
                'preProcessors' => [[
                    'name' => 'CropOnMarkers',
                    'options' => [
                        'relativePath' => basename($profile->asset_path),
                        'sheetToMarkerWidthRatio' => (float) config('omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio', 17.0),
                    ],
                ]],
                'markers' => [
                    'family' => $profile->family,
                    'asset_name' => basename($profile->asset_path),
                    'size' => OmrPageDimensionMath::pixelsFromMillimetres((float) $profile->size_mm),
                    'quiet_zone' => OmrPageDimensionMath::pixelsFromMillimetres((float) $profile->quiet_zone_mm),
                    'inset' => $insetPx,
                    'insets_mm' => $insets,
                    'insets_px' => $insetsPx,
                    'paper' => ['width_mm' => $paperWidthMm, 'height_mm' => $paperHeightMm],
                    'centers' => [[0, 0], [$frameWidth, 0], [0, $frameHeight], [$frameWidth, $frameHeight]],
                    'quadrants' => ['top_left', 'top_right', 'bottom_left', 'bottom_right'],
                ],
            ];
        }
        $template = [
            'bubbleDimensions' => $bubbleDimensions,
            'pageDimensions' => [$frameWidth, $frameHeight],
            'visualComponents' => (array) (($authoring['studio']['components'] ?? null) ?: []),
            'pages' => $pages,
        ];
        $config = $this->config([$frameWidth, $frameHeight]);
        $templateBytes = json_encode($template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $configBytes = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $templateModel = OmrAnswerSheetTemplate::query()->firstOrCreate(
            ['code' => $draft->code],
            ['name' => $draft->name, 'is_synthetic' => false],
        );

        $disk = Storage::disk('local');
        $nextVersion = ((int) $templateModel->versions()->lockForUpdate()->max('version')) + 1;
        $relativeDir = 'omr-bundles/'.$draft->code.'/v'.$nextVersion;
        $markerBytes = (string) file_get_contents(base_path($profile->asset_path));
        $markerName = basename($profile->asset_path);
        $files = [
            'template.json' => $templateBytes,
            'config.json' => $configBytes,
        ];
        foreach ($pages as $page) {
            $pageTemplate = $template;
            $pageTemplate['pages'] = [$page];
            $files[sprintf('reference-page-%d.svg', $page['page_no'])] = $this->renderer->renderReferenceSvg($pageTemplate, $markerBytes);
        }
        $files[$markerName] = $markerBytes;
        $fileHashes = [];
        foreach ($files as $name => $bytes) {
            $disk->put($relativeDir.'/'.$name, $bytes);
            $fileHashes[$name] = hash('sha256', $bytes);
        }
        $manifest = [
            'bundle_version' => 'nexiam.omr.native-bundle.v1',
            'template_code' => $draft->code,
            'template_version' => $nextVersion,
            'synthetic' => false,
            'identity_policy' => 'optional',
            'engine' => [
                'name' => (string) config('omr.omrchecker.engine_name', 'OMRChecker'),
                'commit' => (string) config('omr.omrchecker.engine_commit', ''),
                'patch_set' => (string) config('omr.omrchecker.patch_identity', ''),
            ],
            'files' => $fileHashes,
        ];
        $manifestBytes = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $disk->put($relativeDir.'/manifest.json', $manifestBytes);
        $fileHashes['manifest.json'] = hash('sha256', $manifestBytes);

        try {
            $bundle = NativeBundle::load($draft->code, $nextVersion);
            $derived = $this->manifestDeriver->derive($bundle);
            $version = DB::transaction(function () use ($templateModel, $nextVersion, $bundle, $derived, $draft, $authoringManifest, $fileHashes): OmrAnswerSheetTemplateVersion {
                $lockedTemplate = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail($templateModel->id);
                if (((int) $lockedTemplate->versions()->max('version')) + 1 !== $nextVersion) {
                    throw new OmrCanonicalCompileException('compile_conflict', 'Another version was compiled concurrently.');
                }
                [$width, $height] = $bundle->pageDimensions(1);
                return OmrAnswerSheetTemplateVersion::query()->create([
                    'template_id' => $lockedTemplate->id,
                    'version' => $nextVersion,
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'page_width' => $width,
                    'page_height' => $height,
                    'identity_regions_json' => [],
                    'provider_config_json' => ['driver' => (string) config('omr.provider.driver')],
                    'config_hash' => hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'template_json_hash' => hash('sha256', json_encode($bundle->template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'native_bundle_version' => (string) $bundle->manifest['bundle_version'],
                    'native_bundle_json' => ['template' => $bundle->template, 'config' => $bundle->config, 'manifest' => $bundle->manifest, 'files' => $fileHashes],
                    'native_bundle_hash' => $bundle->bundleHash,
                    'engine_name' => $bundle->manifest['engine']['name'] ?? 'OMRChecker',
                    'engine_commit' => $bundle->manifest['engine']['commit'] ?? '',
                    'patch_identity' => $bundle->manifest['engine']['patch_set'] ?? '',
                    'review_manifest_json' => $derived['manifest'],
                    'review_manifest_hash' => $derived['hash'],
                    'authoring_manifest' => $authoringManifest,
                ]);
            });
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($relativeDir);
            throw $exception;
        }

        return ['version' => $version, 'bundle' => $bundle, 'relative_dir' => $relativeDir];
    }

    /**
     * Promote a previously materialized candidate without regenerating any
     * template, config, marker, reference, or PDF geometry.
     *
     * @param array<string,mixed> $authoringManifest
     * @param array<string,mixed> $artifactSet
     * @return array{version:OmrAnswerSheetTemplateVersion,bundle:NativeBundle,relative_dir:string}
     */
    private function promoteMaterializedCandidate(
        OmrTemplateAuthoringDraft $draft,
        OmrMarkerProfile $profile,
        array $authoringManifest,
        OmrAnswerSheetTemplate $templateModel,
        int $nextVersion,
        array $artifactSet,
    ): array {
        $assetIds = [
            'template.json' => (int) ($artifactSet['native_template_asset_id'] ?? 0),
            'config.json' => (int) ($artifactSet['native_config_asset_id'] ?? 0),
            'manifest.json' => (int) ($artifactSet['native_manifest_asset_id'] ?? 0),
            basename((string) $profile->asset_path) => (int) ($artifactSet['marker_asset_id'] ?? 0),
        ];
        foreach ((array) ($artifactSet['reference_assets'] ?? []) as $reference) {
            if (is_array($reference) && isset($reference['page_no'], $reference['asset_id'])) {
                $assetIds['reference-page-'.((int) $reference['page_no']).'.svg'] = (int) $reference['asset_id'];
            }
        }
        foreach ($assetIds as $name => $assetId) {
            if ($assetId <= 0) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', "Materialized candidate is missing {$name}.");
            }
        }

        $files = [];
        foreach ($assetIds as $name => $assetId) {
            $asset = $draft->assets->firstWhere('id', $assetId);
            if (! $asset instanceof OmrTemplateAsset) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', "Materialized candidate asset {$name} is not owned by the draft.");
            }
            $bytes = Storage::disk($asset->disk)->get($asset->path);
            if (! is_string($bytes) || hash('sha256', $bytes) !== $asset->sha256) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', "Materialized candidate asset {$name} failed integrity validation.");
            }
            $files[$name] = $bytes;
        }

        $relativeDir = 'omr-bundles/'.$draft->code.'/v'.$nextVersion;
        $disk = Storage::disk('local');
        foreach ($files as $name => $bytes) {
            $disk->put($relativeDir.'/'.$name, $bytes);
        }
        try {
            $bundle = NativeBundle::load($draft->code, $nextVersion);
            $derived = $this->manifestDeriver->derive($bundle);
            $version = DB::transaction(function () use ($templateModel, $nextVersion, $bundle, $derived, $authoringManifest, $artifactSet): OmrAnswerSheetTemplateVersion {
                $lockedTemplate = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail($templateModel->id);
                if (((int) $lockedTemplate->versions()->max('version')) + 1 !== $nextVersion) {
                    throw new OmrCanonicalCompileException('compile_conflict', 'Another version was compiled concurrently.');
                }
                [$width, $height] = $bundle->pageDimensions(1);
                return OmrAnswerSheetTemplateVersion::query()->create([
                    'template_id' => $lockedTemplate->id,
                    'version' => $nextVersion,
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'page_width' => $width,
                    'page_height' => $height,
                    'identity_regions_json' => [],
                    'provider_config_json' => ['driver' => (string) config('omr.provider.driver')],
                    'config_hash' => hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'template_json_hash' => hash('sha256', json_encode($bundle->template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'native_bundle_version' => (string) $bundle->manifest['bundle_version'],
                    'native_bundle_json' => ['template' => $bundle->template, 'config' => $bundle->config, 'manifest' => $bundle->manifest, 'files' => $bundle->fileHashes],
                    'native_bundle_hash' => $bundle->bundleHash,
                    'engine_name' => $bundle->manifest['engine']['name'] ?? 'OMRChecker',
                    'engine_commit' => $bundle->manifest['engine']['commit'] ?? '',
                    'patch_identity' => $bundle->manifest['engine']['patch_set'] ?? '',
                    'review_manifest_json' => $derived['manifest'],
                    'review_manifest_hash' => $derived['hash'],
                    'authoring_manifest' => [...$authoringManifest, 'artifact_set' => [
                        'artifact_hash' => $artifactSet['artifact_hash'] ?? null,
                        'definition_hash' => $artifactSet['definition_hash'] ?? null,
                        'template_hash' => $artifactSet['template_hash'] ?? null,
                        'config_hash' => $artifactSet['config_hash'] ?? null,
                        'pdf_hash' => $artifactSet['pdf_hash'] ?? null,
                    ]],
                ]);
            });
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($relativeDir);
            throw $exception;
        }

        return ['version' => $version, 'bundle' => $bundle, 'relative_dir' => $relativeDir];
    }

    /** @param array<string, mixed> $fieldBlocks @return array{0:int,1:int} */
    private function bubbleDimensions(array $fieldBlocks): array
    {
        foreach ($fieldBlocks as $block) {
            if (is_array($block) && is_array($block['bubbleDimensions'] ?? null)) {
                return [(int) ($block['bubbleDimensions'][0] ?? 28), (int) ($block['bubbleDimensions'][1] ?? 28)];
            }
        }

        return [28, 28];
    }

    /** @param array{0:int,1:int} $dimensions @return array<string, mixed> */
    private function config(array $dimensions): array
    {
        return [
            'dimensions' => [
                'display_width' => $dimensions[0],
                'display_height' => $dimensions[1],
                'processing_width' => $dimensions[0],
                'processing_height' => $dimensions[1],
            ],
            'alignment_params' => ['auto_align' => false, 'match_col' => 5, 'max_steps' => 30, 'stride' => 8, 'thickness' => 2],
            'threshold_params' => ['GAMMA_LOW' => 0.9, 'MIN_GAP' => 30, 'MIN_JUMP' => 50, 'PAGE_TYPE' => 'NORMAL', 'GLOBAL_PAGE_THRESHOLD_WHITE' => 200, 'GLOBAL_PAGE_THRESHOLD_BLACK' => 100],
            'outputs' => ['show_image_level' => 0, 'save_image_level' => 0, 'save_detections' => false],
        ];
    }
}
