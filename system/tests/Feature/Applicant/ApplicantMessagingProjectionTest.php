<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';
require_once __DIR__.'/../../Support/AdmissionSupportHelpers.php';

/**
 * @return array{attempt:AdmissionAttempt,applicant:User,guidance:User,phUser:User,program:Program}
 */
function createApplicantCaseProjectionFixture(TestCase $test, string $email): array
{
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $fixtureId = (string) Str::uuid();

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'applicant-projection-pool-'.$fixtureId);
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'applicant-projection-selection-'.$fixtureId);
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    return compact('attempt', 'applicant', 'guidance', 'phUser', 'program');
}

function setupOfficeProjectionFixture(): array
{
    seedE2Foundation();
    (new ApplicantOfficeInquiryDemoSeeder)->run();

    return [
        'applicant' => User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::APPLICANT_EMAIL)->firstOrFail(),
        'registrar' => User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::REGISTRAR_EMAIL)->firstOrFail(),
        'guidance' => User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail(),
        'attempt' => AdmissionAttempt::query()->where('reference_code', ApplicantOfficeInquiryDemoSeeder::REFERENCE_CODE)->firstOrFail(),
    ];
}

test('program support only applicant receives case metadata and no office metadata', function (): void {
    $fixture = createApplicantCaseProjectionFixture($this, 'case-only-projection@example.test');
    $applicant = $fixture['applicant'];

    // Ensure applicant has no office demo actor marker
    $applicant->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);

    // Create a case support thread for this applicant
    app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Application Inquiry Regarding Prerequisites',
        'Hello guidance counselor, please clarify the prerequisite submission timeline.',
        $fixture['phUser'],
        (string) Str::uuid(),
    );

    $response = actingAsMessagingActor($this, $applicant)
        ->get(route('applicant.admission-support'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Applicant/AdmissionSupport')
        ->where('active_group', 'program_support')
        ->where('groups_available.program_support', true)
        ->where('groups_available.offices', false)
        ->has('cases')
        ->has('principal_key')
        ->has('urls')
        ->missing('inquiries')
        ->missing('destinations')
        ->missing('office_label')
        ->missing('synthetic_only')
        ->missing('unavailable')
        ->missing('office_search')
        ->missing('office_cursor')
    );
});

test('office only applicant receives office metadata and no case metadata', function (): void {
    $fixture = setupOfficeProjectionFixture();
    $applicant = $fixture['applicant'];

    // Ensure the applicant does not have program support capability
    $applicant->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);

    // Create an inquiry
    CreateApplicantOfficeInquiry::handle(
        $applicant,
        'registrar',
        'Office hours question',
        'What time does the registrar counter close today?',
        (string) Str::uuid(),
    );

    $response = actingAsMessagingActor($this, $applicant)
        ->get(route('applicant.admission-support', ['group' => 'offices']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Applicant/AdmissionSupport')
        ->where('active_group', 'offices')
        ->where('groups_available.program_support', false)
        ->where('groups_available.offices', true)
        ->has('inquiries')
        ->has('destinations')
        ->where('synthetic_only', true)
        ->has('principal_key')
        ->has('urls')
        ->missing('cases')
        ->missing('case_selected')
        ->missing('case_snapshot')
        ->missing('case_search')
        ->missing('case_cursor')
        ->missing('next_case_cursor')
        ->missing('has_more')
        ->missing('snapshotUrl')
        ->missing('sendUrl')
        ->missing('readUrl')
    );
});

