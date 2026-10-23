<?php

namespace App\Scheduling\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class AutoAssignEligibleAttempt
{
    public function __construct(
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    /**
     * @return array{
     *     assigned: bool,
     *     reason: ?string,
     *     session: ExamSession|ConsultationSession|null,
     *     assignment: ExamAssignment|ConsultationAssignment|null,
     * }
     */
    public function handle(string $domain, AdmissionAttempt $attempt, User $actor): array
    {
        if (! in_array($domain, [AssignmentInvariant::DOMAIN_EXAM, AssignmentInvariant::DOMAIN_CONSULTATION], true)) {
            throw new InvalidArgumentException("Unsupported domain [{$domain}].");
        }

        return $this->planningMutex->run($domain, (int) $attempt->academic_cycle_id, function () use ($domain, $attempt, $actor): array {
            if ($domain === AssignmentInvariant::DOMAIN_EXAM) {
                $sessions = ExamSession::query()
                    ->forPracticeSet($attempt->practice_fixture_set_id)
                    ->autoAssignEligible((int) $attempt->academic_cycle_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->with('room')
                    ->get();
            } else {
                $sessions = ConsultationSession::query()
                    ->autoAssignEligible((int) $attempt->academic_cycle_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->with('room')
                    ->get();
            }

            $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($domain === AssignmentInvariant::DOMAIN_CONSULTATION) {
                if ($lockedAttempt->practice_fixture_set_id !== null) {
                    return [
                        'assigned' => false,
                        'reason' => 'practice_requires_explicit_consultation_handoff',
                        'session' => null,
                        'assignment' => null,
                    ];
                }

                if (
                    AttemptProgramFacts::isAdvised($lockedAttempt)
                    || ConsultationAssignment::query()
                        ->where('admission_attempt_id', $lockedAttempt->id)
                        ->where('status', ConsultationAssignment::STATUS_CONSULTED)
                        ->exists()
                ) {
                    return ['assigned' => false, 'reason' => 'already_advised', 'session' => null, 'assignment' => null];
                }

                if ($this->invariant->consultationResultReadyDetails($lockedAttempt) === null) {
                    return ['assigned' => false, 'reason' => 'ineligible', 'session' => null, 'assignment' => null];
                }

                $hasActive = ConsultationAssignment::query()->active()->where('admission_attempt_id', $lockedAttempt->id)->exists();
                if ($hasActive) {
                    return ['assigned' => false, 'reason' => 'already_assigned', 'session' => null, 'assignment' => null];
                }
            } else {
                if (! $lockedAttempt->isExamEligible()) {
                    return ['assigned' => false, 'reason' => 'ineligible', 'session' => null, 'assignment' => null];
                }

                ExamAssignment::releaseTerminalMissedAssignmentsForAttempt(
                    (int) $lockedAttempt->id,
                    'Reassigned via continuous auto-assignment after prior absence',
                );
                $hasActive = ExamAssignment::query()->blockingScheduling()->where('admission_attempt_id', $lockedAttempt->id)->exists();
                if ($hasActive) {
                    return ['assigned' => false, 'reason' => 'already_assigned', 'session' => null, 'assignment' => null];
                }
            }

            $orderedSessions = $sessions->sort(function (ExamSession|ConsultationSession $a, ExamSession|ConsultationSession $b) use ($domain): int {
                $timeA = $domain === AssignmentInvariant::DOMAIN_EXAM
                    ? ($a->starts_at?->toISOString() ?? '')
                    : ($a->scheduled_at?->toISOString() ?? '');
                $timeB = $domain === AssignmentInvariant::DOMAIN_EXAM
                    ? ($b->starts_at?->toISOString() ?? '')
                    : ($b->scheduled_at?->toISOString() ?? '');

                $timeCompare = strcmp($timeA, $timeB);
                if ($timeCompare !== 0) {
                    return $timeCompare;
                }

                return $a->id <=> $b->id;
            });

            $targetSession = null;
            foreach ($orderedSessions as $session) {
                if ($this->invariant->remainingCapacity($session) > 0) {
                    $targetSession = $session;
                    break;
                }
            }

            if ($targetSession === null) {
                $this->notifyNoCapacity($domain, $lockedAttempt);

                return [
                    'assigned' => false,
                    'reason' => 'no_capacity',
                    'session' => null,
                    'assignment' => null,
                ];
            }

            if ($domain === AssignmentInvariant::DOMAIN_EXAM) {
                $assignment = ExamAssignment::query()->create([
                    'exam_session_id' => $targetSession->id,
                    'admission_attempt_id' => $lockedAttempt->id,
                    'status' => ExamAssignment::STATUS_ASSIGNED,
                    'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                    'origin' => ExamAssignment::ORIGIN_CONTINUOUS,
                    'version' => 1,
                ]);

                $targetSession->increment('version');

                $this->recordAuditEvent->handle('exam.continuous_assignment_created', [
                    'assignment_id' => $assignment->id,
                    'exam_session_id' => $targetSession->id,
                    'attempt_id' => $lockedAttempt->id,
                    'origin' => ExamAssignment::ORIGIN_CONTINUOUS,
                ], (string) $actor->id);
            } else {
                $assignment = ConsultationAssignment::query()->create([
                    'consultation_session_id' => $targetSession->id,
                    'admission_attempt_id' => $lockedAttempt->id,
                    'status' => ConsultationAssignment::STATUS_ASSIGNED,
                    'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
                    'origin' => ConsultationAssignment::ORIGIN_CONTINUOUS,
                    'assigned_by_user_id' => $actor->id,
                    'version' => 1,
                ]);

                $targetSession->increment('version');

                $this->recordAuditEvent->handle('consultation.continuous_assignment_created', [
                    'assignment_id' => $assignment->id,
                    'consultation_session_id' => $targetSession->id,
                    'attempt_id' => $lockedAttempt->id,
                    'origin' => ConsultationAssignment::ORIGIN_CONTINUOUS,
                ], (string) $actor->id);
            }

            return [
                'assigned' => true,
                'reason' => null,
                'session' => $targetSession,
                'assignment' => $assignment,
            ];
        });
    }

    private function notifyNoCapacity(string $domain, AdmissionAttempt $attempt): void
    {
        $hasEnabledSessions = $domain === AssignmentInvariant::DOMAIN_EXAM
            ? ExamSession::query()
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->where('auto_assign_enabled', true)
                ->exists()
            : ConsultationSession::query()
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->where('auto_assign_enabled', true)
                ->exists();

        if (! $hasEnabledSessions) {
            return;
        }

        $idempotencyKey = hash('sha256', "scheduling.no_capacity_operational:{$domain}:{$attempt->academic_cycle_id}:attempt:{$attempt->id}");

        Log::warning("Scheduling capacity exhausted for {$domain} auto-assignment.", [
            'domain' => $domain,
            'academic_cycle_id' => (int) $attempt->academic_cycle_id,
            'attempt_id' => (int) $attempt->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $staffUsers = User::query()
            ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                ->when((int) $attempt->institution_id > 0, fn (Builder $q): Builder => $q->where('institution_id', (int) $attempt->institution_id))
                ->where('office', 'guidance')
                ->where('status', StaffProfile::STATUS_ACTIVE))
            ->get();

        if ($staffUsers->isEmpty()) {
            $staffUsers = User::query()
                ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                    ->where('office', 'guidance')
                    ->where('status', StaffProfile::STATUS_ACTIVE))
                ->get();
        }

        if ($staffUsers->isNotEmpty()) {
            $intent = NotificationIntent::v1(
                eventType: "scheduling.{$domain}.operational_no_capacity.v1",
                templateKey: 'scheduling.operational_no_capacity',
                payload: [
                    'subject' => "Scheduling capacity exhausted for {$domain}",
                    'body' => "All enabled {$domain} sessions in cycle {$attempt->academic_cycle_id} are at full capacity.",
                    'attempt_id' => $attempt->id,
                    'domain' => $domain,
                    'academic_cycle_id' => $attempt->academic_cycle_id,
                ],
            );

            $created = $this->dispatcher->dispatch(
                $intent,
                $idempotencyKey,
                $staffUsers,
                $intent->channels,
            );

            NotificationIntentDispatcher::enqueueAfterCommit($created);
        }
    }
}
