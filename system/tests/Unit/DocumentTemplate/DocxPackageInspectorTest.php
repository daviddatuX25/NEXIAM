<?php

declare(strict_types=1);

use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\DocumentTemplate\Import\DocxPackageInspector;

function createInspectorTestDocx(array $files = []): string
{
    $tempPath = tempnam(sys_get_temp_dir(), 'test_insp_');
    $zip = new ZipArchive;
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $defaultFiles = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>',
        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Hello test</w:t></w:r></w:p></w:body></w:document>',
        'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>',
    ];

    $merged = array_merge($defaultFiles, $files);
    foreach ($merged as $name => $content) {
        if ($content === null) {
            continue;
        }
        $zip->addFromString($name, (string) $content);
    }
    $zip->close();

    return $tempPath;
}

function createInspectorTestPng(int $w = 10, int $h = 10): string
{
    $img = imagecreatetruecolor($w, $h);
    ob_start();
    imagepng($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

function createInspectorTestJpeg(int $w = 10, int $h = 10): string
{
    $img = imagecreatetruecolor($w, $h);
    ob_start();
    imagejpeg($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

beforeEach(function (): void {
    $this->inspector = new DocxPackageInspector;
});

test('inspects a valid docx package successfully', function (): void {
    $pngBytes = createInspectorTestPng(50, 50);
    $relsXml = '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/info" TargetMode="External"/>
        <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>
    </Relationships>';

    $path = createInspectorTestDocx([
        'word/_rels/document.xml.rels' => $relsXml,
        'word/media/image1.png' => $pngBytes,
    ]);

    try {
        $result = $this->inspector->inspect($path);

        expect($result->memberCount)->toBe(5)
            ->and($result->fileSizeBytes)->toBeGreaterThan(0)
            ->and(strlen($result->sha256))->toBe(64)
            ->and($result->documentXml)->toContain('Hello test')
            ->and($result->documentRels)->toHaveKey('rId1')
            ->and($result->documentRels['rId1'])->toBe('https://example.com/info')
            ->and($result->images)->toHaveKey('word/media/image1.png');

        $imageInfo = $result->images['word/media/image1.png'];
        expect($imageInfo['mime'])->toBe('image/png')
            ->and($imageInfo['width'])->toBe(50)
            ->and($imageInfo['height'])->toBe(50)
            ->and($imageInfo['sha256'])->toBe(hash('sha256', $pngBytes));
    } finally {
        @unlink($path);
    }
});

test('rejects package exceeding member count limit', function (): void {
    $files = [];
    for ($i = 0; $i < 205; $i++) {
        $files["word/extra_{$i}.xml"] = '<extra/>';
    }

    $path = createInspectorTestDocx($files);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'exceeding the maximum allowed limit of 200');
    } finally {
        @unlink($path);
    }
});

test('rejects zip path traversal in member paths', function (string $dangerousName): void {
    $path = createInspectorTestDocx([
        $dangerousName => '<dummy/>',
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class);
    } finally {
        @unlink($path);
    }
})->with([
    '../traversal.xml',
    'word/../../etc/passwd',
    '/absolute.xml',
    'word\\backslashes.xml',
    'word//non_normalized.xml',
    './relative.xml',
]);

test('rejects duplicate archive member names', function (): void {
    $tempPath = tempnam(sys_get_temp_dir(), 'test_dup_');
    $zip = new ZipArchive;
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('_rels/.rels', '<Relationships/>');
    $zip->addFromString('word/document.xml', '<w:document/>');
    $zip->addFromString('word/Document.xml', '<w:document/>'); // duplicate case-insensitive
    $zip->close();

    try {
        expect(fn () => $this->inspector->inspect($tempPath))
            ->toThrow(DocxPackageInspectionException::class, 'Duplicate archive member detected');
    } finally {
        @unlink($tempPath);
    }
});

test('rejects package missing required OOXML core parts', function (string $missingPart): void {
    $files = [
        $missingPart => null,
    ];

    $path = createInspectorTestDocx($files);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, "Required OOXML core part is missing: {$missingPart}");
    } finally {
        @unlink($path);
    }
})->with([
    '[Content_Types].xml',
    '_rels/.rels',
    'word/document.xml',
]);

