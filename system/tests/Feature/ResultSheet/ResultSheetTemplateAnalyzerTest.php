<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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
            ->where('html', fn ($html) => str_contains($html, 'Juan') && ! str_contains($html, '{{applicant_name}}'))
        );
});

test('preview renders an uploaded docx template without unresolved macros', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.preview'), [
            'mode' => 'docx',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'document' => new UploadedFile($asset, 'generic_results.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('html', fn ($html) => ! str_contains($html, '{{applicant_name}}'))
        );
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
