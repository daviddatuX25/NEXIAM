<?php

use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\Reports\Queries\CourseQuotaQualifiersDataQuery;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;

/**
 * @return array{
 *     institution: Institution,
 *     cycle: AcademicCycle,
 *     foreignCycle: AcademicCycle,
 *     attempts: array{0: AdmissionAttempt, 1: AdmissionAttempt, 2: AdmissionAttempt, 3: AdmissionAttempt},
 *     foreignAttempts: array{0: AdmissionAttempt, 1: AdmissionAttempt},
 *     grading: GradingSession
 * }
 */
function reportHistoricalProviderFixture(): array
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $program = Program::query()
        ->where('institution_id', $institution->id)
        ->where('code', 'BSIT')
        ->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
        ['slots' => 5],
    );

    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'REPORT-2025-'.Str::upper(Str::random(8)),
        'name' => 'Academic Year 2025–2026 · First Semester',
        'opens_on' => '2025-01-01',
        'closes_on' => '2025-12-31',
        'is_active' => false,
    ]);

    $attempts = [];
    foreach ([
        ['given' => 'Mara', 'family' => 'CurrentOne', 'reference' => 'RPT-CURRENT-001', 'gwa' => '1.75', 'date' => '2026-09-10', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => 91],
        ['given' => 'Noel', 'family' => 'CurrentTwo', 'reference' => 'RPT-CURRENT-002', 'gwa' => '1.82', 'date' => '2026-09-11', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => 91],
        ['given' => 'Bea', 'family' => 'NursingContrast', 'reference' => 'RPT-BSN-003', 'gwa' => '1.90', 'date' => '2026-09-12', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSN', 'first' => 'BSN', 'score' => 91],
        ['given' => 'Pending', 'family' => 'NotAccepted', 'reference' => 'RPT-PENDING-004', 'gwa' => '1.95', 'date' => null, 'status' => AdmissionAttempt::STATUS_PENDING, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => null],
    ] as $index => $row) {
        $identity = ApplicantIdentity::query()->create([
            'institution_id' => $institution->id,
            'normalized_key' => hash('sha256', 'report-fixture-'.Str::uuid()),
            'first_name' => $row['given'],
            'last_name' => $row['family'],
            'birthdate' => '2001-'.sprintf('%02d', $index + 1).'-01',
            'sex' => 'female',
        ]);
        $attempt = AdmissionAttempt::query()->create([
            'applicant_identity_id' => $identity->id,
            'institution_id' => $institution->id,
            'academic_cycle_id' => $cycle->id,
            'reference_code' => $row['reference'],
            'status' => $row['status'],
            'preferred_program_code' => $row['preferred'],
            'program_preference_1_code' => $row['first'],
            'applicant_type' => 'new',
            'strand' => 'STEM',
            'gwa' => $row['gwa'],
            'version' => 1,
            'accepted_at' => $row['date'],
            'created_at' => '2026-09-01 09:00:00',
            'updated_at' => $row['date'] === null ? '2026-09-01 09:00:00' : $row['date'].' 10:00:00',
        ]);
        $attempts[] = $attempt;
    }

    $foreignAttempts = [];
    foreach ([
        ['given' => 'ForeignOne', 'family' => 'Cycle', 'reference' => 'RPT-FOREIGN-001', 'date' => '2025-09-10', 'score' => 99],
        ['given' => 'ForeignTwo', 'family' => 'Cycle', 'reference' => 'RPT-FOREIGN-002', 'date' => '2025-09-11', 'score' => 98],
    ] as $index => $row) {
        $identity = ApplicantIdentity::query()->create([
            'institution_id' => $institution->id,
            'normalized_key' => hash('sha256', 'report-foreign-fixture-'.Str::uuid()),
            'first_name' => $row['given'],
            'last_name' => $row['family'],
            'birthdate' => '2000-'.sprintf('%02d', $index + 1).'-01',
            'sex' => 'male',
        ]);
        $foreignAttempts[] = AdmissionAttempt::query()->create([
            'applicant_identity_id' => $identity->id,
            'institution_id' => $institution->id,
            'academic_cycle_id' => $foreignCycle->id,
            'reference_code' => $row['reference'],
            'status' => AdmissionAttempt::STATUS_ACCEPTED,
            'preferred_program_code' => 'BSIT',
            'program_preference_1_code' => 'BSIT',
            'applicant_type' => 'new',
            'strand' => 'STEM',
            'gwa' => '1.85',
            'version' => 1,
            'accepted_at' => $row['date'].' 10:00:00',
            'created_at' => '2025-09-01 09:00:00',
            'updated_at' => $row['date'].' 10:00:00',
        ]);
    }

    $exam = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REPORT-HISTORICAL-'.Str::upper(Str::random(8)),
        'status' => ExamSession::STATUS_COMPLETED,
        'test_instrument_version_id' => null,
        'test_kit_version_id' => null,
        'booklet_version_id' => null,
        'seating_arrangement_id' => null,
        'starts_at' => '2026-09-08 08:00:00',
        'effective_end_at' => '2026-09-08 10:00:00',
        'version' => 1,
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $exam->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'version' => 1,
        'opened_at' => '2026-09-08 10:00:00',
        'finalized_at' => '2026-09-09 10:00:00',
    ]);

    foreach ($attempts as $index => $attempt) {
        if ($index < 3) {
            reportHistoricalScoreRows($grading, $attempt, 91);
        }
    }
    $foreignExam = ExamSession::query()->create([
        'academic_cycle_id' => $foreignCycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REPORT-FOREIGN-'.Str::upper(Str::random(8)),
        'status' => ExamSession::STATUS_COMPLETED,
        'test_instrument_version_id' => null,
        'test_kit_version_id' => null,
        'booklet_version_id' => null,
        'seating_arrangement_id' => null,
        'starts_at' => '2025-09-08 08:00:00',
        'effective_end_at' => '2025-09-08 10:00:00',
        'version' => 1,
    ]);
    $foreignGrading = GradingSession::query()->create([
        'exam_session_id' => $foreignExam->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'version' => 1,
        'opened_at' => '2025-09-08 10:00:00',
        'finalized_at' => '2025-09-09 10:00:00',
    ]);
    foreach ($foreignAttempts as $index => $attempt) {
        reportHistoricalScoreRows($foreignGrading, $attempt, $index === 0 ? 99 : 98);
    }

    return compact('institution', 'cycle', 'foreignCycle', 'attempts', 'foreignAttempts', 'grading');
}

