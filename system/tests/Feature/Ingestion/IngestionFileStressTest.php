<?php

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Ingestion\Actions\CommitAllInOneIngestionBatch;
use App\Ingestion\Actions\StageImportBatch;
use App\Ingestion\Services\ErrorReportGenerator;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';

uses(RefreshDatabase::class);

function stressImportEnvironment(): array
{
    $institution = Institution::query()->create([
        'code' => 'SYN-INST-001',
        'name' => 'Stress Test Institution',
    ]);
    Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'BSCS',
        'name' => 'Bachelor of Science in Computer Science',
        'is_active' => true,
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'STRESS-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    $areas = [
        ['code' => 'MATH', 'name' => 'Mathematics', 'max_items' => 50],
        ['code' => 'VERBAL', 'name' => 'Verbal Aptitude', 'max_items' => 100],
        ['code' => 'QUANT', 'name' => 'Numerical Aptitude', 'max_items' => 100],
        ['code' => 'LOGIC', 'name' => 'General Ability', 'max_items' => 25],
        ['code' => 'SPATIAL', 'name' => 'Spatial Aptitude', 'max_items' => 25],
        ['code' => 'MEMORY', 'name' => 'Perceptual Aptitude', 'max_items' => 25],
        ['code' => 'LANGUAGE', 'name' => 'Manual Dexterity', 'max_items' => 25],
    ];
    foreach ($areas as $data) {
        $area = AptitudeArea::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'max_items' => $data['max_items'],
            'scoring_method' => AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
            'is_active' => true,
        ]);
        $area->conversionRows()->create([
            'min_score' => 0,
            'max_score' => $data['max_items'],
            'output_value' => 'Average',
        ]);
    }
    $actor = User::factory()->create();
    foreach ([CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT, CapabilityCatalog::GUIDANCE_SCORING_IMPORT] as $capability) {
        Permission::findOrCreate($capability, 'web');
        $actor->givePermissionTo($capability);
    }

    return compact('institution', 'cycle', 'actor');
}

function stressTemporaryFile(string $extension, string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'nexiam_import_stress_').'.'.$extension;
    file_put_contents($path, $contents);

    return $path;
}

function stressUploadedFile(string $path, string $name, string $mime): UploadedFile
{
    return new UploadedFile($path, $name, $mime, null, true);
}

function stressWorkbook(string $extension, array $sheets): string
{
    $workbook = new Spreadsheet;
    $workbook->removeSheetByIndex(0);

    foreach ($sheets as $name => $rows) {
        $sheet = $workbook->createSheet();
        $sheet->setTitle($name);
        $sheet->fromArray($rows);
    }

    $path = tempnam(sys_get_temp_dir(), 'nexiam_import_stress_').'.'.$extension;
    $writer = $extension === 'xls' ? new Xls($workbook) : new Xlsx($workbook);
    $writer->save($path);
    $workbook->disconnectWorksheets();

    return $path;
}

test('stages realistic CSV dialects without corrupting quoted or aliased values', function (string $delimiter, string $prefix, string $extension): void {
    $env = stressImportEnvironment();
    $headers = ['Given Name', 'Surname', 'DOB', 'Gender', 'Program Applied', 'Email Address', 'Address', 'Strand', 'School'];
    $values = [' Ana María ', "Dela {$delimiter} Cruz", '2005-02-03', 'Female', 'BSCS', 'ana@example.test', 'Unit 4, North Road', 'STEM', 'North High'];
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $headers, $delimiter);
    fputcsv($stream, $values, $delimiter);
    rewind($stream);
    $contents = $prefix.stream_get_contents($stream).PHP_EOL;
    fclose($stream);
    $path = stressTemporaryFile($extension, $contents);

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'real-world.'.$extension, 'text/plain'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_APPLICANTS_ONLY,
        $env['actor'],
    );

    expect($batch->total_rows)->toBe(1)
        ->and($batch->valid_rows)->toBe(1)
        ->and($batch->rows->sole()->normalized_payload)->toMatchArray([
            'first_name' => 'Ana María',
            'last_name' => "Dela {$delimiter} Cruz",
            'birthdate' => '2005-02-03',
            'sex' => 'Female',
            'course_preference_1' => 'BSCS',
            'contact_email' => 'ana@example.test',
            'address_line' => 'Unit 4, North Road',
        ]);

    unlink($path);
})->with([
    'comma CSV with UTF-8 BOM' => [',', "\xEF\xBB\xBF", 'csv'],
    'semicolon CSV' => [';', '', 'csv'],
    'tab-delimited TXT' => ["\t", '', 'txt'],
]);