test('rejects macro files and extensions', function (string $macroMember): void {
    $path = createInspectorTestDocx([
        $macroMember => 'BINARY_VBA_PAYLOAD',
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'macro');
    } finally {
        @unlink($path);
    }
})->with([
    'word/vbaProject.bin',
    'word/vbaData.xml',
    'word/macros.docm',
]);

test('rejects executable binaries and OLE embeddings', function (string $forbiddenMember): void {
    $path = createInspectorTestDocx([
        $forbiddenMember => 'MALICIOUS_BYTES',
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class);
    } finally {
        @unlink($path);
    }
})->with([
    'word/payload.exe',
    'word/library.dll',
    'word/embeddings/oleObject1.bin',
    'word/oleObject.bin',
]);

test('rejects OLE compound document magic bytes in XML member', function (): void {
    $oleHeader = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".'fake ole content';
    $path = createInspectorTestDocx([
        'word/sneaky.xml' => $oleHeader,
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'OLE compound document detected');
    } finally {
        @unlink($path);
    }
});

test('rejects unsafe XML containing DOCTYPE or ENTITY declarations (XXE)', function (string $unsafeXml): void {
    $path = createInspectorTestDocx([
        'word/document.xml' => $unsafeXml,
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'DOCTYPE and ENTITY declarations are not allowed');
    } finally {
        @unlink($path);
    }
})->with([
    '<?xml version="1.0"?><!DOCTYPE test SYSTEM "http://attacker.com/evil.dtd"><w:document/>',
    '<?xml version="1.0"?><!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><w:document/>',
]);

test('rejects unsafe external relationships with forbidden schemes', function (string $unsafeTarget): void {
    $relsXml = '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.$unsafeTarget.'" TargetMode="External"/>
    </Relationships>';

    $path = createInspectorTestDocx([
        'word/_rels/document.xml.rels' => $relsXml,
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class);
    } finally {
        @unlink($path);
    }
})->with([
    'file:///etc/passwd',
    'php://filter/resource=index.php',
    'gopher://evil.com',
    'javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
]);

test('rejects non-hyperlink external relationships', function (): void {
    $relsXml = '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://remote.com/pic.png" TargetMode="External"/>
    </Relationships>';

    $path = createInspectorTestDocx([
        'word/_rels/document.xml.rels' => $relsXml,
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'Only external hyperlinks are allowed');
    } finally {
        @unlink($path);
    }
});

test('rejects image with invalid magic bytes in word/media', function (): void {
    $path = createInspectorTestDocx([
        'word/media/image1.png' => 'NOT_A_REAL_PNG',
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'invalid magic bytes or unsupported format');
    } finally {
        @unlink($path);
    }
});

test('rejects SVG in word/media', function (): void {
    $path = createInspectorTestDocx([
        'word/media/vector.svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    ]);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'Active SVG or vector media is not permitted');
    } finally {
        @unlink($path);
    }
});

test('rejects more than 20 images in word/media', function (): void {
    $pngBytes = createInspectorTestPng(1, 1);
    $images = [];
    for ($i = 1; $i <= 21; $i++) {
        $images["word/media/image{$i}.png"] = $pngBytes;
    }

    $path = createInspectorTestDocx($images);

    try {
        expect(fn () => $this->inspector->inspect($path))
            ->toThrow(DocxPackageInspectionException::class, 'more than 20 images in word/media/');
    } finally {
        @unlink($path);
    }
});

test('rejects encrypted members from metadata without asking for a password', function (): void {
    $path = createInspectorTestDocx();
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->setEncryptionName('word/document.xml', ZipArchive::EM_AES_256, 'secret'))->toBeTrue();
    $zip->close();
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class, 'Encrypted');
    } finally {
        unlink($path);
    }
});

test('rejects expanded metadata bound before attempting XML inflation', function (): void {
    $path = createInspectorTestDocx();
    $bytes = file_get_contents($path);
    $offset = strpos($bytes, "PK\x01\x02");
    while ($offset !== false) {
        $nameLength = unpack('v', substr($bytes, $offset + 28, 2))[1];
        if (substr($bytes, $offset + 46, $nameLength) === 'word/document.xml') {
            $bytes = substr_replace($bytes, pack('V', DocxPackageInspector::MAX_UNCOMPRESSED_BYTES + 1), $offset + 24, 4);
            break;
        }
        $offset = strpos($bytes, "PK\x01\x02", $offset + 4);
    }
    file_put_contents($path, $bytes);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
});

