<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

    $this->actingAs($guidance)
        ->get(route('setup.result-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/ResultSheetTemplates/Index'));
});

test('creating an active HTML template makes it the single active template', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    ResultSheetTemplate::query()->create(resultTemplatePayload(['name' => 'Old', 'is_active' => true]));

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.store'), resultTemplatePayload(['name' => 'New']))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1)
        ->and(ResultSheetTemplate::query()->where('is_active', true)->value('name'))->toBe('New');
});

test('activating a template deactivates the previous active one atomically', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $a = ResultSheetTemplate::query()->create(resultTemplatePayload(['name' => 'A', 'is_active' => true]));
    $b = ResultSheetTemplate::query()->create(resultTemplatePayload(['name' => 'B', 'is_active' => false]));

    $this->actingAs($guidance)
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

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.deactivate', $a))
        ->assertRedirect();

    expect(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(0);
});

test('non-assessment staff cannot manage result templates', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.result-templates.index'))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('setup.result-templates.store'), resultTemplatePayload())
        ->assertForbidden();
});

test('docx template upload is stored privately with a checksum', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $expectedChecksum = hash_file('sha256', $asset);

    $this->actingAs($guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Word Results',
            'mode' => 'docx',
            'paper_size' => 'legal',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'document' => new Illuminate\Http\UploadedFile($asset, 'generic_results.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
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

test('a non-document file is refused for docx mode', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
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

    $this->actingAs($guidance)
        ->put(route('setup.result-templates.update', $t), [
            'name' => 'Renamed',
            'mode' => 'html',
            'paper_size' => 'letter',
            'orientation' => 'landscape',
            'logical_unit' => 'half_letter',
            'content' => '<p>Updated {{applicant_name}}</p>',
            'is_active' => true,
        ])
        ->assertRedirect();

    $t->refresh();
    expect($t->name)->toBe('Renamed')
        ->and($t->paper_size)->toBe('letter')
        ->and($t->orientation)->toBe('landscape')
        ->and($t->logical_unit)->toBe('half_letter')
        ->and(ResultSheetTemplate::query()->where('is_active', true)->count())->toBe(1);
});
