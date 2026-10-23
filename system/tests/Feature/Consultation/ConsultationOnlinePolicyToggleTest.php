<?php

declare(strict_types=1);

use App\Authorization\CapabilityAuthorizer;
use App\Consultation\Actions\ManageConsultationPolicyAction;
use App\Consultation\Actions\StartOnlineConsultation;
use App\Consultation\Queries\OnlineConsultationDetailQuery;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Consultation\Support\ConsultationPolicyResolver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntentDispatcher;
use App\Scheduling\AssignmentPlanningMutex;
use App\Setup\Registry\SetupReadinessRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['unavailable' => true], 503)]);
});

function createAcceptedToggleApplicant(string $email): AdmissionAttempt
{
    $lastName = 'Toggle'.substr(hash('sha256', $email), 0, 10);
    $submission = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    test()->actingAs($registrar)
        ->post(route('admissions.accept', $submission['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $submission['attempt']->fresh();
}

function prepareToggleApplicantResult(AdmissionAttempt $attempt): ResultDocument
{
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-ONLINE-'.Str::uuid(),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);

    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'online-toggle-v1',
        'checksum' => hash('sha256', 'online-toggle-'.$attempt->id),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/online-toggle-'.$attempt->id.'.html',
        'preview_path' => 'results/online-toggle-'.$attempt->id.'.html',
        'version' => 1,
    ]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();

    return $document;
}

function readyMediaOnlineStarter(): StartOnlineConsultation
{
    return new StartOnlineConsultation(
        app(CapabilityAuthorizer::class),
        app(AssignmentPlanningMutex::class),
        app(ConsultationPolicyResolver::class),
        app(ConsultationMediaReadiness::class),
        app(RecordAuditEvent::class),
        app(NotificationIntentDispatcher::class),
        static fn (): array => ['ready' => true, 'reason' => null],
    );
}

function saveToggleMode(string $normalMethod, bool $allowOnlineExceptions, int $expectedVersion): ConsultationPolicy
{
    $test = test();

    return app(ManageConsultationPolicyAction::class)->handle($test->cycle, $test->guidance, [
        'normal_method' => $normalMethod,
        'allow_online_exceptions' => $allowOnlineExceptions,
        'expected_version' => $expectedVersion,
    ]);
}

test('setup consultation policy readiness uses the effective current-cycle policy only', function (): void {
    $institution = Institution::current();
    $cycle = $this->cycle->fresh();
    ConsultationPolicy::query()->where('institution_id', $institution->id)->delete();
    $definition = app(SetupReadinessRegistry::class)->get('consultation_policy');
    expect($definition)->not->toBeNull();

    $default = app(ConsultationPolicyResolver::class)->forCycle($cycle);
    expect($default->exists)->toBeFalse()
        ->and($definition->status($institution))->toBe('ready')
        ->and($definition->checks($institution)[0]['passed'])->toBeTrue();

    saveToggleMode(ConsultationPolicy::METHOD_F2F, false, 0);
    expect($definition->status($institution))->toBe('ready');

    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 1);
    expect($definition->status($institution))->toBe('ready');

    $otherInstitution = Institution::query()->create([
        'code' => 'SETUP-CONSULTATION-OTHER',
        'name' => 'Other institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $otherInstitution->id,
        'code' => 'READINESS-FOREIGN-CYCLE',
        'name' => 'Foreign cycle',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => now()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => true,
    ]);
    ConsultationPolicy::query()->create([
        'institution_id' => $otherInstitution->id,
        'academic_cycle_id' => $foreignCycle->id,
        'normal_method' => ConsultationPolicy::METHOD_ONLINE,
        'allow_online_exceptions' => false,
        'configured_by_user_id' => $this->guidance->id,
        'version' => 1,
    ]);
    ConsultationPolicy::query()->where('academic_cycle_id', $cycle->id)->delete();
    expect($definition->status($institution))->toBe('ready');
    $cycle->update(['is_active' => false]);
    expect($definition->status($institution))->toBe('critical');
    $cycle->update(['is_active' => true]);
    ConsultationPolicy::query()->create([
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'normal_method' => 'unsupported',
        'allow_online_exceptions' => false,
        'configured_by_user_id' => $this->guidance->id,
        'version' => 1,
    ]);
    expect($definition->status($institution))->toBe('critical');

    AcademicCycle::query()->where('institution_id', $institution->id)->update(['is_active' => false]);
    expect($definition->status($institution))->toBe('critical');
});
test('an absent policy defaults to Online version zero and permits explicit ready starts without persisting policy', function (): void {
    $resolver = app(ConsultationPolicyResolver::class);
    expect(ConsultationPolicy::query()->where('academic_cycle_id', $this->cycle->id)->exists())->toBeFalse()
        ->and($resolver->isOnlineEnabled((int) $this->cycle->institution_id, (int) $this->cycle->id))->toBeTrue();

    $attempt = createAcceptedToggleApplicant('toggle.default.'.uniqid().'@example.test');
    $document = prepareToggleApplicantResult($attempt);
    $receipt = readyMediaOnlineStarter()->handle($attempt, $this->guidance, (string) Str::uuid());
    $encounter = ConsultationEncounter::query()->findOrFail($receipt['encounter_id']);
    $delivery = NotificationDelivery::query()
        ->where('event_type', 'consultation.online_invited.v1')
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->firstOrFail();

    expect($encounter->consultation_policy_version)->toBe(0)
        ->and($encounter->result_document_id)->toBe($document->id)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue()
        ->and(ConsultationPolicy::query()->where('academic_cycle_id', $this->cycle->id)->exists())->toBeFalse();

    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_INVITED)
        ->and($encounter->fresh()->schedule_generation)->toBe(1)
        ->and(app(JitsiMediaAdapter::class)->accessWindowEnd($encounter->fresh()))->not->toBeNull();

    $historicalAttempt = createAcceptedToggleApplicant('toggle.no-pin.'.uniqid().'@example.test');
    $unPinnedEncounter = ConsultationEncounter::query()->create([
        'institution_id' => $this->cycle->institution_id,
        'admission_attempt_id' => $historicalAttempt->id,
        'counselor_user_id' => $this->guidance->id,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_INVITED,
        'room_name' => 'consultation-'.Str::random(32),
        'schedule_generation' => 1,
        'access_starts_at' => now()->subMinute(),
        'access_ends_at' => now()->addMinutes(10),
        'idempotency_key' => (string) Str::uuid(),
    ]);
    expect(app(JitsiMediaAdapter::class)->accessWindowEnd($unPinnedEncounter))->toBeNull();
});

