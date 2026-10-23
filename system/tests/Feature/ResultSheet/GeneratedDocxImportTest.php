<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Rendering\DocxFiller;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use App\ResultSheet\Support\ResultSheetTemplateAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ResultSheetDocxFixture;

function resultSheetSortedUniqueStrings(array $values): array
{
    $values = array_values(array_unique(array_map(static fn ($value): string => (string) $value, $values)));
    sort($values);

    return $values;
}

test('a generated docx covers every advertised and fillable placeholder as one set', function (): void {
    seedE2Foundation();

    $analyzer = app(ResultSheetTemplateAnalyzer::class);
    $placeholders = app(ResultSheetPlaceholderService::class);
    $catalog = $analyzer->catalogKeys();
    $source = ResultSheetDocxFixture::createValid($catalog['all']);

    try {
        $analysis = $analyzer->analyze(['document_path' => $source]);
        $expected = resultSheetSortedUniqueStrings($catalog['all']);
        $found = resultSheetSortedUniqueStrings($analysis['found']);
        $replacementKeys = array_keys($placeholders->buildDocxReplacements([$placeholders->sampleData()]));

        expect($analysis)->toHaveKey('unresolved')
            ->and($analysis['valid'])->toBeTrue()
            ->and($found)->toBe($expected)
            ->and($analysis['found'])->toHaveCount(count($found))
            ->and(resultSheetSortedUniqueStrings($analysis['missing']))->toBe([])
            ->and(resultSheetSortedUniqueStrings($analysis['extra']))->toBe([])
            ->and(resultSheetSortedUniqueStrings($analysis['unresolved']))->toBe([])
            ->and(resultSheetSortedUniqueStrings(array_diff($catalog['all'], $replacementKeys)))->toBe([]);
    } finally {
        ResultSheetDocxFixture::delete($source);
    }
});

test('docx filler replaces macros across body table header footer and XML boundaries', function (): void {
    seedE2Foundation();

    $analyzer = app(ResultSheetTemplateAnalyzer::class);
    $placeholders = app(ResultSheetPlaceholderService::class);
    $source = ResultSheetDocxFixture::createValid($analyzer->catalogKeys()['all']);
    $filled = null;

    $unicodeName = 'Zoë & <Admissions> — 測試';
    $reference = 'REF<&>"-2026';

    try {
        $sample = $placeholders->sampleData();
        $sample['name'] = $unicodeName;
        $sample['reference'] = $reference;
        $replacements = $placeholders->buildDocxReplacements([$sample]);

        $filled = app(DocxFiller::class)->fill($source, $replacements);
        $xmlParts = ResultSheetDocxFixture::readWordXmlParts($filled);
        $textParts = ResultSheetDocxFixture::decodeWordXmlParts($filled);

        expect($textParts)->toHaveKeys(['word/document.xml', 'word/header1.xml', 'word/footer1.xml'])
            ->and($textParts['word/document.xml'])->toContain($unicodeName)
            ->and($textParts['word/document.xml'])->toContain('Table cell: '.$reference)
            ->and($textParts['word/document.xml'])->toContain($reference.$unicodeName)
            ->and($textParts['word/header1.xml'])->toContain('Header styled split: '.$replacements['institution_name'])
            ->and($textParts['word/footer1.xml'])->toContain('Footer styled split: '.$replacements['counselor_name']);

        foreach ($xmlParts as $name => $xml) {
            $document = new DOMDocument;

            expect($document->loadXML($xml))->toBeTrue("{$name} must remain valid XML")
                ->and($textParts[$name])->not->toContain('{{')
                ->and($textParts[$name])->not->toContain('}}');
        }
    } finally {
        if ($filled !== null) {
            ResultSheetDocxFixture::delete($filled);
        }

        ResultSheetDocxFixture::delete($source);
    }
});

test('validate-document reports unknown and malformed macros in a generated invalid docx', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $source = ResultSheetDocxFixture::createInvalid();

    try {
        $json = $this->actingAs($guidance)
            ->post(route('setup.result-templates.validate-document'), [
                'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                'document' => new UploadedFile(
                    $source,
                    'generated-invalid.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true,
                ),
            ])
            ->assertOk()
            ->json();

        expect($json['valid'])->toBeFalse()
            ->and($json['found'])->toContain('applicant_reference')
            ->and($json['found'])->toContain('xyz')
            ->and(resultSheetSortedUniqueStrings($json['extra']))->toBe(['xyz'])
            ->and($json)->toHaveKey('unresolved')
            ->and(collect($json['unresolved'])->contains(
                static fn ($fragment): bool => str_contains((string) $fragment, 'unclosed'),
            ))->toBeTrue();
    } finally {
        ResultSheetDocxFixture::delete($source);
    }
});

test('posting a generated docx stores byte-identical private inactive content and metadata', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $catalog = app(ResultSheetTemplateAnalyzer::class)->catalogKeys();
    $source = ResultSheetDocxFixture::createValid($catalog['all']);
    $templateName = 'Generated DOCX Regression';
    $storedPath = null;

    try {
        $bytes = (string) file_get_contents($source);
        $checksum = hash('sha256', $bytes);

        $this->actingAs($guidance)
            ->post(route('setup.result-templates.store'), [
                'name' => $templateName,
                'mode' => ResultSheetTemplate::MODE_DOCX,
                'paper_size' => ResultSheetTemplate::PAPER_LEGAL,
                'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
                'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                'document' => new UploadedFile(
                    $source,
                    'generated-valid.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true,
                ),
                'is_active' => false,
            ])
            ->assertRedirect();

        $template = ResultSheetTemplate::query()->where('name', $templateName)->firstOrFail();
        $storedPath = $template->document_path;
        $storedBytes = Storage::disk('local')->get($storedPath);

        expect($template->name)->toBe($templateName)
            ->and($template->mode)->toBe(ResultSheetTemplate::MODE_DOCX)
            ->and($template->paper_size)->toBe(ResultSheetTemplate::PAPER_LEGAL)
            ->and($template->orientation)->toBe(ResultSheetTemplate::ORIENTATION_PORTRAIT)
            ->and($template->logical_unit)->toBe(ResultSheetTemplate::LOGICAL_FULL)
            ->and($template->is_active)->toBeFalse()
            ->and($template->document_extension)->toBe('docx')
            ->and($template->document_checksum)->toBe($checksum)
            ->and($template->created_by_user_id)->toBe($guidance->id)
            ->and(Storage::disk('local')->exists($storedPath))->toBeTrue()
            ->and($storedBytes)->toBe($bytes)
            ->and(hash('sha256', $storedBytes))->toBe($checksum);
    } finally {
        $path = $storedPath ?? ResultSheetTemplate::query()
            ->where('name', $templateName)
            ->value('document_path');

        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
        }

        ResultSheetDocxFixture::delete($source);
    }
});