test('inspects and stages modern and legacy Excel workbooks with selectable sheets', function (string $extension, string $mime): void {
    $env = stressImportEnvironment();
    $kitVersion = createTestActiveKitVersionFor($this, $env['actor'], $env['institution'], $env['cycle'], 'STRESS-WORKBOOK');
    $headers = ['first_name', 'last_name', 'birthdate', 'sex', 'course_preference_1', 'contact_email', 'strand', 'last_school_enrolled', 'RAW_A'];
    $path = stressWorkbook($extension, [
        'Instructions' => [['Read me']],
        'Morning' => [$headers, ['Maria', 'Santos', '2005-01-02', 'female', 'BSCS', 'maria@example.test', 'STEM', 'North High', 2]],
        'Afternoon' => [$headers, ['Juan', 'Cruz', '2004-03-04', 'male', 'BSCS', 'juan@example.test', 'ABM', 'South High', 1]],
    ]);

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'sessions.'.$extension, $mime),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_ALL_IN_ONE,
        $env['actor'],
        ['Morning', 'Afternoon'],
        targetKitVersion: $kitVersion,
    );

    expect($batch->topology_meta['sheets'])->toHaveCount(3)
        ->and($batch->total_rows)->toBe(2)
        ->and($batch->valid_rows)->toBe(2)
        ->and($batch->rows->pluck('sheet_name')->all())->toBe(['Morning', 'Afternoon']);

    unlink($path);
})->with([
    'XLSX' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'legacy XLS' => ['xls', 'application/vnd.ms-excel'],
]);

test('classifies mixed invalid rows and preserves valid siblings during staging', function (): void {
    $env = stressImportEnvironment();
    $kitVersion = createTestActiveKitVersionFor($this, $env['actor'], $env['institution'], $env['cycle'], 'STRESS-MIXED');
    $csv = implode("\n", [
        'first_name,last_name,birthdate,sex,course,applicant_number,email,strand,school,RAW_A',
        'Valid,Student,2005-01-01,female,BSCS,REF-1,valid@example.test,STEM,North High,2',
        ',Missing,2005-01-01,male,BSCS,REF-2,missing@example.test,STEM,North High,1',
        'Bad,Date,not-a-date,female,BSCS,REF-3,date@example.test,STEM,North High,1',
        'Bad,Email,2005-01-01,male,BSCS,REF-4,not-an-email,STEM,North High,1',
        'Bad,Score,2005-01-01,female,BSCS,REF-5,score@example.test,STEM,North High,abc',
        'Too,High,2005-01-01,male,BSCS,REF-6,high@example.test,STEM,North High,3',
        'Duplicate,Reference,2005-01-01,female,BSCS,REF-1,duplicate@example.test,STEM,North High,1',
    ]);
    $path = stressTemporaryFile('csv', $csv);

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'mixed.csv', 'text/csv'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_ALL_IN_ONE,
        $env['actor'],
        targetKitVersion: $kitVersion,
    );

    expect($batch->total_rows)->toBe(7)
        ->and($batch->valid_rows)->toBe(1)
        ->and($batch->invalid_rows)->toBe(6)
        ->and($batch->rows->where('status', ImportBatchRow::STATUS_INVALID)->flatMap->errors->all())
        ->toContain(
            'Missing required field: first_name',
            'Invalid date of birth format: not-a-date',
            'Invalid email address format: not-an-email',
            'Score for RAW_A must be numeric',
            'Score for RAW_A exceeds the selected Setup maximum 2.000000',
            'Duplicate applicant reference in batch: REF-1',
        );

    unlink($path);
});

