<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\RecordThreadService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\RecordThread;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\ExamReminderDispatcher;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationProjection;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Testing\Actions\DispatchPracticeReminder;
use App\Testing\Actions\PreparePracticeExchange;
use App\Testing\Actions\RegisterPracticeInquiryActors;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
    Queue::fake();
    Mail::fake();
    config(['mail.default' => 'array']);
    $this->freezeSecond();
});

/** @return array{set:PracticeFixtureSet,attempt:AdmissionAttempt,applicant:User,guidance:User,academic:User} */
function practiceMessageFixture(): array
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $academic = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $set = PracticeFixtureSet::query()->create([
        'label' => 'Case rehearsal', 'institution_id' => $cycle->institution_id, 'academic_cycle_id' => $cycle->id,
        'scenario' => 'case-coordination', 'purpose' => 'demo', 'created_by_user_id' => $guidance->id,
        'actor_bindings' => ['guidance' => $guidance->id, 'academic-leader' => $academic->id],
        'material_snapshot' => [], 'credentials' => [],
    ]);
    $result = app(PracticeWorkspace::class)->run($set, fn (): array => submitSyntheticApplicant('case.'.Str::uuid().'@example.test', 'Rivera'));
    $attempt = $result['attempt'];
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($result['access_token'], '2001-01-01');

    return compact('set', 'attempt', 'applicant', 'guidance', 'academic');
}

function practiceMessageSession(PracticeFixtureSet $set, AdmissionAttempt $attempt, string $window = '24h'): ExamSession
{
    return app(PracticeWorkspace::class)->run($set, function () use ($set, $attempt, $window): ExamSession {
        $session = ExamSession::query()->create([
            'academic_cycle_id' => $set->academic_cycle_id,
            'room_id' => Room::query()->where('code', 'RM-101')->firstOrFail()->id,
            'code' => 'EX-'.Str::uuid(), 'status' => ExamSession::STATUS_PUBLISHED,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'starts_at' => now()->addMinutes(ExamReminderDispatcher::WINDOW_RANGES[$window][0]),
            'effective_end_at' => now()->addMinutes(ExamReminderDispatcher::WINDOW_RANGES[$window][0] + 120),
            'notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(1), 'version' => 1,
        ]);
        ExamAssignment::query()->create([
            'exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id,
            'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED, 'version' => 1,
        ]);

        return $session;
    });
}

function practiceMessageReleasedCase(array $fixture): Program
{
    $attempt = $fixture['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED, 'accepted_at' => now()]);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id, 'template_version' => 'test-contract',
        'checksum' => hash('sha256', 'contract'), 'status' => 'ready', 'version' => 1,
    ]);
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id, 'result_document_id' => $document->id,
        'released_by_user_id' => $fixture['guidance']->id, 'release_version' => 1,
        'is_current' => true, 'released_at' => now(), 'idempotency_key' => (string) Str::uuid(),
    ]);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id, 'program_id' => $program->id,
        'opened_by_user_id' => $fixture['guidance']->id, 'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'Test relationship', 'idempotency_key' => (string) Str::uuid(), 'opened_at' => now(), 'version' => 1,
    ]);

    return $program;
}

test('practice reminders share the ordinary window dispatcher and persist queued deduplicated receipts', function (string $window): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt'], $window);
    $action = app(DispatchPracticeReminder::class);
    $input = ['session_id' => $session->id, 'window' => $window];
    expect($action->prerequisites($fixture['set'], $input)[0]['status'])->toBe('available');
    $first = $action->handle($fixture['set'], $input);
    $second = $action->handle($fixture['set'], $input);
    expect($first['created_count'])->toBeGreaterThan(0)
        ->and($second['created_count'])->toBe(0)
        ->and(array_column($first['deliveries'], 'status'))->each->toBe(NotificationDelivery::STATUS_PENDING)
        ->and(array_column($first['deliveries'], 'id'))->toBe(array_column($second['deliveries'], 'id'));
    foreach ($first['deliveries'] as $row) {
        $delivery = NotificationDelivery::query()->findOrFail($row['id']);
        expect($delivery->payload['practice_fixture_set_id'])->toBe($fixture['set']->id)
            ->and($delivery->recipient_type)->toBe(ApplicantIdentity::class)
            ->and((int) $delivery->recipient_id)->toBe($fixture['attempt']->applicant_identity_id);
    }
    Mail::assertNothingSent();
})->with(['72h', '24h', '2h', '1h']);

