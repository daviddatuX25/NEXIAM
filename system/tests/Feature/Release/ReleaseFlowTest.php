<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationDelivery;

test('release notifies after commit once', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('release-flow@example.test', 'ReleaseFlow');
    $attempt = $result['attempt'];

    // NT-8 — the applicant must hold a portal account for the in_app + mail
    // deliveries to have a recipient; login creates the account.
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->post(route('admissions.accept', $attempt), ['expected_version' => 1]);

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REL-1',
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
    ]);

    $session = ExamSession::query()->firstOrFail();
    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $attempt->id,
    ]);
    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), ['expected_version' => 1]);

    // GRE-3 — attendance + submission must be recorded before the session closes.
    $assignment = $session->assignments()->first();
    app(RecordExamAttendance::class)
        ->handle($assignment, $guidance, 'present', 'att-rel-1');
    app(RecordExamSubmission::class)
        ->handle($assignment, $guidance, 'sub-rel-1');

    $this->actingAs($guidance)->post(route('exams.sessions.close', $session), ['expected_version' => 2]);

    $grading = GradingSession::query()->firstOrFail();
    $areas = AptitudeArea::query()->where('is_active', true)->get();

    $this->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => $areas->map(fn ($area) => [
            'aptitude_area_id' => $area->id,
            'score' => 85,
        ])->all(),
        'idempotency_key' => 'scores-release-1',
    ]);

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
