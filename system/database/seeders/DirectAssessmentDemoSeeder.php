<?php

namespace Database\Seeders;

use App\Ingestion\Actions\StageImportBatch;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Ingestion\ImportBatch;
use App\Models\User;
use App\Setup\Services\IntakeFieldPolicyRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DirectAssessmentDemoSeeder extends Seeder
{
    /**
     * @var list<array{reference: string, first_name: string, last_name: string, birthdate: string, sex: string, email: string, phone: string, strand: string, school: string, program: string}>
     */
    private const DIRECT_ASSESSMENT_APPLICANTS = [
        ['reference' => 'APP-DIR-MIKA', 'first_name' => 'Mika', 'last_name' => 'Santos', 'birthdate' => '2005-02-14', 'sex' => 'female', 'email' => 'direct.assessment.mika@nexiam.test', 'phone' => '09171234001', 'strand' => 'STEM', 'school' => 'Synthetic Senior High School', 'program' => 'BSIT'],
        ['reference' => 'APP-DIR-PAOLO', 'first_name' => 'Paolo', 'last_name' => 'Reyes', 'birthdate' => '2004-07-22', 'sex' => 'male', 'email' => 'direct.assessment.paolo@nexiam.test', 'phone' => '09171234002', 'strand' => 'ABM', 'school' => 'Synthetic Senior High School', 'program' => 'BA-Psych'],
        ['reference' => 'APP-DIR-ALTHEA', 'first_name' => 'Althea', 'last_name' => 'Garcia', 'birthdate' => '2005-11-03', 'sex' => 'female', 'email' => 'direct.assessment.althea@nexiam.test', 'phone' => '09171234003', 'strand' => 'HUMSS', 'school' => 'Synthetic Senior High School', 'program' => 'BSIT'],
    ];

    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'SYN-INST-001'],
            ['name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College')],
        );

        $cycle = AcademicCycle::query()->updateOrCreate(
            ['code' => 'SYN-CYCLE-2026'],
            [
                'institution_id' => $institution->id,
                'name' => '2025-2026 1st Semester',
                'school_year' => '2025-2026',
                'semester' => '1',
                'opens_on' => '2026-08-01',
                'closes_on' => '2026-12-31',
                'is_active' => true,
                'allow_guidance_all_in_one_intake' => false,
            ],
        );

        $this->call([
            ReferenceCatalogSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            IntakeRequirementSeeder::class,
        ]);

        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', 'SYN-WINDOW-2026-A')
            ->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        app(IntakeFieldPolicyRegistry::class)->getOrSeedForCycle($institution, $cycle);

        foreach (self::DIRECT_ASSESSMENT_APPLICANTS as $applicant) {
            $this->ensureAcceptedApplicant($institution, $cycle, $window, $applicant);
        }

        $this->stagePendingApprovalBatch($institution, $cycle, $guidance);
    }

    /**
     * @param  array{reference: string, first_name: string, last_name: string, birthdate: string, sex: string, email: string, phone: string, strand: string, school: string, program: string}  $applicant
     */
    private function ensureAcceptedApplicant(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        array $applicant,
    ): void {
        $identity = ApplicantIdentity::query()->updateOrCreate(
            [
                'institution_id' => $institution->id,
                'normalized_key' => hash('sha256', 'direct-assessment-demo:'.$applicant['email']),
            ],
            [
                'first_name' => $applicant['first_name'],
                'last_name' => $applicant['last_name'],
                'birthdate' => $applicant['birthdate'],
                'sex' => $applicant['sex'],
                'email' => $applicant['email'],
                'phone' => $applicant['phone'],
            ],
        );

        $attempt = AdmissionAttempt::query()->updateOrCreate(
            ['reference_code' => $applicant['reference']],
            [
                'applicant_identity_id' => $identity->id,
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'application_window_id' => $window->id,
                'status' => AdmissionAttempt::STATUS_ACCEPTED,
                'preferred_program_code' => $applicant['program'],
                'program_preference_1_code' => $applicant['program'],
                'applicant_type' => 'new',
                'last_school_enrolled' => $applicant['school'],
                'strand' => $applicant['strand'],
                'gwa' => null,
                'contact_email' => $applicant['email'],
                'contact_phone' => $applicant['phone'],
                'accepted_at' => now(),
                'version' => 1,
            ],
        );

        if ($attempt->examAssignments()->exists()) {
            throw new RuntimeException("Direct Assessment demo applicant {$applicant['email']} must not already have an exam assignment.");
        }
    }

    private function stagePendingApprovalBatch(Institution $institution, AcademicCycle $cycle, User $guidance): void
    {
        $base = tempnam(sys_get_temp_dir(), 'nexiam-direct-assessment-');

        if ($base === false) {
            throw new RuntimeException('Unable to create temporary All-in-One demo import file.');
        }
        $path = $base.'.csv';

        try {
            $file = fopen($path, 'wb');

            if ($file === false) {
                throw new RuntimeException('Unable to open temporary All-in-One demo import file.');
            }

            fputcsv($file, [
                'applicant_number', 'first_name', 'last_name', 'middle_name', 'birthdate', 'sex', 'contact_email', 'contact_phone',
                'strand', 'last_school_enrolled', 'course_preference_1', 'course_preference_2', 'course_preference_3',
                'VERBAL', 'QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE',
            ]);
            fputcsv($file, ['DA-IMPORT-1001', 'Nina', 'Cruz', 'Mae', '2005-04-12', 'female', 'nina.cruz.import@nexiam.test', '09171234101', 'STEM', 'Synthetic Senior High School', 'BSIT', 'BA-Psych', '', 88, 84, 22, 21, 20, 23]);
            fputcsv($file, ['DA-IMPORT-1002', 'Marco', 'Lim', 'Jose', '2004-09-08', 'male', 'marco.lim.import@nexiam.test', '09171234102', 'ABM', 'Synthetic Senior High School', 'BA-Psych', 'BSIT', '', 75, 78, 20, 22, 19, 21]);
            fputcsv($file, ['DA-IMPORT-1003', 'Sofia', 'Tan', 'Lee', '2005-01-26', 'female', 'sofia.tan.import@nexiam.test', '09171234103', 'HUMSS', 'Synthetic Senior High School', 'BSIT', 'BA-Psych', '', 92, 85, 24, 23, 22, 24]);
            fclose($file);

            $batch = app(StageImportBatch::class)->handle(
                file: new UploadedFile($path, 'direct_assessment_interoffice_approval.csv', 'text/csv', null, true),
                institution: $institution,
                cycle: $cycle,
                importType: ImportBatch::TYPE_ALL_IN_ONE,
                actor: $guidance,
            );

            if ($batch->status !== ImportBatch::STATUS_PENDING_APPROVAL || $batch->total_rows !== 3 || $batch->valid_rows !== 3) {
                throw new RuntimeException("Direct Assessment demo import state mismatch: status={$batch->status}, total={$batch->total_rows}, valid={$batch->valid_rows}");
            }
        } finally {
            unlink($path);
        }
    }
}
