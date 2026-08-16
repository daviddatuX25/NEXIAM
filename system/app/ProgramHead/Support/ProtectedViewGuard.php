<?php

namespace App\ProgramHead\Support;

use App\Admission\Support\AttemptProgramFacts;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;

/**
 * D-080: Tier B protected-record gate. Full PH record access requires
 * assigned-program scope, released result, and an applicant-acknowledged
 * current selection or an explicit targeted follow-up for this program.
 * Every protected open persists audit + access-history evidence BEFORE
 * serialization (fail-closed) and deduplicates the applicant notice to the
 * first protected open by the same PH/program within 24 hours.
 */
final class ProtectedViewGuard
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    /**
     * @return array{tier: string, access_reason: ?string}
     */
    public function tierFor(AdmissionAttempt $attempt, Program $program): array
    {
        if (! AttemptProgramFacts::isResultReleased($attempt)) {
            return ['tier' => 'A', 'access_reason' => null];
        }

        $selection = AttemptProgramFacts::currentSelection($attempt);
        $acknowledged = $selection !== null
            && $selection->program_id === $program->id
            && $selection->applicant_acknowledged_at !== null;

        $targeted = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('program_id', $program->id)
            ->exists();

        if (! $acknowledged && ! $targeted) {
            return ['tier' => 'A', 'access_reason' => null];
        }

        return [
            'tier' => 'B',
            'access_reason' => $acknowledged ? 'applicant_selection_acknowledged' : 'targeted_follow_up',
        ];
    }

    /**
     * Persist protected-view evidence + notice intent (deduped to the first
     * protected open by the same PH/program within 24 hours). Throws before
     * any serialization when evidence cannot be written (fail-closed).
     */
    public function recordProtectedView(AdmissionAttempt $attempt, Program $program, User $ph, string $accessReason): void
    {
        DB::transaction(function () use ($attempt, $program, $ph, $accessReason): void {
            $this->recordAuditEvent->handle('program.head_record_viewed', [
                'attempt_id' => $attempt->id,
                'program_id' => $program->id,
                'access_reason' => $accessReason,
            ], (string) $ph->id);

            // 24h dedupe window per PH/program: same event id until the day rolls over.
            $dayBucket = intdiv(now()->getTimestamp(), 86_400);
            $eventId = hash('sha256', "program_head.record_viewed:{$ph->id}:{$program->id}:{$dayBucket}");

            $this->dispatcher->dispatch(
                NotificationIntent::v1(
                    eventType: 'program_head.record_viewed.v1',
                    templateKey: 'program_head.record_viewed',
                    payload: [
                        'subject' => 'Your admission record was reviewed',
                        'body' => "Your admission-support record was reviewed by the assigned program head for {$program->name}.",
                        'program_code' => $program->code,
                    ],
                ),
                $eventId,
                collect([NotificationRecipients::applicantFor($attempt)])->filter(),
                [NotificationDelivery::CHANNEL_IN_APP],
            );
        });

        NotificationIntentDispatcher::enqueueAfterCommit(
            NotificationDelivery::query()
                ->where('event_id', hash('sha256', "program_head.record_viewed:{$ph->id}:{$program->id}:".intdiv(now()->getTimestamp(), 86_400)))
                ->get(),
        );
    }
}
