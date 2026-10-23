<?php

use App\Admission\Support\ApplicantCapabilityGrants;
use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Gateway\AssistantToolGateway;
use App\Application\Capabilities\CapabilityKind;
use App\Application\Capabilities\CapabilityResult;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

function applicantGuidanceUser(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function applicantSetup(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function applicantLogin(array $result): void
{
    if (auth()->check()) {
        test()->post(route('logout'));
    }

    test()->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));
}

function applicantSession(User $guidance, string $code): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => now()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();

    return ExamSession::query()->where('code', $code)->firstOrFail();
}

function applicantOpenReservation(User $guidance, ExamSession $session): void
{
    test()->actingAs($guidance)
        ->post(route('exams.sessions.open-reservation', $session), [
            'expected_version' => $session->version,
        ])
        ->assertRedirect();

    expect($session->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION);
}

function applicantInvokeSlots(User $actor): CapabilityResult
{
    $actorType = $actor->applicantAccount()->exists() ? 'applicant' : 'staff';
    $institutionId = $actorType === 'staff'
        ? $actor->actorScope()->institutionId
        : $actor->applicantAccount?->identity?->institution_id;

    $context = new InvocationContext(
        actorId: (string) $actor->id,
        actorType: $actorType,
        institutionId: $institutionId,
        surfaceProfile: $actorType,
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'slots-test',
    );

    return app(AssistantToolGateway::class)->invoke(
        actor: $actor,
        context: $context,
        toolId: 'exams.slots.list@1',
        input: [],
    );
}

test('catalog includes exams.slots.list read row', function (): void {
    $catalog = app(AssistantToolCatalog::class);

    expect($catalog->get('exams.slots.list', 1))->not->toBeNull()
        ->and($catalog->get('exams.slots.list', 1)?->kind)->toBe(CapabilityKind::Read)
        ->and($catalog->get('exams.slots.list', 1)?->id())->toBe('exams.slots.list@1');
});

test('applicant login grants applicant slot capabilities', function (): void {
    seedE2Foundation();
    $result = applicantSetup('cap-login@example.test', 'CapLogin');
    applicantLogin($result);

    $user = User::query()->where('email', 'cap-login@example.test')->firstOrFail();

    expect($user->hasPermissionTo('applicant.exam.slots.view'))->toBeTrue()
        ->and($user->hasPermissionTo('applicant.exam.slot.reserve'))->toBeTrue();
});

test('kernel returns own open slots for applicant with open reservation', function (): void {
    seedE2Foundation();
    $guidance = applicantGuidanceUser();
    $result = applicantSetup('cap-slots@example.test', 'CapSlots');
    $session = applicantSession($guidance, 'EX-SLOTS-1');
    applicantOpenReservation($guidance, $session);

    applicantLogin($result);
    $user = User::query()->where('email', 'cap-slots@example.test')->firstOrFail();
    $resultCall = applicantInvokeSlots($user);

    expect($resultCall->ok)->toBeTrue()
        ->and($resultCall->data['mode'])->toBe('open')
        ->and($resultCall->data['slots'])->not->toBeEmpty()
        ->and($resultCall->data['slots'][0]['id'])->toBe($session->id)
        ->and($resultCall->data['slots'][0])->toHaveKeys([
            'id', 'code', 'start_time', 'end_time', 'room', 'capacity', 'remaining', 'full',
        ]);
});

test('kernel returns assigned seat after applicant reserves', function (): void {
    seedE2Foundation();
    $guidance = applicantGuidanceUser();
    $result = applicantSetup('cap-assigned@example.test', 'CapAssigned');
    $session = applicantSession($guidance, 'EX-ASSIGNED-1');
    applicantOpenReservation($guidance, $session);

    applicantLogin($result);
    $this->post(route('applicant.schedule.reserve'), [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => 'cap-assigned-'.$result['attempt']->id,
    ])->assertRedirect(route('applicant.schedule'));

    $user = User::query()->where('email', 'cap-assigned@example.test')->firstOrFail();
    $resultCall = applicantInvokeSlots($user);

    expect($resultCall->ok)->toBeTrue()
        ->and($resultCall->data['mode'])->toBe('assigned')
        ->and($resultCall->data['assignment']['session']['code'])->toBe($session->code);
});

test('kernel returns no slots when reservation is closed', function (): void {
    seedE2Foundation();
    $guidance = applicantGuidanceUser();
    $result = applicantSetup('cap-closed@example.test', 'CapClosed');
    $session = applicantSession($guidance, 'EX-CLOSED-1');
    applicantOpenReservation($guidance, $session);

    $this->actingAs($guidance)
        ->post(route('exams.sessions.close-reservation', $session), [
            'expected_version' => $session->fresh()->version,
        ])
        ->assertRedirect();

    applicantLogin($result);
    $user = User::query()->where('email', 'cap-closed@example.test')->firstOrFail();
    $resultCall = applicantInvokeSlots($user);

    expect($resultCall->ok)->toBeTrue()
        ->and($resultCall->data['mode'])->toBe('open')
        ->and($resultCall->data['slots'])->toBe([]);
});