test('F2F without online exceptions denies explicit online starts', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_F2F, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.f2f-deny.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $noticesBefore = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count();

    expect(fn () => readyMediaOnlineStarter()->handle($attempt, $this->guidance, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect(ConsultationEncounter::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count())->toBe($noticesBefore);
});

test('F2F with optional online exceptions permits explicit individual starts', function (): void {
    $policy = saveToggleMode(ConsultationPolicy::METHOD_F2F, true, 0);
    $attempt = createAcceptedToggleApplicant('toggle.f2f-allow.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $receipt = readyMediaOnlineStarter()->handle($attempt, $this->guidance, (string) Str::uuid());

    expect($policy->normal_method)->toBe(ConsultationPolicy::METHOD_F2F)
        ->and($policy->allow_online_exceptions)->toBeTrue()
        ->and($receipt['status'])->toBe(ConsultationEncounter::STATUS_INVITED)
        ->and(ConsultationEncounter::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
});

test('mode updates that preserve effective online permission do not fence active encounters', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.preserve.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $receipt = readyMediaOnlineStarter()->handle($attempt, $this->guidance, (string) Str::uuid());
    $encounter = ConsultationEncounter::query()->findOrFail($receipt['encounter_id']);

    saveToggleMode(ConsultationPolicy::METHOD_F2F, true, 1);

    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_INVITED)
        ->and($encounter->fresh()->consultation_policy_version)->toBe(1)
        ->and(app(JitsiMediaAdapter::class)->accessWindowEnd($encounter->fresh()))->not->toBeNull();
});

test('cycle policy writes reject foreign institution scope', function (): void {
    $foreignInstitution = Institution::query()->create(['code' => 'FOREIGN-'.Str::uuid(), 'name' => 'Foreign Institution']);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'FOREIGN-CYCLE-'.Str::uuid(),
        'name' => 'Foreign Cycle',
        'opens_on' => now()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
    ]);

    expect(fn () => app(ManageConsultationPolicyAction::class)->handle($foreignCycle, $this->guidance, [
        'normal_method' => ConsultationPolicy::METHOD_ONLINE,
        'allow_online_exceptions' => false,
        'expected_version' => 0,
    ]))->toThrow(AuthorizationException::class);
});

