<?php

use App\DocumentTemplate\Support\DocxShapeAssetInjector;
use ZipArchive;

use function file_exists;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

test('it fills a drawing shape with the registered photo asset', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'shape-asset-');
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"><w:body><w:p><w:r><w:drawing><wp:inline><wp:extent cx="1828800" cy="1828800"/><a:graphic><a:graphicData><wps:wsp><wps:spPr></wps:spPr><wps:txbxContent><w:p><w:r><w:t>{{applicant_photo_2x2}}</w:t></w:r></w:p></wps:txbxContent></wps:wsp></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
    $zip->close();

    try {
        expect(DocxShapeAssetInjector::inject($path, ['applicant_photo_2x2' => null]))->toBeTrue();

        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $documentXml = $zip->getFromName('word/document.xml');
        $relationships = $zip->getFromName('word/_rels/document.xml.rels');
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        expect($zip->locateName('word/media/shape_asset_0.png'))->not->toBeFalse()
            ->and($documentXml)->toContain('<a:blipFill>')
            ->and($documentXml)->toContain('r:embed="rIdAsset0"')
            ->and($documentXml)->not->toContain('{{applicant_photo_2x2}}')
            ->and($documentXml)->toContain('cx="1828800" cy="1828800"')
            ->and($relationships)->toContain('Id="rIdAsset0"')
            ->and($relationships)->toContain('Target="media/shape_asset_0.png"')
            ->and($contentTypes)->toContain('Extension="png" ContentType="image/png"');
        $zip->close();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
test('it injects both a real candidate photo file and a real verification QR code into Word shapes', function (): void {
    $photoPath = tempnam(sys_get_temp_dir(), 'real-candidate-');
    $im = imagecreatetruecolor(400, 400);
    $bg = imagecolorallocate($im, 240, 240, 245);
    $skin = imagecolorallocate($im, 210, 160, 130);
    imagefilledrectangle($im, 0, 0, 399, 399, $bg);
    imagefilledellipse($im, 200, 180, 160, 200, $skin);
    imagejpeg($im, $photoPath, 95);
    imagedestroy($im);

    $docxPath = tempnam(sys_get_temp_dir(), 'real-docx-');
    $zip = new ZipArchive;
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"><w:body>'
        .'<w:p><w:r><w:drawing><wp:inline><wp:extent cx="1828800" cy="1828800"/><a:graphic><a:graphicData><wps:wsp><wps:spPr></wps:spPr><wps:txbxContent><w:p><w:r><w:t>{{applicant_photo_2x2}}</w:t></w:r></w:p></wps:txbxContent></wps:wsp></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
        .'<w:p><w:r><w:drawing><wp:inline><wp:extent cx="914400" cy="914400"/><a:graphic><a:graphicData><wps:wsp><wps:spPr></wps:spPr><wps:txbxContent><w:p><w:r><w:t>{{verification_qr}}</w:t></w:r></w:p></wps:txbxContent></wps:wsp></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
        .'</w:body></w:document>');
    $zip->close();

    try {
        $injected = DocxShapeAssetInjector::inject($docxPath, [
            'applicant_photo_2x2' => $photoPath,
            'verification_qr' => 'https://nexiam.test/verify/applicant-2026-00412',
        ]);
        expect($injected)->toBeTrue();

        $zip = new ZipArchive;
        expect($zip->open($docxPath))->toBeTrue();

        $photoBytes = $zip->getFromName('word/media/shape_asset_0.png');
        $qrBytes = $zip->getFromName('word/media/shape_asset_1.png');
        expect($photoBytes)->not->toBeFalse()
            ->and($qrBytes)->not->toBeFalse();

        $size0 = getimagesizefromstring($photoBytes);
        $size1 = getimagesizefromstring($qrBytes);
        $sizes = [$size0[0], $size1[0]];
        sort($sizes);

        // One is the 600x600 2x2 photo, one is the 620x620 QR code (600px + 20px margin)
        expect($sizes)->toBe([600, 620])
            ->and($qrBytes)->toStartWith("\x89PNG\r\n\x1a\n")
            ->and($photoBytes)->toStartWith("\x89PNG\r\n\x1a\n");

        $documentXml = $zip->getFromName('word/document.xml');
        expect($documentXml)->toContain('r:embed="rIdAsset0"')
            ->and($documentXml)->toContain('r:embed="rIdAsset1"')
            ->and($documentXml)->not->toContain('{{applicant_photo_2x2}}')
            ->and($documentXml)->not->toContain('{{verification_qr}}');

        $zip->close();
    } finally {
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
        if (file_exists($docxPath)) {
            unlink($docxPath);
        }
    }
});
