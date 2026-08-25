<?php

namespace App\Assessment\Omr;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use Illuminate\Support\Str;
use RuntimeException;

use function abs;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function hash;
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
     * @throws RuntimeException when the version is not marker-bearing or its
     *                          bundle/review manifest is incomplete
     */
    public function build(
        OmrAnswerSheetTemplateVersion $version,
        string $imageBytes,
        string $imageMime,
    ): OmrDetectionRequest {
        $templateJson = is_array($version->native_bundle_json) ? ($version->native_bundle_json['template'] ?? null) : null;
        $marker = is_array($templateJson) ? OmrCapturePolicy::markerMetadata($templateJson) : null;
        if ($marker === null) {
            throw new RuntimeException('The diagnostic requires a marker-bearing template version; scan (CropPage) profiles are not supported.');
        }

        [$width, $height] = $this->imageDimensions($imageBytes);
        if ($width === null || $height === null) {
            throw new RuntimeException('The uploaded image cannot be decoded.');
        }

        $bundle = NativeBundle::load((string) $version->template->code, (int) $version->version);
        $reviewManifest = is_array($version->review_manifest_json) ? $version->review_manifest_json : null;
        if ($reviewManifest === null) {
            throw new RuntimeException('The template version has no review manifest to derive diagnostic geometry from.');
        }

        return new OmrDetectionRequest(
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: 'omr-diagnostic-'.(string) Str::uuid(),
            correlationId: 'omr:diagnostic:'.$version->id,
            imageBytes: $imageBytes,
            inputHash: hash('sha256', $imageBytes),
            imageMime: $imageMime,
            imageWidth: $width,
            imageHeight: $height,
            areas: [],
            items: $this->diagnosticItems($reviewManifest, 1),
            identityRegions: [],
            nativeBundleHash: (string) $version->native_bundle_hash,
            manifestHash: (string) $version->review_manifest_hash,
            configHash: (string) $version->config_hash,
            bookletHash: '',
            nativeTemplate: $bundle->pageTemplate(1),
            nativeConfig: $bundle->config,
            nativeBundleMetadata: [
                'files' => $bundle->fileHashes,
                'engine' => $bundle->manifest['engine'] ?? null,
                'bundle_version' => $bundle->manifest['bundle_version'] ?? null,
                'mapping_hash' => $version->authoring_manifest['mapping_hash'] ?? $version->review_manifest_hash,
            ],
            reviewManifest: $reviewManifest,
            engineName: (string) $version->engine_name,
            engineCommit: $version->engine_commit !== null ? (string) $version->engine_commit : null,
            patchIdentity: (string) $version->patch_identity,
            expectedFieldLabels: [],
            expectedOptionCodes: [],
            providerDeadline: (float) config('omr.provider.deadline_seconds', 30),
            pageNo: 1,
            nativeAssets: [$marker['asset_name'] => $bundle->assetBytes($marker['asset_name'])],
        );
    }

    /**
     * Build the same signed worker request for a materialized draft candidate.
     */
    public function buildCandidate(
        \App\Assessment\Omr\Canonical\CanonicalArtifactSet $artifactSet,
        string $imageBytes,
        string $imageMime,
        int $pageNo = 1,
    ): OmrDetectionRequest {
        [$width, $height] = $this->imageDimensions($imageBytes);
        if ($width === null || $height === null) {
            throw new RuntimeException('The candidate preview image cannot be decoded.');
        }
        $page = null;
        foreach ((array) ($artifactSet->template['pages'] ?? []) as $candidatePage) {
            if (is_array($candidatePage) && (int) ($candidatePage['page_no'] ?? 0) === $pageNo) {
                $page = $candidatePage;
                break;
            }
        }
        if (! is_array($page)) {
            throw new RuntimeException("The candidate bundle has no page {$pageNo}.");
        }
        $markers = (array) ($page['markers'] ?? []);
        $assetName = (string) ($markers['asset_name'] ?? '');
        if ($assetName === '' || ! isset($artifactSet->files[$assetName])) {
            throw new RuntimeException('The candidate bundle has no declared marker asset.');
        }
        $nativeTemplate = [
            'pageDimensions' => $page['pageDimensions'] ?? $artifactSet->template['pageDimensions'] ?? null,
            'bubbleDimensions' => $artifactSet->template['bubbleDimensions'] ?? null,
            'customLabels' => new \stdClass,
            'fieldBlocks' => NativeBundle::workerFieldBlocks((array) ($page['fieldBlocks'] ?? [])),
            'preProcessors' => $page['preProcessors'] ?? [],
        ];
        $reviewManifest = $artifactSet->reviewManifest;

        return new OmrDetectionRequest(
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: 'omr-candidate-'.(string) Str::uuid(),
            correlationId: 'omr:candidate:'.$artifactSet->artifactHash,
            imageBytes: $imageBytes,
            inputHash: hash('sha256', $imageBytes),
            imageMime: $imageMime,
            imageWidth: $width,
            imageHeight: $height,
            areas: [],
            items: $this->diagnosticItems($reviewManifest, $pageNo),
            identityRegions: [],
            nativeBundleHash: (string) ($reviewManifest['bundle_hash'] ?? ''),
            manifestHash: $artifactSet->reviewManifestHash,
            configHash: hash('sha256', json_encode($artifactSet->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            bookletHash: '',
            nativeTemplate: $nativeTemplate,
            nativeConfig: $artifactSet->config,
            nativeBundleMetadata: [
                'files' => $reviewManifest['file_hashes'] ?? [],
                'engine' => $artifactSet->manifest['engine'] ?? null,
                'bundle_version' => $artifactSet->manifest['bundle_version'] ?? null,
                'mapping_hash' => $artifactSet->definition['source_hash'] ?? $artifactSet->reviewManifestHash,
            ],
            reviewManifest: $reviewManifest,
            engineName: (string) ($artifactSet->manifest['engine']['name'] ?? config('omr.omrchecker.engine_name', 'OMRChecker')),
            engineCommit: isset($artifactSet->manifest['engine']['commit']) ? (string) $artifactSet->manifest['engine']['commit'] : null,
            patchIdentity: (string) ($artifactSet->manifest['engine']['patch_set'] ?? config('omr.omrchecker.patch_identity', '')),
            expectedFieldLabels: [],
            expectedOptionCodes: [],
            providerDeadline: (float) config('omr.provider.deadline_seconds', 30),
            pageNo: $pageNo,
            nativeAssets: [$assetName => $artifactSet->files[$assetName]],
        );
    }
    /**
     *
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
