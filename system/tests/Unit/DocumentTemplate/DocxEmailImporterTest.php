<?php

declare(strict_types=1);

use App\DocumentTemplate\Import\DocxEmailImporter;
use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\DocumentTemplate\Import\DocxPackageInspector;
use App\Notifications\Templates\Email\NoticeEmailSchema;

function createImporterTestDocx(string $documentXml, array $extraFiles = []): string
{
    $tempPath = tempnam(sys_get_temp_dir(), 'test_imp_');
    $zip = new ZipArchive;
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $defaultFiles = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>',
        'word/document.xml' => $documentXml,
        'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>',
    ];

    $merged = array_merge($defaultFiles, $extraFiles);
    foreach ($merged as $name => $content) {
        $zip->addFromString($name, (string) $content);
    }
    $zip->close();

    return $tempPath;
}

beforeEach(function (): void {
    $this->importer = new DocxEmailImporter;
});

test('converts basic paragraphs and text formatting into paragraph blocks', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p>
                <w:pPr>
                    <w:jc w:val="center"/>
                </w:pPr>
                <w:r>
                    <w:rPr>
                        <w:b/>
                        <w:color w:val="FF0000"/>
                    </w:rPr>
                    <w:t>Bold Red Text</w:t>
                </w:r>
                <w:r>
                    <w:rPr>
                        <w:i/>
                        <w:u w:val="single"/>
                    </w:rPr>
                    <w:t> and Italic Underlined Text</w:t>
                </w:r>
            </w:p>
        </w:body>
    </w:document>';

    $path = createImporterTestDocx($xml);

    try {
        $result = $this->importer->import($path);
        NoticeEmailSchema::assertValid($result->candidateBlueprint);

        $blocks = $result->candidateBlueprint['blocks'];
        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['type'])->toBe('paragraph')
            ->and($blocks[0]['align'])->toBe('center')
            ->and($blocks[0]['runs'])->toHaveCount(2);

        $run1 = $blocks[0]['runs'][0];
        expect($run1['text'])->toBe('Bold Red Text')
            ->and($run1['bold'])->toBeTrue()
            ->and($run1['color'])->toBe('#FF0000');

        $run2 = $blocks[0]['runs'][1];
        expect($run2['text'])->toBe(' and Italic Underlined Text')
            ->and($run2['italic'])->toBeTrue()
            ->and($run2['underline'])->toBeTrue();
    } finally {
        @unlink($path);
    }
});

test('converts heading paragraph styles into heading blocks', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p>
                <w:pPr>
                    <w:pStyle w:val="Heading1"/>
                </w:pPr>
                <w:r><w:t>Main Title</w:t></w:r>
            </w:p>
            <w:p>
                <w:pPr>
                    <w:pStyle w:val="Heading2"/>
                </w:pPr>
                <w:r><w:t>Subtitle</w:t></w:r>
            </w:p>
            <w:p>
                <w:pPr>
                    <w:outlineLvl w:val="2"/>
                </w:pPr>
                <w:r><w:t>Outline Heading 3</w:t></w:r>
            </w:p>
        </w:body>
    </w:document>';

    $path = createImporterTestDocx($xml);

    try {
        $result = $this->importer->import($path);
        NoticeEmailSchema::assertValid($result->candidateBlueprint);

        $blocks = $result->candidateBlueprint['blocks'];
        expect($blocks)->toHaveCount(3)
            ->and($blocks[0]['type'])->toBe('heading')
            ->and($blocks[0]['level'])->toBe(1)
            ->and($blocks[0]['runs'][0]['text'])->toBe('Main Title')
            ->and($blocks[1]['type'])->toBe('heading')
            ->and($blocks[1]['level'])->toBe(2)
            ->and($blocks[1]['runs'][0]['text'])->toBe('Subtitle')
            ->and($blocks[2]['type'])->toBe('heading')
            ->and($blocks[2]['level'])->toBe(3)
            ->and($blocks[2]['runs'][0]['text'])->toBe('Outline Heading 3');
    } finally {
        @unlink($path);
    }
});

