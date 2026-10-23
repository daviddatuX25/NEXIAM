<?php

namespace App\Consultation\Support;

use App\Admission\Support\AttemptProgramFacts;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionSupportSnapshot;

/**
 * Shared read-only applicability checks for persisted decision-support results.
 * This class never evaluates a model or creates a snapshot.
 */
final class RecordedEvaluationApplicability
{
    /**
     * @param  array<string, mixed>  $recordedResult
     * @param  array<string, mixed>  $currentScoreProjection
     */
    public function matchesCurrentEvidence(
        AdmissionAttempt $attempt,
        AdmissionSupportSnapshot $snapshot,
        array $recordedResult,
        array $currentScoreProjection,
    ): bool {
        if (! AttemptProgramFacts::isResultReady($attempt)
            || ! $this->assessmentContextMatches(
                is_array($recordedResult['assessment_context'] ?? null) ? $recordedResult['assessment_context'] : null,
                is_array($currentScoreProjection['assessment_context'] ?? null) ? $currentScoreProjection['assessment_context'] : null,
            )
            || ! $this->hasCanonicalRecordedSchema($snapshot, $attempt, $recordedResult)) {
            return false;
        }

        $input = AdmissionInputPayload::fromAttempt(
            $attempt,
            ConsultationScoreProjection::admissionInputSeries($currentScoreProjection),
        );

        return is_string($snapshot->input_digest)
            && hash_equals($snapshot->input_digest, $input->digest());
    }

    /**
     * @param  array<string, mixed>|null  $captured
     * @param  array<string, mixed>|null  $current
     */
    private function assessmentContextMatches(?array $captured, ?array $current): bool
    {
        $identityKeys = [
            'version',
            'assignment_id',
            'exam_session_id',
            'grading_session_id',
            'administration_id',
            'result_set_id',
        ];

        if ($captured === null || $current === null
            || array_diff($identityKeys, array_keys($captured)) !== []
            || array_diff(array_keys($captured), $identityKeys) !== []) {
            return false;
        }

        foreach ($identityKeys as $key) {
            if (! array_key_exists($key, $current) || $captured[$key] !== $current[$key]) {
                return false;
            }
        }

        return $captured['version'] === 1;
    }

    /**
     * @param  array<string, mixed>  $recordedResult
     */
    private function hasCanonicalRecordedSchema(
        AdmissionSupportSnapshot $snapshot,
        AdmissionAttempt $attempt,
        array $recordedResult,
    ): bool {
        $context = $recordedResult['evaluation_context'] ?? null;
        if (! is_array($context)
            || ! is_int($context['benchmark_activation_id'] ?? null)
            || ! is_int($context['benchmark_set_id'] ?? null)
            || ! is_int($context['institution_id'] ?? null)
            || ! is_int($context['academic_cycle_id'] ?? null)
            || ! is_string($context['schema_digest'] ?? null)
            || ! is_string($context['content_digest'] ?? null)
            || ! is_string($context['input_digest'] ?? null)
            || (int) $snapshot->academic_cycle_id !== (int) $attempt->academic_cycle_id
            || (int) $snapshot->benchmark_activation_id !== $context['benchmark_activation_id']
            || $context['institution_id'] !== (int) $attempt->institution_id
            || $context['academic_cycle_id'] !== (int) $attempt->academic_cycle_id
            || ! is_string($snapshot->benchmark_hash)
            || ! is_string($snapshot->input_digest)
            || ! hash_equals($snapshot->benchmark_hash, $context['content_digest'])
            || ! hash_equals($snapshot->input_digest, $context['input_digest'])) {
            return false;
        }

        $activation = AdmissionBenchmarkActivation::query()
            ->with('benchmarkSet.features')
            ->whereKey($context['benchmark_activation_id'])
            ->where('institution_id', $attempt->institution_id)
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->first();
        $set = $activation?->benchmarkSet;

        return $activation !== null
            && (int) $activation->benchmark_set_id === $context['benchmark_set_id']
            && $set !== null
            && (int) $set->institution_id === (int) $attempt->institution_id
            && hash_equals((string) $set->schema_sha256, $context['schema_digest'])
            && hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            && hash_equals((string) $set->content_sha256, $context['content_digest'])
            && hash_equals((string) $set->content_sha256, $set->computedContentDigest())
            && BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($set));
    }
}
