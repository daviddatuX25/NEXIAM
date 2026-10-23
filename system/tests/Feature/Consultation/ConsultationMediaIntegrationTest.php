<?php

declare(strict_types=1);

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Consultation\Services\JitsiTokenService;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Release\ResultDocument;
use App\Models\User;
use Database\Seeders\OnlineConsultationDemoSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    config([
        'jitsi.domain' => 'meet.example.test',
        'jitsi.origin' => 'https://meet.example.test',
        'jitsi.app_id' => 'nexiam',
        'jitsi.app_secret' => str_repeat('s', 48),
        'jitsi.audience' => 'nexiam-consultation',
        'jitsi.jwt_ttl_seconds' => 900,
        'jitsi.admission_secret' => str_repeat('a', 48),
        'jitsi.control_url' => 'http://prosody:5280/nexiam_admission',
        'jitsi.control_token' => str_repeat('c', 48),
        'jitsi.admission_url' => 'http://app:8000/consultation/internal/jitsi/admission',
    ]);
    ConsultationPolicy::query()->create([
        'institution_id' => $this->cycle->institution_id,
        'academic_cycle_id' => $this->cycle->id,
        'normal_method' => ConsultationPolicy::METHOD_ONLINE,
        'allow_online_exceptions' => false,
        'configured_by_user_id' => $this->guidance->id,
        'version' => 1,
    ]);
});