test('reconciles tokens split across word runs into clean tokens', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p>
                <w:r><w:t>Dear {{</w:t></w:r>
                <w:r><w:t>applicant_</w:t></w:r>
                <w:r><w:t>name}},</w:t></w:r>
                <w:r><w:t> your reference is {{application_no}}.</w:t></w:r>
            </w:p>
        </w:body>
    </w:document>';

    $path = createImporterTestDocx($xml);

    try {
        $result = $this->importer->import($path, allowedTokens: ['applicant_name', 'application_no']);
        NoticeEmailSchema::assertValid($result->candidateBlueprint, ['applicant_name', 'application_no']);

        $blocks = $result->candidateBlueprint['blocks'];
        expect($blocks)->toHaveCount(1);

        $paragraph = $blocks[0];
        $runs = $paragraph['runs'];

        // Token must be reconstructed and balanced
        $combinedText = '';
        foreach ($runs as $r) {
            $combinedText .= $r['text'];
        }

        expect($combinedText)->toBe('Dear {{applicant_name}}, your reference is {{application_no}}.')
            ->and(substr_count($combinedText, '{{'))->toBe(2)
            ->and(substr_count($combinedText, '}}'))->toBe(2);

        // Every individual run must not have unbalanced braces
        foreach ($runs as $r) {
            expect(substr_count($r['text'], '{{'))->toBe(substr_count($r['text'], '}}'));
        }
    } finally {
        @unlink($path);
    }
});

test('converts list items into a list block', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:p>
                <w:pPr>
                    <w:numPr>
                        <w:ilvl w:val="0"/>
                        <w:numId w:val="1"/>
                    </w:numPr>
                </w:pPr>
                <w:r><w:t>First requirement</w:t></w:r>
            </w:p>
            <w:p>
                <w:pPr>
                    <w:numPr>
                        <w:ilvl w:val="0"/>
                        <w:numId w:val="1"/>
                    </w:numPr>
                </w:pPr>
                <w:r><w:t>Second requirement</w:t></w:r>
            </w:p>
        </w:body>
    </w:document>';

    $path = createImporterTestDocx($xml, [
        'word/numbering.xml' => '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>',
    ]);

    try {
        $result = $this->importer->import($path);
        NoticeEmailSchema::assertValid($result->candidateBlueprint);

        $blocks = $result->candidateBlueprint['blocks'];
        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['type'])->toBe('list')
            ->and($blocks[0]['ordered'])->toBeTrue()
            ->and($blocks[0]['items'])->toHaveCount(2);

        expect($blocks[0]['items'][0]['runs'][0]['text'])->toBe('First requirement')
            ->and($blocks[0]['items'][1]['runs'][0]['text'])->toBe('Second requirement');
    } finally {
        @unlink($path);
    }
});

test('converts rectangular table with rows and cells', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
        <w:body>
            <w:tbl>
                <w:tr>
                    <w:tc><w:p><w:r><w:t>Col 1 Header</w:t></w:r></w:p></w:tc>
                    <w:tc><w:p><w:r><w:t>Col 2 Header</w:t></w:r></w:p></w:tc>
                </w:tr>
                <w:tr>
                    <w:tc><w:p><w:r><w:t>Row 1 Val 1</w:t></w:r></w:p></w:tc>
                    <w:tc><w:p><w:r><w:t>Row 1 Val 2</w:t></w:r></w:p></w:tc>
                </w:tr>
            </w:tbl>
        </w:body>
    </w:document>';

    $path = createImporterTestDocx($xml);

    try {
        $result = $this->importer->import($path);
        NoticeEmailSchema::assertValid($result->candidateBlueprint);

        $blocks = $result->candidateBlueprint['blocks'];
        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['type'])->toBe('table')
            ->and($blocks[0]['rows'])->toHaveCount(2)
            ->and($blocks[0]['rows'][0])->toHaveCount(2)
            ->and($blocks[0]['rows'][1])->toHaveCount(2);
    } finally {
        @unlink($path);
    }
});