test('reports empty optional and required source XML as safe package diagnostics', function (): void {
    foreach ([
        ['word/settings.xml' => ''],
        ['word/document.xml' => ''],
    ] as $files) {
        $path = createInspectorTestDocx($files);
        try {
            expect(fn () => $this->inspector->inspect($path))
                ->toThrow(DocxPackageInspectionException::class, 'is empty');
        } finally {
            unlink($path);
        }
    }
});

test('rejects malformed XML and excessive XML depth or nodes', function (string $xml): void {
    $path = createInspectorTestDocx(['word/document.xml' => $xml]);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    '<root><broken></root>',
    str_repeat('<node>', DocxPackageInspector::MAX_XML_DEPTH + 2).str_repeat('</node>', DocxPackageInspector::MAX_XML_DEPTH + 2),
    '<root>'.str_repeat('<node/>', DocxPackageInspector::MAX_XML_NODES + 1).'</root>',
    '<?xml version="1.0" encoding="UTF-16"?>'."\0".'<!DOCTYPE root><root/>',
]);

test('rejects relationship identity spoofing and disguised resource targets', function (string $attributes): void {
    $path = createInspectorTestDocx(['word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '.$attributes.'/></Relationships>']);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    'Target="https://remote.test/image.png"',
    'Target="//remote.test/image.png"',
    'Target="../../../outside.png"',
    'Target="media/%2e%2e/payload.png"',
    'Target="media/missing.png"',
    'Target="https://remote.test/image.png" TargetMode="external"',
]);

test('rejects duplicate relationship IDs even when both links are individually safe', function (): void {
    $path = createInspectorTestDocx(['word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://a.test" TargetMode="External"/><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://b.test" TargetMode="External"/></Relationships>']);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class, 'duplicate relationship');
    } finally {
        unlink($path);
    }
});

test('rejects hidden macro content types and embedded XML objects', function (string $xml): void {
    $path = createInspectorTestDocx(['word/extra.xml' => $xml]);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    '<Types><Override ContentType="application/vnd.ms-word.document.macroEnabled.main+xml"/></Types>',
    '<root><object/></root>',
    '<root><altChunk/></root>',
]);

test('rejects excessive image bytes and dimensions before producing image metadata', function (string $bytes): void {
    $path = createInspectorTestDocx(['word/media/oversized.png' => $bytes]);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=').str_repeat('x', DocxPackageInspector::MAX_IMAGE_BYTES),
    substr_replace(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='), pack('N2', 20001, 1000), 16, 8),
]);

test('rejects direct linked resources and external-fetch Word fields without relationships', function (string $xml): void {
    $path = createInspectorTestDocx(['word/unsafe.xml' => $xml]);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    '<root><imagedata src="https://remote.test/image.png"/></root>',
    '<root><field href="file:///etc/passwd"/></root>',
    '<w:fldSimple xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" w:instr="INCLUDEPICTURE &quot;https://remote.test/image&quot;"/>',
    '<w:instrText xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">DDEAUTO application command</w:instrText>',
]);

test('rejects compressed files over ten MiB before opening the archive', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'notice-too-large-');
    file_put_contents($path, str_repeat('x', DocxPackageInspector::MAX_FILE_BYTES + 1));
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class, 'maximum file size limit');
    } finally {
        unlink($path);
    }
});

test('enforces processing deadline instead of partially returning inspected state', function (): void {
    $property = new ReflectionProperty(DocxPackageInspector::class, 'deadline');
    $property->setValue($this->inspector, hrtime(true) - 1);
    expect(fn () => $this->inspector->parseXml('word/document.xml', '<root><text/></root>'))
        ->toThrow(DocxPackageInspectionException::class, 'processing time limit');
});

