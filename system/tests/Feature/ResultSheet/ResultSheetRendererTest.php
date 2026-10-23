<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
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

test('builder render rejects score rows that exceed the persisted table height', function (): void {
    seedE2Foundation();
    $sample = app(ResultSheetPlaceholderService::class)->sampleData();
    $sample['scores'][] = ['domain' => 'Additional Area', 'raw' => 85, 'converted' => 'Above Average'];
    $document = ResultSheetBuilderSchema::defaultDocument();

    $template = new ResultSheetTemplate([
        'name' => 'Builder overflow',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
        'builder_json' => $document,
        'content' => '<div class="result-sheet-builder"></div>',
    ]);

    expect(fn () => app(ResultSheetRenderer::class)->renderForApplicants($template, [$sample]))
        ->toThrow(RuntimeException::class, 'builder_invalid');
});

test('docx template fills and converts through the docx converter', function (): void {
    seedE2Foundation();

    $fake = new class implements DocxToPdfConverter
    {
        public string $convertedPath = '';

        public bool $existedAtConvert = false;

        public ?float $timeoutSeconds = null;

        public function isAvailable(): bool
        {
            return true;
        }

        public function convert(string $documentPath, ?float $timeoutSeconds = null): string
        {
            $this->convertedPath = $documentPath;
            $this->existedAtConvert = is_file($documentPath);
            $this->timeoutSeconds = $timeoutSeconds;

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

    $artifacts = $renderer->renderItem($template, [$data], 4.25);

    expect($artifacts['pdf'])->toStartWith('%PDF')
        ->and($artifacts['docx'])->not->toBeNull()
        ->and(strlen((string) $artifacts['docx']))->toBeGreaterThan(1000)
        ->and($fake->existedAtConvert)->toBeTrue()
        ->and($fake->convertedPath)->toContain('docx_filled_')
        ->and($fake->timeoutSeconds)->toBe(4.25);
});

test('docx render without an available converter fails truthfully', function (): void {
    seedE2Foundation();

    $unavailable = new class implements DocxToPdfConverter
    {
        public function isAvailable(): bool
        {
            return false;
        }

        public function convert(string $documentPath, ?float $timeoutSeconds = null): string
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
