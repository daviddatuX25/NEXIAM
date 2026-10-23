<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantCapabilityGrants;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationProjection;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Release\Actions\ReleaseResult;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
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
function processNotificationDelivery(NotificationDelivery $delivery): void
{
    (new ProcessNotificationDelivery($delivery->id))->handle(
        app(RecordAuditEvent::class),
        app(NotificationBusinessValidity::class),
    );
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
        processNotificationDelivery($delivery);
    }

    $deliveries->each(fn (NotificationDelivery $delivery) => $delivery->refresh());

    expect($deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_IN_APP)->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_MAIL)->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_MAIL)->provider_reference)->not->toBeNull();
});

test('in-app delivery, projection, and read timestamps remain distinct', function (): void {
    $user = notifUser('timestamp-states@example.test');
    $delivery = app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1('test.timestamp.v1', 'test.timestamp', ['subject' => 'Exam notice']),
        'event-timestamps',
        [$user],
        [NotificationDelivery::CHANNEL_IN_APP],
    )->firstOrFail();

    processNotificationDelivery($delivery);
    $delivery->refresh();

    expect($delivery->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->seen_at)->toBeNull();

    $deliveredAt = $delivery->delivered_at;
    $projection = app(NotificationProjection::class)->forUser($user);
    $delivery->refresh();
    $seenAt = $delivery->seen_at;

    expect($projection['recent'][0]['delivered_at'])->toBe($deliveredAt->toIso8601String())
        ->and($seenAt)->not->toBeNull();

    $this->actingAs($user)->post(route('notifications.read', $delivery))->assertRedirect();
    $delivery->refresh();

    expect($delivery->read_at)->not->toBeNull()
        ->and($delivery->seen_at->toIso8601String())->toBe($seenAt->toIso8601String())
        ->and($delivery->delivered_at->toIso8601String())->toBe($deliveredAt->toIso8601String());
});

test('inbox copy stays readable when a notification payload has missing blank or malformed text', function (): void {
    $user = notifUser('malformed-copy@example.test');
    $delivery = app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1('test.malformed_copy.v1', 'internal.test_malformed_copy', [
            'subject' => ['unexpected' => 'array'],
            'body' => '   ',
        ]),
        'event-malformed-copy',
        [$user],
        [NotificationDelivery::CHANNEL_IN_APP],
    )->firstOrFail();

    $bell = app(NotificationProjection::class)->forUser($user);
    $inbox = app(NotificationProjection::class)->list($user);
    $expectedCopy = [
        'subject' => 'Notification update',
        'body' => 'Open your notifications to review this update.',
    ];

    expect($bell['recent'][0])->toMatchArray($expectedCopy)
        ->and($inbox[0])->toMatchArray($expectedCopy)
        ->and($bell['recent'][0]['subject'])->not->toContain('internal.test_malformed_copy')
        ->and($delivery->fresh()->seen_at)->not->toBeNull();
});

test('in-app inbox shows one unread item when an event also has a mail delivery', function (): void {
    $user = notifUser('inbox-channels@example.test');
    $channels = [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL];
    $intent = NotificationIntent::v1('test.event.v1', 'test.template', ['subject' => 'Schedule changed'], $channels);

    app(NotificationIntentDispatcher::class)->dispatch($intent, 'event-inbox-channels', [$user], $channels);

    $projection = app(NotificationProjection::class)->forUser($user);

    expect($projection['unread_count'])->toBe(1)
        ->and($projection['recent'])->toHaveCount(1)
        ->and($projection['recent'][0]['channel'])->toBe(NotificationDelivery::CHANNEL_IN_APP)
        ->and($projection['recent'][0]['email_delivery']['status'])->toBe(NotificationDelivery::STATUS_PENDING);
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

    processNotificationDelivery($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_RETRYING)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->next_attempt_at)->not->toBeNull()
        ->and($delivery->last_error)->not->toBeNull()
        ->and($delivery->last_error)->not->toContain('@');

    // Backoff due — a later attempt still fails → terminal after max attempts.
    $delivery->update(['attempts' => 4, 'next_attempt_at' => now()->subSecond()]);
    processNotificationDelivery($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_FAILED_TERMINAL)
        ->and($delivery->attempts)->toBe(5);
});

test('recovery scan delivers a durable in-app row when its original enqueue was lost', function (): void {
    config(['queue.default' => 'sync']);

    $user = notifUser('recovery@example.test');
    $delivery = NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => 'event-recovery',
        'event_type' => 'test.recovery.v1',
        'event_version' => 1,
        'recipient_type' => User::class,
        'recipient_id' => $user->id,
        'channel' => NotificationDelivery::CHANNEL_IN_APP,
        'template_key' => 'test.recovery',
        'template_version' => 1,
        'payload' => ['subject' => 'Recovered'],
        'status' => NotificationDelivery::STATUS_PENDING,
        'attempts' => 0,
    ]);

    expect(Artisan::call('notifications:recover-deliveries'))->toBe(0);

    $delivery->refresh();
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($delivery->delivered_at)->not->toBeNull();
});

