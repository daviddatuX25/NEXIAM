<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrPageDimensionMath;

use function config;
use function count;
use function in_array;
use function is_array;
use function json_encode;
use function round;

final class OmrMappingValidator
{
    /**
     * @param array<string, mixed> $mapping
     * @param array{page_width?:int,page_height?:int,page_count?:int,marker_zones?:list<array<string,mixed>>,content_zones?:list<array<string,mixed>>} $context
     * @return array{valid:bool,errors:list<array{code:string,message:string,block?:string,row?:int}>,warnings:list<string>,field_blocks:array<string,mixed>}
     */
    public function validate(array $mapping, array $context = []): array
    {
        $errors = [];
        $warnings = [];
        $fieldBlocks = [];
        $labels = [];
        $regions = [];
        $pageWidth = (int) ($context['page_width'] ?? $mapping['page_width'] ?? 0);
        $pageHeight = (int) ($context['page_height'] ?? $mapping['page_height'] ?? 0);
        $pageCount = (int) ($context['page_count'] ?? $mapping['page_count'] ?? 1);
        $blocks = $mapping['blocks'] ?? [];
        if (! is_array($blocks) || $blocks === []) {
            return ['valid' => false, 'errors' => [['code' => 'mapping_empty', 'message' => 'At least one OMR block is required.']], 'warnings' => [], 'field_blocks' => []];
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                $errors[] = ['code' => 'block_invalid', 'message' => "Block {$index} is invalid."];
                continue;
            }
            $name = (string) ($block['name'] ?? "Block{$index}");
            $pageNo = (int) ($block['page_no'] ?? 1);
            $fieldType = (string) ($block['field_type'] ?? 'QTYPE_MCQ4');
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
            if (OmrPageDimensionMath::millimetresFromPixels(min($bubbleWidth, $bubbleHeight)) < (float) config('omr.canonical.limits.minimum_bubble_mm', 4.2)) {
                $errors[] = ['code' => 'bubble_too_small', 'message' => "Block {$name} has bubbles below the minimum physical diameter.", 'block' => $name];
            }
            $edgeGap = $optionPitch - $bubbleWidth;
            $minimumGapPx = OmrPageDimensionMath::pixelsFromMillimetres((float) config('omr.canonical.limits.minimum_option_edge_gap_mm', 1.26));
            if ($edgeGap < $minimumGapPx) {
                $errors[] = ['code' => 'pitch_too_tight', 'message' => "Block {$name} has insufficient option edge gap.", 'block' => $name];
            }
            $isVertical = ($block['direction'] ?? 'horizontal') === 'vertical';
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
            if ($pageWidth > 0 && ($originX < 0 || $originY < 0 || $originX + $width > $pageWidth || $originY + $height > $pageHeight)) {
                $errors[] = ['code' => 'out_of_bounds', 'message' => "Block {$name} lies outside the canonical page.", 'block' => $name];
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

            $blockLabels = $this->nativeLabels($name, $fieldType, (array) ($block['labels'] ?? []), $rows);
            if ($blockLabels === []) {
                $prefix = (string) ($block['label_prefix'] ?? $name.'_Q_');
                for ($row = 1; $row <= $rows; $row++) {
                    $blockLabels[] = sprintf('%s%03d', $prefix, $row);
                }
            }
            if (! $isVertical && count($blockLabels) !== $rows) {
                $errors[] = ['code' => 'label_count_mismatch', 'message' => "Block {$name} label count does not match rows.", 'block' => $name];
            }
            foreach ($blockLabels as $row => $label) {
                if (isset($labels[$label])) {
                    $errors[] = ['code' => 'duplicate_label', 'message' => "Label {$label} is duplicated.", 'block' => $name, 'row' => $row + 1];
                }
                $labels[$label] = true;
            }
            $fieldBlocks[$name] = [
                'fieldType' => $fieldType,
                'direction' => (string) ($block['direction'] ?? 'horizontal'),
                'fieldLabels' => array_values($blockLabels),
                'bubblesGap' => $optionPitch,
                'labelsGap' => $rowPitch,
                'origin' => [$originX, $originY],
                'bubbleDimensions' => [$bubbleWidth, $bubbleHeight],
                'page_no' => $pageNo,
            ];
        }

        $numberedGroups = [];
        foreach (array_keys($labels) as $label) {
            if (preg_match('/^(.*?)(\d+)$/', (string) $label, $matches) === 1) {
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

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'field_blocks' => $fieldBlocks,
        ];
    }

    /** @param list<mixed> $labels @return list<string> */
    private function nativeLabels(string $name, string $fieldType, array $labels, int $rows): array
    {
        $upperName = strtoupper($name);
        $identity = $fieldType === 'QTYPE_INT'
            && (str_contains($upperName, 'ID') || str_contains($upperName, 'APPLICANT') || str_contains($upperName, 'EXAMINEE'));
        $prefix = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));

        return array_values(array_map(
            static function (mixed $label, int $index) use ($identity, $prefix, $fieldType): string {
                $value = strtoupper(trim((string) $label));
                if (str_starts_with($value, 'APPLICANT_NUMBER_') || str_starts_with($value, 'BIRTHDATE_')) {
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
