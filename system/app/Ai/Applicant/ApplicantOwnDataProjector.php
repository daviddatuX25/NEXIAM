<?php

namespace App\Ai\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Release\ResultRelease;
use App\Models\User;

/**
 * Lifecycle-authorized applicant own-data projection for Inquiry (OpenSpec 7).
 * Mirrors portal schedule ownership — never embeds cross-applicant rows.
 *
 * @phpstan-type OwnDataArray array{
 *   available: bool,
 *   reason: string|null,
 *   attempt: array{id: int, reference_code: string|null, status: string}|null,
 *   schedule: array{page_state: string, summary: string}|null,
 *   results: array{released: bool, summary: string|null}
 * }
 */
final class ApplicantOwnDataProjector
{
    /**
     * @return OwnDataArray
     */
    public function forUser(User $user): array
    {
        $identityId = ApplicantScope::identityIdForUser($user);
        if ($identityId === null) {
            return $this->empty('no_applicant_identity');
        }

        $attempt = AdmissionAttempt::query()
            ->where('applicant_identity_id', $identityId)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return $this->empty('no_application');
        }

        $schedule = $this->scheduleSummary($attempt);
        $results = $this->resultsSummary($attempt);

        return [
            'available' => true,
            'reason' => null,
            'attempt' => [
                'id' => (int) $attempt->id,
                'reference_code' => $attempt->reference_code,
                'status' => (string) $attempt->status,
            ],
            'schedule' => $schedule,
            'results' => $results,
        ];
    }

    /**
     * @return OwnDataArray
     */
    public function forActorId(string $actorId): array
    {
        $user = User::query()->find($actorId);
        if ($user === null) {
            return $this->empty('actor_not_found');
        }

        return $this->forUser($user);
    }

    /**
     * @param  OwnDataArray  $projection
     */
    public function relevantTo(string $prompt, array $projection): bool
    {
        if (! ($projection['available'] ?? false)) {
            return false;
        }

        $needle = mb_strtolower($prompt);
        foreach (['status', 'application', 'attempt', 'schedule', 'exam', 'seat', 'session', 'score', 'result', 'grade', 'released'] as $token) {
            if (str_contains($needle, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{page_state: string, summary: string}
     */
    private function scheduleSummary(AdmissionAttempt $attempt): array
    {
        if (! $attempt->isExamEligible()) {
            return [
                'page_state' => 'pending_acceptance',
                'summary' => 'Exam scheduling opens after your application is accepted.',
            ];
        }

        $assignment = ExamAssignment::query()
            ->active()
            ->with(['examSession.room:id,code,name'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null) {
            $session = $assignment->examSession;
            $published = in_array($session?->status, [
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
                ExamSession::STATUS_COMPLETED,
            ], true);

            $when = $session?->starts_at?->toDayDateTimeString() ?? 'scheduled time';
            $room = $session?->room?->name ?: ($session?->room?->code ?? 'room TBA');

            return [
                'page_state' => $published ? 'assigned_published' : 'assigned_unpublished',
                'summary' => $published
                    ? "Your exam seat is confirmed for {$when} ({$room})."
                    : 'You are assigned to a session. Staff will publish the schedule when ready.',
            ];
        }

        $open = ExamSession::query()
            ->openForReservation()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
            ->exists();

        if (! $open) {
            return [
                'page_state' => 'waiting_for_reservation_open',
                'summary' => 'No reservation window is open yet. Check back after Guidance opens booking.',
            ];
        }

        return [
            'page_state' => 'reservation_open',
            'summary' => 'Reservation is open. Choose a seat on the ordinary Schedule page.',
        ];
    }

    /**
     * @return array{released: bool, summary: string|null}
     */
    private function resultsSummary(AdmissionAttempt $attempt): array
    {
        $release = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->orderByDesc('released_at')
            ->first();

        if ($release === null) {
            return [
                'released' => false,
                'summary' => null,
            ];
        }

        return [
            'released' => true,
            'summary' => 'Your results have been released. Open the ordinary portal pages for the official view.',
        ];
    }

    /**
     * @return OwnDataArray
     */
    private function empty(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'attempt' => null,
            'schedule' => null,
            'results' => [
                'released' => false,
                'summary' => null,
            ],
        ];
    }
}
