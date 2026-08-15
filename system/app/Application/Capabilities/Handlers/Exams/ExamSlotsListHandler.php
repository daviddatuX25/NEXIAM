<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Admission\Support\ApplicantScope;
use App\Examination\Queries\ApplicantSlotCalendar;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * exams.slots.list@1 — OWN-scope read for the applicant slot skill (Slice 9).
 *
 * Uses the same calendar projection as /applicant/schedule (one core).
 * Returns shaped results (never another user's data); the skill surfaces them.
 */
final class ExamSlotsListHandler
{
    public function __construct(private readonly ApplicantSlotCalendar $calendar)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            return ['mode' => 'denied', 'reason' => 'unauthenticated'];
        }

        $identityId = ApplicantScope::identityIdForUser($actor);
        if ($identityId === null) {
            return ['mode' => 'denied', 'reason' => 'no_applicant_identity'];
        }

        $attempt = AdmissionAttempt::query()
            ->with(['identity:id,first_name,last_name,email'])
            ->where('applicant_identity_id', $identityId)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return ['mode' => 'denied', 'reason' => 'no_attempt'];
        }

        if (! $attempt->isExamEligible()) {
            return ['mode' => 'denied', 'reason' => 'not_exam_eligible'];
        }

        $assignment = ExamAssignment::query()
            ->active()
            ->with('examSession.room:id,code,name')
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null) {
            $session = $assignment->examSession;
            $published = in_array($session?->status, [
                \App\Models\Examination\ExamSession::STATUS_PUBLISHED,
                \App\Models\Examination\ExamSession::STATUS_IN_PROGRESS,
                \App\Models\Examination\ExamSession::STATUS_COMPLETED,
            ], true);

            return [
                'mode' => 'assigned',
                'assignment' => [
                    'id' => $assignment->id,
                    'source' => $assignment->source,
                    'session' => $session ? [
                        'code' => $session->code,
                        'status' => $session->status,
                        'date' => $session->starts_at?->toDateString(),
                        'start_time' => $session->starts_at?->format('H:i'),
                        'end_time' => $session->effective_end_at?->format('H:i'),
                        'room' => $session->room?->name ?: $session->room?->code,
                        'published' => $published,
                    ] : null,
                ],
            ];
        }

        return [
            'mode' => 'open',
            'slots' => $this->calendar->flatSlots($attempt),
        ];
    }
}
