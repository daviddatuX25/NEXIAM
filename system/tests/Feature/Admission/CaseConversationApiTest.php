<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Support\ApplicantCapabilityGrants;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

/**
 * @return array{
 *     attempt: AdmissionAttempt,
 *     applicant: User,
 *     guidance: User,
 *     phUser: User,
 *     program: Program
 * }
 */
function createCaseConversationFixture(TestCase $test, string $email): array
{
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-'.md5($email));
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'selection-'.md5($email));
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $phUser->staffProfile->update(['program_codes' => [$program->code]]);
    $phUser->refresh();

    return compact('attempt', 'applicant', 'guidance', 'phUser', 'program');
}

/**
 * @param  array{attempt:AdmissionAttempt,applicant:User,guidance:User,phUser:User,program:Program}  $fixture
 */
function openCaseApiApplicantThread(array $fixture, string $subject, string $body, ?string $idempotencyKey = null): RecordThread
{
    return app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        $subject,
        $body,
        $fixture['phUser'],
        $idempotencyKey ?? (string) Str::uuid(),
    );
}

test('GET case-conversations.show returns JSON snapshot with valid thread details, messages, page cursors, unread count, observation token, and private no-store cache headers', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-show@example.test');
    $phUser = $fixture['phUser'];
    $applicant = $fixture['applicant'];

    // The assigned Program Head opens the support thread with its first message.
    $thread = openCaseApiApplicantThread(
        $fixture,
        'Support Discussion on Credentials',
        'Please submit Form 137.',
        '11111111-1111-4111-8111-111111111001',
    );

    $sendMessageAction = app(SendAdmissionSupportMessage::class);
    $msg1 = RecordThreadMessage::query()->where('record_thread_id', $thread->id)->where('sequence', 1)->firstOrFail();
    $msg2 = $sendMessageAction->handle($thread, $applicant, 'Uploaded my Form 137.', '22222222-2222-4222-8222-222222222222');
    $msg3 = $sendMessageAction->handle($thread, $applicant, 'Also uploaded Certificate of Good Moral.', '33333333-3333-4333-8333-333333333333');

    // PH views the conversation
    $response = actingAsMessagingActor($this, $phUser)->getJson(route('case-conversations.show', $thread));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    $data = $response->json();

    // Verify thread details
    expect($data['thread']['id'])->toBe((string) $thread->id)
        ->and($data['thread']['subject'])->toBe('Support Discussion on Credentials')
        ->and($data['thread']['thread_type'])->toBe(RecordThread::TYPE_APPLICANT_SUPPORT)
        ->and($data['thread']['program_code'])->toBe('BSN')
        ->and($data['thread']['status'])->toBe(RecordThread::STATUS_OPEN)
        ->and($data['thread']['can_read'])->toBeTrue()
        ->and($data['thread']['can_send'])->toBeTrue();

    // Verify messages list
    expect($data['messages'])->toHaveCount(3)
        ->and($data['messages'][0]['id'])->toBe((string) $msg1->id)
        ->and($data['messages'][0]['sequence'])->toBe('1')
        ->and($data['messages'][0]['sender']['is_self'])->toBeTrue()
        ->and($data['messages'][0]['body'])->toBe('Please submit Form 137.')
        ->and($data['messages'][1]['id'])->toBe((string) $msg2->id)
        ->and($data['messages'][1]['sequence'])->toBe('2')
        ->and($data['messages'][1]['sender']['is_self'])->toBeFalse()
        ->and($data['messages'][1]['body'])->toBe('Uploaded my Form 137.')
        ->and($data['messages'][2]['id'])->toBe((string) $msg3->id)
        ->and($data['messages'][2]['sequence'])->toBe('3')
        ->and($data['messages'][2]['sender']['is_self'])->toBeFalse()
        ->and($data['messages'][2]['body'])->toBe('Also uploaded Certificate of Good Moral.');

    // Verify page cursors
    expect($data['page']['oldest_sequence'])->toBe('1')
        ->and($data['page']['newest_sequence'])->toBe('3')
        ->and($data['page']['has_older'])->toBeFalse()
        ->and($data['page']['has_more_after'])->toBeFalse();

    // Verify sequences and unread count for PH (messages 2 and 3 sent by applicant)
    expect($data['latest_sequence'])->toBe('3')
        ->and($data['own_read_sequence'])->toBe('0')
        ->and($data['unread_count'])->toBe(2);

    expect($data['observation_token'])->toBeString()->not->toBeEmpty()
        ->and($data['as_of'])->toBeString()->not->toBeEmpty();

    // Verify cursor pagination with after_sequence
    $pagedResponse = actingAsMessagingActor($this, $phUser)->getJson(route('case-conversations.show', [
        'thread' => $thread,
        'after_sequence' => '1',
    ]));

    $pagedResponse->assertOk();
    $pagedData = $pagedResponse->json();
    expect($pagedData['messages'])->toHaveCount(2)
        ->and($pagedData['page']['oldest_sequence'])->toBe('2')
        ->and($pagedData['page']['newest_sequence'])->toBe('3')
        ->and($pagedData['page']['has_older'])->toBeTrue()
        ->and($pagedData['page']['has_more_after'])->toBeFalse();
});

