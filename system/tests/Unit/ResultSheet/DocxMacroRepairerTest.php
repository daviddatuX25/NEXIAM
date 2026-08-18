<?php

use App\ResultSheet\Support\DocxMacroRepairer;
use App\ResultSheet\Support\DocxTextExtractor;

function runXml(string ...$texts): string
{
    $runs = '';

    foreach ($texts as $text) {
        $runs .= '<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'.$text.'</w:t></w:r>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p>'.$runs.'</w:p></w:body></w:document>';
}

test('repair merges a macro fragmented across adjacent runs', function (): void {
    $xml = runXml('Hello {{appli', 'cant_name}}!');

    $repaired = app(DocxMacroRepairer::class)->repairXml($xml);

    preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $repaired, $matches);

    expect($matches[1] ?? [])->toContain('applicant_name')
        ->and($repaired)->not->toContain('{{appli</w:t>');
});

test('extractor sees a whole macro after repair', function (): void {
    $xml = runXml('{{applicant_', 'reference}} and {{scores_', 'rows}}');
    $repaired = app(DocxMacroRepairer::class)->repairXml($xml);

    $temp = tempnam(sys_get_temp_dir(), 'repair_').'.docx';
    $zip = new ZipArchive;
    $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $repaired);
    $zip->close();

    $placeholders = app(DocxTextExtractor::class)->placeholders($temp);
    @unlink($temp);

    expect($placeholders)->toContain('applicant_reference')
        ->and($placeholders)->toContain('scores_rows');
});

test('repair strips xml tags still inside macros as a fallback', function (): void {
    $xml = runXml('{{applicant', '<w:r><w:t>_name</w:t></w:r>}}');
    $repaired = app(DocxMacroRepairer::class)->repairXml($xml);

    expect($repaired)->toContain('{{applicant_name}}');
});

test('repair preserves text-box structure inside paragraphs', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p><w:r><w:t xml:space="preserve">A {{ap'
        .'plicant_name}}</w:t></w:r>'
        .'<w:r><w:t xml:space="preserve">tail</w:t></w:r></w:p>'
        .'<w:p><w:r><w:t xml:space="preserve">outside</w:t></w:r></w:p></w:body></w:document>';

    $repaired = app(DocxMacroRepairer::class)->repairXml($xml);

    // Must remain well-formed XML with all runs intact.
    libxml_use_internal_errors(true);
    $ok = simplexml_load_string($repaired);
    expect($ok)->not->toBeFalse()
        ->and($repaired)->toContain('{{applicant_name}}')
        ->and(substr_count($repaired, '<w:r>'))->toBe(3)
        ->and(substr_count($repaired, '</w:r>'))->toBe(3);
});
