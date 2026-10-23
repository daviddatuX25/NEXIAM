<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationDelivery;

require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

test('release notifies after commit once', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $fixture = individualReleaseReadinessFixture($this, $guidance, 'release-flow');
    $attempt = $fixture['attempts']['complete'];
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $fixture['access_tokens']['complete'],
        '2001-01-01',
    );
    recordIndividualReleaseExternalFinal($fixture, 'complete', $guidance);

    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('institution_id', $cycle->institution_id)->firstOrFail();
    $consSession = ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'CONS-FLOW-1',
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDays(2),
        'version' => 1,
    ]);
    ConsultationAssignment::query()->create([
        'consultation_session_id' => $consSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    $this->actingAs($guidance)->post(route('release.prepare', $attempt));
    $this->actingAs($guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'idempotency_key' => 'consult-1',
    ]);
    $response = $this->actingAs($guidance)->post(route('release.store', $attempt), [
        'idempotency_key' => 'release-1',
    ]);
    $response->assertRedirect()->assertSessionDoesntHaveErrors();

    expect(ResultRelease::query()->where('is_current', true)->exists())->toBeTrue();

    $deliveries = NotificationDelivery::forUser($applicant)
        ->where('event_type', 'result.released.v1')
        ->get();

    expect($deliveries)->not->toBeEmpty()
        ->and($deliveries->pluck('channel')->all())->toContain(NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL)
        ->and($deliveries->every(fn (NotificationDelivery $d): bool => $d->status === NotificationDelivery::STATUS_DELIVERED))->toBeTrue();
});

test('consultation decision idempotency does not duplicate', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $consSession = ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'CONS-DUP-1',
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDays(2),
        'version' => 1,
    ]);
    ConsultationAssignment::query()->create([
        'consultation_session_id' => $consSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    $this->actingAs($guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'idempotency_key' => 'consult-dup',
    ]);

    $count = ConsultationDecision::query()->count();

    $this->actingAs($guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'idempotency_key' => 'consult-dup',
    ]);

    expect(ConsultationDecision::query()->count())->toBe($count);
});

test('applicant portal scopes to own attempts', function (): void {
    seedE2Foundation();
    submitSyntheticApplicant('other@example.test', 'Other');
    $a = submitSyntheticApplicant('one@example.test', 'One');

    $userA = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $a['access_token'],
        '2001-01-01',
    );

    $this->actingAs($userA)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('identity.applicant_no', $a['attempt']->reference_code)
        );

    $this->actingAs($userA)
        ->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('attempt.id', $a['attempt']->id)
        );

    expect(AdmissionAttempt::query()->count())->toBeGreaterThan(1);
});
