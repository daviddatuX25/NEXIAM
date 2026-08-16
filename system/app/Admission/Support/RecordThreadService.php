<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\User;
use RuntimeException;

/**
 * Server-owned thread lifecycle (D-092): participants are derived from the
 * case relationship, never client-chosen. Internal threads are PH<->Guidance
 * only; applicant-support threads require a current selection or targeted
 * follow-up for the program and end pre-`enrolled`.
 */
final class RecordThreadService
{
    public function __construct() {}

    /**
     * @return list<RecordThread>
     */
    public function forAttempt(AdmissionAttempt $attempt, string $threadType): array
    {
        return RecordThread::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('thread_type', $threadType)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function openInternalThread(
        AdmissionAttempt $attempt,
        ?int $programId,
        string $subject,
        User $creator,
    ): RecordThread {
        return RecordThread::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $programId,
            'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            'subject' => $subject,
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $creator->id,
            'created_at' => now(),
        ]);
    }

    public function openApplicantThread(
        AdmissionAttempt $attempt,
        ?int $programId,
        string $subject,
        User $creator,
    ): RecordThread {
        $this->assertApplicantThreadAllowed($attempt, $programId);

        return RecordThread::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $programId,
            'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
            'subject' => $subject,
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $creator->id,
            'created_at' => now(),
        ]);
    }

    public function assertApplicantThreadAllowed(AdmissionAttempt $attempt, ?int $programId): void
    {
        if (AttemptProgramFacts::isEnrolled($attempt)) {
            throw new RuntimeException('Applicant support messaging ends once the applicant is enrolled.');
        }

        $selection = AttemptProgramFacts::currentSelection($attempt);
        $selected = $selection !== null && $selection->program_id === $programId;

        $followUp = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('program_id', $programId)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->exists();

        if (! $selected && ! $followUp) {
            throw new RuntimeException('Applicant messaging requires a current selection or targeted follow-up for that program.');
        }
    }

    /**
     * True when the actor still passes current scope for the thread: staff via
     * role/office check, applicant via linked identity on the thread's attempt.
     */
    public function canParticipate(RecordThread $thread, User $actor): bool
    {
        if ($thread->status !== RecordThread::STATUS_OPEN) {
            return false;
        }

        $actorProfile = $actor->staffProfile;

        if ($actorProfile !== null) {
            // Staff participant: PH (assigned program) or Guidance.
            if ($actor->hasRole('program_head')) {
                if ($thread->program_id === null) {
                    return false;
                }

                return in_array(
                    $thread->program?->code,
                    $actorProfile->program_codes ?? [],
                    true,
                );
            }

            return $actor->hasAnyRole(['guidance_counselor', 'guidance_clerk']);
        }

        // Applicant participant: must own the attempt via applicant account.
        $account = $actor->applicantAccount;

        return $account !== null
            && $account->applicant_identity_id === $thread->attempt?->applicant_identity_id
            && $thread->thread_type === RecordThread::TYPE_APPLICANT_SUPPORT;
    }

    public function markRead(RecordThread $thread, User $actor): void
    {
        RecordThreadRead::query()->updateOrCreate(
            ['record_thread_id' => $thread->id, 'user_id' => $actor->id],
            ['last_read_at' => now()],
        );
    }

    /**
     * @return list<RecordThreadMessage>
     */
    public function messagesFor(RecordThread $thread): array
    {
        return $thread->messages()->get()->all();
    }
}