test('staff without applicant capability is denied by kernel', function (): void {
    seedE2Foundation();
    $counselor = applicantGuidanceUser();

    $resultCall = applicantInvokeSlots($counselor);

    expect($resultCall->ok)->toBeFalse()
        ->and($resultCall->status)->toBe('denied')
        ->and($resultCall->reason)->toBe('capability_missing');
});

test('applicant without identity is denied by handler', function (): void {
    seedE2Foundation();
    $user = User::factory()->create(['email' => 'cap.noidentity@example.test']);
    ApplicantCapabilityGrants::sync($user);
    $this->actingAs($user);

    $resultCall = applicantInvokeSlots($user);

    expect($resultCall->status)->toBe('denied')
        ->and(in_array($resultCall->reason, ['no_applicant_identity', 'envelope_denied:blocked'], true))->toBeTrue();
});

test('applicant can never read another applicant slots (own scope)', function (): void {
    seedE2Foundation();
    $guidance = applicantGuidanceUser();
    $a = applicantSetup('cap-own-a@example.test', 'OwnA');
    $b = applicantSetup('cap-own-b@example.test', 'OwnB');
    $session = applicantSession($guidance, 'EX-OWN-1');
    applicantOpenReservation($guidance, $session);

    applicantLogin($b);
    $userB = User::query()->where('email', 'cap-own-b@example.test')->firstOrFail();

    // B's identity resolves only B's own attempt/slots — never A's data.
    $resultCall = applicantInvokeSlots($userB);

    expect($resultCall->ok)->toBeTrue()
        ->and($resultCall->data['mode'])->toBe('open')
        ->and(collect($resultCall->data['slots'])->pluck('id')->contains($session->id))->toBeTrue();
});

function applicantInvokeReserve(User $actor, array $input): CapabilityResult
{
    $serverConfirmed = (bool) ($input['execute'] ?? false);
    unset($input['execute']);

    $institutionId = $actor->applicantAccount?->identity?->institution_id;
    $context = new InvocationContext(
        actorId: (string) $actor->id,
        actorType: 'applicant',
        institutionId: $institutionId,
        surfaceProfile: 'applicant',
        mode: 'assistant',
        contextEpoch: 'epoch-1',
        purpose: 'test',
        correlationId: 'reserve-test',
        executionId: $serverConfirmed ? 'exec-reserve-test' : null,
        canonicalDigest: $serverConfirmed ? 'sha256-test-digest' : null,
    );

    return app(AssistantToolGateway::class)->invoke(
        actor: $actor,
        context: $context,
        toolId: 'exams.slot.reserve@1',
        input: $input,
        serverConfirmed: $serverConfirmed,
    );
}

test('catalog includes exams.slot.reserve propose row', function (): void {
    $catalog = app(AssistantToolCatalog::class);

    expect($catalog->get('exams.slot.reserve', 1))->not->toBeNull()
        ->and($catalog->get('exams.slot.reserve', 1)?->kind)->toBe(CapabilityKind::Propose)
        ->and($catalog->get('exams.slot.reserve', 1)?->id())->toBe('exams.slot.reserve@1');
});

test('propose reserves nothing; confirm reserves idempotently', function (): void {
    seedE2Foundation();
    $guidance = applicantGuidanceUser();
    $result = applicantSetup('cap-propose@example.test', 'CapPropose');
    $session = applicantSession($guidance, 'EX-PROP-1');
    applicantOpenReservation($guidance, $session);

    applicantLogin($result);
    $user = User::query()->where('email', 'cap-propose@example.test')->firstOrFail();

    $key = 'cap-propose-'.$result['attempt']->id.'-'.$session->id;

    // Propose → no write.
    $propose = applicantInvokeReserve($user, [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => $key,
    ]);

    expect($propose->ok)->toBeTrue()
        ->and($propose->data['mode'])->toBe('propose')
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(0);

    // Confirm (execute) → reserves once.
    $execute = applicantInvokeReserve($user, [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => $key,
        'execute' => true,
    ]);

    expect($execute->ok)->toBeTrue()
        ->and($execute->data['mode'])->toBe('execute')
        ->and($execute->data['assignment_id'])->toBeInt()
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1);

    // Same key again → same assignment (idempotent), no duplicate.
    $again = applicantInvokeReserve($user, [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'idempotency_key' => $key,
        'execute' => true,
    ]);

    expect($again->ok)->toBeTrue()
        ->and($again->data['assignment_id'])->toBe($execute->data['assignment_id'])
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(1);
});

test('reserve denies an attempt that is not the actor own', function (): void {
    seedE2Foundation();
    $guidance = applicantGuidanceUser();
    $victim = applicantSetup('cap-victim@example.test', 'CapVictim');
    $session = applicantSession($guidance, 'EX-VICTIM-1');
    applicantOpenReservation($guidance, $session);

    // Attacker: an applicant with NO own attempt cannot reserve victim's attempt.
    $attacker = User::factory()->create(['email' => 'cap-attacker@example.test']);
    ApplicantCapabilityGrants::sync($attacker);
    $this->actingAs($attacker);

    $resultCall = applicantInvokeReserve($attacker, [
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $victim['attempt']->id,
        'execute' => true,
    ]);
    expect($resultCall->status)->toBe('denied')
        ->and(in_array($resultCall->reason, ['not_own_attempt', 'envelope_denied:blocked'], true))->toBeTrue();
});