test('converts hyperlinks to link properties in text runs', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
        <w:body>
            <w:p>
                <w:hyperlink r:id="rId1">
                    <w:r><w:t>Visit portal here</w:t></w:r>
                </w:hyperlink>
            </w:p>
        </w:body>
    </w:document>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://portal.nexiam.edu" TargetMode="External"/>
    </Relationships>';

    $path = createImporterTestDocx($xml, [
        'word/_rels/document.xml.rels' => $relsXml,
    ]);

    try {
        $result = $this->importer->import($path);
        NoticeEmailSchema::assertValid($result->candidateBlueprint);

        $blocks = $result->candidateBlueprint['blocks'];
        expect($blocks)->toHaveCount(1);
        $run = $blocks[0]['runs'][0];
        expect($run['text'])->toBe('Visit portal here')
            ->and($run['link'])->toBe('https://portal.nexiam.edu');
    } finally {
        @unlink($path);
    }
});

test('detects unsupported elements and reports findings deterministically', function (): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
        <w:body>
            <w:headerReference w:type="default" r:id="rIdH"/>
            <w:p>
                <w:r><w:t>Paragraph with page break</w:t></w:r>
                <w:br w:type="page"/>
            </w:p>
            <w:p>
                <m:oMath><m:r><m:t>x + y = z</m:t></m:r></m:oMath>
            </w:p>
            <w:p>
                <w:r><w:footnoteReference w:id="1"/></w:r>
                <w:r><w:t>Some text with footnote</w:t></w:r>
            </w:p>
            <w:tbl>
                <w:tr>
                    <w:tc>
                        <w:tcPr><w:vMerge w:val="restart"/></w:tcPr>
                        <w:p><w:r><w:t>Merged cell</w:t></w:r></w:p>
                    </w:tc>
                </w:tr>
            </w:tbl>
        </w:body>
    </w:document>';

    $path = createImporterTestDocx($xml);

    try {
        $result = $this->importer->import($path);
        $report = $result->report;

        expect($report)->toHaveKey('report_sha256')
            ->and($report)->toHaveKey('source_sha256')
            ->and($report)->toHaveKey('findings')
            ->and(strlen($report['report_sha256']))->toBe(64)
            ->and($report['findings'])->not->toBeEmpty();

        $kinds = array_column($report['findings'], 'kind');
        expect($kinds)->toContain('unsupported_body')
            ->and($kinds)->toContain('page_control')
            ->and($kinds)->toContain('unsupported_oMath')
            ->and($kinds)->toContain('unsupported_footnoteReference')
            ->and($kinds)->toContain('unsupported_table');
    } finally {
        @unlink($path);
    }
});

test('preserves multiple split tokens and unicode surrounding formatted runs', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>José {{app</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>licant_name}} and {{por</w:t></w:r><w:r><w:t>tal_url}}!</w:t></w:r></w:p></w:body></w:document>');
    try {
        $result = $this->importer->import($path, allowedTokens: ['applicant_name', 'portal_url']);
        expect(DocxEmailImporter::authoredText($result->candidateBlueprint))->toBe('José {{applicant_name}} and {{portal_url}}!');
        foreach ($result->candidateBlueprint['blocks'][0]['runs'] as $run) {
            expect(substr_count($run['text'], '{{'))->toBe(substr_count($run['text'], '}}'));
        }
    } finally {
        unlink($path);
    }
});

test('token blockers cannot be treated as reviewable fidelity findings', function (string $text, array $required): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p></w:body></w:document>');
    try {
        expect(fn () => $this->importer->import($path, allowedTokens: ['applicant_name'], requiredTokens: $required))
            ->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    ['{{password}}', []],
    ['{{ref_code}}', []],
    ['{{applicant_name', []],
    ['{{}}', []],
    ['Welcome', ['applicant_name']],
]);