test('expired mail lease becomes uncertain without automatic duplicate send', function (): void {
    Mail::fake();
    $user = notifUser('expired-mail@example.test');
    $delivery = NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => 'event-expired-mail',
        'event_type' => 'test.expired_mail.v1',
        'event_version' => 1,
        'recipient_type' => User::class,
        'recipient_id' => $user->id,
        'channel' => NotificationDelivery::CHANNEL_MAIL,
        'template_key' => 'test.expired_mail',
        'template_version' => 1,
        'payload' => ['subject' => 'Retry check'],
        'status' => NotificationDelivery::STATUS_PROCESSING,
        'attempts' => 1,
        'claim_token' => (string) Str::uuid(),
        'lease_expires_at' => now()->subSecond(),
    ]);

    processNotificationDelivery($delivery);

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_OUTCOME_UNKNOWN)
        ->and($delivery->fresh()->outcome_unknown_at)->not->toBeNull();
    Mail::assertNothingSent();
});

test('exam operations recipients are scoped by capability and institution', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NOTIF-SCOPE',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDay(),
        'effective_end_at' => now()->addDay()->addHours(2),
        'version' => 1,
    ]);

    $otherInstitution = Institution::query()->create([
        'code' => 'SYN-INST-OTHER-NOTIF',
        'name' => 'Other Institution',
    ]);
    $foreignGuidanceUser = User::query()->create([
        'name' => 'Foreign Guidance',
        'email' => 'foreign-guidance@example.test',
        'password' => Str::password(32),
    ]);
    $foreignGuidanceUser->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
    $foreignGuidanceUser->staffProfile()->create([
        'institution_id' => $otherInstitution->id,
        'office' => 'guidance',
        'status' => 'active',
    ]);

    $registrarUser = User::query()->create([
        'name' => 'Registrar with Exam Capability',
        'email' => 'registrar-exam-cap@example.test',
        'password' => Str::password(32),
    ]);
    $registrarUser->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
    $registrarUser->staffProfile()->create([
        'institution_id' => $cycle->institution_id,
        'office' => 'registrar',
        'status' => 'active',
    ]);

    $recipients = NotificationRecipients::testAdministratorUsersFor(
        $session,
        app(CapabilityAuthorizer::class),
    );

    expect($recipients->pluck('id'))->toContain(
        User::query()->where('email', 'guidance.counselor@nexiam.test')->value('id'),
    )->not->toContain($foreignGuidanceUser->id, $registrarUser->id);
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
        ->and($view['recent'][0]['failed'])->toBeFalse()
        ->and($view['queue_active'])->toBeTrue();
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

