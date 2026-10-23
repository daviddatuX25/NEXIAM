<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Queries\OmrReviewGeometryQuery;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use RuntimeException;

use function array_map;
use function array_unique;
use function is_array;
use function preg_match;
use function str_starts_with;

/**
 * Immutable template/booklet pin for one sheet. The provider receives only
 * observation geometry and native artifact identity; answer keys remain in this
 * NEXIAM-only snapshot for projection after validation.
 */
final readonly class OmrPinnedSnapshot
{
    /**
     * @param  array<string, int>  $areas
     * @param  array<string, int>  $areaIdsByCode
     * @param  list<OmrItemGeometry>  $items
     * @param  array<string, OmrRegion>  $identityRegions
     * @param  array<int, string>  $correctOptionsByPosition
     * @param  list<string>  $expectedFieldLabels
     * @param  array<string, list<string>>  $expectedOptionCodes
     * @param  array<string, mixed>  $nativeTemplate
     * @param  array<string, mixed>  $nativeConfig
     * @param  array<string, mixed>  $nativeBundleMetadata
     * @param  array<string, mixed>  $reviewManifest
     * @param  array<string, string>  $nativeAssets  bounded marker assets (name => bytes) carried to the worker
     */
    public function __construct(
        public array $areas,
        public array $areaIdsByCode,
        public array $items,
        public array $identityRegions,
        public array $correctOptionsByPosition,
        public string $nativeBundleHash,
        public string $manifestHash,
        public string $configHash,
        public string $bookletHash,
        public array $nativeTemplate,
        public string $nativeConfigJson,
        public array $nativeConfig,
        public array $nativeBundleMetadata,
        public array $reviewManifest,
        public string $engineName,
        public ?string $engineCommit,
        public string $patchIdentity,
        public array $expectedFieldLabels,
        public array $expectedOptionCodes,
        public int $pageCount,
        public array $pageTemplates,
        public array $pageIdentityRegions,
        public array $nativeAssets = [],
        public array $formalSubtestIdsByPosition = [],
    ) {}

    /**
     * Build the pin from the batch's immutable template and booklet versions.
     * Version-owned projection rows are the sole source of answer geometry;
     * booklet questions contribute only area/position coverage and correct
     * options through `OmrReviewGeometryQuery`.
     *
     * @throws RuntimeException when the pin is incomplete
     */
    public static function pin(
        OmrAnswerSheetTemplateVersion $templateVersion,
        OmrQuestionBookletVersion $bookletVersion,
        ?OmrBookletSet $set = null,
        ?array $kitContext = null,
    ): self {
        $nativeBundleHash = (string) $templateVersion->native_bundle_hash;
        $manifestHash = (string) $templateVersion->review_manifest_hash;
        $configHash = (string) $templateVersion->config_hash;
        $pinnedBundle = NativeBundle::loadForVersion($templateVersion);
        if (($pinnedBundle->fileHashes['config.json'] ?? null) !== $configHash) {
            throw new RuntimeException('The pinned OMR config hash does not match its immutable config.json bytes.');
        }
        $engineName = (string) $templateVersion->engine_name;
        $patchIdentity = (string) $templateVersion->patch_identity;
        $manifest = $templateVersion->review_manifest_json;
        $nativeBundle = $templateVersion->native_bundle_json;

        if ($nativeBundleHash === '' || $manifestHash === '' || $configHash === ''
            || $engineName === '' || $patchIdentity === '' || ! is_array($manifest)
            || ! is_array($nativeBundle) || ! is_array($nativeBundle['template'] ?? null)
            || ! is_array($nativeBundle['config'] ?? null) || ! is_array($nativeBundle['manifest'] ?? null)
            || ! is_array($nativeBundle['files'] ?? null)) {
            throw new RuntimeException('A pinned OMR template is missing immutable native bundle identity.');
        }

        // The version-owned geometry projection is the sole authority for
        // per-item regions; analysis blocks with typed failures when it is
        // missing or stale instead of degrading to placeholder geometry.
        app(OmrTemplateGeometryProjector::class)->currentFor($templateVersion);

        [$expectedFieldLabels, $expectedOptionCodes] = self::manifestExpectations($manifest);

        $pages = is_array($nativeBundle['template']['pages'] ?? null)
            ? array_values($nativeBundle['template']['pages'])
            : [];
        $pageCount = $pages !== [] ? count($pages) : 1;
        $pageTemplates = [];
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pageTemplates[$pageNo] = $pages !== []
                ? [
                    'pageDimensions' => $pages[$pageNo - 1]['pageDimensions'],
                    'bubbleDimensions' => $nativeBundle['template']['bubbleDimensions'],
                    'customLabels' => [],
                    'fieldBlocks' => $pages[$pageNo - 1]['fieldBlocks'],
                    'preProcessors' => $pages[$pageNo - 1]['preProcessors'] ?? [],
                ]
                : [
                    'pageDimensions' => $nativeBundle['template']['pageDimensions'],
                    'bubbleDimensions' => $nativeBundle['template']['bubbleDimensions'],
                    'customLabels' => [],
                    'fieldBlocks' => $nativeBundle['template']['fieldBlocks'],
                    'preProcessors' => $nativeBundle['template']['preProcessors'] ?? [],
                ];
            $pageTemplates[$pageNo]['customLabels'] = self::pageCustomLabels($pageTemplates[$pageNo]);
        }

        $areas = [];
        $areaIdsByCode = [];
        if ($kitContext === null) {
            foreach ($templateVersion->areas->sortBy('display_order') as $templateArea) {
                $code = $templateArea->aptitudeArea?->code;

                if ($code === null || $code === '') {
                    throw new RuntimeException('A pinned template area is missing its aptitude area code.');
                }

                $areas[$code] = (int) $templateArea->max_items;
                $areaIdsByCode[$code] = (int) $templateArea->aptitude_area_id;
            }
        }

        $items = [];
        $formalSubtestIdsByPosition = [];
        $projectedItems = app(OmrReviewGeometryQuery::class)->itemsFor($templateVersion, $bookletVersion, $set, $kitContext);
        if ($kitContext !== null) {
            foreach ($projectedItems as $projected) {
                $code = (string) $projected['area_code'];
                $subtestId = (int) $projected['formal_subtest_id'];
                if ($code === '' || $subtestId < 1) {
                    throw new RuntimeException('The saved Kit composition does not resolve a formal subtest for its native area.');
                }
                $areaIdsByCode[$code] ??= $subtestId;
                $areas[$code] = ($areas[$code] ?? 0) + 1;
                $formalSubtestIdsByPosition[(int) $projected['position']] = $subtestId;
            }
        }
        foreach ($projectedItems as $projected) {
            $options = array_map(
                static fn (array $option): OmrOptionGeometry => new OmrOptionGeometry(
                    code: (string) $option['code'],
                    region: new OmrRegion(
                        (float) $option['region']['x'],
                        (float) $option['region']['y'],
                        (float) $option['region']['w'],
                        (float) $option['region']['h'],
                    ),
                    displayOrder: (int) ($option['display_order'] ?? 0),
                ),
                (array) ($projected['options'] ?? []),
            );
            $questionNo = (int) $projected['question_no'];
            $items[] = new OmrItemGeometry(
                position: (int) $projected['position'],
                areaCode: (string) $projected['area_code'],
                questionNo: $questionNo,
                nativeLabel: (string) ($projected['native_label'] ?? sprintf('%s_Q_%d', $projected['area_code'], $questionNo)),
                region: new OmrRegion(
                    (float) $projected['region']['x'],
                    (float) $projected['region']['y'],
                    (float) $projected['region']['w'],
                    (float) $projected['region']['h'],
                ),
                options: $options,
                pageNo: (int) $projected['page_no'],
                physicalItemUid: isset($projected['physical_item_uid']) ? (string) $projected['physical_item_uid'] : null,
            );
            $correctOptionsByPosition[(int) $projected['position']] = (string) $projected['correct_option_code'];
        }

        if ($areas === [] || $items === []) {
            throw new RuntimeException('The pinned OMR configuration has no scorable geometry.');
        }

        $identityRegions = [];
        $pageIdentityRegions = [];
        $storedIdentity = (array) $templateVersion->identity_regions_json;
        $pageKeyed = $storedIdentity !== []
            && array_keys($storedIdentity) !== array_filter(array_keys($storedIdentity), 'is_string');
        if ($pageKeyed) {
            foreach ($storedIdentity as $pageNo => $pageRegions) {
                $pageIdentityRegions[(int) $pageNo] = [];
                foreach ((array) $pageRegions as $fieldCode => $region) {
                    $pageIdentityRegions[(int) $pageNo][(string) $fieldCode] = OmrRegion::fromArray((array) $region);
                }
            }
            $identityRegions = $pageIdentityRegions[1] ?? [];
        } else {
            foreach ($storedIdentity as $fieldCode => $region) {
                $identityRegions[(string) $fieldCode] = OmrRegion::fromArray((array) $region);
            }
        }
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pageIdentityRegions[$pageNo] ??= $identityRegions;
        }

        // Marker-bearing versions carry the fiducial asset to the worker. The
        // bytes come from the immutable on-disk bundle (declared in the
        // hash-covered files map); a declared-but-missing asset fails closed.
        $nativeAssets = [];
        $markerMetadata = OmrCapturePolicy::markerMetadata($nativeBundle['template'] ?? []);
        if ($markerMetadata !== null) {
            $nativeAssets[$markerMetadata['asset_name']] = $pinnedBundle->assetBytes($markerMetadata['asset_name']);
        }

        return new self(
            areas: $areas,
            areaIdsByCode: $areaIdsByCode,
            items: $items,
            identityRegions: $identityRegions,
            correctOptionsByPosition: $correctOptionsByPosition,
            nativeBundleHash: $nativeBundleHash,
            manifestHash: $manifestHash,
            configHash: $configHash,
            bookletHash: (string) ($set?->answer_key_hash ?? $bookletVersion->answer_key_hash),
            nativeTemplate: $nativeBundle['template'],
            nativeConfigJson: $pinnedBundle->assetBytes('config.json'),
            nativeConfig: $pinnedBundle->config,
            nativeBundleMetadata: [
                'files' => $pinnedBundle->fileHashes,
                'engine' => $nativeBundle['manifest']['engine'] ?? null,
                'bundle_version' => $nativeBundle['manifest']['bundle_version'] ?? null,
            ],
            reviewManifest: $manifest,
            engineName: $engineName,
            engineCommit: $templateVersion->engine_commit !== null ? (string) $templateVersion->engine_commit : null,
            patchIdentity: $patchIdentity,
            expectedFieldLabels: $expectedFieldLabels,
            expectedOptionCodes: $expectedOptionCodes,
            pageCount: $pageCount,
            pageTemplates: $pageTemplates,
            pageIdentityRegions: $pageIdentityRegions,
            nativeAssets: $nativeAssets,
            formalSubtestIdsByPosition: $formalSubtestIdsByPosition,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{0: list<string>, 1: array<string, list<string>>}
     */
    private static function manifestExpectations(array $manifest): array
    {
        $labels = [];
        $options = [];
        $fieldKeys = [];

        foreach ((array) ($manifest['fields'] ?? []) as $field) {
            if (! is_array($field) || ! isset($field['label'], $field['option'])) {
                throw new RuntimeException('The pinned OMR review manifest contains a malformed field.');
            }

            $label = (string) $field['label'];
            $option = (string) $field['option'];
            $labels[] = $label;
            $options[$label][] = $option;
            // Identity grids repeat per page; scorable bubbles must stay unique.
            if (! str_starts_with($label, 'APPLICANT_NUMBER_')
                && ! str_starts_with($label, 'BIRTHDATE_')) {
                $fieldKeys[] = $label.'|'.$option;
            }
        }

        if ($fieldKeys === [] || count($fieldKeys) !== count(array_unique($fieldKeys))) {
            throw new RuntimeException('The pinned OMR review manifest has duplicate or missing field bubbles.');
        }

        $labels = array_values(array_unique($labels));
        if ($labels === []) {
            throw new RuntimeException('The pinned OMR review manifest has no stable field labels.');
        }

        foreach ($options as $label => $labelOptions) {
            $options[$label] = array_values(array_unique($labelOptions));
        }

        return [$labels, $options];
    }

    /** @param array<string, mixed> $pageTemplate @return array<string, list<string>> */
    private static function pageCustomLabels(array $pageTemplate): array
    {
        $labels = [];
        foreach ((array) ($pageTemplate['fieldBlocks'] ?? []) as $block) {
            foreach ((array) ($block['fieldLabels'] ?? []) as $fieldLabel) {
                $labels = [...$labels, ...NativeBundle::expandLabel((string) $fieldLabel)];
            }
        }

        $grouped = [];
        foreach ($labels as $label) {
            if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                $grouped['APPLICANT_NUMBER'][] = $label;

                continue;
            }
            if (preg_match('/^([A-Z0-9_]+)_Q_\d+$/', $label, $matches) === 1) {
                $grouped[$matches[1]][] = $label;
            }
        }

        return $grouped;
    }
}
