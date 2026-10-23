<?php

namespace App\Admission\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Server-owned thread lifecycle (D-092): participants are derived from the
 * case relationship, never client-chosen. Internal threads are PH<->Guidance
 * only; applicant-support threads require a current selection or targeted
 * follow-up and remain readable after clearance/sheet issue until an actual
 * legacy enrolled stamp exists for the attempt.
 */
final class RecordThreadService
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @return list<RecordThread>
     */
    public function forAttempt(AdmissionAttempt $attempt, string $threadType, User $actor): array
    {
        if (! in_array($threadType, [
            RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            RecordThread::TYPE_APPLICANT_SUPPORT,
        ], true)) {
            return [];
        }

        $attempt = AdmissionAttempt::query()->findOrFail($attempt->id);

        return RecordThread::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('thread_type', $threadType)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->filter(fn (RecordThread $thread): bool => $this->canRead($thread, $actor))
            ->values()
            ->all();
    }

    public function canRead(RecordThread $thread, User $actor): bool
    {
        return $this->readableThreadsQuery($actor)
            ->where('record_threads.id', $thread->id)
            ->exists();
    }

    /**
     * The authoritative SQL eligibility scope shared by exact-root reads and
     * bounded unread discovery. GF-21 actor capability/scope is resolved fresh
     * once before constructing the scoped candidate query.
     *
     * @return Builder<RecordThread>
     */
    public function readableThreadsQuery(User $actor): Builder
    {
        $freshActor = User::query()->find($actor->id);
        $empty = RecordThread::query()->whereRaw('1 = 0');
        if ($freshActor === null) {
            return $empty;
        }

        $identityId = $freshActor->applicantAccount()->value('applicant_identity_id');
        $profile = null;
        $actorScope = null;
        $practiceOffice = null;
        $programCodes = [];
        $requiresReleasedResults = false;

        if ($identityId !== null) {
            if (! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE)->allowed) {
                return $empty;
            }
            $threadTypes = [RecordThread::TYPE_APPLICANT_SUPPORT];
        } else {
            $profile = $freshActor->staffProfile()->first();
            $actorScope = $freshActor->actorScope();
            if ($profile === null
                || ! $profile->isActive()
                || $profile->deactivated_at !== null
                || $profile->institution_id === null
                || $actorScope->activeCycleCode === null
                || ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE, [
                    'institution_id' => $profile->institution_id,
                ])->allowed) {
                return $empty;
            }

            if ($actorScope->office === 'guidance') {
                if (! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)->allowed
                    && ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)->allowed) {
                    return $empty;
                }
                $threadTypes = [RecordThread::TYPE_INTERNAL_PH_GUIDANCE];
                $practiceOffice = 'guidance';
            } elseif ($actorScope->office === 'program' && $actorScope->programCodes !== []) {
                $threadTypes = [
                    RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
                    RecordThread::TYPE_APPLICANT_SUPPORT,
                ];
                $programCodes = $actorScope->programCodes;
                $practiceOffice = 'academic-leader';
                $requiresReleasedResults = true;
            } else {
                return $empty;
            }
        }

        $selectionStatuses = ProgramSelection::NON_TERMINAL_STATUSES;
        $selectionStatusPlaceholders = implode(',', array_fill(0, count($selectionStatuses), '?'));

        $query = RecordThread::query()
            ->select('record_threads.*')
            ->join('admission_attempts as eligible_attempts', 'eligible_attempts.id', '=', 'record_threads.admission_attempt_id')
            ->join('programs as eligible_programs', 'eligible_programs.id', '=', 'record_threads.program_id')
            ->join('academic_cycles as eligible_cycles', 'eligible_cycles.id', '=', 'eligible_attempts.academic_cycle_id')
            ->whereIn('record_threads.thread_type', $threadTypes)
            ->where('eligible_programs.is_active', true)
            ->whereColumn('eligible_programs.institution_id', 'eligible_attempts.institution_id')
            ->whereColumn('eligible_cycles.institution_id', 'eligible_attempts.institution_id');

        if ($identityId !== null) {
            $query->where('eligible_attempts.applicant_identity_id', $identityId);
        } else {
            $query->where('eligible_attempts.institution_id', $profile->institution_id)
                ->where('eligible_cycles.is_active', true)
                ->where('eligible_cycles.code', $actorScope->activeCycleCode);
            if ($programCodes !== []) {
                $query->whereIn('eligible_programs.code', $programCodes);
            }
        }
        $query->whereNotExists(function ($enrolled): void {
            $enrolled->selectRaw('1')
                ->from('admission_attempt_enrollment_stamps as enrolled_stamp')
                ->whereColumn('enrolled_stamp.admission_attempt_id', 'eligible_attempts.id')
                ->where('enrolled_stamp.stamp_type', EnrollmentStamp::STAMP_ENROLLED)
                ->whereNotExists(function ($newer): void {
                    $newer->selectRaw('1')
                        ->from('admission_attempt_enrollment_stamps as newer_stamp')
                        ->whereColumn('newer_stamp.admission_attempt_id', 'eligible_attempts.id')
                        ->whereColumn('newer_stamp.id', '>', 'enrolled_stamp.id');
                });
        });

        $query->where(function (Builder $relationships) use ($selectionStatuses, $selectionStatusPlaceholders): void {
            $relationships
                ->whereExists(function ($selection) use ($selectionStatuses, $selectionStatusPlaceholders): void {
                    $selection->selectRaw('1')
                        ->from('admission_attempt_program_selections')
                        ->whereColumn('admission_attempt_program_selections.admission_attempt_id', 'eligible_attempts.id')
                        ->whereColumn('admission_attempt_program_selections.program_id', 'record_threads.program_id')
                        ->whereIn('admission_attempt_program_selections.status', $selectionStatuses)
                        ->whereRaw(
                            'admission_attempt_program_selections.id = (SELECT MAX(current_selection.id) FROM admission_attempt_program_selections AS current_selection WHERE current_selection.admission_attempt_id = eligible_attempts.id AND current_selection.status IN ('.$selectionStatusPlaceholders.'))',
                            $selectionStatuses,
                        );
                })
                ->orWhereExists(function ($followUps): void {
                    $followUps->selectRaw('1')
                        ->from('admission_attempt_program_follow_ups')
                        ->whereColumn('admission_attempt_program_follow_ups.admission_attempt_id', 'eligible_attempts.id')
                        ->whereColumn('admission_attempt_program_follow_ups.program_id', 'record_threads.program_id')
                        ->whereIn('admission_attempt_program_follow_ups.status', ProgramFollowUp::OPEN_STATUSES);
                });
        });

        if ($identityId === null) {
            if ($requiresReleasedResults) {
                $query->where('eligible_attempts.status', AdmissionAttempt::STATUS_ACCEPTED)
                    ->where(function (Builder $eligibility): void {
                        $eligibility
                            ->whereExists(function ($releases): void {
                                $releases->selectRaw('1')
                                    ->from('result_releases')
                                    ->whereColumn('result_releases.admission_attempt_id', 'eligible_attempts.id')
                                    ->where('result_releases.is_current', true);
                            })
                            ->orWhereExists(function ($followUps): void {
                                $followUps->selectRaw('1')
                                    ->from('admission_attempt_program_follow_ups')
                                    ->whereColumn('admission_attempt_program_follow_ups.admission_attempt_id', 'eligible_attempts.id')
                                    ->whereColumn('admission_attempt_program_follow_ups.program_id', 'record_threads.program_id')
                                    ->whereIn('admission_attempt_program_follow_ups.status', ProgramFollowUp::OPEN_STATUSES);
                            });
                    });
            }

            $actorId = (int) $freshActor->id;
            $query->where(function (Builder $practice) use ($actorId, $practiceOffice): void {
                $practice->whereNull('eligible_attempts.practice_fixture_set_id')
                    ->orWhereExists(function ($sets) use ($actorId, $practiceOffice): void {
                        $sets->selectRaw('1')
                            ->from('practice_fixture_sets')
                            ->whereColumn('practice_fixture_sets.id', 'eligible_attempts.practice_fixture_set_id')
                            ->whereColumn('practice_fixture_sets.institution_id', 'eligible_attempts.institution_id')
                            ->whereColumn('practice_fixture_sets.academic_cycle_id', 'eligible_attempts.academic_cycle_id')
                            ->where('practice_fixture_sets.actor_bindings->'.$practiceOffice, $actorId)
                            ->whereExists(function ($identities): void {
                                $identities->selectRaw('1')
                                    ->from('applicant_identities')
                                    ->whereColumn('applicant_identities.id', 'eligible_attempts.applicant_identity_id')
                                    ->whereColumn('applicant_identities.practice_fixture_set_id', 'practice_fixture_sets.id');
                            });
                    });
            });
        }

        return $query;
    }

    public function canSend(RecordThread $thread, User $actor): bool
    {
        $freshThread = RecordThread::query()->find($thread->id);

        return $freshThread !== null
            && $freshThread->status === RecordThread::STATUS_OPEN
            && ($freshThread->attempt?->practice_fixture_set_id === null
                || PracticeFixtureSet::query()->whereKey($freshThread->attempt->practice_fixture_set_id)->where('status', 'active')->exists())
            && $this->canRead($freshThread, $actor);
    }

    public function markRead(
        RecordThread $thread,
        User $actor,
        string $observedSequence,
        string $observationToken,
    ): void {
        DB::transaction(function () use ($thread, $actor, $observedSequence, $observationToken): void {
            $attemptId = RecordThread::query()->whereKey($thread->id)->value('admission_attempt_id');
            $attempt = AdmissionAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            $currentThread = RecordThread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            if ((int) $currentThread->admission_attempt_id !== (int) $attempt->id || ! $this->canRead($currentThread, $actor)) {
                throw new RuntimeException('You are not authorized to read this thread.');
            }
            if (! CaseConversationToken::verify($actor, $currentThread->id, $observedSequence, $observationToken)) {
                throw new RuntimeException('Invalid or expired observation token.');
            }

            $read = RecordThreadRead::query()
                ->where('record_thread_id', $currentThread->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            $currentLastRead = (int) ($read?->last_read_sequence ?? 0);
            $observed = (int) $observedSequence;

            if ($observed > $currentLastRead) {
                if ($read === null) {
                    $read = new RecordThreadRead;
                    $read->record_thread_id = $currentThread->id;
                    $read->user_id = $actor->id;
                }
                $read->last_read_sequence = max($currentLastRead, $observed);
                $read->last_read_at = now();
                $read->save();

                $this->audit->handle('thread.message_read', [
                    'thread_id' => $currentThread->id,
                    'attempt_id' => $currentThread->admission_attempt_id,
                    'actor_id' => $actor->id,
                    'last_read_sequence' => $read->last_read_sequence,
                ], (string) $actor->id);
            }
        });
    }

    /**
     * @return LengthAwarePaginator<int, RecordThreadMessage>
     */
    public function messagesFor(RecordThread $thread, User $actor, int $perPage = 50): LengthAwarePaginator
    {
        return DB::transaction(function () use ($thread, $actor, $perPage): LengthAwarePaginator {
            $currentThread = RecordThread::query()->findOrFail($thread->id);
            AdmissionAttempt::query()
                ->whereKey($currentThread->admission_attempt_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canRead($currentThread, $actor)) {
                throw new RuntimeException('You are not authorized to read this thread.');
            }

            $this->audit->handle('thread.messages_viewed', [
                'thread_id' => $currentThread->id,
                'attempt_id' => $currentThread->admission_attempt_id,
                'program_id' => $currentThread->program_id,
                'actor_id' => $actor->id,
            ], (string) $actor->id);

            $query = RecordThreadMessage::query()
                ->where('record_thread_id', $currentThread->id)
                ->orderBy('created_at')
                ->orderBy('id');
            $perPage = min(max($perPage, 1), 100);
            $pageName = 'thread_messages_'.$currentThread->id.'_page';
            $requestedPage = request()->query($pageName);
            $page = filter_var($requestedPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($requestedPage === null) {
                $page = max(1, (int) ceil((clone $query)->count() / $perPage));
            } elseif ($page === false) {
                $page = 1;
            }

            return $query->paginate($perPage, ['*'], $pageName, $page);
        });
    }

    public function staffCanParticipate(User $actor, AdmissionAttempt $attempt, ?int $programId, string $threadType): bool
    {
        $freshAttempt = AdmissionAttempt::query()->find($attempt->id);
        if ($freshAttempt === null || AttemptProgramFacts::isEnrolled($freshAttempt)) {
            return false;
        }

        $freshActor = User::query()->find($actor->id);
        if ($freshActor === null) {
            return false;
        }

        $profile = $freshActor->staffProfile()->first();
        $program = $programId === null ? null : Program::query()->find($programId);
        $attempt = $freshAttempt;
        $cycle = $attempt->academicCycle()->first();
        if ($profile === null
            || ! $profile->isActive()
            || $profile->deactivated_at !== null
            || $program === null
            || ! $program->is_active
            || $cycle === null
            || ! $cycle->is_active
            || (int) $profile->institution_id !== (int) $attempt->institution_id
            || (int) $program->institution_id !== (int) $attempt->institution_id
            || (int) $cycle->institution_id !== (int) $attempt->institution_id) {
            return false;
        }

        $actorScope = $freshActor->actorScope();
        if ($actorScope->activeCycleCode !== $cycle->code) {
            return false;
        }
        $isGuidance = $actorScope->office === 'guidance'
            && ($this->authorizer->decideFresh($freshActor, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)->allowed
                || $this->authorizer->decideFresh($freshActor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)->allowed);
        $isProgramHead = $actorScope->office === 'program'
            && in_array($program->code, $actorScope->programCodes, true);
        $scope = ['institution_id' => $attempt->institution_id];
        if ($isProgramHead) {
            $scope['program'] = $program->code;
        }

        if ((! $isGuidance && ! $isProgramHead)
            || ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE, $scope)->allowed) {
            return false;
        }
        if ($attempt->practice_fixture_set_id !== null) {
            $set = PracticeFixtureSet::query()->find($attempt->practice_fixture_set_id);
            if ($set === null
                || (int) $set->institution_id !== (int) $attempt->institution_id
                || (int) $set->academic_cycle_id !== (int) $attempt->academic_cycle_id
                || (string) $attempt->identity()->value('practice_fixture_set_id') !== (string) $set->id
                || ! in_array((int) $freshActor->id, [
                    (int) ($set->actor_bindings['guidance'] ?? 0),
                    (int) ($set->actor_bindings['academic-leader'] ?? 0),
                ], true)) {
                return false;
            }
        }
        if (! in_array($threadType, [
            RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            RecordThread::TYPE_APPLICANT_SUPPORT,
        ], true)) {
            return false;
        }

        if ($threadType === RecordThread::TYPE_APPLICANT_SUPPORT && ! $isProgramHead) {
            return false;
        }

        if (! $isGuidance
            && (! AttemptProgramFacts::isAccepted($attempt)
                || (! AttemptProgramFacts::isResultReleased($attempt)
                    && ! AttemptProgramFacts::hasOpenFollowUpForProgram($attempt, (int) $program->id)))) {
            return false;
        }

        // Internal deliberation is restricted to PH/Guidance. Applicant-support
        // is reserved for the assigned Program Head; applicant identity is handled separately.
        return true;

    }

    public function hasCaseRelationship(AdmissionAttempt $attempt, ?int $programId): bool
    {
        if ($programId === null || AttemptProgramFacts::isEnrolled($attempt)) {
            return false;
        }

        $selection = AttemptProgramFacts::currentSelection($attempt);
        $selected = $selection !== null && (int) $selection->program_id === (int) $programId;
        $followUp = AttemptProgramFacts::hasOpenFollowUpForProgram($attempt, $programId);

        return $selected || $followUp;
    }
}
