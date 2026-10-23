<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrTemplateAuthoringDraft;

use function array_unique;
use function config;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function str_starts_with;
use function strlen;
use function trim;

final class OmrMappingValidator
{
    /**
     * @param  array<string, mixed>  $mapping
     * @param  array{page_width?:int,page_height?:int,page_count?:int,marker_zones?:list<array<string,mixed>>,content_zones?:list<array<string,mixed>>,require_declaration?:bool,is_uploaded?:bool,source_kind?:string,minimum_bubble_mm?:float}  $context
     * @return array{valid:bool,input:array<string,mixed>,errors:list<array{code:string,message:string,block?:string,row?:int}>,warnings:list<string>,field_blocks:array<string,mixed>}
     */
    public function validate(array $mapping, array $context = []): array
    {
        $errors = [];
        $warnings = [];
        $fieldBlocks = [];
        $labels = [];
        $regions = [];
        $bubbleDimensionsByBlock = [];
        $containsExplicitPositions = false;
        $isUploaded = ($context['is_uploaded'] ?? false) === true
            || in_array(($context['source_kind'] ?? $mapping['source_kind'] ?? ''), [
                OmrTemplateAuthoringDraft::SOURCE_PDF,
                OmrTemplateAuthoringDraft::SOURCE_IMAGE,
                OmrTemplateAuthoringDraft::SOURCE_DOCX,
                OmrSourcePolicy::UPLOADED_ORIGINAL,
            ], true)
            || (($context['require_declaration'] ?? false) === true);
        $minimumAllowedBubbleMm = (float) ($context['minimum_bubble_mm'] ?? ($isUploaded
            ? config('omr.canonical.limits.minimum_uploaded_bubble_mm', 2.5)
            : config('omr.canonical.limits.minimum_bubble_mm', 4.2)));
        $pageWidth = (int) ($context['page_width'] ?? $mapping['page_width'] ?? 0);
        $pageHeight = (int) ($context['page_height'] ?? $mapping['page_height'] ?? 0);
        $pageCount = (int) ($context['page_count'] ?? $mapping['page_count'] ?? 1);
        $paperWidth = (float) ($context['paper_width_mm'] ?? $mapping['paper_width_mm'] ?? 210.0);
        $paperHeight = (float) ($context['paper_height_mm'] ?? $mapping['paper_height_mm'] ?? 297.0);
        $documentFields = $mapping['studio']['document_fields']
            ?? $mapping['input']['document_fields']
            ?? $mapping['document_fields']
            ?? [];
        array_push(
            $errors,
            ...$this->validateDocumentFields(
                $documentFields,
                $pageCount,
                $paperWidth,
                $paperHeight,
            ),
        );
        if (($context['test_instrument_version_id'] ?? null) !== null) {
            $this->validateInstrumentMapping($mapping, $context, $errors);
        }
        $blocks = $mapping['blocks'] ?? [];
        if (! is_array($blocks) || $blocks === []) {
            $errors[] = ['code' => 'mapping_empty', 'message' => 'At least one OMR block is required.'];

            return ['valid' => false, 'input' => $mapping, 'errors' => $errors, 'warnings' => [], 'field_blocks' => []];
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                $errors[] = ['code' => 'block_invalid', 'message' => "Block {$index} is invalid."];

                continue;
            }
            $name = (string) ($block['name'] ?? "Block{$index}");
            $pageNo = (int) ($block['page_no'] ?? 1);
            $fieldType = (string) ($block['field_type'] ?? 'QTYPE_MCQ4');
            $bubbleValues = null;
            if (array_key_exists('bubble_values', $block)) {
                if (! is_array($block['bubble_values'])) {
                    $errors[] = ['code' => 'option_set_unsupported', 'message' => "Block {$name} uses an unsupported option set.", 'block' => $name];

                    continue;
                }
                $rawBubbleValues = $block['bubble_values'];
                $validBubbleValues = count($rawBubbleValues) >= 2 && count($rawBubbleValues) <= 10;
                foreach ($rawBubbleValues as $value) {
                    if (! is_string($value) || trim($value) === '' || strlen($value) > 32) {
                        $validBubbleValues = false;

                        break;
                    }
                }
                if (! $validBubbleValues || count(array_unique($rawBubbleValues, SORT_STRING)) !== count($rawBubbleValues)) {
                    $errors[] = ['code' => 'option_set_unsupported', 'message' => "Block {$name} uses an unsupported option set.", 'block' => $name];

                    continue;
                }
                $bubbleValues = array_values(array_map('strval', $rawBubbleValues));
                $options = count($bubbleValues);
                $expectedOptions = $options;
            } else {
                $options = (int) ($block['options'] ?? match ($fieldType) {
                    'QTYPE_MCQ5' => 5,
                    'QTYPE_INT' => 10,
                    default => 4,
                });
                $expectedOptions = match ($fieldType) {
                    'QTYPE_MCQ4' => 4,
                    'QTYPE_MCQ5' => 5,
                    'QTYPE_INT' => 10,
                    default => null,
                };
            }
            if ($expectedOptions === null || $options !== $expectedOptions) {
                $errors[] = ['code' => 'option_set_unsupported', 'message' => "Block {$name} uses an unsupported option set.", 'block' => $name];

                continue;
            }
            if ($pageNo < 1 || $pageNo > $pageCount) {
                $errors[] = ['code' => 'page_parity_mismatch', 'message' => "Block {$name} references page {$pageNo} outside the source page count.", 'block' => $name];
            }
            $originX = (int) ($block['origin_x'] ?? 0);
            $originY = (int) ($block['origin_y'] ?? 0);
            $width = (int) ($block['width'] ?? 0);
            $height = (int) ($block['height'] ?? 0);
            $rows = (int) ($block['rows'] ?? 0);
            $optionPitch = (int) ($block['option_pitch_px'] ?? 0);
            $rowPitch = (int) ($block['row_pitch_px'] ?? 0);
            $bubbleWidth = (int) ($block['bubble_width_px'] ?? 0);
            $bubbleHeight = (int) ($block['bubble_height_px'] ?? $bubbleWidth);
            if ($width <= 0 || $height <= 0 || $rows <= 0 || $optionPitch <= 0 || $rowPitch <= 0 || $bubbleWidth <= 0 || $bubbleHeight <= 0) {
                $errors[] = ['code' => 'block_geometry_invalid', 'message' => "Block {$name} has invalid geometry.", 'block' => $name];

                continue;
            }
            $bubbleDimensionsByBlock[$name] = [$bubbleWidth, $bubbleHeight];
            if (OmrPageDimensionMath::millimetresFromPixels(min($bubbleWidth, $bubbleHeight)) < $minimumAllowedBubbleMm) {
                $errors[] = ['code' => 'bubble_too_small', 'message' => "Block {$name} has bubbles below the minimum physical diameter.", 'block' => $name];
            }
            $edgeGap = $optionPitch - $bubbleWidth;
            $minimumGapPx = OmrPageDimensionMath::pixelsFromMillimetres((float) config('omr.canonical.limits.minimum_option_edge_gap_mm', 1.26));
            if ($edgeGap < $minimumGapPx) {
                $errors[] = ['code' => 'pitch_too_tight', 'message' => "Block {$name} has insufficient option edge gap.", 'block' => $name];
            }
            $hasExplicitPositions = array_key_exists('bubble_positions', $block);
            $containsExplicitPositions = $containsExplicitPositions || $hasExplicitPositions;
            if ($hasExplicitPositions && ($pageWidth <= 0 || $pageHeight <= 0)) {
                $errors[] = ['code' => 'bubble_positions_invalid', 'message' => "Block {$name} explicit positions require positive page dimensions.", 'block' => $name];
            }
            $blockLabels = $this->nativeLabels($name, $fieldType, (array) ($block['labels'] ?? []), $rows);
            if ($blockLabels === []) {
                $prefix = (string) ($block['label_prefix'] ?? $name.'_Q_');
                for ($row = 1; $row <= $rows; $row++) {
                    $blockLabels[] = sprintf('%s%03d', $prefix, $row);
                }
            }
            $positions = null;
            if ($hasExplicitPositions) {
                $positions = $block['bubble_positions'];
                $positionShapeValid = is_array($positions)
                    && array_is_list($positions)
                    && count($positions) === count($blockLabels);
                if ($positionShapeValid) {
                    foreach ($positions as $positionRow) {
                        if (! is_array($positionRow) || ! array_is_list($positionRow) || count($positionRow) !== $options) {
                            $positionShapeValid = false;
                            break;
                        }
                        foreach ($positionRow as $position) {
                            if (! is_array($position) || array_is_list($position) === false
                                || count($position) !== 2 || ! is_int($position[0] ?? null) || ! is_int($position[1] ?? null)) {
                                $positionShapeValid = false;
                                break 2;
                            }
                        }
                    }
                }
                if (! $positionShapeValid) {
                    $errors[] = ['code' => 'bubble_positions_invalid', 'message' => "Block {$name} explicit bubble positions must match its field labels and options.", 'block' => $name];
                } else {
                    $minX = PHP_INT_MAX;
                    $minY = PHP_INT_MAX;
                    $maxX = PHP_INT_MIN;
                    $maxY = PHP_INT_MIN;
                    $bubbleRects = [];
                    $minimumGapPx = max(1, OmrPageDimensionMath::pixelsFromMillimetres((float) config('omr.canonical.limits.minimum_option_edge_gap_mm', 1.26)) - 1);
                    foreach ($positions as $positionRow) {
                        foreach ($positionRow as $position) {
                            [$x, $y] = $position;
                            $rect = ['x' => $x, 'y' => $y, 'w' => $bubbleWidth, 'h' => $bubbleHeight];
                            $minX = min($minX, $x);
                            $minY = min($minY, $y);
                            $maxX = max($maxX, $x + $bubbleWidth);
                            $maxY = max($maxY, $y + $bubbleHeight);
                            if ($pageWidth > 0 && ($x < 0 || $y < 0 || $x + $bubbleWidth >= $pageWidth || $y + $bubbleHeight >= $pageHeight)) {
                                $errors[] = ['code' => 'out_of_bounds', 'message' => "Block {$name} has a bubble outside the answer-sheet page.", 'block' => $name];
                            }
                            foreach ($bubbleRects as $otherRect) {
                                if ($this->overlaps($rect, $otherRect)) {
                                    $errors[] = ['code' => 'bubble_positions_overlap', 'message' => "Block {$name} contains overlapping explicit bubbles.", 'block' => $name];
                                    break;
                                }
                                $gapX = max(0, $otherRect['x'] - ($x + $bubbleWidth), $x - ($otherRect['x'] + $otherRect['w']));
                                $gapY = max(0, $otherRect['y'] - ($y + $bubbleHeight), $y - ($otherRect['y'] + $otherRect['h']));
                                if ($gapX * $gapX + $gapY * $gapY < $minimumGapPx * $minimumGapPx) {
                                    $errors[] = ['code' => 'bubble_positions_too_close', 'message' => "Block {$name} has explicit bubbles below the minimum edge gap.", 'block' => $name];
                                    break;
                                }
                            }
                            $bubbleRects[] = $rect;
                        }
                    }
                    $originX = $minX;
                    $originY = $minY;
                    $width = $maxX - $minX;
                    $height = $maxY - $minY;
                }
            }
            $isVertical = ($block['direction'] ?? 'horizontal') === 'vertical';
            if ($positions === null) {
                $digitCount = count((array) ($block['labels'] ?? []));
                $requiredWidth = $isVertical
                    ? $bubbleWidth + (max(1, $digitCount) - 1) * $rowPitch
                    : $bubbleWidth + ($options - 1) * $optionPitch;
                $requiredHeight = $isVertical
                    ? $bubbleHeight + ($rows - 1) * $optionPitch
                    : $bubbleHeight + ($rows - 1) * $rowPitch;
                if ($requiredWidth > $width || $requiredHeight > $height) {
                    $code = ($block['split_complete'] ?? false) === true ? 'overfull_row' : 'explicit_split_required';
                    $errors[] = ['code' => $code, 'message' => "Block {$name} is overfull; explicitly split irregular rows before saving.", 'block' => $name];
                }
            }
            if ($pageWidth > 0 && ($originX < 0 || $originY < 0 || $originX + $width > $pageWidth || $originY + $height > $pageHeight)) {
                $errors[] = ['code' => 'out_of_bounds', 'message' => "Block {$name} lies outside the answer-sheet page.", 'block' => $name];
            }
            $region = ['page_no' => $pageNo, 'x' => $originX, 'y' => $originY, 'w' => $width, 'h' => $height, 'name' => $name];
            foreach ($regions as $existing) {
                if ($existing['page_no'] === $pageNo && $this->overlaps($region, $existing)) {
                    $errors[] = ['code' => 'block_overlap', 'message' => "Block {$name} overlaps {$existing['name']}.", 'block' => $name];
                }
            }
            foreach ([...(array) ($context['marker_zones'] ?? []), ...(array) ($context['content_zones'] ?? [])] as $zone) {
                if (($zone['page_no'] ?? $pageNo) === $pageNo && $this->overlaps($region, $zone)) {
                    $errors[] = ['code' => 'region_collision', 'message' => "Block {$name} collides with a protected page region.", 'block' => $name];
                }
            }
            $regions[] = $region;

            if (! $isVertical && count($blockLabels) !== $rows) {
                $errors[] = ['code' => 'label_count_mismatch', 'message' => "Block {$name} label count does not match rows.", 'block' => $name];
            }
            foreach ($blockLabels as $row => $label) {
                $isIdentityLabel = str_starts_with($label, 'APPLICANT_NUMBER_') || str_starts_with($label, 'BIRTHDATE_');
                $labelKey = $isIdentityLabel ? $pageNo.':'.$label : $label;
                if (isset($labels[$labelKey])) {
                    $errors[] = ['code' => 'duplicate_label', 'message' => "Label {$label} is duplicated.", 'block' => $name, 'row' => $row + 1];
                }
                $labels[$labelKey] = true;
            }
            $fieldBlocks[$name] = [
                ...($bubbleValues !== null ? ['bubbleValues' => $bubbleValues] : ['fieldType' => $fieldType]),
                'direction' => (string) ($block['direction'] ?? 'horizontal'),
                'fieldLabels' => array_values($blockLabels),
                'bubblesGap' => $optionPitch,
                'origin' => [$originX, $originY],
                'bubbleDimensions' => [$bubbleWidth, $bubbleHeight],
                'labelsGap' => $rowPitch,
                ...($hasExplicitPositions && is_array($positions) && $positionShapeValid ? ['bubblePositions' => array_values($positions)] : []),
                'page_no' => $pageNo,
            ];
        }

