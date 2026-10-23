<?php

namespace App\Notifications;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;

final class PracticeNotificationSafety
{
    public function __construct(private readonly PracticeReminderSafety $reminders) {}

    public function setId(NotificationIntent $intent, User|ApplicantIdentity|ApplicationOpeningSubscription $recipient): ?string
    {
        if ($recipient instanceof ApplicationOpeningSubscription) {
            return null;
        }
        $id = $intent->payload['practice_fixture_set_id'] ?? app(PracticeWorkspace::class)->currentId();
        if ($id === null && $recipient instanceof ApplicantIdentity) {
            $id = ApplicantIdentity::query()->whereKey($recipient->id)->value('practice_fixture_set_id');
        }
        if ($id === null && isset($intent->payload['session_id'])) {
            $id = ExamSession::query()->whereKey($intent->payload['session_id'])->value('practice_fixture_set_id');
        }
        if ($id === null && isset($intent->payload['attempt_id'])) {
            $id = AdmissionAttempt::query()->whereKey($intent->payload['attempt_id'])->value('practice_fixture_set_id');
        }
        if ($id === null && isset($intent->payload['admission_attempt_id'])) {
            $id = AdmissionAttempt::query()->whereKey($intent->payload['admission_attempt_id'])->value('practice_fixture_set_id');
        }

        return $id === null ? null : (string) $id;
    }

    /** @param list<string> $channels
     * @return list<string>
     */
    public function channels(string $setId, User|ApplicantIdentity|ApplicationOpeningSubscription $recipient, array $channels): array
    {
        if ($recipient instanceof ApplicationOpeningSubscription) {
            return [];
        }
        $set = PracticeFixtureSet::query()->find($setId);
        if (! app()->environment(['local', 'testing']) || $set === null || $set->status !== 'active'
            || ! $set->academicCycle?->is_active
            || (int) $set->academicCycle?->institution_id !== (int) $set->institution_id) {
            return [];
        }
        $email = null;
        if ($recipient instanceof ApplicantIdentity) {
            $fresh = ApplicantIdentity::query()->find($recipient->id);
            if ($fresh === null || (string) $fresh->practice_fixture_set_id !== $setId
                || (int) $fresh->institution_id !== (int) $set->institution_id) {
                return [];
            }
            $email = $fresh->email;
        } else {
            $fresh = User::query()->with('staffProfile')->find($recipient->id);
            $profile = $fresh?->staffProfile;
            if ($fresh === null || $profile === null || ! $profile->isActive() || $profile->deactivated_at !== null
                || (int) $profile->institution_id !== (int) $set->institution_id
                || ! in_array((int) $fresh->id, array_map('intval', array_values($set->actor_bindings ?? [])), true)
                || ! AcademicCycle::query()->whereKey($set->academic_cycle_id)
                    ->where('institution_id', $set->institution_id)->where('is_active', true)
                    ->where('code', $profile->active_cycle_code)->exists()) {
                return [];
            }
            $email = $fresh->email;
        }
        if (! $this->reminders->safeMailTransport() || ! is_string($email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! str_ends_with(strtolower($email), '.test')) {
            $channels = array_values(array_diff($channels, [NotificationDelivery::CHANNEL_MAIL]));
        }

        return array_values(array_intersect($channels, [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL]));
    }

    /** @param array<string,mixed> $payload
     * @return array<string,int>
     */
    public function roots(array $payload): array
    {
        $roots = [];
        foreach (['attempt_id', 'admission_attempt_id', 'session_id', 'identity_id'] as $key) {
            if (isset($payload[$key])) {
                $roots[$key] = (int) $payload[$key];
            }
        }

        return $roots;
    }

    /** @param array<string,int> $roots */
    public function rootsAreOwned(string $setId, array $roots): bool
    {
        $set = PracticeFixtureSet::query()->find($setId);
        if ($set === null) {
            return false;
        }
        foreach ($roots as $key => $id) {
            $query = match ($key) {
                'attempt_id', 'admission_attempt_id' => AdmissionAttempt::query()
                    ->where('institution_id', $set->institution_id)->where('academic_cycle_id', $set->academic_cycle_id)
                    ->whereHas('identity', fn ($query) => $query->where('practice_fixture_set_id', $setId)->where('institution_id', $set->institution_id)),
                'session_id' => ExamSession::query()->where('academic_cycle_id', $set->academic_cycle_id),
                'identity_id' => ApplicantIdentity::query()->where('institution_id', $set->institution_id),
                default => null,
            };
            if ($query === null || ! $query->whereKey($id)->where('practice_fixture_set_id', $setId)->exists()) {
                return false;
            }
        }

        return true;
    }

    public function deliveryIsSafe(NotificationDelivery $delivery): bool
    {
        if ($delivery->recipient_type === ApplicationOpeningSubscription::class) {
            return $delivery->channel === NotificationDelivery::CHANNEL_MAIL
                && in_array($delivery->event_type, ['application.opening_alert.verify.v1', 'application.opening_alert.opened.v1'], true)
                && ApplicationOpeningSubscription::query()->whereKey($delivery->recipient_id)
                    ->where('institution_id', $delivery->payload['institution_id'] ?? 0)->exists();
        }
        $setId = $delivery->payload['practice_fixture_set_id'] ?? null;
        if ($setId === null) {
            return $delivery->recipient_type !== ApplicantIdentity::class
                || ApplicantIdentity::query()->whereKey($delivery->recipient_id)->whereNull('practice_fixture_set_id')->exists();
        }
        if (! $this->rootsAreOwned((string) $setId, $delivery->payload['practice_roots'] ?? [])) {
            return false;
        }
        $recipient = match ($delivery->recipient_type) {
            ApplicantIdentity::class => ApplicantIdentity::query()->find($delivery->recipient_id),
            User::class => User::query()->find($delivery->recipient_id),
            default => null,
        };
        if ($delivery->channel === NotificationDelivery::CHANNEL_MAIL
            && ($recipient === null
                || ! is_string($delivery->payload['practice_recipient_email'] ?? null)
                || $delivery->payload['practice_recipient_email'] !== $recipient->email
                || ! str_ends_with(strtolower($delivery->payload['practice_recipient_email']), '.test'))) {
            return false;
        }

        return $recipient !== null && in_array($delivery->channel, $this->channels((string) $setId, $recipient, [$delivery->channel]), true);
    }
}
