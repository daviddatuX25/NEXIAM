<?php

namespace Database\Seeders;

use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;

class JourneyFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();

        app(SubmitAdmissionAttempt::class)->handle(
            $institution,
            $cycle,
            $window,
            new AdmissionIntakeData(
                firstName: 'Journey',
                lastName: 'Applicant',
                birthdate: '2004-06-15',
                sex: 'female',
                contactEmail: 'journey.applicant@example.test',
                applicantType: 'new',
                programPreferenceCodes: ['BSN', 'BSPSY'],
                grades: [
                    'GWA' => '1.85',
                    'ENG' => '90.00',
                    'FIL' => '91.00',
                    'MATH' => '88.00',
                    'SCI' => '89.00',
                ],
                middleName: null,
                suffix: null,
                contactPhone: null,
                lastSchoolEnrolled: 'Tagudin National High School',
                strand: 'STEM',
                addressLine: null,
                city: 'Tagudin',
                province: 'Ilocos Sur',
                zipCode: null,
                termsAccepted: true,
            ),
            'journey-fixture-seeder',
        );
    }
}
