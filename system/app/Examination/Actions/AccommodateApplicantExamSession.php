<?php

namespace App\Examination\Actions;

use App\Examination\Support\PublishedSessionSeatAllocator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionSeat;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccommodateApplicantExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $assignmentInvariant,
        private readonly BindAssessmentAdministration $binder,
        private readonly PublishedSessionSeatAllocator $seatAllocator,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(
        ExamSession $targetSession,
        AdmissionAttempt $attempt,
        User $actor,
        string $reasonCategory,
        string $reasonNotes,
    ): ExamAssignment {
        if (! in_array($reasonCategory, ['travel_distance', 'tardiness_consideration', 'administrative_override'], true)) {
            throw new RuntimeException('Invalid accommodation reason category.');
        }

        $reasonNotes = trim($reasonNotes);
        if (mb_strlen($reasonNotes) < 3) {
            throw new RuntimeException('Accommodation notes are required.');
        }

        $created = collect();
        $result = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $targetSession->academic_cycle_id,
            function () use ($targetSession, $attempt, $actor, $reasonCategory, $reasonNotes, &$created): ExamAssignment {
                return DB::transaction(function () use ($targetSession, $attempt, $actor, $reasonCategory, $reasonNotes, &$created): ExamAssignment {
                    $targetSession->loadMissing('academicCycle', 'room');
                    $sessionInstitutionId = (int) ($targetSession->academicCycle?->institution_id ?? $targetSession->room?->institution_id);
                    $targetSession = ExamSession::query()
                        ->forWorkspace()
                        ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $sessionInstitutionId))
                        ->lockForUpdate()
                        ->findOrFail($targetSession->id);
                    $attempt = AdmissionAttempt::query()
                        ->lockForUpdate()
                        ->findOrFail($attempt->id);
                    $targetSession->loadMissing('academicCycle', 'room', 'currentLogisticsPlan', 'currentMaterialSnapshot');

                    if (! $attempt->isExamEligible()
                        || (int) $attempt->academic_cycle_id !== (int) $targetSession->academic_cycle_id
                        || (int) $attempt->institution_id !== (int) ($targetSession->academicCycle?->institution_id ?? $targetSession->room?->institution_id)
                        || (string) ($attempt->practice_fixture_set_id ?? '') !== (string) ($targetSession->practice_fixture_set_id ?? '')) {
                        throw new RuntimeException('Applicant is not eligible for this exam session.');
                    }
                    if (! in_array($targetSession->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                        throw new RuntimeException('Target session is not open for examination conduct.');
                    }

                    if ($targetSession->closed_at !== null
                        || ($targetSession->status === ExamSession::STATUS_IN_PROGRESS && $targetSession->effective_end_at !== null && now()->gt($targetSession->effective_end_at))) {
                        throw new RuntimeException('Target session conduct window has closed.');
                    }

                    $logisticsPlan = $targetSession->currentLogisticsPlan;
                    $snapshot = $logisticsPlan === null ? $targetSession->currentMaterialSnapshot : null;
                    if ($logisticsPlan === null && $snapshot === null) {
                        throw new RuntimeException('Target exam session has no current logistics or historical material plan.');
                    }
                    if ($this->assignmentInvariant->remainingCapacity($targetSession) <= 0) {
                        throw new RuntimeException('Target exam session is at maximum published capacity or has no available seat.');
                    }

                    $unoccupiedSeat = null;
                    if ($snapshot !== null) {
                        $unoccupiedSeat = ExamSessionSeat::query()
                            ->where('material_snapshot_id', $snapshot->id)
                            ->whereDoesntHave('allocations', fn ($query) => $query->whereNull('effective_to'))
                            ->orderBy('ordinal')->lockForUpdate()->first();
                        if ($unoccupiedSeat === null) {
                            throw new RuntimeException('Target historical material manifest has no available seat.');
                        }
                    }
                    $existingAssignments = ExamAssignment::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->with(['examSession', 'attendanceRecord', 'submissionRecord'])
                        ->lockForUpdate()
                        ->get();

                    foreach ($existingAssignments as $existing) {
                        if ($existing->submissionRecord()->exists()) {
                            throw new RuntimeException('Applicant has already participated/submitted an exam attempt. Re-examination requires administrative policy clearance.');
                        }
                    }

                    $administrationIds = AssessmentAdministration::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->whereIn('exam_assignment_id', $existingAssignments->pluck('id'))
                        ->select('id');
                    $hasAssessmentUse = false;
                    foreach (['assessment_raw_result_revisions', 'assessment_scoring_runs', 'assessment_conversion_revisions', 'assessment_result_sets'] as $table) {
                        if (DB::table($table)->whereIn('administration_id', $administrationIds)->exists()) {
                            $hasAssessmentUse = true;
                            break;
                        }
                    }

                    if ($hasAssessmentUse || ScoreRecord::query()->where('admission_attempt_id', $attempt->id)->exists()) {
                        throw new RuntimeException('Applicant has already participated/submitted an exam attempt. Re-examination requires administrative policy clearance.');
                    }
                    foreach ($existingAssignments as $existing) {
                        $wasReleasedAtClose = ! $existing->isActive()
                            && in_array($existing->attendanceRecord?->outcome, [
                                ExamAttendanceRecord::OUTCOME_ABSENT,
                                ExamAttendanceRecord::OUTCOME_VOID,
                            ], true);

                        if (! $existing->isActive() && ! $wasReleasedAtClose) {
                            continue;
                        }

                        if ($existing->isActive() && (int) $existing->exam_session_id === (int) $targetSession->id) {
                            throw new RuntimeException('Applicant is already assigned to this exam session.');
                        }

                        if ($existing->isActive()
                            && $existing->examSession?->status === ExamSession::STATUS_COMPLETED
                            && $existing->attendanceRecord?->outcome !== ExamAttendanceRecord::OUTCOME_ABSENT) {
                            throw new RuntimeException('Cannot accommodate examinee from a completed session unless marked absent.');
                        }
                        if ($existing->isActive()
                            && $existing->examSession?->status === ExamSession::STATUS_IN_PROGRESS
                            && in_array($existing->attendanceRecord?->outcome, [ExamAttendanceRecord::OUTCOME_PRESENT, ExamAttendanceRecord::OUTCOME_VOID], true)) {
                            throw new RuntimeException('Cannot transfer an examinee who is already marked present or void in an in-progress session.');
                        }

                        $existing->update([
                            'status' => ExamAssignment::STATUS_REMOVED,
                            'ended_at' => now(),
                            'end_reason' => "Accommodated in {$targetSession->code}: {$reasonCategory} - {$reasonNotes}",
                            'version' => (int) $existing->version + 1,
                        ]);
                        AssessmentAdministration::query()
                            ->where('exam_assignment_id', $existing->id)
                            ->whereNull('superseded_at')
                            ->update(['superseded_at' => now()]);

                        $allocations = ExamAssignmentSeatAllocation::query()
                            ->where('exam_assignment_id', $existing->id)
                            ->whereNull('effective_to')
                            ->lockForUpdate()
                            ->get();

                        $accommodateSecond = now()->floorSecond();
                        foreach ($allocations as $alloc) {
                            $fromSecond = $alloc->effective_from?->copy()->floorSecond();
                            $closeTime = ($fromSecond !== null && $fromSecond->gte($accommodateSecond))
                                ? $fromSecond->copy()->addSecond()
                                : $accommodateSecond;
                            $alloc->update([
                                'effective_to' => $closeTime,
                                'reason' => "Accommodated in {$targetSession->code}",
                            ]);
                        }
                        $logisticsAllocations = ExamAssignmentLogisticsSeatAllocation::query()
                            ->where('exam_assignment_id', $existing->id)
                            ->whereNull('effective_to')->lockForUpdate()->get();
                        foreach ($logisticsAllocations as $allocation) {
                            $fromSecond = $allocation->effective_from?->copy()->floorSecond();
                            $allocation->update([
                                'effective_to' => $fromSecond !== null && $fromSecond->gte($accommodateSecond)
                                    ? $fromSecond->copy()->addSecond()
                                    : $accommodateSecond,
                                'reason' => "Accommodated in {$targetSession->code}",
                            ]);
                        }
                    }

                    $newAssignment = ExamAssignment::query()->create([
                        'exam_session_id' => $targetSession->id,
                        'admission_attempt_id' => $attempt->id,
                        'status' => ExamAssignment::STATUS_ASSIGNED,
                        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                        'origin' => ExamAssignment::ORIGIN_MANUAL,
                    ]);

                    $logisticsAllocation = null;
                    $seatLabel = $unoccupiedSeat?->seat_label;
                    $bookletSetId = $unoccupiedSeat?->booklet_set_id;
                    if ($logisticsPlan !== null) {
                        $this->seatAllocator->allocateIfPublished(
                            $targetSession,
                            $newAssignment,
                            $actor,
                            "Accommodated: {$reasonCategory} - {$reasonNotes}",
                        );
                        $logisticsAllocation = $newAssignment->activeLogisticsSeatAllocation()->with('seat')->first();
                        $seatLabel = $logisticsAllocation?->seat?->seat_label;
                    } elseif ($snapshot !== null && $unoccupiedSeat !== null) {
                        ExamAssignmentSeatAllocation::query()->create([
                            'exam_assignment_id' => $newAssignment->id,
                            'exam_session_seat_id' => $unoccupiedSeat->id,
                            'effective_from' => now(),
                            'reason' => "Accommodated: {$reasonCategory} - {$reasonNotes}",
                            'assigned_by_user_id' => $actor->id,
                        ]);
                        $materialSet = $snapshot->sets()->where('booklet_set_id', $unoccupiedSeat->booklet_set_id)->first();
                        $this->binder->bindScheduledAssignment($newAssignment, $snapshot, $unoccupiedSeat, $materialSet, $actor);
                    }

                    $this->recordAuditEvent->handle('exam.applicant_accommodated', [
                        'target_exam_session_id' => $targetSession->id,
                        'admission_attempt_id' => $attempt->id,
                        'new_assignment_id' => $newAssignment->id,
                        'seat_id' => $unoccupiedSeat?->id,
                        'seat_label' => $seatLabel,
                        'booklet_set_id' => $bookletSetId,
                        'logistics_plan_id' => $logisticsPlan?->id,
                        'logistics_seat_allocation_id' => $logisticsAllocation?->id,
                        'reason_category' => $reasonCategory,
                        'reason_notes' => $reasonNotes,
                    ], (string) $actor->id);

                    $identity = NotificationRecipients::applicantFor($attempt);
                    if ($identity !== null) {
                        $appointmentTiming = $targetSession->status === ExamSession::STATUS_IN_PROGRESS
                            ? 'which is in progress. Started at '.self::campusInstant($targetSession->started_at).'; expected end '.self::campusInstant($targetSession->effective_end_at)
                            : 'scheduled for '.$targetSession->starts_at?->format('F j, Y g:i A').' (campus time)';
                        $intent = NotificationIntent::v1(
                            eventType: 'exam.session_reassigned.v1',
                            templateKey: 'exam.session_reassigned',
                            payload: [
                                'subject' => "Your exam appointment has changed to {$targetSession->code}",
                                'body' => "Your exam appointment has changed to session {$targetSession->code}, {$appointmentTiming}. View your schedule for the current appointment details.",
                                'action_label' => 'View schedule',
                                'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                                'assignment_id' => (int) $newAssignment->id,
                                'session_id' => (int) $targetSession->id,
                                'session_version' => (int) $targetSession->version,
                                'session_status' => $targetSession->status,
                                'academic_cycle_id' => (int) $targetSession->academic_cycle_id,
                                'institution_id' => $sessionInstitutionId,
                                'starts_at' => $targetSession->starts_at?->toIso8601String(),
                                'started_at' => $targetSession->started_at?->toIso8601String(),
                                'effective_end_at' => $targetSession->effective_end_at?->toIso8601String(),
                            ],
                            channels: [NotificationDelivery::CHANNEL_IN_APP],
                        );
                        $created = $this->dispatcher->dispatch(
                            $intent,
                            hash('sha256', "exam.session_reassigned:assignment:{$newAssignment->id}"),
                            [$identity],
                            $intent->channels,
                        );
                    }

                    return $newAssignment->fresh([
                        'activeSeatAllocation.sessionSeat',
                        'activeLogisticsSeatAllocation.seat',
                        'examSession',
                    ]);
                });
            },
        );

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $result;
    }

    private static function campusInstant(?CarbonInterface $instant): string
    {
        return $instant?->toImmutable()->setTimezone('Asia/Manila')->format('F j, Y g:i A T') ?? 'Not recorded';
    }
}
