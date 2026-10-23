<?php

namespace App\Assessment\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\User;
use RuntimeException;

final class FreezeAssessmentResultSet
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(AssessmentAdministration $administration, GradingSession $gradingSession, User $actor): AssessmentResultSet
    {
        $existing = AssessmentResultSet::query()
            ->where('administration_id', $administration->id)
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->orderBy('id')
            ->get();

        if ($existing->count() > 1) {
            throw new RuntimeException('Multiple frozen result snapshots exist for this assessment administration.');
        }

        if ($existing->isNotEmpty()) {
            $resultSet = $existing->first();
            if ((int) $resultSet->grading_session_id !== (int) $gradingSession->id
                || (int) $resultSet->exam_session_id !== (int) $gradingSession->exam_session_id) {
                throw new RuntimeException('Frozen assessment result belongs to a different sitting.');
            }

            return $resultSet;
        }

        if ((int) $administration->exam_assignment_id === 0
            || (int) $administration->admission_attempt_id === 0
            || (int) $gradingSession->exam_session_id === 0) {
            throw new RuntimeException('Assessment result snapshot requires exact administration and sitting identity.');
        }

        if ($administration->binding_state === AssessmentAdministration::STATE_FINALIZED) {
            throw new RuntimeException('The finalized assessment administration has no reusable result snapshot.');
        }

        $rawRevisions = $administration->currentRawResultRevisions()
            ->where('status', AssessmentRawResultRevision::STATUS_VALID)
            ->orderBy('metric_code')
            ->get();

        if ($rawRevisions->isEmpty()) {
            $rawRevisions = $administration->currentRawResultRevisions()
                ->orderBy('metric_code')
                ->get();
        }

        $conversionRevision = $administration->currentConversionRevision;
        $rawVector = [];
        foreach ($rawRevisions as $rawRevision) {
            $rawEntry = [
                'id' => $rawRevision->id,
                'metric_id' => $rawRevision->metric_id,
                'metric_code' => $rawRevision->metric_code,
                'revision_no' => $rawRevision->revision_no,
                'raw_value' => (string) $rawRevision->raw_value,
                'source' => $rawRevision->source,
                'status' => $rawRevision->status,
            ];
            if (is_array($rawRevision->provenance_json) && $rawRevision->provenance_json !== []) {
                $rawEntry['provenance'] = $rawRevision->provenance_json;
            }
            $rawVector[$rawRevision->metric_code] = $rawEntry;
        }

        $conversionVector = [
            'conversion_revision_id' => $conversionRevision?->id,
            'norm_package_version_id' => $conversionRevision?->norm_package_version_id,
            'revision_no' => $conversionRevision?->revision_no,
            'status' => $conversionRevision?->status ?? 'valid',
            'typed_outputs' => $conversionRevision?->typed_outputs_json ?? [],
        ];
        if ($conversionRevision !== null) {
            $conversionVector['source'] = $conversionRevision->source;
            if (is_array($conversionRevision->provenance_json) && $conversionRevision->provenance_json !== []) {
                $conversionVector['provenance'] = $conversionRevision->provenance_json;
            }
            if (is_array($conversionRevision->typed_outputs_json)) {
                foreach ($conversionRevision->typed_outputs_json as $metricCode => $scales) {
                    $conversionVector[$metricCode] = $scales;
                }
            }
        }

        $resultSet = AssessmentResultSet::query()->create([
            'administration_id' => $administration->id,
            'grading_session_id' => $gradingSession->id,
            'exam_session_id' => $gradingSession->exam_session_id,
            'raw_revision_vector_json' => $rawVector,
            'conversion_revision_vector_json' => $conversionVector,
            'status' => AssessmentResultSet::STATUS_FINALIZED,
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now(),
        ]);

        $administration->update(['binding_state' => AssessmentAdministration::STATE_FINALIZED]);
        $this->recordAuditEvent->handle('assessment.result_set_finalized', [
            'result_set_id' => $resultSet->id,
            'administration_id' => $administration->id,
            'grading_session_id' => $gradingSession->id,
            'admission_attempt_id' => $administration->admission_attempt_id,
        ], (string) $actor->id);

        return $resultSet;
    }
}
