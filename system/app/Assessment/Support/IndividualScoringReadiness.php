<?php

declare(strict_types=1);

namespace App\Assessment\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\ScoreConversionRow;
use Illuminate\Support\Collection;

final class IndividualScoringReadiness
{
    public function __construct(
        private readonly AssessmentAdministrationFinalizationPredicate $predicate,
    ) {}

    /**
     * @return array{
     *     ready: bool,
     *     reason: ?string,
     *     state: string,
     *     exam_session_id: ?int,
     *     grading_session_id: ?int,
     *     administration_id: ?int,
     *     result_set_id: ?int,
     *     score_locked: bool,
     *     score_locked_at: ?string,
     *     finalized_at: ?string,
     *     conversion_revision_id: ?int,
     *     raw_revision_ids: list<int>,
     *     current_fingerprint: ?string
     * }
     */
    public function forAttempt(AdmissionAttempt $attempt): array
    {
        $attemptId = (int) $attempt->getKey();

        return $this->forAttempts(collect([$attempt]))[$attemptId]
            ?? $this->blocked('attempt_missing', 'The requested admission attempt no longer exists.');
    }

    /**
     * @param  Collection<int, AdmissionAttempt>  $attempts
     * @return array<int, array{
     *     ready: bool,
     *     reason: ?string,
     *     state: string,
     *     exam_session_id: ?int,
     *     grading_session_id: ?int,
     *     administration_id: ?int,
     *     result_set_id: ?int,
     *     score_locked: bool,
     *     score_locked_at: ?string,
     *     finalized_at: ?string,
     *     conversion_revision_id: ?int,
     *     raw_revision_ids: list<int>,
     *     current_fingerprint: ?string
     * }>
     */
    public function forAttempts(Collection $attempts): array
    {
        $attemptIds = $attempts
            ->map(static fn (AdmissionAttempt $attempt): int => (int) $attempt->getKey())
            ->filter(static fn (int $attemptId): bool => $attemptId > 0)
            ->unique()
            ->values();
        if ($attemptIds->isEmpty()) {
            return [];
        }

        $readiness = [];
        foreach ($attemptIds as $attemptId) {
            $readiness[$attemptId] = $this->blocked('no_current_sitting', 'No current active examination sitting is available.');
        }

        $freshAttempts = AdmissionAttempt::query()
            ->whereIn('id', $attemptIds)
            ->get(['id', 'status', 'institution_id', 'academic_cycle_id'])
            ->keyBy('id');
        $assignmentsByAttempt = ExamAssignment::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->active()
            ->with([
                'examSession:id,academic_cycle_id,test_instrument_version_id,status',
                'examSession.academicCycle:id,institution_id',
                'examSession.gradingSession:id,exam_session_id,status,finalized_at',
                'attendanceRecord:id,exam_assignment_id,outcome',
                'submissionRecord:id,exam_assignment_id,submitted_at',
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('admission_attempt_id');

        $currentAssignments = collect();
        foreach ($attemptIds as $attemptId) {
            $attempt = $freshAttempts->get($attemptId);
            $assignment = $assignmentsByAttempt->get($attemptId, collect())->first();
            if ($attempt === null || $assignment === null) {
                continue;
            }

            $session = $assignment->examSession;
            $cycle = $session?->academicCycle;
            $gradingSession = $session?->gradingSession;
            if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
                || $session === null
                || $cycle === null
                || (int) $assignment->admission_attempt_id !== (int) $attempt->id
                || (int) $session->academic_cycle_id !== (int) $attempt->academic_cycle_id
                || (int) $cycle->institution_id !== (int) $attempt->institution_id
                || $session->status !== ExamSession::STATUS_COMPLETED
                || ! GradingRoster::isEligible($assignment)
                || $gradingSession === null
                || ! in_array($gradingSession->status, [
                    GradingSession::STATUS_OPEN,
                    GradingSession::STATUS_REVIEW,
                    GradingSession::STATUS_FINALIZED,
                ], true)) {
                $readiness[$attemptId] = $this->blocked(
                    'ineligible_sitting',
                    'Scoring readiness requires the accepted applicant’s exact completed sitting and eligible present/submitted assignment.',
                    (int) $session?->id ?: null,
                    (int) $gradingSession?->id ?: null,
                );

                continue;
            }

            $currentAssignments->put((int) $assignment->getKey(), $assignment);
        }

        if ($currentAssignments->isEmpty()) {
            return $readiness;
        }

        $administrationsByAssignment = AssessmentAdministration::query()
            ->whereIn('exam_assignment_id', $currentAssignments->keys())
            ->with([
                'testInstrumentVersion.metrics.construct',
                'testInstrumentVersion.instrument',
                'examAssignment.examSession.academicCycle',
                'examAssignment.examSession.gradingSession',
            ])
            ->orderByDesc('binding_revision')
            ->orderByDesc('id')
            ->get()
            ->groupBy('exam_assignment_id');
        $activeAdministrationsByAssignment = $administrationsByAssignment->map(
            static fn (Collection $rows): Collection => $rows->whereNull('superseded_at')->values(),
        );
        $selectedAdministrations = collect();
        foreach ($currentAssignments as $assignmentId => $assignment) {
            $attemptId = (int) $assignment->admission_attempt_id;
            $session = $assignment->examSession;
            $administrationHistory = $administrationsByAssignment->get($assignmentId, collect());
            $activeAdministrations = $activeAdministrationsByAssignment->get($assignmentId, collect());
            $profileBound = $session->test_instrument_version_id !== null || $administrationHistory->isNotEmpty();

            if (! $profileBound) {
                continue;
            }

            if ($activeAdministrations->count() !== 1) {
                $readiness[$attemptId] = $this->blocked(
                    $activeAdministrations->isEmpty() ? 'profile_binding_missing' : 'profile_binding_ambiguous',
                    'A profile-bound sitting requires one exact current assessment administration; legacy scores cannot substitute.',
                    (int) $session->id,
                    (int) $session->gradingSession->id,
                );

                continue;
            }

            /** @var AssessmentAdministration $administration */
            $administration = $activeAdministrations->first();
            if ((int) $administration->exam_assignment_id !== (int) $assignmentId
                || (int) $administration->admission_attempt_id !== $attemptId
                || ($session->test_instrument_version_id !== null
                    && (int) $administration->test_instrument_version_id !== (int) $session->test_instrument_version_id)
                || $administration->test_instrument_version_id === null) {
                $readiness[$attemptId] = $this->blocked(
                    'wrong_sitting_binding',
                    'The assessment administration does not match the exact current assignment and profile for this sitting.',
                    (int) $session->id,
                    (int) $session->gradingSession->id,
                    (int) $administration->id,
                );

                continue;
            }

            $selectedAdministrations->put((int) $administration->id, $administration);
        }

        $gradingSessionIds = $currentAssignments
            ->map(static fn (ExamAssignment $assignment): int => (int) $assignment->examSession->gradingSession->id)
            ->unique()
            ->values();
        $scoreRecordsByKey = ScoreRecord::query()
            ->whereIn('grading_session_id', $gradingSessionIds)
            ->whereIn('admission_attempt_id', $attemptIds)
            ->get()
            ->groupBy(static fn (ScoreRecord $record): string => $record->grading_session_id.':'.$record->admission_attempt_id);
        $legacyAreas = AptitudeArea::query()
            ->where('is_active', true)
            ->with('conversionRows')
            ->get();

        $currentRawRevisionsByAdmin = collect();
        $currentConversionsByAdmin = collect();
        $resultSetsByAdmin = collect();
        if ($selectedAdministrations->isNotEmpty()) {
            $administrationIds = $selectedAdministrations->keys();
            $currentRawRevisionsByAdmin = AssessmentRawResultRevision::query()
                ->whereIn('administration_id', $administrationIds)
                ->where('is_current', true)
                ->get()
                ->groupBy('administration_id');
            $currentConversionsByAdmin = AssessmentConversionRevision::query()
                ->whereIn('administration_id', $administrationIds)
                ->where('is_current', true)
                ->with(['rawDependencies', 'normPackageVersion.normPackage'])
                ->get()
                ->groupBy('administration_id');
            $resultSetsByAdmin = AssessmentResultSet::query()
                ->whereIn('administration_id', $administrationIds)
                ->where('status', AssessmentResultSet::STATUS_FINALIZED)
                ->get()
                ->groupBy('administration_id');
        }

        foreach ($currentAssignments as $assignment) {
            $attemptId = (int) $assignment->admission_attempt_id;
            $session = $assignment->examSession;
            $gradingSession = $session->gradingSession;
            $recordKey = $gradingSession->id.':'.$attemptId;
            $attemptRecords = $scoreRecordsByKey->get($recordKey, collect());
            $administrationRows = $activeAdministrationsByAssignment->get((int) $assignment->id, collect());

            if ($administrationRows->isEmpty() && $session->test_instrument_version_id === null
                && $administrationsByAssignment->get((int) $assignment->id, collect())->isEmpty()) {
                $evaluation = $this->predicate->evaluateLegacyIndividual($legacyAreas, $attemptRecords);
                $fingerprint = $evaluation['ok']
                    ? $this->legacyFingerprint(
                        $attemptId,
                        (int) $session->id,
                        (int) $gradingSession->id,
                        $legacyAreas,
                        $attemptRecords,
                    )
                    : null;
                $readiness[$attemptId] = $this->resultFromEvaluation(
                    $evaluation,
                    (int) $session->id,
                    (int) $gradingSession->id,
                    null,
                    null,
                    $gradingSession->status === GradingSession::STATUS_FINALIZED,
                    $gradingSession->finalized_at?->toIso8601String(),
                    null,
                    [],
                    $fingerprint,
                );

                continue;
            }

            if ($administrationRows->count() !== 1) {
                continue;
            }

            /** @var AssessmentAdministration $administration */
            $administration = $administrationRows->first();
            $rawRevisions = $currentRawRevisionsByAdmin->get((int) $administration->id, collect());
            $conversionRevisions = $currentConversionsByAdmin->get((int) $administration->id, collect());
            $resultSets = $resultSetsByAdmin->get((int) $administration->id, collect());
            if ($conversionRevisions->count() > 1 || $resultSets->count() > 1) {
                $readiness[$attemptId] = $this->blocked(
                    $resultSets->count() > 1 ? 'frozen_snapshot_ambiguous' : 'conversion_ambiguous',
                    'The exact sitting has ambiguous current scoring or frozen snapshot evidence.',
                    (int) $session->id,
                    (int) $gradingSession->id,
                    (int) $administration->id,
                );

                continue;
            }

            /** @var AssessmentConversionRevision|null $conversion */
            $conversion = $conversionRevisions->first();
            /** @var AssessmentResultSet|null $resultSet */
            $resultSet = $resultSets->first();
            $locked = $administration->binding_state === AssessmentAdministration::STATE_FINALIZED || $resultSet !== null;
            if ($resultSet !== null && ! $this->frozenSnapshotMatchesCurrentEvidence(
                $resultSet,
                $administration,
                $session,
                $gradingSession,
                $rawRevisions,
                $conversion,
            )) {
                $readiness[$attemptId] = $this->blocked(
                    'frozen_snapshot_stale_or_wrong_sitting',
                    'The frozen result snapshot does not match the exact current sitting and scoring revisions.',
                    (int) $session->id,
                    (int) $gradingSession->id,
                    (int) $administration->id,
                    (int) $resultSet->id,
                    true,
                    $resultSet->finalized_at?->toIso8601String(),
                    (int) $conversion?->id ?: null,
                    $this->rawRevisionIds($rawRevisions),
                );

                continue;
            }

            if ($locked && $resultSet === null) {
                $readiness[$attemptId] = $this->blocked(
                    'frozen_snapshot_missing',
                    'This administration is locked but has no matching immutable result snapshot.',
                    (int) $session->id,
                    (int) $gradingSession->id,
                    (int) $administration->id,
                    null,
                    true,
                    null,
                    (int) $conversion?->id ?: null,
                    $this->rawRevisionIds($rawRevisions),
                );

                continue;
            }

            $evaluation = $this->predicate->evaluateAdministrationIndividual(
                $administration,
                $attemptRecords,
                $rawRevisions,
                $conversion,
            );
            $rawRevisionIds = $this->rawRevisionIds($rawRevisions);
            $fingerprint = $evaluation['ok']
                ? $this->fingerprint($administration, $rawRevisionIds, $conversion)
                : null;
            $readiness[$attemptId] = $this->resultFromEvaluation(
                $evaluation,
                (int) $session->id,
                (int) $gradingSession->id,
                (int) $administration->id,
                $resultSet?->id === null ? null : (int) $resultSet->id,
                $locked,
                $resultSet?->finalized_at?->toIso8601String(),
                $conversion?->id === null ? null : (int) $conversion->id,
                $rawRevisionIds,
                $fingerprint,
            );
        }

        return $readiness;
    }

    /**
     * @param  array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}  $evaluation
     * @param  list<int>  $rawRevisionIds
     * @return array{
     *     ready: bool,
     *     reason: ?string,
     *     state: string,
     *     exam_session_id: ?int,
     *     grading_session_id: ?int,
     *     administration_id: ?int,
     *     result_set_id: ?int,
     *     score_locked: bool,
     *     score_locked_at: ?string,
     *     finalized_at: ?string,
     *     conversion_revision_id: ?int,
     *     raw_revision_ids: list<int>,
     *     current_fingerprint: ?string
     * }
     */
    private function resultFromEvaluation(
        array $evaluation,
        int $examSessionId,
        int $gradingSessionId,
        ?int $administrationId,
        ?int $resultSetId,
        bool $scoreLocked,
        ?string $finalizedAt,
        ?int $conversionRevisionId,
        array $rawRevisionIds,
        ?string $fingerprint = null,
    ): array {
        return [
            'ready' => $evaluation['ok'],
            'reason' => $evaluation['reason'],
            'state' => $scoreLocked && $evaluation['ok'] ? 'frozen' : $evaluation['state'],
            'exam_session_id' => $examSessionId,
            'grading_session_id' => $gradingSessionId,
            'administration_id' => $administrationId,
            'result_set_id' => $resultSetId,
            'score_locked' => $scoreLocked,
            'score_locked_at' => $scoreLocked ? $finalizedAt : null,
            'finalized_at' => $finalizedAt,
            'conversion_revision_id' => $conversionRevisionId,
            'raw_revision_ids' => $rawRevisionIds,
            'current_fingerprint' => $fingerprint,
        ];
    }

    /**
     * @param  list<int>  $rawRevisionIds
     * @return array{
     *     ready: bool,
     *     reason: ?string,
     *     state: string,
     *     exam_session_id: ?int,
     *     grading_session_id: ?int,
     *     administration_id: ?int,
     *     result_set_id: ?int,
     *     score_locked: bool,
     *     score_locked_at: ?string,
     *     finalized_at: ?string,
     *     conversion_revision_id: ?int,
     *     raw_revision_ids: list<int>,
     *     current_fingerprint: ?string
     * }
     */
    private function blocked(
        string $state,
        string $reason,
        ?int $examSessionId = null,
        ?int $gradingSessionId = null,
        ?int $administrationId = null,
        ?int $resultSetId = null,
        bool $scoreLocked = false,
        ?string $finalizedAt = null,
        ?int $conversionRevisionId = null,
        array $rawRevisionIds = [],
    ): array {
        return [
            'ready' => false,
            'reason' => $reason,
            'state' => $state,
            'exam_session_id' => $examSessionId,
            'grading_session_id' => $gradingSessionId,
            'administration_id' => $administrationId,
            'result_set_id' => $resultSetId,
            'score_locked' => $scoreLocked,
            'score_locked_at' => $scoreLocked ? $finalizedAt : null,
            'finalized_at' => $finalizedAt,
            'conversion_revision_id' => $conversionRevisionId,
            'raw_revision_ids' => $rawRevisionIds,
            'current_fingerprint' => null,
        ];
    }

    /**
     * @param  Collection<int, AssessmentRawResultRevision>  $rawRevisions
     * @return list<int>
     */
    private function rawRevisionIds(Collection $rawRevisions): array
    {
        return $rawRevisions
            ->where('status', AssessmentRawResultRevision::STATUS_VALID)
            ->sortBy('metric_code')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $rawRevisionIds
     */
    private function fingerprint(
        AssessmentAdministration $administration,
        array $rawRevisionIds,
        ?AssessmentConversionRevision $conversion,
    ): string {
        return hash('sha256', json_encode([
            'administration_id' => (int) $administration->id,
            'raw_revision_ids' => $rawRevisionIds,
            'conversion_revision_id' => $conversion?->id === null ? null : (int) $conversion->id,
            'raw_dependency_sha256' => $conversion?->raw_dependency_sha256,
            'typed_outputs_sha256' => hash('sha256', json_encode((array) ($conversion?->typed_outputs_json ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  Collection<int, AptitudeArea>  $areas
     * @param  Collection<int, ScoreRecord>  $scoreRecords
     */
    private function legacyFingerprint(
        int $attemptId,
        int $examSessionId,
        int $gradingSessionId,
        Collection $areas,
        Collection $scoreRecords,
    ): string {
        $areaVector = $areas
            ->sortBy('id')
            ->map(static fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => (string) $area->code,
                'version' => (int) $area->version,
                'max_items' => (int) $area->max_items,
                'scoring_method' => (string) $area->scoring_method,
                'conversion_rows' => $area->conversionRows
                    ->map(static fn (ScoreConversionRow $row): array => [
                        'id' => (int) $row->id,
                        'min_score' => (int) $row->min_score,
                        'max_score' => (int) $row->max_score,
                        'output_value' => (string) $row->output_value,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
        $scoreVector = $scoreRecords
            ->sortBy([['aptitude_area_id', 'asc'], ['id', 'asc']])
            ->map(static fn (ScoreRecord $record): array => [
                'id' => (int) $record->id,
                'aptitude_area_id' => (int) $record->aptitude_area_id,
                'score' => $record->score,
                'converted_score' => $record->converted_score,
                'source' => (string) $record->source,
                'capture_sheet_id' => $record->capture_sheet_id === null ? null : (int) $record->capture_sheet_id,
                'confidence' => $record->confidence,
                'updated_at' => $record->updated_at?->toISOString(),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'mode' => 'legacy_untyped',
            'attempt_id' => $attemptId,
            'exam_session_id' => $examSessionId,
            'grading_session_id' => $gradingSessionId,
            'areas' => $areaVector,
            'score_records' => $scoreVector,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  Collection<int, AssessmentRawResultRevision>  $currentRawRevisions
     */
    private function frozenSnapshotMatchesCurrentEvidence(
        AssessmentResultSet $resultSet,
        AssessmentAdministration $administration,
        ExamSession $examSession,
        GradingSession $gradingSession,
        Collection $currentRawRevisions,
        ?AssessmentConversionRevision $currentConversion,
    ): bool {
        if ((int) $resultSet->administration_id !== (int) $administration->id
            || (int) $resultSet->exam_session_id !== (int) $examSession->id
            || (int) $resultSet->grading_session_id !== (int) $gradingSession->id
            || $resultSet->status !== AssessmentResultSet::STATUS_FINALIZED) {
            return false;
        }

        $snapshotRawVector = (array) $resultSet->raw_revision_vector_json;
        $currentRawVector = $currentRawRevisions->where('status', AssessmentRawResultRevision::STATUS_VALID);
        if ($currentRawVector->isEmpty()) {
            $currentRawVector = $currentRawRevisions;
        }
        $snapshotRawIds = [];
        foreach ($snapshotRawVector as $metricCode => $entry) {
            if (! is_array($entry) || ! is_numeric($entry['id'] ?? null)) {
                return false;
            }
            $snapshotRawIds[(string) $metricCode] = (int) $entry['id'];
        }
        $currentRawIds = $currentRawVector
            ->mapWithKeys(static fn (AssessmentRawResultRevision $revision): array => [(string) $revision->metric_code => (int) $revision->id])
            ->all();
        ksort($snapshotRawIds);
        ksort($currentRawIds);
        if ($snapshotRawIds !== $currentRawIds) {
            return false;
        }

        $conversionVector = (array) $resultSet->conversion_revision_vector_json;
        $snapshotConversionId = $conversionVector['conversion_revision_id'] ?? null;
        $currentConversionId = $currentConversion?->id;

        return ($snapshotConversionId === null ? null : (int) $snapshotConversionId)
            === ($currentConversionId === null ? null : (int) $currentConversionId);
    }
}