function reportHistoricalScoreRows(GradingSession $grading, AdmissionAttempt $attempt, int $score): void
{
    foreach (['VERBAL', 'QUANT'] as $code) {
        $area = AptitudeArea::query()->where('code', $code)->firstOrFail();
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id,
            'score' => $score,
            'source' => ScoreRecord::SOURCE_MANUAL,
            'idempotency_key' => 'report-historical-'.$attempt->id.'-'.$code,
        ]);
    }
}

function reportControllerScoreRows(GradingSession $grading, int $attemptId, float $score): void
{
    foreach (['VERBAL', 'QUANT'] as $code) {
        $area = AptitudeArea::query()->where('code', $code)->firstOrFail();
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $attemptId,
            'aptitude_area_id' => $area->id,
            'score' => $score,
            'source' => ScoreRecord::SOURCE_MANUAL,
            'idempotency_key' => "report-controller-{$grading->id}-{$attemptId}-{$area->id}",
        ]);
    }
}

function reportWordTemplateBytes(string $surfaceKey, string $marker): string
{
    $surface = DocumentSurfaceRegistry::resolve($surfaceKey);
    expect($surface)->not->toBeNull();
    $catalog = $surface->placeholderCatalog()['all'];
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText($marker);
    $scalarKeys = [];
    $repeatingKeys = [];
    foreach ($catalog as $key) {
        if (! is_string($key)) {
            continue;
        }
        if (str_contains($key, '.')) {
            $repeatingKeys[] = $key;
        } else {
            $scalarKeys[] = $key;
        }
    }
    foreach ($scalarKeys as $key) {
        $section->addText('Catalog: {{'.$key.'}}');
    }
    if ($repeatingKeys !== []) {
        $table = $section->addTable();
        $row = $table->addRow();
        // One wide cell (8500 twips) stacks all repeating macros cleanly
        // with readable staff labels, fitting within A4 printable width (9026 twips).
        $cell = $row->addCell(8500);
        foreach ($repeatingKeys as $key) {
            $label = ucwords(str_replace(['.', '_'], [' ', ' '], $key));
            $cell->addText($label.': {{'.$key.'}}');
        }
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'report-word-fixture-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create temporary report DOCX fixture.');
    }
    $docxPath = $temporaryPath.'.docx';
    unlink($temporaryPath);
    try {
        $word->save($docxPath, 'Word2007');
        $bytes = file_get_contents($docxPath);
        if ($bytes === false) {
            throw new RuntimeException('Unable to read generated report DOCX fixture.');
        }

        return $bytes;
    } finally {
        if (is_file($docxPath)) {
            unlink($docxPath);
        }
    }
}

function activeReportBuilderTemplate(string $surfaceKey, string $marker): ReportTemplate
{
    $surfaceObject = DocumentSurfaceRegistry::resolve($surfaceKey);
    expect($surfaceObject)->not->toBeNull();
    $blueprint = $surfaceObject->defaultBlueprint('a4', 'portrait');
    $blueprint['components'][0]['props']['title'] = $marker;
    $blueprint = ResultSheetBuilderSchema::normalize($blueprint);
    $html = app(DocumentBlueprintCompiler::class)->compile($blueprint);

    return ReportTemplate::query()->create([
        'surface_key' => $surfaceKey,
        'name' => $marker,
        'mode' => 'html',
        'source_kind' => 'builder',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'builder_schema_version' => 1,
        'builder_json' => $blueprint,
        'compiler_version' => 'document-blueprint-1',
        'content' => $html,
        'compiled_html' => $html,
        'is_active' => true,
    ]);
}
function reportPdfText(string $pdfBytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'report-pdf-text-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PDF text extraction file.');
    }

    try {
        file_put_contents($path, $pdfBytes);

        return shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null') ?? '';
    } finally {
        @unlink($path);
    }
}

