<?php

namespace App\Examination\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UnpublishExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly BindAssessmentAdministration $binder,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function handle(ExamSession $session, User $actor, int $expectedVersion, string $reason): ExamSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Unpublish requires a reason.');
        }

        $created = collect();
        $unpublished = DB::transaction(function () use ($session, $actor, $expectedVersion, $reason, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }
            if ($session->status !== ExamSession::STATUS_PUBLISHED) {
                throw new RuntimeException('Only published sessions may be unpublished.');
            }
            if ($session->starts_at !== null && $session->starts_at->isPast()) {
                throw new RuntimeException('A session cannot be unpublished after it starts.');
            }
            if ($this->hasOperationalEvidence($session)) {
                throw new RuntimeException('A session with attendance, submission, or capture evidence cannot be unpublished.');
            }
            $applicantRecipients = NotificationRecipients::assignedApplicantsFor($session);
            $staffRecipients = NotificationRecipients::testAdministratorUsersFor($session, $this->authorizer);

            $supersededAt = now();
            $logisticsPlan = ExamSessionLogisticsPlan::query()
                ->where('exam_session_id', $session->id)->where('is_current', true)
                ->lockForUpdate()->first();
            $snapshot = null;
            if ($logisticsPlan !== null) {
                $allocations = ExamAssignmentLogisticsSeatAllocation::query()
                    ->where('logistics_plan_id', $logisticsPlan->id)->whereNull('effective_to')
                    ->orderBy('id')->lockForUpdate()->get();
                $supersededSecond = now()->floorSecond();
                foreach ($allocations as $allocation) {
                    $fromSecond = $allocation->effective_from?->copy()->floorSecond();
                    $allocation->update([
                        'effective_to' => $fromSecond !== null && $fromSecond->gte($supersededSecond)
                            ? $fromSecond->copy()->addSecond()
                            : $supersededSecond,
                    ]);
                }
                $logisticsPlan->update(['is_current' => false]);
            } else {
                $snapshot = ExamSessionMaterialSnapshot::query()
                    ->where('exam_session_id', $session->id)->where('is_current', true)
                    ->lockForUpdate()->first();
                if ($snapshot !== null) {
                    $allocations = ExamAssignmentSeatAllocation::query()
                        ->whereNull('effective_to')
                        ->whereHas('sessionSeat', fn ($query) => $query->where('material_snapshot_id', $snapshot->id))
                        ->orderBy('id')->lockForUpdate()->get();
                    $supersededSecond = now()->floorSecond();
                    foreach ($allocations as $allocation) {
                        $fromSecond = $allocation->effective_from?->copy()->floorSecond();
                        $allocation->update([
                            'effective_to' => $fromSecond !== null && $fromSecond->gte($supersededSecond)
                                ? $fromSecond->copy()->addSecond()
                                : $supersededSecond,
                        ]);
                    }
                    $snapshot->update([
                        'is_current' => false,
                        'superseded_at' => $supersededAt,
                    ]);
                }
            }

            $this->binder->supersedeSessionBindings($session, $snapshot);

            $session->update([
                'status' => ExamSession::STATUS_DRAFT,
                'published_at' => null,
                'notification_policy_snapshot' => null,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_unpublished', [
                'exam_session_id' => $session->id,
                'reason' => $reason,
                'logistics_plan_id' => $logisticsPlan?->id,
                'logistics_plan_revision' => $logisticsPlan?->revision,
                'material_snapshot_id' => $snapshot?->id,
                'material_snapshot_revision' => $snapshot?->revision,
            ], (string) $actor->id);

            if ($applicantRecipients->isNotEmpty()) {
                $applicantIntent = NotificationIntent::v1(
                    eventType: 'exam.session_unpublished.v1',
                    templateKey: 'exam.session_unpublished',
                    payload: [
                        ...AdmissionExaminationNoticeContext::examinationPayload($session, 'session-unpublished'),
                        'subject' => "Exam schedule update: {$session->code}",
                        'body' => "The published schedule for exam session {$session->code} was returned to draft for revision ({$reason}). Please wait for the updated schedule.",
                        'action_label' => 'View schedule',
                        'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                        'session_id' => $session->id,
                        'code' => $session->code,
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
                );

                $created = $created->concat($this->dispatcher->dispatch(
                    $applicantIntent,
                    hash('sha256', "exam.session_unpublished:applicants:{$session->id}:v{$session->version}"),
                    $applicantRecipients,
                    $applicantIntent->channels,
                ));
            }

            if ($staffRecipients->isNotEmpty()) {
                $staffIntent = NotificationIntent::v1(
                    eventType: 'exam.session_unpublished.v1',
                    templateKey: 'exam.session_unpublished.staff',
                    payload: [
                        'subject' => "Session {$session->code} returned to draft",
                        'body' => "Exam session {$session->code} was unpublished ({$reason}). Review the session in Exam Scheduling.",
                        'action_label' => 'View exam session',
                        'action_url' => route('exams.sessions.index'),
                        'session_id' => $session->id,
                        'code' => $session->code,
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP],
                );

                $created = $created->concat($this->dispatcher->dispatch(
                    $staffIntent,
                    hash('sha256', "exam.session_unpublished:staff:{$session->id}:v{$session->version}"),
                    $staffRecipients,
                    $staffIntent->channels,
                ));
            }

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $unpublished;
    }

    private function hasOperationalEvidence(ExamSession $session): bool
    {
        $assignmentIds = DB::table('exam_assignments')
            ->where('exam_session_id', $session->id)
            ->select('id');

        if (DB::table('exam_attendance_records')->whereIn('exam_assignment_id', $assignmentIds)->exists()) {
            return true;
        }
        if (DB::table('exam_submission_records')->whereIn('exam_assignment_id', $assignmentIds)->exists()) {
            return true;
        }
        $sessionAttemptIds = DB::table('exam_assignments')
            ->where('exam_session_id', $session->id)
            ->select('admission_attempt_id');
        $administrationIds = DB::table('assessment_administrations')
            ->whereIn('exam_assignment_id', $assignmentIds)
            ->whereIn('admission_attempt_id', $sessionAttemptIds)
            ->select('id');
        foreach (['assessment_scoring_runs', 'assessment_raw_result_revisions', 'assessment_conversion_revisions', 'assessment_result_sets'] as $table) {
            if (DB::table($table)->whereIn('administration_id', $administrationIds)->exists()) {
                return true;
            }
        }

        return DB::table('omr_capture_sheets')
            ->join('omr_capture_batches', 'omr_capture_batches.id', '=', 'omr_capture_sheets.batch_id')
            ->join('grading_sessions', 'grading_sessions.id', '=', 'omr_capture_batches.grading_session_id')
            ->where('grading_sessions.exam_session_id', $session->id)
            ->exists();
    }
}
