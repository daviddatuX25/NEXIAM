<?php

use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Reports\Surfaces\PassingReportSurface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

beforeEach(function (): void {
    seedE2Foundation();
});
test('report template create and edit provide visual builder props', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ReportTemplate::query()->where('surface_key', 'passing_report')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)->get('/setup/report-templates/passing_report/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Create')
            ->has('placeholderGroups')
            ->has('builderCatalog')
            ->has('builderCapabilities')
            ->has('builderDefaults')
            ->has('sampleContext')
        );

    $this->get(route('setup.report-templates.edit', [
        'surface' => 'passing_report',
        'reportTemplate' => $template->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Edit')
            ->has('placeholderGroups')
            ->has('builderCatalog')
            ->has('builderCapabilities')
            ->has('builderDefaults')
            ->has('sampleContext')
        );
});
test('office template picker shows applicant school and history tags only on student grain', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $registrar)->get('/setup/report-templates/admission_slip/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('field_policy.default_grain', 'student')
            ->where('placeholderGroups.applicant.0.placeholder', 'last_school_enrolled')
            ->where('placeholderGroups.applicant.1.placeholder', 'last_school_short')
            ->where('placeholderGroups.applicant.1.aliases.0', 'school_short')
            ->where('placeholderGroups.applicant.2.placeholder', 'strand_or_prev_course')
            ->where('placeholderGroups.applicant.3.placeholder', 'strand_or_prev_course_short')
            ->where('placeholderGroups.applicant.3.aliases.0', 'history_short')
            ->where('sampleContext.last_school_enrolled', 'Ilocos Sur College')
            ->where('sampleContext.school_short', 'ISC')
            ->where('sampleContext.strand_or_prev_course', 'Humanities and Social Sciences')
            ->where('sampleContext.history_short', 'HUMSS')
        );

    actingAsAdmissionSupportStaff($this, $registrar)->get('/setup/report-templates/course_quota_qualifiers/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('field_policy.default_grain', 'cycle_program')
            ->where('placeholderGroups.applicant', [])
            ->missing('sampleContext.last_school_enrolled')
            ->missing('sampleContext.last_school_short')
            ->missing('sampleContext.strand_or_prev_course_short')
        );
});
test('custom office documents start neutral and activate independently from each other and system surfaces', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $passingReport = ReportTemplate::query()->where('surface_key', 'passing_report')->where('is_active', true)->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)->get('/setup/document-templates/custom_document/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Create')
            ->where('surface.surface_key', 'custom_document')
            ->where('field_policy.default_selected_leaves', [])
        );

    $createdTemplates = collect(['Guidance Referral Slip', 'Parent Conference Letter'])
        ->map(fn (string $name) => actingAsAdmissionSupportStaff($this, $guidance)
            ->postJson(route('setup.document-templates.store', ['surface' => 'custom_document']), [
                'name' => $name,
                'mode' => 'html',
                'content' => '<p>'.$name.'</p>',
                'selected_leaves' => [],
            ])
            ->assertCreated()
            ->json('id'));

    foreach ($createdTemplates as $templateId) {
        actingAsAdmissionSupportStaff($this, $guidance)->post(route('setup.document-templates.activate', [
            'surface' => 'custom_document',
            'reportTemplate' => $templateId,
        ]))->assertRedirect();
    }

    expect(ReportTemplate::query()
        ->whereIn('id', $createdTemplates)
        ->where('surface_key', 'custom_document')
        ->where('office', 'guidance')
        ->where('is_active', true)
        ->count())->toBe(2)
        ->and($passingReport->fresh()->is_active)->toBeTrue();
});