beforeEach(function (): void {
    seedE2Foundation();
});

test('passing report exports require guidance or test administrator authority', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $registrar)->get('/reports/export/passing-report?format=csv')->assertForbidden();
    $this->get('/reports/export/passing-report?format=pdf')->assertForbidden();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=csv')->assertOk();
});

test('passing report CSV has the required columns and emits an immutable audit event', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $response = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=csv');

    $response->assertOk();
    $csvRows = array_map(static fn (string $line): array => str_getcsv($line), explode("\n", trim($response->streamedContent())));
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csvRows[0])->toBe(['Row #', 'Reference Code', 'Applicant Name', 'Sex', 'Strand', 'Program', 'Composite Score', 'Stanine', 'Rating', 'Remarks']);

    $audits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'csv')
        ->get();
    expect($audits)->toHaveCount(1);
    $audit = $audits->first();
    expect($audit->payload['type'])->toBe('passing-report')
        ->and($audit->payload['format'])->toBe('csv');
});

test('passing report DOCX export creates a valid default document when no template is active', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $response = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=docx');
    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $document = $response->streamedContent();
    expect(substr($document, 0, 2))->toBe('PK');
});
test('passing report CSV retains formula-injection protection for provider values', function (): void {
    $fixture = reportHistoricalProviderFixture();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $attempt = $fixture['attempts'][0];
    $attempt->identity->forceFill(['first_name' => '=2+2'])->save();

    $response = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=csv')->assertOk();
    $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\n", trim($response->streamedContent())));
    expect($rows[1][2])->toStartWith("'=2+2");
});

