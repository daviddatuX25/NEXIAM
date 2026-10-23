<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use Illuminate\Support\Str;
use RuntimeException;

use function abs;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function hash_equals;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function min;
use function preg_match;
use function str_starts_with;

/**
 * Shared builder for the read-only worker diagnostics (builder Test capture
 * and capture preflight): turns an immutable template version into the
 * authenticated detection-envelope request using the bundle as the geometry
 * authority — no client coordinates, no free-form template id, no booklet.
 */
final class OmrDiagnosticRequestBuilder
{
    /**
     * @throws RuntimeException when the pinned mode, target, bundle, or review manifest is incomplete
     */
    public function build(
        OmrAnswerSheetTemplateVersion $version,
        string $imageBytes,
        string $imageMime,
        int $pageNo = 1,
    ): OmrDetectionRequest {
        $registrationMode = $version->registration_mode;
        if (! $registrationMode instanceof OmrRegistrationMode
            || (int) $version->id < 1
            || $pageNo < 1
            || $pageNo > min(4, $version->pageCount())) {
            throw new RuntimeException('The immutable diagnostic target identity is invalid.');
        }

        $bundle = NativeBundle::loadForVersion($version);
        if (! hash_equals((string) $version->config_hash, (string) ($bundle->fileHashes['config.json'] ?? ''))) {
            throw new RuntimeException('The immutable diagnostic config hash does not match its config.json bytes.');
        }
        $manifestMode = $bundle->manifest['registration_mode'] ?? null;
        if (($manifestMode !== null && $manifestMode !== $registrationMode->value)
            || ($manifestMode === null && $registrationMode !== OmrRegistrationMode::FiducialAnchored)) {
            throw new RuntimeException('The immutable version registration mode does not match its native bundle.');
        }

        $reviewManifest = is_array($version->review_manifest_json) ? $version->review_manifest_json : null;
        if ($reviewManifest === null) {
            throw new RuntimeException('The template version has no review manifest to derive diagnostic geometry from.');
        }

        $templateJson = is_array($version->native_bundle_json) ? ($version->native_bundle_json['template'] ?? null) : null;
        $marker = is_array($templateJson) ? OmrCapturePolicy::markerMetadata($templateJson) : null;
        $nativeTemplate = $bundle->pageTemplate($pageNo);
        if ($registrationMode === OmrRegistrationMode::FiducialAnchored && $marker === null) {
            throw new RuntimeException('The anchored diagnostic requires its immutable marker profile and assets.');
        }
        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            if ($marker !== null || ! empty($nativeTemplate['markers']) || $this->hasMutatingCrop($nativeTemplate)) {
                throw new RuntimeException('The document-bound diagnostic bundle contains marker or geometry-warping state.');
            }
        }

        $workerIdentity = [
            'name' => (string) config('omr.omrchecker.engine_name', ''),
            'commit' => (string) config('omr.omrchecker.engine_commit', ''),
            'patch_identity' => (string) config('omr.omrchecker.patch_identity', ''),
        ];
        if (in_array('', array_map('trim', $workerIdentity), true)) {
            throw new RuntimeException('The configured OMR worker identity is incomplete.');
        }

        $sourceImage = OmrSourceImage::fromBytes($imageBytes);