test('Word template save accepts a nullable builder field and clears its acknowledged draft', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Storage::fake('local');

    $surface = app(PassingReportSurface::class);
    $catalog = $surface->placeholderCatalog()['all'];
    $catalogKeys = array_is_list($catalog) ? $catalog : array_keys($catalog);
    $word = new PhpWord;
    $section = $word->addSection();
    $scalarKeys = [];
    $repeatingKeys = [];
    foreach ($catalogKeys as $key) {
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
        $row = $section->addTable()->addRow();
        foreach ($repeatingKeys as $key) {
            $row->addCell(2000)->addText('{{'.$key.'}}');
        }
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'report-template-word-');
    expect($temporaryPath)->toBeString();
    $docxPath = $temporaryPath.'.docx';
    unlink($temporaryPath);
    try {
        IOFactory::createWriter($word, 'Word2007')->save($docxPath);
        $docxBytes = file_get_contents($docxPath);
    } finally {
        if (is_file($docxPath)) {
            unlink($docxPath);
        }
    }
    expect($docxBytes)->toBeString();

    actingAsAdmissionSupportStaff($this, $guidance)->postJson(route('setup.report-templates.store', ['surface' => 'passing_report']), [
        'name' => 'Created inactive DOCX template with null builder',
        'mode' => 'docx',
        'document' => UploadedFile::fake()->createWithContent('passing-report.docx', $docxBytes),
        'builder_json' => null,
    ])->assertCreated();

    $createdTemplate = ReportTemplate::query()->where('name', 'Created inactive DOCX template with null builder')->firstOrFail();
    expect($createdTemplate->mode)->toBe('docx')
        ->and($createdTemplate->is_active)->toBeFalse()
        ->and($createdTemplate->document_checksum)->toBe(hash('sha256', $docxBytes));

    $template = ReportTemplate::query()->create([
        'surface_key' => 'passing_report',
        'name' => 'Inactive DOCX template before save',
        'mode' => 'docx',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'document_path' => 'report-templates/passing_report/nullable-builder-test.docx',
        'document_checksum' => hash('sha256', $docxBytes),
        'document_extension' => 'docx',
        'source_kind' => ReportTemplate::SOURCE_DOCX,
        'builder_json' => null,
        'is_active' => false,
        'created_by_user_id' => $guidance->id,
    ]);
    Storage::disk('local')->put($template->document_path, $docxBytes);
    $sourceChecksum = $template->sourceChecksum();
    $draftId = 'report_draft_passing_report_'.$guidance->id.'_'.$template->id;
    $draftCacheKey = 'report_draft_passing_report_'.$guidance->id.'_'.$draftId;

    $this->postJson(route('setup.report-templates.drafts.store', ['surface' => 'passing_report']), [
        'source_template_id' => $template->id,
        'draft_id' => $draftId,
        'stage' => 3,
        'lock_version' => 4,
        'state' => ['name' => 'Uncommitted cached name', 'mode' => 'docx'],
    ])->assertCreated();

    $url = route('setup.report-templates.update', ['surface' => 'passing_report', 'reportTemplate' => $template->id]);
    $this->putJson($url, [
        'name' => 'Invalid update must not persist',
        'mode' => 'docx',
        'builder_json' => '{"schema_version":broken}',
        'draft_id' => $draftId,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('builder_json');

    expect($template->fresh()->name)->toBe('Inactive DOCX template before save')
        ->and($template->fresh()->sourceChecksum())->toBe($sourceChecksum)
        ->and($template->fresh()->is_active)->toBeFalse();
    expect(Cache::get($draftCacheKey)['state']['name'])->toBe('Uncommitted cached name');

    $this->put($url, [
        'name' => 'Saved inactive DOCX template',
        'mode' => 'docx',
        'builder_json' => null,
        'draft_id' => $draftId,
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('setup.report-templates.surface', ['surface' => 'passing_report']));

    expect(Cache::get($draftCacheKey))->toBeNull()
        ->and($template->fresh()->name)->toBe('Saved inactive DOCX template')
        ->and($template->fresh()->sourceChecksum())->toBe($sourceChecksum)
        ->and($template->fresh()->is_active)->toBeFalse();

    $this->get(route('setup.report-templates.edit', [
        'surface' => 'passing_report',
        'reportTemplate' => $template->id,
    ]))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Edit')
            ->where('draft', null)
            ->where('documentAnalysis.valid', true)
            ->where('documentAnalysis.source_checksum', $template->fresh()->document_checksum)
        );
});

test('report template builder accepts JSON strings and previews compiled documents', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $builder = json_encode(['schema_version' => 1, 'components' => []], JSON_THROW_ON_ERROR);
    actingAsAdmissionSupportStaff($this, $guidance)->postJson('/setup/report-templates/passing_report', [
        'name' => 'Builder-created passing report',
        'mode' => 'html',
        'builder_json' => $builder,
    ])->assertCreated();

    $template = ReportTemplate::query()->where('name', 'Builder-created passing report')->firstOrFail();
    expect($template->content)->toBeString()
        ->and($template->compiled_html)->toBe($template->content)
        ->and($template->builder_json)->toBeArray();

    $preview = $this->postJson('/setup/report-templates/passing_report/preview', [
        'mode' => 'html',
        'builder_json' => $builder,
    ])->assertOk();

    expect($preview->json('pdf_base64'))->toStartWith('JVBERi')
        ->and($preview->json('html'))->toContain('<iframe');
});

