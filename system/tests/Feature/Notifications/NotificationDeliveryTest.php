<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantCapabilityGrants;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationProjection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

function notifUser(string $email = 'notif.user@example.test'): User
{
    $user = User::query()->create([
        'name' => 'Notification Tester',
        'email' => $email,
        'password' => Str::password(32),
    ]);

    ApplicantCapabilityGrants::sync($user);

    return $user;
}

test('dispatcher dedupes one row per event recipient channel', function (): void {
    $user = notifUser('dedupe@example.test');
    $intent = NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'Hi']);

    $dispatcher = app(NotificationIntentDispatcher::class);

    $first = $dispatcher->dispatch($intent, 'event-abc', [$user], $intent->channels);
    $second = $dispatcher->dispatch($intent, 'event-abc', [$user], $intent->channels);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and(NotificationDelivery::query()->where('event_id', 'event-abc')->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('event_id', 'event-abc')->first()->status)
        ->toBe(NotificationDelivery::STATUS_PENDING);
});

test('distinct event occurrences create distinct rows; distinct recipients are isolated', function (): void {
    $a = notifUser('occurrence-a@example.test');
    $b = notifUser('occurrence-b@example.test');
    $intent = NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'Hi']);
    $dispatcher = app(NotificationIntentDispatcher::class);

    $dispatcher->dispatch($intent, 'event-1', [$a, $b], $intent->channels);
    $dispatcher->dispatch($intent, 'event-2', [$a], $intent->channels);

    expect(NotificationDelivery::query()->where('recipient_id', $a->id)->count())->toBe(2)
        ->and(NotificationDelivery::query()->where('recipient_id', $b->id)->count())->toBe(1);
});

test('worker delivers in-app and mail channels independently', function (): void {
    $user = notifUser('state-machine@example.test');
    $intent = NotificationIntent::v1(
        'test.event.v1',
        'test.template',
        ['subject' => 'Hi', 'body' => 'Body', 'action_url' => url('/'), 'action_label' => 'Open'],
        [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
    );

    $deliveries = app(NotificationIntentDispatcher::class)->dispatch($intent, 'event-sm', [$user], $intent->channels);

    expect($deliveries)->toHaveCount(2);

    foreach ($deliveries as $delivery) {
        expect($delivery->status)->toBe(NotificationDelivery::STATUS_PENDING);
        (new ProcessNotificationDelivery($delivery->id))->handle(app(RecordAuditEvent::class));
    }

    $deliveries->each(fn (NotificationDelivery $delivery) => $delivery->refresh());

    expect($deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_IN_APP)->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_MAIL)->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_MAIL)->provider_reference)->not->toBeNull();
});

test('mail failure schedules retry with backoff then reaches failed_terminal', function (): void {
    Queue::fake();

    // Recipient id points at a user that never existed → mail adapter throws.
    $delivery = NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => 'event-fail',
        'event_type' => 'test.failure.v1',
        'event_version' => 1,
        'recipient_type' => User::class,
        'recipient_id' => 999999,
        'channel' => NotificationDelivery::CHANNEL_MAIL,
        'template_key' => 'test.failure',
        'template_version' => 1,
        'payload' => ['subject' => 'Hi', 'body' => 'Body', 'action_url' => url('/')],
        'status' => NotificationDelivery::STATUS_PENDING,
        'attempts' => 0,
    ]);

    $job = new ProcessNotificationDelivery($delivery->id);
    $job->handle(app(RecordAuditEvent::class));

    $delivery->refresh();
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_PENDING)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->next_attempt_at)->not->toBeNull()
        ->and($delivery->last_error)->not->toBeNull()
        ->and($delivery->last_error)->not->toContain('@');

    // Backoff due — a later attempt still fails → terminal after max attempts.
    $delivery->update(['attempts' => 4, 'next_attempt_at' => now()->subSecond()]);
    $job->handle(app(RecordAuditEvent::class));

    $delivery->refresh();
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_FAILED_TERMINAL)
        ->and($delivery->attempts)->toBe(5);
});