test('recipient type prevents numeric user IDs from claiming identity inbox rows', function (): void {
    $user = notifUser('typed-owner@example.test');
    $identityDelivery = NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => 'typed-recipient-event',
        'event_type' => 'test.typed_recipient.v1',
        'event_version' => 1,
        'recipient_type' => ApplicantIdentity::class,
        'recipient_id' => $user->id,
        'channel' => NotificationDelivery::CHANNEL_IN_APP,
        'template_key' => 'test.typed_recipient',
        'template_version' => 1,
        'payload' => ['subject' => 'Not for this user'],
        'status' => NotificationDelivery::STATUS_PENDING,
        'attempts' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread_count', 0)
            ->where('notifications.recent', fn ($rows) => collect($rows)->pluck('id')->doesntContain($identityDelivery->id)));

    $this->actingAs($user)
        ->post(route('notifications.read', $identityDelivery))
        ->assertForbidden();

    expect($identityDelivery->fresh()->read_at)->toBeNull();
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

test('acceptance notifies the applicant identity before portal activation', function (): void {
    $result = submitSyntheticApplicant('accept-notif@example.test', 'Accept');
    $attempt = AdmissionAttempt::query()->findOrFail($result['attempt']->id);
    $identity = $attempt->identity;
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    expect($identity->applicantAccount)->toBeNull();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    $delivery = NotificationDelivery::query()
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('event_type', 'admission.accepted.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->firstOrFail();

    $applicant = app(AuthenticateApplicantWithAccessKey::class)
        ->handle($result['access_token'], '2001-01-01');
    $projection = app(NotificationProjection::class)->forUser($applicant);

    $row = collect($projection['recent'])->firstWhere('id', $delivery->id);

    expect($row)->toMatchArray([
        'id' => $delivery->id,
        'event_type' => 'admission.accepted.v1',
        'read' => false,
    ]);
    expect(NotificationDelivery::query()
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('event_type', 'admission.accepted.v1')
        ->where('channel', NotificationDelivery::CHANNEL_MAIL)
        ->exists())->toBeTrue();
});

test('an applicant identity receives its own inbox history before portal activation', function (): void {
    $submission = submitSyntheticApplicant('prelogin-notification@example.test', 'PreloginNotice');
    $attempt = AdmissionAttempt::query()->findOrFail($submission['attempt']->id);
    $identity = $attempt->identity;

    expect($identity->applicantAccount)->toBeNull();

    $receipt = NotificationDelivery::query()
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('event_type', 'admission.submitted.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->firstOrFail();

    expect($receipt->payload['context']['values']['application_no'])->toBe($attempt->reference_code)
        ->and(array_key_exists('access_token', $receipt->payload))->toBeFalse();

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $foreignInstitution = Institution::query()->create([
        'code' => 'SYN-INST-FOREIGN-ARRIVAL',
        'name' => 'Foreign Arrival Institution',
    ]);
    $foreignViewer = User::query()->create([
        'name' => 'Foreign Registrar Viewer',
        'email' => 'foreign-registrar-viewer@example.test',
        'password' => Str::password(32),
    ]);
    $foreignViewer->givePermissionTo(CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);
    $foreignViewer->staffProfile()->create([
        'institution_id' => $foreignInstitution->id,
        'office' => 'registrar',
        'status' => 'active',
    ]);

    $staffArrival = NotificationDelivery::forUser($registrar)
        ->where('event_type', 'admission.new_submission.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->firstOrFail();

    expect($staffArrival->payload['context']['values']['application_no'])->toBe($attempt->reference_code)
        ->and($staffArrival->payload)->not->toHaveKey('email')
        ->and(NotificationDelivery::forUser($foreignViewer)
            ->where('event_type', 'admission.new_submission.v1')
            ->exists())->toBeFalse();
    $applicant = app(AuthenticateApplicantWithAccessKey::class)
        ->handle($submission['access_token'], '2001-01-01');
    $projection = app(NotificationProjection::class)->forUser($applicant);

    expect($projection['unread_count'])->toBe(1)
        ->and(collect($projection['recent'])->pluck('id'))->toContain($receipt->id);

    $this->actingAs($applicant)
        ->post(route('notifications.read', $receipt))
        ->assertRedirect();

    expect($receipt->fresh()->read_at)->not->toBeNull();
});

test('applicant inbox isolates identity recipients and rejects mail rows as read state', function (): void {
    $submissionA = submitSyntheticApplicant('identity-scope-a@example.test', 'IdentityScopeA');
    $submissionB = submitSyntheticApplicant('identity-scope-b@example.test', 'IdentityScopeB');
    $attemptA = AdmissionAttempt::query()->findOrFail($submissionA['attempt']->id);
    $identityA = $attemptA->identity;
    $userA = app(AuthenticateApplicantWithAccessKey::class)->handle($submissionA['access_token'], '2001-01-01');
    $userB = app(AuthenticateApplicantWithAccessKey::class)->handle($submissionB['access_token'], '2001-01-01');

    NotificationDelivery::forUser($userB)->update(['read_at' => now()]);
    $deliveries = app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1(
            'test.event.v1',
            'test.event',
            ['subject' => 'Exam schedule', 'body' => 'Your exam schedule is ready.'],
            [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
        ),
        'test:identity-scope-a',
        [$identityA],
        [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
    );
    $inAppDelivery = $deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_IN_APP);
    $mailDelivery = $deliveries->firstWhere('channel', NotificationDelivery::CHANNEL_MAIL);

    $this->actingAs($userB)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread_count', 0)
            ->where('notifications.recent', fn ($rows) => collect($rows)->pluck('id')->doesntContain($inAppDelivery->id)));

    $this->actingAs($userB)
        ->post(route('notifications.read', $inAppDelivery))
        ->assertForbidden();

    $this->actingAs($userA)
        ->post(route('notifications.read', $mailDelivery))
        ->assertForbidden();

    $this->actingAs($userA)
        ->post(route('notifications.read', $inAppDelivery))
        ->assertRedirect();

    expect($inAppDelivery->fresh()->read_at)->not->toBeNull()
        ->and($mailDelivery->fresh()->read_at)->toBeNull();
});

test('mail delivery resolves a pre-login applicant identity without creating a user', function (): void {
    $transport = new ArrayTransport;
    $mailer = new Mailer('prelogin-proof', app('view'), $transport, app('events'));
    $mailer->alwaysFrom('notification-proof@example.test', 'Notification Proof');
    Mail::swap($mailer);

    $submission = submitSyntheticApplicant('prelogin-mail@example.test', 'PreloginMail');
    $attempt = AdmissionAttempt::query()->findOrFail($submission['attempt']->id);
    $identity = $attempt->identity;

    expect($identity->applicantAccount)->toBeNull();

    app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1(
            'admission.status.v1',
            'admission.status',
            ['subject' => 'Application update', 'body' => 'There is an update to your application.'],
            [NotificationDelivery::CHANNEL_MAIL],
        ),
        'admission.status:'.$attempt->id,
        [$identity],
        [NotificationDelivery::CHANNEL_MAIL],
    );
    $delivery = NotificationDelivery::query()
        ->where('event_id', 'admission.status:'.$attempt->id)
        ->where('event_type', 'admission.status.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('channel', NotificationDelivery::CHANNEL_MAIL)
        ->firstOrFail();

    processNotificationDelivery($delivery);

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    expect($transport->messages())->toHaveCount(1)
        ->and($transport->messages()->first()->getOriginalMessage()->getTo()[0]->getAddress())->toBe($identity->email);
    expect($identity->fresh()->applicantAccount)->toBeNull();
});

test('historical proctor duty does not generate an assignment notice', function (): void {
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NOTIF-HISTORICAL-DUTY',
        'starts_at' => now()->addDays(4)->setTime(8, 0),
        'effective_end_at' => now()->addDays(4)->setTime(10, 0),
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);

    ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $clerk->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'notif-historical-duty',
        'assigned_at' => now(),
    ]);

    expect(NotificationDelivery::forUser($clerk)
        ->where('event_type', 'exam.proctor_duty_assigned.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->exists())->toBeFalse();
});

test('result release producer binds each notification to the released attempt', function (): void {
    seedE2OmrFoundation();
    $submission = submitSyntheticApplicant('release-payload@example.test', 'ReleasePayload');
    $attempt = $submission['attempt'];
    $identity = $attempt->identity;
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => $attempt->version])
        ->assertRedirect();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-RELEASE-PAYLOAD',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->addMinutes(30),
        'version' => 1,
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'version' => 1,
        'opened_at' => now(),
        'finalized_at' => now(),
        'finalized_by_user_id' => $guidance->id,
    ]);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'notification-producer-test',
        'checksum' => hash('sha256', 'notification-producer-test'),
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
    ]);

    $release = app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'notification-release-producer',
        'online',
    );

    $deliveries = NotificationDelivery::query()
        ->where('event_type', 'result.released.v1')
        ->where('recipient_id', $identity->id)
        ->get();
    expect($deliveries)->toHaveCount(2)
        ->and($deliveries->every(fn (NotificationDelivery $delivery): bool => (int) $delivery->payload['attempt_id'] === (int) $attempt->id))->toBeTrue()
        ->and($deliveries->every(fn (NotificationDelivery $delivery): bool => app(NotificationBusinessValidity::class)->isCurrent($delivery)))->toBeTrue()
        ->and($release->admission_attempt_id)->toBe($attempt->id);
});