test('report template hub exposes only surfaces authorable by the users office', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $registrar)->get('/setup/report-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Index')
            ->where('page_state', 'ready')
            ->has('offices', 1)
            ->where('offices.0.office', 'registrar')
            ->has('offices.0.surfaces', 1)
            ->where('offices.0.surfaces.0.surface_key', 'course_quota_qualifiers')
            ->missing('offices.0.surfaces.1')
        );

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $guidance)->get('/setup/report-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Index')
            ->where('page_state', 'ready')
            ->has('offices', 1)
            ->where('offices.0.office', 'guidance')
            ->has('offices.0.surfaces', 1)
            ->where('offices.0.surfaces.0.surface_key', 'passing_report')
            ->missing('offices.0.surfaces.1')
        );
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $admin)->get('/setup/document-templates')->assertOk();
    actingAsAdmissionSupportStaff($this, $admin)->get('/setup/report-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/ReportTemplates/Index')
            ->where('available_surfaces', fn ($surfaces) => collect($surfaces)->contains(
                fn ($surface) => (is_array($surface) ? $surface['surface_key'] ?? null : $surface) === 'institutional_executive_summary',
            ))
        );
    actingAsAdmissionSupportStaff($this, $admin)->get('/setup/report-templates/passing_report')->assertForbidden();
    actingAsAdmissionSupportStaff($this, $admin)->get('/setup/report-templates/course_quota_qualifiers')->assertForbidden();

});
test('report template surfaces deny cross-office access', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $registrar)->get('/setup/report-templates/passing_report')->assertForbidden();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $guidance)->get('/setup/report-templates/course_quota_qualifiers')->assertForbidden();
});

test('guidance view-only clerk cannot read or mutate report templates', function (): void {
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $clerk)
        ->get('/setup/report-templates/passing_report')->assertForbidden();
    actingAsAdmissionSupportStaff($this, $clerk)->postJson(route('setup.report-templates.store', ['surface' => 'passing_report']), [
        'name' => 'Unauthorized template',
        'mode' => 'html',
        'content' => '<p>Unauthorized template.</p>',
    ])->assertForbidden();
    $template = ReportTemplate::query()->where('surface_key', 'passing_report')->firstOrFail();
    $this->post(route('setup.report-templates.activate', [
        'surface' => 'passing_report',
        'reportTemplate' => $template->id,
    ]))->assertForbidden();
    $this->postJson(route('setup.report-templates.preview', ['surface' => 'passing_report']), [
        'mode' => 'html',
        'builder_json' => ['schema_version' => 1, 'components' => []],
    ])->assertForbidden();
});

test('template activation is exclusive only within its surface', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $qualifierTemplate = ReportTemplate::query()->where('surface_key', 'course_quota_qualifiers')->firstOrFail();
    $passingTemplate = ReportTemplate::query()->where('surface_key', 'passing_report')->firstOrFail();
    $qualifierTemplate->forceFill(['is_active' => true])->save();
    $passingTemplate->forceFill(['is_active' => false])->save();

    actingAsAdmissionSupportStaff($this, $guidance)->post(route('setup.report-templates.activate', [
        'surface' => 'passing_report',
        'reportTemplate' => $passingTemplate->id,
    ]))->assertRedirect(route('setup.report-templates.surface', ['surface' => 'passing_report']));

    expect($passingTemplate->fresh()->is_active)->toBeTrue()
        ->and($qualifierTemplate->fresh()->is_active)->toBeTrue();
});

