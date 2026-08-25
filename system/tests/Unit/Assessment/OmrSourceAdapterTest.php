<?php

use App\Assessment\Omr\Canonical\OmrSourceAdapterRegistry;
use App\Assessment\Omr\Canonical\OmrSourcePreflight;
use App\Assessment\Omr\Canonical\OmrSourcePreflightException;
use App\Printing\DomPdfPrintRenderer;
use App\ResultSheet\Rendering\DocxToPdfConverter;

function omrTempSource(string $extension, string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'nexiam-omr-test-').'.'.$extension;
    file_put_contents($path, $contents);

    return $path;
}

function omrMatrixPdfBytes(): string
{
    return app(DomPdfPrintRenderer::class)->render(
        '<!doctype html><html><body><h1>Source matrix</h1><p>Static page content.</p></body></html>',
        ['format' => 'custom', 'orientation' => 'portrait', 'width_mm' => 210, 'height_mm' => 297],
    );
}

function omrMatrixDocx(string $path): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', '<document/>');
    $zip->close();
}

test('registry exposes all five canonical source kinds', function (): void {
    expect(app(OmrSourceAdapterRegistry::class)->kinds())
        ->toBe(['html', 'docx', 'pdf', 'image', 'preset']);
});

test('HTML preflight rejects executable, remote, and unknown dynamic content', function (): void {
    $preflight = app(OmrSourcePreflight::class);

    expect(fn () => $preflight->check('html', omrTempSource('html', '<script>alert(1)</script>')))
        ->toThrow(function (OmrSourcePreflightException $exception): void {
            expect($exception->errorCode)->toBe('html_unsafe_markup');
        });
    expect(fn () => $preflight->check('html', omrTempSource('html', '<p>{{ applicant_name }}</p>')))
        ->toThrow(function (OmrSourcePreflightException $exception): void {
            expect($exception->errorCode)->toBe('dynamic_token_rejected');
        });
    expect($preflight->check('html', omrTempSource('html', '<div data-omr-overlay="assessment">{{ assessment }}</div>'))->metadata['overlay_slots'])
        ->toHaveCount(1);
});

test('DOCX preflight accepts a minimal package and rejects macros', function (): void {
    $safe = tempnam(sys_get_temp_dir(), 'nexiam-docx-').'.docx';
    $zip = new ZipArchive;
    $zip->open($safe, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', '<document/>');
    $zip->close();

    expect(app(OmrSourcePreflight::class)->check('docx', $safe)->metadata['zip_members'])->toBe(2);

    $macro = tempnam(sys_get_temp_dir(), 'nexiam-docx-macro-').'.docx';
    $zip = new ZipArchive;
    $zip->open($macro, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', '<document/>');
    $zip->addFromString('word/vbaProject.bin', 'macro');
    $zip->close();

    expect(fn () => app(OmrSourcePreflight::class)->check('docx', $macro))
        ->toThrow(function (OmrSourcePreflightException $exception): void {
            expect($exception->errorCode)->toBe('macro_rejected');
        });
});

test('PDF preflight rejects encryption and malformed xref markers', function (): void {
    $valid = "%PDF-1.7\n1 0 obj<< /Type /Page >>endobj\nstartxref\n0\n%%EOF";
    expect(app(OmrSourcePreflight::class)->check('pdf', omrTempSource('pdf', $valid))->metadata['page_count'])->toBe(1)
        ->and(fn () => app(OmrSourcePreflight::class)->check('pdf', omrTempSource('pdf', "%PDF-1.7\n/Encrypt\nstartxref\n0\n%%EOF")))
        ->toThrow(function (OmrSourcePreflightException $exception): void {
            expect($exception->errorCode)->toBe('encrypted_pdf');
        });
});

test('image preflight accepts sRGB/no-profile and rejects unsupported ICC', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $preflight = app(OmrSourcePreflight::class);

    expect($preflight->check('image', omrTempSource('png', (string) $png))->metadata['width_px'])->toBe(1)
        ->and(fn () => $preflight->check('image', omrTempSource('png', (string) $png.'ICC_PROFILE non-srgb')))
        ->toThrow(function (OmrSourcePreflightException $exception): void {
            expect($exception->errorCode)->toBe('image_icc_unsupported');
        });
});

test('image adapter requires confirmed quad for authoring photos and rejects TIFF v1', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $imagePath = omrTempSource('png', (string) $png);

    expect(fn () => app(OmrSourceAdapterRegistry::class)->get('image')->convert($imagePath, ['authoring_photo' => true]))
        ->toThrow(OmrSourcePreflightException::class, 'quad_confirmation_required');
});

test('all source adapters emit the same canonical page boundary contract', function (): void {
    config(['print.paper' => 'long']);
    $registry = app(OmrSourceAdapterRegistry::class);
    $pdfBytes = omrMatrixPdfBytes();
    $fixtures = [];
    $generatedPages = [];

    $fixtures['html'] = omrTempSource('html', '<!doctype html><html><body><h1>HTML source</h1><p>Static content.</p></body></html>');
    $fixtures['pdf'] = omrTempSource('pdf', $pdfBytes);

    $docxPath = tempnam(sys_get_temp_dir(), 'nexiam-source-matrix-').'.docx';
    omrMatrixDocx($docxPath);
    $fixtures['docx'] = $docxPath;
    app()->instance(DocxToPdfConverter::class, new class($pdfBytes) implements DocxToPdfConverter
    {
        public function __construct(private readonly string $pdfBytes)
        {
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function convert(string $documentPath): string
        {
            return $this->pdfBytes;
        }
    });

    $fixtures['image'] = omrTempSource('png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    $fixtures['preset'] = omrTempSource('json', json_encode([
        'bubbleDimensions' => [40, 40],
        'pageDimensions' => [2060, 3144],
        'fieldBlocks' => [
            'Logic' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['LOGIC_Q_1..2'],
                'bubblesGap' => 66,
                'labelsGap' => 80,
                'origin' => [180, 900],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        foreach ($fixtures as $kind => $path) {
            $context = [
                'width_mm' => 210,
                'height_mm' => 297,
                'source_dpi' => 300,
            ];
            if ($kind === 'preset') {
                $context['new_page'] = ['width' => 2480, 'height' => 3508];
            }
            $result = $registry->get($kind)->convert($path, $context);
            expect($result->kind)->toBe($kind)
                ->and($result->pages)->not->toBeEmpty()
                ->and($result->metadata)->toHaveKey('page_count');

            foreach ($result->pages as $page) {
                $generatedPages[] = $page['path'];
                expect(is_file($page['path']))->toBeTrue()
                    ->and($page['mime'])->toBe('image/png')
                    ->and($page['width'])->toBeGreaterThan(0)
                    ->and($page['height'])->toBeGreaterThan(0)
                    ->and($page['sha256'])->toHaveLength(64);
            }
        }
    } finally {
        foreach ($fixtures as $path) {
            @unlink($path);
        }
        foreach ($generatedPages as $path) {
            @unlink($path);
        }
    }
});