function createMediaAcceptedAttempt(string $email): AdmissionAttempt
{
    $lastName = 'Media'.substr(hash('sha256', $email), 0, 10);
    $submission = submitSyntheticApplicant($email, $lastName);
    app(AuthenticateApplicantWithAccessKey::class)->handle($submission['access_token'], '2001-01-01');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    test()->actingAs($registrar)
        ->post(route('admissions.accept', $submission['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $submission['attempt']->fresh();
}

function createMediaEncounter(AdmissionAttempt $attempt, User $counselor, array $overrides = []): ConsultationEncounter
{
    return ConsultationEncounter::query()->create(array_merge([
        'institution_id' => $attempt->institution_id,
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $counselor->id,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_INVITED,
        'room_name' => 'consultation-'.Str::random(32),
        'schedule_generation' => 4,
        'consultation_policy_version' => 1,
        'access_starts_at' => now()->subMinute(),
        'access_ends_at' => now()->addMinutes(20),
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides));
}

test('live health readiness requires nonce-bound authenticated control and installed admission evidence', function (): void {
    $healthMode = 'healthy';
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) use (&$healthMode) {
        expect($request->url())->toBe('http://prosody:5280/nexiam_admission/health')
            ->and($request->hasHeader('Authorization', 'Bearer '.str_repeat('c', 48)))->toBeTrue();

        if ($healthMode === 'unhealthy') {
            return Http::response(['unavailable' => true], 503);
        }
        if ($healthMode === 'dead') {
            throw new ConnectionException('connection refused');
        }

        return Http::response([
            'nonce' => $healthMode === 'wrong_nonce' ? 'not-the-request-nonce' : $request['nonce'],
            'protocol_version' => 1,
            'admission_hook_installed' => true,
            'admission_callback_reachable' => true,
            'admission_callback_status' => 200,
            'presence_callback_reachable' => true,
            'mandatory_lobby_installed' => true,
            'callbacks_verified_at' => $healthMode === 'stale' ? now()->timestamp - 11 : now()->timestamp,
            'callbacks_age_seconds' => $healthMode === 'stale' ? 11 : 0,
        ]);
    });

    $readiness = app(ConsultationMediaReadiness::class);
    expect(strlen((string) config('jitsi.app_secret')))->toBeGreaterThanOrEqual(32)
        ->and(strlen((string) config('jitsi.admission_secret')))->toBeGreaterThanOrEqual(32)
        ->and($readiness->report())->toBe(['ready' => true, 'reason' => null]);

    $healthMode = 'wrong_nonce';
    expect($readiness->report()['ready'])->toBeFalse();

    $healthMode = 'unhealthy';
    expect($readiness->report()['ready'])->toBeFalse();

    $healthMode = 'stale';
    expect($readiness->report()['ready'])->toBeFalse();

    $healthMode = 'dead';
    expect($readiness->report()['ready'])->toBeFalse();
});

test('authenticated prejoin business denial is a nonrecursive HTTP 200 result', function (): void {
    $payload = [
        'room_name' => 'nexiam-health-probe',
        'participant_id' => 'nexiam-health-probe',
        'token' => 'invalid-health-probe',
    ];

    $response = $this->withHeader('X-Jitsi-Admission-Secret', str_repeat('a', 48))
        ->postJson(route('consultation.jitsi.admission'), $payload);
    $response->assertOk()->assertJson(['allowed' => false]);
    expect($response->headers->get('Content-Length'))->toBe((string) strlen($response->getContent()));

    $this->withHeader('X-Jitsi-Admission-Secret', 'wrong-secret')
        ->postJson(route('consultation.jitsi.admission'), $payload)
        ->assertForbidden();
});

test('media token response targets the configured external HTTPS origin without creating missing rooms', function (): void {
    config(['jitsi.origin' => 'https://meet.example.test:18443']);
    $attempt = createMediaAcceptedAttempt('media-token-origin.'.Str::uuid().'@example.test');
    $encounter = createMediaEncounter($attempt, $this->guidance);
    Http::preventStrayRequests();
    Http::fake(fn (ClientRequest $request) => Http::response([
        'nonce' => $request['nonce'],
        'protocol_version' => 1,
        'admission_hook_installed' => true,
        'admission_callback_reachable' => true,
        'admission_callback_status' => 200,
        'presence_callback_reachable' => true,
        'mandatory_lobby_installed' => true,
        'callbacks_verified_at' => now()->timestamp,
        'callbacks_age_seconds' => 0,
    ]));

    $this->actingAs($this->guidance)
        ->postJson(route('consultation.encounter.token', $encounter))
        ->assertOk()
        ->assertJsonPath('domain', 'meet.example.test:18443')
        ->assertJsonPath('room_name', $encounter->room_name)
        ->assertHeader('Cache-Control', 'no-store, private');

    $encounter->update(['room_name' => null]);
    $this->actingAs($this->guidance)
        ->postJson(route('consultation.encounter.token', $encounter))
        ->assertStatus(409);
    expect($encounter->fresh()->room_name)->toBeNull();
});

test('JWT claims bind audience encounter scope role and current generation', function (): void {
    $attempt = createMediaAcceptedAttempt('media.'.Str::uuid().'@example.test');
    $applicant = $attempt->identity->applicantAccount->user;
    $encounter = createMediaEncounter($attempt, $this->guidance);
    $tokens = app(JitsiTokenService::class);
    $token = $tokens->issue($encounter, $applicant, 'participant', now()->addMinutes(5));
    $participantId = $tokens->participantId($applicant);
    $media = app(JitsiMediaAdapter::class);

    expect($media->verifyAdmission($encounter->room_name, $participantId, $token))->toBeTrue()
        ->and($media->verifyAdmission($encounter->room_name.'-other', $participantId, $token))->toBeFalse();

    $applicantModerator = $tokens->issue($encounter, $applicant, 'moderator', now()->addMinutes(5));
    expect($media->verifyAdmission($encounter->room_name, $participantId, $applicantModerator))->toBeFalse();

    $encounter->update(['schedule_generation' => 5]);
    expect($media->verifyAdmission($encounter->room_name, $participantId, $token))->toBeFalse();

    $encounter->update(['schedule_generation' => 4]);
    ConsultationPolicy::query()->where('academic_cycle_id', $this->cycle->id)->update([
        'normal_method' => ConsultationPolicy::METHOD_F2F,
        'allow_online_exceptions' => false,
        'version' => 2,
    ]);
    expect($media->verifyAdmission($encounter->room_name, $participantId, $token))->toBeFalse();
});

test('only a verified host room join can unlock applicant admission and postjoin recording', function (): void {
    $attempt = createMediaAcceptedAttempt('media-presence.'.Str::uuid().'@example.test');
    $applicant = $attempt->identity->applicantAccount->user;
    $encounter = createMediaEncounter($attempt, $this->guidance);
    $tokens = app(JitsiTokenService::class);
    $hostToken = $tokens->issue($encounter, $this->guidance, 'moderator', now()->addMinutes(5));
    $applicantToken = $tokens->issue($encounter, $applicant, 'participant', now()->addMinutes(5));
    $headers = ['X-Jitsi-Admission-Secret' => str_repeat('a', 48)];
    $presence = fn (string $participantId, string $token, string $event = 'joined'): array => [
        'room_name' => $encounter->room_name,
        'participant_id' => $participantId,
        'token' => $token,
        'event' => $event,
        'schedule_generation' => 4,
    ];

    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($applicant), $applicantToken))
        ->assertForbidden();
    expect($encounter->fresh()->admitted_at)->toBeNull()
        ->and($encounter->fresh()->media_host_joined_at)->toBeNull();

    $hostJoin = $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($this->guidance), $hostToken));
    $hostJoin->assertOk()->assertJsonPath('accepted', true);
    expect($hostJoin->headers->get('Content-Length'))->toBe((string) strlen($hostJoin->getContent()));
    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_WAITING)
        ->and($encounter->fresh()->media_host_joined_at)->not->toBeNull()
        ->and($encounter->fresh()->admitted_at)->toBeNull();

    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($applicant), $applicantToken))
        ->assertOk()
        ->assertJsonPath('accepted', true);
    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_IN_PROGRESS)
        ->and($encounter->fresh()->admitted_at)->not->toBeNull();

    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($this->guidance), $hostToken, 'left'))
        ->assertOk();
    expect($encounter->fresh()->media_host_joined_at)->toBeNull();

    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($applicant), $applicantToken))
        ->assertForbidden();

    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($this->guidance), $hostToken))
        ->assertOk();
    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), $presence($tokens->participantId($applicant), $applicantToken))
        ->assertOk();
    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_IN_PROGRESS)
        ->and($encounter->fresh()->admitted_at)->not->toBeNull();
});