test('report template upload validates placeholder contract and activation is exclusive per surface', function (): void {
    Storage::fake('local');
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $sourcePath = tempnam(sys_get_temp_dir(), 'report-template-test-');
    if ($sourcePath === false) {
        throw new RuntimeException('Unable to create report template fixture file.');
    }
    file_put_contents($sourcePath, reportWordTemplateBytes('passing_report', 'PASSING_DOCX_PDF_MARKER'));
    try {
        $upload = UploadedFile::fake()->createWithContent('passing-template.docx', file_get_contents($sourcePath));
        $first = actingAsAdmissionSupportStaff($this, $guidance)->postJson(route('setup.report-templates.store', ['surface' => 'passing_report']), [
            'name' => 'First template',
            'mode' => 'docx',
            'document' => $upload,
        ])->assertCreated();
        $second = actingAsAdmissionSupportStaff($this, $guidance)->postJson(route('setup.report-templates.store', ['surface' => 'passing_report']), [
            'name' => 'Second template',
            'mode' => 'docx',
            'document' => UploadedFile::fake()->createWithContent('passing-template.docx', file_get_contents($sourcePath)),
        ])->assertCreated();

        $firstTemplate = ReportTemplate::query()->findOrFail($first->json('id'));
        $secondTemplate = ReportTemplate::query()->findOrFail($second->json('id'));
        actingAsAdmissionSupportStaff($this, $guidance)->post(route('setup.report-templates.activate', [
            'surface' => 'passing_report',
            'reportTemplate' => $firstTemplate->id,
        ]))->assertRedirect(route('setup.report-templates.surface', ['surface' => 'passing_report']));
        actingAsAdmissionSupportStaff($this, $guidance)->post(route('setup.report-templates.activate', [
            'surface' => 'passing_report',
            'reportTemplate' => $secondTemplate->id,
        ]))->assertRedirect(route('setup.report-templates.surface', ['surface' => 'passing_report']));

        expect(ReportTemplate::query()->where('surface_key', 'passing_report')->where('is_active', true)->pluck('id')->all())
            ->toBe([$secondTemplate->id]);
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('setup.report-templates.surface', ['surface' => 'passing_report']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setup/ReportTemplates/Surface')
                ->where('templates.0.id', $secondTemplate->id));

        $preview = actingAsAdmissionSupportStaff($this, $guidance)->postJson(route('setup.report-templates.preview', ['surface' => 'passing_report']), [
            'mode' => 'docx',
            'template_id' => $secondTemplate->id,
        ])->assertOk();
        $previewBytes = base64_decode($preview->json('pdf_base64'), true);
        expect($previewBytes)->toBeString()
            ->and(substr($previewBytes, 0, 4))->toBe('%PDF')
            ->and(reportPdfText($previewBytes))->toContain('PASSING_DOCX_PDF_MARKER')
            ->and(reportPdfText($previewBytes))->not->toContain('{{');
        $fixture = reportHistoricalProviderFixture();
        $foreignCycle = $fixture['foreignCycle'];
        $foreignAttempt = $fixture['foreignAttempts'][0];
        $secondForeignAttempt = $fixture['foreignAttempts'][1];
        $docx = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=docx&program_code=BSIT')->assertOk();
        $docxBytes = $docx->streamedContent();
        $docxPath = tempnam(sys_get_temp_dir(), 'report-export-proof-');
        file_put_contents($docxPath, $docxBytes);
        $docxText = app(DocxTextExtractor::class)->text($docxPath);
        @unlink($docxPath);
        expect(substr($docxBytes, 0, 2))->toBe('PK')
            ->and($docxText)->toContain($fixture['attempts'][0]->reference_code)
            ->and($docxText)->toContain($fixture['attempts'][1]->reference_code)
            ->and($docxText)->toContain('Mara CurrentOne')
            ->and($docxText)->toContain('Noel CurrentTwo')
            ->and($docxText)->toContain('91')
            ->and($docxText)->toContain('100.00%')
            ->and($docxText)->not->toContain($fixture['attempts'][2]->reference_code)
            ->and($docxText)->not->toContain($fixture['attempts'][3]->reference_code)
            ->and($docxText)->not->toContain($foreignAttempt->reference_code)
            ->and($docxText)->not->toContain($secondForeignAttempt->reference_code)
            ->and($docxText)->not->toContain('NOA-2026-')
            ->and($docxText)->not->toContain('{{');

        $pdf = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf&program_code=BSIT')->assertOk()->streamedContent();
        $pdfText = reportPdfText($pdf);
        $passingAudits = AuditEvent::query()
            ->where('event_type', 'reports.export_downloaded')
            ->where('actor', (string) $guidance->id)
            ->where('payload->type', 'passing-report')
            ->where('payload->format', 'pdf')
            ->where('payload->template_id', $secondTemplate->id)
            ->where('payload->rendered_pdf_sha256', hash('sha256', $pdf))
            ->get();
        expect($passingAudits)->toHaveCount(1);
        $passingAudit = $passingAudits->first();
        expect(substr($pdf, 0, 4))->toBe('%PDF')
            ->and($pdfText)->toContain('PASSING_DOCX_PDF_MARKER')
            ->and($pdfText)->toContain($fixture['attempts'][0]->reference_code)
            ->and($pdfText)->toContain($fixture['attempts'][1]->reference_code)
            ->and($pdfText)->toContain('Mara CurrentOne')
            ->and($pdfText)->toContain('Noel CurrentTwo')
            ->and($pdfText)->toContain('91')
            ->and($pdfText)->toContain('100.00%')
            ->and($pdfText)->not->toContain($fixture['attempts'][2]->reference_code)
            ->and($pdfText)->not->toContain($foreignAttempt->reference_code)
            ->and($pdfText)->not->toContain($secondForeignAttempt->reference_code)
            ->and($pdfText)->not->toContain('{{')
            ->and($passingAudit->payload)->toMatchArray([
                'type' => 'passing-report',
                'format' => 'pdf',
                'academic_cycle_id' => (int) $fixture['cycle']->id,
                'filters' => ['program_code' => 'BSIT', 'academic_cycle_id' => (int) $fixture['cycle']->id],
                'template_id' => $secondTemplate->id,
                'template_source_kind' => 'docx',
                'template_checksum' => $secondTemplate->sourceChecksum(),
                'render_status' => 'success',
                'rendered_pdf_sha256' => hash('sha256', $pdf),
            ]);
    } finally {
        @unlink($sourcePath);
    }
});
test('operational report PDFs compile the selected builders with provider filters and immutable source audit', function (): void {
    $fixture = reportHistoricalProviderFixture();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycleId = (int) $fixture['cycle']->id;
    $foreignCycle = $fixture['foreignCycle'];
    [$foreignAttempt, $secondForeignAttempt] = $fixture['foreignAttempts'];
    $passingTemplate = activeReportBuilderTemplate('passing_report', 'PASSING_BUILDER_MARKER');
    $quotaTemplate = activeReportBuilderTemplate('course_quota_qualifiers', 'QUOTA_BUILDER_MARKER');

    $passingHubPdf = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf&cycle_id='.$foreignCycle->id.'&program_code=BSIT&cutoff_score=80')
        ->assertOk()->streamedContent();
    $passingHubText = reportPdfText($passingHubPdf);
    expect($passingHubText)
        ->toContain($foreignAttempt->reference_code)
        ->toContain($secondForeignAttempt->reference_code)
        ->not->toContain($fixture['attempts'][0]->reference_code)
        ->not->toContain($fixture['attempts'][1]->reference_code);
    $passingPdf = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf&academic_cycle_id='.$cycleId.'&cycle_id='.$foreignCycle->id.'&program_code=BSIT&cutoff_score=80')
        ->assertOk()->streamedContent();
    $passingPdfText = reportPdfText($passingPdf);
    expect(substr($passingPdf, 0, 4))->toBe('%PDF')
        ->and($passingPdfText)->toContain('PASSING_BUILDER_MARKER')
        ->and($passingPdfText)->toContain(AcademicCycle::query()->findOrFail($cycleId)->staffLabel())
        ->and($passingPdfText)->toContain($fixture['attempts'][0]->reference_code)
        ->and($passingPdfText)->toContain($fixture['attempts'][1]->reference_code)
        ->and($passingPdfText)->toContain('Mara CurrentOne')
        ->and($passingPdfText)->toContain('Noel CurrentTwo')
        ->and($passingPdfText)->toContain('91')
        ->and($passingPdfText)->toContain('100.00%')
        ->and($passingPdfText)->not->toContain($fixture['attempts'][2]->reference_code)
        ->and($passingPdfText)->not->toContain($foreignAttempt->reference_code)
        ->and($passingPdfText)->not->toContain($secondForeignAttempt->reference_code)
        ->and($passingPdfText)->not->toContain('NOA-2026-')
        ->and($passingPdfText)->not->toContain('{{');
    $passingHubAudits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $passingTemplate->id)
        ->where('payload->rendered_pdf_sha256', hash('sha256', $passingHubPdf))
        ->get();
    expect($passingHubAudits)->toHaveCount(1);
    expect($passingHubAudits->first()->payload)
        ->toMatchArray([
            'type' => 'passing-report',
            'format' => 'pdf',
            'academic_cycle_id' => (int) $foreignCycle->id,
            'template_id' => $passingTemplate->id,
            'template_source_kind' => 'builder',
            'template_checksum' => $passingTemplate->sourceChecksum(),
            'compiler_version' => 'document-blueprint-1',
            'render_status' => 'success',
            'rendered_pdf_sha256' => hash('sha256', $passingHubPdf),
        ]);

    $passingAudits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $passingTemplate->id)
        ->where('payload->rendered_pdf_sha256', hash('sha256', $passingPdf))
        ->get();
    expect($passingAudits)->toHaveCount(1);
    $passingAudit = $passingAudits->first();
    expect($passingAudit->payload)
        ->toMatchArray([
            'type' => 'passing-report',
            'format' => 'pdf',
            'academic_cycle_id' => $cycleId,
            'filters' => ['academic_cycle_id' => $cycleId, 'program_code' => 'BSIT', 'cutoff_score' => '80'],
            'template_id' => $passingTemplate->id,
            'template_source_kind' => 'builder',
            'template_checksum' => $passingTemplate->sourceChecksum(),
            'compiler_version' => 'document-blueprint-1',
            'render_status' => 'success',
            'rendered_pdf_sha256' => hash('sha256', $passingPdf),
        ]);

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $quotaContext = app(CourseQuotaQualifiersDataQuery::class)->handle([
        'academic_cycle_id' => $cycleId,
        'program_code' => 'BSIT',
    ]);
    expect($quotaContext)
        ->toMatchArray([
            'target_quota' => 5,
            'accepted_count' => 2,
            'committed_reservations' => 0,
            'remaining_slots' => 3,
            'remaining_quota' => 5,
            'fill_rate' => '40.00%',
            'committed_fill_rate' => '0.00%',
        ])
        ->and($quotaContext['qualifiers'][0])
        ->toMatchArray(['gwa' => '1.75', 'accepted_at' => '2026-09-10', 'program_code' => 'BSIT'])
        ->and($quotaContext['qualifiers'][1])
        ->toMatchArray(['gwa' => '1.82', 'accepted_at' => '2026-09-11', 'program_code' => 'BSIT']);
    $quotaHubPdf = actingAsAdmissionSupportStaff($this, $registrar)->get('/reports/export/course-quota-qualifiers?format=pdf&cycle_id='.$foreignCycle->id.'&program_code=BSIT')
        ->assertOk()->streamedContent();
    $quotaHubText = reportPdfText($quotaHubPdf);
    expect($quotaHubText)
        ->toContain($foreignAttempt->reference_code)
        ->toContain($secondForeignAttempt->reference_code)
        ->not->toContain($fixture['attempts'][0]->reference_code)
        ->not->toContain($fixture['attempts'][1]->reference_code);
    $quotaPdf = actingAsAdmissionSupportStaff($this, $registrar)->get('/reports/export/course-quota-qualifiers?format=pdf&academic_cycle_id='.$cycleId.'&cycle_id='.$foreignCycle->id.'&program_code=BSIT')
        ->assertOk()->streamedContent();
    $quotaPdfText = reportPdfText($quotaPdf);
    expect(substr($quotaPdf, 0, 4))->toBe('%PDF')
        ->and($quotaPdfText)->toContain('QUOTA_BUILDER_MARKER')
        ->and($quotaPdfText)->toContain(AcademicCycle::query()->findOrFail($cycleId)->staffLabel())
        ->and($quotaPdfText)->toContain($fixture['attempts'][0]->reference_code)
        ->and($quotaPdfText)->toContain($fixture['attempts'][1]->reference_code)
        ->and($quotaPdfText)->toContain('Mara CurrentOne')
        ->and($quotaPdfText)->toContain('Noel CurrentTwo')
        ->and($quotaPdfText)->toContain('1.75')
        ->and($quotaPdfText)->toContain('1.82')
        ->and($quotaPdfText)->toContain('2026-09-10')
        ->and($quotaPdfText)->toContain('2026-09-11')
        ->and($quotaPdfText)->toContain('40.00%')
        ->and($quotaPdfText)->not->toContain($fixture['attempts'][2]->reference_code)
        ->and($quotaPdfText)->not->toContain($foreignAttempt->reference_code)
        ->and($quotaPdfText)->not->toContain($secondForeignAttempt->reference_code)
        ->and($quotaPdfText)->not->toContain('NOA-2026-')
        ->and($quotaPdfText)->not->toContain('{{');
    $quotaHubAudits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $registrar->id)
        ->where('payload->type', 'course-quota-qualifiers')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $quotaTemplate->id)
        ->where('payload->rendered_pdf_sha256', hash('sha256', $quotaHubPdf))
        ->get();
    expect($quotaHubAudits)->toHaveCount(1);
    expect($quotaHubAudits->first()->payload)
        ->toMatchArray([
            'type' => 'course-quota-qualifiers',
            'format' => 'pdf',
            'academic_cycle_id' => (int) $foreignCycle->id,
            'template_id' => $quotaTemplate->id,
            'template_source_kind' => 'builder',
            'template_checksum' => $quotaTemplate->sourceChecksum(),
            'compiler_version' => 'document-blueprint-1',
            'render_status' => 'success',
            'rendered_pdf_sha256' => hash('sha256', $quotaHubPdf),
        ]);

    $quotaAudits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $registrar->id)
        ->where('payload->type', 'course-quota-qualifiers')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $quotaTemplate->id)
        ->where('payload->rendered_pdf_sha256', hash('sha256', $quotaPdf))
        ->get();
    expect($quotaAudits)->toHaveCount(1);
    $quotaAudit = $quotaAudits->first();
    expect($quotaAudit->payload)
        ->toMatchArray([
            'type' => 'course-quota-qualifiers',
            'format' => 'pdf',
            'academic_cycle_id' => $cycleId,
            'filters' => ['academic_cycle_id' => $cycleId, 'program_code' => 'BSIT'],
            'template_id' => $quotaTemplate->id,
            'template_source_kind' => 'builder',
            'template_checksum' => $quotaTemplate->sourceChecksum(),
            'compiler_version' => 'document-blueprint-1',
            'render_status' => 'success',
            'rendered_pdf_sha256' => hash('sha256', $quotaPdf),
        ]);
});