test('empty group versus unavailable group behavior', function (): void {
    // 1. Program support: available with 0 threads vs unavailable
    $fixture = createApplicantCaseProjectionFixture($this, 'empty-vs-unavail-case@example.test');
    $applicant = $fixture['applicant'];

    // Available with 0 threads
    $resAvailable = actingAsMessagingActor($this, $applicant)
        ->get(route('applicant.admission-support', ['group' => 'program_support']));

    $resAvailable->assertOk();
    $resAvailable->assertInertia(fn ($page) => $page
        ->where('groups_available.program_support', true)
        ->where('cases', [])
        ->where('has_more', false)
    );

    // Revoke capability -> unavailable
    $applicant->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    $resUnavailable = actingAsMessagingActor($this, $applicant)
        ->get(route('applicant.admission-support', ['group' => 'program_support']));

    $resUnavailable->assertOk();
    $resUnavailable->assertInertia(fn ($page) => $page
        ->where('groups_available.program_support', false)
        ->where('cases', [])
        ->where('case_selected', null)
        ->where('case_snapshot', null)
    );

    // 2. Offices: available with 0 inquiries vs unavailable
    $officeFixture = setupOfficeProjectionFixture();
    $officeApplicant = $officeFixture['applicant'];

    ApplicantOfficeInquiryMessage::query()->delete();
    ApplicantOfficeInquiry::query()->delete();
    $resOfficeAvailable = actingAsMessagingActor($this, $officeApplicant)
        ->get(route('applicant.admission-support', ['group' => 'offices']));

    $resOfficeAvailable->assertOk();
    $resOfficeAvailable->assertInertia(fn ($page) => $page
        ->where('groups_available.offices', true)
        ->where('inquiries', [])
        ->where('unavailable', null)
    );

    // Unregistered applicant without demo actor marker -> unavailable
    $unregisteredUser = User::query()->create([
        'name' => 'Unregistered Office Applicant',
        'email' => 'unreg-office@example.test',
        'password' => bcrypt('password'),
    ]);

    $resOfficeUnavailable = $this->actingAs($unregisteredUser)
        ->get(route('applicant.admission-support', ['group' => 'offices']));

    $resOfficeUnavailable->assertOk();
    $resOfficeUnavailable->assertInertia(fn ($page) => $page
        ->where('groups_available.offices', false)
        ->where('inquiries', [])
        ->where('unavailable.available', false)
    );
});

test('same id domain selector collision and mixed domain selectors are opaquely rejected', function (): void {
    $caseFixture = createApplicantCaseProjectionFixture($this, 'collision-case@example.test');
    $caseApplicant = $caseFixture['applicant'];

    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $caseFixture['attempt'],
        $caseFixture['program']->id,
        'Collision Test Subject',
        'Message body content',
        $caseFixture['phUser'],
        (string) Str::uuid(),
    );

    $officeFixture = setupOfficeProjectionFixture();
    $officeApplicant = $officeFixture['applicant'];

    $inquiry = CreateApplicantOfficeInquiry::handle(
        $officeApplicant,
        'registrar',
        'Collision Office Subject',
        'Inquiry body content',
        (string) Str::uuid(),
    );

    $threadId = (string) $thread->id;
    $inquiryId = (string) $inquiry['inquiry_id'];

    // 1. Nonexistent or foreign thread_id in program support
    actingAsMessagingActor($this, $caseApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'program_support',
            'thread_id' => '99999999',
        ]))
        ->assertNotFound();

    // 2. Supplying office inquiry ID as thread_id to program support
    actingAsMessagingActor($this, $caseApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'program_support',
            'thread_id' => $inquiryId,
        ]))
        ->assertNotFound();

    // 3. Nonexistent or foreign inquiry_id in offices
    actingAsMessagingActor($this, $officeApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'offices',
            'inquiry_id' => '99999999',
        ]))
        ->assertNotFound();

    // 4. A case thread ID that collides with a real office inquiry ID
    $officeCollisionResponse = actingAsMessagingActor($this, $officeApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'offices',
            'inquiry_id' => $threadId,
        ]));

    $officeCollisionResponse->assertOk();
    $officeCollisionResponse->assertInertia(fn ($page) => $page
        ->where('initial.thread.id', $threadId)
        ->where('initial.thread.subject', fn ($subject) => $subject !== 'Collision Test Subject' && is_string($subject) && $subject !== '')
        ->missing('cases')
    );

    // 5. Mixed selectors: group=program_support with inquiry_id
    actingAsMessagingActor($this, $caseApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'program_support',
            'inquiry_id' => $inquiryId,
        ]))
        ->assertNotFound();

    // 6. Mixed selectors: group=offices with thread_id
    actingAsMessagingActor($this, $officeApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'offices',
            'thread_id' => $threadId,
        ]))
        ->assertNotFound();

    // 7. Mixed selectors: both thread_id and inquiry_id provided
    actingAsMessagingActor($this, $caseApplicant)
        ->get(route('applicant.admission-support', [
            'thread_id' => $threadId,
            'inquiry_id' => $inquiryId,
        ]))
        ->assertNotFound();

    // 8. Mixed search query parameters
    actingAsMessagingActor($this, $caseApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'program_support',
            'office_search' => 'inquiry search',
        ]))
        ->assertNotFound();

    actingAsMessagingActor($this, $officeApplicant)
        ->get(route('applicant.admission-support', [
            'group' => 'offices',
            'case_search' => 'case search',
        ]))
        ->assertNotFound();
});