test('scores-only staging rejects rows without a reference or recognized numeric score', function (): void {
    $env = stressImportEnvironment();
    $kitVersion = createTestActiveKitVersionFor($this, $env['actor'], $env['institution'], $env['cycle'], 'STRESS-SCORES');
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $env['cycle']->id,
        'code' => 'EX-STRESS-SCORES',
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'test_kit_version_id' => $kitVersion->id,
        'test_instrument_version_id' => $kitVersion->test_instrument_version_id,
        'version' => 1,
    ]);
    $path = stressTemporaryFile('csv', implode("\n", [
        'applicant_number,RAW_A',
        ',2',
        'REF-2,',
        'REF-3,forty',
        'REF-4,2',
    ]));

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'scores.csv', 'text/csv'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_SCORES_ONLY,
        $env['actor'],
        targetExamSession: $session,
        targetKitVersion: $kitVersion,
    );

    expect($batch->valid_rows)->toBe(1)
        ->and($batch->invalid_rows)->toBe(3)
        ->and($batch->rows->where('status', ImportBatchRow::STATUS_INVALID)->flatMap->errors->all())
        ->toContain(
            'Missing required field: applicant_number',
            'At least one recognized aptitude score is required',
            'Score for RAW_A must be numeric',
        );

    unlink($path);
});

test('analyze endpoint reports empty files and rejects unsupported, oversized, and corrupt uploads', function (): void {
    $env = stressImportEnvironment();
    $validPath = stressTemporaryFile('csv', "first_name,last_name,birthdate,sex,course\nAna,Reyes,2005-01-01,female,BSCS\n");

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => stressUploadedFile($validPath, 'valid.csv', 'text/csv'),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('total_rows', 1)
        ->assertJsonPath('checks.2.status', 'pass');

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => UploadedFile::fake()->createWithContent('payload.pdf', '%PDF-1.7'),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => UploadedFile::fake()->create('oversized.csv', 25_601, 'text/csv'),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => UploadedFile::fake()->createWithContent('empty.csv', ''),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('total_rows', 0)
        ->assertJsonPath('checks.2.status', 'fail');

    $this->actingAs($env['actor'])
        ->post(route('imports.stage'), [
            'file' => UploadedFile::fake()->createWithContent('empty.csv', ''),
            'import_type' => ImportBatch::TYPE_APPLICANTS_ONLY,
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
    expect(ImportBatch::query()->count())->toBe(0);

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => UploadedFile::fake()->createWithContent('corrupt.xlsx', 'not an Excel ZIP container'),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    unlink($validPath);
});

test('rejects a workbook when selected sheet names contain no importable rows', function (): void {
    $env = stressImportEnvironment();
    $path = stressWorkbook('xlsx', [
        'Morning' => [
            ['First Name', 'Last Name', 'Birthdate', 'Sex', 'Course', 'Email', 'Strand', 'School'],
            ['Maria', 'Santos', '2005-01-02', 'female', 'BSCS', 'maria@example.test', 'STEM', 'North High'],
        ],
    ]);

    expect(fn () => app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'sessions.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_APPLICANTS_ONLY,
        $env['actor'],
        ['Unknown Sheet'],
    ))->toThrow(InvalidArgumentException::class, 'selected sheets do not contain');
    expect(ImportBatch::query()->count())->toBe(0);

    unlink($path);
});

test('rejected-row CSV neutralizes spreadsheet formulas in exported cells', function (): void {
    $env = stressImportEnvironment();
    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_APPLICANTS_ONLY,
        'file_name' => 'unsafe.csv',
        'file_hash' => 'unsafe-hash',
        'batch_fingerprint' => 'unsafe-fingerprint',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 1,
        'invalid_rows' => 1,
        'created_by_user_id' => $env['actor']->id,
    ]);
    ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => '=1+1',
        'row_index' => 2,
        'idempotency_key' => 'unsafe-row',
        'status' => ImportBatchRow::STATUS_INVALID,
        'reference_code' => '=HYPERLINK(\"https://example.test\")',
        'raw_payload' => ['first_name' => '=cmd'],
        'normalized_payload' => [],
        'errors' => ['+invalid'],
    ]);

    $response = app(ErrorReportGenerator::class)->generate($batch);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain("'=1+1")
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain("'+invalid");
});

