<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use JsonException;
use RuntimeException;

use function array_filter;
use function array_values;
use function count;
use function hash;
use function is_array;
use function is_numeric;
use function json_encode;
use function max;
use function min;
use function preg_replace;
use function round;
use function sprintf;

/**
 * Server-owned, deterministic interpretation of an editable canonical draft.
 *
 * All recognition geometry is stored in the marker-centre frame at 300 PPI.
 * Millimetres and visual component metadata remain available for rendering;
 * they are never sent to OMRChecker as a second geometry authority.
 */
final readonly class CanonicalSheetDefinition
{
    public const SCHEMA = 'nexiam.omr.canonical-sheet-definition.v1';
    public const PPI = 300;

    /**
     * @param array{width_mm:float,height_mm:float,width_px:int,height_px:int} $paper
     * @param array{top:float,right:float,bottom:float,left:float} $insetsMm
     * @param array{top:int,right:int,bottom:int,left:int} $insetsPx
     * @param array{width:int,height:int} $frame
     * @param array<string,mixed> $fieldBlocks
     * @param list<array<string,mixed>> $visualComponents
     * @param array<string,mixed> $overlay
     * @param array<string,mixed> $settings
     */
    public function __construct(
        public string $schema,
        public string $draftCode,
        public int $candidateVersion,
        public array $paper,
        public array $insetsMm,
        public array $insetsPx,
        public array $frame,
        public array $fieldBlocks,
        public array $visualComponents,
        public array $overlay,
        public array $settings,
        public string $sourceHash,
        public ?int $markerProfileId,
    ) {
    }

    public static function fromDraft(
        OmrTemplateAuthoringDraft $draft,
        OmrMarkerProfile $profile,
        ?int $candidateVersion = null,
    ): self {
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $canonicalSettings = (array) ($canonical['settings'] ?? []);
        $studio = (array) ($authoring['studio'] ?? []);
        $settings = array_replace([
            'paper_width_mm' => $draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML ? 210.0 : 216.0,
            'paper_height_mm' => $draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML ? 297.0 : 330.0,
            'content_margin_mm' => ['top' => 18.0, 'right' => 15.0, 'bottom' => 18.0, 'left' => 15.0],
            'alignment' => OmrPageDimensionMath::ALIGN_CENTER,
        ], $canonicalSettings);

        $paperWidthMm = (float) ($settings['paper_width_mm'] ?? 210.0);
        $paperHeightMm = (float) ($settings['paper_height_mm'] ?? 297.0);
        OmrPageDimensionMath::assertPaperBounds($paperWidthMm, $paperHeightMm);
        $paper = [
            'width_mm' => $paperWidthMm,
            'height_mm' => $paperHeightMm,
            'width_px' => OmrPageDimensionMath::pixelsFromMillimetres($paperWidthMm),
            'height_px' => OmrPageDimensionMath::pixelsFromMillimetres($paperHeightMm),
        ];

        $insetsMm = self::insets($authoring, $settings);
        $insetsPx = [
            'top' => OmrPageDimensionMath::pixelsFromMillimetres($insetsMm['top']),
            'right' => OmrPageDimensionMath::pixelsFromMillimetres($insetsMm['right']),
            'bottom' => OmrPageDimensionMath::pixelsFromMillimetres($insetsMm['bottom']),
            'left' => OmrPageDimensionMath::pixelsFromMillimetres($insetsMm['left']),
        ];
        $frame = [
            'width' => $paper['width_px'] - $insetsPx['left'] - $insetsPx['right'],
            'height' => $paper['height_px'] - $insetsPx['top'] - $insetsPx['bottom'],
        ];
        if ($frame['width'] <= 0 || $frame['height'] <= 0) {
            throw new RuntimeException('marker_geometry_invalid: marker insets leave no canonical frame.');
        }

        $components = self::visualComponents($studio['components'] ?? []);
        $mapping = (array) ($authoring['mapping'] ?? []);
        $fieldBlocks = (array) ($mapping['field_blocks'] ?? []);
        $input = (array) ($mapping['input'] ?? []);
        $inputIsPaperSpace = self::isPaperSpace($input, $paper);
        if ($fieldBlocks === []) {
            $fieldBlocks = self::fieldBlocksFromComponents($components, $insetsPx, $frame);
        } else {
            $fieldBlocks = self::normalizeFieldBlocks($fieldBlocks, $components, $insetsPx, $frame, $inputIsPaperSpace);
        }
        if ($fieldBlocks === []) {
            throw new RuntimeException('canonical_definition_mapping_missing: at least one OMR field block is required.');
        }

        $overlay = (array) ($authoring['overlay'] ?? []);
        $version = $candidateVersion ?? self::candidateVersion($draft);
        $definition = [
            'schema' => self::SCHEMA,
            'draft_code' => (string) $draft->code,
            'candidate_version' => $version,
            'paper' => $paper,
            'insets_mm' => $insetsMm,
            'insets_px' => $insetsPx,
            'frame' => $frame,
            'field_blocks' => $fieldBlocks,
            'visual_components' => $components,
            'overlay' => $overlay,
            'settings' => $settings,
            'marker_profile' => [
                'id' => $profile->id,
                'code' => $profile->code,
                'version' => $profile->version,
                'family' => $profile->family,
                'asset_path' => $profile->asset_path,
                'asset_sha256' => $profile->asset_sha256,
                'status' => $profile->status,
                'geometry_status' => $profile->usesProvisionalGeometry() ? 'provisional' : 'qualified',
                'size_mm' => $profile->materializationSizeMm(),
                'quiet_zone_mm' => $profile->materializationQuietZoneMm(),
            ],
        ];

        return new self(
            schema: self::SCHEMA,
            draftCode: (string) $draft->code,
            candidateVersion: $version,
            paper: $paper,
            insetsMm: $insetsMm,
            insetsPx: $insetsPx,
            frame: $frame,
            fieldBlocks: $fieldBlocks,
            visualComponents: $components,
            overlay: $overlay,
            settings: $settings,
            sourceHash: self::hashCanonical($definition),
            markerProfileId: $profile->id !== null ? (int) $profile->id : null,
        );
    }

    /** @return array{top:float,right:float,bottom:float,left:float} */
    private static function insets(array $authoring, array $settings): array
    {
        $insets = (array) (($authoring['marker']['insets_mm'] ?? null)
            ?: ($settings['marker_inset_mm'] ?? null)
            ?: ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0]);

        return [
            'top' => max(0.0, (float) ($insets['top'] ?? 12.0)),
            'right' => max(0.0, (float) ($insets['right'] ?? 12.0)),
            'bottom' => max(0.0, (float) ($insets['bottom'] ?? 14.0)),
            'left' => max(0.0, (float) ($insets['left'] ?? 12.0)),
        ];
    }

    private static function candidateVersion(OmrTemplateAuthoringDraft $draft): int
    {
        $existing = $draft->template?->versions()->max('version');

        return ((int) $existing) + 1;
    }

    private static function isPaperSpace(array $input, array $paper): bool
    {
        if ($input === []) {
            return true;
        }

        return abs((int) ($input['page_width'] ?? 0) - $paper['width_px']) <= 2
            && abs((int) ($input['page_height'] ?? 0) - $paper['height_px']) <= 2;
    }

    /** @return list<array<string,mixed>> */
    private static function visualComponents(mixed $components): array
    {
        if (! is_array($components)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $component): ?array {
            if (! is_array($component) || ! is_string($component['id'] ?? null) || ! is_string($component['type'] ?? null)) {
                return null;
            }

            $allowed = [
                'id', 'type', 'page_no', 'title', 'subtitle', 'subjectName', 'aptitudeAreaCode', 'name', 'label', 'content', 'text',
                'posX', 'posY', 'w_mm', 'h_mm', 'questionsCount', 'startQ', 'optionsCount', 'cols', 'customValuesStr',
                'digitsCount', 'bubbleDiameterMm', 'bubblePitchMm', 'rowPitchMm', 'backgroundColor', 'borderColor',
                'inputStyle', 'inputKind', 'certificationText', 'showSectionHeader', 'typography', 'zIndex',
            ];
            $normalized = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $component)) {
                    $normalized[$key] = $component[$key];
                }
            }
            $normalized['id'] = (string) $component['id'];
            $normalized['type'] = (string) $component['type'];
            $normalized['posX'] = (float) ($component['posX'] ?? 0);
            $normalized['posY'] = (float) ($component['posY'] ?? 0);
            $normalized['w_mm'] = (float) ($component['w_mm'] ?? 0);
            $normalized['h_mm'] = (float) ($component['h_mm'] ?? 0);

            return $normalized;
        }, $components), static fn (?array $component): bool => $component !== null));
    }

    /** @param array<string,mixed> $fieldBlocks @param list<array<string,mixed>> $components @return array<string,mixed> */
    private static function normalizeFieldBlocks(array $fieldBlocks, array $components, array $insetsPx, array $frame, bool $inputIsPaperSpace): array
    {
        $byId = [];
        foreach ($components as $component) {
            $byId[(string) $component['id']] = $component;
        }
        $normalized = [];
        foreach ($fieldBlocks as $name => $block) {
            if (! is_array($block)) {
                continue;
            }
            $block = $block;
            $origin = (array) ($block['origin'] ?? [0, 0]);
            if ($inputIsPaperSpace) {
                $origin = [(int) $origin[0] - $insetsPx['left'], (int) $origin[1] - $insetsPx['top']];
            } else {
                $origin = [(int) $origin[0], (int) $origin[1]];
            }
            if ($origin[0] < 0 || $origin[1] < 0 || $origin[0] >= $frame['width'] || $origin[1] >= $frame['height']) {
                throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: field block %s origin is outside the marker frame.', (string) $name));
            }
            $block['origin'] = $origin;
            $componentId = (string) preg_replace('/_col\d+$/', '', (string) $name);
            $component = $byId[(string) $name] ?? $byId[$componentId] ?? null;
            $block['page_no'] = max(1, (int) ($block['page_no'] ?? ($component['page_no'] ?? 1)));
            if (is_array($component)) {
                $label = $component['type'] === 'roll'
                    ? ($component['title'] ?? 'Examinee Number')
                    : ($component['subjectName'] ?? $component['name'] ?? $name);
                $block['displayLabel'] = (string) $label;
                $block['showSectionHeader'] = ($component['showSectionHeader'] ?? true) === true;
                if ($component['type'] === 'roll' && (string) ($block['fieldType'] ?? '') === 'QTYPE_INT') {
                    $digitCount = 0;
                    foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                        $digitCount += count(\App\Assessment\Omr\NativeBundle::expandLabel((string) $declaration));
                    }
                    if ($digitCount > 0) {
                        $block['fieldLabels'] = ['APPLICANT_NUMBER_1..'.$digitCount];
                    }
                }
                if ($component['type'] !== 'roll') {
                    $prefix = strtoupper((string) preg_replace(
                        '/[^A-Za-z0-9]+/',
                        '_',
                        (string) ($component['aptitudeAreaCode'] ?? $componentId),
                    ));
                    $nativeLabels = [];
                    foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                        foreach (\App\Assessment\Omr\NativeBundle::expandLabel((string) $declaration) as $label) {
                            if (preg_match('/(\d+)$/', $label, $matches) === 1) {
                                $nativeLabels[] = $prefix.'_Q_'.(int) $matches[1];
                            }
                        }
                    }
                    if ($nativeLabels !== []) {
                        $block['fieldLabels'] = $nativeLabels;
                    }
                }
            }
            $normalized[(string) $name] = $block;
        }

        return $normalized;
    }

    /** @param list<array<string,mixed>> $components @return array<string,mixed> */
    private static function fieldBlocksFromComponents(array $components, array $insetsPx, array $frame): array
    {
        $blocks = [];
        foreach ($components as $component) {
            $type = (string) ($component['type'] ?? '');
            if ($type !== 'roll' && ! in_array($type, ['mcq', 'tf', 'likert', 'custom'], true)) {
                continue;
            }
            $id = (string) $component['id'];
            $pageNo = max(1, (int) ($component['page_no'] ?? 1));
            $originOffset = $type === 'roll' ? ['x' => 6.4, 'y' => 14.0] : ['x' => 5.0, 'y' => 8.0];
            $origin = [
                OmrPageDimensionMath::pixelsFromMillimetres((float) $component['posX'] + $originOffset['x']) - $insetsPx['left'],
                OmrPageDimensionMath::pixelsFromMillimetres((float) $component['posY'] + $originOffset['y']) - $insetsPx['top'],
            ];
            $bubble = OmrPageDimensionMath::pixelsFromMillimetres((float) ($component['bubbleDiameterMm'] ?? 4.5));
            $bubblePitch = OmrPageDimensionMath::pixelsFromMillimetres((float) ($component['bubblePitchMm'] ?? 6.0));
            $rowPitch = OmrPageDimensionMath::pixelsFromMillimetres((float) ($component['rowPitchMm'] ?? 6.8));
            $count = max(1, (int) ($component[$type === 'roll' ? 'digitsCount' : 'questionsCount'] ?? 1));
            if ($type === 'roll') {
                $blocks[$id] = [
                    'fieldType' => 'QTYPE_INT',
                    'direction' => 'vertical',
                    'fieldLabels' => ['APPLICANT_NUMBER_1..'.$count],
                    'bubblesGap' => $rowPitch,
                    'labelsGap' => $bubblePitch,
                    'origin' => $origin,
                    'bubbleDimensions' => [$bubble, $bubble],
                    'displayLabel' => (string) ($component['title'] ?? 'Examinee Number'),
                    'showSectionHeader' => true,
                    'page_no' => $pageNo,
                ];
                continue;
            }
            $options = match ($type) {
                'tf' => ['T', 'F'],
                'likert' => ['1', '2', '3', '4', '5'],
                'custom' => array_values(array_filter(array_map('trim', explode(',', (string) ($component['customValuesStr'] ?? 'A, B'))))),
                default => ['A', 'B', 'C', 'D'],
            };
            $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($component['aptitudeAreaCode'] ?? $id)));
            $start = max(1, (int) ($component['startQ'] ?? 1));
            $end = $start + $count - 1;
            $blocks[$id] = [
                'fieldType' => count($options) === 5 ? 'QTYPE_MCQ5' : (count($options) === 2 ? 'QTYPE_MCQ4' : 'QTYPE_MCQ4'),
                'direction' => 'horizontal',
                'fieldLabels' => [$prefix.'_Q_'.$start.'..'.$end],
                'bubblesGap' => $bubblePitch,
                'labelsGap' => $rowPitch,
                'origin' => $origin,
                'bubbleDimensions' => [$bubble, $bubble],
                'bubbleValues' => $options,
                'displayLabel' => (string) ($component['subjectName'] ?? $component['name'] ?? $id),
                'showSectionHeader' => ($component['showSectionHeader'] ?? true) === true,
                'page_no' => $pageNo,
            ];
        }

        return $blocks;
    }

    /** @param array<string,mixed> $value */
    private static function hashCanonical(array $value): string
    {
        try {
            return hash('sha256', json_encode(self::sortRecursive($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new RuntimeException('canonical_definition_hash_failed', 0, $exception);
        }
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursive($item);
        }

        return $value;
    }
}
