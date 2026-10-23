<?php

use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Models\Foundation\AuditEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\ResultSheet\Actions\ActivateResultSheetTemplate;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\DocxToPdfConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function resultTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'ICAT Results v1',
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<p>Result for {{applicant_name}} ({{applicant_reference}})</p>',
        'watermark_text' => null,
        'is_active' => true,
    ], $overrides);
}

test('guidance counselor can open the result template index', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.result-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/ResultSheetTemplates/Index'));
});

test('official ICAT Word template is the active seeded default', function (): void {
    seedE2Foundation();

    $active = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/template_main_icat_test1.docx');

    expect($active->name)->toBe('ICAT Results (Word)')
        ->and($active->mode)->toBe(ResultSheetTemplate::MODE_DOCX)
        ->and($active->document_checksum)->toBe(hash_file('sha256', $asset))
        ->and(ResultSheetTemplate::query()->where('name', 'ICAT Results (HTML)')->value('is_active'))->toBeFalse();
});

test('creating an active HTML template makes it the single active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    ResultSheetTemplate::query()->create(resultTemplatePayload(['name' => 'Old', 'is_active' => true]));

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), resultTemplatePayload(['name' => 'New']))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1)
        ->and(ResultSheetTemplate::query()->where('is_active', true)->value('name'))->toBe('New');
});

