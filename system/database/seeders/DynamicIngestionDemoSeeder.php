<?php

namespace Database\Seeders;

use App\Authorization\CapabilityAuthorizer;
use App\Ingestion\Actions\CommitAllInOneIngestionBatch;
use App\Ingestion\Actions\StageImportBatch;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Ingestion\ImportBatch;
use App\Models\User;
use App\Setup\Services\IntakeFieldPolicyRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DynamicIngestionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first();

        if ($institution === null) {
            return;
        }

        $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first()
            ?? AcademicCycle::query()->where('institution_id', $institution->id)->first();

        if ($cycle === null) {
            return;
        }

        // 1. Seed or resolve IntakeFieldPolicy v1 for the cycle
        app(IntakeFieldPolicyRegistry::class)->getOrSeedForCycle($institution, $cycle);

        // 2. Generate fixture directory
        $dir = storage_path('app/demo_fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 3. Create sample All-in-One CSV
        $csvPath = $dir.'/sample_all_in_one_examinees.csv';
        $handle = fopen($csvPath, 'w');
        fputcsv($handle, [
            'applicant_number',
            'first_name',
            'last_name',
            'middle_name',
            'birthdate',
            'sex',
            'contact_email',
            'contact_phone',
            'strand',
            'last_school_enrolled',
            'course_preference_1',
            'course_preference_2',
            'course_preference_3',
            'VERBAL',
            'QUANT',
            'LOGIC',
            'SPATIAL',
            'MEMORY',
            'LANGUAGE',
        ]);

        $candidates = [
            ['APP-2026-1001', 'Bea', 'Alonzo', 'Cruz', '2005-03-14', 'female', 'bea.alonzo@example.test', '09171234501', 'STEM', 'Manila Science HS', 'BSIT', 'BA-Psych', 'BSEd-Eng', 88, 84, 22, 21, 20, 23],
            ['APP-2026-1002', 'Daniel', 'Padilla', 'Ford', '2004-11-20', 'male', 'daniel.padilla@example.test', '09171234502', 'ABM', 'Quezon City HS', 'BSBA', 'BSEd-Eng', 'BA-Psych', 75, 78, 20, 22, 19, 21],
            ['APP-2026-1003', 'Kathryn', 'Bernardo', 'Manuel', '2005-01-26', 'female', 'kathryn.bernardo@example.test', '09171234503', 'HUMSS', 'Pasig City HS', 'BA-Psych', 'BSEd-Eng', 'BSBA', 92, 85, 24, 23, 22, 24],
            ['APP-2026-1004', 'Enrique', 'Gil', 'Bacay', '2004-08-19', 'male', 'enrique.gil@example.test', '09171234504', 'GAS', 'Makati High School', 'BSIT', 'BSBA', 'BA-Psych', 70, 72, 19, 18, 21, 20],
            ['APP-2026-1005', 'Liza', 'Soberano', 'Hope', '2005-05-04', 'female', 'liza.soberano@example.test', '09171234505', 'STEM', 'Taguig Science HS', 'BSBA', 'BA-Psych', 'BSEd-Eng', 91, 89, 23, 24, 25, 22],
        ];

        foreach ($candidates as $c) {
            fputcsv($handle, $c);
        }
        fclose($handle);

        // 4. Create sample Multi-Session Excel Workbook
        $xlsxPath = $dir.'/sample_multi_session_examinees.xlsx';
        $spreadsheet = new Spreadsheet;

        // Sheet 1
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Morning Session (Room 101)');
        $sheet1->fromArray([
            ['applicant_number', 'first_name', 'last_name', 'birthdate', 'sex', 'course_preference_1', 'strand', 'contact_email', 'last_school_enrolled', 'VERBAL', 'QUANT', 'LOGIC', 'SPATIAL'],
            ['APP-2026-2001', 'Joshua', 'Garcia', '2005-06-18', 'male', 'BSIT', 'STEM', 'joshua.garcia@example.test', 'Batangas National HS', 86, 82, 21, 20],
            ['APP-2026-2002', 'Julia', 'Barretto', '2005-09-10', 'female', 'BA-Psych', 'STEM', 'julia.barretto@example.test', 'Miriam College HS', 90, 85, 23, 22],
        ]);

        // Sheet 2
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Afternoon Session (Room 102)');
        $sheet2->fromArray([
            ['applicant_number', 'first_name', 'last_name', 'birthdate', 'sex', 'course_preference_1', 'strand', 'contact_email', 'last_school_enrolled', 'VERBAL', 'QUANT', 'LOGIC', 'SPATIAL'],
            ['APP-2026-2003', 'Gerald', 'Anderson', '2004-12-07', 'male', 'BSBA', 'ABM', 'gerald.anderson@example.test', 'General Santos HS', 78, 76, 20, 19],
            ['APP-2026-2004', 'Kim', 'Chiu', '2005-04-19', 'female', 'BSAC', 'ABM', 'kim.chiu@example.test', 'Cebu City HS', 85, 84, 22, 21],
        ]);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        // 5. Stage and commit a demonstration batch so the UI shows live data immediately
        $staffUser = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
            ?? User::query()->first();

        if ($staffUser !== null) {
            $uploaded = new UploadedFile($csvPath, 'sample_all_in_one_examinees.csv', 'text/csv', null, true);
            $batch = app(StageImportBatch::class)->handle(
                file: $uploaded,
                institution: $institution,
                cycle: $cycle,
                importType: ImportBatch::TYPE_ALL_IN_ONE,
                actor: $staffUser,
            );

            if ($batch->status === ImportBatch::STATUS_STAGED) {
                app(CommitAllInOneIngestionBatch::class)->handle(
                    batch: $batch,
                    selectedRowIds: [],
                    actor: $staffUser,
                    authorizer: app(CapabilityAuthorizer::class),
                );
            }
        }
    }
}
