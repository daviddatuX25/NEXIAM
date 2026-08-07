<?php

namespace App\Services;

use App\Models\ApplicantScore;
use App\Models\AptitudeArea;
use App\Models\GradingSession;
use App\Models\User;

class ScoreInputService
{
    /**
     * Upsert scores for an applicant in a grading session.
     *
     * When $enableNormalizedScores is true: raw_score is entered and
     * normalized_score is auto-computed from the aptitude area formula.
     * When false: normalized_score is entered directly by the user.
     *
     * @param  array<array{aptitude_area_id: int, raw_score?: int|null, max_score?: int|null, normalized_score?: float|null}>  $scores
     */
    public function saveScores(GradingSession $gradingSession, int $applicantId, array $scores, User $scoredBy, bool $enableNormalizedScores): void
    {
        $now = now();

        foreach ($scores as $entry) {
            $domainId = (int) $entry['aptitude_area_id'];
            $attributes = [
                'scored_by' => $scoredBy->id,
                'scored_at' => $now,
            ];

            if ($enableNormalizedScores) {
                $rawScore = (int) ($entry['raw_score'] ?? 0);
                $maxScore = (int) ($entry['max_score'] ?? 0);
                $area = AptitudeArea::find($domainId);

                if ($area?->scoring_method === 'conversion_table') {
                    $percentileString = $area->lookupPercentile($rawScore);
                    $attributes['raw_score'] = $rawScore;
                    $attributes['max_score'] = $maxScore;
                    $attributes['normalized_score'] = null;
                    $attributes['percentile_string'] = $percentileString ?? 'N/A';
                } else {
                    $normalizedScore = $area?->computeNormalizedScore((float) $rawScore);
                    $attributes['raw_score'] = $rawScore;
                    $attributes['max_score'] = $maxScore;
                    $attributes['normalized_score'] = $normalizedScore;
                    $attributes['percentile_string'] = null;
                }
            } else {
                $area = AptitudeArea::find($domainId);
                $attributes['raw_score'] = null;
                $attributes['max_score'] = null;
                if ($area?->scoring_method === 'conversion_table') {
                    $attributes['normalized_score'] = null;
                    $attributes['percentile_string'] = $entry['percentile_string'] ?? null;
                } else {
                    $attributes['normalized_score'] = $entry['normalized_score'] ?? null;
                    $attributes['percentile_string'] = null;
                }
            }

            ApplicantScore::updateOrCreate(
                [
                    'grading_session_id' => $gradingSession->id,
                    'applicant_id' => $applicantId,
                    'aptitude_area_id' => $domainId,
                ],
                $attributes
            );
        }
    }

    public function clearScores(GradingSession $gradingSession, int $applicantId): void
    {
        ApplicantScore::where('grading_session_id', $gradingSession->id)
            ->where('applicant_id', $applicantId)
            ->delete();
    }
}