        $numberedGroups = [];
        foreach (array_keys($labels) as $label) {
            $normalizedLabel = preg_replace('/^\d+:/', '', (string) $label) ?? (string) $label;
            if (str_starts_with($normalizedLabel, 'APPLICANT_NUMBER_') || str_starts_with($normalizedLabel, 'BIRTHDATE_')) {
                continue;
            }
            if (preg_match('/^(.*?)(\d+)$/', $normalizedLabel, $matches) === 1) {
                $numberedGroups[$matches[1]][] = (int) $matches[2];
            }
        }
        foreach ($numberedGroups as $prefix => $numbers) {
            sort($numbers);
            $expected = range($numbers[0], $numbers[count($numbers) - 1]);
            if ($numbers !== $expected) {
                $warnings[] = sprintf('missing_question:%s expected %s.', $prefix, implode(',', array_diff($expected, $numbers)));
            }
        }

        if (($context['require_declaration'] ?? false) === true) {
            $this->validateUploadedDeclaration($mapping, $fieldBlocks, $pageCount, $pageWidth, $pageHeight, $errors);
        }

        return [
            'valid' => $errors === [],
            'input' => $mapping,
            'errors' => $errors,
            'warnings' => $warnings,
            'field_blocks' => $fieldBlocks,
        ];
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  list<array<string, mixed>>  $subtests
     * @return array<string, mixed>
     */
    public function normalizeInstrumentBindings(array $mapping, array $subtests): array
    {
        if ($subtests === []) {
            return $mapping;
        }
        $byCode = [];
        foreach ($subtests as $subtest) {
            if (is_array($subtest)) {
                $byCode[(string) ($subtest['code'] ?? '')] = $subtest;
            }
        }
        foreach ((array) ($mapping['blocks'] ?? []) as $blockIdx => $block) {
            if (! is_array($block) || $this->isIdentityBlock($block)) {
                continue;
            }
            $subtestCode = (string) ($block['subtest_code'] ?? '');
            $subtest = $byCode[$subtestCode] ?? null;
            $labels = array_values((array) ($block['labels'] ?? []));
            $itemCodes = array_values((array) ($block['item_codes'] ?? []));
            if ($itemCodes === [] && is_array($subtest)) {
                $subItems = collect($subtest['items'] ?? []);
                foreach ($labels as $label) {
                    if (preg_match('/_Q_([1-9][0-9]*)$/', (string) $label, $m)) {
                        $qNum = (string) (int) $m[1];
                        $matched = $subItems->first(fn ($it): bool => (string) ($it['local_number'] ?? $it['display_order'] ?? '') === $qNum);
                        if ($matched !== null) {
                            $itemCodes[] = (string) $matched['item_code'];
                        }
                    }
                }
                if ($itemCodes !== []) {
                    $mapping['blocks'][$blockIdx]['item_codes'] = $itemCodes;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function isIdentityBlock(array $block): bool
    {
        $blockKind = strtolower((string) ($block['type'] ?? $block['kind'] ?? ''));
        $blockName = strtoupper((string) ($block['name'] ?? $block['id'] ?? ''));
        $fieldType = (string) ($block['field_type'] ?? $block['fieldType'] ?? '');
        if (in_array($blockKind, ['roll', 'identity'], true)
            || $fieldType === 'QTYPE_INT'
            || ($block['identity_grid'] ?? false) === true
            || $blockName === 'STUDENT_ID'
            || str_starts_with($blockName, 'APPLICANT_NUMBER')
            || str_starts_with($blockName, 'EXAM_DATE')
            || str_starts_with($blockName, 'BIRTHDATE')) {
            return true;
        }
        foreach ((array) ($block['labels'] ?? []) as $label) {
            $upperLabel = strtoupper((string) $label);
            if (str_starts_with($upperLabel, 'APPLICANT_NUMBER_')
                || str_starts_with($upperLabel, 'BIRTHDATE_')
                || str_starts_with($upperLabel, 'EXAM_DATE_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $context
     * @param  list<array{code:string,message:string,block?:string,row?:int}>  $errors
     */
    private function validateInstrumentMapping(array &$mapping, array $context, array &$errors): void
    {
        $subtests = (array) ($context['instrument_subtests'] ?? []);
        $mapping = $this->normalizeInstrumentBindings($mapping, $subtests);
        $byCode = [];
        $scored = [];
        foreach ($subtests as $subtest) {
            if (! is_array($subtest)) {
                continue;
            }
            $byCode[(string) ($subtest['code'] ?? '')] = $subtest;
            foreach ((array) ($subtest['items'] ?? []) as $item) {
                if (is_array($item) && ($item['is_scored'] ?? false) === true) {
                    $scored[(string) $subtest['code'].'/'.(string) ($item['item_code'] ?? '')] = false;
                }
            }
        }
        foreach ((array) ($mapping['blocks'] ?? []) as $blockIdx => $block) {
            if (! is_array($block) || $this->isIdentityBlock($block)) {
                continue;
            }
            $subtestCode = (string) ($block['subtest_code'] ?? '');
            $subtest = $byCode[$subtestCode] ?? null;
            $labels = array_values((array) ($block['labels'] ?? []));
            $itemCodes = array_values((array) ($block['item_codes'] ?? []));
            if ($itemCodes === [] && is_array($subtest)) {
                $subItems = collect($subtest['items'] ?? []);
                foreach ($labels as $label) {
                    if (preg_match('/_Q_([1-9][0-9]*)$/', (string) $label, $m)) {
                        $qNum = (string) (int) $m[1];
                        $matched = $subItems->first(fn ($it): bool => (string) ($it['local_number'] ?? $it['display_order'] ?? '') === $qNum);
                        if ($matched !== null) {
                            $itemCodes[] = (string) $matched['item_code'];
                        }
                    }
                }
                if ($itemCodes !== []) {
                    $mapping['blocks'][$blockIdx]['item_codes'] = $itemCodes;
                }
            }
            if (! is_array($subtest) || count($itemCodes) !== count($labels)) {
                $errors[] = ['code' => 'instrument_item_mapping_invalid', 'message' => 'Every mapped question must identify a profile subtest and item code.', 'block' => (string) ($block['name'] ?? '')];

                continue;
            }
            $items = [];
            foreach ((array) ($subtest['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $items[(string) ($item['item_code'] ?? '')] = $item;
                }
            }
            $tokens = $block['bubble_values'] ?? match ($block['field_type'] ?? $block['fieldType'] ?? $block['type'] ?? null) {
                'QTYPE_MCQ5', 'likert' => ['A', 'B', 'C', 'D', 'E'],
                'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                'tf' => ['T', 'F'],
                default => ['A', 'B', 'C', 'D'],
            };
            foreach ($itemCodes as $row => $itemCode) {
                $item = $items[(string) $itemCode] ?? null;
                if (! is_array($item)) {
                    $errors[] = ['code' => 'instrument_item_mapping_invalid', 'message' => 'A mapped question references an unknown item in its subtest.', 'block' => (string) ($block['name'] ?? ''), 'row' => $row + 1];

                    continue;
                }
                $declaredTokens = array_values((array) ($item['option_tokens'] ?? []));
                if (! is_array($tokens) || array_values($tokens) !== $declaredTokens) {
                    $errors[] = ['code' => 'instrument_option_tokens_mismatch', 'message' => 'Mapped response options must exactly match the profile item tokens and order.', 'block' => (string) ($block['name'] ?? ''), 'row' => $row + 1];
                }
                $identity = $subtestCode.'/'.(string) $itemCode;
                if (array_key_exists($identity, $scored)) {
                    if ($scored[$identity]) {
                        $errors[] = ['code' => 'instrument_item_duplicate', 'message' => 'A scored profile item is mapped more than once.', 'block' => (string) ($block['name'] ?? ''), 'row' => $row + 1];
                    }
                    $scored[$identity] = true;
                }
            }
        }
        foreach ($scored as $identity => $mapped) {
            if (! $mapped) {
                $errors[] = ['code' => 'instrument_item_omitted', 'message' => "Scored profile item {$identity} is not mapped."];
            }
        }
    }

    /**
     * @return list<array{code:string,message:string,row?:int}>
     */
    public function validateDocumentFields(
        mixed $documentFields,
        int $pageCount,
        float $paperWidthMm,
        float $paperHeightMm,
    ): array {
        if ($documentFields === null || $documentFields === []) {
            return [];
        }
        if (! is_array($documentFields)) {
            return [['code' => 'document_fields_invalid', 'message' => 'Document fields must be provided as a list.']];
        }

        $errors = [];
        $fieldIds = [];
        foreach ($documentFields as $index => $field) {
            if (! is_array($field)) {
                $errors[] = [
                    'code' => 'document_field_invalid',
                    'message' => 'Each document field must be an object.',
                    'row' => (int) $index,
                ];

                continue;
            }

            $fieldId = $field['id'] ?? null;
            if (! is_string($fieldId) || trim($fieldId) === '') {
                $errors[] = [
                    'code' => 'document_field_id_required',
                    'message' => 'Each document field must have a non-empty ID.',
                    'row' => (int) $index,
                ];
            } elseif (isset($fieldIds[$fieldId])) {
                $errors[] = [
                    'code' => 'document_field_id_duplicate',
                    'message' => sprintf('Document field ID "%s" must be unique within the draft.', $fieldId),
                    'row' => (int) $index,
                ];
            } else {
                $fieldIds[$fieldId] = true;
            }

            $pageNo = $field['page_no'] ?? null;
            if (! is_numeric($pageNo)
                || (float) $pageNo !== (float) (int) $pageNo
                || (int) $pageNo < 1
                || (int) $pageNo > $pageCount) {
                $errors[] = [
                    'code' => 'document_field_page_out_of_range',
                    'message' => sprintf('Document field page must be an integer between 1 and %d.', $pageCount),
                    'row' => (int) $index,
                ];
            }

            $x = $field['x_mm'] ?? null;
            $y = $field['y_mm'] ?? null;
            $width = $field['w_mm'] ?? null;
            $height = $field['h_mm'] ?? null;
            if (! is_numeric($x) || ! is_numeric($y) || ! is_numeric($width) || ! is_numeric($height)
                || (float) $x < 0 || (float) $y < 0 || (float) $width <= 0 || (float) $height <= 0) {
                $errors[] = [
                    'code' => 'document_field_geometry_invalid',
                    'message' => 'Document field coordinates and dimensions must be valid millimetres.',
                    'row' => (int) $index,
                ];

                continue;
            }
            if ((float) $x + (float) $width > $paperWidthMm || (float) $y + (float) $height > $paperHeightMm) {
                $errors[] = [
                    'code' => 'document_field_out_of_bounds',
                    'message' => 'Document field extends beyond the paper dimensions.',
                    'row' => (int) $index,
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string,mixed>  $mapping
     * @param  array<string,mixed>  $fieldBlocks
     * @param  list<array{code:string,message:string,block?:string,row?:int}>  $errors
     */
    private function validateUploadedDeclaration(array $mapping, array $fieldBlocks, int $pageCount, int $pageWidth, int $pageHeight, array &$errors): void
    {
        $inventory = $mapping['declared_inventory'] ?? null;
        $declared = [];
        if (! is_array($inventory) || $inventory === []) {
            $errors[] = ['code' => 'inventory_missing', 'message' => 'Declare every printed question and option before mapping.'];
        } else {
            foreach ($inventory as $entry) {
                if (! is_array($entry)) {
                    $errors[] = ['code' => 'inventory_invalid', 'message' => 'Question inventory entries must be objects.'];

                    continue;
                }
                $pageNo = $entry['page_no'] ?? null;
                $area = $entry['area_code'] ?? null;
                $count = $entry['question_count'] ?? null;
                $options = $entry['option_labels'] ?? null;
                if (! is_int($pageNo) || $pageNo < 1 || $pageNo > $pageCount
                    || ! is_string($area) || preg_match('/^[A-Z][A-Z0-9_]*$/', $area) !== 1
                    || ! is_int($count) || $count < 1 || $count > 1000
                    || ! is_array($options) || count($options) < 2 || count($options) > 10
                    || array_filter($options, static fn ($option): bool => ! is_string($option) || trim($option) === '' || strlen($option) > 32) !== []
                    || count(array_unique($options, SORT_STRING)) !== count($options)) {
                    $errors[] = ['code' => 'inventory_invalid', 'message' => 'Each page and area needs a valid question count and 2 to 10 unique ordered option labels.'];

                    continue;
                }
                $key = $pageNo.':'.$area;
                if (isset($declared[$key])) {
                    $errors[] = ['code' => 'inventory_duplicate', 'message' => "Area {$area} is declared twice on page {$pageNo}."];

                    continue;
                }
                $declared[$key] = ['count' => $count, 'options' => $options];
            }
        }

        $observed = [];
        $areaNumbers = [];
        $identityByPage = [];
        foreach ($fieldBlocks as $block) {
            $pageNo = (int) $block['page_no'];
            $labels = (array) $block['fieldLabels'];
            if (($block['fieldType'] ?? null) === 'QTYPE_INT') {
                foreach ($labels as $label) {
                    if (preg_match('/^APPLICANT_NUMBER_([1-9][0-9]*)$/', (string) $label, $matches) !== 1) {
                        $errors[] = ['code' => 'identity_labels_invalid', 'message' => 'Applicant Number positions must use ordered APPLICANT_NUMBER labels.'];
                    } else {
                        $identityByPage[$pageNo][] = (int) $matches[1];
                    }
                }

                continue;
            }
            foreach ($labels as $label) {
                if (preg_match('/^([A-Z][A-Z0-9_]*)_Q_([1-9][0-9]*)$/', (string) $label, $matches) !== 1) {
                    $errors[] = ['code' => 'inventory_label_invalid', 'message' => "Mapped question {$label} needs a declared area and number."];

                    continue;
                }
                $key = $pageNo.':'.$matches[1];
                $observed[$key][] = (int) $matches[2];
                $areaNumbers[$matches[1]][] = (int) $matches[2];
                if (isset($declared[$key])) {
                    $blockOptions = (array) ($block['bubbleValues'] ?? match ($block['fieldType'] ?? null) {
                        'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
                        'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                        default => ['A', 'B', 'C', 'D'],
                    });
                    if (count($declared[$key]['options']) !== count($blockOptions) || $declared[$key]['options'] !== $blockOptions) {
                        $errors[] = ['code' => 'inventory_option_mismatch', 'message' => "Mapped options for {$matches[1]} on page {$pageNo} differ from the declaration."];
                    }
                }
            }
        }
        foreach ($declared as $key => $expected) {
            if (count($observed[$key] ?? []) !== $expected['count']) {
                $errors[] = ['code' => 'inventory_incomplete', 'message' => "Mapped question count for {$key} differs from the declared sheet."];
            }
        }
        foreach ($areaNumbers as $area => $numbers) {
            sort($numbers);
            if ($numbers !== range(1, count($numbers))) {
                $errors[] = ['code' => 'inventory_sequence_invalid', 'message' => "Questions in {$area} must be numbered contiguously from 1 across all pages."];
            }
        }
        foreach ($observed as $key => $numbers) {
            if (! isset($declared[$key])) {
                $errors[] = ['code' => 'inventory_undeclared', 'message' => "Mapped questions in {$key} are not in the declared sheet."];
            }
        }

        $identity = $mapping['identity'] ?? null;
        if (! is_array($identity)) {
            $errors[] = ['code' => 'identity_method_missing', 'message' => 'Choose shaded Applicant Number, write-in manual matching, or both.'];

            return;
        }

        $strategy = (string) ($identity['strategy'] ?? $identity['method'] ?? '');
        $hasBubbles = isset($identity['has_bubbles'])
            ? (bool) $identity['has_bubbles']
            : ($strategy === 'shaded_grid' || $strategy === 'bubbles_only' || $strategy === 'bubbles_and_write_in');
        $hasWriteIn = isset($identity['has_write_in'])
            ? (bool) $identity['has_write_in']
            : ($strategy === 'write_in_manual' || $strategy === 'write_in_only' || $strategy === 'bubbles_and_write_in');

        if (! $hasBubbles && ! $hasWriteIn) {
            if ($strategy === 'shaded_grid') {
                $hasBubbles = true;
            } elseif ($strategy === 'write_in_manual') {
                $hasWriteIn = true;
            } else {
                $errors[] = ['code' => 'identity_method_missing', 'message' => 'Choose shaded Applicant Number, write-in manual matching, or both.'];

                return;
            }
        }
        $identityPages = null;
        if (isset($identity['identity_pages']) && is_array($identity['identity_pages']) && $identity['identity_pages'] !== []) {
            $identityPages = array_values(array_unique(array_filter(
                $identity['identity_pages'],
                static fn ($pageNo) => is_int($pageNo) && $pageNo >= 1 && $pageNo <= $pageCount,
            )));
        }
        if ($identityPages === null || $identityPages === []) {
            $identityPages = range(1, $pageCount);
        }

        if ($hasBubbles) {
            $digits = $identity['digit_count'] ?? null;
            if (! is_int($digits) || $digits < 1 || $digits > 20) {
                $errors[] = ['code' => 'identity_digits_invalid', 'message' => 'Declare a valid Applicant Number digit count.'];
            } else {
                foreach ($identityPages as $pageNo) {
                    $positions = $identityByPage[$pageNo] ?? [];
                    sort($positions);
                    if ($positions !== range(1, $digits)) {
                        $errors[] = ['code' => 'identity_page_missing', 'message' => "Page {$pageNo} needs {$digits} ordered Applicant Number positions."];
                    }
                }
            }
        }

        if ($hasWriteIn) {
            $boxes = $identity['write_in_boxes'] ?? null;
            $regions = $identity['regions'] ?? null;

            if (is_array($boxes) && $boxes !== []) {
                $pages = [];
                foreach ($boxes as $box) {
                    $pageNo = is_array($box) ? ($box['page_no'] ?? null) : null;
                    $x = is_array($box) ? ($box['x_mm'] ?? null) : null;
                    $y = is_array($box) ? ($box['y_mm'] ?? null) : null;
                    $w = is_array($box) ? ($box['w_mm'] ?? null) : null;
                    $h = is_array($box) ? ($box['h_mm'] ?? null) : null;
                    if (! is_int($pageNo) || $pageNo < 1 || $pageNo > $pageCount || isset($pages[$pageNo])
                        || ! is_numeric($x) || ! is_numeric($y) || ! is_numeric($w) || ! is_numeric($h)
                        || $w <= 0 || $h <= 0 || $x < 0 || $y < 0) {
                        $errors[] = ['code' => 'identity_region_invalid', 'message' => 'Each write-in region must be unique, visible and inside its page.'];

                        continue;
                    }
                    $pages[$pageNo] = true;
                }
                foreach ($identityPages as $pageNo) {
                    if (! isset($pages[$pageNo])) {
                        $errors[] = ['code' => 'identity_page_missing', 'message' => "Page {$pageNo} needs a visible write-in Applicant Number region."];
                    }
                }
            } elseif (is_array($regions) && $regions !== []) {
                $pages = [];
                foreach ($regions as $region) {
                    $pageNo = is_array($region) ? ($region['page_no'] ?? null) : null;
                    if (! is_array($region) || ! is_int($pageNo) || $pageNo < 1 || $pageNo > $pageCount || isset($pages[$pageNo])
                        || ! is_numeric($region['x'] ?? null) || ! is_numeric($region['y'] ?? null)
                        || ! is_numeric($region['w'] ?? null) || ! is_numeric($region['h'] ?? null)
                        || $region['w'] <= 0 || $region['h'] <= 0 || $region['x'] < 0 || $region['y'] < 0
                        || $pageWidth < $region['x'] + $region['w'] || $pageHeight < $region['y'] + $region['h']) {
                        $errors[] = ['code' => 'identity_region_invalid', 'message' => 'Each write-in region must be unique, visible and inside its page.'];

                        continue;
                    }
                    $pages[$pageNo] = true;
                }
                foreach ($identityPages as $pageNo) {
                    if (! isset($pages[$pageNo])) {
                        $errors[] = ['code' => 'identity_page_missing', 'message' => "Page {$pageNo} needs a visible write-in Applicant Number region."];
                    }
                }
            } else {
                foreach ($identityPages as $pageNo) {
                    $errors[] = ['code' => 'identity_page_missing', 'message' => "Page {$pageNo} needs a visible write-in Applicant Number region."];
                }
            }
        }
    }

    /** @param list<mixed> $labels @return list<string> */
    private function nativeLabels(string $name, string $fieldType, array $labels, int $rows): array
    {
        $upperName = strtoupper($name);
        $identity = $fieldType === 'QTYPE_INT'
            && (str_contains($upperName, 'ID') || str_contains($upperName, 'APPLICANT') || str_contains($upperName, 'EXAMINEE'));
        $prefix = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));

        return array_values(array_map(
            static function (mixed $label, int $index) use ($identity, $prefix): string {
                $value = strtoupper(trim((string) $label));
                if (preg_match('/^(APPLICANT_NUMBER|BIRTHDATE)_[1-9][0-9]*$/', $value) === 1) {
                    return $value;
                }
                if ($identity && preg_match('/(\d+)$/', $value, $matches) === 1) {
                    return 'APPLICANT_NUMBER_'.(int) $matches[1];
                }
                if (preg_match('/^([A-Z0-9_]+)_Q_(\d+)$/', $value, $matches) === 1) {
                    return $matches[1].'_Q_'.(int) $matches[2];
                }
                if (preg_match('/(\d+)$/', $value, $matches) === 1) {
                    return $prefix.'_Q_'.(int) $matches[1];
                }

                return $prefix.'_Q_'.($index + 1);
            },
            $labels,
            array_keys($labels),
        ));
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private function overlaps(array $left, array $right): bool
    {
        return (int) $left['x'] < (int) $right['x'] + (int) $right['w']
            && (int) $left['x'] + (int) $left['w'] > (int) $right['x']
            && (int) $left['y'] < (int) $right['y'] + (int) $right['h']
            && (int) $left['y'] + (int) $left['h'] > (int) $right['y'];
    }
}
