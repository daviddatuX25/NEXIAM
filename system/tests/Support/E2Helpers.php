<?php

use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Database\Seeders\AdmissionSupportDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JourneyFixtureSeeder;
use Database\Seeders\OmrDemoSeeder;

function seedE2Foundation(): void
{
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
}

function seedE2JourneyFoundation(): void
{
    (new JourneyFixtureSeeder)->run();
}

function seedE2OmrFoundation(): void
{
    (new OmrDemoSeeder)->run();
}

function seedE2AdmissionSupportFoundation(): void
{
    (new AdmissionSupportDemoSeeder)->run();
}

function submitSyntheticApplicant(?string $email = null, string $lastName = 'Applicant'): array
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();

    $intake = new AdmissionIntakeData(
        firstName: 'Test',
        lastName: $lastName,
        birthdate: '2001-01-01',
        sex: 'male',
        contactEmail: $email ?? 'applicant.'.uniqid('', true).'@example.test',
        applicantType: 'new',
        programPreferenceCodes: ['BSN'],
        grades: [
            'GWA' => '1.75',
            'ENG' => '88.00',
            'FIL' => '90.00',
            'MATH' => '85.00',
            'SCI' => '87.00',
        ],
        middleName: null,
        suffix: null,
        contactPhone: '09171234567',
        lastSchoolEnrolled: 'Sample Senior High School',
        strand: 'STEM',
        addressLine: '123 Sample St',
        city: 'Tagudin',
        province: 'Ilocos Sur',
        zipCode: '2714',
        termsAccepted: true,
    );

    return app(SubmitAdmissionAttempt::class)->handle(
        $institution,
        $cycle,
        $window,
        $intake,
        'test',
    );
}