test('reports omitted header bytes at their actual source location with stable report identity', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Body</w:t></w:r></w:p></w:body></w:document>', [
        'word/header1.xml' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Bring government ID</w:t></w:r></w:p></w:hdr>',
    ]);
    try {
        $first = $this->importer->import($path);
        $second = $this->importer->import($path);
        expect($first->report)->toBe($second->report);
        $finding = $first->report['findings'][0];
        expect($finding['source_part'])->toBe('word/header1.xml')
            ->and($finding['source_location'])->toBe('/w:hdr')
            ->and($finding['source_text'])->toBe('Bring government ID')
            ->and($finding['disposition'])->toBe('requires_correction');
    } finally {
        unlink($path);
    }
});

test('does not invent rectangular table cells or flatten unresolved numbering silently', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc/><w:tc/></w:tr></w:tbl><w:p><w:pPr><w:numPr><w:numId w:val="99"/></w:numPr></w:pPr><w:r><w:t>List information</w:t></w:r></w:p></w:body></w:document>');
    try {
        $result = $this->importer->import($path);
        expect(array_column($result->report['findings'], 'kind'))->toContain('nonrectangular_table', 'unresolved_numbering')
            ->and(array_column($result->candidateBlueprint['blocks'], 'type'))->not->toContain('table')
            ->and(DocxEmailImporter::authoredText($result->candidateBlueprint))->toBe('List information');
    } finally {
        unlink($path);
    }
});

test('maps only privately associated inline images in text image text order', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><w:body><w:p><w:r><w:t>Before</w:t><w:drawing><wp:inline><wp:extent cx="952500" cy="952500"/><wp:docPr id="1" descr="Logo"/><a:blip r:embed="r1"/></wp:inline></w:drawing><w:t>After</w:t></w:r></w:p></w:body></w:document>';
    $path = createImporterTestDocx($xml, ['word/media/logo.png' => $png, 'word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/></Relationships>']);
    try {
        expect(fn () => $this->importer->import($path))->toThrow(DocxPackageInspectionException::class);
        $result = $this->importer->import($path, storeImage: function (array $image) use ($png): string {
            expect($image['bytes'])->toBe($png)->and($image['sha256'])->toBe(hash('sha256', $png));

            return '0520780b-eaf3-4c20-9b8d-5d518a90889a';
        });
        expect(array_column($result->candidateBlueprint['blocks'], 'type'))->toBe(['paragraph', 'image', 'paragraph'])
            ->and($result->candidateBlueprint['blocks'][1]['asset_id'])->toBe('0520780b-eaf3-4c20-9b8d-5d518a90889a')
            ->and($result->candidateBlueprint['blocks'][1]['alt'])->toBe('Logo');
    } finally {
        unlink($path);
    }
});

test('preserves supported numbering definitions and nested ordered and unordered lists', function (): void {
    $paragraph = fn (int $level, string $text): string => '<w:p><w:pPr><w:numPr><w:numId w:val="1"/><w:ilvl w:val="'.$level.'"/></w:numPr></w:pPr><w:r><w:t>'.$text.'</w:t></w:r></w:p>';
    $body = $paragraph(0, 'First').$paragraph(1, 'Nested bullet').$paragraph(0, 'Second');
    $numbering = '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/></w:lvl><w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>';
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>', ['word/numbering.xml' => $numbering]);
    try {
        $result = $this->importer->import($path);
        NoticeEmailSchema::assertValid($result->candidateBlueprint);
        $list = $result->candidateBlueprint['blocks'][0];
        expect($list['ordered'])->toBeTrue()->and($list['items'][0]['runs'][0]['text'])->toBe('First')
            ->and($list['items'][1]['type'])->toBe('list')->and($list['items'][1]['ordered'])->toBeFalse()
            ->and($list['items'][1]['items'][0]['runs'][0]['text'])->toBe('Nested bullet')
            ->and($list['items'][2]['runs'][0]['text'])->toBe('Second')
            ->and($result->report['findings'])->toBe([]);
    } finally {
        unlink($path);
    }
});

