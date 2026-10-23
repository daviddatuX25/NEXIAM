<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;

use function is_array;
use function is_string;
use function preg_replace;
use function str_contains;
use function strtolower;

/**
 * Projects one diagnostic capture after comparison. The result is visibly
 * non-authoritative: it is a projected score, never a production score or a
 * qualification result. Normal evidence is answer-key blind even when the
 * persisted comparison record contains comparison-only material.
 */
final class OmrDiagnosticComparisonProjector
{
    /**
     * @return array<string, mixed>
     */
    public function project(OmrDiagnosticCapture $capture): array
    {
        $capture->loadMissing(['evidenceItems.reviewRevisions']);

        $projectedScore = $this->withoutSensitiveTruth($capture->projected_score_json);

        return [
            'execution_kind' => 'diagnostic',
            'run_id' => (int) $capture->diagnostic_run_id,
            'capture_id' => (int) $capture->getKey(),
            'status' => (string) $capture->status,
            'evidence' => [
                'capture' => $this->withoutSensitiveTruth($capture->evidence_json),
                'items' => $capture->evidenceItems
                    ->sortBy('id')
                    ->map(fn (OmrDiagnosticItemEvidence $item): array => $this->item($item))
                    ->values()
                    ->all(),
            ],
            'effective_answers' => $this->withoutSensitiveTruth($capture->effective_answers_json),
            'projected_score' => [
                'label' => 'Projected / unofficial',
                'official' => false,
                'summary' => $projectedScore,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(OmrDiagnosticItemEvidence $item): array
    {
        return [
            'id' => (int) $item->getKey(),
            'capture_id' => (int) $item->diagnostic_capture_id,
            'item_key' => (string) $item->item_key,
            'page_no' => (int) $item->page_no,
            'area_code' => $item->area_code,
            'question_no' => $item->question_no === null ? null : (int) $item->question_no,
            'option_code' => $item->option_code,
            'option_evidence' => $this->withoutSensitiveTruth($item->option_evidence_json),
            'machine_proposal' => $this->withoutSensitiveTruth($item->machine_proposal_json),
            'machine_confidence' => $item->machine_confidence === null ? null : (float) $item->machine_confidence,
            'evidence_hash' => (string) $item->evidence_hash,
            'evidence_version' => (string) $item->evidence_version,
            'reviews' => $item->reviewRevisions
                ->sortBy('revision')
                ->map(fn (OmrDiagnosticReviewRevision $revision): array => $this->withoutSensitiveTruth($revision->toSafeArray()))
                ->values()
                ->all(),
        ];
    }

    /**
     * Expected answers, answer-key details, and qualification metadata are
     * comparison-only server material and never belong in normal evidence.
     */
    private function withoutSensitiveTruth(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $key => $child) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }
            $safe[$key] = $this->withoutSensitiveTruth($child);
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($key));
        if (! is_string($normalized) || $normalized === '') {
            return false;
        }

        foreach (['answerkey', 'correctanswer', 'correctoption', 'expected', 'qualification'] as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
