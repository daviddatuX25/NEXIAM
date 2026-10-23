<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Printing\DomPdfPrintRenderer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

use function array_filter;
use function array_map;
use function basename;
use function count;
use function file_get_contents;
use function glob;
use function hash;
use function imagealphablending;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagefill;
use function imagepng;
use function imagesavealpha;
use function is_file;
use function json_encode;
use function max;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class CanonicalArtifactMaterializer
{
    public function __construct(
        private readonly CanonicalNativePageRenderer $renderer,
        private readonly DomPdfPrintRenderer $pdfRenderer,
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly NativeManifestDeriver $manifestDeriver,
    ) {}

    public function materialize(
        OmrTemplateAuthoringDraft $draft,
        ?OmrMarkerProfile $profile,
        ?int $candidateVersion = null,
    ): CanonicalArtifactSet {
        if ($draft->registration_mode === OmrRegistrationMode::FiducialAnchored) {
            if ($profile === null || $profile->status === OmrMarkerProfile::STATUS_RETIRED) {
                throw new RuntimeException('marker_profile_missing: anchored materialization requires an available marker profile.');
            }
            $markerPath = base_path((string) $profile->asset_path);
            if (! is_file($markerPath)) {
                throw new RuntimeException('marker_asset_missing: selected marker profile asset is unavailable.');
            }
            $markerBytes = (string) file_get_contents($markerPath);
            if (hash('sha256', $markerBytes) !== (string) $profile->asset_sha256) {
                throw new RuntimeException('marker_asset_integrity_failure: selected marker profile asset hash does not match.');
            }
        } else {
            $markerBytes = null;
        }
        $definition = CanonicalSheetDefinition::fromDraft($draft, $profile, $candidateVersion);
        $pageCount = count($definition->compiledPages);
        $markers = $profile !== null ? $this->markers($definition, $profile) : null;
        $fieldBlocksByPage = $this->fieldBlocksByPage($definition->fieldBlocks, $pageCount);
        $pages = [];
        $templatePages = [];
        foreach (range(1, $pageCount) as $pageNo) {
            $templatePages[] = [
                'page_no' => $pageNo,
                'pageDimensions' => [$definition->frame['width'], $definition->frame['height']],
                'fieldBlocks' => $fieldBlocksByPage[$pageNo] ?? [],
                'preProcessors' => $markers !== null ? [[
                    'name' => 'CropOnMarkers',
                    'options' => [
                        'relativePath' => basename((string) $profile->asset_path),
                        'sheetToMarkerWidthRatio' => (float) config('omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio', 17.0),
                    ],
                ]] : [],
                ...($markers !== null ? ['markers' => $markers] : []),
            ];
        }
        $template = [
            'bubbleDimensions' => $this->bubbleDimensions($definition->fieldBlocks),
            'paper' => [
                'width_mm' => $definition->paper['width_mm'],
                'height_mm' => $definition->paper['height_mm'],
            ],
            'pageDimensions' => [$definition->frame['width'], $definition->frame['height']],
            'visualComponents' => $definition->visualComponents,
            'registration_mode' => $definition->registrationMode->value,
            'qr_mode' => $definition->qrMode->value,
            'compiled_pages' => $definition->compiledPages,
            'definitionHash' => $definition->sourceHash,
            'pages' => $templatePages,
        ];
        $authoring = (array) $draft->authoring_json;
        $settings = (array) data_get($authoring, 'canonical.settings', []);
        $config = $this->config($definition->frame, $settings);
        $manifest = $this->manifest($draft, $profile, $definition, $candidateVersion ?? $definition->candidateVersion);
        $templateBytes = $this->json($template);
        $configBytes = $this->json($config);
        $files = [
            'template.json' => $templateBytes,
            'config.json' => $configBytes,
            ...($profile !== null ? [basename((string) $profile->asset_path) => $markerBytes] : []),
        ];

        $isUploaded = $definition->sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL;
        $uploadedPages = $isUploaded ? $this->uploadedRasterPages($draft, $definition, $pageCount) : null;
        if ($uploadedPages !== null) {
            $pages = $uploadedPages;
        } else {
            $html = $this->renderer->renderHtml($template, $markerBytes);
            $sourcePdfBytes = $this->sourcePdfBytes($draft);
            $previewPdfBytes = $sourcePdfBytes ?? $this->pdfRenderer->render($html, [
                'format' => 'custom',
                'orientation' => 'portrait',
                'width_mm' => $definition->paper['width_mm'],
                'height_mm' => $definition->paper['height_mm'],
            ]);
            $pdfBytes = $previewPdfBytes;
            $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-canonical-materialize-');
            if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
                throw new RuntimeException('canonical_materialization_pdf_write_failed');
            }
            try {
                $rasterPages = $this->rasterizer->rasterize($pdfPath, [
                    'width_mm' => $definition->paper['width_mm'],
                    'height_mm' => $definition->paper['height_mm'],
                    'width_px' => $definition->paper['width_px'],
                    'height_px' => $definition->paper['height_px'],
                    'ppi' => CanonicalSheetDefinition::PPI,
                ]);
                if (count($rasterPages) !== $pageCount) {
                    throw new RuntimeException(sprintf('canonical_materialization_page_count_mismatch: expected %d pages, got %d.', $pageCount, count($rasterPages)));
                }
                foreach ($rasterPages as $raster) {
                    $bytes = (string) file_get_contents($raster['path']);
                    [$bytes, $rasterWidth, $rasterHeight] = $this->normalizeRaster(
                        $bytes,
                        (int) $raster['width'],
                        (int) $raster['height'],
                        $definition->paper['width_px'],
                        $definition->paper['height_px'],
                    );
                    $pages[] = [
                        'page_no' => (int) $raster['page_no'],
                        'bytes' => $bytes,
                        'mime' => 'image/png',
                        'width' => $rasterWidth,
                        'height' => $rasterHeight,
                        'sha256' => hash('sha256', $bytes),
                        'metadata' => [
                            'ppi' => CanonicalSheetDefinition::PPI,
                            'paper_width_mm' => $definition->paper['width_mm'],
                            'paper_height_mm' => $definition->paper['height_mm'],
                            'definition_hash' => $definition->sourceHash,
                        ],
                    ];
                }
                $this->cleanupRasterFiles($rasterPages);
            } finally {
                @unlink($pdfPath);
            }
        }
        $pdfBytes = $isUploaded ? '' : $this->imageBackedPdf($pages, $definition->paper['width_mm'], $definition->paper['height_mm']);

        foreach ($templatePages as $page) {
            $pageTemplate = $template;
            $pageTemplate['pages'] = [$page];
            $referenceName = 'reference-page-'.((int) $page['page_no']).'.svg';
            $files[$referenceName] = $this->renderer->renderReferenceSvg($pageTemplate, $markerBytes);
        }
        $fileHashes = [];
        $orderedFiles = [
            'template.json' => $files['template.json'],
            'config.json' => $files['config.json'],
        ];
        foreach ($files as $name => $bytes) {
            if (str_starts_with($name, 'reference-page-')) {
                $orderedFiles[$name] = $bytes;
            }
        }
        if ($profile !== null) {
            $markerName = basename((string) $profile->asset_path);
            $orderedFiles[$markerName] = $files[$markerName];
        }
        $files = $orderedFiles;
        foreach ($files as $name => $bytes) {
            $fileHashes[$name] = hash('sha256', $bytes);
        }
        $manifest['files'] = $fileHashes;
        $manifestBytes = $this->json($manifest);
        $files['manifest.json'] = $manifestBytes;
        $fileHashes['manifest.json'] = hash('sha256', $manifestBytes);
        [$reviewManifest, $reviewManifestHash] = $this->validateNativeCandidate($draft, $definition, $files);
        $qrReservation = $definition->qrMode === OmrQrMode::Signed
            ? OmrQrReservationResolver::extractReservationBox($definition->overlay)
            : null;
        if ($qrReservation !== null) {
            $reviewManifest['qr_reservation'] = [
                'x' => (int) round($qrReservation['x']),
                'y' => (int) round($qrReservation['y']),
                'width' => (int) round($qrReservation['width']),
                'height' => (int) round($qrReservation['height']),
            ];
            // The inference worker hashes compact insertion-order JSON; keep the
            // persisted review manifest hash on that same canonical boundary.
            $reviewManifestHash = hash('sha256', json_encode($reviewManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }
        $referenceHash = hash('sha256', json_encode(array_filter($fileHashes, static fn (string $name): bool => str_starts_with($name, 'reference-page-'), ARRAY_FILTER_USE_KEY), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $artifactHash = hash('sha256', $this->json([
            'definition_hash' => $definition->sourceHash,
            'files' => $fileHashes,
            'pages' => array_map(static fn (array $page): array => ['page_no' => $page['page_no'], 'sha256' => $page['sha256']], $pages),
            'pdf_sha256' => $isUploaded ? null : hash('sha256', $pdfBytes),
        ]));

        return new CanonicalArtifactSet(
            definition: [
                'schema' => $definition->schema,
                'registration_mode' => $definition->registrationMode->value,
                'qr_mode' => $definition->qrMode->value,
                'compiled_pages' => $definition->compiledPages,
                'draft_code' => $definition->draftCode,
                'candidate_version' => $definition->candidateVersion,
                'paper' => $definition->paper,
                'insets_mm' => $definition->insetsMm,
                'insets_px' => $definition->insetsPx,
                'frame' => $definition->frame,
                'overlay' => $definition->overlay,
                'source_hash' => $definition->sourceHash,
                'marker_profile_id' => $definition->markerProfileId,
                'geometry_identity' => $definition->geometryIdentity,
            ],
            template: $template,
            config: $config,
            manifest: $manifest,
            reviewManifest: $reviewManifest,
            reviewManifestHash: $reviewManifestHash,
            files: $files,
            pages: $pages,
            pdfBytes: $pdfBytes,
            artifactHash: $artifactHash,
            templateHash: hash('sha256', $templateBytes),
            configHash: hash('sha256', $configBytes),
            pdfHash: $isUploaded ? '' : hash('sha256', $pdfBytes),
            referenceHash: $referenceHash,
            markerHash: $markerBytes !== null ? hash('sha256', $markerBytes) : '',
            geometryIdentity: $definition->geometryIdentity,
        );
    }

    /**
     * Rehydrate the immutable candidate bundle already persisted on the draft.
     * Queue workers may run a different renderer image than the web process;
     * rebuilding PDF/raster bytes there can produce a false artifact drift.
     * Current draft geometry is still checked by the caller before use.
     */
    public function loadPersisted(OmrTemplateAuthoringDraft $draft, ?OmrMarkerProfile $profile, bool $includePrintPdf = false): CanonicalArtifactSet
    {
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $persisted = (array) ($canonical['artifact_set'] ?? []);
        $template = is_array($persisted['template'] ?? null) ? $persisted['template'] : [];
        $config = is_array($persisted['config'] ?? null) ? $persisted['config'] : [];
        $manifest = is_array($persisted['manifest'] ?? null) ? $persisted['manifest'] : [];
        $reviewManifest = is_array($persisted['review_manifest'] ?? null) ? $persisted['review_manifest'] : [];
        $artifactHash = (string) ($persisted['artifact_hash'] ?? '');
        $definitionHash = (string) ($persisted['definition_hash'] ?? $template['definitionHash'] ?? '');
        $geometryIdentity = (string) ($persisted['geometry_identity'] ?? $persisted['mapping_hash'] ?? '');
        $configAsset = OmrTemplateAsset::query()
            ->whereKey((int) ($persisted['native_config_asset_id'] ?? 0))
            ->where('authoring_draft_id', $draft->id)
            ->where('kind', OmrTemplateAsset::KIND_NATIVE_CONFIG)
            ->first();
        if ($configAsset === null) {
            throw new RuntimeException('canonical_persisted_config_missing');
        }
        $configBytes = Storage::disk($configAsset->disk)->get($configAsset->path);
        if (! is_string($configBytes)
            || hash('sha256', $configBytes) !== (string) $persisted['config_hash']
            || (string) ($manifest['files']['config.json'] ?? '') !== (string) $persisted['config_hash']) {
            throw new RuntimeException('canonical_persisted_config_integrity_failure');
        }
        $markerAssetId = (int) ($persisted['marker_asset_id'] ?? 0);
        if ($artifactHash === '' || $definitionHash === '' || $geometryIdentity === ''
            || $template === [] || $config === [] || $manifest === [] || $reviewManifest === []) {
            throw new RuntimeException('canonical_persisted_artifact_missing');
        }
        $this->assertPersistedPolicy($draft, $persisted, $template, $manifest, $reviewManifest, $profile);

        $markerAsset = null;
        $markerBytes = null;
        if ($draft->registration_mode === OmrRegistrationMode::FiducialAnchored) {
            $markerAsset = OmrTemplateAsset::query()
                ->whereKey($markerAssetId)
                ->where('authoring_draft_id', $draft->id)
                ->where('kind', OmrTemplateAsset::KIND_MARKER_PROFILE)
                ->first();
            if ($markerAsset === null || $profile === null) {
                throw new RuntimeException('canonical_persisted_marker_missing');
            }
            $markerBytes = Storage::disk($markerAsset->disk)->get($markerAsset->path);
            if (! is_string($markerBytes) || hash('sha256', $markerBytes) !== (string) $markerAsset->sha256
                || hash('sha256', $markerBytes) !== (string) $profile->asset_sha256
                || (string) ($persisted['marker_hash'] ?? '') !== (string) $markerAsset->sha256) {
                throw new RuntimeException('canonical_persisted_marker_integrity_failure');
            }
        }
        $pdfBytes = '';
        if ($includePrintPdf) {
            if (OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL) {
                throw new RuntimeException('uploaded_original_print_forbidden');
            }
            $pdfAssetId = (int) ($persisted['pdf_asset_id'] ?? $canonical['pdf_asset_id'] ?? 0);
            $pdfAsset = OmrTemplateAsset::query()
                ->whereKey($pdfAssetId)
                ->where('authoring_draft_id', $draft->id)
                ->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)
                ->first();
            if ($pdfAsset === null || $pdfAsset->mime !== 'application/pdf') {
                throw new RuntimeException('canonical_persisted_print_asset_missing');
            }

            $pdfBytes = Storage::disk($pdfAsset->disk)->get($pdfAsset->path);
            $pdfHash = is_string($pdfBytes) ? hash('sha256', $pdfBytes) : '';
            if ($pdfBytes === '' || $pdfHash !== (string) $pdfAsset->sha256
                || ((string) ($persisted['pdf_hash'] ?? '') !== '' && $pdfHash !== (string) $persisted['pdf_hash'])) {
                throw new RuntimeException('canonical_persisted_print_asset_integrity_failure');
            }
        }

        $settings = (array) ($canonical['settings'] ?? []);
        $pageDimensions = (array) (($template['pages'][0]['pageDimensions'] ?? $template['pageDimensions'] ?? []));
        $insetsMm = (array) (($authoring['marker']['insets_mm'] ?? []) ?: []);
        $definition = [
            'schema' => 'nexiam.omr.canonical-sheet-definition.v1',
            'registration_mode' => $draft->registration_mode->value,
            'qr_mode' => $draft->qr_mode->value,
            'compiled_pages' => $manifest['compiled_pages'],
            'draft_code' => (string) $draft->code,
            'candidate_version' => (int) ($persisted['candidate_version'] ?? 1),
            'paper' => [
                'width_mm' => (float) ($settings['paper_width_mm'] ?? 210.0),
                'height_mm' => (float) ($settings['paper_height_mm'] ?? 297.0),
            ],
            'insets_mm' => $insetsMm,
            'insets_px' => (array) ($persisted['insets_px'] ?? []),
            'frame' => [
                'width' => (int) ($pageDimensions[0] ?? 0),
                'height' => (int) ($pageDimensions[1] ?? 0),
            ],
            'overlay' => [],
            'source_hash' => $definitionHash,
            'marker_profile_id' => $profile?->id,
            'geometry_identity' => $geometryIdentity,
        ];

        return new CanonicalArtifactSet(
            definition: $definition,
            template: $template,
            config: $config,
            manifest: $manifest,
            reviewManifest: $reviewManifest,
            reviewManifestHash: (string) ($persisted['review_manifest_hash'] ?? ''),
            files: [
                'config.json' => $configBytes,
                ...($profile !== null ? [basename((string) $profile->asset_path) => $markerBytes] : []),
            ],
            pages: [],
            pdfBytes: $pdfBytes,
            artifactHash: $artifactHash,
            templateHash: (string) ($persisted['template_hash'] ?? ''),
            configHash: (string) ($persisted['config_hash'] ?? ''),
            pdfHash: (string) ($persisted['pdf_hash'] ?? ''),
            referenceHash: (string) ($persisted['reference_hash'] ?? ''),
            markerHash: $markerAsset?->sha256 ?? '',
            geometryIdentity: $geometryIdentity,
        );
    }

    /**
     * @param  array<string, mixed>  $persisted
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $reviewManifest
     */
    private function assertPersistedPolicy(
        OmrTemplateAuthoringDraft $draft,
        array $persisted,
        array $template,
        array $manifest,
        array $reviewManifest,
        ?OmrMarkerProfile $profile,
    ): void {
        $pages = array_map(
            static fn (array $page): array => ['page_no' => (int) ($page['page_no'] ?? 0)],
            array_values((array) ($template['pages'] ?? [])),
        );
        $expectedPages = array_map(
            static fn (int $pageNo): array => ['page_no' => $pageNo],
            range(1, (int) $draft->requested_page_count),
        );
        if ($pages !== $expectedPages
            || ($manifest['compiled_pages'] ?? null) !== $pages
            || ($persisted['compiled_pages'] ?? null) !== $pages
            || ($template['compiled_pages'] ?? null) !== $pages
            || ($reviewManifest['compiled_pages'] ?? null) !== $pages) {
            throw new RuntimeException('canonical_persisted_page_mismatch');
        }
        foreach ([$persisted, $template, $manifest, $reviewManifest] as $representation) {
            if (($representation['registration_mode'] ?? null) !== $draft->registration_mode->value
                || ($representation['qr_mode'] ?? null) !== $draft->qr_mode->value) {
                throw new RuntimeException('canonical_persisted_mode_mismatch');
            }
        }
        if ((string) ($persisted['template_hash'] ?? '') !== hash('sha256', $this->json($template))
            || (string) ($manifest['files']['template.json'] ?? '') !== (string) $persisted['template_hash']
            || (string) ($manifest['files']['config.json'] ?? '') !== (string) $persisted['config_hash']
            || (string) ($persisted['review_manifest_hash'] ?? '') !== hash('sha256', json_encode($reviewManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))) {
            throw new RuntimeException('canonical_persisted_integrity_failure');
        }
        if ($draft->registration_mode === OmrRegistrationMode::DocumentBound) {
            foreach ($template['pages'] as $page) {
                if (isset($page['markers']) || ($page['preProcessors'] ?? []) !== []) {
                    throw new RuntimeException('canonical_persisted_marker_mismatch');
                }
            }
        }
        if ($draft->registration_mode === OmrRegistrationMode::DocumentBound
            && ($profile !== null || (int) ($persisted['marker_asset_id'] ?? 0) > 0
                || isset($manifest['marker_profile']))) {
            throw new RuntimeException('canonical_persisted_marker_mismatch');
        }
        if ($draft->qr_mode === OmrQrMode::Disabled && isset($reviewManifest['qr_reservation'])) {
            throw new RuntimeException('canonical_persisted_qr_mismatch');
        }
    }

    private function sourcePdfBytes(OmrTemplateAuthoringDraft $draft): ?string
    {
        if ($draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML) {
            return null;
        }
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $assetId = (int) (($canonical['source_pdf_asset_id'] ?? null) ?: ($canonical['pdf_asset_id'] ?? 0));
        if ($assetId <= 0) {
            return null;
        }
        $asset = $draft->assets()->whereKey($assetId)->first();
        if ($asset === null || $asset->mime !== 'application/pdf') {
            return null;
        }
        $bytes = Storage::disk($asset->disk)->get($asset->path);
        if (! is_string($bytes) || hash('sha256', $bytes) !== $asset->sha256) {
            throw new RuntimeException('canonical_source_pdf_integrity_failure');
        }

        return $bytes;
    }

    /**
     * @return list<array{page_no:int,bytes:string,mime:string,width:int,height:int,sha256:string,metadata:array<string,mixed>}>|null
     */
    private function uploadedRasterPages(OmrTemplateAuthoringDraft $draft, CanonicalSheetDefinition $definition, int $pageCount): ?array
    {
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $candidates = array_values((array) ($canonical['raster_assets'] ?? []));
        if (count($candidates) !== $pageCount) {
            $candidates = array_values((array) ($authoring['pages'] ?? []));
        }
        if (count($candidates) !== $pageCount) {
            return null;
        }

        $pages = [];
        foreach ($candidates as $index => $entry) {
            if (! is_array($entry)) {
                return null;
            }
            $pageNo = (int) ($entry['page_no'] ?? ($index + 1));
            $assetId = (int) ($entry['asset_id'] ?? 0);
            if ($pageNo !== $index + 1 || $assetId <= 0) {
                return null;
            }
            $asset = $draft->assets()->whereKey($assetId)->first();
            if ($asset === null || ! str_starts_with((string) $asset->mime, 'image/')) {
                return null;
            }
            $bytes = Storage::disk($asset->disk)->get($asset->path);
            if (! is_string($bytes) || $bytes === '' || hash('sha256', $bytes) !== (string) $asset->sha256) {
                throw new RuntimeException('canonical_source_raster_integrity_failure');
            }
            $size = @getimagesizefromstring($bytes);
            if (! is_array($size) || (int) ($size[0] ?? 0) <= 0 || (int) ($size[1] ?? 0) <= 0) {
                throw new RuntimeException('canonical_materialization_raster_invalid');
            }
            [$normalizedBytes, $rasterWidth, $rasterHeight] = $this->normalizeRaster(
                $bytes,
                (int) $size[0],
                (int) $size[1],
                $definition->paper['width_px'],
                $definition->paper['height_px'],
            );
            $pages[] = [
                'page_no' => $pageNo,
                'bytes' => $normalizedBytes,
                'mime' => 'image/png',
                'width' => $rasterWidth,
                'height' => $rasterHeight,
                'sha256' => hash('sha256', $normalizedBytes),
                'metadata' => [
                    'ppi' => CanonicalSheetDefinition::PPI,
                    'paper_width_mm' => $definition->paper['width_mm'],
                    'paper_height_mm' => $definition->paper['height_mm'],
                    'definition_hash' => $definition->sourceHash,
                ],
            ];
        }

        return $pages;
    }

    /** @param list<array{page_no:int,bytes:string}> $pages */
    private function imageBackedPdf(array $pages, float $widthMm, float $heightMm): string
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->SetAutoPageBreak(false);
        $temporary = [];
        try {
            foreach ($pages as $page) {
                $path = tempnam(sys_get_temp_dir(), 'nexiam-canonical-pdf-');
                if ($path === false || file_put_contents($path, $page['bytes']) === false) {
                    throw new RuntimeException('canonical_materialization_pdf_image_write_failed');
                }
                $temporary[] = $path;
                $pdf->AddPage('P', [$widthMm, $heightMm]);
                $pdf->Image($path, 0, 0, $widthMm, $heightMm, 'PNG');
            }

            $pdfBytes = (string) $pdf->Output('S');

            return (string) preg_replace('/\/CreationDate \(D:[^)]+\)/', "/CreationDate (D:20000101000000+00'00')", $pdfBytes);
        } finally {
            foreach ($temporary as $path) {
                @unlink($path);
            }
        }
    }

    /** @return array{0:string,1:int,2:int} */
    private function normalizeRaster(string $bytes, int $width, int $height, int $targetWidth, int $targetHeight): array
    {
        if ($width === $targetWidth && $height === $targetHeight) {
            return [$bytes, $width, $height];
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('canonical_materialization_raster_invalid');
        }
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagesavealpha($canvas, false);
        ob_start();
        imagepng($canvas, null, 6);
        $normalized = (string) ob_get_clean();

        return [$normalized, $targetWidth, $targetHeight];
    }

    /** @param array<string,string> $files @return array{0:array<string,mixed>,1:string} */
    private function validateNativeCandidate(OmrTemplateAuthoringDraft $draft, CanonicalSheetDefinition $definition, array $files): array
    {
        $directory = sys_get_temp_dir().'/nexiam-omr-candidate-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('canonical_candidate_temp_unavailable');
        }
        try {
            foreach ($files as $name => $bytes) {
                if (file_put_contents($directory.'/'.$name, $bytes) === false) {
                    throw new RuntimeException("canonical_candidate_write_failed: {$name}");
                }
            }
            $bundle = NativeBundle::loadFromDirectory($directory, (string) $draft->code, $definition->candidateVersion);
            $derived = $this->manifestDeriver->derive($bundle);

            return [$derived['manifest'], $derived['hash']];
        } finally {
            foreach (glob($directory.'/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    /** @param array<string,mixed> $fieldBlocks @return array<int,array<string,mixed>> */
    private function fieldBlocksByPage(array $fieldBlocks, int $pageCount): array
    {
        $pages = array_fill(1, $pageCount, []);
        foreach ($fieldBlocks as $name => $block) {
            if (! is_array($block)) {
                continue;
            }
            $pageNo = (int) ($block['page_no'] ?? 1);
            if ($pageNo < 1 || $pageNo > $pageCount) {
                throw new RuntimeException(sprintf('canonical_definition_page_invalid: field block %s references page %d outside %d pages.', (string) $name, $pageNo, $pageCount));
            }
            $pages[$pageNo][(string) $name] = $block;
        }

        return $pages;
    }

    /** @param array<string,mixed> $definition */
    private function markers(CanonicalSheetDefinition $definition, OmrMarkerProfile $profile): array
    {
        $size = OmrPageDimensionMath::pixelsFromMillimetres($profile->materializationSizeMm());
        $quiet = OmrPageDimensionMath::pixelsFromMillimetres($profile->materializationQuietZoneMm());
        $centers = [
            [0, 0],
            [$definition->frame['width'], 0],
            [0, $definition->frame['height']],
            [$definition->frame['width'], $definition->frame['height']],
        ];

        return [
            'family' => (string) $profile->family,
            'asset_name' => basename((string) $profile->asset_path),
            'size' => $size,
            'quiet_zone' => $quiet,
            'geometry_status' => $profile->usesProvisionalGeometry() ? 'provisional' : 'qualified',
            'inset' => max($definition->insetsPx),
            'insets_mm' => $definition->insetsMm,
            'insets_px' => $definition->insetsPx,
            'paper' => ['width_mm' => $definition->paper['width_mm'], 'height_mm' => $definition->paper['height_mm']],
            'centers' => $centers,
            'quadrants' => ['top_left', 'top_right', 'bottom_left', 'bottom_right'],
        ];
    }

    /** @param array<string,mixed> $frame @param array<string,mixed> $settings */
    private function config(array $frame, array $settings): array
    {
        $smartSnapSearchMm = isset($settings['smart_snap_search_mm']) && is_numeric($settings['smart_snap_search_mm'])
            ? round(max(2.0, min(12.0, (float) $settings['smart_snap_search_mm'])), 2)
            : 5.0;

        return [
            'dimensions' => [
                'display_width' => $frame['width'],
                'display_height' => $frame['height'],
                'processing_width' => $frame['width'],
                'processing_height' => $frame['height'],
            ],
            'alignment_params' => [
                'auto_align' => false,
                'match_col' => 5,
                'max_steps' => 30,
                'smart_snap_search_mm' => $smartSnapSearchMm,
                'stride' => 8,
                'thickness' => 2,
            ],
            'threshold_params' => ['GAMMA_LOW' => 0.9, 'MIN_GAP' => 30, 'MIN_JUMP' => 50, 'PAGE_TYPE' => 'NORMAL', 'GLOBAL_PAGE_THRESHOLD_WHITE' => 200, 'GLOBAL_PAGE_THRESHOLD_BLACK' => 100],
            'outputs' => ['show_image_level' => 0, 'save_image_level' => 0, 'save_detections' => false],
        ];
    }

    private function manifest(OmrTemplateAuthoringDraft $draft, ?OmrMarkerProfile $profile, CanonicalSheetDefinition $definition, int $version): array
    {
        return [
            'bundle_version' => 'nexiam.omr.native-bundle.v1',
            'template_code' => (string) $draft->code,
            'template_version' => $version,
            'synthetic' => false,
            'identity_policy' => 'optional',
            'canonical_definition_schema' => $definition->schema,
            'canonical_definition_hash' => $definition->sourceHash,
            'registration_mode' => $definition->registrationMode->value,
            'qr_mode' => $definition->qrMode->value,
            'compiled_pages' => $definition->compiledPages,
            ...($profile !== null ? ['marker_profile' => [
                'id' => $profile->id,
                'code' => $profile->code,
                'version' => $profile->version,
                'asset_sha256' => $profile->asset_sha256,
                'status' => $profile->status,
                'geometry_status' => $profile->usesProvisionalGeometry() ? 'provisional' : 'qualified',
                'size_mm' => $profile->materializationSizeMm(),
                'quiet_zone_mm' => $profile->materializationQuietZoneMm(),
            ]] : []),
            'engine' => [
                'name' => (string) config('omr.omrchecker.engine_name', 'OMRChecker'),
                'commit' => (string) config('omr.omrchecker.engine_commit', ''),
                'patch_set' => (string) config('omr.omrchecker.patch_identity', ''),
            ],
        ];
    }

    /** @param array<string,mixed> $blocks @return array{0:int,1:int} */
    private function bubbleDimensions(array $blocks): array
    {
        foreach ($blocks as $block) {
            if (is_array($block) && is_array($block['bubbleDimensions'] ?? null)) {
                return [(int) ($block['bubbleDimensions'][0] ?? 28), (int) ($block['bubbleDimensions'][1] ?? 28)];
            }
        }

        return [OmrPageDimensionMath::pixelsFromMillimetres(4.5), OmrPageDimensionMath::pixelsFromMillimetres(4.5)];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** @param list<array<string,mixed>> $rasterPages */
    private function cleanupRasterFiles(array $rasterPages): void
    {
        $directories = [];
        foreach ($rasterPages as $page) {
            $path = $page['path'] ?? null;
            if (! is_string($path)) {
                continue;
            }
            $directories[dirname($path)] = true;
            @unlink($path);
        }
        foreach (array_keys($directories) as $directory) {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