test('rejects genuine split and nested Word external-fetch field instructions before conversion', function (string $body): void {
    $path = createInspectorTestDocx(['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>'.$body.'</w:p></w:body></w:document>']);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>INCLUDE</w:instrText></w:r><w:r><w:instrText>PICTURE "https://remote.test/image"</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>Image result</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>',
    '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>IF </w:instrText></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>DDE</w:instrText></w:r><w:r><w:instrText>AUTO application command</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r><w:r><w:instrText> = "yes" "yes" "no"</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>',
    '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>INCLUDE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>DATE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r><w:r><w:instrText>PICTURE "https://remote.test/image"</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>',
    '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>DATA</w:instrText></w:r><w:r><w:instrText>BASE remote-source</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>',
]);

test('accepts provably benign split Word fields while enforcing complete field boundaries', function (): void {
    $path = createInspectorTestDocx(['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>PA</w:instrText></w:r><w:r><w:instrText>GE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p></w:body></w:document>']);
    try {
        expect($this->inspector->inspect($path)->documentXml)->toContain('fldCharType="end"');
    } finally {
        unlink($path);
    }
});

test('rejects malformed or excessively nested Word field controls', function (string $body): void {
    $path = createInspectorTestDocx(['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>'.$body.'</w:p></w:body></w:document>']);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with([
    '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>PAGE</w:instrText></w:r>',
    '<w:r><w:fldChar w:fldCharType="end"/></w:r>',
    str_repeat('<w:r><w:fldChar w:fldCharType="begin"/></w:r>', 17).str_repeat('<w:r><w:fldChar w:fldCharType="end"/></w:r>', 17),
]);

test('applies the shared exact local-origin HTTP exception only to inert hyperlink relationships', function (string $environment, string $target, bool $allowed): void {
    app()->instance('env', $environment);
    config()->set('app.url', 'http://localhost:18000');
    $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="link1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.$target.'" TargetMode="External"/></Relationships>';
    $path = createInspectorTestDocx(['word/_rels/document.xml.rels' => $rels]);
    try {
        if ($allowed) {
            expect($this->inspector->inspect($path)->documentRels['link1'])->toBe($target);
        } else {
            expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
        }
    } finally {
        unlink($path);
    }
})->with([
    ['local', 'http://localhost:18000/applicant/login', true],
    ['testing', 'http://localhost:18000/applicant/login', true],
    ['local', 'http://localhost/applicant/login', false],
    ['local', 'http://127.0.0.1:18000/applicant/login', false],
    ['testing', 'http://foreign.test/applicant/login', false],
    ['production', 'http://localhost:18000/applicant/login', false],
    ['production', 'https://foreign.test/applicant/login', true],
]);

test('applies the same origin policy to literal hyperlink fields without enabling external-fetch fields', function (string $environment, string $command, bool $allowed): void {
    app()->instance('env', $environment);
    config()->set('app.url', 'http://localhost:18000');
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:fldSimple w:instr="'.htmlspecialchars($command, ENT_QUOTES | ENT_XML1).'"><w:r><w:t>Visible link</w:t></w:r></w:fldSimple></w:p></w:body></w:document>';
    $path = createInspectorTestDocx(['word/document.xml' => $xml]);
    try {
        if ($allowed) {
            expect($this->inspector->inspect($path)->documentXml)->toBe($xml);
        } else {
            expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
        }
    } finally {
        unlink($path);
    }
})->with([
    ['local', 'HYPERLINK "http://localhost:18000/link"', true],
    ['testing', 'HYPERLINK "http://localhost:18000/applicant/login"', true],
    ['local', 'HYPERLINK "http://foreign.test/link"', false],
    ['production', 'HYPERLINK "http://localhost:18000/link"', false],
    ['local', 'INCLUDEPICTURE "http://localhost:18000/image.png"', false],
    ['testing', 'DDEAUTO "http://localhost:18000" command', false],
]);

test('configured local HTTP origin never enables external resource relationships', function (string $type): void {
    app()->instance('env', 'local');
    config()->set('app.url', 'http://localhost:18000');
    $path = createInspectorTestDocx(['word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$type.'" Target="http://localhost:18000/resource" TargetMode="External"/></Relationships>']);
    try {
        expect(fn () => $this->inspector->inspect($path))->toThrow(DocxPackageInspectionException::class);
    } finally {
        unlink($path);
    }
})->with(['image', 'attachedTemplate', 'oleObject']);
