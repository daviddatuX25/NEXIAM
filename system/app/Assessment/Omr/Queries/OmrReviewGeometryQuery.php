<?php

namespace App\Assessment\Omr\Queries;

use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;

use function array_column;
use function array_map;
use function count;
use function in_array;
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
    public function __construct(private readonly OmrTemplateGeometryProjector $geometryProjector)
    {
    }

    /**
     * @return list<array{
     *     position: int,
     *     area_id: int,
     *     area_code: string,
     *     area_name: string,
     *     question_no: int,
     *     correct_option_code: string,
     *     page_no: int,
     *     region: array{x: float, y: float, w: float, h: float},
     *     options: list<array{code: string, region: array{x: float, y: float, w: float, h: float}}>,
     * }>
     */
    public function itemsFor(OmrAnswerSheetTemplateVersion $template, OmrQuestionBookletVersion $booklet): array
    {
        $projection = $this->geometryProjector->currentFor($template);

        $templateByArea = [];
        foreach ($projection->projectedQuestions as $question) {
            $templateByArea[$question['area_code']][] = $question;
        }

        $bookletByArea = [];
        foreach ($booklet->questions->sortBy('question_position')->values() as $bookletQuestion) {
            $area = $bookletQuestion->aptitudeArea;
            if ($area === null) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    sprintf('Booklet question at position %d has no aptitude area.', (int) $bookletQuestion->question_position),
                );
            }
            $bookletByArea[(string) $area->code][] = $bookletQuestion;
        }

        foreach ($templateByArea as $areaCode => $templateQuestions) {
            $bookletQuestions = $bookletByArea[$areaCode] ?? [];
            if (count($bookletQuestions) !== count($templateQuestions)) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    sprintf(
                        'The booklet declares %d questions for area %s but the template projection covers %d.',
                        count($bookletQuestions),
                        $areaCode,
                        count($templateQuestions),
                    ),
                );
            }
        }

        $items = [];
        foreach ($bookletByArea as $areaCode => $bookletQuestions) {
            $templateQuestions = $templateByArea[$areaCode] ?? [];
            foreach ($bookletQuestions as $index => $bookletQuestion) {
                $templateQuestion = $templateQuestions[$index];
                $declaredCodes = array_column($templateQuestion['options'], 'code');

                if (! in_array((string) $bookletQuestion->correct_option_code, $declaredCodes, true)) {
                    throw new OmrTemplateProjectionException(
                        'pair_option_set_mismatch',
                        sprintf(
                            'Booklet question %d correct option %s is outside the template declared option set for %s_Q_%d.',
                            (int) $bookletQuestion->question_position,
                            (string) $bookletQuestion->correct_option_code,
                            $areaCode,
                            (int) $templateQuestion['question_no'],
                        ),
                    );
                }

                $items[] = [
                    'position' => (int) $bookletQuestion->question_position,
                    'area_id' => (int) $templateQuestion['area_id'],
                    'area_code' => $areaCode,
                    'area_name' => (string) $templateQuestion['area_name'],
                    'question_no' => (int) $templateQuestion['question_no'],
                    'correct_option_code' => (string) $bookletQuestion->correct_option_code,
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
}
