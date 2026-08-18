<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Rendering\DocxToPdfConverter;
use App\ResultSheet\Rendering\ResultSheetRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;

function htmlTemplate(): ResultSheetTemplate
{
    return new ResultSheetTemplate([
        'name' => 'HTML Results',
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{institution_name}}</h1><p>{{applicant_name}} ({{applicant_reference}})</p>'
            .'<table><tbody class="scores-rows-placeholder"></tbody></table>',
        'watermark_text' => 'DRAFT',
    ]);
}

test('html template renders pdf bytes with sample data', function (): void {
    seedE2Foundation();

    $renderer = app(ResultSheetRenderer::class);
    $data = app(ResultSheetPlaceholderService::class)->sampleData();

    $bytes = $renderer->renderForApplicants(htmlTemplate(), [$data]);

    expect($bytes)->toStartWith('%PDF')
        ->and(strlen($bytes))->toBeGreaterThan(1000);
});

test('docx template fills and converts through the docx converter', function (): void {
    seedE2Foundation();

    $fake = new class implements DocxToPdfConverter
    {
        public string $convertedPath = '';

        public bool $existedAtConvert = false;

        public function isAvailable(): bool
        {
            return true;
        }

        public function convert(string $documentPath): string
        {
            $this->convertedPath = $documentPath;
            $this->existedAtConvert = is_file($documentPath);

            return '%PDF-1.4 fake-converted';
        }
    };

    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    Storage::disk('local')->put('result-sheet-templates/fixture.docx', (string) file_get_contents($asset));

    $renderer = app(ResultSheetRenderer::class, ['converter' => $fake]);
    $data = app(ResultSheetPlaceholderService::class)->sampleData();
    $template = new ResultSheetTemplate([
        'name' => 'Word Results',
        'mode' => 'docx',
        'document_path' => 'result-sheet-templates/fixture.docx',
        'document_extension' => 'docx',
    ]);

    $bytes = $renderer->renderForApplicants($template, [$data]);

    expect($bytes)->toStartWith('%PDF')
        ->and($fake->existedAtConvert)->toBeTrue()
        ->and($fake->convertedPath)->toContain('docx_filled_');
});

test('dual slot template renders two applicants on one sheet', function (): void {
    seedE2Foundation();

    $renderer = app(ResultSheetRenderer::class);
    $service = app(ResultSheetPlaceholderService::class);
    $data1 = $service->sampleData();
    $data2 = $service->sampleData();
    $data2['name'] = 'Maria L. Santos';
    $data2['reference'] = '2026-000456';

    $template = htmlTemplate();
    $template->logical_unit = ResultSheetTemplate::LOGICAL_HALF_A4;

    $bytes = $renderer->renderForApplicants($template, [$data1, $data2]);

    expect($bytes)->toStartWith('%PDF')
        ->and(strlen($bytes))->toBeGreaterThan(1000);
});

test('docx render without an available converter fails truthfully', function (): void {
    seedE2Foundation();

    $unavailable = new class implements DocxToPdfConverter
    {
        public function isAvailable(): bool
        {
            return false;
        }

        public function convert(string $documentPath): string
        {
            throw new RuntimeException('unreachable');
        }
    };

    $renderer = app(ResultSheetRenderer::class, ['converter' => $unavailable]);
    $data = app(ResultSheetPlaceholderService::class)->sampleData();
    $template = new ResultSheetTemplate([
        'name' => 'Word Results',
        'mode' => 'docx',
        'document_path' => 'result-sheet-templates/fixture.docx',
        'document_extension' => 'docx',
    ]);

    expect(fn () => $renderer->renderForApplicants($template, [$data]))
        ->toThrow(RuntimeException::class, 'libreoffice_unavailable');
});