test('ordinary scanner excludes owned sessions while scoped reminders never scan another session', function (): void {
    $fixture = practiceMessageFixture();
    $selected = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $other = practiceMessageFixture();
    $unselected = practiceMessageSession($other['set'], $other['attempt']);
    $before = NotificationDelivery::query()->count();
    expect(Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']))->toBe(0)
        ->and(NotificationDelivery::query()->count())->toBe($before);
    $result = app(DispatchPracticeReminder::class)->handle($fixture['set'], ['session_id' => $selected->id, 'window' => '24h']);
    expect($result['created_count'])->toBeGreaterThan(0)
        ->and(NotificationDelivery::query()->where('event_id', ExamReminderDispatcher::eventId($unselected, $unselected->assignments()->firstOrFail(), '24h'))->exists())->toBeFalse();
});

test('disabled offsets out of window and foreign sessions block reminders without delivery writes', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $other = practiceMessageFixture();
    $action = app(DispatchPracticeReminder::class);
    $input = ['session_id' => $session->id, 'window' => '24h'];
    $before = NotificationDelivery::query()->count();
    $session->update(['notification_policy_snapshot' => (new ExamNotificationPolicy(1, true, [60], true))->snapshot(1)]);
    expect(fn () => $action->handle($fixture['set'], $input))->toThrow(ValidationException::class);
    $session->update(['notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(1), 'starts_at' => now()->addDays(5)]);
    expect(fn () => $action->handle($fixture['set'], $input))->toThrow(ValidationException::class);
    expect(fn () => $action->handle($other['set'], $input))->toThrow(ValidationException::class);
    expect(NotificationDelivery::query()->count())->toBe($before);
});

test('one foreign assignment or unsafe mail blocks the entire selected reminder', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $foreign = submitSyntheticApplicant('ordinary@example.test', 'Ordinary');
    $assignmentId = DB::table('exam_assignments')->insertGetId([
        'exam_session_id' => $session->id, 'admission_attempt_id' => $foreign['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    $action = app(DispatchPracticeReminder::class);
    $input = ['session_id' => $session->id, 'window' => '24h'];
    $before = NotificationDelivery::query()->count();
    expect(fn () => $action->handle($fixture['set'], $input))->toThrow(ValidationException::class);
    DB::table('exam_assignments')->where('id', $assignmentId)->update(['status' => ExamAssignment::STATUS_CANCELLED]);
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'external.example.com']);
    expect(fn () => $action->handle($fixture['set'], $input))->toThrow(ValidationException::class);
    config(['mail.default' => 'array']);
    $fixture['attempt']->identity->update(['email' => 'actual@university.edu']);
    expect(fn () => $action->handle($fixture['set'], $input))->toThrow(ValidationException::class);
    expect(NotificationDelivery::query()->count())->toBe($before);
});

test('practice reminder worker suppresses retirement and changed ownership rather than reporting delivery', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $result = app(DispatchPracticeReminder::class)->handle($fixture['set'], ['session_id' => $session->id, 'window' => '24h']);
    $fixture['set']->update(['status' => 'retired', 'retired_at' => now()]);
    foreach ($result['deliveries'] as $row) {
        (new ProcessNotificationDelivery($row['id']))->handle(app(RecordAuditEvent::class), app(NotificationBusinessValidity::class));
        expect(NotificationDelivery::query()->findOrFail($row['id'])->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED);
    }
    Mail::assertNothingSent();
});

test('queued practice mail becomes invalid if the configured transport changes', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $result = app(DispatchPracticeReminder::class)->handle($fixture['set'], ['session_id' => $session->id, 'window' => '24h']);
    $mail = collect($result['deliveries'])->firstWhere('channel', NotificationDelivery::CHANNEL_MAIL);
    expect($mail)->not->toBeNull();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'external.example.com']);
    (new ProcessNotificationDelivery($mail['id']))->handle(app(RecordAuditEvent::class), app(NotificationBusinessValidity::class));
    expect(NotificationDelivery::query()->findOrFail($mail['id'])->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED);
    Mail::assertNothingSent();
});

test('queued reminder loses eligibility when a persisted root is moved or unclassified', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $result = app(DispatchPracticeReminder::class)->handle($fixture['set'], ['session_id' => $session->id, 'window' => '24h']);
    DB::table('applicant_identities')->where('id', $fixture['attempt']->applicant_identity_id)->update(['practice_fixture_set_id' => null]);
    foreach ($result['deliveries'] as $row) {
        (new ProcessNotificationDelivery($row['id']))->handle(app(RecordAuditEvent::class), app(NotificationBusinessValidity::class));
        expect(NotificationDelivery::query()->findOrFail($row['id'])->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED);
    }
    Mail::assertNothingSent();
});