test('storing an invalid active builder template preserves the current active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();
    $document = ResultSheetBuilderSchema::defaultDocument();
    $document['component_templates']['header'] = [
        'revision' => 1,
        'html' => '<script>alert(1)</script>',
    ];

    $this->withoutExceptionHandling();

    expect(fn () => actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Invalid Active Builder',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'builder_json' => json_encode($document, JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'is_active' => true,
        ]))->toThrow(RuntimeException::class, 'builder_invalid:');

    expect(ResultSheetTemplate::query()->where('name', 'Invalid Active Builder')->doesntExist())
        ->toBeTrue()
        ->and($prior->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('storing an active builder template refuses a non-PDF sample and preserves the current active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();
    $document = ResultSheetBuilderSchema::defaultDocument();

    app()->instance(PrintRenderer::class, new class implements PrintRenderer
    {
        public function render(string $html, array $options = []): string
        {
            return 'not-a-pdf';
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'No PDF Builder',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'builder_json' => json_encode($document, JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'is_active' => true,
        ]))->toThrow(RuntimeException::class, 'template_pdf_verification_failed');

    expect(ResultSheetTemplate::query()->where('name', 'No PDF Builder')->doesntExist())
        ->toBeTrue()
        ->and($prior->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('storing a valid active builder template verifies a real sample PDF before activation', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();
    $document = ResultSheetBuilderSchema::defaultDocument();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Valid Active Builder',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'builder_json' => json_encode($document, JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $active = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    expect($active->name)->toBe('Valid Active Builder')
        ->and($active->source_kind)->toBe(ResultSheetTemplate::SOURCE_BUILDER)
        ->and($active->compiled_html)->toContain('result-sheet-builder')
        ->and($prior->fresh()->is_active)->toBeFalse()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('updating a builder template to active refuses a non-PDF sample and preserves the current active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();
    $document = ResultSheetBuilderSchema::defaultDocument();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Inactive Builder Candidate',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'builder_json' => json_encode($document, JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'is_active' => false,
        ])
        ->assertRedirect();

    $candidate = ResultSheetTemplate::query()->where('name', 'Inactive Builder Candidate')->firstOrFail();

    app()->instance(PrintRenderer::class, new class implements PrintRenderer
    {
        public function render(string $html, array $options = []): string
        {
            return 'not-a-pdf';
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => actingAsAdmissionSupportStaff($this, $guidance)
        ->put(route('setup.result-templates.update', $candidate), [
            'name' => 'Invalid Active Builder Candidate',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'builder_json' => json_encode($document, JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'is_active' => true,
            'lock_version' => $candidate->lock_version,
        ]))->toThrow(RuntimeException::class, 'template_pdf_verification_failed');

    expect($candidate->fresh()->is_active)->toBeFalse()
        ->and($candidate->fresh()->name)->toBe('Inactive Builder Candidate')
        ->and($prior->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('activating a template deactivates the previous active one atomically', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $a = ResultSheetTemplate::query()->create(resultTemplatePayload(['name' => 'A', 'is_active' => true]));
    $b = ResultSheetTemplate::query()->create(resultTemplatePayload(['name' => 'B', 'is_active' => false]));

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.activate', $b))
        ->assertRedirect();

    expect($a->fresh()->is_active)->toBeFalse()
        ->and($b->fresh()->is_active)->toBeTrue();
});

test('deactivating the active template leaves no active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    ResultSheetTemplate::query()->update(['is_active' => false]);
    $a = ResultSheetTemplate::query()->create(resultTemplatePayload(['is_active' => true]));

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.deactivate', $a))
        ->assertRedirect();

    expect(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(0);
});

test('non-assessment staff cannot manage result templates', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $registrar)
        ->get(route('setup.result-templates.index'))
        ->assertForbidden();

    actingAsAdmissionSupportStaff($this, $registrar)
        ->post(route('setup.result-templates.store'), resultTemplatePayload())
        ->assertForbidden();
});

test('docx template upload is stored privately with a checksum', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $expectedChecksum = hash_file('sha256', $asset);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Word Results',
            'mode' => 'docx',
            'paper_size' => 'legal',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'document' => new UploadedFile($asset, 'generic_results.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
            'is_active' => false,
        ])
        ->assertRedirect();

    $template = ResultSheetTemplate::query()->where('name', 'Word Results')->firstOrFail();
    expect($template->mode)->toBe('docx')
        ->and($template->paper_size)->toBe('legal')
        ->and($template->document_checksum)->toBe($expectedChecksum)
        ->and($template->document_extension)->toBe('docx')
        ->and(Storage::disk('local')->exists($template->document_path))->toBeTrue();
});

test('storing an invalid active word template preserves the current active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $this->withoutExceptionHandling();

    expect(fn () => actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Invalid Active Word',
            'mode' => ResultSheetTemplate::MODE_DOCX,
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'document' => UploadedFile::fake()->create(
                'invalid-active.docx',
                10,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            'is_active' => true,
        ]))->toThrow(RuntimeException::class);

    expect(ResultSheetTemplate::query()->where('name', 'Invalid Active Word')->doesntExist())->toBeTrue()
        ->and($prior->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('updating a word template to active verifies before replacing the current active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();
    $candidate = ResultSheetTemplate::query()->create([
        'name' => 'Inactive Word Candidate',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => ResultSheetTemplate::PAPER_A4,
        'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
        'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
        'is_active' => false,
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => actingAsAdmissionSupportStaff($this, $guidance)
        ->put(route('setup.result-templates.update', $candidate), [
            'name' => 'Invalid Active Word Candidate',
            'mode' => ResultSheetTemplate::MODE_DOCX,
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'document' => UploadedFile::fake()->create(
                'invalid-update.docx',
                10,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            'is_active' => true,
        ]))->toThrow(RuntimeException::class);

    expect($candidate->fresh()->is_active)->toBeFalse()
        ->and($candidate->fresh()->document_path)->toBeNull()
        ->and($prior->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('a non-document file is refused for docx mode', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Bad',
            'mode' => 'docx',
            'document' => UploadedFile::fake()->create('bad.txt', 10),
            'is_active' => false,
        ])
        ->assertSessionHasErrors('document');

    expect(ResultSheetTemplate::query()->where('name', 'Bad')->doesntExist())->toBeTrue();
});

test('updating a template replaces its content and keeps single active', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $t = ResultSheetTemplate::query()->create(resultTemplatePayload(['is_active' => true]));

    actingAsAdmissionSupportStaff($this, $guidance)
        ->put(route('setup.result-templates.update', $t), [
            'name' => 'Renamed',
            'mode' => 'html',
            'paper_size' => 'letter',
            'orientation' => 'landscape',
            'logical_unit' => 'full',
            'content' => '<p>Updated {{applicant_name}}</p>',
            'is_active' => true,
        ])
        ->assertRedirect();

    $t->refresh();
    expect($t->name)->toBe('Renamed')
        ->and($t->paper_size)->toBe('letter')
        ->and($t->orientation)->toBe('landscape')
        ->and($t->logical_unit)->toBe('full')
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('activating an invalid word template fails truthfully and preserves the prior active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $invalidDocx = ResultSheetTemplate::query()->create([
        'name' => 'Invalid Docx Candidate',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => 'result-sheet-templates/missing.docx',
        'document_checksum' => 'fake-checksum',
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.activate', $invalidDocx))
        ->assertSessionHasErrors('activation');

    expect($invalidDocx->fresh()->is_active)->toBeFalse()
        ->and($prior->fresh()->is_active)->toBeTrue();
});

test('activating a document template without a checksum preserves the current active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $prior = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $missingChecksum = ResultSheetTemplate::query()->create([
        'name' => 'Missing Document Checksum',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => ResultSheetTemplate::PAPER_A4,
        'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
        'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
        'document_path' => $prior->document_path,
        'document_checksum' => null,
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.activate', $missingChecksum))
        ->assertSessionHasErrors(['activation' => 'template_document_integrity_failed']);

    expect($missingChecksum->fresh()->is_active)->toBeFalse()
        ->and($prior->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});

test('activating a valid official docx template activates it and deactivates prior active template atomically', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $priorActive = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $dest = 'result-sheet-templates/valid_official.docx';
    Storage::disk('local')->put($dest, (string) file_get_contents($asset));

    $docx = ResultSheetTemplate::query()->create([
        'name' => 'Valid Official Template',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $dest,
        'document_checksum' => hash_file('sha256', $asset),
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    $this->instance(
        DocxToPdfConverter::class,
        new class implements DocxToPdfConverter
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function convert(string $documentPath, ?float $timeoutSeconds = null): string
            {
                return "%PDF-1.4\n%mock-valid-pdf\n%%EOF";
            }
        }
    );

    expect($priorActive->is_active)->toBeTrue()
        ->and($docx->is_active)->toBeFalse();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.activate', $docx))
        ->assertRedirect()
        ->assertSessionHas('status', 'Template activated.');

    expect($priorActive->fresh()->is_active)->toBeFalse()
        ->and($docx->fresh()->is_active)->toBeTrue()
        ->and(ResultSheetTemplate::active()->id)->toBe($docx->id);
});

test('activating a docx template with missing file on disk is refused and preserves prior active', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeHtml = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $docx = ResultSheetTemplate::query()->create([
        'name' => 'Missing File Template',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'legal',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => 'result-sheet-templates/noxiamstent_file.docx',
        'document_checksum' => hash('sha256', 'dummy'),
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    $auditBefore = AuditEvent::query()->where('event_type', 'result_template.activated')->count();
    $action = app(ActivateResultSheetTemplate::class);

    expect(fn () => $action->handle($docx, $guidance))
        ->toThrow(RuntimeException::class, 'Template document missing.');

    expect($activeHtml->fresh()->is_active)->toBeTrue()
        ->and($docx->fresh()->is_active)->toBeFalse()
        ->and(ResultSheetTemplate::active()->id)->toBe($activeHtml->id)
        ->and(AuditEvent::query()->where('event_type', 'result_template.activated')->count())->toBe($auditBefore);
});

test('activating a docx template with checksum mismatch is refused and preserves prior active', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeHtml = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $dest = 'result-sheet-templates/mismatch_test.docx';
    Storage::disk('local')->put($dest, (string) file_get_contents($asset));

    $docx = ResultSheetTemplate::query()->create([
        'name' => 'Checksum Mismatch Template',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'legal',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $dest,
        'document_checksum' => 'invalid_sha256_checksum_value',
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    $auditBefore = AuditEvent::query()->where('event_type', 'result_template.activated')->count();
    $action = app(ActivateResultSheetTemplate::class);

    expect(fn () => $action->handle($docx, $guidance))
        ->toThrow(RuntimeException::class, 'Template document integrity failed.');

    expect($activeHtml->fresh()->is_active)->toBeTrue()
        ->and($docx->fresh()->is_active)->toBeFalse()
        ->and(ResultSheetTemplate::active()->id)->toBe($activeHtml->id)
        ->and(AuditEvent::query()->where('event_type', 'result_template.activated')->count())->toBe($auditBefore);
});

test('activating a docx template with missing required or unknown placeholders is refused and preserves prior active', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeHtml = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $zip = new ZipArchive;
    $tempPath = tempnam(sys_get_temp_dir(), 'bad_docx_');
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>{{unknown_macro_field}}</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    $content = (string) file_get_contents($tempPath);
    @unlink($tempPath);

    $dest = 'result-sheet-templates/bad_placeholders.docx';
    Storage::disk('local')->put($dest, $content);

    $docx = ResultSheetTemplate::query()->create([
        'name' => 'Bad Placeholders Template',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'legal',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $dest,
        'document_checksum' => hash('sha256', $content),
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    $auditBefore = AuditEvent::query()->where('event_type', 'result_template.activated')->count();
    $action = app(ActivateResultSheetTemplate::class);

    expect(fn () => $action->handle($docx, $guidance))
        ->toThrow(RuntimeException::class, 'Template placeholders invalid.');

    expect($activeHtml->fresh()->is_active)->toBeTrue()
        ->and($docx->fresh()->is_active)->toBeFalse()
        ->and(ResultSheetTemplate::active()->id)->toBe($activeHtml->id)
        ->and(AuditEvent::query()->where('event_type', 'result_template.activated')->count())->toBe($auditBefore);
});

test('activating a docx template when PDF conversion fails is refused and preserves prior active', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activeHtml = ResultSheetTemplate::query()->where('is_active', true)->firstOrFail();

    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $dest = 'result-sheet-templates/pdf_fail_test.docx';
    $content = (string) file_get_contents($asset);
    Storage::disk('local')->put($dest, $content);

    $docx = ResultSheetTemplate::query()->create([
        'name' => 'PDF Conversion Fail Template',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'legal',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $dest,
        'document_checksum' => hash('sha256', $content),
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    $this->instance(
        DocxToPdfConverter::class,
        new class implements DocxToPdfConverter
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function convert(string $documentPath, ?float $timeoutSeconds = null): string
            {
                throw new RuntimeException('Simulated LibreOffice failure');
            }
        }
    );

    $auditBefore = AuditEvent::query()->where('event_type', 'result_template.activated')->count();
    $action = app(ActivateResultSheetTemplate::class);

    expect(fn () => $action->handle($docx, $guidance))
        ->toThrow(RuntimeException::class, 'Template PDF verification failed.');

    expect($activeHtml->fresh()->is_active)->toBeTrue()
        ->and($docx->fresh()->is_active)->toBeFalse()
        ->and(ResultSheetTemplate::active()->id)->toBe($activeHtml->id)
        ->and(AuditEvent::query()->where('event_type', 'result_template.activated')->count())->toBe($auditBefore);
});

test('authorized result template thumbnail delivers cached PNG and purges on deletion', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ResultSheetTemplate::query()->firstOrFail();
    $thumbnailService = app(DocumentThumbnailService::class);
    $fingerprint = $thumbnailService->fingerprint($template->printChecksum());

    $url = route('setup.result-templates.thumbnail', $template);
    actingAsAdmissionSupportStaff($this, $guidance)->get($url.'?v='.$fingerprint)->assertNotFound();

    $pdfFixture = new FPDF;
    $pdfFixture->AddPage();
    $pdfFixture->SetFont('Helvetica', '', 12);
    $pdfFixture->Text(20, 20, 'Result Sheet Page');
    $thumbnailService->store('result_sheet', $template->id, $template->printChecksum(), (string) $pdfFixture->Output('S'));

    $response = actingAsAdmissionSupportStaff($this, $guidance)->get($url.'?v='.$fingerprint);
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Cache-Control'))->toContain('immutable');

    actingAsAdmissionSupportStaff($this, $guidance)->get($url.'?v=stale_'.str_repeat('0', 58))->assertNotFound();

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $registrar)->get($url.'?v='.$fingerprint)->assertForbidden();

    actingAsAdmissionSupportStaff($this, $guidance)->delete(route('setup.result-templates.destroy', $template))
        ->assertRedirect();
    expect($thumbnailService->read('result_sheet', $template->id, $fingerprint))->toBeNull();
});

test('result template index exposes thumbnail URLs with version fingerprint', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ResultSheetTemplate::query()->orderByDesc('is_active')->orderBy('name')->firstOrFail();
    $thumbnailService = app(DocumentThumbnailService::class);
    $fingerprint = $thumbnailService->fingerprint($template->printChecksum());

    actingAsAdmissionSupportStaff($this, $guidance)->get(route('setup.result-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates.0.thumbnail_url')
            ->where('templates.0.thumbnail_url', route('setup.result-templates.thumbnail', $template).'?v='.$fingerprint));
});
