<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNotePublication;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Admission\ProgramAdvisingNoteUsage;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use App\ProgramHead\Support\ProtectedViewGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecordProgramAdvisingNoteUse
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly CapabilityAuthorizer $capabilities,
        private readonly ProtectedViewGuard $protectedViews,
        private readonly ProgramHeadApplicantQueries $applicantQueries,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        AdmissionAttempt $attempt,
        ProgramAdvisingNoteRevision $revision,
        string $idempotencyKey,
    ): ProgramAdvisingNoteUsage {
        return DB::transaction(function () use ($actor, $attempt, $revision, $idempotencyKey): ProgramAdvisingNoteUsage {
            $freshAttempt = AdmissionAttempt::query()->findOrFail($attempt->id);
            $exactRevision = ProgramAdvisingNoteRevision::query()->findOrFail($revision->id);
            $note = ProgramAdvisingNote::query()->whereKey($exactRevision->program_advising_note_id)->lockForUpdate()->firstOrFail();
            $program = Program::query()->findOrFail($note->program_id);
            $cycle = AcademicCycle::query()->findOrFail($note->academic_cycle_id);
            $freshCycle = AcademicCycle::query()->findOrFail($freshAttempt->academic_cycle_id);
            $freshActor = User::query()->find($actor->id);
            $profile = $freshActor?->staffProfile()->first();
            $actorScope = $freshActor?->actorScope();

            if ($freshActor === null
                || $profile === null
                || ! $profile->isActive()
                || (int) $profile->institution_id !== (int) $freshAttempt->institution_id
                || ! $freshCycle->is_active
                || $actorScope?->institutionId !== (int) $freshAttempt->institution_id
                || $actorScope->activeCycleCode !== $freshCycle->code
                || (int) $note->institution_id !== (int) $freshAttempt->institution_id
                || (int) $note->program_id !== (int) $program->id
                || (int) $note->academic_cycle_id !== (int) $freshAttempt->academic_cycle_id
                || (int) $freshCycle->institution_id !== (int) $freshAttempt->institution_id
                || (int) $program->institution_id !== (int) $freshAttempt->institution_id
                || (int) $cycle->institution_id !== (int) $freshAttempt->institution_id) {
                throw new AuthorizationException('The actor or advising note is outside the consultation scope.');
            }
            $this->authorizer->authorize($freshActor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW, $program, $cycle);

            $isGuidance = $profile->office === 'guidance';
            if ($isGuidance) {
                $guidanceScope = [
                    'office' => 'guidance',
                    'institution_id' => $freshAttempt->institution_id,
                    'cycle' => $freshCycle->code,
                ];
                $canConsult = $this->capabilities->allowsAny($freshActor, [
                    CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
                    CapabilityCatalog::GUIDANCE_SELECTION_MANAGE,
                ], $guidanceScope);
                if (! $canConsult || ! AttemptProgramFacts::isAccepted($freshAttempt)
                    || ! AttemptProgramFacts::isResultReady($freshAttempt)
                    || ! $this->guidanceProgramIsEligible($freshAttempt, $program)) {
                    throw new AuthorizationException('The actor is not authorized for an eligible consultation in this program and cycle.');
                }
            } else {
                if ($profile->office !== 'program'
                    || ! in_array($program->code, $actorScope->programCodes, true)
                    || ! $this->capabilities->allows($freshActor, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
                        'institution_id' => $freshAttempt->institution_id,
                        'program' => $program->code,
                    ])
                    || $this->protectedViews->tierFor($freshAttempt, $program)['tier'] !== 'B') {
                    throw new AuthorizationException('The actor is not authorized for this protected applicant case.');
                }
            }

            if ($note->audience !== 'program') {
                throw new AuthorizationException('Private advising notes are not available for consultation.');
            }

            if (trim($idempotencyKey) === '') {
                throw new RuntimeException('A stable idempotency key is required.');
            }
            $operationKey = sprintf('%s:%s:%s:%s', $freshActor->id, 'advising.note.use', 'attempt:'.$freshAttempt->id, $idempotencyKey);
            $existingOperation = Operation::query()
                ->where('idempotency_key', $operationKey)
                ->lockForUpdate()
                ->first();
            if ($existingOperation !== null) {
                $existingPayload = $existingOperation->payload;
                if (($existingPayload['attempt_id'] ?? null) !== $freshAttempt->id
                    || ($existingPayload['note_id'] ?? null) !== $note->id
                    || ($existingPayload['revision_id'] ?? null) !== $exactRevision->id
                    || ($existingPayload['program_id'] ?? null) !== $program->id
                    || ($existingPayload['academic_cycle_id'] ?? null) !== $cycle->id) {
                    throw new RuntimeException('Idempotency key was already used with a different request payload.');
                }
                $publicationId = $existingPayload['publication_id'] ?? null;
            } else {
                if ((int) $note->published_revision_id !== (int) $exactRevision->id) {
                    throw new RuntimeException('Only the current published note revision can be used.');
                }
                $publicationId = $note->published_publication_id;
            }
            $publication = ProgramAdvisingNotePublication::query()
                ->where('program_advising_note_id', $note->id)
                ->where('revision_id', $exactRevision->id)
                ->whereKey($publicationId)
                ->where('operation', 'publish')
                ->firstOrFail();
            $payload = [
                'attempt_id' => $freshAttempt->id,
                'note_id' => $note->id,
                'revision_id' => $exactRevision->id,
                'publication_id' => $publication->id,
                'program_id' => $program->id,
                'academic_cycle_id' => $cycle->id,
            ];
            $operation = $this->idempotency->claim($freshActor, 'advising.note.use', 'attempt:'.$freshAttempt->id, $payload, $idempotencyKey);
            $operation->refresh();
            $usageId = $operation->payload['receipt_usage_id'] ?? null;
            if ($usageId !== null) {
                return ProgramAdvisingNoteUsage::query()
                    ->where('admission_attempt_id', $freshAttempt->id)
                    ->whereKey($usageId)
                    ->firstOrFail();
            }

            if ($isGuidance) {
                $this->audit->handle('guidance.advising_note_consultation_opened', [
                    'attempt_id' => $freshAttempt->id,
                    'program_id' => $program->id,
                    'academic_cycle_id' => $cycle->id,
                    'revision_id' => $exactRevision->id,
                ], (string) $freshActor->id);
            } else {
                $protectedRecord = $this->applicantQueries->protectedRecord($freshAttempt, $program, $freshActor);
                if (($protectedRecord['tier'] ?? null) !== 'B') {
                    throw new AuthorizationException('The actor is no longer authorized for this protected applicant case.');
                }
            }

            $usage = ProgramAdvisingNoteUsage::query()->create([
                'admission_attempt_id' => $freshAttempt->id,
                'program_advising_note_id' => $note->id,
                'revision_id' => $exactRevision->id,
                'publication_id' => $publication->id,
                'actor_user_id' => $freshActor->id,
                'idempotency_operation_key' => $operation->idempotency_key,
                'used_at' => now(),
            ]);
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_usage_id' => $usage->id]])->save();
            $this->audit->handle('advising_note.used', [
                'attempt_id' => $freshAttempt->id,
                'note_id' => $note->id,
                'revision_id' => $exactRevision->id,
                'publication_id' => $publication->id,
                'usage_id' => $usage->id,
            ], (string) $freshActor->id);

            return $usage;
        });
    }

    private function guidanceProgramIsEligible(AdmissionAttempt $attempt, Program $program): bool
    {
        if (AttemptProgramFacts::currentSelection($attempt)?->program_id === $program->id) {
            return true;
        }
        if (in_array($program->code, [
            $attempt->preferred_program_code,
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
        ], true)) {
            return true;
        }
        if (ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->where('program_id', $program->id)->exists()) {
            return true;
        }

        $pool = ProgramPool::currentFor((int) $attempt->id);

        return $pool !== null && $pool->options()->where('program_id', $program->id)->exists();
    }
}