test('course quota qualifiers export supports CSV and DOCX for Registrar only', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $csv = actingAsAdmissionSupportStaff($this, $registrar)->get('/reports/export/course-quota-qualifiers?format=csv')->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('Reference Code');

    $docx = $this->get('/reports/export/course-quota-qualifiers?format=docx')->assertOk();
    expect($docx->headers->get('content-type'))->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and(substr($docx->streamedContent(), 0, 2))->toBe('PK');

    $this->get('/reports/export/course_quota_qualifiers?format=csv')->assertOk();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $guidance)->get('/reports/export/course-quota-qualifiers?format=csv')->assertForbidden();
    $this->get('/reports/export/course_quota_qualifiers?format=docx')->assertForbidden();
});

test('authorized report template thumbnail delivers cached PNG and purges on deletion', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ReportTemplate::query()->where('surface_key', 'passing_report')->firstOrFail();
    $thumbnailService = app(DocumentThumbnailService::class);
    $fingerprint = $thumbnailService->fingerprint($template->sourceChecksum());
    $thumbnailService->purge('passing_report', $template->id);

    $url = route('setup.report-templates.thumbnail', ['surface' => 'passing_report', 'reportTemplate' => $template->id]);
    actingAsAdmissionSupportStaff($this, $guidance)->get($url.'?v='.$fingerprint)->assertNotFound();

    $pdfFixture = new FPDF;
    $pdfFixture->AddPage();
    $pdfFixture->SetFont('Helvetica', '', 12);
    $pdfFixture->Text(20, 20, 'Report Page');
    $thumbnailService->store('passing_report', $template->id, $template->sourceChecksum(), (string) $pdfFixture->Output('S'));

    $response = $this->get($url.'?v='.$fingerprint);
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(substr($thumbnailService->read('passing_report', $template->id, $fingerprint), 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    $this->get($url.'?v=stale_'.str_repeat('0', 58))->assertNotFound();

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $registrar)->get($url.'?v='.$fingerprint)->assertForbidden();

    actingAsAdmissionSupportStaff($this, $guidance)->delete(route('setup.report-templates.destroy', ['surface' => 'passing_report', 'reportTemplate' => $template->id]))
        ->assertRedirect();
    expect($thumbnailService->read('passing_report', $template->id, $fingerprint))->toBeNull();
});

test('report template index and surface expose thumbnail URLs with version fingerprint', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ReportTemplate::query()->where('surface_key', 'passing_report')->firstOrFail();
    $thumbnailService = app(DocumentThumbnailService::class);
    $fingerprint = $thumbnailService->fingerprint($template->sourceChecksum());

    actingAsAdmissionSupportStaff($this, $guidance)->get(route('setup.report-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('offices.0.surfaces.0.active_template.thumbnail_url', route('setup.report-templates.thumbnail', [
                'surface' => 'passing_report',
                'reportTemplate' => $template->id,
            ]).'?v='.$fingerprint));

    $this->get(route('setup.report-templates.surface', ['surface' => 'passing_report']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('templates.0.thumbnail_url'));
});

test('report template requests reject a session after its persisted staff epoch advances', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $guidance->id)->firstOrFail();
    $sessionVersion = (int) $state->session_version;

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get('/setup/report-templates')
        ->assertOk();

    $state->forceFill(['session_version' => $sessionVersion + 1])->save();

    $this->get('/setup/report-templates')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email', 'Your session has expired due to a security update. Please sign in again.')
        ->assertSessionMissing('auth.surface')
        ->assertSessionMissing('auth.session_version');

    $this->assertGuest();
});

test('report template studio exposes baseline inventory, office packs, pagination tags, and streams ISPSC QAD-F032a baseline docx', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)->get('/setup/report-templates/passing_report/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('baseline_inventory', 4)
            ->has('office_packs')
            ->has('placeholderGroups.Pagination')
        );

    $download = $this->get('/setup/report-templates/baselines/ispsc_qad_f032a_rev5/download')->assertOk();
    expect($download->headers->get('content-type'))->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and(substr($download->streamedContent(), 0, 2))->toBe('PK');
});

