<?php

use App\Models\Printing\PrintJob;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\DocxFiller;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('validate-document reports found and missing placeholders for a docx template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');

    $response = $this->actingAs($guidance)
        ->post(route('setup.result-templates.validate-document'), [
            'logical_unit' => 'full',
            'document' => new UploadedFile($asset, 'generic_results.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])
        ->assertOk();

    $json = $response->json();
    expect($json)->toHaveKey('found')
        ->and($json)->toHaveKey('missing')
        ->and($json)->toHaveKey('extra')
        ->and($json['found'])->toContain('applicant_name')
        ->and($json['found'])->toContain('scores_rows');
});

test('analyzer flags missing required placeholders in html content', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.validate-document'), [
            'logical_unit' => 'full',
            'content' => '<p>No placeholders here</p>',
            'mode' => 'html',
        ])
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('valid', false)
            ->where('missing', ['applicant_reference'])
            ->has('found')
            ->has('recommended_missing')
            ->has('extra')
            ->has('unresolved')
        );
});

test('preview renders an html template with sample applicant data', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'html',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'content' => '<p>Hello {{applicant_name}} ({{applicant_reference}})</p>',
        ])
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('html', fn ($html) => str_contains($html, 'application/pdf'))
            ->where('pdf_base64', fn ($b64) => str_starts_with((string) base64_decode($b64), '%PDF'))
            ->etc()
        );
});

test('preview renders a valid builder document with compiled context', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'html',
            'builder_json' => json_encode(ResultSheetBuilderSchema::defaultDocument(), JSON_THROW_ON_ERROR),
            'content' => '<p>legacy fallback must not render</p>',
        ])
        ->assertOk()
        ->assertJsonPath('pdf_base64', fn ($b64): bool => str_starts_with((string) base64_decode($b64), '%PDF'));
});

test('preview rejects malformed builder json instead of falling back to html', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'html',
            'builder_json' => '{',
            'content' => '<p>legacy fallback must not render</p>',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'builder_invalid');
});

test('analyzer rejects nested or extra macro braces', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.validate-document'), [
            'logical_unit' => 'full',
            'content' => '<p>{{{applicant_name}}}</p>',
            'mode' => 'html',
        ])
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJson(fn ($json) => $json->where('unresolved.0', '{{{')->etc());
});

test('preview renders an uploaded docx template without unresolved macros', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');

    $response = $this->actingAs($guidance)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'docx',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'document' => new UploadedFile($asset, 'generic_results.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])
        ->assertOk();

    $job = PrintJob::query()->findOrFail($response->json('preview_job_id'));
    expect($job->status)->toBe(PrintJob::STATUS_READY);

    $pdf = app(SensitiveAssetStore::class)->read((string) $job->file_path);

    expect($pdf)->toStartWith('%PDF')
        ->and($job->file_checksum)->toBe(hash('sha256', $pdf));
});

test('docx replacement preserves the placeholder run typography', function (): void {
    seedE2Foundation();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $styledSource = storage_path('app/temp/libreoffice/font-preservation-'.uniqid().'.docx');
    @mkdir(dirname($styledSource), 0755, true);
    copy($asset, $styledSource);

    $zip = new ZipArchive;
    expect($zip->open($styledSource))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    expect($xml)->toBeString();
    $xml = str_replace(
        '<w:rPr><w:b/><w:sz w:val="28"/></w:rPr>',
        '<w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:b/><w:sz w:val="28"/></w:rPr>',
        $xml,
        $count,
    );
    expect($count)->toBe(1);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    $filled = null;

    try {
        $filled = app(DocxFiller::class)->fill($styledSource, ['institution_name' => 'NEXIAM']);
        $filledZip = new ZipArchive;
        expect($filledZip->open($filled))->toBeTrue();
        $filledXml = $filledZip->getFromName('word/document.xml');
        $filledZip->close();

        expect($filledXml)->toBeString()
            ->and($filledXml)->not->toContain('{{institution_name}}')
            ->and($filledXml)->toContain('NEXIAM')
            ->and($filledXml)->toContain('w:ascii="Aptos"')
            ->and($filledXml)->toContain('w:hAnsi="Aptos"')
            ->and($filledXml)->toContain('<w:b/>')
            ->and($filledXml)->toContain('<w:sz w:val="28"/>');
    } finally {
        if (is_string($filled) && is_file($filled)) {
            @unlink($filled);
        }
        @unlink($styledSource);
    }
});

test('preview renders a stored docx template without deleting its source', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ResultSheetTemplate::query()->where('name', 'ICAT Results (Word)')->firstOrFail();
    $sourcePath = (string) $template->document_path;

    expect(Storage::disk('local')->exists($sourcePath))->toBeTrue();

    $response = $this->actingAs($guidance)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'docx',
            'template_id' => $template->id,
            'paper_size' => 'legal',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
        ])
        ->assertOk();

    $json = $response->json();

    expect($json['preview_job_id'])->toBeInt()
        ->and($json['preview_url'])->toContain('/printing/jobs/')
        ->and(Storage::disk('local')->exists($sourcePath))->toBeTrue();
});

test('non-assessment staff cannot validate or preview templates', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');

    $this->actingAs($registrar)
        ->post(route('setup.result-templates.validate-document'), [
            'logical_unit' => 'full',
            'document' => new UploadedFile($asset, 'generic_results.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'html',
            'content' => '<p>x</p>',
        ])
        ->assertForbidden();
});

test('official seeded ICAT word template validates with applicant_number alias as valid', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/template_main_icat_test1.docx');

    $response = $this->actingAs($guidance)
        ->post(route('setup.result-templates.validate-document'), [
            'logical_unit' => 'full',
            'document' => new UploadedFile($asset, 'template_main_icat_test1.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])
        ->assertOk();

    $json = $response->json();
    expect($json['valid'])->toBeTrue()
        ->and($json['missing'])->toBeEmpty()
        ->and($json['extra'])->toBeEmpty()
        ->and($json['unresolved'])->toBeEmpty()
        ->and($json['found'])->toContain('applicant_number');
});

test('short abbreviation snippets such as ref and name satisfy required placeholders', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $response = $this->actingAs($guidance)
        ->post(route('setup.result-templates.validate-document'), [
            'logical_unit' => 'full',
            'content' => '<p>Results for {{name}} ({{ref}}) - Room {{rm}} on {{date}}</p>',
            'mode' => 'html',
        ])
        ->assertOk();

    $json = $response->json();
    expect($json['valid'])->toBeTrue()
        ->and($json['missing'])->toBeEmpty()
        ->and($json['found'])->toContain('ref')
        ->and($json['found'])->toContain('name')
        ->and($json['found'])->toContain('rm')
        ->and($json['found'])->toContain('date');
});
