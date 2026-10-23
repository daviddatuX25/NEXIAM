<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\UnpublishExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Policies\ExamNotificationPolicy;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    seedE2Foundation();
});

test('unauthorized user cannot access any domain desk', function (): void {
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->get(route('setup.notifications.admissions'))
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->get(route('setup.notifications.examination'))
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->get(route('setup.notifications.results'))
        ->assertForbidden();
});

test('registrar can access admissions desk but is forbidden from examination desk', function (): void {
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.notifications.admissions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Admissions')
            ->has('active_cycle')
            ->has('policy')
            ->has('notices')
            ->where('capabilities.can_manage', true));

    $this->actingAs($registrar)
        ->get(route('setup.notifications.examination'))
        ->assertForbidden();
});

test('guidance counselor can access examination desk but is forbidden from admissions desk', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($counselor)
        ->get(route('setup.notifications.examination'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Examination')
            ->has('active_cycle')
            ->has('policy')
            ->has('custom_sessions')
            ->has('notices')
            ->where('capabilities.can_manage', true));

    $this->actingAs($counselor)
        ->get(route('setup.notifications.admissions'))
        ->assertForbidden();
});

test('authorized registrar can update admissions communication policy', function (): void {
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($registrar)
        ->put(route('setup.notifications.admissions.update'), [
            'applicant_status_email_enabled' => false,
            'registrar_new_submission_alert_enabled' => false,
            'expected_version' => AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy)->version,
        ])
        ->assertRedirect();

    $freshPolicy = $cycle->fresh()->admission_notification_policy;
    expect($freshPolicy['applicant_status_email_enabled'])->toBeFalse()
        ->and($freshPolicy['registrar_new_submission_alert_enabled'])->toBeFalse();
});

test('authorized guidance counselor can update examination countdown intervals', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($counselor)
        ->put(route('setup.notifications.examination.update'), [
            'publication_email_enabled' => true,
            'reminder_email_enabled' => true,
            'reminder_offsets_minutes' => [1440, 60],
            'expected_version' => ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy)->version,
        ])
        ->assertRedirect();

    $freshPolicy = $cycle->fresh()->exam_notification_policy;
    expect($freshPolicy['reminder_offsets_minutes'])->toBe([1440, 60]);
});
test('admissions desk update strictly rejects unpersisted delivery_controls or unknown keys', function (): void {
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $response = $this->actingAs($registrar)
        ->from(route('setup.notifications.admissions'))
        ->put(route('setup.notifications.admissions.update'), [
            'applicant_status_email_enabled' => true,
            'registrar_new_submission_alert_enabled' => true,
            'expected_version' => AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy)->version,
            'delivery_controls' => [
                'send_email_body' => true,
                'attach_pdf_document' => false,
                'enable_portal_download' => true,
            ],
        ]);

    $response->assertSessionHasErrors(['policy']);
});

test('examination desk update strictly rejects unpersisted delivery_controls or unknown keys', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $response = $this->actingAs($counselor)
        ->from(route('setup.notifications.examination'))
        ->put(route('setup.notifications.examination.update'), [
            'publication_email_enabled' => true,
            'reminder_email_enabled' => true,
            'reminder_offsets_minutes' => [1440, 60],
            'expected_version' => ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy)->version,
            'delivery_controls' => [
                'send_email_body' => true,
                'attach_pdf_document' => true,
                'enable_portal_download' => true,
            ],
        ]);

    $response->assertSessionHasErrors(['policy']);
});

