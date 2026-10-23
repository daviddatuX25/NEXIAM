<?php

namespace App\Notifications;

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Templates\NoticeRecipientContextResolver;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExamReminderDispatcher
{
    public const WINDOW_RANGES = [
        '72h' => [4320, 30],
        '24h' => [1440, 30],
        '2h' => [120, 30],
        '1h' => [60, 15],
    ];

    public function __construct(
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly NoticeRecipientContextResolver $contextResolver,
        private readonly PracticeReminderSafety $practiceSafety,
    ) {}

    /** @return array{Carbon, Carbon} */
    public static function range(string $window): array
    {
        if (! isset(self::WINDOW_RANGES[$window])) {
            throw ValidationException::withMessages(['window' => 'Choose 72h, 24h, 2h or 1h.']);
        }
        [$offset, $tolerance] = self::WINDOW_RANGES[$window];
        $now = now();

        return [$now->copy()->addMinutes($offset - $tolerance), $now->copy()->addMinutes($offset + $tolerance)];
    }

    public function policy(ExamSession $session): ExamNotificationPolicy
    {
        try {
            return ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot)
                ?? ExamNotificationPolicy::fromStorage($session->academicCycle?->exam_notification_policy)
                    ->withSessionOverride($session->notification_policy);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['policy' => $exception->getMessage()]);
        }
    }

    public function blockedReason(ExamSession $session, string $window): ?string
    {
        [$start, $end] = self::range($window);
        if ($session->status !== ExamSession::STATUS_PUBLISHED) {
            return 'The selected session is not published.';
        }
        if ($session->starts_at === null || ! $session->starts_at->betweenIncluded($start, $end)) {
            return 'The selected session is outside the actual '.$window.' reminder window.';
        }
        if (! in_array(self::WINDOW_RANGES[$window][0], $this->policy($session)->reminderOffsetsMinutes, true)) {
            return 'The session notification policy disables the '.$window.' reminder.';
        }

        return null;
    }

    /** @return Collection<int, NotificationDelivery> */
    public function dispatch(ExamSession $selected, string $window, ?PracticeFixtureSet $set = null): Collection
    {
        return DB::transaction(function () use ($selected, $window, $set): Collection {
            $session = ExamSession::query()->whereKey($selected->id)->lockForUpdate()->firstOrFail();
            $session->load(['room', 'academicCycle.institution']);
            $reason = $this->blockedReason($session, $window);
            if ($set !== null) {
                $set = PracticeFixtureSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();
                if ($reason !== null) {
                    throw ValidationException::withMessages(['reminder' => $reason]);
                }
            } elseif ($reason !== null || $session->practice_fixture_set_id !== null) {
                return collect();
            }

            $assignments = $session->assignments()->where('status', ExamAssignment::STATUS_ASSIGNED)
                ->with(['admissionAttempt.identity', 'activeSeatAllocation.sessionSeat.materialSnapshot'])
                ->lockForUpdate()->get();
            $policy = $this->policy($session);
            $channels = [NotificationDelivery::CHANNEL_IN_APP];
            if ($policy->reminderEmailEnabled) {
                $channels[] = NotificationDelivery::CHANNEL_MAIL;
            }
            if ($set !== null) {
                $this->practiceSafety->assertSession($set, $session, $assignments, $channels);
            }

            $created = collect();
            foreach ($assignments as $assignment) {
                $attempt = $assignment->admissionAttempt;
                $identity = $attempt?->identity;
                if ($identity === null || $attempt->archived_at !== null
                    || (int) $attempt->academic_cycle_id !== (int) $session->academic_cycle_id
                    || (int) $attempt->institution_id !== (int) $session->academicCycle?->institution_id
                    || (int) $identity->institution_id !== (int) $attempt->institution_id
                    || ($set === null && ($attempt->practice_fixture_set_id !== null || $identity->practice_fixture_set_id !== null))) {
                    continue;
                }
                $context = $this->contextResolver->reminder($assignment, $session);
                $version = $session->notification_policy_snapshot['session_version'] ?? $session->version;
                $room = $session->room?->name ?? 'assigned examination room';
                $start = $session->starts_at->toDayDateTimeString();
                $intent = NotificationIntent::v1(
                    eventType: 'exam.reminder.v1',
                    templateKey: 'exam.reminder',
                    payload: [
                        'institution_id' => $context['institution_id'],
                        'domain' => 'examination', 'canonical_slug' => 'reminder',
                        'event_context' => $context['values'],
                        'session_id' => $session->id, 'assignment_id' => $assignment->id,
                        'identity_id' => $identity->id, 'schedule_generation' => $version,
                        'session_version' => $version, 'assignment_version' => $assignment->version,
                        'practice_fixture_set_id' => $set?->id,
                        'code' => $session->code, 'window' => $window,
                        'starts_at' => $session->starts_at->toIso8601String(),
                        'subject' => match ($window) {
                            '72h' => "Exam Reminder: {$session->code} is in 3 days",
                            '24h' => "Exam Reminder: {$session->code} is in 24 hours",
                            '2h' => "Exam Reminder: {$session->code} is in 2 hours",
                            default => "Urgent: Exam Session {$session->code} starts soon",
                        },
                        'body' => match ($window) {
                            '72h' => "Your exam session {$session->code} begins on {$start} in {$room}. Please prepare your requirements.",
                            '24h' => "Your exam session {$session->code} begins on {$start} in {$room}. Please arrive 15 minutes early.",
                            '2h' => "Your exam session {$session->code} begins in 2 hours at {$start} in {$room}. Please make your way to the testing venue.",
                            default => "Your exam session {$session->code} begins shortly at {$start} in {$room}. Please proceed to your room.",
                        },
                        'action_label' => 'View schedule',
                        'action_url' => rtrim((string) config('app.url'), '/').route('applicant.schedule', absolute: false),
                    ],
                    channels: $channels,
                );
                $eventId = self::eventId($session, $assignment, $window);
                $created = $created->concat($this->dispatcher->dispatch($intent, $eventId, [$identity], $channels));
            }
            NotificationIntentDispatcher::enqueueAfterCommit($created);

            return $created;
        });
    }

    public static function eventId(ExamSession $session, ExamAssignment $assignment, string $window): string
    {
        $version = $session->notification_policy_snapshot['session_version'] ?? $session->version;

        return hash('sha256', "exam.reminder:{$session->id}:{$assignment->id}:{$window}:v{$version}");
    }
}