test('policy version conflict leaves the existing toggle unchanged', function (): void {
    $policy = saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);

    expect(fn () => saveToggleMode(ConsultationPolicy::METHOD_F2F, false, 0))
        ->toThrow(ValidationException::class);
    expect($policy->fresh()->normal_method)->toBe(ConsultationPolicy::METHOD_ONLINE)
        ->and($policy->fresh()->allow_online_exceptions)->toBeFalse();
});

test('Online mode normalizes exceptions off and changes no consultation or notice state', function (): void {
    $encountersBefore = ConsultationEncounter::query()->count();
    $noticesBefore = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count();

    $policy = saveToggleMode(ConsultationPolicy::METHOD_ONLINE, true, 0);

    expect($policy->normal_method)->toBe(ConsultationPolicy::METHOD_ONLINE)
        ->and($policy->allow_online_exceptions)->toBeFalse()
        ->and(ConsultationEncounter::query()->count())->toBe($encountersBefore)
        ->and(NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count())->toBe($noticesBefore);
});

test('unsupported consultation modes are rejected without creating a policy', function (): void {
    expect(fn () => app(ManageConsultationPolicyAction::class)->handle($this->cycle, $this->guidance, [
        'normal_method' => 'hybrid',
        'allow_online_exceptions' => false,
        'expected_version' => 0,
    ]))->toThrow(ValidationException::class);
    expect(ConsultationPolicy::query()->where('academic_cycle_id', $this->cycle->id)->exists())->toBeFalse();
});

