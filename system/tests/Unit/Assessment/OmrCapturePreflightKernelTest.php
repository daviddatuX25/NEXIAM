<?php

use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCapturePreflightLimits;

function preflightKernelImageBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

test('capture preflight accepts a decoded image and exposes deterministic metadata', function (): void {
    $bytes = preflightKernelImageBytes();
    $result = (new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 10, 20)))->validate(
        new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
    );

    expect($result->succeeded())->toBeTrue()
        ->and($result->sha256)->toBe(hash('sha256', $bytes))
        ->and($result->byteCount)->toBe(strlen($bytes))
        ->and($result->width)->toBe(1)
        ->and($result->height)->toBe(1)
        ->and($result->pageCount)->toBe(1);
});

test('capture preflight rejects MIME and byte violations without worker concerns', function (): void {
    $bytes = preflightKernelImageBytes();
    $validator = new OmrCapturePreflight(new OmrCapturePreflightLimits(4, 10, 20));
    $oversize = $validator->validate(new OmrCaptureInput(bytes: $bytes, mime: 'image/png'));
    $mismatch = (new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 10, 20)))->validate(
        new OmrCaptureInput(bytes: $bytes, mime: 'image/jpeg'),
    );

    expect($oversize->succeeded())->toBeFalse()
        ->and($oversize->code)->toBe('upload_too_large')
        ->and($mismatch->succeeded())->toBeFalse()
        ->and($mismatch->code)->toBe('magic_mismatch');
});

test('capture preflight enforces decoded pixels and PDF page count', function (): void {
    $bytes = preflightKernelImageBytes();
    $pixel = (new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 0, 20)))->validate(
        new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
    );
    $pdf = "%PDF-1.7\n1 0 obj << /Type /Page >> endobj\n2 0 obj << /Type /Page >> endobj\nstartxref\n0\n%%EOF";
    $pdfResult = (new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 10, 20)))->validate(
        new OmrCaptureInput(bytes: $pdf, mime: 'application/pdf', pageNo: 2),
    );

    expect($pixel->succeeded())->toBeFalse()
        ->and($pixel->code)->toBe('pixel_budget_exceeded')
        ->and($pdfResult->succeeded())->toBeTrue()
        ->and($pdfResult->pageCount)->toBe(2)
        ->and($pdfResult->width)->toBeNull();
});
