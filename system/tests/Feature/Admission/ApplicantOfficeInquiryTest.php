<?php

use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Actions\MarkApplicantOfficeInquiryRead;
use App\Admission\Actions\ResolveApplicantOfficeInquiry;
use App\Admission\Actions\SendApplicantOfficeInquiryMessage;
use App\Admission\Queries\ApplicantOfficeInquiryQuery;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Authorization\CapabilityCatalog;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Operation;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @return array{applicant: User, registrar: User, guidance: User, attempt: AdmissionAttempt} */
function officeInquiryFixture(): array
{
    seedE2Foundation();
    (new ApplicantOfficeInquiryDemoSeeder)->run();

    session()->start();

    return [
        'applicant' => User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::APPLICANT_EMAIL)->firstOrFail(),
        'registrar' => User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::REGISTRAR_EMAIL)->firstOrFail(),
        'guidance' => User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail(),
        'attempt' => AdmissionAttempt::query()->where('reference_code', ApplicantOfficeInquiryDemoSeeder::REFERENCE_CODE)->firstOrFail(),
    ];
}

test('applicant inquiry index preserves Inertia transport, safe unavailability, opaque ownership denial, and navigation without Xiam', function (): void {
    ['applicant' => $applicant] = officeInquiryFixture();
    config(['xiam.enabled' => false]);

    $created = CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Synthetic owned question', 'Synthetic owned message', (string) Str::uuid());
    $inquiryId = (int) $created['inquiry_id'];
    $unregistered = User::factory()->create();
    $unavailable = $this->actingAs($unregistered)
        ->withHeader('Accept', 'text/html')
        ->get(route('applicant.inquiries.index'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Inquiries/Index')
            ->where('unavailable.message', 'Office inquiries are unavailable for this account.')
            ->where('inquiries', [])
            ->where('destinations', [])
        );

    $inertiaUnavailable = $this->actingAs($unregistered)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('applicant.inquiries.index'))
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Applicant/Inquiries/Index')
        ->assertJsonPath('props.unavailable.available', false)
        ->assertJsonPath('props.inquiries', [])
        ->assertJsonPath('props.destinations', [])
        ->assertJsonPath('props.urls.create', null)
        ->assertJsonPath('props.principal_key', '');

    $this->flushHeaders();

    $redirect = $this->actingAs($applicant)
        ->get(route('applicant.inquiries.index'))
        ->assertRedirect(route('applicant.admission-support', ['group' => 'offices']));

    $preservedRedirect = $this->actingAs($applicant)
        ->get(route('applicant.inquiries.index', ['search' => 'Synthetic question', 'before_id' => '123']))
        ->assertRedirect(route('applicant.admission-support', [
            'group' => 'offices',
            'office_search' => 'Synthetic question',
            'office_cursor' => '123',
        ]));

    $canonicalShow = $this->actingAs($applicant)
        ->get(route('applicant.inquiries.show', ['inquiry' => $inquiryId]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Inquiries/Show')
            ->where('return_url', route('applicant.admission-support', ['group' => 'offices'], false))
            ->where('urls.return', route('applicant.admission-support', ['group' => 'offices'], false))
            ->where('initial.thread.id', (string) $inquiryId)
        );

    $customReturn = '/applicant/admission-support?group=offices&office_search=test&office_cursor=cur456';
    $customShow = $this->actingAs($applicant)
        ->get(route('applicant.inquiries.show', ['inquiry' => $inquiryId, 'return_url' => $customReturn]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Inquiries/Show')
            ->where('return_url', $customReturn)
            ->where('urls.return', $customReturn)
        );
    $denied = $this->actingAs($unregistered)
        ->get(route('applicant.inquiries.show', ['inquiry' => $inquiryId]))
        ->assertNotFound()
        ->assertExactJson(['code' => 'not_found', 'message' => 'Inquiry not found.']);

    foreach ([$unavailable, $inertiaUnavailable, $redirect, $preservedRedirect, $canonicalShow, $customShow, $denied] as $response) {
        expect($response->headers->hasCacheControlDirective('private'))->toBeTrue()
            ->and($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    }
});

test('ordinary office inquiry commands replay exactly, observe reads monotonically and close without changing admission state', function (): void {
    $fixture = officeInquiryFixture();
    ['applicant' => $applicant, 'registrar' => $registrar, 'attempt' => $attempt] = $fixture;
    $admissionBefore = $attempt->getAttributes();
    $quotasBefore = ProgramQuota::query()->orderBy('id')->get()->map->getAttributes()->all();
    $createKey = (string) Str::uuid();
    $created = CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Synthetic question', 'Synthetic first message', $createKey);
    $inquiryId = (int) $created['inquiry_id'];
    $query = app(ApplicantOfficeInquiryQuery::class);
    $this->actingAs($registrar)->withSession(['office_inquiry_test' => true]);
    $firstObservation = $query->snapshot($registrar, $inquiryId);
    $selected = json_decode($firstObservation['attempt_key'], true, 8, JSON_THROW_ON_ERROR);
    expect($selected)->toBe([
        'office',
        (string) $attempt->institution_id,
        (string) $inquiryId,
        (string) $attempt->academicCycle->code,
        'registrar',
    ])
        ->and($firstObservation['thread']['applicant']['display_name'])->toBe($attempt->identity->fullName())
        ->and($firstObservation['thread']['applicant']['reference_code'])->toBe($attempt->reference_code)
        ->and(array_keys($firstObservation['thread']['applicant']))->toBe(['display_name', 'reference_code']);
    $staffList = $query->listFor($registrar);
    $staffRow = collect($staffList['inquiries'])->firstWhere('id', (string) $inquiryId);
    expect($staffRow['applicant'])->toBe([
        'display_name' => $attempt->identity->fullName(),
        'reference_code' => $attempt->reference_code,
    ]);
    $this->actingAs($applicant);
    $applicantSnapshot = $query->snapshot($applicant, $inquiryId);
    expect($applicantSnapshot['attempt_key'])->toBe($firstObservation['attempt_key'])
        ->and(isset($applicantSnapshot['thread']['applicant']))->toBeFalse();
    $applicantList = $query->listFor($applicant);
    $applicantRow = collect($applicantList['inquiries'])->firstWhere('id', (string) $inquiryId);
    expect(isset($applicantRow['applicant']))->toBeFalse();
    $this->actingAs($registrar)->withSession(['office_inquiry_test' => true]);
    $replyKey = (string) Str::uuid();
    $reply = SendApplicantOfficeInquiryMessage::handle($registrar, $inquiryId, 'Synthetic staff reply', $replyKey);
    $this->actingAs($applicant);
    $latest = $query->snapshot($applicant, $inquiryId);
    expect($latest['latest_sequence'])->toBe('2')
        ->and($latest['unread_count'])->toBe(1)
        ->and($latest['messages'][1]['body'])->toBe('Synthetic staff reply');
    $read = MarkApplicantOfficeInquiryRead::handle($applicant, $inquiryId, '2', $latest['observation_token']);
    expect($read['own_read_sequence'])->toBe('2')->and($read['unread_count'])->toBe(0);
    expect(fn () => MarkApplicantOfficeInquiryRead::handle($applicant, $inquiryId, '1', $firstObservation['observation_token']))
        ->toThrow(ValidationException::class);
    $this->actingAs($registrar);
    $newObservation = $query->snapshot($registrar, $inquiryId);
    MarkApplicantOfficeInquiryRead::handle($registrar, $inquiryId, '2', $newObservation['observation_token']);
    $olderRead = MarkApplicantOfficeInquiryRead::handle($registrar, $inquiryId, '1', $firstObservation['observation_token']);
    expect($olderRead['own_read_sequence'])->toBe('2');

    $resolveKey = (string) Str::uuid();
    $resolved = ResolveApplicantOfficeInquiry::handle($registrar, $inquiryId, $resolveKey);
    $counts = [ApplicantOfficeInquiryMessage::count(), AuditEvent::count(), NotificationDelivery::count(), Operation::count()];
    $createReplay = CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Synthetic question', 'Synthetic first message', $createKey);
    $replyReplay = SendApplicantOfficeInquiryMessage::handle($registrar, $inquiryId, 'Synthetic staff reply', $replyKey);
    expect($createReplay['replayed'])->toBeTrue()->and($createReplay['receipt_id'])->toBe($created['receipt_id'])
        ->and($replyReplay['message_id'])->toBe($reply['message_id'])->and($replyReplay['replayed'])->toBeTrue()
        ->and(ResolveApplicantOfficeInquiry::handle($registrar, $inquiryId, $resolveKey))->toBe($resolved);
    expect(fn () => SendApplicantOfficeInquiryMessage::handle($registrar, $inquiryId, 'Changed reply', $replyKey))
        ->toThrow(RuntimeException::class, 'office_inquiry_conflict');
    expect(fn () => SendApplicantOfficeInquiryMessage::handle($applicant, $inquiryId, 'New reply after closure', (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'office_inquiry_closed');
    expect([ApplicantOfficeInquiryMessage::count(), AuditEvent::count(), NotificationDelivery::count(), Operation::count()])->toBe($counts)
        ->and($attempt->refresh()->getAttributes())->toBe($admissionBefore)
        ->and($attempt->programPools()->count())->toBe(0)
        ->and($attempt->programSelections()->count())->toBe(0)
        ->and($attempt->examAssignments()->count())->toBe(0)
        ->and($attempt->consultationDecisions()->count())->toBe(0)
        ->and($attempt->enrollmentStamps()->count())->toBe(0)
        ->and(ProgramQuota::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($quotasBefore);
    expect($query->snapshot($applicant, $inquiryId)['thread']['can_send'])->toBeFalse();
});

test('foreign office, other applicant, changed cycle, revoked markers and production cannot access or mutate inquiries', function (): void {
    ['applicant' => $applicant, 'registrar' => $registrar, 'guidance' => $guidance, 'attempt' => $attempt] = officeInquiryFixture();
    $key = (string) Str::uuid();
    $created = CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Scope boundary', 'Synthetic own-office message', $key);
    $inquiryId = (int) $created['inquiry_id'];
    $query = app(ApplicantOfficeInquiryQuery::class);
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $attempt->institution_id, 'normalized_key' => 'SYN-INQUIRY-OTHER-'.Str::uuid(),
        'first_name' => 'Synthetic', 'last_name' => 'Other Inquiry Applicant', 'birthdate' => '2003-01-01', 'sex' => 'female',
    ]);
    $other = User::factory()->create();
    ApplicantAccount::query()->create(['applicant_identity_id' => $identity->id, 'user_id' => $other->id, 'setup_completed_at' => now()]);
    AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id, 'institution_id' => $attempt->institution_id,
        'academic_cycle_id' => $attempt->academic_cycle_id, 'application_window_id' => $attempt->application_window_id,
        'reference_code' => 'SYN-INQUIRY-OTHER-'.Str::uuid(), 'status' => AdmissionAttempt::STATUS_PENDING,
        'applicant_type' => 'new', 'privacy_policy_id' => $attempt->privacy_policy_id,
    ]);
    ApplicantOfficeInquiryDemoActor::query()->create([
        'user_id' => $other->id, 'institution_id' => $attempt->institution_id, 'actor_type' => 'applicant',
        'namespace' => ApplicantOfficeInquiryDemoActor::NAMESPACE,
    ]);
    $other->givePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    $counts = [ApplicantOfficeInquiry::count(), ApplicantOfficeInquiryMessage::count(), Operation::count(), NotificationDelivery::count()];
    foreach ([$guidance, $other] as $denied) {
        expect(fn () => $query->snapshot($denied, $inquiryId))->toThrow(RuntimeException::class, 'office_inquiry_not_found');
        expect(fn () => SendApplicantOfficeInquiryMessage::handle($denied, $inquiryId, 'Forbidden', (string) Str::uuid()))
            ->toThrow(RuntimeException::class, 'office_inquiry_not_found');
        expect(fn () => ResolveApplicantOfficeInquiry::handle($denied, $inquiryId, (string) Str::uuid()))
            ->toThrow(RuntimeException::class, 'office_inquiry_not_found');
    }
    $registrar->staffProfile->update(['active_cycle_code' => 'SYN-OTHER-CYCLE']);
    expect(fn () => $query->snapshot($registrar, $inquiryId))->toThrow(RuntimeException::class, 'office_inquiry_not_found');
    $registrar->staffProfile->update(['active_cycle_code' => 'SYN-CYCLE-2026']);
    $registrar->syncRoles([]);
    $registrar->revokePermissionTo(CapabilityCatalog::OFFICE_INQUIRY_HANDLE);
    ApplicantOfficeInquiryDemoActor::query()->where('user_id', $registrar->id)->delete();
    expect(fn () => $query->snapshot($registrar, $inquiryId))->toThrow(RuntimeException::class, 'office_inquiry_not_found');
    ApplicantOfficeInquiryDemoActor::query()->where('user_id', $applicant->id)->delete();
    $applicant->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    expect(fn () => CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Scope boundary', 'Synthetic own-office message', $key))
        ->toThrow(RuntimeException::class, 'office_inquiry_not_found');
    expect(app(ApplicantOfficeInquiryAccess::class)->availability($applicant))->toBe([
        'available' => false,
        'reason' => 'unavailable',
        'message' => 'Office inquiries are unavailable for this account.',
    ]);
    $environment = app()->environment();
    try {
        app()->instance('env', 'production');
        expect(fn () => $query->options($guidance))->toThrow(RuntimeException::class, 'office_inquiry_not_found');
        expect(fn () => CreateApplicantOfficeInquiry::handle($other, 'guidance', 'Denied production', 'Synthetic only', (string) Str::uuid()))
            ->toThrow(RuntimeException::class, 'office_inquiry_not_found');
        expect(fn () => (new ApplicantOfficeInquiryDemoSeeder)->run())->toThrow(RuntimeException::class, 'Office inquiry demo requires local/testing console execution.');
    } finally {
        app()->instance('env', $environment);
    }
    expect([ApplicantOfficeInquiry::count(), ApplicantOfficeInquiryMessage::count(), Operation::count(), NotificationDelivery::count()])->toBe($counts);
});

test('synthetic demo reruns preserve workflow and credentials while message storage, receipts, audit and notices minimize personal data', function (): void {
    ['applicant' => $applicant, 'registrar' => $registrar, 'attempt' => $attempt] = officeInquiryFixture();
    $body = 'Synthetic private sentinel: private-inquiry@example.test DOB 2004-05-15 phone 09998887777';
    $subject = 'Synthetic private subject sentinel';
    $created = CreateApplicantOfficeInquiry::handle($applicant, 'registrar', $subject, $body, (string) Str::uuid());
    $reply = SendApplicantOfficeInquiryMessage::handle($registrar, (int) $created['inquiry_id'], $body, (string) Str::uuid());
    $stored = ApplicantOfficeInquiryMessage::query()->findOrFail($created['message_id']);
    expect($stored->body)->toBe($body)
        ->and($stored->getRawOriginal('body'))->not->toContain('private-inquiry@example.test')
        ->and(Crypt::decryptString($stored->getRawOriginal('body')))->toBe($body);
    $ledger = Operation::query()->whereIn('id', [$created['receipt_id'], $reply['receipt_id']])->get();
    expect($ledger)->toHaveCount(2);
    foreach ($ledger as $operation) {
        expect($operation->payload['body_digest'])->toBe(hash('sha256', $body))
            ->and($operation->payload)->not->toHaveKeys(['body', 'subject', 'transcript']);
    }
    $deliveries = NotificationDelivery::query()->where('event_type', 'admission.office_inquiry_message.v1')
        ->where('payload->inquiry_id', $created['inquiry_id'])->get();
    expect($deliveries)->toHaveCount(5)
        ->and($deliveries->pluck('recipient_type')->all())->toContain(User::class, ApplicantIdentity::class);
    $safeSurfaces = json_encode([
        $ledger->pluck('payload')->all(), $created, $reply, $deliveries->pluck('payload')->all(),
        AuditEvent::query()->where('event_type', 'like', '%office_inquiry%')->pluck('payload')->all(),
    ], JSON_THROW_ON_ERROR);
    expect($safeSurfaces)->not->toContain($body, $subject, 'private-inquiry@example.test', '09998887777', ApplicantOfficeInquiryDemoSeeder::ACCESS_KEY);

    $open = ApplicantOfficeInquiry::query()->where('admission_attempt_id', $attempt->id)->where('status', 'open')->where('id', '!=', $created['inquiry_id'])->firstOrFail();
    ResolveApplicantOfficeInquiry::handle($registrar, $open->id, (string) Str::uuid());
    $password = $registrar->getRawOriginal('password');
    $authState = StaffAuthenticationState::query()->where('user_id', $registrar->id)->firstOrFail();
    $authState->update(['mfa_required' => true]);
    $authBefore = $authState->refresh()->getAttributes();
    $counts = [User::count(), ApplicantOfficeInquiry::count(), ApplicantOfficeInquiryMessage::count(), Operation::count(), NotificationDelivery::count(), AuditEvent::count()];
    (new ApplicantOfficeInquiryDemoSeeder)->run();
    expect([User::count(), ApplicantOfficeInquiry::count(), ApplicantOfficeInquiryMessage::count(), Operation::count(), NotificationDelivery::count(), AuditEvent::count()])->toBe($counts)
        ->and($registrar->refresh()->getRawOriginal('password'))->toBe($password)
        ->and($authState->refresh()->getAttributes())->toBe($authBefore)
        ->and($open->refresh()->status)->toBe('closed');
    ApplicantOfficeInquiryDemoActor::query()->where('user_id', $registrar->id)->delete();
    expect(fn () => (new ApplicantOfficeInquiryDemoSeeder)->run())->toThrow(RuntimeException::class, 'unowned account');
    expect(User::count())->toBe($counts[0]);
});

test('office inquiry search is subject-only, literal, scoped, and preserves bounded cursor pagination', function (): void {
    ['applicant' => $applicant, 'registrar' => $registrar] = officeInquiryFixture();
    $query = app(ApplicantOfficeInquiryQuery::class);

    for ($index = 0; $index < 21; $index++) {
        CreateApplicantOfficeInquiry::handle(
            $applicant,
            'registrar',
            'Indexed question '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'body-only-needle',
            (string) Str::uuid(),
        );
    }
    CreateApplicantOfficeInquiry::handle(
        $applicant,
        'registrar',
        'Literal 100% indexed question',
        'unrelated body',
        (string) Str::uuid(),
    );

    $firstPage = $query->listFor($applicant, null, ' Indexed ');
    expect($firstPage['inquiries'])->toHaveCount(20)
        ->and($firstPage['next_cursor'])->toBeString();
    $secondPage = $query->listFor($applicant, $firstPage['next_cursor'], 'Indexed');
    expect($secondPage['inquiries'])->toHaveCount(2)
        ->and($secondPage['next_cursor'])->toBeNull()
        ->and(collect([...$firstPage['inquiries'], ...$secondPage['inquiries']])->pluck('subject')->every(
            fn (string $subject): bool => str_contains(mb_strtolower($subject), 'indexed'),
        ))->toBeTrue();
    expect($query->listFor($applicant, null, 'body-only-needle')['inquiries'])->toBe([])
        ->and($query->listFor($applicant, null, '%')['inquiries'])->toHaveCount(1);

    expect(fn () => $query->listFor($applicant, $firstPage['next_cursor'], 'different search'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $query->listFor($registrar, $firstPage['next_cursor'], 'Indexed'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $query->listFor($applicant, 'malformed-cursor', 'Indexed'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $query->listFor($applicant, null, str_repeat('x', 121)))
        ->toThrow(ValidationException::class);
});

test('office inquiry summary activity uses the newest actual message timestamp', function (): void {
    ['applicant' => $applicant, 'registrar' => $registrar] = officeInquiryFixture();
    $created = CreateApplicantOfficeInquiry::handle(
        $applicant,
        'registrar',
        'Activity timestamp question',
        'First synthetic message',
        (string) Str::uuid(),
    );
    $firstMessageId = (int) $created['message_id'];
    SendApplicantOfficeInquiryMessage::handle(
        $registrar,
        (int) $created['inquiry_id'],
        'Second synthetic message',
        (string) Str::uuid(),
    );
    ApplicantOfficeInquiryMessage::query()->whereKey($firstMessageId)->update(['created_at' => '2030-01-02 03:04:05']);
    ApplicantOfficeInquiryMessage::query()->where('applicant_office_inquiry_id', $created['inquiry_id'])
        ->where('sequence', 2)->update(['created_at' => '2025-01-02 03:04:05']);
    ApplicantOfficeInquiry::query()->whereKey($created['inquiry_id'])->update(['updated_at' => '2035-01-02 03:04:05']);

    $summary = collect(app(ApplicantOfficeInquiryQuery::class)->listFor($applicant)['inquiries'])
        ->firstWhere('id', (string) $created['inquiry_id']);

    expect($summary['latest_message_at'])->toBe('2025-01-02T03:04:05.000000Z')
        ->and($summary['updated_at'])->toBe($summary['latest_message_at']);
});

test('office inquiry summary activity follows a send interleaved after candidate selection', function (): void {
    ['applicant' => $applicant, 'registrar' => $registrar] = officeInquiryFixture();
    $created = CreateApplicantOfficeInquiry::handle(
        $applicant,
        'registrar',
        'Interleaved activity question',
        'Initial message',
        (string) Str::uuid(),
    );
    $inquiryId = (int) $created['inquiry_id'];
    ApplicantOfficeInquiryMessage::query()->whereKey($created['message_id'])
        ->update(['created_at' => '2020-01-02 03:04:05']);

    $interleaved = false;
    DB::listen(function (QueryExecuted $query) use (&$interleaved, $registrar, $inquiryId): void {
        if ($interleaved || ! str_contains(strtolower($query->sql), 'applicant_office_inquiries')
            || ! str_contains(strtolower($query->sql), 'limit 21')) {
            return;
        }

        $interleaved = true;
        SendApplicantOfficeInquiryMessage::handle(
            $registrar,
            $inquiryId,
            'Message committed between candidate selection and row locking',
            (string) Str::uuid(),
        );
    });

    $summary = collect(app(ApplicantOfficeInquiryQuery::class)->listFor($applicant)['inquiries'])
        ->firstWhere('id', (string) $inquiryId);
    $highestSequenceMessage = ApplicantOfficeInquiryMessage::query()
        ->where('applicant_office_inquiry_id', $inquiryId)->orderByDesc('sequence')->firstOrFail();

    expect($interleaved)->toBeTrue()
        ->and($summary['latest_sequence'])->toBe((string) $highestSequenceMessage->sequence)
        ->and($summary['latest_message_at'])->toBe($highestSequenceMessage->created_at->toISOString())
        ->and($summary['unread_count'])->toBe(1);
});

test('standard guidance and registrar roles receive office inquiries, messaging badges, and audit-free partial reloads', function (): void {
    ['applicant' => $applicant] = officeInquiryFixture();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    expect(ApplicantOfficeInquiryDemoActor::query()->whereIn('user_id', [$guidance->id, $registrar->id])->exists())->toBeFalse();

    $createdByOffice = [];
    foreach ([
        ['office' => 'guidance', 'subject' => 'Standard guidance office question'],
        ['office' => 'registrar', 'subject' => 'Standard registrar office question'],
    ] as $recipient) {
        $created = CreateApplicantOfficeInquiry::handle(
            $applicant,
            $recipient['office'],
            $recipient['subject'],
            'Synthetic applicant inquiry message',
            (string) Str::uuid(),
        );
        $createdByOffice[$recipient['office']] = [...$created, 'subject' => $recipient['subject']];
    }

    foreach ([[$guidance, 'guidance'], [$registrar, 'registrar']] as [$staff, $office]) {
        $delivery = NotificationDelivery::query()
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->where('event_type', 'admission.office_inquiry_message.v1')
            ->where('recipient_type', User::class)
            ->where('recipient_id', $staff->id)
            ->where('payload->inquiry_id', (string) $createdByOffice[$office]['inquiry_id'])
            ->firstOrFail();
        $this->actingAs($staff)
            ->get(route('staff.inquiries.index', ['group' => 'offices']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admission/Inquiries/Index')
                ->where('group', 'offices')
                ->where('inquiries.0.subject', $createdByOffice[$office]['subject'])
            );

        $viewAuditCount = AuditEvent::query()
            ->whereIn('event_type', ['office_inquiry.list_viewed', 'office_inquiry.messages_viewed'])
            ->count();
        $partial = $this->actingAs($staff)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Data' => 'notifications,navigation_badges',
                'X-Inertia-Partial-Component' => 'Admission/Inquiries/Index',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get(route('staff.inquiries.index', ['group' => 'offices']))
            ->assertOk()
            ->assertHeader('X-Inertia', 'true');

        $notificationIds = collect($partial->json('props.notifications.recent'))->pluck('id');
        $badge = $partial->json('props.navigation_badges');
        expect($notificationIds)->toContain($delivery->id)
            ->and($partial->json('props.notifications.unread_count'))->toBeGreaterThan(0)
            ->and($badge['items']['messaging']['count'])->toBeGreaterThan(0)
            ->and($badge['groups']['messaging']['offices'])->toBeGreaterThan(0)
            ->and(AuditEvent::query()
                ->whereIn('event_type', ['office_inquiry.list_viewed', 'office_inquiry.messages_viewed'])
                ->count())->toBe($viewAuditCount);

        $this->flushHeaders();
    }
});