test('disabling fences active encounters without reviving them and preserves saved outcomes', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.fence.'.uniqid().'@example.test');

    $invited = ConsultationEncounter::query()->create([
        'institution_id' => $this->cycle->institution_id,
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->guidance->id,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_INVITED,
        'schedule_generation' => 2,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $outcome = ConsultationEncounter::query()->create([
        'institution_id' => $this->cycle->institution_id,
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->guidance->id,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_OUTCOME_RECORDED,
        'schedule_generation' => 4,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    saveToggleMode(ConsultationPolicy::METHOD_F2F, false, 1);

    expect($invited->fresh()->status)->toBe(ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME)
        ->and($invited->fresh()->schedule_generation)->toBe(3)
        ->and($invited->fresh()->media_close_status)->toBe(ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING)
        ->and($outcome->fresh()->status)->toBe(ConsultationEncounter::STATUS_OUTCOME_RECORDED)
        ->and($outcome->fresh()->schedule_generation)->toBe(5)
        ->and($outcome->fresh()->media_close_status)->toBe(ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING);

    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 2);
    expect($invited->fresh()->status)->toBe(ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME)
        ->and($invited->fresh()->schedule_generation)->toBe(3);
});

test('unready media denies start without encounter or invitation notice', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    config(['jitsi.app_secret' => 'intentionally-short-test-secret']);
    $attempt = createAcceptedToggleApplicant('toggle.unready.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $noticesBefore = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count();

    expect(fn () => app(StartOnlineConsultation::class)->handle($attempt, $this->guidance, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    expect(ConsultationEncounter::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count())->toBe($noticesBefore);
});

test('unready attempt without prepared result cannot start online consultation and creates no encounter or notice', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.nodoc.'.uniqid().'@example.test');
    $starter = readyMediaOnlineStarter();
    $noticesBefore = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count();

    expect(fn () => $starter->handle($attempt, $this->guidance, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    expect(ConsultationEncounter::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count())->toBe($noticesBefore);
});

test('direct applicant starts online consultation, pins exact document and policy, and delivers online invitation via applicant portal', function (): void {
    $policy = saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.direct.'.uniqid().'@example.test');
    $document = prepareToggleApplicantResult($attempt);
    $starter = readyMediaOnlineStarter();
    $key = (string) Str::uuid();

    $receipt = $starter->handle($attempt, $this->guidance, $key);

    $encounter = ConsultationEncounter::query()->where('idempotency_key', $key)->firstOrFail();
    expect($receipt['encounter_id'])->toBe((int) $encounter->id)
        ->and($receipt['status'])->toBe(ConsultationEncounter::STATUS_INVITED)
        ->and(preg_match('/^consultation-[a-z0-9]{40}$/', (string) $encounter->room_name))->toBe(1)
        ->and((int) $encounter->result_document_id)->toBe((int) $document->id)
        ->and((int) $encounter->result_document_version)->toBe((int) $document->version)
        ->and((int) $encounter->consultation_policy_version)->toBe((int) $policy->version)
        ->and($encounter->command_fingerprint)->not->toBeNull();

    $delivery = NotificationDelivery::query()
        ->where('event_type', 'consultation.online_invited.v1')
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->firstOrFail();
    expect($delivery->payload['portal']['action_url'])->toBe(route('applicant.portal'))
        ->and($delivery->payload['event_resource']['schedule_generation'])->toBe(1)
        ->and($delivery->payload['event_resource']['result_document_id'])->toBe($document->id)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue();
});

test('scheduled applicant starts online within session window and rejects starts outside window', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('sched.window.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $starter = readyMediaOnlineStarter();

    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $futureSession = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $room->id,
        'code' => 'CONS-FUTURE-'.Str::uuid(),
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(2),
        'published_at' => now()->subDay(),
        'version' => 1,
    ]);
    ConsultationAssignment::query()->create([
        'consultation_session_id' => $futureSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    expect(fn () => $starter->handle($attempt, $this->guidance, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    $activeSession = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $room->id,
        'code' => 'CONS-ACTIVE-'.Str::uuid(),
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->subMinutes(10),
        'published_at' => now()->subDay(),
        'version' => 1,
    ]);
    ConsultationAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->update([
            'consultation_session_id' => $activeSession->id,
            'status' => ConsultationAssignment::STATUS_ASSIGNED,
        ]);

    $key = (string) Str::uuid();
    $receipt = $starter->handle($attempt, $this->guidance, $key);
    $encounter = ConsultationEncounter::query()->where('idempotency_key', $key)->firstOrFail();

    expect($receipt['encounter_id'])->toBe((int) $encounter->id)
        ->and($encounter->booking_path)->toBe(ConsultationEncounter::BOOKING_PATH_SCHEDULED)
        ->and((int) $encounter->consultation_session_id)->toBe((int) $activeSession->id);
});

test('idempotent replay returns receipt without duplicate encounter or notice and rejects conflicting context', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.replay.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $starter = readyMediaOnlineStarter();
    $key = 'toggle-replay-key-1';

    $firstReceipt = $starter->handle($attempt, $this->guidance, $key);
    $noticesAfterFirst = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count();

    $secondReceipt = $starter->handle($attempt, $this->guidance, $key);
    $noticesAfterSecond = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->count();

    expect($secondReceipt['encounter_id'])->toBe($firstReceipt['encounter_id'])
        ->and($noticesAfterSecond)->toBe($noticesAfterFirst);

    $otherAttempt = createAcceptedToggleApplicant('toggle.other.'.uniqid().'@example.test');
    prepareToggleApplicantResult($otherAttempt);
    expect(fn () => $starter->handle($otherAttempt, $this->guidance, $key))
        ->toThrow(ValidationException::class);
});

test('policy disable fences replay from returning an open encounter URL and invalidates pending notices', function (): void {
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $attempt = createAcceptedToggleApplicant('toggle.fence.notice.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $starter = readyMediaOnlineStarter();
    $key = (string) Str::uuid();

    $starter->handle($attempt, $this->guidance, $key);
    $delivery = NotificationDelivery::query()
        ->where('event_type', 'consultation.online_invited.v1')
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->firstOrFail();
    expect(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue();

    saveToggleMode(ConsultationPolicy::METHOD_F2F, false, 1);

    $replayedReceipt = $starter->handle($attempt, $this->guidance, $key);
    expect($replayedReceipt['url'])->toBe(route('consultation.index'))
        ->and(app(NotificationBusinessValidity::class)->isCurrent($delivery->fresh()))->toBeFalse();
});
test('applicant detail shows online guidance only when effective policy permits it', function (): void {
    $this->withoutExceptionHandling();
    $this->withoutVite();
    $attempt = createAcceptedToggleApplicant('toggle.detail.policy.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    $version = saveToggleMode(ConsultationPolicy::METHOD_F2F, false, 0)->version;

    $this->actingAs($this->guidance)
        ->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->where('online_consultation.visible', false)
            ->where('online_consultation.can_start', false)
            ->where('online_consultation.exception_mode', false)
            ->where('online_consultation.encounter_url', null));

    $version = saveToggleMode(ConsultationPolicy::METHOD_F2F, true, $version)->version;
    $this->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->where('online_consultation.visible', true)
            ->where('online_consultation.exception_mode', true)
            ->where('online_consultation.can_start', false)
            ->where('online_consultation.reason', fn ($reason) => is_string($reason) && $reason !== ''));

    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, $version);
    $this->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->where('online_consultation.visible', true)
            ->where('online_consultation.exception_mode', false));
});

test('applicant detail opens an owned active online encounter instead of offering a duplicate start', function (): void {
    $attempt = createAcceptedToggleApplicant('toggle.detail.active.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $receipt = readyMediaOnlineStarter()->handle($attempt, $this->guidance, (string) Str::uuid());
    $expectedUrl = route('consultation.encounter.show', ['encounter' => $receipt['encounter_id']]);

    $this->actingAs($this->guidance)
        ->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->where('online_consultation.visible', true)
            ->where('online_consultation.can_start', false)
            ->where('online_consultation.encounter_url', $expectedUrl)
            ->where('online_consultation.reason', 'An online consultation is already active for this applicant.'));
});
test('applicant detail never offers a foreign counselor active encounter', function (): void {
    $attempt = createAcceptedToggleApplicant('toggle.detail.foreign.'.uniqid().'@example.test');
    prepareToggleApplicantResult($attempt);
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    ConsultationEncounter::query()->create([
        'institution_id' => $this->cycle->institution_id,
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => User::factory()->create()->id,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_INVITED,
        'room_name' => 'consultation-'.Str::random(32),
        'schedule_generation' => 1,
        'access_starts_at' => now()->subMinute(),
        'access_ends_at' => now()->addMinutes(10),
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($this->guidance)
        ->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->where('online_consultation.visible', true)
            ->where('online_consultation.can_start', false)
            ->where('online_consultation.encounter_url', null));
});

test('expired online encounters recover through a fresh start without reopening the old call', function (): void {
    $attempt = createAcceptedToggleApplicant('toggle.expired.'.Str::uuid().'@example.test');
    prepareToggleApplicantResult($attempt);
    saveToggleMode(ConsultationPolicy::METHOD_ONLINE, false, 0);
    $starter = readyMediaOnlineStarter();
    $oldKey = (string) Str::uuid();
    $oldReceipt = $starter->handle($attempt, $this->guidance, $oldKey);
    $expired = ConsultationEncounter::query()->findOrFail($oldReceipt['encounter_id']);
    $oldGeneration = $expired->schedule_generation;
    $this->travelTo($expired->access_ends_at);

    $detail = app(OnlineConsultationDetailQuery::class)
        ->forGuidance($attempt->fresh(), $this->guidance);
    expect($detail['encounter_url'])->toBeNull()
        ->and($detail['reason'])->not->toBe('An online consultation is already active for this applicant.');

    $replay = $starter->handle($attempt, $this->guidance, $oldKey);
    expect($replay['encounter_id'])->toBe($oldReceipt['encounter_id'])
        ->and($replay['url'])->toBe(route('consultation.index'));

    $freshReceipt = $starter->handle($attempt, $this->guidance, (string) Str::uuid());
    $fresh = ConsultationEncounter::query()->findOrFail($freshReceipt['encounter_id']);
    $expired->refresh();
    expect($fresh->id)->not->toBe($expired->id)
        ->and($freshReceipt['url'])->toBe(route('consultation.encounter.show', $fresh))
        ->and($fresh->access_starts_at->equalTo(now()))->toBeTrue()
        ->and($expired->status)->toBe(ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME)
        ->and($expired->schedule_generation)->toBe($oldGeneration + 1)
        ->and($expired->media_close_status)->toBe(ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING)
        ->and(app(JitsiMediaAdapter::class)->accessWindowEnd($expired))->toBeNull();
});
test('consultation policy return destination is restricted to the consultation index route', function (): void {
    $consultationUrl = route('consultation.index');

    $this->actingAs($this->guidance)
        ->get(route('setup.consultation-policy.index', ['return_to' => $consultationUrl]))
        ->assertInertia(fn ($page) => $page
            ->where('return_to', $consultationUrl));

    $this->actingAs($this->guidance)
        ->get(route('setup.consultation-policy.index', ['return_to' => 'https://outside.example/']))
        ->assertInertia(fn ($page) => $page
            ->where('return_to', null));
});
