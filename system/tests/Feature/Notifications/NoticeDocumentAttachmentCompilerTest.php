<?php

use App\DocumentTemplate\Import\DocxPackageInspector;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Support\DocxMacroRepairer;
use App\DocumentTemplate\Support\MacroScanner;
use App\Models\Admission\ApplicantIdentity;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use ZipArchive;

use function sys_get_temp_dir;
use function tempnam;

test('notification mailable attaches a PDF from its constructor or payload', function (): void {
    $constructorPath = tempnam(sys_get_temp_dir(), 'notice-pdf-');
    $payloadPath = tempnam(sys_get_temp_dir(), 'notice-pdf-');
    file_put_contents($constructorPath, '%PDF-1.4 constructor');
    file_put_contents($payloadPath, '%PDF-1.4 payload');

    try {
        $constructorMail = new NotificationMailable(
            templateKey: 'admission.accepted',
            templateVersion: 1,
            payload: [],
            attachmentPath: $constructorPath,
            attachmentName: 'admission-notice.pdf',
        );
        $payloadMail = new NotificationMailable(
            templateKey: 'admission.accepted',
            templateVersion: 1,
            payload: [
                'attachment_path' => $payloadPath,
                'attachment_name' => 'certificate.pdf',
            ],
        );

        expect($constructorMail->attachments())->toHaveCount(1)
            ->and($constructorMail->attachments()[0]->as)->toBe('admission-notice.pdf')
            ->and($payloadMail->attachments())->toHaveCount(1)
            ->and($payloadMail->attachments()[0]->as)->toBe('certificate.pdf');
    } finally {
        @unlink($constructorPath);
        @unlink($payloadPath);
    }
});

test('notice compiler fills drawing shape photo and verification QR placeholders and compiles PDF', function (): void {
    $sourceTemplate = base_path('database/seed-assets/result-sheets/generic_results.docx');
    $templatePath = tempnam(sys_get_temp_dir(), 'notice-template-').'.docx';
    copy($sourceTemplate, $templatePath);

    // Add shape elements to the document
    $zip = new ZipArchive;
    expect($zip->open($templatePath))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    if (preg_match('/<w:document\b[^>]*\bxmlns:wps=/', $xml) !== 1) {
        $xml = preg_replace(
            '/<w:document\b/',
            '<w:document xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"',
            $xml,
            1,
        ) ?? $xml;
    }
    if (preg_match('/<w:document\b[^>]*\bxmlns:a=/', $xml) !== 1) {
        $xml = preg_replace(
            '/<w:document\b/',
            '<w:document xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"',
            $xml,
            1,
        ) ?? $xml;
    }
    $shapeXml = '<w:p><w:r><w:drawing><wp:inline><wp:extent cx="1828800" cy="1828800"/><a:graphic><a:graphicData><wps:wsp><wps:spPr></wps:spPr><wps:txbxContent><w:p><w:r><w:t>{{applicant_photo_2x2}}</w:t></w:r></w:p></wps:txbxContent></wps:wsp></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
        .'<w:p><w:r><w:drawing><wp:inline><wp:extent cx="914400" cy="914400"/><a:graphic><a:graphicData><wps:wsp><wps:spPr></wps:spPr><wps:txbxContent><w:p><w:r><w:t>{{verification_qr}}</w:t></w:r></w:p></wps:txbxContent></wps:wsp></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    $xml = str_replace('</w:body>', $shapeXml.'</w:body>', $xml);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    $converter = Mockery::mock(LibreOfficeConverter::class);
    $converter->shouldReceive('isAvailable')->once()->andReturnTrue();
    $converter->shouldReceive('convertToPdf')->once()->withArgs(function (string $path): bool {
        $inspection = app(DocxPackageInspector::class)->inspect($path);
        expect(array_filter($inspection->allXmlParts, static fn (string $xml): bool => trim($xml) === ''))->toBe([]);

        return is_file($path);
    })->andReturn('%PDF-1.4 sample notice document');

    $compiler = new NoticeDocumentAttachmentCompiler(
        new DocxTemplateFiller(new DocxMacroRepairer),
        $converter,
    );
    $identity = new ApplicantIdentity;
    $identity->id = 123;
    $replacements = array_replace(
        array_fill_keys(MacroScanner::scan($xml)['found'], 'Synthetic value'),
        $compiler->prepareApplicantAssetReplacements($identity),
    );

    $pdfPath = $compiler->compileNoticePdf($templatePath, $replacements, 'official-notice.pdf');

    try {
        expect(is_file($pdfPath))->toBeTrue()
            ->and(basename($pdfPath))->toBe('official-notice.pdf')
            ->and(file_get_contents($pdfPath))->toStartWith('%PDF-');
    } finally {
        @unlink($templatePath);
        @unlink($pdfPath);
    }
});

test('notice compiler fails closed when LibreOffice is unavailable', function (): void {
    $templatePath = tempnam(sys_get_temp_dir(), 'notice-template-fail-');
    $zip = new ZipArchive;
    expect($zip->open($templatePath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Hello</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    $converter = Mockery::mock(LibreOfficeConverter::class);
    $converter->shouldReceive('isAvailable')->once()->andReturnFalse();

    $compiler = new NoticeDocumentAttachmentCompiler(
        new DocxTemplateFiller(new DocxMacroRepairer),
        $converter,
    );

    try {
        expect(fn () => $compiler->compileNoticePdf($templatePath, []))
            ->toThrow(RuntimeException::class, 'libreoffice_unavailable');
    } finally {
        @unlink($templatePath);
    }
});