test('rejects unsafe tokens even when they occur only in omitted Word content', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Safe body</w:t></w:r></w:p></w:body></w:document>', [
        'word/footer1.xml' => '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>{{password}}</w:t></w:r></w:p></w:ftr>',
    ]);
    try {
        expect(fn () => $this->importer->import($path, allowedTokens: ['applicant_name']))
            ->toThrow(DocxPackageInspectionException::class, 'Unknown notice token');
    } finally {
        unlink($path);
    }
});

test('reports charts in the actual chart part without trying to turn non-raster drawing into an image', function (): void {
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><w:body><w:p><w:r><w:t>Chart below</w:t><w:drawing><wp:inline><a:graphic><a:graphicData uri="chart"/></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>';
    $path = createImporterTestDocx($xml, ['word/charts/chart1.xml' => '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart><c:title><c:v>Critical chart title</c:v></c:title></c:chart></c:chartSpace>']);
    try {
        $result = $this->importer->import($path);
        $finding = array_values(array_filter($result->report['findings'], fn (array $item): bool => $item['source_part'] === 'word/charts/chart1.xml'))[0];
        expect($finding['source_location'])->toBe('/c:chartSpace')->and($finding['source_text'])->toBe('Critical chart title')
            ->and($finding['severity'])->toBe('critical')->and(DocxEmailImporter::authoredText($result->candidateBlueprint))->toBe('Chart below');
    } finally {
        unlink($path);
    }
});

test('inline content controls never produce an empty success without corrective findings', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:sdt><w:sdtContent><w:r><w:t>Bring your government ID</w:t></w:r></w:sdtContent></w:sdt></w:p></w:body></w:document>');
    try {
        $result = $this->importer->import($path);
        $finding = array_values(array_filter($result->report['findings'], fn (array $item): bool => $item['kind'] === 'unsupported_sdt'))[0];
        expect($finding['source_location'])->toBe('/w:document/w:body/w:p/w:sdt')
            ->and($finding['source_text'])->toBe('Bring your government ID')
            ->and($finding['disposition'])->toBe('requires_correction');
    } finally {
        unlink($path);
    }
});

test('semantic text includes typed tokens and string list and table cells without blueprint metadata', function (): void {
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
        ['type' => 'paragraph', 'runs' => [['text' => 'Dear '], ['token' => 'applicant_name']]],
        ['type' => 'list', 'ordered' => false, 'items' => ['Bring ID']],
        ['type' => 'table', 'rows' => [['Room', 'One']]],
    ]];
    NoticeEmailSchema::assertValid($blueprint);
    expect(DocxEmailImporter::authoredText($blueprint))->toBe('Dear {{applicant_name}}Bring IDRoomOne');
});

test('rejects a changed original when passed a previously inspected package', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Original</w:t></w:r></w:p></w:body></w:document>');
    try {
        $inspection = (new DocxPackageInspector)->inspect($path);
        file_put_contents($path, 'Changed original');
        expect(fn () => $this->importer->import($path, $inspection))->toThrow(DocxPackageInspectionException::class, 'Source checksum changed');
    } finally {
        unlink($path);
    }
});

test('maps inherited heading and list styles from actual Word definitions', function (): void {
    $styles = '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="HeadBase"><w:name w:val="Heading 2"/></w:style><w:style w:type="paragraph" w:styleId="CustomHead"><w:basedOn w:val="HeadBase"/></w:style><w:style w:type="paragraph" w:styleId="Numbered"><w:pPr><w:numPr><w:numId w:val="2"/><w:ilvl w:val="0"/></w:numPr></w:pPr></w:style></w:styles>';
    $numbering = '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl></w:abstractNum><w:num w:numId="2"><w:abstractNumId w:val="0"/></w:num></w:numbering>';
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="CustomHead"/></w:pPr><w:r><w:t>Heading</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Numbered"/></w:pPr><w:r><w:t>First step</w:t></w:r></w:p></w:body></w:document>', ['word/styles.xml' => $styles, 'word/numbering.xml' => $numbering]);
    try {
        $result = $this->importer->import($path);
        expect($result->candidateBlueprint['blocks'][0]['type'])->toBe('heading')
            ->and($result->candidateBlueprint['blocks'][0]['level'])->toBe(2)
            ->and($result->candidateBlueprint['blocks'][1]['type'])->toBe('list')
            ->and($result->candidateBlueprint['blocks'][1]['ordered'])->toBeTrue();
    } finally {
        unlink($path);
    }
});

