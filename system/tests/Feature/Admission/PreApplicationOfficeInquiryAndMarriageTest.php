<?php

declare(strict_types=1);

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';
require_once __DIR__.'/../../Support/AdmissionSupportHelpers.php';

function setupPreApplicationInquiryFixture(): array
{
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    (new ApplicantOfficeInquiryDemoSeeder)->run();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
    $window = ApplicationWindow::query()
        ->where('academic_cycle_id', $cycle->id)
        ->where('status', 'published')
        ->firstOrFail();
    $policy = PublicPolicy::designatedIntake((int) $institution->id);
    $revision = $policy?->publishedRevision;

    return [
        'institution' => $institution,
        'cycle' => $cycle,
        'window' => $window,
        'policy' => $policy,
        'revision' => $revision,
        'registrar' => User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail(),
        'guidance' => User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail(),
    ];
}

test('pre-application visitor can open an inquiry without email, read staff replies, and staff can resolve it in the unified queue', function (): void {
    Mail::fake();
    $fixture = setupPreApplicationInquiryFixture();
    $guidance = $fixture['guidance'];

    $modalResponse = $this->getJson(route('public.inquiries.modal'))
        ->assertOk()
        ->assertJsonPath('authenticated_applicant', false)
        ->assertJsonPath('inquiry_state.available', true);

    $deviceToken = (string) $modalResponse->json('device_token');
    expect($deviceToken)->not->toBe('');

    $createKey = (string) Str::uuid();
    $created = $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->postJson(route('public.inquiries.store'), [
            'office' => 'guidance',
            'subject' => 'Entrance Exam Schedule & Slot Reservation',
            'body' => 'Are Saturday morning exam slots open for prospective applicants?',
            'idempotency_key' => $createKey,
        ])
        ->assertOk();

    $inquiryId = (int) $created->json('inquiry_id');
    $publicRef = (string) $created->json('public_reference_code');
    expect($publicRef)->toMatch('/^INQ-\d{2}-\d{5}$/')
        ->and($created->json('snapshot.thread.public_reference_code'))->toBe($publicRef);

    // Replaying with the same idempotency key does not duplicate the inquiry
    $replayed = $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->postJson(route('public.inquiries.store'), [
            'office' => 'guidance',
            'subject' => 'Entrance Exam Schedule & Slot Reservation',
            'body' => 'Are Saturday morning exam slots open for prospective applicants?',
            'idempotency_key' => $createKey,
        ])
        ->assertOk()
        ->assertJsonPath('inquiry_id', (string) $inquiryId)
        ->assertJsonPath('replayed', true);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();

    // Staff sees the pre-application inquiry in the unified office queue with its Public reference code
    actingAsMessagingActor($this, $guidance)
        ->get(route('staff.inquiries.index', ['group' => 'offices']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Inquiries/Index')
            ->where('inquiries.0.id', (string) $inquiryId)
            ->where('inquiries.0.applicant.reference_code', $publicRef)
            ->where('inquiries.0.is_pre_application', true)
        );

    // Staff replies to the pre-application inquiry
    actingAsMessagingActor($this, $guidance)
        ->postJson(route('inquiries.messages.store', ['inquiry' => $inquiryId]), [
            'body' => 'Yes, Saturday 8:00 AM slots are open once you submit your application.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk()
        ->assertJsonPath('message.sequence', '2');

    // Pre-application visitor reads the staff reply via web snapshot and marks it read
    auth()->guard('web')->logout();
    $snapshot = $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->getJson(route('public.inquiries.snapshot', ['inquiry' => $inquiryId]))
        ->assertOk()
        ->assertJsonPath('latest_sequence', '2')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(2, 'messages');

    $observationToken = (string) $snapshot->json('observation_token');
    $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->postJson(route('public.inquiries.read', ['inquiry' => $inquiryId]), [
            'observed_sequence' => '2',
            'observation_token' => $observationToken,
        ])
        ->assertOk()
        ->assertJsonPath('own_read_sequence', '2')
        ->assertJsonPath('unread_count', 0);

    // Staff resolves the pre-application inquiry
    actingAsMessagingActor($this, $guidance)
        ->postJson(route('inquiries.resolve', ['inquiry' => $inquiryId]), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'closed');
});

test('pre-application inquiry enforces max 2 open inquiries per device token and rate limiting', function (): void {
    $fixture = setupPreApplicationInquiryFixture();
    $deviceToken = Str::random(48);

    for ($i = 1; $i <= 2; $i++) {
        $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
            ->postJson(route('public.inquiries.store'), [
                'office' => 'registrar',
                'subject' => "Pre-application question {$i}",
                'body' => "Question body number {$i} before applying.",
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertOk();
    }

    // 3rd open inquiry on the same device token is rejected with 422 due to the 2-open-inquiry cap
    $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->postJson(route('public.inquiries.store'), [
            'office' => 'guidance',
            'subject' => 'Third concurrent open question',
            'body' => 'Trying to open a third inquiry while two are open.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subject']);

    // 4th attempt within 15 minutes hits the 3-per-15-minutes rate limiter (429)
    $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->postJson(route('public.inquiries.store'), [
            'office' => 'guidance',
            'subject' => 'Fourth attempt within window',
            'body' => 'Should be throttled by public-inquiry-create.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(429);
});

test('pre-application inquiry transactionally marries to the applicant attempt on /apply submit and to the user account on login', function (): void {
    $fixture = setupPreApplicationInquiryFixture();
    $deviceToken = Str::random(48);

    $created = $this->withHeader(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken)
        ->postJson(route('public.inquiries.store'), [
            'office' => 'registrar',
            'subject' => 'Document Requirements (Form 138, PSA, TOR)',
            'body' => 'Can I submit my online application while waiting for my PSA copy?',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk();

    $inquiryId = (int) $created->json('inquiry_id');
    $publicRef = (string) $created->json('public_reference_code');

    // Guidance/Registrar replies before the visitor finishes applying
    actingAsMessagingActor($this, $fixture['registrar'])
        ->postJson(route('inquiries.messages.store', ['inquiry' => $inquiryId]), [
            'body' => 'Yes! Submit online now and bring the PSA copy during Clearance.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk();
    auth()->guard('web')->logout();

    // Visitor submits their online application on the same device
    $programCode = (string) Program::query()
        ->where('institution_id', $fixture['institution']->id)
        ->where('is_active', true)
        ->where('is_admission_offered', true)
        ->value('code');

    request()->headers->set(ApplicantOfficeInquiryAccess::DEVICE_HEADER, $deviceToken);
    $submission = app(SubmitAdmissionAttempt::class)->handle(
        $fixture['institution'],
        $fixture['cycle'],
        $fixture['window'],
        new AdmissionIntakeData(
            firstName: 'Maria',
            middleName: 'Luna',
            lastName: 'Santos',
            suffix: null,
            birthdate: '2007-05-14',
            sex: 'female',
            contactEmail: 'maria.santos.preapply@nexiam.test',
            contactPhone: '09171234567',
            addressLine: 'Poblacion',
            city: 'Tagudin',
            province: 'Ilocos Sur',
            zipCode: '2714',
            applicantType: 'new',
            lastSchoolEnrolled: 'Tagudin National High School',
            strand: 'STEM',
            programPreferenceCodes: [$programCode],
            termsAccepted: true,
            publicPolicyId: (int) $fixture['policy']->id,
            publicPolicyRevisionId: (int) $fixture['revision']->id,
            publicPolicyRevisionDigest: $fixture['revision']->contentDigest(),
        ),
    );

    /** @var AdmissionAttempt $attempt */
    $attempt = $submission['attempt'];
    $accessKey = $submission['access_token'];

    $inquiryAfterSubmit = ApplicantOfficeInquiry::query()->findOrFail($inquiryId);
    expect((int) $inquiryAfterSubmit->admission_attempt_id)->toBe((int) $attempt->id)
        ->and((int) $inquiryAfterSubmit->applicant_identity_id)->toBe((int) $attempt->applicant_identity_id)
        ->and($inquiryAfterSubmit->public_reference_code)->toBe($publicRef);

    // Applicant signs in with their new Access Key + Birthdate
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)->handle($accessKey, '2007-05-14');

    $marriedInquiry = ApplicantOfficeInquiry::query()->findOrFail($inquiryId);
    expect((int) $marriedInquiry->created_by_user_id)->toBe((int) $applicantUser->id)
        ->and($marriedInquiry->guest_token_hash)->toBeNull();

    $firstMessage = ApplicantOfficeInquiryMessage::query()
        ->where('applicant_office_inquiry_id', $inquiryId)
        ->where('sequence', 1)
        ->firstOrFail();
    expect((int) $firstMessage->sender_user_id)->toBe((int) $applicantUser->id);

    // Married thread is now visible in the authenticated Applicant Portal Messages hub (/applicant/admission-support?group=offices)
    actingAsMessagingActor($this, $applicantUser)
        ->get(route('applicant.admission-support', ['group' => 'offices']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/AdmissionSupport')
            ->where('active_group', 'offices')
            ->where('inquiries.0.id', (string) $inquiryId)
            ->where('inquiries.0.subject', 'Document Requirements (Form 138, PSA, TOR)')
        );
});
