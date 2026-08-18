<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Rendering\DocxToPdfConverter;
use App\ResultSheet\Rendering\ResultSheetRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;

/**
 * Real LibreOffice smoke: fills the actual SecureCAT institutional template
 * with sample data and converts it to PDF. Skipped when the binary is absent
 * (CI/plain composer images); the image build installs libreoffice-writer.
 */
test('the real ICAT docx template fills and converts to pdf', function (): void {
    $converter = app(DocxToPdfConverter::class);

    if (! $converter->isAvailable()) {
        $this->markTestSkipped('LibreOffice binary not available in this image.');
    }

    seedE2Foundation();

    $asset = database_path('seed-assets/result-sheets/template_main_icat_test1.docx');
    Storage::disk('local')->put('result-sheet-templates/icat.docx', (string) file_get_contents($asset));

    $renderer = app(ResultSheetRenderer::class);
    $data = app(ResultSheetPlaceholderService::class)->sampleData();
    $data['sex'] = 'male';
    $data['gwa'] = '1.75';
    $data['strand'] = 'STEM';
    $data['course_applied_code'] = 'BSIT';
    $data['recommended_course_code'] = 'BSIT';

    $template = new ResultSheetTemplate([
        'name' => 'ICAT Results',
        'mode' => 'docx',
        'paper_size' => 'legal',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => 'result-sheet-templates/icat.docx',
        'document_extension' => 'docx',
    ]);

    $bytes = $renderer->renderForApplicants($template, [$data]);

    expect($bytes)->toStartWith('%PDF')
        ->and(strlen($bytes))->toBeGreaterThan(10000);

    // The filled name must appear in the rendered PDF text layer.
    $temp = tempnam(sys_get_temp_dir(), 'icat_').'.pdf';
    file_put_contents($temp, $bytes);

    $text = shell_exec('pdftotext '.escapeshellarg($temp).' - 2>/dev/null') ?? '';
    @unlink($temp);

    expect($text)->toContain('Juan');
});