test('booking-only session version changes neither invalidate nor duplicate reminders', function (): void {
    seedE2OmrFoundation();
    $submission = submitSyntheticApplicant('reminder-booking-version@example.test', 'ReminderBookingVersion');
    $attempt = $submission['attempt'];
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $sessionVersion = 4;
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-BOOKING-REMINDER',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addHours(24),
        'effective_end_at' => now()->addHours(26),
        'version' => $sessionVersion,
        'notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot($sessionVersion),
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    expect(Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']))->toBe(0);
    $reminderDeliveries = NotificationDelivery::query()
        ->where('event_type', 'exam.reminder.v1')
        ->where('payload->assignment_id', $assignment->id)
        ->get();
    expect($reminderDeliveries)->toHaveCount(2)
        ->and($reminderDeliveries->where('channel', NotificationDelivery::CHANNEL_IN_APP))->toHaveCount(1)
        ->and($reminderDeliveries->where('channel', NotificationDelivery::CHANNEL_MAIL))->toHaveCount(1)
        ->and($reminderDeliveries->every(fn (NotificationDelivery $delivery): bool => $delivery->payload['session_version'] === $sessionVersion))->toBeTrue();
    $inAppDelivery = $reminderDeliveries->firstWhere('channel', NotificationDelivery::CHANNEL_IN_APP);

    $session->update([
        'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
        'version' => $sessionVersion + 1,
    ]);

    expect(app(NotificationBusinessValidity::class)->isCurrent($inAppDelivery))->toBeTrue()
        ->and(Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']))->toBe(0)
        ->and(NotificationDelivery::query()
            ->where('event_type', 'exam.reminder.v1')
            ->where('payload->assignment_id', $assignment->id)
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->count())->toBe(1)
        ->and(NotificationDelivery::query()
            ->where('event_type', 'exam.reminder.v1')
            ->where('payload->assignment_id', $assignment->id)
            ->where('channel', NotificationDelivery::CHANNEL_MAIL)
            ->count())->toBe(1);
});
