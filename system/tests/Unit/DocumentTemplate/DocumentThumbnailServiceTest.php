<?php

use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Support\Media\PdfThumbnailRasterizer;
use FPDF;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Storage::fake('local');
});

function documentThumbnailService(): DocumentThumbnailService
{
    return new DocumentThumbnailService(new PdfThumbnailRasterizer);
}

function documentThumbnailPdfFixture(): string
{
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Private document thumbnail');

    return (string) $pdf->Output('S');
}

test('it fingerprints source and renderer identity deterministically', function (): void {
    $service = documentThumbnailService();
    $source = hash('sha256', 'source-one');

    expect($service->fingerprint($source))->toBe($service->fingerprint($source))
        ->and($service->fingerprint(hash('sha256', 'source-two')))->not->toBe($service->fingerprint($source));
});

test('it stores and reads first-page PNG bytes on the private local disk', function (): void {
    $service = documentThumbnailService();
    $sourceChecksum = hash('sha256', 'report-version-one');
    $pdfBytes = documentThumbnailPdfFixture();

    expect($service->read('passing_reports', 41, $service->fingerprint($sourceChecksum)))->toBeNull();

    $fingerprint = $service->store('passing_reports', 41, $sourceChecksum, $pdfBytes);
    $png = $service->read('passing_reports', 41, $fingerprint);

    expect($fingerprint)->toBe($service->fingerprint($sourceChecksum))
        ->and($png)->toBeString()
        ->and(getimagesizefromstring($png)['mime'])->toBe('image/png')
        ->and(Storage::disk('local')->exists('thumbnails/passing_reports/41/'.$fingerprint.'.png'))->toBeTrue()
        ->and(Storage::disk('local')->allFiles('thumbnails/passing_reports/41'))->toBe(['thumbnails/passing_reports/41/'.$fingerprint.'.png']);
});

test('a changed source has a distinct cache identity and does not read the old thumbnail', function (): void {
    $service = documentThumbnailService();
    $sourceOne = hash('sha256', 'report-version-one');
    $sourceTwo = hash('sha256', 'report-version-two');
    $fingerprintOne = $service->store('passing_reports', 42, $sourceOne, documentThumbnailPdfFixture());

    expect($service->read('passing_reports', 42, $service->fingerprint($sourceTwo)))->toBeNull();

    $fingerprintTwo = $service->store('passing_reports', 42, $sourceTwo, documentThumbnailPdfFixture());

    expect($fingerprintTwo)->not->toBe($fingerprintOne)
        ->and($service->read('passing_reports', 42, $fingerprintOne))->toBeNull()
        ->and($service->read('passing_reports', 42, $fingerprintTwo))->toBeString();
});

test('it treats missing and corrupt PNGs as misses and deletes corruption', function (): void {
    $service = documentThumbnailService();
    $fingerprint = $service->fingerprint(hash('sha256', 'corruptible-source'));
    $path = 'thumbnails/passing_reports/43/'.$fingerprint.'.png';

    expect($service->read('passing_reports', 43, $fingerprint))->toBeNull();

    Storage::disk('local')->put($path, "\x89PNG\r\n\x1a\ntruncated");

    expect($service->read('passing_reports', 43, $fingerprint))->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('a successful read removes only stale fingerprint siblings and purge is template scoped', function (): void {
    $service = documentThumbnailService();
    $sourceChecksum = hash('sha256', 'current-source');
    $fingerprint = $service->store('passing_reports', 44, $sourceChecksum, documentThumbnailPdfFixture());
    $directory = 'thumbnails/passing_reports/44';
    $stalePath = $directory.'/'.str_repeat('b', 64).'.png';
    $unrecognizedPath = $directory.'/notes.txt';
    $otherTemplatePath = 'thumbnails/passing_reports/45/preserved.png';
    $outsidePath = 'thumbnails/passing_reports/notes.txt';
    Storage::disk('local')->put($stalePath, 'stale');
    Storage::disk('local')->put($unrecognizedPath, 'leave unrecognized');
    Storage::disk('local')->put($otherTemplatePath, 'other template');
    Storage::disk('local')->put($outsidePath, 'outside template directory');

    expect($service->read('passing_reports', 44, $fingerprint))->toBeString()
        ->and(Storage::disk('local')->exists($stalePath))->toBeFalse()
        ->and(Storage::disk('local')->exists($unrecognizedPath))->toBeTrue();

    $service->purge('passing_reports', 44);

    expect(Storage::disk('local')->allFiles($directory))->toBe([])
        ->and(Storage::disk('local')->exists($otherTemplatePath))->toBeTrue()
        ->and(Storage::disk('local')->exists($outsidePath))->toBeTrue();
});

test('it rejects unsafe surfaces, ids, checksums, and fingerprints', function (): void {
    $service = documentThumbnailService();

    expect(fn () => $service->fingerprint('not-a-checksum'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->read('../private', 1, str_repeat('a', 64)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->read('passing_reports', 0, str_repeat('a', 64)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->read('passing_reports', 1, '../outside'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->store('passing_reports', 1, 'bad', documentThumbnailPdfFixture()))->toThrow(InvalidArgumentException::class);
});