test('truthfulness — freshly dispatched rows are queued, never delivered', function (): void {
    $user = notifUser('truthful@example.test');
    $intent = NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'Hi']);

    $deliveries = app(NotificationIntentDispatcher::class)->dispatch($intent, 'event-truth', [$user], $intent->channels);

    // Worker intentionally not run: projection must NOT claim delivered.
    $projection = app(NotificationProjection::class);
    $view = $projection->forUser($user);

    expect($view['recent'][0]['status'])->toBe(NotificationDelivery::STATUS_PENDING)
        ->and($view['recent'][0]['delivered_at'])->toBeNull()
        ->and($view['recent'][0]['failed'])->toBeFalse();
});

test('own-data gate — user cannot see or mark another user rows', function (): void {
    $userA = notifUser('owner-a@example.test');
    $userB = notifUser('owner-b@example.test');

    $deliveryA = app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'For A']),
        'event-owner',
        [$userA],
        ['in_app'],
    )->first();

    $this->actingAs($userB)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->where('notifications.unread_count', 0)
            ->where('notifications.recent', fn ($rows) => collect($rows)->pluck('id')->doesntContain($deliveryA->id)));

    $this->actingAs($userB)
        ->post(route('notifications.read', $deliveryA))
        ->assertForbidden();

    expect($deliveryA->fresh()->read_at)->toBeNull();

    $this->actingAs($userA)
        ->post(route('notifications.read', $deliveryA))
        ->assertRedirect();

    expect($deliveryA->fresh()->read_at)->not->toBeNull();
});

test('mark-read is idempotent and mark-all is scoped to own rows', function (): void {
    $userA = notifUser('markall-a@example.test');
    $userB = notifUser('markall-b@example.test');

    $dispatcher = app(NotificationIntentDispatcher::class);
    $dispatcher->dispatch(
        NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'A1']),
        'event-ma-a1',
        [$userA],
        ['in_app'],
    );
    $dispatcher->dispatch(
        NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'A2']),
        'event-ma-a2',
        [$userA],
        ['in_app'],
    );
    $bDelivery = $dispatcher->dispatch(
        NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'B1']),
        'event-ma-b1',
        [$userB],
        ['in_app'],
    )->first();

    $this->actingAs($userA)->post(route('notifications.read-all'))->assertRedirect();

    expect(NotificationDelivery::forUser($userA)->whereNull('read_at')->count())->toBe(0)
        ->and($bDelivery->fresh()->read_at)->toBeNull();

    $first = NotificationDelivery::forUser($userA)->first();
    $this->actingAs($userA)->post(route('notifications.read', $first))->assertRedirect();
    $readAt = $first->fresh()->read_at;
    expect($readAt)->not->toBeNull();

    // Idempotent repeat keeps the original timestamp.
    $this->actingAs($userA)->post(route('notifications.read', $first))->assertRedirect();
    expect($first->fresh()->read_at->toISOString())->toBe($readAt->toISOString());
});

test('unread-count endpoint returns own count as JSON', function (): void {
    $user = notifUser('unread@example.test');
    app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'Hi']),
        'event-unread',
        [$user],
        ['in_app'],
    );

    $this->actingAs($user)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJson(['count' => 1]);
});

test('notifications routes require the notify.read.own capability', function (): void {
    $outsider = User::query()->create([
        'name' => 'No Caps',
        'email' => 'nocaps@example.test',
        'password' => Str::password(32),
    ]);

    $this->actingAs($outsider)
        ->get(route('notifications.index'))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->getJson(route('notifications.unread-count'))
        ->assertForbidden();

    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($staff)->get(route('notifications.index'))->assertOk();
});

test('acceptance emits an in-app delivery to the applicant portal user', function (): void {
    $result = submitSyntheticApplicant('accept-notif@example.test', 'Accept');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($result['access_token'], '2001-01-01');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    expect(NotificationDelivery::forUser($applicant)
        ->where('event_type', 'admission.accepted.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->exists())->toBeTrue();
});

test('proctor duty assignment emits an in-app delivery to the proctor', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($counselor)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NOTIF-1',
        'date' => now()->addDays(4)->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
    ])->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-NOTIF-1')->firstOrFail();

    $this->actingAs($counselor)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => $clerk->id,
            'idempotency_key' => 'notif-proctor-1',
        ])
        ->assertRedirect();

    expect(NotificationDelivery::forUser($clerk)
        ->where('event_type', 'exam.proctor_duty_assigned.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->exists())->toBeTrue();
});
