<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Str;

function applicantSubmissionGuidancePayload(string $email, string $applicantType): array
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    (new PublicIntakeConsentDemoSeeder)->run();
    $policy = PublicPolicy::designatedIntake((int) Institution::current()->getKey())
        ?? throw new RuntimeException('A designated public intake policy is required.');
    $revision = $policy->publishedRevision
        ?? throw new RuntimeException('The designated policy must have a published revision.');

    return [
        'academic_cycle_id' => $cycle->id,
        'application_window_id' => $window->id,
        'public_policy_id' => $policy->id,
        'public_policy_revision_id' => $revision->id,
        'public_policy_revision_digest' => $revision->contentDigest(),
        'first_name' => $applicantType === 'new' ? 'Freshman' : 'Transferee',
        'last_name' => 'Applicant',
        'email' => $email,
        'birthdate' => $applicantType === 'new' ? '2001-01-01' : '2002-02-02',
        'sex' => 'female',
        'applicant_type' => $applicantType,
        'program_preference_1' => 'BSIT',
        'terms_accepted' => true,
        'last_school_enrolled' => 'Sample Senior High School',
        'strand' => 'STEM',
        'grades' => ['GWA' => '1.50', 'ENG' => '88', 'FIL' => '90', 'MATH' => '85', 'SCI' => '87'],
        'city' => 'Tagudin',
    ];
}

test('confirmation lists active requirements for the applicant track and institution in catalog order', function (): void {
    seedE2Foundation();

    $institution = Institution::current();
    IntakeRequirement::query()
        ->where('institution_id', $institution->id)
        ->where('code', 'good_moral')
        ->update(['is_active' => false]);

    IntakeRequirement::query()->create([
        'institution_id' => $institution->id,
        'code' => 'optional_checklist',
        'label' => 'Optional supporting document',
        'applicant_track' => IntakeRequirement::TRACK_ALL,
        'item_kind' => IntakeRequirement::KIND_DOCUMENT,
        'is_mandatory' => false,
        'is_active' => true,
        'sort_order' => 45,
    ]);

    $otherInstitution = Institution::query()->create([
        'code' => 'SYN-OTHER-001',
        'name' => 'Other Institution',
    ]);
    IntakeRequirement::query()->create([
        'institution_id' => $otherInstitution->id,
        'code' => 'foreign_requirement',
        'label' => 'Foreign institution requirement',
        'applicant_track' => IntakeRequirement::TRACK_ALL,
        'item_kind' => IntakeRequirement::KIND_DOCUMENT,
        'is_mandatory' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $expectedByApplicantType = [
        'new' => [
            'High School Report Card (Form 138 / SF9 - Original)',
            'PSA Birth Certificate (SECPA Original)',
            'Two (2) Identical Passport-size Photos with Nametag',
            'Optional supporting document',
            'One (1) Long Brown Envelope',
            'One (1) Long White Folder',
        ],
        'transferee' => [
            'PSA Birth Certificate (SECPA Original)',
            'Two (2) Identical Passport-size Photos with Nametag',
            'Optional supporting document',
            'One (1) Long Brown Envelope',
            'Official Transcript of Records (TOR)',
            'Transfer Credential / Honorable Dismissal',
        ],
    ];

    foreach ($expectedByApplicantType as $applicantType => $expectedLabels) {
        $this->post(route('apply.submit'), applicantSubmissionGuidancePayload(
            "{$applicantType}@example.test",
            $applicantType,
        ))->assertRedirect(route('apply.confirmation'));

        $this->get(route('apply.confirmation'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admission/Confirmation')
                ->where('intake_requirements', function ($requirements) use ($expectedLabels): bool {
                    return $requirements->pluck('label')->all() === $expectedLabels
                        && $requirements->every(
                            fn (array $requirement): bool => array_keys($requirement) === ['label', 'item_kind', 'is_mandatory'],
                        );
                })
                ->where(
                    'intake_requirements.'.($applicantType === 'new' ? 3 : 2).'.is_mandatory',
                    false,
                )
            );
    }
});

test('confirmation does not fall back to static requirements when the active catalog is empty', function (): void {
    seedE2Foundation();

    IntakeRequirement::query()
        ->where('institution_id', Institution::current()->id)
        ->delete();

    $this->post(route('apply.submit'), applicantSubmissionGuidancePayload('empty-catalog@example.test', 'new'))
        ->assertRedirect(route('apply.confirmation'));

    $this->get(route('apply.confirmation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Confirmation')
            ->where('intake_requirements', [])
        );
});

test('custom school and academic history text remain accepted outside the suggestion catalog', function (): void {
    seedE2Foundation();

    $cases = [
        [
            'email' => 'custom-new-'.Str::uuid().'@example.test',
            'applicant_type' => 'new',
            'first_name' => 'CustomNew'.Str::uuid(),
            'last_school_enrolled' => 'Ilocos Example Senior High School',
            'strand' => 'Technical Professional - Maritime Transport',
        ],
        [
            'email' => 'legacy-new-'.Str::uuid().'@example.test',
            'applicant_type' => 'new',
            'first_name' => 'LegacyNew'.Str::uuid(),
            'last_school_enrolled' => 'Regional Science High School',
            'strand' => 'STEM',
        ],
        [
            'email' => 'custom-transfer-'.Str::uuid().'@example.test',
            'applicant_type' => 'transferee',
            'first_name' => 'CustomTransfer'.Str::uuid(),
            'last_school_enrolled' => 'Independent College of Example',
            'strand' => 'Bachelor of Architecture (Sustainable Design major)',
        ],
    ];

    foreach ($cases as $case) {
        $payload = applicantSubmissionGuidancePayload(
            $case['email'],
            $case['applicant_type'],
        );
        $payload['last_school_enrolled'] = $case['last_school_enrolled'];
        $payload['first_name'] = $case['first_name'];
        $payload['strand'] = $case['strand'];

        $this->post(route('apply.submit'), $payload)
            ->assertRedirect(route('apply.confirmation'));

        $attempt = AdmissionAttempt::query()
            ->where('contact_email', $case['email'])
            ->firstOrFail();

        expect($attempt->last_school_enrolled)->toBe($case['last_school_enrolled'])
            ->and($attempt->strand)->toBe($case['strand']);
    }
});
