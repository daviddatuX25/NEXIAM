<?php

namespace App\Examination\Actions;

use App\Assessment\Support\EnsureGradingSessionForCompletedExam;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateDirectAssessmentCohort
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly BindAssessmentAdministration $binder,
        private readonly EnsureGradingSessionForCompletedExam $ensureGradingSession,
    ) {}

    /**
     * @param  array<int, int|string>  $attemptIds
     * @return array{session: ExamSession, grading_session: GradingSession, assignment_count: int}
     */
    public function handle(
        AcademicCycle $cycle,
        array $attemptIds,
        User $actor,
        ?string $label = null,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $effectiveEndAt = null,
        ?Room $room = null,
        ?TestKitVersion $kitVersion = null,
    ): array {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $cycle->id,
            function () use ($cycle, $attemptIds, $actor, $label, $startsAt, $effectiveEndAt, $room, $kitVersion): array {
                return DB::transaction(function () use ($cycle, $attemptIds, $actor, $label, $startsAt, $effectiveEndAt, $room, $kitVersion): array {
                    sort($attemptIds);

                    if ($attemptIds === []) {
                        throw new \InvalidArgumentException('At least one applicant must be selected.');
                    }

                    if ($kitVersion === null) {
                        throw new \InvalidArgumentException('An explicit locked Test Kit version is required for direct assessments.');
                    }

                    $kitVersion = TestKitVersion::query()->findOrFail($kitVersion->id);
                    $profileVersion = $kitVersion->test_instrument_version_id === null
                        ? null
                        : TestInstrumentVersion::query()->findOrFail($kitVersion->test_instrument_version_id);

                    $sessionData = [
                        'academic_cycle_id' => $cycle->id,
                        'code' => 'EX-DIR-'.$cycle->id.'-'.strtolower((string) Str::ulid()),
                        'label' => $label,
                        'room_id' => $room?->id,
                        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                        'status' => ExamSession::STATUS_COMPLETED,
                        'starts_at' => $startsAt ?? now()->subHours(2),
                        'effective_end_at' => $effectiveEndAt ?? now()->subHour(),
                        'closed_at' => now(),
                        'version' => 1,
                    ];

                    $sessionData['test_kit_version_id'] = $kitVersion->id;
                    $sessionData['test_instrument_version_id'] = $profileVersion?->id;
                    $sessionData['booklet_version_id'] = $kitVersion->booklet_version_id;
                    $session = ExamSession::query()->create($sessionData);

                    foreach ($attemptIds as $id) {
                        $attempt = AdmissionAttempt::query()->lockForUpdate()->find($id);

                        if ($attempt === null || $attempt->academic_cycle_id !== $cycle->id) {
                            throw new \DomainException("Applicant {$id} does not belong to the active cycle.");
                        }

                        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                            throw new \DomainException("Applicant {$attempt->reference_code} is not in accepted status ({$attempt->status}).");
                        }

                        $hasActiveAssignment = ExamAssignment::query()
                            ->where('admission_attempt_id', $attempt->id)
                            ->where('status', ExamAssignment::STATUS_ASSIGNED)
                            ->exists();

                        if ($hasActiveAssignment) {
                            throw new \DomainException("Applicant {$attempt->reference_code} already has an active examination assignment.");
                        }

                        $assignment = ExamAssignment::query()->create([
                            'exam_session_id' => $session->id,
                            'admission_attempt_id' => $attempt->id,
                            'status' => ExamAssignment::STATUS_ASSIGNED,
                            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                            'origin' => ExamAssignment::ORIGIN_MANUAL,
                            'version' => 1,
                        ]);

                        $operation = $profileVersion?->isFinalOnly() === true ? 'external_final' : 'manual';
                        $acquisitionChannel = $profileVersion?->isFinalOnly() === true ? 'manual' : null;
                        $this->binder->handle($assignment, $kitVersion, $actor, $operation, acquisitionChannel: $acquisitionChannel);

                        ExamAttendanceRecord::query()->create([
                            'exam_assignment_id' => $assignment->id,
                            'recorded_by_user_id' => $actor->id,
                            'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
                            'idempotency_key' => hash('sha256', "direct-attendance:{$assignment->id}"),
                            'recorded_at' => now(),
                        ]);

                        ExamSubmissionRecord::query()->create([
                            'exam_assignment_id' => $assignment->id,
                            'recorded_by_user_id' => $actor->id,
                            'idempotency_key' => hash('sha256', "direct-submission:{$assignment->id}"),
                            'submitted_at' => now(),
                        ]);
                    }

                    $gradingSession = $this->ensureGradingSession->handle($session, $actor, 'direct_assessment');

                    $this->recordAuditEvent->handle('exam.direct_assessment_assigned', [
                        'exam_session_id' => $session->id,
                        'attempt_ids' => $attemptIds,
                        'label' => $label,
                        'actor_user_id' => $actor->id,
                    ], (string) $actor->id);

                    return [
                        'session' => $session,
                        'grading_session' => $gradingSession,
                        'assignment_count' => count($attemptIds),
                    ];
                });
            },
        );
    }
}