test('expired but correctly signed host token can only clear its current departure receipt', function (): void {
    $attempt = createMediaAcceptedAttempt('media-expired-left.'.Str::uuid().'@example.test');
    $encounter = createMediaEncounter($attempt, $this->guidance);
    $tokens = app(JitsiTokenService::class);
    $hostToken = $tokens->issue($encounter, $this->guidance, 'moderator', now()->addMinute());
    $participantId = $tokens->participantId($this->guidance);
    $headers = ['X-Jitsi-Admission-Secret' => str_repeat('a', 48)];
    $payload = [
        'room_name' => $encounter->room_name,
        'participant_id' => $participantId,
        'token' => $hostToken,
        'schedule_generation' => (int) $encounter->schedule_generation,
    ];

    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), [...$payload, 'event' => 'joined'])
        ->assertOk();
    expect($encounter->fresh()->media_host_joined_at)->not->toBeNull();

    $this->travel(2)->minutes();
    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), [...$payload, 'event' => 'joined'])
        ->assertForbidden();
    $this->withHeaders($headers)
        ->postJson(route('consultation.jitsi.presence'), [...$payload, 'event' => 'left'])
        ->assertOk()
        ->assertJsonPath('accepted', true);

    expect($encounter->fresh()->media_host_joined_at)->toBeNull();
});

test('eligible direct walk-in detail exposes current media readiness for online start', function (): void {
    app(OnlineConsultationDemoSeeder::class)->run();
    $attempt = AdmissionAttempt::query()
        ->whereHas('identity', fn ($query) => $query->where('email', 'online.walkin@nexiam.test'))
        ->firstOrFail();

    $this->actingAs($this->guidance)
        ->get(route('admission-support.show', $attempt))
        ->assertInertia(fn ($page) => $page
            ->where('online_consultation.visible', true)
            ->where('online_consultation.can_start', true)
            ->where('online_consultation.reason', null)
            ->where('online_consultation.exception_mode', false));
});
test('closure persists its fence and outcome while an unacknowledged room close can be retried', function (): void {
    $attempt = createMediaAcceptedAttempt('media-close.'.Str::uuid().'@example.test');
    $encounter = createMediaEncounter($attempt, $this->guidance, [
        'status' => ConsultationEncounter::STATUS_OUTCOME_RECORDED,
        'room_name' => 'consultation-close-retry',
        'schedule_generation' => 9,
    ]);
    $terminateCalls = 0;
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) use (&$terminateCalls) {
        if (str_ends_with($request->url(), '/health')) {
            return Http::response([
                'nonce' => $request['nonce'],
                'protocol_version' => 1,
                'admission_hook_installed' => true,
                'admission_callback_reachable' => true,
                'admission_callback_status' => 200,
                'presence_callback_reachable' => true,
                'mandatory_lobby_installed' => true,
                'callbacks_verified_at' => now()->timestamp,
                'callbacks_age_seconds' => 0,
            ]);
        }
        $terminateCalls++;
        if ($terminateCalls === 1) {
            return Http::response([
                'room_name' => $request['room_name'],
                'request_id' => 'wrong-request',
                'generation' => $request['generation'],
                'terminated' => true,
            ]);
        }

        return Http::response([
            'room_name' => $request['room_name'],
            'request_id' => $request['request_id'],
            'generation' => $request['generation'],
            'terminated' => true,
        ]);
    });

    $this->actingAs($this->guidance)
        ->post(route('consultation.encounter.close', $encounter))
        ->assertStatus(502)
        ->assertJsonPath('pending', true);

    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME)
        ->and($encounter->fresh()->schedule_generation)->toBe(10)
        ->and($encounter->fresh()->media_close_status)->toBe(ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING)
        ->and($encounter->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($this->guidance)
        ->post(route('consultation.encounter.close', $encounter))
        ->assertOk()
        ->assertJsonPath('status', ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME)
        ->assertJsonPath('media_close_status', ConsultationEncounter::MEDIA_CLOSE_STATUS_ACKNOWLEDGED);
    expect($terminateCalls)->toBe(2)
        ->and($encounter->fresh()->schedule_generation)->toBe(10)
        ->and($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME);
});

