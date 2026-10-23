<?php

use App\DocumentTemplate\Actions\ExportDocumentTemplateBundle;
use App\DocumentTemplate\Actions\ImportDocumentTemplateBundle;
use App\DocumentTemplate\Packaging\DirectoryBundleReader;
use App\DocumentTemplate\Packaging\DocumentBundlePackage;
use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

test('result sheet bundle exports and imports as an inactive draft', function (): void {
    $template = ResultSheetTemplate::query()->create([
        'name' => 'Bundle lifecycle test',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'legal',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'source_kind' => ResultSheetTemplate::SOURCE_LEGACY_HTML,
        'content' => '<main>{{applicant_reference}}</main>',
        'is_active' => false,
    ]);

    $export = app(ExportDocumentTemplateBundle::class)->handle('result_sheet', $template->id);
    try {
        $bundle = app(DocumentBundlePackage::class)->extractAndValidate($export['zip_path']);

        expect($bundle['manifest']['surface_key'])->toBe('result_sheet')
            ->and($bundle['files']['content.html'])->toBe('<main>{{applicant_reference}}</main>');

        $imported = app(ImportDocumentTemplateBundle::class)->handle($export['zip_path']);
        $importedTemplate = ResultSheetTemplate::query()->findOrFail($imported['template_id']);

        expect($imported['is_active'])->toBeFalse()
            ->and($importedTemplate->is_active)->toBeFalse()
            ->and($importedTemplate->name)->toBe('Bundle lifecycle test (Imported)')
            ->and($importedTemplate->content)->toBe('<main>{{applicant_reference}}</main>');
    } finally {
        @unlink($export['zip_path']);
    }
});

test('document bundle package rejects zip-slip members', function (): void {
    $zipPath = tempnam(sys_get_temp_dir(), 'document-bundle-slip-');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode([
        'schema_version' => DocumentBundlePackage::SCHEMA_VERSION,
        'surface_key' => 'result_sheet',
        'template' => ['name' => 'Traversal attempt', 'mode' => 'html'],
        'files' => ['../../etc/passwd' => ['role' => 'source', 'size' => 4, 'sha256' => hash('sha256', 'root')]],
    ], JSON_THROW_ON_ERROR));
    $zip->addFromString('../../etc/passwd', 'root');
    $zip->close();

    try {
        expect(fn () => app(DocumentBundlePackage::class)->extractAndValidate($zipPath))
            ->toThrow(RuntimeException::class);
    } finally {
        @unlink($zipPath);
    }
});

test('document bundle package rejects XXE in nested office XML', function (): void {
    $nestedPath = tempnam(sys_get_temp_dir(), 'document-bundle-nested-');
    $zipPath = tempnam(sys_get_temp_dir(), 'document-bundle-xxe-');
    $nested = new ZipArchive;
    $nested->open($nestedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $nested->addFromString(
        'word/document.xml',
        '<!DOCTYPE document [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><document>&xxe;</document>',
    );
    $nested->close();
    $docx = file_get_contents($nestedPath);
    $manifest = [
        'schema_version' => DocumentBundlePackage::SCHEMA_VERSION,
        'surface_key' => 'result_sheet',
        'template' => ['name' => 'Entity attempt', 'mode' => 'docx'],
        'files' => [
            'template.docx' => [
                'role' => 'source_docx',
                'size' => strlen($docx),
                'sha256' => hash('sha256', $docx),
            ],
        ],
    ];
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->addFromString('template.docx', $docx);
    $zip->close();

    try {
        expect(fn () => app(DocumentBundlePackage::class)->extractAndValidate($zipPath))
            ->toThrow(RuntimeException::class);
    } finally {
        @unlink($nestedPath);
        @unlink($zipPath);
    }
});

test('directory bundle reader loads the result sheet seed catalog with verified bytes', function (): void {
    $directory = database_path('seed-assets/templates/result_sheet/template_main_icat_test1');
    $bundle = app(DirectoryBundleReader::class)->read($directory);
    $asset = $bundle['files']['template.docx'];
    $manifestAsset = $bundle['manifest']['files']['template.docx'];

    expect($bundle['manifest']['surface_key'])->toBe('result_sheet')
        ->and(strlen($asset))->toBe($manifestAsset['size'])
        ->and(hash('sha256', $asset))->toBe($manifestAsset['sha256']);
});