test('practice in-app delivery is reported delivered only after the actual ordinary worker transition', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $result = app(DispatchPracticeReminder::class)->handle($fixture['set'], ['session_id' => $session->id, 'window' => '24h']);
    $row = collect($result['deliveries'])->firstWhere('channel', NotificationDelivery::CHANNEL_IN_APP);
    expect($row['status'])->toBe(NotificationDelivery::STATUS_PENDING);
    (new ProcessNotificationDelivery($row['id']))->handle(app(RecordAuditEvent::class), app(NotificationBusinessValidity::class));
    $delivery = NotificationDelivery::query()->findOrFail($row['id']);
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($delivery->delivered_at)->not->toBeNull();
    Mail::assertNothingSent();
});

test('practice reminder delivery rows roll back with their owning operation', function (): void {
    $fixture = practiceMessageFixture();
    $session = practiceMessageSession($fixture['set'], $fixture['attempt']);
    $before = NotificationDelivery::query()->count();
    expect(fn () => DB::transaction(function () use ($fixture, $session): void {
        app(DispatchPracticeReminder::class)->handle($fixture['set'], ['session_id' => $session->id, 'window' => '24h']);
        throw new RuntimeException('Operation rollback');
    }))->toThrow(RuntimeException::class);
    expect(NotificationDelivery::query()->count())->toBe($before);
});

test('ordinary practice workflow notifications omit unbound staff and real email without replacing recipients', function (): void {
    $fixture = practiceMessageFixture();
    $bound = $fixture['guidance'];
    $bound->update(['email' => 'actual@university.edu']);
    $unbound = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $deliveries = app(PracticeWorkspace::class)->run($fixture['set'], fn () => app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1('practice.test.v1', 'practice.test', ['subject' => 'Case update'], ['in_app', 'mail']),
        'practice-test-'.Str::uuid(), [$bound, $unbound], ['in_app', 'mail'],
    ));
    expect($deliveries)->toHaveCount(1)
        ->and($deliveries->first()->channel)->toBe(NotificationDelivery::CHANNEL_IN_APP)
        ->and((int) $deliveries->first()->recipient_id)->toBe($bound->id)
        ->and($deliveries->first()->payload['practice_fixture_set_id'])->toBe($fixture['set']->id);
    $projection = app(NotificationProjection::class);
    expect($projection->unreadCount($bound))->toBe(0);
    $selectedUnread = app(PracticeWorkspace::class)->run($fixture['set'], fn (): int => $projection->unreadCount($bound));
    expect($selectedUnread)->toBe(1);
});