test('stages a thousand-row CSV with stable unique row keys and deduplicates an exact replay', function (): void {
    $env = stressImportEnvironment();
    $lines = ['first_name,last_name,birthdate,sex,course,applicant_number,email,strand,school'];
    for ($index = 1; $index <= 1000; $index++) {
        $lines[] = "Candidate{$index},Load,2005-01-01,female,BSCS,LOAD-{$index},candidate{$index}@example.test,STEM,Load High";
    }
    $path = stressTemporaryFile('csv', implode("\n", $lines));
    $file = stressUploadedFile($path, 'load.csv', 'text/csv');
    $action = app(StageImportBatch::class);

    $first = $action->handle($file, $env['institution'], $env['cycle'], ImportBatch::TYPE_APPLICANTS_ONLY, $env['actor']);
    $replay = $action->handle($file, $env['institution'], $env['cycle'], ImportBatch::TYPE_APPLICANTS_ONLY, $env['actor']);

    expect($first->total_rows)->toBe(1000)
        ->and($first->valid_rows)->toBe(1000)
        ->and($first->rows->pluck('idempotency_key')->unique())->toHaveCount(1000)
        ->and($replay->id)->toBe($first->id)
        ->and(ImportBatch::query()->count())->toBe(1)
        ->and(ImportBatchRow::query()->count())->toBe(1000);

    unlink($path);
});

test('stages spreadsheet with leading empty row, title banner, interspersed empty rows, and preserves physical row numbers', function (): void {
    $env = stressImportEnvironment();
    $path = stressWorkbook('xlsx', [
        'Cohort' => [
            1 => [null, null, null, null, null, null, null],
            2 => ['UNIVERSITY ADMISSION APPLICANTS BATCH 2026', null, null, null, null, null, null],
            3 => [null, null, null, null, null, null, null],
            4 => ['First Name', 'Last Name', 'Birthdate', 'Sex', 'Course', 'Email', 'Strand', 'School'],
            5 => [null, null, null, null, null, null, null, null],
            6 => ['Maria', 'Santos', '2005-04-12', 'female', 'BSCS', 'maria@example.test', 'STEM', 'North High'],
            7 => ['   ', " \u{00A0} ", null, null, null, null, null, null],
            8 => ['Juan', 'Dela Cruz', '2004-11-08', 'male', 'BSCS', 'juan@example.test', 'ABM', 'South High'],
            9 => [null, null, null, null, null, null, null, null],
        ],
    ]);

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'spaced_cohort.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_APPLICANTS_ONLY,
        $env['actor'],
    );
    expect($batch->total_rows)->toBe(2)
        ->and($batch->valid_rows)->toBe(2)
        ->and($batch->rows->pluck('row_index')->all())->toBe([6, 8])
        ->and($batch->rows->first()->normalized_payload['first_name'])->toBe('Maria')
        ->and($batch->rows->last()->normalized_payload['first_name'])->toBe('Juan');

    unlink($path);
});