        return new OmrDetectionRequest(
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: 'omr-diagnostic-'.(string) Str::uuid(),
            correlationId: 'omr:diagnostic:'.$version->id,
            imageBytes: $imageBytes,
            inputHash: $sourceImage->inputHash,
            imageMime: $imageMime,
            imageWidth: $sourceImage->width,
            imageHeight: $sourceImage->height,
            areas: [],
            items: $this->diagnosticItems($reviewManifest, $pageNo),
            identityRegions: [],
            nativeBundleHash: (string) $version->native_bundle_hash,
            manifestHash: (string) $version->review_manifest_hash,
            configHash: (string) $bundle->fileHashes['config.json'],
            bookletHash: '',
            nativeTemplate: $nativeTemplate,
            nativeConfigJson: $bundle->assetBytes('config.json'),
            nativeBundleMetadata: [
                'files' => $bundle->fileHashes,
                'engine' => $bundle->manifest['engine'] ?? null,
                'bundle_version' => $bundle->manifest['bundle_version'] ?? null,
                'mapping_hash' => $version->authoring_manifest['mapping_hash'] ?? $version->review_manifest_hash,
                'rectification_marker_insets_px' => $marker !== null ? $this->rectificationMarkerInsets($marker) : null,
            ],
            reviewManifest: $reviewManifest,
            engineName: $workerIdentity['name'],
            engineCommit: $workerIdentity['commit'],
            patchIdentity: $workerIdentity['patch_identity'],
            expectedFieldLabels: [],
            expectedOptionCodes: [],
            registrationMode: $registrationMode,
            templateVersionId: (int) $version->id,
            candidateTargetId: null,
            sourceImage: $sourceImage,
            pageNo: $pageNo,
            providerDeadline: (float) config('omr.provider.deadline_seconds', 30),
            nativeAssets: $marker !== null ? [$marker['asset_name'] => $bundle->assetBytes($marker['asset_name'])] : [],
        );
    }

    /**
     * Build the same signed worker request for a materialized draft candidate.
     */
    public function buildCandidate(
        CanonicalArtifactSet $artifactSet,
        string $imageBytes,
        string $imageMime,
        int $pageNo = 1,
    ): OmrDetectionRequest {
        $modeValue = $artifactSet->template['registration_mode'] ?? null;
        $registrationMode = is_string($modeValue) ? OmrRegistrationMode::tryFrom($modeValue) : null;
        if (! $registrationMode instanceof OmrRegistrationMode
            || ($artifactSet->definition['registration_mode'] ?? null) !== $modeValue
            || ($artifactSet->manifest['registration_mode'] ?? null) !== $modeValue
            || preg_match('/^[a-f0-9]{64}$/', $artifactSet->artifactHash) !== 1) {
            throw new RuntimeException('The candidate diagnostic target identity or registration mode is invalid.');
        }

        $page = null;
        foreach ((array) ($artifactSet->template['pages'] ?? []) as $candidatePage) {
            if (is_array($candidatePage) && (int) ($candidatePage['page_no'] ?? 0) === $pageNo) {
                $page = $candidatePage;
                break;
            }
        }
        if (! is_array($page) || $pageNo < 1 || $pageNo > 4) {
            throw new RuntimeException("The candidate bundle has no page {$pageNo}.");
        }
        $markers = (array) ($page['markers'] ?? []);
        $assetName = (string) ($markers['asset_name'] ?? '');
        $nativeTemplate = [
            'pageDimensions' => $page['pageDimensions'] ?? $artifactSet->template['pageDimensions'] ?? null,
            'bubbleDimensions' => $artifactSet->template['bubbleDimensions'] ?? null,
            'customLabels' => new \stdClass,
            'fieldBlocks' => NativeBundle::workerFieldBlocks((array) ($page['fieldBlocks'] ?? [])),
            'preProcessors' => $page['preProcessors'] ?? [],
        ];
        if ($registrationMode === OmrRegistrationMode::FiducialAnchored) {
            if ($assetName === '' || ! isset($artifactSet->files[$assetName])) {
                throw new RuntimeException('The anchored candidate bundle has no declared marker asset.');
            }
        } elseif ($markers !== [] || $assetName !== '' || $this->hasMutatingCrop($nativeTemplate)) {
            throw new RuntimeException('The document-bound candidate contains marker or geometry-warping state.');
        }

        $reviewManifest = $artifactSet->reviewManifest;
        $sourceImage = OmrSourceImage::fromBytes($imageBytes);

        return new OmrDetectionRequest(
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: 'omr-candidate-'.(string) Str::uuid(),
            correlationId: 'omr:candidate:'.$artifactSet->artifactHash,
            imageBytes: $imageBytes,
            inputHash: $sourceImage->inputHash,
            imageMime: $imageMime,
            imageWidth: $sourceImage->width,
            imageHeight: $sourceImage->height,
            areas: [],
            items: $this->diagnosticItems($reviewManifest, $pageNo),
            identityRegions: [],
            nativeBundleHash: (string) ($reviewManifest['bundle_hash'] ?? ''),
            manifestHash: $artifactSet->reviewManifestHash,
            configHash: $artifactSet->configHash,
            bookletHash: '',
            nativeTemplate: $nativeTemplate,
            nativeConfigJson: $artifactSet->file('config.json'),
            nativeBundleMetadata: [
                'files' => $reviewManifest['file_hashes'] ?? [],
                'engine' => $artifactSet->manifest['engine'] ?? null,
                'bundle_version' => $artifactSet->manifest['bundle_version'] ?? null,
                'mapping_hash' => $artifactSet->definition['source_hash'] ?? $artifactSet->reviewManifestHash,
                'rectification_marker_insets_px' => $registrationMode === OmrRegistrationMode::FiducialAnchored
                    ? $this->rectificationMarkerInsets($markers)
                    : null,
            ],
            reviewManifest: $reviewManifest,
            engineName: (string) ($artifactSet->manifest['engine']['name'] ?? config('omr.omrchecker.engine_name', 'OMRChecker')),
            engineCommit: isset($artifactSet->manifest['engine']['commit']) ? (string) $artifactSet->manifest['engine']['commit'] : null,
            patchIdentity: (string) ($artifactSet->manifest['engine']['patch_set'] ?? config('omr.omrchecker.patch_identity', '')),
            expectedFieldLabels: [],
            expectedOptionCodes: [],
            registrationMode: $registrationMode,
            templateVersionId: null,
            candidateTargetId: 'candidate:'.$artifactSet->artifactHash,
            sourceImage: $sourceImage,
            pageNo: $pageNo,
            providerDeadline: (float) config('omr.provider.deadline_seconds', 30),
            nativeAssets: $registrationMode === OmrRegistrationMode::FiducialAnchored
                ? [$assetName => $artifactSet->files[$assetName]]
                : [],
        );
    }

    /**
     * @param  array<string, mixed>  $pageTemplate
     * @param  list<array<string, mixed>>  $shifts
     * @return list<string>
     */
    public function unsafeBlocks(array $pageTemplate, array $shifts): array
    {
        $blocks = is_array($pageTemplate['fieldBlocks'] ?? null) ? $pageTemplate['fieldBlocks'] : [];
        $unsafe = [];
        foreach ($shifts as $shift) {
            if (! is_array($shift) || ! is_string($shift['block'] ?? null)
                || ! is_int($shift['x'] ?? null) || ! is_int($shift['y'] ?? null)) {
                continue;
            }
            $block = $blocks[$shift['block']] ?? null;
            if (! is_array($block)) {
                continue;
            }
            [$pitchX, $pitchY] = $this->optionPitch($block);
            if (($pitchX !== null && abs($shift['x']) >= $pitchX * 0.5)
                || ($pitchY !== null && abs($shift['y']) >= $pitchY * 0.5)) {
                $unsafe[] = (string) $shift['block'];
            }
        }

        return array_values(array_unique($unsafe));
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{0: ?float, 1: ?float}
     */
    public function optionPitch(array $block): array
    {
        $bubblesGap = (float) ($block['bubblesGap'] ?? 0);
        $labelsGap = (float) ($block['labelsGap'] ?? 0);
        $direction = (string) ($block['direction'] ?? '');
        $fieldType = (string) ($block['fieldType'] ?? '');

        if ($fieldType === 'QTYPE_INT' || $direction === 'vertical') {
            return [$labelsGap > 0 ? $labelsGap : null, $bubblesGap > 0 ? $bubblesGap : null];
        }
        if ($direction === 'horizontal') {
            return [$bubblesGap > 0 ? $bubblesGap : null, $labelsGap > 0 ? $labelsGap : null];
        }

        $gaps = array_values(array_filter(
            [$bubblesGap, $labelsGap],
            static fn (float $gap): bool => $gap > 0,
        ));
        $pitch = $gaps === [] ? null : min($gaps);

        return [$pitch, $pitch];
    }

    /**
     * Derive the worker's expected items from the review manifest fields
     * (label/option/region) — the same projection the detection envelope uses,
     * read-only and version-agnostic (drafts and activated versions alike).
     *
     * @return list<OmrItemGeometry>
     */
    public function diagnosticItems(array $manifest, int $pageNo): array
    {
        $byLabel = [];
        foreach ((array) ($manifest['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null)
                || ! is_array($field['region'] ?? null) || (int) ($field['page'] ?? 1) !== $pageNo
                || str_starts_with((string) $field['label'], 'APPLICANT_NUMBER_')) {
                continue;
            }
            $byLabel[(string) $field['label']][] = $field;
        }

        $items = [];
        foreach ($byLabel as $label => $fields) {
            if (preg_match('/^([A-Z0-9_]+)_Q_(\d+)$/', (string) $label, $matches) !== 1) {
                continue;
            }
            $regions = array_map(static fn (array $field): array => $field['region'], $fields);
            $items[] = new OmrItemGeometry(
                position: count($items) + 1,
                areaCode: $matches[1],
                questionNo: (int) $matches[2],
                nativeLabel: (string) $label,
                region: OmrRegion::fromArray($this->union($regions)),
                options: array_map(
                    static fn (array $field, int $index): OmrOptionGeometry => new OmrOptionGeometry(
                        code: (string) $field['option'],
                        region: OmrRegion::fromArray($field['region']),
                        displayOrder: $index + 1,
                    ),
                    $fields,
                    array_keys($fields),
                ),
            );
        }

        if ($items === []) {
            throw new RuntimeException('The review manifest contains no scorable geometry for the diagnostic.');
        }

        return $items;
    }

    /** @param list<array{x: float, y: float, w: float, h: float}> $regions @return array{x: float, y: float, w: float, h: float} */
    public function union(array $regions): array
    {
        return [
            'x' => min(array_map(static fn (array $region): float => (float) $region['x'], $regions)),
            'y' => min(array_map(static fn (array $region): float => (float) $region['y'], $regions)),
            'w' => max(array_map(static fn (array $region): float => (float) $region['x'] + $region['w'], $regions)) - min(array_map(static fn (array $region): float => (float) $region['x'], $regions)),
            'h' => max(array_map(static fn (array $region): float => (float) $region['y'] + $region['h'], $regions)) - min(array_map(static fn (array $region): float => (float) $region['y'], $regions)),
        ];
    }

    /**
     * Rebind only the image portion of an existing pinned request. Geometry,
     * hashes, engine identity, and expected observation labels remain intact.
     */
    public function rebindImage(
        OmrDetectionRequest $request,
        string $imageBytes,
        string $imageMime,
        int $pageNo,
        ?OmrClientQuad $clientQuad = null,
    ): OmrDetectionRequest {
        $sourceImage = OmrSourceImage::fromBytes($imageBytes);

        return new OmrDetectionRequest(
            envelopeVersion: $request->envelopeVersion,
            runId: $request->runId,
            correlationId: $request->correlationId,
            imageBytes: $imageBytes,
            inputHash: $sourceImage->inputHash,
            imageMime: $imageMime,
            imageWidth: $sourceImage->width,
            imageHeight: $sourceImage->height,
            areas: $request->areas,
            items: $request->items,
            identityRegions: $request->identityRegions,
            nativeBundleHash: $request->nativeBundleHash,
            manifestHash: $request->manifestHash,
            configHash: $request->configHash,
            nativeTemplate: $request->nativeTemplate,
            bookletHash: $request->bookletHash,
            nativeConfigJson: $request->nativeConfigJson,
            nativeBundleMetadata: $request->nativeBundleMetadata,
            reviewManifest: $request->reviewManifest,
            engineName: $request->engineName,
            engineCommit: $request->engineCommit,
            patchIdentity: $request->patchIdentity,
            expectedFieldLabels: $request->expectedFieldLabels,
            expectedOptionCodes: $request->expectedOptionCodes,
            registrationMode: $request->registrationMode,
            templateVersionId: $request->templateVersionId,
            candidateTargetId: $request->candidateTargetId,
            sourceImage: $sourceImage,
            pageNo: $pageNo,
            providerDeadline: $request->providerDeadline,
            clientQuad: $clientQuad,
            nativeAssets: $request->nativeAssets,
        );
    }

    /** @param array<string, mixed> $pageTemplate */
    private function hasMutatingCrop(array $pageTemplate): bool
    {
        foreach ((array) ($pageTemplate['preProcessors'] ?? []) as $processor) {
            $name = is_string($processor) ? $processor : (is_array($processor) ? ($processor['name'] ?? null) : null);
            if (in_array($name, ['CropPage', 'CropOnMarkers'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $markers
     * @return array{top: int, right: int, bottom: int, left: int}|null
     */
    private function rectificationMarkerInsets(array $markers): ?array
    {
        $insets = $markers['insets_px'] ?? null;
        if (is_array($insets)
            && min(array_map(static fn (string $side): int => (int) ($insets[$side] ?? 0), ['top', 'right', 'bottom', 'left'])) > 0) {
            return [
                'top' => (int) $insets['top'],
                'right' => (int) $insets['right'],
                'bottom' => (int) $insets['bottom'],
                'left' => (int) $insets['left'],
            ];
        }

        $inset = (int) ($markers['inset'] ?? 0);
        if ($inset <= 0) {
            return null;
        }

        return ['top' => $inset, 'right' => $inset, 'bottom' => $inset, 'left' => $inset];
    }

    /** @return array{0: ?int, 1: ?int} */
    public function imageDimensions(string $bytes): array
    {
        $info = getimagesizefromstring($bytes);

        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }
}
