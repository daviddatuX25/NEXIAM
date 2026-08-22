<?php

use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;

const OMR_RENDER_TEST_PAYLOAD = 'nexiam:blank:v1:NI-0123456789ABCDEFGHJKMNPQRS:p1:p1:kdev-v1:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

test('server QR renderer emits deterministic SVG and PNG bytes', function (): void {
    $renderer = app(OmrQrCodeRenderer::class);
    $svgOne = $renderer->svg(OMR_RENDER_TEST_PAYLOAD, 160);
    $svgTwo = $renderer->svg(OMR_RENDER_TEST_PAYLOAD, 160);
    $png = $renderer->png(OMR_RENDER_TEST_PAYLOAD, 160);

    expect($svgOne)->toBe($svgTwo)
        ->and($svgOne)->toContain('<svg')
        ->and($png)->toStartWith("\x89PNG")
        ->and(hash('sha256', $png))->toHaveLength(64);
});