test('unauthorized user requesting GET case-conversations.show gets 404 without leaking existence', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-authz@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Applicant Support Thread', 'First applicant-visible message.');

    // 1. Staff from an unrelated college (Dean CCIT) has RECORD_THREAD_PARTICIPATE capability but cannot read BSN thread
    $unrelatedStaff = User::query()->where('email', 'dean.ccit@nexiam.test')->firstOrFail();
    actingAsMessagingActor($this, $unrelatedStaff)
        ->getJson(route('case-conversations.show', $thread))
        ->assertNotFound()
        ->assertJson(['code' => 'not_found', 'message' => 'Conversation not found.'])
        ->assertHeader('Cache-Control', 'no-store, private');

    // 2. Applicant of this attempt cannot read internal staff-guidance case threads
    $internalThread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $fixture['attempt'],
        $fixture['program']->id,
        'Internal Staff Only',
        'A private staff-only discussion.',
        $fixture['phUser'],
        (string) Str::uuid(),
    );
    actingAsMessagingActor($this, $fixture['applicant'])
        ->getJson(route('case-conversations.show', $internalThread))
        ->assertNotFound();

    // 3. Outsider applicant from a different identity
    $outsiderIdentity = ApplicantIdentity::query()->create([
        'institution_id' => $fixture['attempt']->institution_id,
        'normalized_key' => 'OUTSIDER-'.Str::uuid(),
        'first_name' => 'Outsider',
        'last_name' => 'Applicant',
        'birthdate' => '2001-01-01',
        'sex' => 'male',
    ]);
    $outsiderUser = User::factory()->create([
        'email' => 'outsider-applicant-'.Str::uuid().'@example.test',
    ]);
    ApplicantAccount::query()->create([
        'applicant_identity_id' => $outsiderIdentity->id,
        'user_id' => $outsiderUser->id,
    ]);
    ApplicantCapabilityGrants::sync($outsiderUser);

    actingAsMessagingActor($this, $outsiderUser)
        ->getJson(route('case-conversations.show', $thread))
        ->assertNotFound();
});

