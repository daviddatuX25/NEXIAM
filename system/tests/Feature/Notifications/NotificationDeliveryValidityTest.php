<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

function validityDelivery(string $eventType, ApplicantIdentity $identity, array $payload, string $channel = NotificationDelivery::CHANNEL_IN_APP): NotificationDelivery
{
    return NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => hash('sha256', Str::uuid()->toString()),
        'event_type' => $eventType,
        'recipient_type' => ApplicantIdentity::class,
        'recipient_id' => $identity->id,
        'channel' => $channel,
        'template_key' => match ($eventType) {
            'result.released.v1' => ($payload['mode'] ?? null) === 'online' ? 'result.released.online' : 'result.released.f2f',
            default => $eventType,
        },
        'payload' => $payload,
        'status' => NotificationDelivery::STATUS_PENDING,
        'attempts' => 0,
    ]);
}

function processValidityDelivery(NotificationDelivery $delivery): void
{
    app()->call([
        app(ProcessNotificationDelivery::class, ['deliveryId' => $delivery->id]),
        'handle',
    ]);
}

test('stale reminder is superseded before channel processing when assignment is no longer active', function (): void {
    $applicant = submitSyntheticApplicant('validity-reminder@example.test', 'ValidityReminder');
    $identity = $applicant['attempt']->identity;
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(24);
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-VALIDITY',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => $startsAt,
        'effective_end_at' => $startsAt->copy()->addHours(2),
        'version' => 3,
        'notification_policy_snapshot' => ['session_version' => 3],
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $applicant['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);
    $delivery = validityDelivery('exam.reminder.v1', $identity, [
        'session_id' => $session->id,
        'assignment_id' => $assignment->id,
        'identity_id' => $identity->id,
        'schedule_generation' => 3,
        'session_version' => 3,
        'assignment_version' => 1,
        'starts_at' => $startsAt->toIso8601String(),
        'window' => '24h',
    ]);

    $assignment->update(['status' => ExamAssignment::STATUS_REMOVED, 'version' => 2]);
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');
    $unreadBefore = app(NotificationProjection::class)->unreadCount($user);
    processValidityDelivery($delivery);

    $audit = AuditEvent::query()->where('event_type', 'notification.delivery_superseded')->firstOrFail();
    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($delivery->fresh()->claim_token)->toBeNull()
        ->and($delivery->fresh()->last_error)->toBeNull()
        ->and($audit->payload)->not->toHaveKey('body')
        ->and($audit->payload)->not->toHaveKey('score')
        ->and(app(NotificationProjection::class)->unreadCount($user))->toBe($unreadBefore - 1)
        ->and(collect(app(NotificationProjection::class)->forUser($user)['recent'])->pluck('id'))->not->toContain($delivery->id);
});

test('reminder remains current when booking changes increment mutable session version', function (): void {
    $applicant = submitSyntheticApplicant('validity-booking-version@example.test', 'ValidityBookingVersion');
    $identity = $applicant['attempt']->identity;
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(24);
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-BOOKING-VERSION',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => $startsAt,
        'effective_end_at' => $startsAt->copy()->addHours(2),
        'version' => 4,
        'notification_policy_snapshot' => ['session_version' => 4],
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $applicant['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);
    $delivery = validityDelivery('exam.reminder.v1', $identity, [
        'session_id' => $session->id,
        'assignment_id' => $assignment->id,
        'identity_id' => $identity->id,
        'schedule_generation' => 4,
        'session_version' => 4,
        'assignment_version' => 1,
        'starts_at' => $startsAt->toIso8601String(),
        'window' => '24h',
    ]);

    $session->update([
        'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
        'version' => 5,
    ]);

    expect(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue();
    processValidityDelivery($delivery);
    expect($delivery->fresh()->status)->not->toBe(NotificationDelivery::STATUS_SUPERSEDED);
});

test('result delivery is superseded after its release is withdrawn', function (): void {
    $applicant = submitSyntheticApplicant('validity-release@example.test', 'ValidityRelease');
    $identity = $applicant['attempt']->identity;
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $applicant['attempt']->id,
        'template_version' => 'validity-test',
        'checksum' => hash('sha256', 'validity-result-document'),
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
    ]);
    $release = ResultRelease::query()->create([
        'admission_attempt_id' => $applicant['attempt']->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'mode' => 'online',
        'idempotency_key' => 'validity-release-1',
    ]);
    $delivery = validityDelivery('result.released.v1', $identity, [
        'release_id' => $release->id,
        'release_version' => 1,
        'attempt_id' => $applicant['attempt']->id,
        'identity_id' => $identity->id,
        'mode' => 'online',
        'subject' => 'Your admission result is available',
    ]);

    expect(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue();
    $wrongModeDelivery = validityDelivery('result.released.v1', $identity, array_replace($delivery->payload, ['mode' => 'f2f']));
    expect(app(NotificationBusinessValidity::class)->isCurrent($wrongModeDelivery))->toBeFalse();
    processValidityDelivery($wrongModeDelivery);
    expect($wrongModeDelivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED);

    $release->update(['is_current' => false, 'unreleased_at' => now()]);
    processValidityDelivery($delivery);

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($delivery->fresh()->payload)->not->toHaveKey('score')
        ->and($delivery->fresh()->last_error)->toBeNull();
});

test('unbound protected events fail closed while historical state notices remain deliverable', function (): void {
    $applicant = submitSyntheticApplicant('validity-history@example.test', 'ValidityHistory');
    $identity = $applicant['attempt']->identity;
    $unboundReminder = validityDelivery('exam.reminder.v1', $identity, ['session_id' => 999]);
    $unboundResult = validityDelivery('result.released.v1', $identity, ['attempt_id' => $applicant['attempt']->id]);
    $historicalNotice = validityDelivery('admission.accepted.v1', $identity, [
        'subject' => 'Your application was accepted',
        'attempt_id' => $applicant['attempt']->id,
    ]);

    processValidityDelivery($unboundReminder);
    processValidityDelivery($unboundResult);
    processValidityDelivery($historicalNotice);

    expect($unboundReminder->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($unboundResult->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($historicalNotice->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
});

test('filtered notification projections remain JSON lists after leading and middle notices are hidden', function (): void {
    $application = submitSyntheticApplicant('validity-list-shape@example.test', 'ValidityListShape');
    $identity = $application['attempt']->identity;
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($application['access_token'], '2001-01-01');
    $visibleIds = [];
    foreach ([
        ['visible', 1],
        ['hidden', 2],
        ['visible', 3],
        ['hidden', 4],
    ] as [$visibility, $minute]) {
        $delivery = validityDelivery(
            $visibility === 'visible' ? 'admission.accepted.v1' : 'exam.session_started_early.v1',
            $identity,
            [
                'attempt_id' => $application['attempt']->id,
                'identity_id' => $identity->id,
                'subject' => $visibility === 'visible' ? 'Application accepted '.$minute : 'Outdated appointment',
                'body' => 'Review your application status.',
                'action_url' => route('applicant.portal'),
            ],
        );
        $delivery->forceFill(['created_at' => now()->addMinutes($minute)])->save();
        if ($visibility === 'visible') {
            array_unshift($visibleIds, $delivery->id);
        }
    }

    $projection = app(NotificationProjection::class);
    $bell = $projection->forUser($applicant, 2);
    $inbox = $projection->list($applicant, 2);

    expect(array_is_list($bell['recent']))->toBeTrue()
        ->and(array_is_list($inbox))->toBeTrue()
        ->and(array_column($bell['recent'], 'id'))->toBe($visibleIds)
        ->and(array_column($inbox, 'id'))->toBe($visibleIds)
        ->and(json_decode(json_encode($bell, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR)->recent)->toBeArray();
});