test('online decision workspace binds the admitted host encounter without physical print or ending media', function (): void {
    $this->withoutVite();
    app(OnlineConsultationDemoSeeder::class)->run();
    $attempt = AdmissionAttempt::query()
        ->whereHas('identity', fn ($query) => $query->where('email', 'online.walkin@nexiam.test'))
        ->firstOrFail();
    $document = ResultDocument::query()->findOrFail($attempt->current_result_document_id);
    $encounter = createMediaEncounter($attempt, $this->guidance, [
        'result_document_id' => $document->id,
        'result_document_version' => $document->version,
        'status' => ConsultationEncounter::STATUS_IN_PROGRESS,
        'admitted_at' => now(),
        'media_host_joined_at' => now(),
    ]);
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) {
        if (! str_ends_with($request->url(), '/health')) {
            return Http::response(['unavailable' => true], 503);
        }

        return Http::response([
            'nonce' => $request['nonce'],
            'protocol_version' => 1,
            'admission_hook_installed' => true,
            'admission_callback_reachable' => true,
            'admission_callback_status' => 200,
            'presence_callback_reachable' => true,
            'mandatory_lobby_installed' => true,
            'callbacks_verified_at' => now()->timestamp,
            'callbacks_age_seconds' => 0,
        ]);
    });
    $workspace = route('admission-support.show', ['attempt' => $attempt->id, 'encounter_id' => $encounter->id]);
    $this->actingAs($this->guidance)->get($workspace)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/ApplicantAdmissionSupport')
            ->where('online_encounter_id', $encounter->id));
    $otherCounselor = User::query()->where('email', 'online.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($otherCounselor)->get($workspace)->assertNotFound();
    $this->assertDatabaseMissing('result_document_print_receipts', ['admission_attempt_id' => $attempt->id]);
    $commandKey = (string) Str::uuid();
    $this->actingAs($this->guidance)
        ->from($workspace)
        ->post(route('consultation.decision', $attempt), [
            'decision_type' => 'defer',
            'notes' => 'Synthetic online consultation outcome.',
            'encounter_id' => $encounter->id,
            'idempotency_key' => $commandKey,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect($workspace);
    $this->assertDatabaseHas('consultation_decisions', [
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'defer',
        'idempotency_key' => $commandKey,
    ]);
    expect($encounter->fresh()->status)->toBe(ConsultationEncounter::STATUS_OUTCOME_RECORDED)
        ->and($encounter->fresh()->medium)->toBe(ConsultationEncounter::MEDIUM_ONLINE)
        ->and($encounter->fresh()->media_close_status)->toBe(ConsultationEncounter::MEDIA_CLOSE_STATUS_NONE)
        ->and(ConsultationEncounter::query()->where('admission_attempt_id', $attempt->id)->where('medium', ConsultationEncounter::MEDIUM_F2F)->exists())->toBeFalse();
    $this->get($workspace)->assertOk();
});