test('unpublishing an exam session clears notification_policy_snapshot so republish takes fresh draft policy', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $submission = submitSyntheticApplicant('snapshot-reset@example.test', 'SnapshotReset');
    $attempt = AdmissionAttempt::query()->findOrFail($submission['attempt']->id);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, 1);

    $counselor->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EX-SNAPSHOT-RESET-TEST',
        now()->addDays(3)->toDateTimeString(),
        now()->addDays(3)->addHours(2)->toDateTimeString(),
        $counselor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    $session->update([
        'notification_policy' => [
            'schema_version' => 1,
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [1440],
            'reminder_email_enabled' => true,
        ],
    ]);

    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $counselor);

    app(PublishExamSession::class)->handle($session, $counselor, $session->version);

    $publishedSession = $session->fresh();
    expect($publishedSession->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($publishedSession->notification_policy_snapshot)->not->toBeNull()
        ->and($publishedSession->notification_policy_snapshot['publication_email_enabled'])->toBeFalse();

    // Now unpublish
    app(UnpublishExamSession::class)->handle($publishedSession, $counselor, $publishedSession->version, 'Revising countdown policy');

    $unpublishedSession = $session->fresh();
    expect($unpublishedSession->status)->toBe(ExamSession::STATUS_DRAFT)
        ->and($unpublishedSession->notification_policy_snapshot)->toBeNull();

    // Update draft policy override
    $unpublishedSession->update([
        'notification_policy' => [
            'schema_version' => 1,
            'publication_email_enabled' => true,
            'reminder_offsets_minutes' => [4320, 1440, 120, 60],
            'reminder_email_enabled' => true,
        ],
    ]);

    // Republish and assert that fresh draft policy was frozen into the new snapshot
    app(PublishExamSession::class)->handle($unpublishedSession, $counselor, $unpublishedSession->version);

    $republishedSession = $session->fresh();
    expect($republishedSession->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($republishedSession->notification_policy_snapshot)->not->toBeNull()
        ->and($republishedSession->notification_policy_snapshot['publication_email_enabled'])->toBeTrue()
        ->and($republishedSession->notification_policy_snapshot['reminder_offsets_minutes'])->toBe([4320, 1440, 120, 60]);
});

test('desk policy requests reject every unsupported field without changing persisted policy', function (string $field, mixed $value): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $admissionPolicy = $cycle->admission_notification_policy;
    $examPolicy = $cycle->exam_notification_policy;
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->putJson(route('setup.notifications.admissions.update'), [
        'expected_version' => AdmissionNotificationPolicy::fromStorage($admissionPolicy)->version,
        'applicant_status_email_enabled' => false,
        'registrar_new_submission_alert_enabled' => false,
        $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors('policy');
    $this->actingAs($guidance)->putJson(route('setup.notifications.examination.update'), [
        'expected_version' => ExamNotificationPolicy::fromStorage($examPolicy)->version,
        'publication_email_enabled' => false,
        'reminder_email_enabled' => false,
        'reminder_offsets_minutes' => [60],
        $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors('policy');

    expect($cycle->fresh()->admission_notification_policy)->toBe($admissionPolicy)
        ->and($cycle->fresh()->exam_notification_policy)->toBe($examPolicy);
})->with([
    'delivery controls' => ['delivery_controls', []],
    'email switch' => ['send_email_body', true],
    'PDF switch' => ['attach_pdf_document', true],
    'portal switch' => ['enable_portal_download', true],
    'unknown field' => ['new_policy_switch', true],
]);

test('stale desk expected-version leaves policy unchanged with no saved status', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $policy = $cycle->admission_notification_policy;
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)->from(route('setup.notifications.admissions'))
        ->put(route('setup.notifications.admissions.update'), [
            'expected_version' => AdmissionNotificationPolicy::fromStorage($policy)->version + 1,
            'applicant_status_email_enabled' => false,
            'registrar_new_submission_alert_enabled' => false,
        ])->assertSessionHasErrors('version')->assertSessionMissing('status');
    expect($cycle->fresh()->admission_notification_policy)->toBe($policy);
});

test('cross-office grant cannot authorize policy writes and Results remains release-only workflow policy', function (): void {
    $actor = User::factory()->create();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $cycle->institution_id,
        'office' => 'registrar',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET2FAKEY',
        'two_factor_generation' => (string) Str::uuid(),
        'must_change_password' => false,
    ]);
    $actor->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web'));
    $actor->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web'));
    $this->actingAs($actor)->putJson(route('setup.notifications.examination.update'), [])
        ->assertForbidden();
    $this->putJson(route('setup.notifications.results.update'), [])->assertForbidden();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($guidance)->from(route('setup.notifications.results'))
        ->put(route('setup.notifications.results.update'), [])
        ->assertRedirect()->assertSessionHas('status', 'Result and consultation notices are governed by their release workflows; no standalone policy was changed.');
});
