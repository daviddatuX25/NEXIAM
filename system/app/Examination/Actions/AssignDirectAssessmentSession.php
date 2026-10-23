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

final class AssignDirectAssessmentSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly BindAssessmentAdministration $binder,
        private readonly EnsureGradingSessionForCompletedExam $ensureGradingSession,
    ) {}

    /**
     * @return array{assignment: ExamAssignment, grading_session: GradingSession}
     */
    public function handle(
        AcademicCycle $cycle,
        ?Room $room,
        AdmissionAttempt $attempt,
        User $actor,
        ?ExamSession $targetSession = null,
        ?TestKitVersion $kitVersion = null,
    ): array {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $cycle->id,
            function () use ($cycle, $room, $attempt, $actor, $targetSession, $kitVersion): array {
                $session = $targetSession ?? ExamSession::query()->firstOrCreate(
                    [
                        'academic_cycle_id' => $cycle->id,
                        'code' => 'EX-INTAKE-'.$cycle->id,
                    ],
                    [
                        'room_id' => $room?->id,
                        'status' => ExamSession::STATUS_COMPLETED,
                        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                        'starts_at' => now()->subHours(2),
                        'effective_end_at' => now()->subHour(),
                        'closed_at' => now(),
                        'version' => 1,
                    ],
                );

                $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
                $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                if ((int) $session->academic_cycle_id !== (int) $cycle->id || $session->status !== ExamSession::STATUS_COMPLETED) {
                    throw new \RuntimeException('Direct assessment import requires a completed session in the batch academic cycle.');
                }

                if ($kitVersion === null) {
                    throw new \InvalidArgumentException('An explicit locked Test Kit version is required for direct assessment work.');
                }

                $kitVersion = TestKitVersion::query()->findOrFail($kitVersion->id);
                $profileVersion = $kitVersion->test_instrument_version_id === null
                    ? null
                    : TestInstrumentVersion::query()->findOrFail($kitVersion->test_instrument_version_id);

                if ($session->test_kit_version_id !== null
                    && (int) $session->test_kit_version_id !== (int) $kitVersion->id) {
                    throw new \RuntimeException('The selected Test Kit version does not match the target session.');
                }
                if ($session->test_instrument_version_id !== null
                    && (int) $session->test_instrument_version_id !== (int) $profileVersion?->id) {
                    throw new \RuntimeException('The selected Test Kit profile does not match the target session.');
                }
                if ($session->booklet_version_id !== null
                    && (int) $session->booklet_version_id !== (int) $kitVersion->booklet_version_id) {
                    throw new \RuntimeException('The selected Test Kit Booklet does not match the target session.');
                }
                $session->test_kit_version_id = $kitVersion->id;
                $session->test_instrument_version_id = $profileVersion?->id;
                $session->booklet_version_id = $kitVersion->booklet_version_id;
                $session->save();

                $assignment = ExamAssignment::query()->firstOrCreate(
                    [
                        'exam_session_id' => $session->id,
                        'admission_attempt_id' => $attempt->id,
                    ],
                    [
                        'status' => ExamAssignment::STATUS_ASSIGNED,
                        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                        'origin' => ExamAssignment::ORIGIN_MANUAL,
                        'version' => 1,
                    ],
                );

                $operation = $profileVersion?->isFinalOnly() === true ? 'external_final' : 'import';
                $acquisitionChannel = $profileVersion?->isFinalOnly() === true ? 'import' : null;
                $this->binder->handle($assignment, $kitVersion, $actor, $operation, acquisitionChannel: $acquisitionChannel);
                ExamAttendanceRecord::query()->firstOrCreate(
                    ['exam_assignment_id' => $assignment->id],
                    [
                        'recorded_by_user_id' => $actor->id,
                        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
                        'idempotency_key' => hash('sha256', "direct-attendance:{$assignment->id}"),
                        'recorded_at' => now(),
                    ],
                );

                ExamSubmissionRecord::query()->firstOrCreate(
                    ['exam_assignment_id' => $assignment->id],
                    [
                        'recorded_by_user_id' => $actor->id,
                        'idempotency_key' => hash('sha256', "direct-submission:{$assignment->id}"),
                        'submitted_at' => now(),
                    ],
                );

                $gradingSession = $this->ensureGradingSession->handle($session, $actor, 'ingestion');

                $this->recordAuditEvent->handle('exam.direct_assessment_assigned', [
                    'exam_session_id' => $session->id,
                    'assignment_id' => $assignment->id,
                    'attempt_id' => $attempt->id,
                ], (string) $actor->id);

                return [
                    'assignment' => $assignment,
                    'grading_session' => $gradingSession,
                ];
            },
        );
    }
}