test('invalid selected report PDF source fails closed and is audited rather than using a default document', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    ReportTemplate::query()->create([
        'surface_key' => 'passing_report',
        'name' => 'Unsupported active source',
        'mode' => 'html',
        'source_kind' => 'unsupported',
        'is_active' => true,
    ]);

    actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf')->assertUnprocessable();
    $audits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->template_source_kind', 'unsupported')
        ->where('payload->render_status', 'failed')
        ->get();
    expect($audits)->toHaveCount(1);
    $audit = $audits->first();
    expect($audit->payload['render_status'])->toBe('failed')
        ->and($audit->payload['rendered_pdf_sha256'])->toBeNull()
        ->and($audit->payload['template_source_kind'])->toBe('unsupported');
});

test('selected raw HTML report bindings outside the surface catalog fail closed before rendering', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $html = '<h1>{{academic_cycle}}</h1><p>{{unknown_report_binding}}</p>';
    $template = ReportTemplate::query()->create([
        'surface_key' => 'passing_report',
        'name' => 'Raw HTML unknown binding',
        'mode' => 'html',
        'source_kind' => ReportTemplate::SOURCE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'content' => $html,
        'compiled_html' => $html,
        'is_active' => true,
    ]);
    $renderer = Mockery::mock(PrintRenderer::class);
    $renderer->shouldNotReceive('render');
    app()->instance(PrintRenderer::class, $renderer);

    $response = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf');
    $response->assertUnprocessable();
    $audits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $template->id)
        ->where('payload->render_status', 'failed')
        ->get();
    expect($audits)->toHaveCount(1);
    $audit = $audits->first();
    expect($response->getContent())->not->toContain('%PDF')
        ->and($audit->payload['template_id'])->toBe($template->id)
        ->and($audit->payload['template_source_kind'])->toBe(ReportTemplate::SOURCE_HTML)
        ->and($audit->payload['render_status'])->toBe('failed')
        ->and($audit->payload['rendered_pdf_sha256'])->toBeNull();
});

