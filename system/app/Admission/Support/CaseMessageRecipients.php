<?php

namespace App\Admission\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\RecordThread;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationRecipients;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CaseMessageRecipients
{
    public function __construct(
        private readonly RecordThreadService $threads,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /**
     * @return Collection<int, User|ApplicantIdentity>
     */
    public function recipientsFor(RecordThread $thread, User $sender): Collection
    {
        $thread->loadMissing(['attempt.identity', 'program']);

        $attempt = $thread->attempt;
        if ($attempt === null && $thread->admission_attempt_id !== null) {
            $attempt = AdmissionAttempt::query()->with('identity')->find($thread->admission_attempt_id);
        }

        if ($attempt === null) {
            return collect();
        }

        $attempt->loadMissing('identity');

        $program = $thread->program;
        if ($program === null && $thread->program_id !== null) {
            $program = Program::query()->find($thread->program_id);
        }

        $institutionId = (int) $attempt->institution_id;
        $programCode = $program?->code;

        if ($thread->thread_type === RecordThread::TYPE_INTERNAL_PH_GUIDANCE) {
            $guidanceUsers = User::query()
                ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                    ->where('institution_id', $institutionId)
                    ->where('office', 'guidance')
                    ->where('status', StaffProfile::STATUS_ACTIVE))
                ->with('staffProfile')
                ->get()
                ->filter(fn (User $user): bool => $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
                    'institution_id' => $institutionId,
                    'office' => 'guidance',
                ])
                    || $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, [
                        'institution_id' => $institutionId,
                        'office' => 'guidance',
                    ])
                    || $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
                    || $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
                );

            $programHeadUsers = collect();
            if ($programCode !== null && $programCode !== '') {
                $programHeadUsers = User::query()
                    ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                        ->where('institution_id', $institutionId)
                        ->where('status', StaffProfile::STATUS_ACTIVE)
                        ->where('office', '!=', 'guidance'))
                    ->with('staffProfile')
                    ->get()
                    ->filter(fn (User $user): bool => in_array($programCode, $user->actorScope()->programCodes, true));
            }

            return $guidanceUsers
                ->concat($programHeadUsers)
                ->filter(fn (User $user): bool => (int) $user->id !== (int) $sender->id && $this->threads->canRead($thread, $user))
                ->unique('id')
                ->values();
        }

        if ($thread->thread_type === RecordThread::TYPE_APPLICANT_SUPPORT) {
            $programHeadUsers = collect();
            if ($programCode !== null && $programCode !== '') {
                $programHeadUsers = User::query()
                    ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                        ->where('institution_id', $institutionId)
                        ->where('status', StaffProfile::STATUS_ACTIVE)
                        ->where('office', 'program'))
                    ->with('staffProfile')
                    ->get()
                    ->filter(fn (User $user): bool => (int) $user->id !== (int) $sender->id
                        && in_array($programCode, $user->actorScope()->programCodes, true)
                        && $this->threads->canRead($thread, $user));
            }

            $recipients = $programHeadUsers->values();
            $applicant = NotificationRecipients::applicantFor($attempt);
            $senderIdentityId = User::query()
                ->whereKey($sender->id)
                ->whereHas('applicantAccount')
                ->first()
                ?->applicantAccount()
                ->value('applicant_identity_id');
            $recipientUser = $applicant === null
                ? null
                : User::query()
                    ->whereHas('applicantAccount', fn (Builder $query): Builder => $query->where('applicant_identity_id', $applicant->id))
                    ->first();

            if ($applicant !== null
                && (int) $applicant->id !== (int) $senderIdentityId
                && $recipientUser !== null
                && $this->threads->canRead($thread, $recipientUser)) {
                $recipients->push($applicant);
            }

            return $recipients
                ->unique(fn (User|ApplicantIdentity $recipient): string => $recipient::class.':'.$recipient->getKey())
                ->values();
        }

        return collect();
    }
}