test('full email import is independently bounded and never truncated to the portal caption', function (): void {
    $text = str_repeat('Word import text. ', 100);
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p></w:body></w:document>');
    try {
        $result = $this->importer->import($path);
        expect(DocxEmailImporter::authoredText($result->candidateBlueprint))->toBe($text);
    } finally {
        unlink($path);
    }
});

test('safe complex field fallback text is mapped with acknowledgment rather than restoring instruction code', function (): void {
    $body = '<w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>PAGE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>';
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
    try {
        $result = $this->importer->import($path);
        expect(DocxEmailImporter::authoredText($result->candidateBlueprint))->toBe('1');
        foreach ($result->report['findings'] as $finding) {
            expect($finding['disposition'])->toBe('acknowledgment')->and($finding['source_text'])->toBe('');
        }
    } finally {
        unlink($path);
    }
});

test('reports internal Word hyperlink as inert text instead of silently claiming a mapped email link', function (): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:hyperlink w:anchor="section"><w:r><w:t>Jump to section</w:t></w:r></w:hyperlink></w:p></w:body></w:document>');
    try {
        $result = $this->importer->import($path);
        expect($result->candidateBlueprint['blocks'][0]['runs'][0])->toBe(['text' => 'Jump to section'])
            ->and($result->report['findings'][0]['kind'])->toBe('hyperlink_inert')
            ->and($result->report['findings'][0]['source_location'])->toBe('/w:document/w:body/w:p/w:hyperlink');
    } finally {
        unlink($path);
    }
});

test('maps approved local HTTP links without fetching and rejects foreign or production HTTP', function (string $environment, string $target, bool $allowed): void {
    app()->instance('env', $environment);
    config()->set('app.url', 'http://localhost:18000');
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:hyperlink r:id="r1"><w:r><w:t>Open portal</w:t></w:r></w:hyperlink></w:p></w:body></w:document>';
    $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.$target.'" TargetMode="External"/></Relationships>';
    $path = createImporterTestDocx($xml, ['word/_rels/document.xml.rels' => $rels]);
    try {
        if ($allowed) {
            $result = $this->importer->import($path);
            expect($result->candidateBlueprint['blocks'][0]['runs'][0]['link'])->toBe($target)
                ->and($result->report['findings'])->toBe([]);
        } else {
            expect(fn () => $this->importer->import($path))->toThrow(DocxPackageInspectionException::class);
        }
    } finally {
        unlink($path);
    }
})->with([
    ['local', 'http://localhost:18000/applicant/login', true],
    ['testing', 'http://localhost:18000/applicant/login', true],
    ['local', 'http://localhost:18001/applicant/login', false],
    ['testing', 'http://foreign.test/applicant/login', false],
    ['production', 'http://localhost:18000/applicant/login', false],
]);

test('ignoring XML indentation preserves authored whitespace and paragraph boundaries in token safety checks', function (string $body): void {
    $path = createImporterTestDocx('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
    try {
        expect(fn () => $this->importer->import($path, allowedTokens: ['applicant_name']))
            ->toThrow(DocxPackageInspectionException::class, 'Malformed notice token');
    } finally {
        unlink($path);
    }
})->with([
    '<w:p><w:r><w:t>{{applicant_</w:t></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:t>name}}</w:t></w:r></w:p>',
    '<w:p><w:r><w:t>{{applicant_</w:t></w:r></w:p><w:p><w:r><w:t>name}}</w:t></w:r></w:p>',
]);
