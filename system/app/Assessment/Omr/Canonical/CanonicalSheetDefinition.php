<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use JsonException;
use RuntimeException;

use function abs;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_replace;
use function array_values;
use function count;
use function explode;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function ksort;
use function max;
use function preg_replace;
use function range;
use function sprintf;
use function strtoupper;

/**
 * Server-owned, deterministic interpretation of an editable canonical draft.
 *
 * Recognition coordinates use the marker-centre frame for anchored paper
 * and the full physical paper frame for document-bound paper.
 */
final readonly class CanonicalSheetDefinition
{
    public const SCHEMA = 'nexiam.omr.canonical-sheet-definition.v1';

    public const PPI = 300;

    /**
     * @param  array{width_mm:float,height_mm:float,width_px:int,height_px:int}  $paper
     * @param  array{top:float,right:float,bottom:float,left:float}  $insetsMm
     * @param  array{top:int,right:int,bottom:int,left:int}  $insetsPx
     * @param  array{width:int,height:int}  $frame
     * @param  array<string,mixed>  $fieldBlocks
     * @param  list<array<string,mixed>>  $visualComponents
     * @param  array<string,mixed>  $overlay
     * @param  array<string,mixed>  $settings
     */
    public function __construct(
        public string $schema,
        public string $draftCode,
        public OmrRegistrationMode $registrationMode,
        public OmrQrMode $qrMode,
        public array $compiledPages,
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
        public string $geometryIdentity = '',
        public string $sourceKind = OmrSourcePolicy::SYSTEM_DESIGNED,
        public ?array $declaredInventory = null,
        public ?array $identityMethod = null,
    ) {}

    public static function fromDraft(
        OmrTemplateAuthoringDraft $draft,
        ?OmrMarkerProfile $profile,
        ?int $candidateVersion = null,
    ): self {
        $authoring = (array) $draft->authoring_json;
        $registrationMode = $draft->registration_mode;
        $qrMode = $draft->qr_mode;
        if (! $registrationMode instanceof OmrRegistrationMode || ! $qrMode instanceof OmrQrMode) {
            throw new RuntimeException('canonical_policy_missing: draft paper policy must be explicit.');
        }
        $sourcePages = array_values((array) ($authoring['pages'] ?? []));
        $pageCount = (int) $draft->requested_page_count;
        if ($pageCount < 1 || $pageCount > 4 || count($sourcePages) !== $pageCount) {
            throw new RuntimeException('canonical_page_count_mismatch: authoring pages must match the requested count.');
        }
        $compiledPages = [];
        foreach ($sourcePages as $index => $page) {
            if (! is_array($page) || (int) ($page['page_no'] ?? 0) !== $index + 1) {
                throw new RuntimeException('canonical_page_order_invalid: source pages must be ordered and contiguous.');
            }
            $compiledPages[] = ['page_no' => $index + 1];
        }
        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            if ($profile !== null || $draft->marker_profile_id !== null || ! empty($authoring['marker'])
                || array_key_exists('marker_inset_mm', (array) ($authoring['canonical']['settings'] ?? []))) {
                throw new RuntimeException('registration_mode_incompatible: document-bound drafts cannot carry marker state.');
            }
        } elseif ($profile === null || (int) $profile->id !== (int) $draft->marker_profile_id) {
            throw new RuntimeException('marker_profile_missing: anchored drafts require their selected marker profile.');
        }
        if ($qrMode === OmrQrMode::Disabled && (
            OmrQrReservationResolver::extractReservationBox($authoring) !== null
            || ($authoring['studio']['showQrOverlay'] ?? false) === true
            || preg_match("/data-omr-overlay=[\"']blank_instance_qr[\"']/i", (string) ($authoring['studio']['htmlCode'] ?? '')) === 1
        )) {
            throw new RuntimeException('qr_mode_incompatible: QR-disabled drafts cannot carry a production QR reservation.');
        }
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

        $insetsMm = $registrationMode === OmrRegistrationMode::FiducialAnchored
            ? self::insets($authoring, $settings)
            : ['top' => 0.0, 'right' => 0.0, 'bottom' => 0.0, 'left' => 0.0];
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
            throw new RuntimeException('canonical_geometry_invalid: paper registration frame has no area.');
        }

        $studioComponents = $studio['components'] ?? null;
        $studioSections = $studio['sections'] ?? null;
        $components = self::visualComponents($studioComponents);
        if ($components === [] && is_array($studioSections) && $studioSections !== []) {
            $components = self::visualComponentsFromSections($studioSections);
        }

        $mapping = (array) ($authoring['mapping'] ?? []);
        $fieldBlocks = (array) ($mapping['field_blocks'] ?? []);
        $input = (array) ($mapping['input'] ?? []);
        $inputIsPaperSpace = self::isPaperSpace($input, $paper);

        if ($fieldBlocks !== []) {
            $fieldBlocks = self::normalizeFieldBlocks($fieldBlocks, $components, $insetsPx, $frame, $inputIsPaperSpace);
            $components = self::reconcileComponentsWithFieldBlocks($components, $fieldBlocks, $insetsPx);
        } elseif ($components !== []) {
            $fieldBlocks = self::fieldBlocksFromComponents($components, $insetsPx, $frame, []);
        }

        if ($fieldBlocks === []) {
            throw new RuntimeException('canonical_definition_mapping_missing: at least one OMR field block is required.');
        }

        $overlay = (array) ($authoring['overlay'] ?? []);
        $version = $candidateVersion ?? self::candidateVersion($draft);
        $sourceKind = OmrSourcePolicy::sourceKindForDraft($draft) ?? OmrSourcePolicy::SYSTEM_DESIGNED;
        $declaredInventory = $sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL && is_array($input['declared_inventory'] ?? null)
            ? $input['declared_inventory']
            : null;
        $identityMethod = $sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL && is_array($input['identity'] ?? null)
            ? $input['identity']
            : null;
        $definition = [
            'schema' => self::SCHEMA,
            'draft_code' => (string) $draft->code,
            'registration_mode' => $registrationMode->value,
            'qr_mode' => $qrMode->value,
            'compiled_pages' => $compiledPages,
            'candidate_version' => $version,
            'paper' => $paper,
            'insets_mm' => $insetsMm,
            'insets_px' => $insetsPx,
            'frame' => $frame,
            'field_blocks' => $fieldBlocks,
            'visual_components' => $components,
            'overlay' => $overlay,
            'settings' => $settings,
            'marker_profile' => $profile !== null ? [
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
            ] : null,
            ...($sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL ? [
                'source_kind' => $sourceKind,
                'declared_inventory' => $declaredInventory,
                'identity_method' => $identityMethod,
            ] : []),
        ];

        $sourceHash = self::hashCanonical($definition);
        $markerProfileId = $profile?->id !== null ? (int) $profile->id : null;

        $tempInstance = new self(
            schema: self::SCHEMA,
            registrationMode: $registrationMode,
            qrMode: $qrMode,
            compiledPages: $compiledPages,
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
            sourceHash: $sourceHash,
            markerProfileId: $markerProfileId,
            geometryIdentity: '',
            sourceKind: $sourceKind,
            declaredInventory: $declaredInventory,
            identityMethod: $identityMethod,
        );

        $geometryIdentity = OmrCanonicalGeometryIdentity::fromDefinition($tempInstance);

        return new self(
            schema: self::SCHEMA,
            draftCode: (string) $draft->code,
            candidateVersion: $version,
            registrationMode: $registrationMode,
            qrMode: $qrMode,
            compiledPages: $compiledPages,
            paper: $paper,
            insetsMm: $insetsMm,
            insetsPx: $insetsPx,
            frame: $frame,
            fieldBlocks: $fieldBlocks,
            visualComponents: $components,
            overlay: $overlay,
            settings: $settings,
            sourceHash: $sourceHash,
            markerProfileId: $markerProfileId,
            geometryIdentity: $geometryIdentity,
            sourceKind: $sourceKind,
            declaredInventory: $declaredInventory,
            identityMethod: $identityMethod,
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
        $template = $draft->template
            ?? ($draft->code !== '' ? OmrAnswerSheetTemplate::query()->where('code', $draft->code)->first() : null);
        $existing = $template?->versions()->max('version');

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

    /** @param list<array<string,mixed>> $sections @return list<array<string,mixed>> */
    private static function visualComponentsFromSections(array $sections): array
    {
        $components = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $id = (string) ($section['id'] ?? ('Section_'.(count($components) + 1)));
            $components[] = [
                'id' => $id,
                'type' => (string) ($section['type'] ?? 'mcq'),
                'page_no' => (int) ($section['page_no'] ?? 1),
                'posX' => (float) ($section['posX'] ?? 20.0),
                'posY' => (float) ($section['posY'] ?? 50.0),
                'w_mm' => (float) ($section['w_mm'] ?? 0.0),
                'h_mm' => (float) ($section['h_mm'] ?? 0.0),
                'title' => (string) ($section['title'] ?? $section['name'] ?? $id),
                'subjectName' => (string) ($section['subjectName'] ?? $section['name'] ?? $id),
                'aptitudeAreaCode' => (string) ($section['aptitudeAreaCode'] ?? $section['area_code'] ?? $id),
                'questionsCount' => (int) ($section['questionsCount'] ?? 10),
                'startQ' => (int) ($section['startQ'] ?? 1),
                'optionsCount' => (int) ($section['optionsCount'] ?? 4),
                'digitsCount' => (int) ($section['digitsCount'] ?? 0),
                'bubbleDiameterMm' => (float) ($section['bubbleDiameterMm'] ?? 4.5),
                'bubblePitchMm' => (float) ($section['bubblePitchMm'] ?? 6.0),
                'rowPitchMm' => (float) ($section['rowPitchMm'] ?? 6.8),
                'showSectionHeader' => (bool) ($section['showSectionHeader'] ?? true),
            ];
        }

        return self::visualComponents($components);
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
            $origin = (array) ($block['origin'] ?? [0, 0]);
            if ($inputIsPaperSpace) {
                $origin = [(int) $origin[0] - $insetsPx['left'], (int) $origin[1] - $insetsPx['top']];
            } else {
                $origin = [(int) $origin[0], (int) $origin[1]];
            }
            if ($origin[0] < 0 || $origin[1] < 0 || $origin[0] >= $frame['width'] || $origin[1] >= $frame['height']) {
                throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: field block %s origin is outside the registration frame.', (string) $name));
            }
            $block['origin'] = $origin;
            if (array_key_exists('bubblePositions', $block)) {
                if (! is_array($block['bubblePositions']) || ! array_is_list($block['bubblePositions'])) {
                    throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: field block %s has invalid bubble positions.', (string) $name));
                }
                $bubbleDimensions = (array) ($block['bubbleDimensions'] ?? []);
                foreach ($block['bubblePositions'] as $rowIndex => $row) {
                    if (! is_array($row) || ! array_is_list($row)) {
                        throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: field block %s has invalid bubble positions.', (string) $name));
                    }
                    foreach ($row as $optionIndex => $position) {
                        if (! is_array($position) || count($position) !== 2 || ! is_int($position[0] ?? null) || ! is_int($position[1] ?? null)) {
                            throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: field block %s has invalid bubble positions.', (string) $name));
                        }
                        $x = $position[0] - ($inputIsPaperSpace ? $insetsPx['left'] : 0);
                        $y = $position[1] - ($inputIsPaperSpace ? $insetsPx['top'] : 0);
                        if ($x < 0 || $y < 0
                            || $x + (int) ($bubbleDimensions[0] ?? 0) > $frame['width']
                            || $y + (int) ($bubbleDimensions[1] ?? 0) > $frame['height']) {
                            throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: field block %s bubble position is outside the registration frame.', (string) $name));
                        }
                        $block['bubblePositions'][$rowIndex][$optionIndex] = [$x, $y];
                    }
                }
                $block['bubblePositions'] = array_values(array_map('array_values', $block['bubblePositions']));
            }
            $componentId = (string) preg_replace('/_col\d+$/', '', (string) $name);
            $component = $byId[(string) $name] ?? $byId[$componentId] ?? null;
            $block['page_no'] = max(1, (int) ($block['page_no'] ?? ($component['page_no'] ?? 1)));
            if (is_array($component)) {
                // Mapping field_blocks are the validated recognition authority.
                // Visual component metadata may fill absent display hints only;
                // it must never rewrite labels, options, or physical geometry.
                if (! array_key_exists('displayLabel', $block)) {
                    $label = $component['type'] === 'roll'
                        ? ($component['title'] ?? 'Examinee Number')
                        : ($component['subjectName'] ?? $component['name'] ?? $name);
                    $block['displayLabel'] = (string) $label;
                }
                if (! array_key_exists('showSectionHeader', $block)) {
                    $block['showSectionHeader'] = ($component['showSectionHeader'] ?? true) === true;
                }
            }
            $normalized[(string) $name] = $block;
        }

        return $normalized;
    }

    /**
     * Derive deterministic recognition field blocks from visual components.
     * If existing mapping blocks were provided, custom labels/values are reconciled
     * while the physical bubble coordinates are anchored to the canonical component.
     *
     * @param  list<array<string,mixed>>  $components
     * @param  array<string,mixed>  $existingFieldBlocks
     * @return array<string,mixed>
     */
    private static function fieldBlocksFromComponents(
        array $components,
        array $insetsPx,
        array $frame,
        array $existingFieldBlocks = [],
    ): array {
        $blocks = [];
        foreach ($components as $component) {
            $type = (string) ($component['type'] ?? '');
            if ($type !== 'roll' && ! in_array($type, ['mcq', 'tf', 'likert', 'custom'], true)) {
                continue;
            }
            $id = (string) $component['id'];
            $existing = (array) ($existingFieldBlocks[$id] ?? []);
            $pageNo = max(1, (int) ($component['page_no'] ?? 1));
            $originOffset = $type === 'roll' ? ['x' => 6.4, 'y' => 14.0] : ['x' => 5.0, 'y' => 8.0];
            $origin = [
                OmrPageDimensionMath::pixelsFromMillimetres((float) $component['posX'] + $originOffset['x']) - $insetsPx['left'],
                OmrPageDimensionMath::pixelsFromMillimetres((float) $component['posY'] + $originOffset['y']) - $insetsPx['top'],
            ];

            if ($origin[0] < 0 || $origin[1] < 0 || $origin[0] >= $frame['width'] || $origin[1] >= $frame['height']) {
                throw new RuntimeException(sprintf('canonical_definition_geometry_invalid: component %s is placed outside the printable registration frame.', $id));
            }

            $bubble = OmrPageDimensionMath::pixelsFromMillimetres((float) ($component['bubbleDiameterMm'] ?? 4.5));
            $bubblePitch = OmrPageDimensionMath::pixelsFromMillimetres((float) ($component['bubblePitchMm'] ?? 6.0));
            $rowPitch = OmrPageDimensionMath::pixelsFromMillimetres((float) ($component['rowPitchMm'] ?? 6.8));
            $count = max(1, (int) ($component[$type === 'roll' ? 'digitsCount' : 'questionsCount'] ?? 1));

            if ($type === 'roll') {
                $fieldLabels = $count > 1 ? ['APPLICANT_NUMBER_1..'.$count] : ['APPLICANT_NUMBER_1'];
                $blocks[$id] = [
                    'fieldType' => 'QTYPE_INT',
                    'direction' => 'vertical',
                    'fieldLabels' => $fieldLabels,
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
            if (isset($existing['bubbleValues']) && is_array($existing['bubbleValues']) && $existing['bubbleValues'] !== []) {
                $options = array_values(array_map('strval', $existing['bubbleValues']));
            }

            $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($component['aptitudeAreaCode'] ?? $id)));
            $start = max(1, (int) ($component['startQ'] ?? 1));
            $end = $start + $count - 1;
            $fieldLabels = $start === $end ? [$prefix.'_Q_'.$start] : [$prefix.'_Q_'.$start.'..'.$end];
            if (isset($existing['fieldLabels']) && is_array($existing['fieldLabels']) && $existing['fieldLabels'] !== []) {
                $fieldLabels = array_values(array_map('strval', $existing['fieldLabels']));
            }

            $blocks[$id] = [
                ...($options === ['A', 'B', 'C', 'D']
                    ? ['fieldType' => 'QTYPE_MCQ4']
                    : ($options === ['A', 'B', 'C', 'D', 'E']
                        ? ['fieldType' => 'QTYPE_MCQ5']
                        : ['bubbleValues' => $options])),
                'direction' => 'horizontal',
                'fieldLabels' => $fieldLabels,
                'bubblesGap' => $bubblePitch,
                'labelsGap' => $rowPitch,
                'origin' => $origin,
                'bubbleDimensions' => [$bubble, $bubble],
                'displayLabel' => (string) ($component['subjectName'] ?? $component['name'] ?? $id),
                'showSectionHeader' => ($component['showSectionHeader'] ?? true) === true,
                'page_no' => $pageNo,
            ];
        }

        return $blocks;
    }

    /**
     * Reconcile component coordinates to match the validated mapping field blocks
     * so identity is unified and move-away/move-back remains byte-identical.
     *
     * @param  list<array<string,mixed>>  $components
     * @param  array<string,mixed>  $fieldBlocks
     * @param  array{top:int,right:int,bottom:int,left:int}  $insetsPx
     * @return list<array<string,mixed>>
     */
    private static function reconcileComponentsWithFieldBlocks(array $components, array $fieldBlocks, array $insetsPx): array
    {
        if ($components === [] || $fieldBlocks === []) {
            return $components;
        }

        $reconciled = [];
        foreach ($components as $component) {
            $id = (string) ($component['id'] ?? '');
            $block = $fieldBlocks[$id] ?? null;
            if (! is_array($block)) {
                foreach ($fieldBlocks as $blockName => $candidateBlock) {
                    if (is_array($candidateBlock) && str_starts_with((string) $blockName, $id)) {
                        $block = $candidateBlock;
                        break;
                    }
                }
            }

            if (is_array($block)) {
                $type = (string) ($component['type'] ?? '');
                $originOffset = $type === 'roll' ? ['x' => 6.4, 'y' => 14.0] : ['x' => 5.0, 'y' => 8.0];
                $origin = (array) ($block['origin'] ?? [0, 0]);
                $paperX = (float) ((int) $origin[0] + $insetsPx['left']);
                $paperY = (float) ((int) $origin[1] + $insetsPx['top']);
                $component['posX'] = round(OmrPageDimensionMath::millimetresFromPixels($paperX) - $originOffset['x'], 2);
                $component['posY'] = round(OmrPageDimensionMath::millimetresFromPixels($paperY) - $originOffset['y'], 2);
                if (isset($block['page_no'])) {
                    $component['page_no'] = max(1, (int) $block['page_no']);
                }
                if (isset($block['bubbleDimensions'][0]) && (int) $block['bubbleDimensions'][0] > 0) {
                    $component['bubbleDiameterMm'] = round(OmrPageDimensionMath::millimetresFromPixels((float) $block['bubbleDimensions'][0]), 2);
                }
                if ($type !== 'roll') {
                    if (isset($block['bubblesGap']) && (int) $block['bubblesGap'] > 0) {
                        $component['bubblePitchMm'] = round(OmrPageDimensionMath::millimetresFromPixels((float) $block['bubblesGap']), 2);
                    }
                    if (isset($block['labelsGap']) && (int) $block['labelsGap'] > 0) {
                        $component['rowPitchMm'] = round(OmrPageDimensionMath::millimetresFromPixels((float) $block['labelsGap']), 2);
                    }
                } else {
                    if (isset($block['labelsGap']) && (int) $block['labelsGap'] > 0) {
                        $component['bubblePitchMm'] = round(OmrPageDimensionMath::millimetresFromPixels((float) $block['labelsGap']), 2);
                    }
                    if (isset($block['bubblesGap']) && (int) $block['bubblesGap'] > 0) {
                        $component['rowPitchMm'] = round(OmrPageDimensionMath::millimetresFromPixels((float) $block['bubblesGap']), 2);
                    }
                }
            }

            $reconciled[] = $component;
        }

        return $reconciled;
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