test('fresh owned inquiry registration uses exact pre-registered office counterparts without grants', function (): void {
    (new ApplicantOfficeInquiryDemoSeeder)->run();
    $fixture = practiceMessageFixture();
    $registrar = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::REGISTRAR_EMAIL)->firstOrFail();
    $guidance = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail();
    $fixture['set']->update(['actor_bindings' => ['registrar' => $registrar->id, 'guidance' => $guidance->id]]);
    $helper = app(RegisterPracticeInquiryActors::class);
    expect(array_column($helper->prerequisitesFor($fixture['set']->actor_bindings, (int) $fixture['set']->institution_id, (int) $fixture['set']->academic_cycle_id), 'status'))
        ->toBe(['available', 'available']);
    $fixture['applicant']->givePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    $permissions = $fixture['applicant']->getAllPermissions()->pluck('name')->sort()->values()->all();
    $result = $helper->register($fixture['set']);
    expect($result['registered_count'])->toBe(1)
        ->and($result['staff'])->toBe(['registrar' => $registrar->id, 'guidance' => $guidance->id])
        ->and($fixture['applicant']->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe($permissions);
    $access = app(ApplicantOfficeInquiryAccess::class);
    expect($access->officeStaffFor($fixture['attempt'], 'registrar')->modelKeys())->toBe([$registrar->id]);
    $created = CreateApplicantOfficeInquiry::handle($fixture['applicant'], 'registrar', 'Admission question', 'Please explain the next step.', (string) Str::uuid());
    expect($created['status'])->toBe('open');
    $fixture['applicant']->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    expect(fn () => $helper->register($fixture['set']))->toThrow(ValidationException::class);
    expect(app(CapabilityAuthorizer::class)->decideFresh($fixture['applicant'], CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed)->toBeFalse();
});

test('student registration bootstraps only a brand-new owned account and never restores revoked authority', function (): void {
    (new ApplicantOfficeInquiryDemoSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $registrar = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::REGISTRAR_EMAIL)->firstOrFail();
    $guidance = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail();
    $set = PracticeFixtureSet::query()->create([
        'label' => 'Student rehearsal', 'institution_id' => $cycle->institution_id, 'academic_cycle_id' => $cycle->id,
        'scenario' => 'student', 'purpose' => 'research', 'created_by_user_id' => $guidance->id,
        'actor_bindings' => ['guidance' => $guidance->id, 'registrar' => $registrar->id],
        'material_snapshot' => [], 'credentials' => [],
    ]);
    $fresh = app(PracticeWorkspace::class)->run($set, fn (): array => submitSyntheticApplicant('student.'.Str::uuid().'@example.test', 'Santos'));
    $identity = $fresh['attempt']->identity;
    $set->update(['credentials' => [(string) $identity->id => [
        'access_key' => $fresh['access_token'], 'birthdate' => '2001-01-01', 'admission_attempt_id' => $fresh['attempt']->id,
    ]]]);
    expect($identity->applicantAccount()->exists())->toBeFalse();
    $helper = app(RegisterPracticeInquiryActors::class);
    $result = $helper->register($set);
    expect($result['registered_count'])->toBe(1)
        ->and($result)->not->toHaveKey('credentials')
        ->and($result['applicants'][0]['admission_attempt_id'])->toBe($fresh['attempt']->id);
    $user = User::query()->findOrFail($result['applicants'][0]['user_id']);
    $user->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    expect(fn () => $helper->register($set))->toThrow(ValidationException::class);
    expect(app(CapabilityAuthorizer::class)->decideFresh($user, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed)->toBeFalse();
});

test('inquiry registration blocks missing applicant permission and never adopts unclassified staff', function (): void {
    $fixture = practiceMessageFixture();
    $helper = app(RegisterPracticeInquiryActors::class);
    $before = ApplicantOfficeInquiryDemoActor::query()->count();
    expect(array_column($helper->prerequisitesFor($fixture['set']->actor_bindings, (int) $fixture['set']->institution_id, (int) $fixture['set']->academic_cycle_id), 'status'))
        ->toBe(['missing', 'available']);
    expect(fn () => $helper->register($fixture['set']))->toThrow(ValidationException::class);
    expect(ApplicantOfficeInquiryDemoActor::query()->count())->toBe($before);

    $fixture['guidance']->syncRoles([]);
    expect(array_column($helper->prerequisitesFor($fixture['set']->actor_bindings, (int) $fixture['set']->institution_id, (int) $fixture['set']->academic_cycle_id), 'status'))
        ->toBe(['missing', 'missing']);
});

test('native paired preparation creates metadata without an empty thread and excludes Registrar and unrelated academic actors', function (): void {
    $fixture = practiceMessageFixture();
    practiceMessageReleasedCase($fixture);
    $action = app(PreparePracticeExchange::class);
    expect($action->prerequisites($fixture['set'])[0]['status'])->toBe('available');
    $first = $action->handle($fixture['set']);
    $second = $action->handle($fixture['set']);
    expect($second['thread_id'])->toBeNull()
        ->and($first['admission_attempt_id'])->toBe((string) $fixture['attempt']->id)
        ->and(RecordThread::query()->where('admission_attempt_id', $fixture['attempt']->id)->count())->toBe(0)
        ->and($action->projection($fixture['set'])['url'] ?? null)->toBeNull();

    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $fixture['attempt'],
        (int) $first['program_id'],
        'Case coordination',
        'Guidance is opening the paired case exchange.',
        $fixture['guidance'],
        '16161616-1616-4616-8616-161616161616',
    );
    $prepared = $action->handle($fixture['set']);
    expect($prepared['thread_id'])->toBe((string) $thread->id);
    expect($thread->fresh()->latest_message_sequence)->toBe(1)
        ->and($thread->messages()->where('sequence', 1)->value('body'))->toBe('Guidance is opening the paired case exchange.');
    expect($action->projection($fixture['set'])['url'])->toContain('practice_set=');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $registrar->givePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    $registrar->staffProfile()->update(['program_codes' => ['BSN']]);
    expect(app(RecordThreadService::class)->canRead($thread, $registrar))->toBeFalse();
    $fixture['academic']->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    $fixture['academic']->roles()->detach();
    expect($action->projection($fixture['set'])['status'])->toBe('missing');
});

test('pairing requires actual released results program relationship and a permitted native opener without impersonation', function (): void {
    $fixture = practiceMessageFixture();
    $action = app(PreparePracticeExchange::class);
    expect($action->prerequisites($fixture['set'])[0]['reason'])->toContain('released results');
    expect(fn () => $action->handle($fixture['set']))->toThrow(ValidationException::class);
    practiceMessageReleasedCase($fixture);
    $fixture['set']->update(['created_by_user_id' => User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail()->id]);
    expect($action->prerequisites($fixture['set'])[0]['reason'])->toContain('cannot impersonate');
    expect(fn () => $action->handle($fixture['set']))->toThrow(ValidationException::class);
    expect(RecordThread::query()->where('admission_attempt_id', $fixture['attempt']->id)->count())->toBe(0);
});