test('unknown case thread IDs return opaque private no-store JSON errors on snapshot, send, and read', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-unknown@example.test');
    $actor = actingAsMessagingActor($this, $fixture['phUser']);
    $threadId = '999999999';

    $actor->getJson(route('case-conversations.show', ['thread' => $threadId]))
        ->assertNotFound()
        ->assertJson(['code' => 'not_found', 'message' => 'Conversation not found.'])
        ->assertHeader('Cache-Control', 'no-store, private');
    $actor->postJson(route('case-conversations.messages.store', ['thread' => $threadId]), [
        'body' => 'A message must not be recorded.',
        'idempotency_key' => '12121212-1212-4212-8212-121212121212',
    ])->assertNotFound()
        ->assertJson(['code' => 'not_found', 'message' => 'Conversation not found.'])
        ->assertHeader('Cache-Control', 'no-store, private');
    $actor->postJson(route('case-conversations.read', ['thread' => $threadId]), [
        'observed_sequence' => '1',
        'observation_token' => 'unused',
    ])->assertNotFound()
        ->assertJson(['code' => 'not_found', 'message' => 'Conversation not found.'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('case conversation JSON routes require an authenticated session', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-unauth@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Authentication', 'Initial support message.');

    auth()->logout();
    session()->flush();
    $this->flushSession();

    $this->getJson(route('case-conversations.show', $thread))->assertUnauthorized();
    $this->postJson(route('case-conversations.messages.store', $thread), [
        'body' => 'This request must not be accepted.',
        'idempotency_key' => '99999999-9999-4999-8999-999999999999',
    ])->assertUnauthorized();
    $this->postJson(route('case-conversations.read', $thread), [
        'observed_sequence' => '1',
        'observation_token' => 'untrusted',
    ])->assertUnauthorized();
});

test('POST case-conversations.messages.store sends message, allocates sequence, and returns message snapshot', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-send@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Application Inquiries', 'Initial application support message.');

    // Opening atomically created the first message.
    expect($thread->fresh()->latest_message_sequence)->toBe(1);

    $response = actingAsMessagingActor($this, $fixture['phUser'])->postJson(route('case-conversations.messages.store', $thread), [
        'body' => 'Please bring two passport-sized photos for verification.',
        'idempotency_key' => '11111111-1111-4111-8111-111111111111',
    ]);

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    $data = $response->json();

    expect($data['replayed'])->toBeFalse()
        ->and($data['as_of'])->toBeString()->not->toBeEmpty()
        ->and($data['message']['id'])->toBeString()->not->toBeEmpty()
        ->and($data['message']['sequence'])->toBe('2')
        ->and($data['message']['sender']['display_name'])->toBe($fixture['phUser']->name)
        ->and($data['message']['sender']['is_self'])->toBeTrue()
        ->and($data['message']['body'])->toBe('Please bring two passport-sized photos for verification.')
        ->and($data['message']['created_at'])->toBeString()->not->toBeEmpty();

    // Verify DB update
    expect($thread->fresh()->latest_message_sequence)->toBe(2);
    expect(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(2);
});

test('replaying POST case-conversations.messages.store with same idempotency key returns replayed true and does not duplicate message', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-replay@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Schedule Inquiries', 'Initial schedule support message.');

    $payload = [
        'body' => 'Your interview is scheduled tomorrow at 9:00 AM.',
        'idempotency_key' => '22222222-2222-4222-8222-222222222222',
    ];

    // First request
    $firstResponse = actingAsMessagingActor($this, $fixture['phUser'])
        ->postJson(route('case-conversations.messages.store', $thread), $payload);

    $firstResponse->assertOk();
    expect($firstResponse->json('replayed'))->toBeFalse();
    $firstMessageId = $firstResponse->json('message.id');
    expect($firstMessageId)->toBeString()->not->toBeEmpty();
    expect(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(2);

    // Replay request with exact same payload and idempotency key
    $replayResponse = actingAsMessagingActor($this, $fixture['phUser'])
        ->postJson(route('case-conversations.messages.store', $thread), $payload);

    $replayResponse->assertOk();
    expect($replayResponse->json('replayed'))->toBeTrue()
        ->and($replayResponse->json('message.id'))->toBe($firstMessageId)
        ->and($replayResponse->json('message.sequence'))->toBe('2')
        ->and($replayResponse->json('message.body'))->toBe('Your interview is scheduled tomorrow at 9:00 AM.');
    actingAsMessagingActor($this, $fixture['phUser'])
        ->postJson(route('case-conversations.messages.store', $thread), [
            'body' => 'A changed body cannot reuse the operation key.',
            'idempotency_key' => $payload['idempotency_key'],
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'idempotency_conflict');

    // DB count remains exactly 1 - no duplicate created
    expect(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(2)
        ->and($thread->fresh()->latest_message_sequence)->toBe(2);
});

test('POST case-conversations.read with valid observation token updates last_read_sequence and recalculates unread_count', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-read@example.test');
    $thread = openCaseApiApplicantThread(
        $fixture,
        'Support Onboarding',
        'Welcome to the admissions portal.',
    );

    // Snapshot sequence 2, retain its authenticated session cookie, then commit a later reply.
    $sendAction = app(SendAdmissionSupportMessage::class);
    $sendAction->handle($thread, $fixture['applicant'], 'Thank you, I have a question.', '44444444-4444-4444-8444-444444444444');

    actingAsMessagingActor($this, $fixture['phUser'])->withCredentials();
    $snapshotSeq2Response = $this->getJson(route('case-conversations.show', $thread))->assertOk();
    $sessionCookieName = config('session.cookie');
    $sessionCookie = $snapshotSeq2Response->getCookie($sessionCookieName);
    expect($sessionCookie)->not->toBeNull();
    $this->withCookie($sessionCookieName, $sessionCookie->getValue());
    $snapshotSeq2 = $snapshotSeq2Response->json();
    $validTokenSeq2 = $snapshotSeq2['observation_token'];

    $sendAction->handle($thread, $fixture['applicant'], 'When do classes start?', '55555555-5555-4555-8555-555555555555');

    $response = $this->postJson(route('case-conversations.read', $thread), [
        'observed_sequence' => '2',
        'observation_token' => $validTokenSeq2,
    ]);

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    expect($response->json('own_read_sequence'))->toBe('2')
        ->and($response->json('unread_count'))->toBe(1) // the reply committed after the snapshot remains unread
        ->and($response->json('as_of'))->toBeString()->not->toBeEmpty();

    // Verify DB
    expect(RecordThreadRead::query()
        ->where('record_thread_id', $thread->id)
        ->where('user_id', $fixture['phUser']->id)
        ->value('last_read_sequence')
    )->toBe(2);

    // A later authenticated snapshot authorizes the newly observed third message.
    $snapshotSeq3Response = $this->getJson(route('case-conversations.show', $thread))->assertOk();
    $sessionCookie = $snapshotSeq3Response->getCookie($sessionCookieName);
    expect($sessionCookie)->not->toBeNull();
    $this->withCookie($sessionCookieName, $sessionCookie->getValue());
    $snapshotSeq3 = $snapshotSeq3Response->json();
    $validTokenSeq3 = $snapshotSeq3['observation_token'];
    $response2 = $this->postJson(route('case-conversations.read', $thread), [
        'observed_sequence' => '3',
        'observation_token' => $validTokenSeq3,
    ]);

    $response2->assertOk();
    expect($response2->json('own_read_sequence'))->toBe('3')
        ->and($response2->json('unread_count'))->toBe(0);

    expect(RecordThreadRead::query()
        ->where('record_thread_id', $thread->id)
        ->where('user_id', $fixture['phUser']->id)
        ->value('last_read_sequence')
    )->toBe(3);
});

test('POST case-conversations.read with invalid observation token returns 422 with validation error code', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-badtoken@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Token Verification', 'A message to read.');

    $response = actingAsMessagingActor($this, $fixture['phUser'])->postJson(route('case-conversations.read', $thread), [
        'observed_sequence' => '1',
        'observation_token' => 'invalid-forged-token-sig',
    ]);

    $response->assertStatus(422);
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    expect($response->json('code'))->toBe('validation_failed');
    actingAsMessagingActor($this, $fixture['phUser'])
        ->postJson(route('threads.read', $thread), [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');

    // DB remains unchanged
    expect(RecordThreadRead::query()
        ->where('record_thread_id', $thread->id)
        ->where('user_id', $fixture['phUser']->id)
        ->exists()
    )->toBeFalse();
});

test('HTML case read requests validate the observed boundary and redirect after the ordinary read action', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-read-redirect@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Read redirect', 'First message.');

    $actor = actingAsMessagingActor($this, $fixture['phUser'])->withCredentials();
    $snapshotResponse = $actor->getJson(route('case-conversations.show', $thread))->assertOk();
    $sessionCookieName = config('session.cookie');
    $sessionCookie = $snapshotResponse->getCookie($sessionCookieName);
    expect($sessionCookie)->not->toBeNull();
    $actor->withCookie($sessionCookieName, $sessionCookie->getValue());
    $snapshot = $snapshotResponse->json();

    $actor->post(route('threads.read', $thread), [
        'observed_sequence' => '1',
        'observation_token' => $snapshot['observation_token'],
    ])->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(RecordThreadRead::query()
        ->where('record_thread_id', $thread->id)
        ->where('user_id', $fixture['phUser']->id)
        ->value('last_read_sequence'))->toBe(1);
});

test('conversation HTTP inputs reject invalid text, authority fields, and sequence cursors without mutation', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-validation@example.test');
    $thread = openCaseApiApplicantThread($fixture, 'Validation', 'Existing message.');

    $actor = actingAsMessagingActor($this, $fixture['phUser']);
    $route = route('case-conversations.messages.store', $thread);
    $basePayload = ['idempotency_key' => '77777777-7777-4777-8777-777777777777'];

    $actor->postJson($route, [...$basePayload, 'body' => '   '])
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    $actor->postJson($route, [...$basePayload, 'body' => str_repeat('x', 4001)])
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    $actor->postJson($route, [...$basePayload, 'body' => 'Forged context', 'actor_id' => $fixture['applicant']->id])
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');

    $actor->post($route, [
        ...$basePayload,
        'body' => "\xB1",
    ])->assertRedirect()
        ->assertHeader('Cache-Control', 'no-store, private');

    actingAsMessagingActor($this, $fixture['phUser'])
        ->getJson(route('case-conversations.show', ['thread' => $thread, 'actor_id' => $fixture['applicant']->id]))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    actingAsMessagingActor($this, $fixture['phUser'])
        ->getJson(route('case-conversations.show', ['thread' => $thread, 'after_sequence' => '0', 'before_sequence' => '1']))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    actingAsMessagingActor($this, $fixture['phUser'])
        ->getJson(route('case-conversations.show', ['thread' => $thread, 'after_sequence' => '2']))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed');

    expect(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(1)
        ->and($thread->fresh()->latest_message_sequence)->toBe(1);
});

test('POST case-conversations.applicant.open by assigned Program Head atomically creates thread with sequence 1 and first message with sequence 1', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-open@example.test');
    $phUser = $fixture['phUser'];
    $attempt = $fixture['attempt'];
    $program = $fixture['program'];

    expect(RecordThread::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);

    $response = actingAsMessagingActor($this, $phUser)->postJson(route('case-conversations.applicant.open', [
        'program' => $program->code,
        'attempt' => $attempt->id,
    ]), [
        'subject' => 'Department Review Notice',
        'body' => 'Please coordinate with the Nursing department regarding clinical duties.',
        'idempotency_key' => '33333333-3333-4333-8333-333333333333',
    ]);

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    $data = $response->json();

    expect($data['thread']['id'])->toBeString()->not->toBeEmpty()
        ->and($data['thread']['subject'])->toBe('Department Review Notice')
        ->and($data['message']['sequence'])->toBe('1')
        ->and($data['message']['body'])->toBe('Please coordinate with the Nursing department regarding clinical duties.')
        ->and($data['message']['sender']['display_name'])->toBe($phUser->name)
        ->and($data['message']['sender']['is_self'])->toBeTrue()
        ->and($data['as_of'])->toBeString()->not->toBeEmpty();

    // Verify DB atomicity
    $createdThread = RecordThread::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    expect($createdThread->id)->toBe((int) $data['thread']['id'])
        ->and($createdThread->thread_type)->toBe(RecordThread::TYPE_APPLICANT_SUPPORT)
        ->and($createdThread->latest_message_sequence)->toBe(1)
        ->and($createdThread->subject)->toBe('Department Review Notice')
        ->and($createdThread->status)->toBe(RecordThread::STATUS_OPEN)
        ->and($createdThread->created_by_user_id)->toBe($phUser->id);

    $createdMessage = RecordThreadMessage::query()->where('record_thread_id', $createdThread->id)->firstOrFail();
    expect($createdMessage->id)->toBe((int) $data['message']['id'])
        ->and($createdMessage->sequence)->toBe(1)
        ->and($createdMessage->sender_user_id)->toBe($phUser->id)
        ->and($createdMessage->body)->toBe('Please coordinate with the Nursing department regarding clinical duties.');
});

test('POST program-head.threads.open atomically creates and idempotently replays an internal thread and first message', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-internal-open@example.test');
    $program = $fixture['program'];
    $attempt = $fixture['attempt'];
    $payload = [
        'subject' => 'Internal staffing review',
        'body' => 'Confirm the assigned review schedule before responding to the applicant.',
        'idempotency_key' => '88888888-8888-4888-8888-888888888888',
    ];
    $url = route('program-head.threads.open', ['program' => $program->code, 'attempt' => $attempt->id]);

    $actor = actingAsMessagingActor($this, $fixture['phUser']);
    $actor->postJson($url, ['subject' => $payload['subject']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
    $actor->postJson($url, [...$payload, 'idempotency_key' => 'not-a-uuid'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertHeader('Cache-Control', 'no-store, private');
    $actor->postJson($url, [...$payload, 'body' => str_repeat('x', 4001)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
    $actor->postJson($url, [...$payload, 'program_id' => $program->id])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');

    $response = $actor->postJson($url, $payload)->assertOk();
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
    $response->assertJsonPath('thread.subject', $payload['subject'])
        ->assertJsonPath('thread.id', $response->json('thread.id'))
        ->assertJsonPath('message.sequence', '1')
        ->assertJsonPath('message.body', $payload['body'])
        ->assertJsonPath('replayed', false);
    $threadId = $response->json('thread.id');
    $messageId = $response->json('message.id');

    $replay = $actor->postJson($url, $payload)->assertOk();
    $replay->assertJsonPath('thread.id', $threadId)
        ->assertJsonPath('message.id', $messageId)
        ->assertJsonPath('replayed', true);
    $actor->postJson($url, [...$payload, 'body' => 'Different internal message.'])
        ->assertConflict()
        ->assertJsonPath('code', 'idempotency_conflict');

    expect(RecordThread::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
        ->count())->toBe(1)
        ->and(RecordThreadMessage::query()->where('record_thread_id', $threadId)->count())->toBe(1)
        ->and(RecordThreadMessage::query()->where('record_thread_id', $threadId)->value('id'))->toBe((int) $messageId);
});

test('in-app notification delivery row is created for the recipient and not for the sender on message send and support open', function (): void {
    $fixture = createCaseConversationFixture($this, 'case-api-notif@example.test');
    $phUser = $fixture['phUser'];
    $attempt = $fixture['attempt'];
    $applicant = $fixture['applicant'];
    $program = $fixture['program'];

    // Part 1: Program Head opens applicant support conversation
    $openResponse = actingAsMessagingActor($this, $phUser)->postJson(route('case-conversations.applicant.open', [
        'program' => $program->code,
        'attempt' => $attempt->id,
    ]), [
        'subject' => 'Notice on Medical Clearance',
        'body' => 'Your medical certificate needs department verification.',
        'idempotency_key' => '44444444-4444-4444-8444-444444444444',
    ]);
    $openResponse->assertOk();
    $threadId = (int) $openResponse->json('thread.id');
    $firstMessageId = (int) $openResponse->json('message.id');

    // Recipient for support open is ApplicantIdentity, NOT Program Head sender
    $applicantDeliveryOnOpen = NotificationDelivery::query()
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->latest('created_at')
        ->first();

    expect($applicantDeliveryOnOpen)->not->toBeNull()
        ->and($applicantDeliveryOnOpen->payload['thread_id'])->toBe($threadId)
        ->and($applicantDeliveryOnOpen->payload['message_id'])->toBe($firstMessageId);

    // Verify sender (PH) did NOT receive an in-app notification
    expect(NotificationDelivery::query()
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->where('recipient_type', User::class)
        ->where('recipient_id', $phUser->id)
        ->exists()
    )->toBeFalse();

    // Part 2: Applicant sends reply message to the thread
    $thread = RecordThread::query()->findOrFail($threadId);
    $applicantSendResponse = actingAsMessagingActor($this, $applicant)->postJson(route('case-conversations.messages.store', $thread), [
        'body' => 'I have scheduled the medical clearance appointment.',
        'idempotency_key' => '55555555-5555-4555-8555-555555555555',
    ]);
    $applicantSendResponse->assertOk();
    $replyMsgId = (int) $applicantSendResponse->json('message.id');

    // Recipient for message sent by applicant is the assigned Program Head
    $phDelivery = NotificationDelivery::query()
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', User::class)
        ->where('recipient_id', $phUser->id)
        ->latest('created_at')
        ->first();

    expect($phDelivery)->not->toBeNull()
        ->and($phDelivery->payload['thread_id'])->toBe($threadId)
        ->and($phDelivery->payload['message_id'])->toBe($replyMsgId);

    // Sender (Applicant) did NOT receive notification for their own message
    $applicantDeliveriesForReply = NotificationDelivery::query()
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->where('payload->message_id', $replyMsgId)
        ->exists();
    expect($applicantDeliveriesForReply)->toBeFalse();

    // Part 3: Program Head sends another message to thread
    $phSendResponse = actingAsMessagingActor($this, $phUser)->postJson(route('case-conversations.messages.store', $thread), [
        'body' => 'Acknowledged. Bring the receipt as well.',
        'idempotency_key' => '66666666-6666-4666-8666-666666666666',
    ]);
    $phSendResponse->assertOk();
    $phMsgId = (int) $phSendResponse->json('message.id');

    // Recipient is ApplicantIdentity
    $applicantDeliveryOnPhReply = NotificationDelivery::query()
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->where('payload->message_id', $phMsgId)
        ->first();

    expect($applicantDeliveryOnPhReply)->not->toBeNull();

    // Sender (PH) did NOT receive notification for their own message
    $phDeliveriesForReply = NotificationDelivery::query()
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->where('recipient_type', User::class)
        ->where('recipient_id', $phUser->id)
        ->where('payload->message_id', $phMsgId)
        ->exists();
    expect($phDeliveriesForReply)->toBeFalse();
});

test('case read acknowledgments deny revoked, enrolled, and wrong actors with opaque 404 and no read mutation', function (): void {
    $revoked = createCaseConversationFixture($this, 'case-read-revoked@example.test');
    $revokedThread = openCaseApiApplicantThread($revoked, 'Revoked access', 'Still retained.');
    $revokedSnapshot = actingAsMessagingActor($this, $revoked['applicant'])
        ->getJson(route('case-conversations.show', $revokedThread))
        ->assertOk()
        ->json();
    $revoked['applicant']->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    actingAsMessagingActor($this, $revoked['applicant'])
        ->postJson(route('case-conversations.read', $revokedThread), [
            'observed_sequence' => '1',
            'observation_token' => $revokedSnapshot['observation_token'],
        ])
        ->assertForbidden();
    expect(RecordThreadRead::query()->where('record_thread_id', $revokedThread->id)->count())->toBe(0);

    $enrolled = createCaseConversationFixture($this, 'case-read-enrolled@example.test');
    $enrolledThread = openCaseApiApplicantThread($enrolled, 'Enrolled access', 'Still retained.');
    $enrolledSnapshot = actingAsMessagingActor($this, $enrolled['applicant'])
        ->getJson(route('case-conversations.show', $enrolledThread))
        ->assertOk()
        ->json();
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $enrolled['attempt']->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $enrolled['guidance']->id,
        'reason' => 'Read authorization regression fixture',
        'version' => 1,
        'created_at' => now(),
    ]);
    actingAsMessagingActor($this, $enrolled['applicant'])
        ->postJson(route('case-conversations.read', $enrolledThread), [
            'observed_sequence' => '1',
            'observation_token' => $enrolledSnapshot['observation_token'],
        ])
        ->assertNotFound()
        ->assertJson(['code' => 'not_found', 'message' => 'Conversation not found.'])
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(RecordThreadRead::query()->where('record_thread_id', $enrolledThread->id)->count())->toBe(0);

    $wrongActor = createCaseConversationFixture($this, 'case-read-wrong-actor@example.test');
    $wrongActorThread = openCaseApiApplicantThread($wrongActor, 'Wrong actor', 'Still retained.');
    $wrongActorSnapshot = actingAsMessagingActor($this, $wrongActor['applicant'])
        ->getJson(route('case-conversations.show', $wrongActorThread))
        ->assertOk()
        ->json();
    actingAsMessagingActor($this, User::query()->where('email', 'dean.ccit@nexiam.test')->firstOrFail())
        ->postJson(route('case-conversations.read', $wrongActorThread), [
            'observed_sequence' => '1',
            'observation_token' => $wrongActorSnapshot['observation_token'],
        ])
        ->assertNotFound()
        ->assertJson(['code' => 'not_found', 'message' => 'Conversation not found.'])
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(RecordThreadRead::query()->where('record_thread_id', $wrongActorThread->id)->count())->toBe(0)
        ->and(RecordThread::query()->whereKey($revokedThread->id)->exists())->toBeTrue()
        ->and(RecordThread::query()->whereKey($enrolledThread->id)->exists())->toBeTrue()
        ->and(RecordThread::query()->whereKey($wrongActorThread->id)->exists())->toBeTrue();
});