test('passing report PDF uses the default only when no selected template exists', function (): void {
    ReportTemplate::query()->where('surface_key', 'passing_report')->update(['is_active' => false]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $response = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf');
    $response->assertOk();
    $pdf = $response->streamedContent();
    $audits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->rendered_pdf_sha256', hash('sha256', $pdf))
        ->get();
    expect($audits)->toHaveCount(1);
    $audit = $audits->first();
    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(reportPdfText($pdf))->not->toContain('{{')
        ->and($audit->payload['template_id'])->toBeNull()
        ->and($audit->payload['template_source_kind'])->toBe('default')
        ->and($audit->payload['render_status'])->toBe('success')
        ->and($audit->payload['rendered_pdf_sha256'])->toBe(hash('sha256', $pdf));
});

test('PDF audit failure returns no rendered bytes and records failed audit status', function (): void {
    ReportTemplate::query()->where('surface_key', 'passing_report')->update(['is_active' => false]);
    $realAudit = app(RecordAuditEvent::class);
    $auditCalls = 0;
    $auditWithFailure = Mockery::mock(RecordAuditEvent::class);
    $auditWithFailure->shouldReceive('handle')->twice()->andReturnUsing(function (...$arguments) use (&$auditCalls, $realAudit): AuditEvent {
        $auditCalls++;
        if ($auditCalls === 1) {
            throw new RuntimeException('Audit sink unavailable.');
        }

        return $realAudit->handle(...$arguments);
    });
    app()->instance(RecordAuditEvent::class, $auditWithFailure);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $response = actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf');
    $response->assertServerError();
    expect($response->getContent())->not->toContain("\x25\x50\x44\x46")
        ->and($auditCalls)->toBe(2);
    $audits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->render_status', 'failed')
        ->get();
    expect($audits)->toHaveCount(1);
    $audit = $audits->first();
    expect($audit->payload['render_status'])->toBe('failed')
        ->and($audit->payload['rendered_pdf_sha256'])->toBeNull();
});

test('HTML report template save accepts an explicit null builder JSON when source HTML is provided', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $response = actingAsAdmissionSupportStaff($this, $guidance)->postJson('/setup/report-templates/passing_report', [
        'name' => 'Null builder HTML source',
        'mode' => 'html',
        'content' => '<p>HTML source remains available.</p>',
        'builder_json' => null,
    ])->assertCreated();

    $template = ReportTemplate::query()->findOrFail($response->json('id'));
    expect($template->source_kind)->toBe('html')
        ->and($template->builder_json)->toBeNull();

    $this->putJson('/setup/report-templates/passing_report/'.$template->id, [
        'content' => '<p>Updated HTML source.</p>',
        'builder_json' => null,
    ])->assertOk();
    expect($template->fresh()->source_kind)->toBe('html')
        ->and($template->fresh()->builder_json)->toBeNull();
});
test('course quota report PDF preserves the selected DOCX source path', function (): void {
    Storage::fake('local');
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $fixture = reportHistoricalProviderFixture();
    $cycleId = (int) $fixture['cycle']->id;
    $first = $fixture['attempts'][0];
    $second = $fixture['attempts'][1];
    $excludedProgram = $fixture['attempts'][2];
    $foreign = $fixture['foreignAttempts'][0];
    $secondForeign = $fixture['foreignAttempts'][1];
    $bytes = reportWordTemplateBytes('course_quota_qualifiers', 'QUOTA_DOCX_PDF_MARKER');
    $created = actingAsAdmissionSupportStaff($this, $registrar)->postJson(route('setup.report-templates.store', ['surface' => 'course_quota_qualifiers']), [
        'name' => 'Course quota DOCX source',
        'mode' => 'docx',
        'document' => UploadedFile::fake()->createWithContent('quota-report.docx', $bytes),
        'builder_json' => null,
    ])->assertCreated();
    $template = ReportTemplate::query()->findOrFail($created->json('id'));
    $documentChecksum = hash('sha256', $bytes);
    $sourceChecksum = $template->sourceChecksum();
    expect($template->document_checksum)->toBe($documentChecksum)
        ->and(hash('sha256', Storage::disk('local')->get($template->document_path)))->toBe($documentChecksum);

    actingAsAdmissionSupportStaff($this, $registrar)->get(route('setup.report-templates.edit', [
        'surface' => 'course_quota_qualifiers',
        'reportTemplate' => $template->id,
    ]))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Edit')
            ->where('template.id', $template->id)
            ->where('documentAnalysis.source_checksum', $documentChecksum));
    actingAsAdmissionSupportStaff($this, $registrar)->post(route('setup.report-templates.activate', [
        'surface' => 'course_quota_qualifiers',
        'reportTemplate' => $template->id,
    ]))->assertRedirect();
    $preview = actingAsAdmissionSupportStaff($this, $registrar)->postJson(route('setup.report-templates.preview', ['surface' => 'course_quota_qualifiers']), [
        'mode' => 'docx',
        'template_id' => $template->id,
    ])->assertOk();
    $previewBytes = base64_decode($preview->json('pdf_base64'), true);
    expect($previewBytes)->toBeString()
        ->and(substr($previewBytes, 0, 4))->toBe('%PDF')
        ->and(reportPdfText($previewBytes))->toContain('QUOTA_DOCX_PDF_MARKER')
        ->and(reportPdfText($previewBytes))->not->toContain('{{');

    expect($template->fresh()->sourceChecksum())->toBe($sourceChecksum);
    $docx = actingAsAdmissionSupportStaff($this, $registrar)->get('/reports/export/course-quota-qualifiers?format=docx&cycle_id='.$cycleId.'&program_code=BSIT')->assertOk()->streamedContent();
    $docxPath = tempnam(sys_get_temp_dir(), 'quota-report-export-proof-');
    if ($docxPath === false) {
        throw new RuntimeException('Unable to create temporary quota DOCX export proof.');
    }
    file_put_contents($docxPath, $docx);
    $docxText = app(DocxTextExtractor::class)->text($docxPath);
    @unlink($docxPath);
    expect(substr($docx, 0, 2))->toBe('PK')
        ->and($docxText)->toContain('QUOTA_DOCX_PDF_MARKER')
        ->and($docxText)->toContain($first->reference_code)
        ->and($docxText)->toContain($second->reference_code)
        ->and($docxText)->toContain('Mara CurrentOne')
        ->and($docxText)->toContain('Noel CurrentTwo')
        ->and($docxText)->toContain('1.75')
        ->and($docxText)->toContain('1.82')
        ->and($docxText)->toContain('2026-09-10')
        ->and($docxText)->toContain('2026-09-11')
        ->and($docxText)->toContain('5')
        ->and($docxText)->toContain('2')
        ->and($docxText)->toContain('3')
        ->and($docxText)->toContain('40.00%')
        ->and($docxText)->not->toContain($excludedProgram->reference_code)
        ->and($docxText)->not->toContain($foreign->reference_code)
        ->and($docxText)->not->toContain($secondForeign->reference_code)
        ->and($docxText)->not->toContain('NOA-2026-')
        ->and($docxText)->not->toContain('{{');
    $pdf = actingAsAdmissionSupportStaff($this, $registrar)->get('/reports/export/course-quota-qualifiers?format=pdf&cycle_id='.$cycleId.'&program_code=BSIT')->assertOk()->streamedContent();
    $pdfText = reportPdfText($pdf);
    $quotaAudits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $registrar->id)
        ->where('payload->type', 'course-quota-qualifiers')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $template->id)
        ->where('payload->rendered_pdf_sha256', hash('sha256', $pdf))
        ->get();
    expect($quotaAudits)->toHaveCount(1);
    $audit = $quotaAudits->first();
    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and($pdfText)->toContain('QUOTA_DOCX_PDF_MARKER')
        ->and($pdfText)->toContain($first->reference_code)
        ->and($pdfText)->toContain($second->reference_code)
        ->and($pdfText)->toContain('Mara CurrentOne')
        ->and($pdfText)->toContain('Noel CurrentTwo')
        ->and($pdfText)->toContain('1.75')
        ->and($pdfText)->toContain('1.82')
        ->and($pdfText)->toContain('2026-09-10')
        ->and($pdfText)->toContain('2026-09-11')
        ->and($pdfText)->toContain('40.00%')
        ->and($pdfText)->not->toContain($excludedProgram->reference_code)
        ->and($pdfText)->not->toContain($foreign->reference_code)
        ->and($pdfText)->not->toContain($secondForeign->reference_code)
        ->and($pdfText)->not->toContain('NOA-2026-')
        ->and($pdfText)->not->toContain('{{')
        ->and($audit->payload['template_id'])->toBe($template->id)
        ->and($audit->payload['template_checksum'])->toBe($sourceChecksum)
        ->and($audit->payload['render_status'])->toBe('success')
        ->and($audit->payload['rendered_pdf_sha256'])->toBe(hash('sha256', $pdf));
    expect($template->fresh()->sourceChecksum())->toBe($sourceChecksum);
});
test('report PDF renderer failures produce failed immutable export audit events', function (): void {
    Storage::fake('local');
    $bytes = 'invalid-docx-source';
    $template = ReportTemplate::query()->create([
        'surface_key' => 'passing_report',
        'name' => 'Broken DOCX source',
        'mode' => 'docx',
        'source_kind' => 'docx',
        'document_extension' => 'docx',
        'document_checksum' => hash('sha256', $bytes),
        'document_path' => 'report-templates/passing_report/broken.docx',
        'is_active' => true,
    ]);
    Storage::disk('local')->put($template->document_path, $bytes);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/passing-report?format=pdf')->assertServerError();
    $audits = AuditEvent::query()
        ->where('event_type', 'reports.export_downloaded')
        ->where('actor', (string) $guidance->id)
        ->where('payload->type', 'passing-report')
        ->where('payload->format', 'pdf')
        ->where('payload->template_id', $template->id)
        ->where('payload->render_status', 'failed')
        ->get();
    expect($audits)->toHaveCount(1);
    $audit = $audits->first();
    expect($audit->payload['template_id'])->toBe($template->id)
        ->and($audit->payload['render_status'])->toBe('failed')
        ->and($audit->payload['rendered_pdf_sha256'])->toBeNull();
});