test('handles accented unicode characters, non-breaking spaces, curly quotes, and multiline addresses', function (): void {
    $env = stressImportEnvironment();
    $csvContent = implode("\n", [
        'First Name,Last Name,Birthdate,Sex,Course,Email,Strand,School,Address',
        "\" José Niño \",\"Dela Peña\",\"2005-05-15\",\"female\",\"BSCS\",\"jose@example.test\",\"STEM\",\"St. Jude\",\"\"\"Building 4\"\", Floor 2\nBarangay San José\nCity of Manila\"",
    ]);
    $path = stressTemporaryFile('csv', $csvContent);

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'unicode.csv', 'text/csv'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_APPLICANTS_ONLY,
        $env['actor'],
    );

    expect($batch->total_rows)->toBe(1)
        ->and($batch->valid_rows)->toBe(1);

    $payload = $batch->rows->sole()->normalized_payload;
    expect($payload['first_name'])->toBe('José Niño')
        ->and($payload['last_name'])->toBe('Dela Peña')
        ->and($payload['address_line'])->toContain('Building 4')
        ->and($payload['address_line'])->toContain('Barangay San José');

    unlink($path);
});

test('evaluates calculated formulas and native Excel numeric dates during staging', function (): void {
    $env = stressImportEnvironment();
    $workbook = new Spreadsheet;
    $sheet = $workbook->getActiveSheet();
    $sheet->setTitle('FormulaSheet');
    $sheet->setCellValue('A1', 'First Name');
    $sheet->setCellValue('B1', 'Last Name');
    $sheet->setCellValue('C1', 'Birthdate');
    $sheet->setCellValue('D1', 'Sex');
    $sheet->setCellValue('E1', 'Course');
    $sheet->setCellValue('F1', 'Email');
    $sheet->setCellValue('G1', 'Strand');
    $sheet->setCellValue('H1', 'School');
    $sheet->setCellValue('I1', 'RAW_A');

    // Row 2: calculated formulas and date serial
    $sheet->setCellValue('A2', '=UPPER("Maria")');
    $sheet->setCellValue('B2', 'Santos');
    $sheet->setCellValue('C2', 38454); // 2005-04-12
    $sheet->getStyle('C2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    $sheet->setCellValue('D2', 'female');
    $sheet->setCellValue('E2', 'BSCS');
    $sheet->setCellValue('F2', 'maria@example.test');
    $sheet->setCellValue('G2', 'STEM');
    $sheet->setCellValue('H2', 'North High');
    $sheet->setCellValue('I2', '=1+1'); // 2

    $path = tempnam(sys_get_temp_dir(), 'formula_test_').'.xlsx';
    $writer = new Xlsx($workbook);
    $writer->save($path);
    $workbook->disconnectWorksheets();

    $kitVersion = createTestActiveKitVersionFor($this, $env['actor'], $env['institution'], $env['cycle'], 'STRESS-FORMULA');
    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'formulas.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_ALL_IN_ONE,
        $env['actor'],
        targetKitVersion: $kitVersion,
    );

    expect($batch->total_rows)->toBe(1)
        ->and($batch->valid_rows)->toBe(1);

    $payload = $batch->rows->sole()->normalized_payload;
    expect($payload['first_name'])->toBe('MARIA')
        ->and($payload['birthdate'])->toBe('2005-04-12')
        ->and($payload['raw_a'])->toBe('2');

    unlink($path);
});

test('rejects spreadsheet with empty column header with an actionable error message', function (): void {
    $env = stressImportEnvironment();
    $path = stressWorkbook('xlsx', [
        'Students' => [
            ['First Name', '', 'Last Name', 'Birthdate', 'Sex', 'Course', 'Strand', 'School'],
            ['Maria', 'G.', 'Santos', '2005-01-01', 'female', 'BSCS', 'STEM', 'North High'],
        ],
    ]);

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => stressUploadedFile($path, 'empty_col.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file')
        ->assertJsonFragment([
            'file' => ['Sheet "Students" has an empty column header in column B (Row 1).'],
        ]);

    unlink($path);
});