test('access history returns bounded events with no payload and logs no new audit event', function (): void {
    $fixture = createApplicantCaseProjectionFixture($this, 'access-history-bounded@example.test');
    $applicant = $fixture['applicant'];
    $attempt = $fixture['attempt'];

    // Seed 55 audit events for this attempt across approved types
    $recordAudit = app(RecordAuditEvent::class);
    for ($i = 1; $i <= 55; $i++) {
        $recordAudit->handle(
            'program.head_record_viewed',
            [
                'attempt_id' => (string) $attempt->id,
                'secret_review_note' => 'Confidential internal note '.$i,
                'sensitive_applicant_record_id' => 'REC-'.$i,
            ],
            'Staff Reviewer '.$i,
        );
    }

    $auditCountBefore = AuditEvent::query()->count();

    $response = actingAsMessagingActor($this, $applicant)
        ->get(route('applicant.access-history'));

    $response->assertOk();

    // Verify no new audit event was emitted
    $auditCountAfter = AuditEvent::query()->count();
    expect($auditCountAfter)->toBe($auditCountBefore);

    $response->assertInertia(function ($page): void {
        $page->component('Applicant/AccessHistory')
            ->where('events.data.0.event_type', 'program.head_record_viewed')
            ->where('events.data.0.label', 'Program Head viewed your record');

        $events = $page->toArray()['props']['events'];
        expect($events['data'])->toHaveCount(25)
            ->and($events['total'])->toBeGreaterThanOrEqual(55)
            ->and($events['per_page'])->toBe(25);

        foreach ($events['data'] as $event) {
            expect($event)->toHaveKeys(['id', 'event_type', 'label', 'category', 'actor_display', 'direction', 'occurred_at'])
                ->and(array_keys($event))->not->toContain('actor')
                ->and(array_keys($event))->not->toContain('payload')
                ->and(array_keys($event))->not->toContain('attempt_id')
                ->and(array_keys($event))->not->toContain('secret_review_note')
                ->and(array_keys($event))->not->toContain('sensitive_applicant_record_id');
        }
    });
});

