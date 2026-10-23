<?php

use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(fn () => seedE2Foundation());

/**
 * @return array{attempt: AdmissionAttempt, identity: ApplicantIdentity}
 */
function notificationRecoveryApplicant(?string $email = null): array
{
    $email ??= 'recovery-'.Str::random(10).'@example.test';
    $submitted = submitSyntheticApplicant($email, 'Recovery'.Str::random(6));

    return [
        'attempt' => $submitted['attempt'],
        'identity' => $submitted['attempt']->identity,
    ];
}

function notificationRecoveryDelivery(
    string $eventType,
    string $status,
    AdmissionAttempt $attempt,
    array $payload = [],
): NotificationDelivery {
    return NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => hash('sha256', (string) Str::uuid()),
        'event_type' => $eventType,
        'event_version' => 1,
        'recipient_type' => ApplicantIdentity::class,
        'recipient_id' => $attempt->applicant_identity_id,
        'channel' => NotificationDelivery::CHANNEL_MAIL,
        'template_key' => 'notification.recovery.test',
        'template_version' => 1,
        'payload' => array_merge(['attempt_id' => (string) $attempt->id], $payload),
        'status' => $status,
        'attempts' => 4,
        'last_error' => 'Provider did not accept the message.',
        'outcome_unknown_at' => $status === NotificationDelivery::STATUS_OUTCOME_UNKNOWN ? now()->subMinute() : null,
    ]);
}

function notificationRecoveryOccurrence(TestCase $test, User $actor, NotificationDelivery $delivery): string
{
    $domain = str_starts_with($delivery->event_type, 'exam.') ? 'examination' : 'admissions';
    $rows = actingAsAdmissionSupportStaff($test, $actor)
        ->getJson(route('setup.notifications.occurrences', ['domain' => $domain]))
        ->assertOk()
        ->json('data');

    return collect($rows)
        ->first(fn (array $row): bool => str_starts_with($row['occurrence_id'], $delivery->event_id.'~'))['occurrence_id'];
}

function notificationRecoveryRetryUrl(string $occurrence, string $delivery): string
{
    return route('setup.notifications.occurrences.deliveries.retry', [
        'occurrence' => $occurrence,
        'delivery' => $delivery,
    ]);
}

function notificationRecoverySession(string $code, string $status = ExamSession::STATUS_PUBLISHED): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(24);

    return ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => $status,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => $startsAt,
        'effective_end_at' => $startsAt->copy()->addHours(2),
        'version' => 3,
        'notification_policy_snapshot' => ['session_version' => 3],
    ]);
}

function notificationRecoveryReminder(
    AdmissionAttempt $attempt,
    ExamSession $session,
): NotificationDelivery {
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);

    return notificationRecoveryDelivery('exam.reminder.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $attempt, [
        'session_id' => (string) $session->id,
        'assignment_id' => (string) $assignment->id,
        'identity_id' => (string) $attempt->applicant_identity_id,
        'schedule_generation' => 3,
        'session_version' => 3,
        'assignment_version' => 1,
        'starts_at' => $session->starts_at->toIso8601String(),
        'window' => '24h',
    ]);
}

test('guest cannot retry a notification delivery', function (): void {
    $applicant = notificationRecoveryApplicant();
    $delivery = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $applicant['attempt']);
    $occurrence = notificationRecoveryOccurrence($this, User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail(), $delivery);

    auth()->logout();

    $this->post(notificationRecoveryRetryUrl($occurrence, $delivery->id))
        ->assertRedirect(route('login'));
});

test('retry authorization is limited to the owning domain and staff capabilities', function (): void {
    $applicant = notificationRecoveryApplicant();
    $registrarClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $outsider = User::factory()->create();

    $admission = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $applicant['attempt']);
    $admissionOccurrence = notificationRecoveryOccurrence($this, $registrarClerk, $admission);
    $this->actingAs($guidance)->postJson(notificationRecoveryRetryUrl($admissionOccurrence, $admission->id))->assertForbidden();

    $session = notificationRecoverySession('EX-RECOVERY-AUTH');
    $reminder = notificationRecoveryReminder($applicant['attempt'], $session);
    $reminderOccurrence = notificationRecoveryOccurrence($this, $guidance, $reminder);
    $this->actingAs($registrarClerk)->postJson(notificationRecoveryRetryUrl($reminderOccurrence, $reminder->id))->assertForbidden();
    $this->actingAs($outsider)
        ->withSession(['auth.surface' => null, 'auth.session_version' => null])
        ->postJson(notificationRecoveryRetryUrl($admissionOccurrence, $admission->id))
        ->assertForbidden();
});

test('retry returns not found for missing occurrence or delivery and mismatched occurrence delivery', function (): void {
    $applicant = notificationRecoveryApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $first = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $applicant['attempt']);
    $second = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $applicant['attempt']);
    $occurrence = notificationRecoveryOccurrence($this, $registrar, $first);

    $this->actingAs($registrar)
        ->postJson(notificationRecoveryRetryUrl('not-an-occurrence', $first->id))
        ->assertNotFound();
    $this->postJson(notificationRecoveryRetryUrl($occurrence, (string) Str::uuid()))
        ->assertNotFound();
    $this->postJson(notificationRecoveryRetryUrl($occurrence, $second->id))
        ->assertNotFound();
});

