<?php

declare(strict_types=1);

use App\Assessment\Omr\Canonical\OmrQrReservationResolver;

test('resolves default margin-bounded placement when no reservation is provided', function (): void {
    $resolved = OmrQrReservationResolver::resolvePx(null, 2480, 3508);

    expect($resolved['has_reservation'])->toBeFalse()
        ->and($resolved['qr_size'])->toBeGreaterThanOrEqual(100)
        ->and($resolved['qr_x'])->toBeGreaterThan(0)
        ->and($resolved['qr_y'])->toBeGreaterThan(0)
        ->and($resolved['qr_x'] + $resolved['qr_size'])->toBeLessThan(2480)
        ->and($resolved['display_code_y'])->toBeGreaterThan($resolved['qr_y'] + $resolved['qr_size']);
});

test('resolves from reviewManifest qr_reservation in pixels and achieves 100% containment', function (): void {
    // Draft 1 Stage 1/3 QR reservation: x: 100mm (1181px), y: 26mm (307px), w: 34mm (402px), h: 34mm (402px)
    $manifest = [
        'qr_reservation' => [
            'x' => 1181,
            'y' => 307,
            'width' => 402,
            'height' => 402,
        ],
    ];

    $resolved = OmrQrReservationResolver::resolvePx($manifest, 2480, 3508);

    expect($resolved['has_reservation'])->toBeTrue()
        ->and($resolved['x'])->toBe(1181)
        ->and($resolved['y'])->toBe(307)
        ->and($resolved['width'])->toBe(402)
        ->and($resolved['height'])->toBe(402);

    // Authored geometry is the QR matrix itself, not a padded card.
    expect($resolved['qr_x'])->toBe(1181)
        ->and($resolved['qr_y'])->toBe(307)
        ->and($resolved['qr_size'])->toBe(402)
        ->and($resolved['display_code_x'])->toBe(1181)
        ->and($resolved['display_code_y'])->toBeGreaterThanOrEqual(709);
});

test('resolves from reviewManifest qr_reservation in mm with 100% containment', function (): void {
    $manifest = [
        'qr_reservation' => [
            'x_mm' => 100.0,
            'y_mm' => 26.0,
            'w_mm' => 34.0,
            'h_mm' => 34.0,
        ],
    ];

    $resolved = OmrQrReservationResolver::resolvePx($manifest, 2480, 3508, ppi: 300.0);

    expect($resolved['has_reservation'])->toBeTrue();

    $boxX = (int) round(100.0 * 300.0 / 25.4);
    $boxY = (int) round(26.0 * 300.0 / 25.4);
    $boxSize = (int) round(34.0 * 300.0 / 25.4);

    expect($resolved['qr_x'])->toBe($boxX)
        ->and($resolved['qr_y'])->toBe($boxY)
        ->and($resolved['qr_size'])->toBe($boxSize);
});

test('resolves from overlay boxes with blank_instance_qr tag', function (): void {
    $source = [
        'overlay' => [
            'boxes' => [
                [
                    'name' => 'title',
                    'tag' => 'header_title',
                    'x' => 100,
                    'y' => 50,
                    'width' => 1000,
                    'height' => 100,
                ],
                [
                    'name' => 'exam_qr',
                    'tag' => 'blank_instance_qr',
                    'x' => 1181,
                    'y' => 307,
                    'width' => 402,
                    'height' => 402,
                ],
            ],
        ],
    ];

    $resolved = OmrQrReservationResolver::resolvePx($source, 2480, 3508);

    expect($resolved['has_reservation'])->toBeTrue()
        ->and($resolved['x'])->toBe(1181)
        ->and($resolved['y'])->toBe(307)
        ->and($resolved['width'])->toBe(402)
        ->and($resolved['height'])->toBe(402)
        ->and($resolved['qr_x'])->toBe(1181)
        ->and($resolved['qr_y'])->toBe(307)
        ->and($resolved['qr_size'])->toBe(402);
});

test('resolves from visualComponents with type qr', function (): void {
    $source = [
        'visualComponents' => [
            [
                'id' => 'qr_comp_1',
                'type' => 'qr',
                'posX' => 100.0,
                'posY' => 26.0,
                'w_mm' => 34.0,
                'h_mm' => 34.0,
            ],
        ],
    ];

    $resolved = OmrQrReservationResolver::resolvePx($source, 2480, 3508);

    expect($resolved['has_reservation'])->toBeTrue()
        ->and($resolved['qr_x'])->toBe((int) round(100.0 * 300.0 / 25.4))
        ->and($resolved['qr_y'])->toBe((int) round(26.0 * 300.0 / 25.4))
        ->and($resolved['qr_size'])->toBe((int) round(34.0 * 300.0 / 25.4));
});

test('resolves in millimetres for PDF compositor with 100% containment', function (): void {
    $overlay = [
        'boxes' => [
            [
                'tag' => 'blank_instance_qr',
                'x' => 100.0,
                'y' => 26.0,
                'width' => 34.0,
                'height' => 34.0,
                'unit' => 'mm',
            ],
        ],
    ];

    $resolvedMm = OmrQrReservationResolver::resolveMm($overlay, 210.0, 297.0);

    expect($resolvedMm['has_reservation'])->toBeTrue()
        ->and($resolvedMm['x'])->toBe(100.0)
        ->and($resolvedMm['y'])->toBe(26.0)
        ->and($resolvedMm['width'])->toBe(34.0)
        ->and($resolvedMm['height'])->toBe(34.0)
        ->and($resolvedMm['qr_x'])->toBe(100.0)
        ->and($resolvedMm['qr_y'])->toBe(26.0)
        ->and($resolvedMm['qr_size'])->toBe(34.0)
        ->and($resolvedMm['display_code_y'])->toBeGreaterThanOrEqual(60.0);
});

test('resolves small reserved box without clipping or negative dimensions', function (): void {
    $manifest = [
        'qr_reservation' => [
            'x' => 500,
            'y' => 500,
            'width' => 120,
            'height' => 120,
        ],
    ];

    $resolved = OmrQrReservationResolver::resolvePx($manifest, 2480, 3508);

    expect($resolved['has_reservation'])->toBeTrue()
        ->and($resolved['qr_size'])->toBeGreaterThanOrEqual(20)
        ->and($resolved['qr_x'])->toBeGreaterThanOrEqual(500)
        ->and($resolved['qr_x'] + $resolved['qr_size'])->toBeLessThanOrEqual(620)
        ->and($resolved['qr_y'])->toBeGreaterThanOrEqual(500)
        ->and($resolved['display_code_y'])->toBeGreaterThan($resolved['qr_y']);
});
