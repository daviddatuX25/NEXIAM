<?php

namespace App\Notifications;

use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PracticeReminderSafety
{
    public function safeMailTransport(): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }
        $mailer = config('mail.mailers.'.config('mail.default'), []);
        if (in_array($mailer['transport'] ?? null, ['array', 'log'], true)) {
            return true;
        }

        return ($mailer['transport'] ?? null) === 'smtp'
            && ($mailer['host'] ?? null) === 'mailpit'
            && (int) ($mailer['port'] ?? 0) === 1025
            && empty($mailer['url'])
            && empty($mailer['username'])
            && empty($mailer['password']);
    }

    /** @param Collection<int, ExamAssignment> $assignments
     * @param  list<string>  $channels
     */
    public function assertSession(PracticeFixtureSet $set, ExamSession $session, Collection $assignments, array $channels): void
    {
        if (! app()->environment(['local', 'testing']) || $set->status !== 'active'
            || (string) $session->practice_fixture_set_id !== (string) $set->id
            || (int) $session->academic_cycle_id !== (int) $set->academic_cycle_id
            || (int) $session->academicCycle?->institution_id !== (int) $set->institution_id
            || ! $session->academicCycle?->is_active) {
            throw ValidationException::withMessages(['reminder' => 'The session is not in this active practice set and current institution/cycle.']);
        }
        if (ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot) === null
            || (int) ($session->notification_policy_snapshot['session_version'] ?? 0) < 1) {
            throw ValidationException::withMessages(['reminder' => 'The selected published session has no verified publication notification-policy/version snapshot.']);
        }
        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages(['reminder' => 'The selected session has no eligible assigned practice applicants.']);
        }
        if (in_array(NotificationDelivery::CHANNEL_MAIL, $channels, true) && ! $this->safeMailTransport()) {
            throw ValidationException::withMessages(['reminder' => 'Practice email requires the configured local Mailpit, array or log transport.']);
        }
        foreach ($assignments as $assignment) {
            $attempt = $assignment->admissionAttempt;
            $identity = $attempt?->identity;
            if ($assignment->status !== ExamAssignment::STATUS_ASSIGNED
                || (int) $assignment->exam_session_id !== (int) $session->id
                || $attempt === null || $identity === null || $attempt->archived_at !== null
                || (string) $attempt->practice_fixture_set_id !== (string) $set->id
                || (string) $identity->practice_fixture_set_id !== (string) $set->id
                || (int) $attempt->academic_cycle_id !== (int) $set->academic_cycle_id
                || (int) $attempt->institution_id !== (int) $set->institution_id
                || (int) $identity->institution_id !== (int) $set->institution_id) {
                throw ValidationException::withMessages(['reminder' => 'An assignment has an unclassified, foreign or unavailable applicant root. No recipients were dispatched.']);
            }
            if (in_array(NotificationDelivery::CHANNEL_MAIL, $channels, true)
                && (! is_string($identity->email) || ! filter_var($identity->email, FILTER_VALIDATE_EMAIL)
                    || ! str_ends_with(strtolower($identity->email), '.test'))) {
                throw ValidationException::withMessages(['reminder' => 'Every practice mail recipient must retain an explicitly fictional .test address.']);
            }
        }
    }

    public function deliveryIsSafe(NotificationDelivery $delivery): bool
    {
        $setId = $delivery->payload['practice_fixture_set_id'] ?? null;
        $resource = $delivery->payload['event_resource'] ?? $delivery->payload;
        $session = ExamSession::query()->find($resource['id'] ?? $resource['session_id'] ?? null);
        if ($setId === null) {
            $assignment = ExamAssignment::query()->with('admissionAttempt.identity')->find($resource['assignment_id'] ?? null);

            return $session !== null && $session->practice_fixture_set_id === null
                && $assignment?->admissionAttempt !== null
                && $assignment->admissionAttempt->practice_fixture_set_id === null
                && $assignment->admissionAttempt->identity?->practice_fixture_set_id === null;
        }
        $set = PracticeFixtureSet::query()->find($setId);
        $assignment = ExamAssignment::query()->with('admissionAttempt.identity')
            ->find($resource['assignment_id'] ?? null);
        if ($set === null || $session === null || $assignment === null
            || $delivery->recipient_type !== ApplicantIdentity::class
            || (string) $delivery->recipient_id !== (string) $assignment->admissionAttempt?->applicant_identity_id) {
            return false;
        }
        try {
            $window = $resource['window'] ?? $delivery->payload['practice_reminder_window'] ?? null;
            $policy = ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot)
                ?? ExamNotificationPolicy::fromStorage($session->academicCycle?->exam_notification_policy)
                    ->withSessionOverride($session->notification_policy);
            if (! isset(ExamReminderDispatcher::WINDOW_RANGES[$window ?? ''])
                || ! in_array(ExamReminderDispatcher::WINDOW_RANGES[$window][0], $policy->reminderOffsetsMinutes, true)) {
                return false;
            }
            $this->assertSession($set, $session, collect([$assignment]), [$delivery->channel]);

            return true;
        } catch (ValidationException|\InvalidArgumentException) {
            return false;
        }
    }
}
