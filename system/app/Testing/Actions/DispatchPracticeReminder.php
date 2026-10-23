<?php

namespace App\Testing\Actions;

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Notifications\ExamReminderDispatcher;
use App\Notifications\NotificationDelivery;
use App\Notifications\PracticeReminderSafety;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DispatchPracticeReminder
{
    public function __construct(
        private readonly ExamReminderDispatcher $reminders,
        private readonly PracticeReminderSafety $safety,
    ) {}

    /** @param array<string, mixed> $input
     * @return list<array{key:string,label:string,status:string,reason:string}>
     */
    public function prerequisites(PracticeFixtureSet $set, array $input): array
    {
        $set = PracticeFixtureSet::query()->findOrFail($set->id);
        $session = $this->session($set, $input);
        $reason = null;
        if ($session === null) {
            $reason = 'Choose an exact session belonging to this practice set.';
        } else {
            try {
                $window = (string) ($input['window'] ?? '');
                $reason = $this->reminders->blockedReason($session, $window);
                $channels = [NotificationDelivery::CHANNEL_IN_APP];
                if ($this->reminders->policy($session)->reminderEmailEnabled) {
                    $channels[] = NotificationDelivery::CHANNEL_MAIL;
                }
                $this->safety->assertSession($set, $session, $session->assignments()
                    ->where('status', ExamAssignment::STATUS_ASSIGNED)
                    ->with('admissionAttempt.identity')->get(), $channels);
            } catch (ValidationException $exception) {
                $reason = collect($exception->errors())->flatten()->first();
            }
        }

        return [[
            'key' => 'practice-reminder', 'label' => 'Selected-session reminder',
            'status' => $reason === null ? 'available' : 'missing',
            'reason' => $reason ?? 'The actual policy, time window, owned recipients and local channels permit this reminder.',
        ]];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function handle(PracticeFixtureSet $set, array $input): array
    {
        return DB::transaction(function () use ($set, $input): array {
            $set = PracticeFixtureSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();
            $session = $this->session($set, $input);
            if ($session === null) {
                throw ValidationException::withMessages(['session_id' => 'Choose an exact session belonging to this practice set.']);
            }
            $window = (string) ($input['window'] ?? '');
            $created = $this->reminders->dispatch($session, $window, $set);
            $eventIds = $session->assignments()->where('status', ExamAssignment::STATUS_ASSIGNED)->get()
                ->map(fn (ExamAssignment $assignment): string => ExamReminderDispatcher::eventId($session, $assignment, $window));
            $deliveries = NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')
                ->whereIn('event_id', $eventIds)->orderBy('id')->get();

            return [
                'session_id' => (string) $session->id, 'window' => $window,
                'created_count' => $created->count(),
                'deliveries' => $deliveries->map(fn (NotificationDelivery $delivery): array => [
                    'id' => $delivery->id, 'window' => $window, 'channel' => $delivery->channel,
                    'status' => $delivery->status,
                    'operational_status' => NotificationDelivery::operationalStatus($delivery->channel, $delivery->status),
                    'reason' => $delivery->last_error ?? 'Status is the persisted delivery state; enqueueing is not delivery proof.',
                ])->all(),
            ];
        });
    }

    /** @param array<string, mixed> $input */
    private function session(PracticeFixtureSet $set, array $input): ?ExamSession
    {
        return ExamSession::query()->with('academicCycle')->whereKey($input['session_id'] ?? null)
            ->where('practice_fixture_set_id', $set->id)
            ->where('academic_cycle_id', $set->academic_cycle_id)->first();
    }
}