test('access history renders safe empty state for user without applicant account', function (): void {
    $userWithoutApplicant = User::query()->create([
        'name' => 'Standalone User',
        'email' => 'standalone-no-applicant@example.test',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($userWithoutApplicant)
        ->get(route('applicant.access-history'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Applicant/AccessHistory')
        ->has('events')
        ->where('events.data', [])
        ->where('events.total', 0)
        ->missing('access_history')
    );
});
test('exam timing help returns an editable own-context office draft without creating an inquiry', function (): void {
    $fixture = setupOfficeProjectionFixture();
    $applicant = $fixture['applicant'];
    $inquiryCount = ApplicantOfficeInquiry::query()->count();
    $messageCount = ApplicantOfficeInquiryMessage::query()->count();

    $response = actingAsMessagingActor($this, $applicant)->get(
        route('applicant.admission-support', ['group' => 'offices', 'draft_reason' => 'exam_time_extension']),
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('active_group', 'offices')
        ->where('draft_reason', 'exam_time_extension')
        ->has('inquiry_draft')
        ->where('inquiry_draft.reason', 'exam_time_extension')
        ->where('inquiry_draft.principal_key', $page->toArray()['props']['principal_key'])
        ->where('inquiry_draft.attempt_key', $page->toArray()['props']['attempt_key'])
        ->where('inquiry_draft.office', 'guidance')
        ->where('inquiry_draft.subject', 'Question about exam timing')
        ->has('inquiry_draft.body')
        ->missing('inquiry_draft.applicant_id')
        ->missing('inquiry_draft.thread_id')
    );

    expect(ApplicantOfficeInquiry::query()->count())->toBe($inquiryCount)
        ->and(ApplicantOfficeInquiryMessage::query()->count())->toBe($messageCount);
});

test('exam timing help rejects other reasons and non-office groups', function (): void {
    $fixture = setupOfficeProjectionFixture();

    actingAsMessagingActor($this, $fixture['applicant'])
        ->get(route('applicant.admission-support', ['group' => 'offices', 'draft_reason' => 'free_text']))
        ->assertNotFound();

    actingAsMessagingActor($this, $fixture['applicant'])
        ->get(route('applicant.admission-support', ['group' => 'program_support', 'draft_reason' => 'exam_time_extension']))
        ->assertNotFound();

    actingAsMessagingActor($this, $fixture['applicant'])
        ->get(route('applicant.admission-support', [
            'group' => 'offices',
            'draft_reason' => ['exam_time_extension'],
        ]))
        ->assertNotFound();

    actingAsMessagingActor($this, $fixture['applicant'])
        ->get(route('applicant.admission-support', [
            'group' => 'offices',
            'draft_reason' => 'exam_time_extension',
            'inquiry_id' => '999999999',
        ]))
        ->assertNotFound();
});

test('exam timing help has no draft when office access or applicant attempt is unavailable', function (): void {
    $userWithoutApplicant = User::query()->create([
        'name' => 'Unregistered exam help applicant',
        'email' => 'exam-help-unregistered@example.test',
        'password' => bcrypt('password'),
    ]);

    $response = actingAsMessagingActor($this, $userWithoutApplicant)->get(
        route('applicant.admission-support', ['group' => 'offices', 'draft_reason' => 'exam_time_extension']),
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('draft_reason', 'exam_time_extension')
        ->where('inquiry_draft', null)
        ->where('attempt_key', '')
    );
    $fixture = setupOfficeProjectionFixture();
    $fixture['applicant']->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);

    $unavailableResponse = actingAsMessagingActor($this, $fixture['applicant'])->get(
        route('applicant.admission-support', ['group' => 'offices', 'draft_reason' => 'exam_time_extension']),
    );
    $unavailableResponse->assertOk();
    $unavailableResponse->assertInertia(fn ($page) => $page
        ->where('draft_reason', 'exam_time_extension')
        ->where('inquiry_draft', null)
    );
});
test('external-enrollment report opens an editable Registrar draft without recording enrollment', function (): void {
    $fixture = setupOfficeProjectionFixture();
    $before = ApplicantOfficeInquiry::query()->count();
    $stampCount = EnrollmentStamp::query()->where('admission_attempt_id', $fixture['attempt']->id)->count();
    $attemptVersion = $fixture['attempt']->version;
    $response = actingAsMessagingActor($this, $fixture['applicant'])->get(
        route('applicant.admission-support', ['group' => 'offices', 'draft_reason' => 'program_enrolled_elsewhere']),
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('draft_reason', 'program_enrolled_elsewhere')
        ->where('inquiry_draft.reason', 'program_enrolled_elsewhere')
        ->where('inquiry_draft.office', 'registrar')
        ->missing('inquiry_draft.program_id')
        ->missing('inquiry_draft.enrollment_status'));

    expect(ApplicantOfficeInquiry::query()->count())->toBe($before);
    expect(EnrollmentStamp::query()->where('admission_attempt_id', $fixture['attempt']->id)->count())->toBe($stampCount)
        ->and($fixture['attempt']->fresh()->version)->toBe($attemptVersion);
});

test('program report drafts remain unavailable when scoped office access is denied', function (): void {
    $fixture = setupOfficeProjectionFixture();
    $fixture['applicant']->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);

    actingAsMessagingActor($this, $fixture['applicant'])
        ->get(route('applicant.admission-support', ['group' => 'offices', 'draft_reason' => 'program_enrolled_elsewhere']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('inquiry_draft', null)
            ->where('unavailable.available', false));
});

test('/messages redirects unauthenticated user to login, applicant to admission support, and staff to inquiries', function (): void {
    $this->get('/messages')->assertRedirect(route('login'));

    $fixture = createApplicantCaseProjectionFixture($this, 'redirects-test@example.test');
    $applicant = $fixture['applicant'];
    $guidance = $fixture['guidance'];

    $this->actingAs($applicant)
        ->get('/messages')
        ->assertRedirect(route('applicant.admission-support'));

    $this->actingAs($applicant)
        ->get('/applicant/messages')
        ->assertRedirect(route('applicant.admission-support'));

    actingAsMessagingActor($this, $guidance)
        ->get('/messages')
        ->assertRedirect(route('staff.inquiries.index'));
});
