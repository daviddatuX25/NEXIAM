<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;

beforeEach(function (): void {
    seedE2Foundation();
});

test('Registrar and Guidance manage only their cycle notification policies', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->put(route('setup.cycles.notifications.admissions', $cycle), [
            'expected_version' => 0,
            'applicant_status_email_enabled' => false,
            'registrar_new_submission_alert_enabled' => false,
        ])
        ->assertRedirect();

    expect($cycle->fresh()->admission_notification_policy)->toMatchArray([
        'schema_version' => 1,
        'version' => 1,
        'applicant_status_email_enabled' => false,
        'registrar_new_submission_alert_enabled' => false,
    ]);

    $this->actingAs($registrar)
        ->put(route('setup.cycles.notifications.examination', $cycle), [
            'expected_version' => 0,
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [60, 1440],
            'reminder_email_enabled' => false,
        ])
        ->assertForbidden();

    $this->actingAs($guidance)
        ->put(route('setup.cycles.notifications.examination', $cycle), [
            'expected_version' => 0,
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [60, 1440],
            'reminder_email_enabled' => false,
        ])
        ->assertRedirect();

    expect($cycle->fresh()->exam_notification_policy)->toMatchArray([
        'schema_version' => 1,
        'version' => 1,
        'publication_email_enabled' => false,
        'reminder_offsets_minutes' => [1440, 60],
        'reminder_email_enabled' => false,
    ]);
});

test('cycle notification policy rejects unsupported and duplicate reminder fields', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->put(route('setup.cycles.notifications.examination', $cycle), [
            'expected_version' => 0,
            'publication_email_enabled' => true,
            'reminder_offsets_minutes' => [1440, 1440],
            'reminder_email_enabled' => true,
        ])
        ->assertSessionHasErrors('reminder_offsets_minutes.1');

    $this->actingAs($guidance)
        ->put(route('setup.cycles.notifications.examination', $cycle), [
            'expected_version' => 0,
            'publication_email_enabled' => true,
            'reminder_offsets_minutes' => [1440],
            'reminder_email_enabled' => true,
            'arbitrary_template' => 'unexpected',
        ])
        ->assertSessionHasErrors('policy');

    expect($cycle->fresh()->exam_notification_policy)->toBeNull();
});

test('cycle communication links redirect authorized offices without changing historical context', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    foreach ([$registrar, $guidance] as $actor) {
        $this->actingAs($actor)
            ->get(route('setup.cycles.notifications.edit', $cycle))
            ->assertRedirectToRoute('setup.notifications');
    }

    $this->actingAs(User::factory()->create())
        ->get(route('setup.cycles.notifications.edit', $cycle))
        ->assertForbidden();

    $cycle->forceFill(['is_active' => false])->save();
    $this->actingAs($guidance)
        ->get(route('setup.cycles.notifications.edit', $cycle))
        ->assertStatus(409);

    $foreignInstitution = Institution::query()->create([
        'code' => 'NOTIFICATION-FOREIGN',
        'name' => 'Foreign notification institution',
    ]);
    $foreignCycle = $cycle->replicate();
    $foreignCycle->forceFill([
        'institution_id' => $foreignInstitution->id,
        'code' => 'NOTIFICATION-FOREIGN-CYCLE',
        'is_active' => true,
    ])->save();
    $this->actingAs($guidance)
        ->get(route('setup.cycles.notifications.edit', $foreignCycle))
        ->assertForbidden();
});

test('disabling Registrar arrival notices suppresses only the optional staff notice', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->put(route('setup.cycles.notifications.admissions', $cycle), [
            'expected_version' => 0,
            'applicant_status_email_enabled' => true,
            'registrar_new_submission_alert_enabled' => false,
        ])
        ->assertRedirect();

    $submission = submitSyntheticApplicant('arrival-opt-out@example.test', 'ArrivalOptOut');
    $attempt = AdmissionAttempt::query()->findOrFail($submission['attempt']->id);
    $identity = $attempt->identity;

    expect(NotificationDelivery::query()
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('event_type', 'admission.submitted.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->exists())->toBeTrue()
        ->and(NotificationDelivery::query()
            ->where('recipient_type', User::class)
            ->where('recipient_id', $registrar->id)
            ->where('event_type', 'admission.new_submission.v1')
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->exists())->toBeFalse();
});

test('exam session create and edit pages expose cycle notification policy', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('exams.sessions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Create')
            ->has('cycle_exam_notification_policy'));

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NOTIF-EDIT-TEST',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
        'version' => 1,
    ]);

    $this->actingAs($guidance)
        ->get(route('exams.sessions.edit', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Edit')
            ->has('cycle_exam_notification_policy')
            ->where('session.notification_policy', null));
});

test('exam session can customize notification policy and freezes snapshot on publish', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $submission = submitSyntheticApplicant('examinee.custom-notif@example.test', 'CustomNotif');
    $attempt = AdmissionAttempt::query()->findOrFail($submission['attempt']->id);
    $identity = $attempt->identity;
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $this->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-CUSTOM-NOTIF',
        'starts_at' => now()->addDays(3)->toDateTimeString(),
        'effective_end_at' => now()->addDays(3)->addHours(2)->toDateTimeString(),
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'use_cycle_notification_defaults' => false,
        'notification_policy' => [
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [1440, 60],
            'reminder_email_enabled' => true,
        ],
    ])->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-CUSTOM-NOTIF')->firstOrFail();
    expect($session->notification_policy)->toMatchArray([
        'schema_version' => 1,
        'publication_email_enabled' => false,
        'reminder_offsets_minutes' => [1440, 60],
        'reminder_email_enabled' => true,
    ]);

    $this->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $attempt->id,
    ]);

    $this->actingAs($guidance)->post(route('exams.sessions.publish', $session), [
        'expected_version' => 1,
    ])->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($session->notification_policy_snapshot)->toMatchArray([
            'schema_version' => 1,
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [1440, 60],
            'reminder_email_enabled' => true,
        ]);

    // Disabled publication email emits ONLY in_app delivery row
    expect(NotificationDelivery::query()
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('event_type', 'exam.session_published.v1')
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->exists())->toBeTrue()
        ->and(NotificationDelivery::query()
            ->where('recipient_type', ApplicantIdentity::class)
            ->where('recipient_id', $identity->id)
            ->where('event_type', 'exam.session_published.v1')
            ->where('channel', NotificationDelivery::CHANNEL_MAIL)
            ->exists())->toBeFalse();
});