test('rejects spreadsheet with duplicate mapped column aliases with an actionable error message', function (): void {
    $env = stressImportEnvironment();
    $path = stressWorkbook('xlsx', [
        'Students' => [
            ['First Name', 'Given Name', 'Last Name', 'Birthdate', 'Sex', 'Course', 'Strand', 'School'],
            ['Maria', 'Maria', 'Santos', '2005-01-01', 'female', 'BSCS', 'STEM', 'North High'],
        ],
    ]);

    $this->actingAs($env['actor'])
        ->post(route('imports.analyze'), [
            'file' => stressUploadedFile($path, 'duplicate_col.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'academic_cycle_id' => $env['cycle']->id,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file')
        ->assertJsonFragment([
            'file' => ['Sheet "Students" has duplicate columns (A and B) both mapping to "first_name".'],
        ]);

    unlink($path);
});

test('applicant-only upload stages a reviewable batch before creating applicants', function (): void {
    $env = stressImportEnvironment();
    $csv = implode("\n", [
        'first_name,last_name,birthdate,sex,course,contact_email,strand,school',
        'Auto,Applicant,2005-01-01,female,BSCS,auto@example.test,STEM,Central High',
    ]);
    $path = stressTemporaryFile('csv', $csv);

    $this->actingAs($env['actor'])
        ->post(route('imports.stage'), [
            'file' => stressUploadedFile($path, 'auto_apps.csv', 'text/csv'),
            'import_type' => ImportBatch::TYPE_APPLICANTS_ONLY,
            'academic_cycle_id' => $env['cycle']->id,
            'auto_commit' => '1',
        ])
        ->assertRedirect();

    $batch = ImportBatch::query()->latest('id')->firstOrFail();
    $rowIds = $batch->rows()->where('status', 'valid')->pluck('id')->all();
    expect($batch->status)->toBe(ImportBatch::STATUS_STAGED)
        ->and(ApplicantIdentity::query()->count())->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe(0);

    $this->actingAs($env['actor'])
        ->post(route('imports.commit', $batch), ['selected_row_ids' => $rowIds])
        ->assertRedirect();

    expect(AdmissionAttempt::query()->count())->toBe(1)
        ->and(ApplicantIdentity::query()->where('first_name', 'Auto')->exists())->toBeTrue();

    unlink($path);
});

test('score-only upload stages reviewable rows before score records are written', function (): void {
    $env = stressImportEnvironment();
    $kitVersion = createTestActiveKitVersionFor($this, $env['actor'], $env['institution'], $env['cycle'], 'STRESS-SCORES-UI');
    $room = Room::query()->create([
        'institution_id' => $env['institution']->id,
        'code' => 'RM-STRESS',
        'name' => 'Stress Test Room',
        'space_kind' => SpaceKind::Room,
        'capacity' => 50,
        'is_active' => true,
    ]);

    $batch = ImportBatch::query()->create([
        'institution_id' => $env['institution']->id,
        'academic_cycle_id' => $env['cycle']->id,
        'policy_version' => 1,
        'import_type' => ImportBatch::TYPE_ALL_IN_ONE,
        'file_name' => 'init.csv',
        'file_hash' => 'init-hash',
        'batch_fingerprint' => 'init-fp',
        'status' => ImportBatch::STATUS_STAGED,
        'total_rows' => 1,
        'valid_rows' => 1,
        'created_by_user_id' => $env['actor']->id,
        'topology_meta' => [
            'assessment_selection' => [
                'test_kit_version_id' => $kitVersion->id,
                'norm_package_version_id' => null,
            ],
        ],
    ]);
    $initialRow = ImportBatchRow::query()->create([
        'import_batch_id' => $batch->id,
        'sheet_name' => 'Sheet1',
        'row_index' => 2,
        'idempotency_key' => 'init-row-key',
        'status' => ImportBatchRow::STATUS_VALID,
        'raw_payload' => [],
        'normalized_payload' => [
            'first_name' => 'Cognitive',
            'last_name' => 'Candidate',
            'birthdate' => '2005-01-01',
            'sex' => 'female',
            'course_preference_1' => 'BSCS',
            'RAW_A' => '1',
        ],
    ]);
    $initialCommit = app(CommitAllInOneIngestionBatch::class)->handle($batch, [], $env['actor'], app(CapabilityAuthorizer::class));
    expect($initialCommit->imported_rows)->toBe(1)
        ->and($initialCommit->failed_rows)->toBe(0)
        ->and($initialRow->fresh()->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and($initialRow->fresh()->errors)->toBeNull();
    $attempt = AdmissionAttempt::query()->sole();

    $targetAssignment = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->active()
        ->firstOrFail();
    $targetExamSessionId = (int) $targetAssignment->exam_session_id;
    $administrationId = AssessmentAdministration::query()
        ->where('exam_assignment_id', $targetAssignment->id)
        ->active()
        ->value('id');
    $scoresCsv = implode("\n", [
        'applicant_number,RAW_A',
        "{$attempt->reference_code},2",
    ]);
    $scoresPath = stressTemporaryFile('csv', $scoresCsv);
    $this->actingAs($env['actor'])
        ->post(route('imports.stage'), [
            'file' => stressUploadedFile($scoresPath, 'scores.csv', 'text/csv'),
            'import_type' => ImportBatch::TYPE_SCORES_ONLY,
            'academic_cycle_id' => $env['cycle']->id,
            'target_exam_session_id' => $targetExamSessionId,
            'test_kit_version_id' => $kitVersion->id,
            'auto_commit' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $scoreBatch = ImportBatch::query()->latest('id')->firstOrFail();
    expect($scoreBatch->status)->toBe(ImportBatch::STATUS_STAGED)
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $administrationId)->where('is_current', true)->value('raw_value'))->toBe('1.000000')
        ->and(ScoreRecord::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
    $stagedScoreRow = $scoreBatch->rows()->sole();
    $this->assertSame(
        ImportBatchRow::STATUS_VALID,
        $stagedScoreRow->status,
        json_encode($stagedScoreRow->errors, JSON_THROW_ON_ERROR),
    );

    $this->actingAs($env['actor'])
        ->post(route('imports.commit', $scoreBatch), [
            'selected_row_ids' => $scoreBatch->rows()->where('status', ImportBatchRow::STATUS_VALID)->pluck('id')->all(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $committedScoreRow = $scoreBatch->rows()->sole()->fresh();
    expect($committedScoreRow->status)->toBe(ImportBatchRow::STATUS_IMPORTED)
        ->and($committedScoreRow->errors)->toBeNull()
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $administrationId)->where('is_current', true)->value('raw_value'))->toBe('2.000000')
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $administrationId)->where('source', AssessmentRawResultRevision::SOURCE_IMPORT)->exists())->toBeTrue();

    unlink($scoresPath);
});

test('stages exact kit raw metric values and enforces the profile maximum', function (): void {
    $env = stressImportEnvironment();
    $kitVersion = createTestActiveKitVersionFor($this, $env['actor'], $env['institution'], $env['cycle'], 'STRESS-ALIASES');
    $csv = implode("\n", [
        'first_name,last_name,birthdate,sex,course,contact_email,strand,school,RAW_A',
        'Valid,Cognitive,2005-01-01,female,BSCS,valid@example.test,STEM,North High,2',
        'Exceeded,Cognitive,2005-01-01,male,BSCS,exceeded@example.test,STEM,North High,3',
    ]);
    $path = stressTemporaryFile('csv', $csv);

    $batch = app(StageImportBatch::class)->handle(
        stressUploadedFile($path, 'aliases.csv', 'text/csv'),
        $env['institution'],
        $env['cycle'],
        ImportBatch::TYPE_ALL_IN_ONE,
        $env['actor'],
        targetKitVersion: $kitVersion,
    );

    expect($batch->total_rows)->toBe(2)
        ->and($batch->valid_rows)->toBe(1)
        ->and($batch->invalid_rows)->toBe(1);

    $failingRow = $batch->rows->last();
    expect($failingRow->status)->toBe(ImportBatchRow::STATUS_INVALID)
        ->and($failingRow->errors)->toContain('Score for RAW_A exceeds the selected Setup maximum 2.000000');

    unlink($path);
});