test('course_quota_qualifiers edit proving ground exposes active_pack and previews customized table columns and pagination footer', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $template = ReportTemplate::query()->where('surface_key', 'course_quota_qualifiers')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $registrar)->get("/setup/report-templates/course_quota_qualifiers/{$template->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('active_pack')
            ->where('placeholderGroups.Pagination.0.placeholder', 'page_i')
        );

    $blueprint = $template->builder_json;
    foreach ($blueprint['components'] as &$component) {
        if (($component['type'] ?? '') === 'data_table') {
            $component['props']['columns'] = [
                ['key' => 'reference_code', 'label' => 'Ref ID', 'checked' => true],
                ['key' => 'applicant_name', 'label' => 'Candidate Name', 'checked' => true],
                ['key' => 'strand', 'label' => 'SHS Strand', 'checked' => false],
            ];
        }
        if (($component['type'] ?? '') === 'footer') {
            $component['props']['text'] = 'Page {{page_i}} of {{page_all}}';
        }
    }
    unset($component);

    $preview = $this->postJson('/setup/report-templates/course_quota_qualifiers/preview', [
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'builder_json' => $blueprint,
    ])->assertOk();

    expect($preview->json('pdf_base64'))->toBeString()->not->toBeEmpty();
});

test('flattened document hub, mounted slots, scope picker options, document render, and server receipt endpoint enforce office scope', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ReportTemplate::query()->where('surface_key', 'course_quota_qualifiers')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $registrar)->get('/setup/report-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('flat_variants')
            ->has('baseline_inventory', 4)
        );

    $this->put("/setup/report-templates/course_quota_qualifiers/{$template->id}", [
        'name' => $template->name,
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'builder_json' => $template->builder_json,
        'mounted_slots' => ['analytics_reports_hub'],
        'custom_group' => 'AACCUP Accreditation',
    ])->assertRedirect();

    $template->refresh();
    expect($template->mounted_slots)->toBe(['analytics_reports_hub'])
        ->and($template->custom_group)->toBe('AACCUP Accreditation');

    $this->getJson('/document-printing/scope-options?surface_key=course_quota_qualifiers')
        ->assertOk()
        ->assertJsonStructure(['cycles', 'programs', 'applicants']);

    $this->postJson('/document-printing/render', [
        'template_id' => $template->id,
        'surface_key' => 'course_quota_qualifiers',
    ])->assertOk()->assertJsonPath('status', 'ready');

    $this->postJson('/document-printing/mark-printed', [
        'template_id' => $template->id,
        'filename' => 'qualifiers.pdf',
    ])->assertOk()->assertJsonStructure(['receipt_id', 'printed_at', 'actor_name']);

    // Registrar cannot render Guidance passing_report
    $passingTemplate = ReportTemplate::query()->where('surface_key', 'passing_report')->firstOrFail();
    $this->postJson('/document-printing/render', [
        'template_id' => $passingTemplate->id,
        'surface_key' => 'passing_report',
    ])->assertForbidden();

    // Guidance counselor without registrar applicant-view cannot query student scope-options
    actingAsAdmissionSupportStaff($this, $guidance)
        ->getJson('/document-printing/scope-options?surface_key=admission_slip&grain=student')
        ->assertForbidden();
});

test('stored docx template can be exported and downloaded by authorized staff', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Storage::fake('local');

    $fileContent = 'Fake DOCX template binary content for export test';
    $path = 'report-templates/custom_document/export_test.docx';
    Storage::disk('local')->put($path, $fileContent);

    $template = ReportTemplate::query()->create([
        'surface_key' => 'custom_document',
        'office' => 'guidance',
        'name' => 'Guidance Counseling Intake Slip',
        'mode' => 'docx',
        'source_kind' => 'docx',
        'document_path' => $path,
        'document_extension' => 'docx',
        'document_checksum' => hash('sha256', $fileContent),
        'is_active' => true,
    ]);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.document-templates.download-source', [
            'surface' => 'custom_document',
            'reportTemplate' => $template->id,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload("guidance-counseling-intake-slip_{$template->id}.docx");
});
