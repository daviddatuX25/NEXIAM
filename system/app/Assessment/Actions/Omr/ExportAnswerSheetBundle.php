<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Canonical\OmrBundlePackage;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

use function base_path;
use function file_get_contents;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_replace;
use function sprintf;

final class ExportAnswerSheetBundle
{
    public function __construct(
        private readonly OmrBundlePackage $package,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Export an answer sheet draft or finalized version into a portable .zip archive.
     *
     * @return array{zip_path: string, filename: string, template_code: string}
     */
    public function handle(
        OmrTemplateAuthoringDraft|OmrAnswerSheetTemplateVersion $target,
        User $actor,
    ): array {
        $isDraft = $target instanceof OmrTemplateAuthoringDraft;
        if ($isDraft) {
            if (! in_array($target->source_kind, [
                OmrTemplateAuthoringDraft::SOURCE_IMAGE,
                OmrTemplateAuthoringDraft::SOURCE_PDF,
                OmrTemplateAuthoringDraft::SOURCE_DOCX,
                OmrTemplateAuthoringDraft::SOURCE_HTML,
            ], true)) {
                OmrSourcePolicy::assertSystemDesignedDraft($target);
            }
        } else {
            OmrSourcePolicy::assertSystemDesignedVersion($target);
        }

        $templateCode = (string) ($isDraft ? $target->code : $target->template->code);
        $templateName = (string) ($isDraft ? $target->name : $target->template->name);
        $versionNumber = (int) ($isDraft ? ($target->lock_version ?? 1) : $target->version);
        if (! $target->registration_mode instanceof OmrRegistrationMode
            || ! $target->qr_mode instanceof OmrQrMode) {
            throw new InvalidArgumentException('The answer sheet has no explicit paper policy.');
        }

        $markerProfile = $isDraft
            ? $target->markerProfile
            : OmrMarkerProfile::query()->where('code', $target->native_bundle_json['manifest']['marker_profile']['code'] ?? '')->first();

        if ($markerProfile === null && $isDraft && $target->marker_profile_id) {
            $markerProfile = OmrMarkerProfile::query()->find($target->marker_profile_id);
        }
        if ($target->registration_mode === OmrRegistrationMode::DocumentBound && $markerProfile !== null) {
            throw new InvalidArgumentException('Document-bound answer sheets cannot export a marker profile.');
        }
        $historicalScoringReference = null;
        if ($target->test_instrument_version_id !== null) {
            $historicalProfile = TestInstrumentVersion::query()
                ->with('instrument')
                ->find((int) $target->test_instrument_version_id);
            $historicalScoringReference = [
                'version_id' => (int) $target->test_instrument_version_id,
                'instrument_code' => $historicalProfile?->instrument?->code,
                'binding' => false,
            ];
        }

        $files = [];
        $assetMap = [];
        $draftAssetsManifest = [];
        $isUploadedOriginal = $isDraft && in_array($target->source_kind, [
            OmrTemplateAuthoringDraft::SOURCE_IMAGE,
            OmrTemplateAuthoringDraft::SOURCE_PDF,
            OmrTemplateAuthoringDraft::SOURCE_DOCX,
        ], true);
        // 1. Gather native bundle files
        if ($isDraft) {
            $draftAssets = $target->assets()->orderBy('id')->get();
            $proofPages = $draftAssets->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)->keyBy('page_no');
            foreach ($draftAssets as $asset) {
                $disk = (string) $asset->disk;
                $bytes = Storage::disk($disk)->get($asset->path);
                if (! is_string($bytes)) {
                    continue;
                }

                if (in_array($asset->kind, [
                    OmrTemplateAsset::KIND_SOURCE,
                    OmrTemplateAsset::KIND_INTERMEDIATE_PAGE,
                    OmrTemplateAsset::KIND_RASTER_PAGE,
                ], true)) {
                    $extension = match (strtolower((string) $asset->mime)) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'application/pdf' => 'pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'image/svg+xml' => 'svg',
                        default => 'bin',
                    };
                    $zipPath = sprintf('assets/asset-%d.%s', $asset->id, $extension);
                    $files[$zipPath] = $bytes;
                    $assetMap[$asset->id] = 'logical:draft_asset_'.$asset->id;
                    $draftAssetsManifest[] = [
                        'old_id' => (int) $asset->id,
                        'zip_path' => $zipPath,
                        'kind' => (string) $asset->kind,
                        'mime' => (string) $asset->mime,
                        'page_no' => $asset->page_no === null ? null : (int) $asset->page_no,
                        'sha256' => (string) $asset->sha256,
                        'metadata' => (array) $asset->metadata,
                    ];
                    if ($asset->kind === OmrTemplateAsset::KIND_RASTER_PAGE
                        && ! isset($proofPages[$asset->page_no ?? 1])
                        && in_array($target->source_kind, [OmrTemplateAuthoringDraft::SOURCE_HTML, OmrTemplateAuthoringDraft::SOURCE_PRESET], true)) {
                        $files[sprintf('print/preview-page-%d.png', $asset->page_no ?? 1)] = $bytes;
                    }
                } elseif ($asset->kind === OmrTemplateAsset::KIND_NATIVE_TEMPLATE) {
                    $files['native/template.json'] = $bytes;
                    $assetMap[$asset->id] = 'logical:native_template';
                } elseif ($asset->kind === OmrTemplateAsset::KIND_NATIVE_CONFIG) {
                    $files['native/config.json'] = $bytes;
                    $assetMap[$asset->id] = 'logical:native_config';
                } elseif ($asset->kind === OmrTemplateAsset::KIND_NATIVE_MANIFEST) {
                    $files['native/manifest.json'] = $bytes;
                    $assetMap[$asset->id] = 'logical:native_manifest';
                } elseif ($asset->kind === OmrTemplateAsset::KIND_REFERENCE_PAGE) {
                    $files[sprintf('native/reference-page-%d.svg', $asset->page_no ?? 1)] = $bytes;
                    $assetMap[$asset->id] = sprintf('logical:reference_page_%d', $asset->page_no ?? 1);
                } elseif ($asset->kind === OmrTemplateAsset::KIND_PRINT_PDF) {
                    if (! $isUploadedOriginal) {
                        $files['print/sheet.pdf'] = $bytes;
                        $assetMap[$asset->id] = 'logical:print_pdf';
                    }
                } elseif ($asset->kind === OmrTemplateAsset::KIND_DIGITAL_PROOF || $asset->kind === OmrTemplateAsset::KIND_RASTER_PAGE) {
                    $files[sprintf('print/preview-page-%d.png', $asset->page_no ?? 1)] = $bytes;
                    $assetMap[$asset->id] = sprintf('logical:preview_page_%d', $asset->page_no ?? 1);
                }
            }
        } else {
            $versionAssets = $target->canonicalAssets()->get();
            foreach ($versionAssets as $asset) {
                $disk = (string) $asset->disk;
                $bytes = Storage::disk($disk)->get($asset->path);
                if (! is_string($bytes)) {
                    continue;
                }

                if ($asset->kind === OmrTemplateAsset::KIND_NATIVE_TEMPLATE) {
                    $files['native/template.json'] = $bytes;
                } elseif ($asset->kind === OmrTemplateAsset::KIND_NATIVE_CONFIG) {
                    $files['native/config.json'] = $bytes;
                } elseif ($asset->kind === OmrTemplateAsset::KIND_NATIVE_MANIFEST) {
                    $files['native/manifest.json'] = $bytes;
                } elseif ($asset->kind === OmrTemplateAsset::KIND_REFERENCE_PAGE) {
                    $files[sprintf('native/reference-page-%d.svg', $asset->page_no ?? 1)] = $bytes;
                } elseif ($asset->kind === OmrTemplateAsset::KIND_PRINT_PDF) {
                    $files['print/sheet.pdf'] = $bytes;
                } elseif ($asset->kind === OmrTemplateAsset::KIND_DIGITAL_PROOF || $asset->kind === OmrTemplateAsset::KIND_RASTER_PAGE) {
                    $files[sprintf('print/preview-page-%d.png', $asset->page_no ?? 1)] = $bytes;
                }
            }

            // Fallback for native bundle if not stored in assets table
            if (! isset($files['native/template.json']) && is_array($target->native_bundle_json['template'] ?? null)) {
                $files['native/template.json'] = json_encode($target->native_bundle_json['template'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            if (! isset($files['native/config.json']) && is_array($target->native_bundle_json['config'] ?? null)) {
                $files['native/config.json'] = json_encode($target->native_bundle_json['config'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            if (! isset($files['native/manifest.json']) && is_array($target->native_bundle_json['manifest'] ?? null)) {
                $files['native/manifest.json'] = json_encode($target->native_bundle_json['manifest'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        // 2. Gather marker image bytes
        if ($markerProfile !== null && ! empty($markerProfile->asset_path)) {
            $markerBytes = $this->resolveMarkerBytes($markerProfile->asset_path);
            if ($markerBytes !== null) {
                $files['native/marker.jpg'] = $markerBytes;
            }
        }

        // 3. Prepare authoring.json with logical asset tokens
        $rawAuthoring = $isDraft
            ? (array) ($target->authoring_json ?? [])
            : (array) ($target->authoring_manifest ?? []);

        $portableAuthoringJson = $this->rewriteAuthoringPayloadToLogicalTokens($rawAuthoring, $assetMap);
        $files['authoring.json'] = json_encode($portableAuthoringJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 4. Determine page dimensions and question count from template json if present
        $templateData = isset($files['native/template.json']) ? json_decode($files['native/template.json'], true) : [];
        $pageCount = $isDraft
            ? (int) $target->requested_page_count
            : (is_array($templateData['pages'] ?? null) ? count($templateData['pages']) : 1);
        $dimensions = is_array($templateData['pageDimensions'] ?? null)
            ? ['width_px' => $templateData['pageDimensions'][0] ?? 2060, 'height_px' => $templateData['pageDimensions'][1] ?? 3144]
            : ['width_px' => 2060, 'height_px' => 3144];

        // 5. Construct root manifest.json
        $pagesManifest = [];
        for ($p = 1; $p <= $pageCount; $p++) {
            $pagesManifest[] = [
                'page_no' => $p,
                'reference_asset' => sprintf('native/reference-page-%d.svg', $p),
                'preview_asset' => sprintf('print/preview-page-%d.png', $p),
                'dimensions_px' => [$dimensions['width_px'], $dimensions['height_px']],
            ];
        }

        $manifestData = [
            'schema_version' => OmrBundlePackage::SCHEMA_VERSION,
            'source_provenance' => [
                'system' => 'NEXIAM Greenfield Monolith',
                'institution' => (string) config('app.name', 'NEXIAM'),
                'exported_by' => $actor->name ?? 'Staff',
                'original_code' => $templateCode,
                'original_version' => $versionNumber,
                'is_draft' => $isDraft,
                'historical_scoring_reference' => $historicalScoringReference,
            ],
            'template' => [
                'code' => $templateCode,
                'display_name' => $templateName,
                'page_count' => $isDraft ? (int) $target->requested_page_count : (int) $target->page_count,
                'registration_mode' => $target->registration_mode->value,
                'qr_mode' => $target->qr_mode->value,
                'marker_profile' => $markerProfile ? [
                    'code' => $markerProfile->code,
                    'family' => $markerProfile->family,
                    'asset_name' => 'marker.jpg',
                    'marker_hash' => $markerProfile->asset_sha256,
                    'size_mm' => (float) $markerProfile->size_mm,
                    'quiet_zone_mm' => (float) $markerProfile->quiet_zone_mm,
                ] : null,
            ],
            'source_kind' => $isDraft ? $target->source_kind : OmrTemplateAuthoringDraft::SOURCE_HTML,
            'registration_mode' => $target->registration_mode->value,
            'qr_mode' => $target->qr_mode->value,
            'requested_page_count' => $isDraft ? (int) $target->requested_page_count : (int) $target->page_count,
            'is_synthetic' => $isDraft ? $target->is_synthetic : $target->template->is_synthetic,
            'draft_assets' => $draftAssetsManifest,
            'pages' => $pagesManifest,
        ];

        // 6. Create archive
        $zipPath = $this->package->createArchive($files, $manifestData);
        $filename = sprintf('answersheet-%s-v%d.zip', strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $templateCode)), $versionNumber);

        // 7. Audit log
        $this->recordAuditEvent->handle('omr.answer_sheet_bundle_exported', [
            'target_type' => $isDraft ? 'draft' : 'version',
            'target_id' => $target->id,
            'template_code' => $templateCode,
            'version' => $versionNumber,
            'file_count' => count($files) + 1,
        ], (string) $actor->id);

        return [
            'zip_path' => $zipPath,
            'filename' => $filename,
            'template_code' => $templateCode,
        ];
    }

    private function resolveMarkerBytes(string $assetPath): ?string
    {
        if (is_file(base_path($assetPath))) {
            return (string) file_get_contents(base_path($assetPath));
        }

        if (Storage::disk('local')->exists($assetPath)) {
            return (string) Storage::disk('local')->get($assetPath);
        }

        return null;
    }

    /**
     * Rewrite database primary keys inside the authoring JSON into portable logical keys.
     *
     * @param  array<string, mixed>  $authoring
     * @param  array<int, string>  $assetMap
     * @return array<string, mixed>
     */
    private function rewriteAuthoringPayloadToLogicalTokens(array $authoring, array $assetMap): array
    {
        foreach ($authoring as $key => $value) {
            if (is_string($key) && preg_match('/(?:^|_)asset_ids?$/', $key) === 1) {
                if (is_array($value)) {
                    $authoring[$key] = array_map(
                        static fn (mixed $id): mixed => (is_int($id) || is_string($id)) ? ($assetMap[$id] ?? $id) : $id,
                        $value,
                    );
                } elseif (is_int($value) || is_string($value)) {
                    $authoring[$key] = $assetMap[$value] ?? $value;
                }
            } elseif (is_array($value)) {
                $authoring[$key] = $this->rewriteAuthoringPayloadToLogicalTokens($value, $assetMap);
            }
        }

        return $authoring;
    }
}
