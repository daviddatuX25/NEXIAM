<?php

namespace App\Assessment\Omr\Queries;

use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;

use function array_column;
use function array_map;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function usort;

/**
 * Merges the template-owned geometry projection with a pinned booklet's
 * correct options. Booklet questions are matched to projected template
 * questions by validated area code plus within-area question position — the
 * merge never depends on `template_question_id` links, so canonical compiled
 * templates (projected at compile time) and authored booklets pair
 * identically.
 *
 * Typed failures block instead of degrading to placeholder regions:
 * `geometry_projection_missing`, `geometry_projection_stale`,
 * `pair_geometry_coverage_mismatch`, `pair_option_set_mismatch`.
 */
final class OmrReviewGeometryQuery
{
    public function __construct(private readonly OmrTemplateGeometryProjector $geometryProjector) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsFor(OmrAnswerSheetTemplateVersion $template, OmrQuestionBookletVersion $booklet, ?OmrBookletSet $set = null, ?array $kitContext = null): array
    {
        $projection = $this->geometryProjector->currentFor($template);

        if ($kitContext !== null) {
            return $this->kitItemsFor($projection->questions, $template, $booklet, $set, $kitContext);
        }

        $templateByArea = [];
        foreach ($projection->projectedQuestions as $question) {
            $templateByArea[$question['area_code']][] = $question;
        }

        $bookletByArea = [];
        if ($set !== null) {
            $set->loadMissing('items.aptitudeArea');
            foreach ($set->items->sortBy('global_position')->values() as $setItem) {
                $area = $setItem->aptitudeArea;
                if ($area === null) {
                    throw new OmrTemplateProjectionException(
                        'pair_geometry_coverage_mismatch',
                        sprintf('Set item at position %d has no aptitude area.', (int) $setItem->global_position),
                    );
                }
                $bookletByArea[(string) $area->code][] = [
                    'position' => (int) $setItem->global_position,
                    'question_no' => (int) $setItem->area_position,
                    'correct_option_code' => (string) $setItem->correct_option_code,
                ];
            }
        } else {
            foreach ($booklet->questions->sortBy('question_position')->values() as $bookletQuestion) {
                $area = $bookletQuestion->aptitudeArea;
                if ($area === null) {
                    throw new OmrTemplateProjectionException(
                        'pair_geometry_coverage_mismatch',
                        sprintf('Booklet question at position %d has no aptitude area.', (int) $bookletQuestion->question_position),
                    );
                }
                $bookletByArea[(string) $area->code][] = [
                    'position' => (int) $bookletQuestion->question_position,
                    'question_no' => (int) $bookletQuestion->question_position,
                    'correct_option_code' => (string) $bookletQuestion->correct_option_code,
                ];
            }
        }

        $items = [];
        foreach ($bookletByArea as $areaCode => $bookletQuestions) {
            $templateQuestions = $templateByArea[$areaCode] ?? [];
            foreach ($bookletQuestions as $index => $bookletQuestion) {
                $templateQuestion = $templateQuestions[$index] ?? null;
                if ($templateQuestion === null) {
                    throw new OmrTemplateProjectionException(
                        'pair_geometry_coverage_mismatch',
                        sprintf('The template has no geometry for %s question %d.', $areaCode, $index + 1),
                    );
                }
                $declaredCodes = array_column($templateQuestion['options'], 'code');
                if (! in_array($bookletQuestion['correct_option_code'], $declaredCodes, true)) {
                    throw new OmrTemplateProjectionException(
                        'pair_option_set_mismatch',
                        sprintf(
                            'Booklet question %d correct option %s is outside the template declared option set for %s_Q_%d.',
                            (int) $bookletQuestion['position'],
                            (string) $bookletQuestion['correct_option_code'],
                            $areaCode,
                            (int) $templateQuestion['question_no'],
                        ),
                    );
                }

                $items[] = [
                    'position' => (int) $bookletQuestion['position'],
                    'area_id' => (int) $templateQuestion['area_id'],
                    'area_code' => $areaCode,
                    'area_name' => (string) $templateQuestion['area_name'],
                    'question_no' => (int) $bookletQuestion['question_no'],
                    'correct_option_code' => (string) $bookletQuestion['correct_option_code'],
                    'page_no' => (int) $templateQuestion['page_no'],
                    'region' => $templateQuestion['region'],
                    'options' => array_map(static fn (array $option): array => [
                        'code' => (string) $option['code'],
                        'region' => $option['region'],
                    ], $templateQuestion['options']),
                ];
            }
        }

        usort($items, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $projectedQuestions
     * @param  array<string, mixed>  $kitContext
     * @return list<array<string, mixed>>
     */
    private function kitItemsFor(
        array $projectedQuestions,
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        ?OmrBookletSet $set,
        array $kitContext,
    ): array {
        $itemMap = $kitContext['item_map'] ?? null;
        $bindings = is_array($itemMap) ? ($itemMap['bindings'] ?? null) : null;
        $compositionRows = is_array($itemMap) ? ($itemMap['composition_rows'] ?? null) : null;
        if (! is_array($bindings) || ! is_array($compositionRows) || $set === null
            || (int) ($kitContext['template_version_id'] ?? 0) !== (int) $template->id
            || (int) ($kitContext['booklet_version_id'] ?? 0) !== (int) $booklet->id
            || (int) ($kitContext['booklet_set_id'] ?? 0) !== (int) $set->id) {
            throw new OmrTemplateProjectionException(
                'pair_geometry_coverage_mismatch',
                'The pinned Kit composition does not match the exact OMR component versions.',
            );
        }

        $geometryByLabel = [];
        foreach ($projectedQuestions as $question) {
            $label = $question['native_label'] ?? null;
            if (! is_string($label) || $label === '' || isset($geometryByLabel[$label])) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    'The pinned Sheet geometry has a missing or duplicate native item identity.',
                );
            }
            $geometryByLabel[$label] = $question;
        }

        $rowsByLabel = [];
        foreach ($compositionRows as $row) {
            if (is_array($row) && ($row['disposition'] ?? null) === 'physical') {
                $label = $row['native_label'] ?? null;
                if (! is_string($label) || isset($rowsByLabel[$label])) {
                    throw new OmrTemplateProjectionException(
                        'pair_geometry_coverage_mismatch',
                        'The saved Kit composition has a missing or duplicate physical Sheet identity.',
                    );
                }
                $rowsByLabel[$label] = $row;
            }
        }

        $items = [];
        $seenLabels = [];
        foreach ($bindings as $binding) {
            if (! is_array($binding) || ! is_string($binding['native_label'] ?? null)) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    'The saved Kit composition has an invalid physical item binding.',
                );
            }
            $label = $binding['native_label'];
            $question = $geometryByLabel[$label] ?? null;
            $row = $rowsByLabel[$label] ?? null;
            $questionPageNo = (int) ($question['page'] ?? $question['page_no'] ?? 0);
            if ($question === null || $row === null || isset($seenLabels[$label])
                || ! is_string($row['physical_item_uid'] ?? null) || $row['physical_item_uid'] === ''
                || (string) ($question['physical_item_uid'] ?? '') !== $row['physical_item_uid']
                || (int) ($row['booklet_set_item_id'] ?? 0) < 1
                || (string) ($question['printed_section_uid'] ?? '') !== (string) ($row['physical_section_uid'] ?? '')
                || (int) ($row['booklet_item_id'] ?? 0) < 1
                || ! is_string($row['booklet_item_uid'] ?? null) || $row['booklet_item_uid'] === ''
                || ! is_string($row['booklet_section_uid'] ?? null) || $row['booklet_section_uid'] === ''
                || ! is_string($row['physical_section_uid'] ?? null) || $row['physical_section_uid'] === ''
                || $questionPageNo !== (int) ($row['page_no'] ?? 0)
                || (int) ($binding['page_no'] ?? 0) !== (int) ($row['page_no'] ?? 0)
                || (int) ($binding['global_position'] ?? 0) !== (int) ($row['global_position'] ?? 0)
                || (int) ($binding['sheet_item_id'] ?? 0) !== (int) ($row['sheet_item_id'] ?? 0)
                || (int) ($binding['item_id'] ?? 0) !== (int) ($row['formal_item_id'] ?? 0)
                || (int) ($binding['subtest_id'] ?? 0) !== (int) ($row['formal_subtest_id'] ?? 0)) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    sprintf('The saved Kit composition does not uniquely resolve Sheet item %s.', $label),
                );
            }
            $areaCode = is_string($question['area_code'] ?? null) && $question['area_code'] !== ''
                ? $question['area_code']
                : $label;
            $questionNo = (int) ($question['question_no'] ?? $binding['global_position'] ?? 0);
            $correctOption = $binding['correct_token'] ?? null;
            if ($correctOption !== null
                && ! in_array((string) $correctOption, array_column($question['options'], 'code'), true)) {
                throw new OmrTemplateProjectionException(
                    'pair_option_set_mismatch',
                    sprintf('The saved key token is outside the Sheet option set for %s.', $label),
                );
            }
            $seenLabels[$label] = true;
            $items[] = [
                'position' => (int) $binding['global_position'],
                'area_id' => (int) $binding['subtest_id'],
                'formal_item_id' => (int) $binding['item_id'],
                'formal_subtest_id' => (int) $binding['subtest_id'],
                'area_code' => $areaCode,
                'area_name' => $areaCode,
                'question_no' => $questionNo,
                'correct_option_code' => $correctOption === null ? '' : (string) $correctOption,
                'page_no' => (int) $binding['page_no'],
                'physical_section_uid' => (string) ($row['physical_section_uid'] ?? ''),
                'booklet_set_item_id' => (int) ($row['booklet_set_item_id'] ?? 0),
                'booklet_item_id' => (int) ($row['booklet_item_id'] ?? 0),
                'booklet_item_uid' => (string) ($row['booklet_item_uid'] ?? ''),
                'booklet_section_uid' => (string) ($row['booklet_section_uid'] ?? ''),
                'native_label' => $label,
                'physical_item_uid' => (string) ($row['physical_item_uid'] ?? ''),
                'region' => $question['region'],
                'options' => array_map(static fn (array $option): array => [
                    'code' => (string) $option['code'],
                    'region' => $option['region'],
                    'display_order' => (int) ($option['display_order'] ?? 0),
                ], $question['options']),
            ];
        }

        if (count($seenLabels) !== count($geometryByLabel) || count($seenLabels) !== count($rowsByLabel)) {
            throw new OmrTemplateProjectionException(
                'pair_geometry_coverage_mismatch',
                'The saved Kit composition does not cover every projected physical Sheet item.',
            );
        }

        usort($items, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return $items;
    }
}
