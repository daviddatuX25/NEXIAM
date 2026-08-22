<?php

namespace App\Assessment\Omr;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletQuestion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateQuestion;
use RuntimeException;

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
    ) {
    }

    /**
     * Build the pin from the batch's immutable template and booklet versions.
     * Loaded relations required: template areas with aptitudeArea and
     * questions.options; booklet questions with templateQuestion and aptitudeArea.
     *
     * @throws RuntimeException when the pin is incomplete
     */
    public static function pin(
        OmrAnswerSheetTemplateVersion $templateVersion,
        OmrQuestionBookletVersion $bookletVersion,
    ): self {
        $nativeBundleHash = (string) $templateVersion->native_bundle_hash;
        $manifestHash = (string) $templateVersion->review_manifest_hash;
        $configHash = (string) $templateVersion->config_hash;
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
        $areaCodeByTemplateAreaId = [];

        foreach ($templateVersion->areas->sortBy('display_order') as $templateArea) {
            $code = $templateArea->aptitudeArea?->code;

            if ($code === null || $code === '') {
                throw new RuntimeException('A pinned template area is missing its aptitude area code.');
            }

            $areas[$code] = (int) $templateArea->max_items;
            $areaIdsByCode[$code] = (int) $templateArea->aptitude_area_id;
            $areaCodeByTemplateAreaId[(int) $templateArea->id] = $code;
        }

        $items = [];
        $correctOptionsByPosition = [];

        foreach ($bookletVersion->questions->sortBy('question_position') as $bookletQuestion) {
            $templateQuestion = $bookletQuestion->templateQuestion;

            if ($templateQuestion === null) {
                throw new RuntimeException('A booklet question is missing its pinned template question.');
            }

            $code = self::areaCodeFor($bookletQuestion, $templateQuestion, $areaCodeByTemplateAreaId);
            $items[] = self::geometryItem($bookletQuestion, $templateQuestion, $code);
            $correctOptionsByPosition[(int) $bookletQuestion->question_position] = (string) $bookletQuestion->correct_option_code;
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
        $markerMetadata = \App\Assessment\Omr\OmrCapturePolicy::markerMetadata($nativeBundle['template'] ?? []);
        if ($markerMetadata !== null) {
            $manifestCode = $nativeBundle['manifest']['template_code'] ?? null;
            $manifestVersion = (int) ($nativeBundle['manifest']['template_version'] ?? 0);
            if (! is_string($manifestCode) || $manifestCode === '' || $manifestVersion <= 0) {
                throw new RuntimeException('A pinned marker-bearing OMR template is missing its bundle identity.');
            }
            $pinnedBundle = NativeBundle::load($manifestCode, $manifestVersion);
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
            bookletHash: (string) $bookletVersion->answer_key_hash,
            nativeTemplate: $nativeBundle['template'],
            nativeConfig: $nativeBundle['config'],
            nativeBundleMetadata: [
                'files' => $nativeBundle['files'],
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

    /** @param array<int, string> $areaCodeByTemplateAreaId */
    private static function areaCodeFor(
        OmrQuestionBookletQuestion $bookletQuestion,
        OmrTemplateQuestion $templateQuestion,
        array $areaCodeByTemplateAreaId,
    ): string {
        $fromTemplateArea = $areaCodeByTemplateAreaId[(int) $templateQuestion->template_area_id] ?? null;

        if ($fromTemplateArea !== null && $fromTemplateArea !== '') {
            return $fromTemplateArea;
        }

        $code = $bookletQuestion->aptitudeArea?->code;

        if ($code !== null && $code !== '') {
            return $code;
        }

        throw new RuntimeException("Booklet question at position {$bookletQuestion->question_position} has no resolvable area code.");
    }

    private static function geometryItem(
        OmrQuestionBookletQuestion $bookletQuestion,
        OmrTemplateQuestion $templateQuestion,
        string $areaCode,
    ): OmrItemGeometry {
        $options = $templateQuestion->options
            ->sortBy('display_order')
            ->values()
            ->map(fn ($option): OmrOptionGeometry => new OmrOptionGeometry(
                code: (string) $option->code,
                region: self::regionOf($option->region_x, $option->region_y, $option->region_w, $option->region_h),
                displayOrder: (int) $option->display_order,
            ))->all();

        return new OmrItemGeometry(
            position: (int) $bookletQuestion->question_position,
            areaCode: $areaCode,
            questionNo: (int) ($templateQuestion->question_no ?? $bookletQuestion->question_position),
            pageNo: (int) ($templateQuestion->page_no ?? 1),
            nativeLabel: sprintf('%s_Q_%d', $areaCode, (int) ($templateQuestion->question_no ?? $bookletQuestion->question_position)),
            region: self::regionOf($templateQuestion->region_x, $templateQuestion->region_y, $templateQuestion->region_w, $templateQuestion->region_h),
            options: $options,
        );
    }

    private static function regionOf(float $x, float $y, float $w, float $h): OmrRegion
    {
        return new OmrRegion((float) $x, (float) $y, (float) $w, (float) $h);
    }
}