test('retry rejects statuses that are not failed terminal or outcome unknown', function (): void {
    $applicant = notificationRecoveryApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    foreach ([
        NotificationDelivery::STATUS_DELIVERED,
        NotificationDelivery::STATUS_PENDING,
        NotificationDelivery::STATUS_PROCESSING,
        NotificationDelivery::STATUS_RETRYING,
        NotificationDelivery::STATUS_SUPERSEDED,
    ] as $status) {
        $delivery = notificationRecoveryDelivery('admission.accepted.v1', $status, $applicant['attempt']);
        $occurrence = notificationRecoveryOccurrence($this, $registrar, $delivery);

        $this->actingAs($registrar)
            ->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only failed terminal or uncertain deliveries can be retried.');
    }
});

test('retry rejects a stale expected delivery version with conflict', function (): void {
    $applicant = notificationRecoveryApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $delivery = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $applicant['attempt']);
    $occurrence = notificationRecoveryOccurrence($this, $registrar, $delivery);

    $this->actingAs($registrar)
        ->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id), [
            'expected_version' => $delivery->updated_at->timestamp + 10,
        ])
        ->assertStatus(409);
});

test('outcome unknown retry requires duplicate-risk acknowledgement and a reason', function (): void {
    $applicant = notificationRecoveryApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $delivery = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_OUTCOME_UNKNOWN, $applicant['attempt']);
    $occurrence = notificationRecoveryOccurrence($this, $registrar, $delivery);

    $this->actingAs($registrar)
        ->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id), ['reason' => 'Provider log review'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('acknowledge_duplicate_risk');

    $this->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id), ['acknowledge_duplicate_risk' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

test('valid retry of an outcome unknown delivery resets state and records actor audit', function (): void {
    Queue::fake();
    $applicant = notificationRecoveryApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $delivery = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_OUTCOME_UNKNOWN, $applicant['attempt']);
    $occurrence = notificationRecoveryOccurrence($this, $registrar, $delivery);
    $reason = 'Confirmed provider did not send';

    $this->actingAs($registrar)
        ->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id), [
            'acknowledge_duplicate_risk' => true,
            'reason' => $reason,
        ])
        ->assertOk();

    $audit = AuditEvent::query()->where('event_type', 'notification.delivery_manually_retried')->firstOrFail();
    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_PENDING)
        ->and($delivery->fresh()->attempts)->toBe(0)
        ->and($delivery->fresh()->last_error)->toBeNull()
        ->and($delivery->fresh()->outcome_unknown_at)->toBeNull()
        ->and($audit->payload)->toMatchArray([
            'delivery_id' => $delivery->id,
            'actor_id' => $registrar->id,
            'reason' => $reason,
            'prior_status' => NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
        ]);
    Queue::assertPushed(ProcessNotificationDelivery::class, fn (ProcessNotificationDelivery $job): bool => $job->deliveryId === $delivery->id);
});

test('valid retry of a terminal failure resets state and dispatches processing', function (): void {
    Queue::fake();
    $applicant = notificationRecoveryApplicant();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $delivery = notificationRecoveryDelivery('admission.accepted.v1', NotificationDelivery::STATUS_FAILED_TERMINAL, $applicant['attempt']);
    $occurrence = notificationRecoveryOccurrence($this, $registrar, $delivery);
    $reason = 'Corrected delivery configuration';

    $this->actingAs($registrar)
        ->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id), ['reason' => $reason])
        ->assertOk();

    $audit = AuditEvent::query()->where('event_type', 'notification.delivery_manually_retried')->firstOrFail();
    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_PENDING)
        ->and($delivery->fresh()->attempts)->toBe(0)
        ->and($delivery->fresh()->last_error)->toBeNull()
        ->and($audit->payload)->toMatchArray([
            'delivery_id' => $delivery->id,
            'actor_id' => $registrar->id,
            'reason' => $reason,
            'prior_status' => NotificationDelivery::STATUS_FAILED_TERMINAL,
        ]);
    Queue::assertPushed(ProcessNotificationDelivery::class, fn (ProcessNotificationDelivery $job): bool => $job->deliveryId === $delivery->id);
});

test('retry-time reminder validity supersedes cancelled and rescheduled sessions', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $supersededDeliveryIds = [];

    foreach (['cancelled', 'rescheduled'] as $case) {
        $applicant = notificationRecoveryApplicant();
        $session = notificationRecoverySession('EX-RECOVERY-'.strtoupper($case));
        $delivery = notificationRecoveryReminder($applicant['attempt'], $session);
        $session->update($case === 'cancelled'
            ? ['status' => ExamSession::STATUS_CANCELLED]
            : ['starts_at' => $session->starts_at->copy()->addHour()]);
        $occurrence = notificationRecoveryOccurrence($this, $guidance, $delivery);

        $this->actingAs($guidance)
            ->postJson(notificationRecoveryRetryUrl($occurrence, $delivery->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This notification is no longer current and has been superseded.');

        expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED);
        $supersededDeliveryIds[] = $delivery->id;
    }

    $auditedDeliveryIds = AuditEvent::query()
        ->where('event_type', 'notification.delivery_superseded')
        ->get()
        ->pluck('payload.delivery_id');
    expect($auditedDeliveryIds->intersect($supersededDeliveryIds))->toHaveCount(2);
});
