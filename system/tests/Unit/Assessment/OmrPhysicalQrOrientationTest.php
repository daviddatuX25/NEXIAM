<?php

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrQrObservation;
use App\Assessment\Omr\Canonical\OmrQrReader;
use App\Assessment\Omr\Verification\OmrPhysicalQrOrientation;
use Mockery\MockInterface;

function trustedOrientationPayload(): OmrCalibrationQrPayload
{
    return new OmrCalibrationQrPayload(['test-v1' => 'test-secret'], 'test-v1');
}

test('signed calibration geometry becomes a trusted correction turn', function () {
    $payloads = trustedOrientationPayload();
    $payload = $payloads->sign('CHAL-ABCDEF1234', 1, 1);
    $reader = Mockery::mock(OmrQrReader::class, function (MockInterface $mock) use ($payload): void {
        $mock->shouldReceive('inspect')->once()->andReturn(new OmrQrObservation(
            payload: $payload,
            corners: [[10.0, 10.0], [10.0, 40.0], [-20.0, 40.0], [-20.0, 10.0]],
            topEdgeAngle: 90.0,
            quarterTurnResidual: 0.0,
            correctionQuarterTurn: 270,
        ));
    });

    $turn = (new OmrPhysicalQrOrientation($reader, $payloads))->inspect(
        'image-bytes',
        'image/png',
        'CHAL-ABCDEF1234',
        1,
    );

    expect($turn)->toBe(270);
});

test('geometry cannot be trusted before signed challenge verification', function () {
    $payloads = trustedOrientationPayload();
    $payload = $payloads->sign('CHAL-ABCDEF1234', 1, 1);
    $tampered = substr($payload, 0, -1).(str_ends_with($payload, '0') ? '1' : '0');
    $reader = Mockery::mock(OmrQrReader::class, function (MockInterface $mock) use ($tampered): void {
        $mock->shouldReceive('inspect')->once()->andReturn(new OmrQrObservation(
            payload: $tampered,
            corners: [[10.0, 10.0], [10.0, 40.0], [-20.0, 40.0], [-20.0, 10.0]],
            topEdgeAngle: 90.0,
            quarterTurnResidual: 0.0,
            correctionQuarterTurn: 270,
        ));
    });

    expect(fn () => (new OmrPhysicalQrOrientation($reader, $payloads))->inspect(
        'image-bytes',
        'image/png',
        'CHAL-ABCDEF1234',
        1,
    ))->toThrow(RuntimeException::class, 'qr_signature_invalid');
});

test('untrusted QR worker responses preserve marker-only fallback', function () {
    $reader = Mockery::mock(OmrQrReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('inspect')->once()->andThrow(new RuntimeException('qr_reader_response_invalid'));
    });

    $turn = (new OmrPhysicalQrOrientation($reader, trustedOrientationPayload()))->inspect(
        'image-bytes',
        'image/png',
        'CHAL-ABCDEF1234',
        1,
    );

    expect($turn)->toBeNull();
});
