<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\NotificationOutbox;
use App\Models\Release\ResultRelease;
use App\Models\User;

test('release notifies after commit once', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $attempt = $result['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->post(route('admissions.accept', $attempt), ['expected_version' => 1]);

    $cycle = \App\Models\Foundation\AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = \App\Models\Foundation\Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REL-1',
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->subHour()->toDateTimeString(),
    ]);

    $session = ExamSession::query()->firstOrFail();
    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $attempt->id,
    ]);
    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), ['expected_version' => 1]);
    $this->actingAs($guidance)->post(route('exams.sessions.close', $session), ['expected_version' => 2]);

    $grading = GradingSession::query()->firstOrFail();
    $areas = AptitudeArea::query()->where('is_active', true)->get();

    foreach ($areas as $area) {
        $this->actingAs($guidance)->post(route('grading.score', $grading), [
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id,
            'score' => 85,
            'idempotency_key' => 'score-'.$area->id,
        ]);
    }

    $this->actingAs($guidance)->post(route('grading.finalize', $grading));

    $this->actingAs($guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'idempotency_key' => 'consult-1',
    ]);

    $this->actingAs($guidance)->post(route('release.prepare', $attempt));

    $response = $this->actingAs($guidance)->post(route('release.store', $attempt), [
        'idempotency_key' => 'release-1',
    ]);

    $response->assertRedirect()->assertSessionDoesntHaveErrors();

    expect(ResultRelease::query()->where('is_current', true)->exists())->toBeTrue();

    $outbox = NotificationOutbox::query()->where('idempotency_key', 'like', 'notify-release-%')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->status)->toBe(NotificationOutbox::STATUS_SENT);
});

test('consultation decision idempotency does not duplicate', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant();
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

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

    $userA = app(\App\Admission\Actions\AuthenticateApplicantWithAccessKey::class)->handle(
        $a['access_token'],
        '2001-01-01',
    );

    $this->actingAs($userA)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Dashboard')
            ->has('attempts', 1)
        );

    expect(AdmissionAttempt::query()->count())->toBeGreaterThan(1);
});
