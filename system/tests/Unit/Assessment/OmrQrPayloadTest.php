<?php

use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Assessment\Omr\Canonical\OmrQrPayloadException;

const OMR_TEST_INSTANCE_CODE = 'NI-0123456789ABCDEFGHJKMNPQRS';

test('signed QR payloads verify through the configured keyring', function (): void {
    $qr = new OmrQrPayload(['current-v1' => 'current-secret']);
    $payload = $qr->sign(OMR_TEST_INSTANCE_CODE, 1, 2, 'current-v1');
    $verified = $qr->verify($payload);

    expect($verified)->toMatchArray([
        'version' => 'v1',
        'instance_code' => OMR_TEST_INSTANCE_CODE,
        'page_no' => 1,
        'page_count' => 2,
        'signing_key_id' => 'current-v1',
        'signature_version' => 1,
    ])->and($verified['payload_hash'])->toHaveLength(64);
});

test('historical keys remain valid after key rotation', function (): void {
    $qr = new OmrQrPayload([
        'current-v1' => 'current-secret',
        'legacy-v1' => 'legacy-secret',
    ]);
    $payload = $qr->sign(OMR_TEST_INSTANCE_CODE, 2, 2, 'legacy-v1');

    expect($qr->verify($payload)['signing_key_id'])->toBe('legacy-v1');
});

test('unknown keys and forged payloads fail closed with typed errors', function (): void {
    $signed = new OmrQrPayload(['current-v1' => 'current-secret'])
        ->sign(OMR_TEST_INSTANCE_CODE, 1, 1, 'current-v1');
    $unknown = str_replace(':kcurrent-v1:', ':kretired-v1:', $signed);
    $forged = substr($signed, 0, -1).'0';

    expect(fn () => (new OmrQrPayload(['current-v1' => 'current-secret']))->verify($unknown))
        ->toThrow(OmrQrPayloadException::class, 'qr_key_unknown')
        ->and(fn () => (new OmrQrPayload(['current-v1' => 'current-secret']))->verify($forged))
        ->toThrow(OmrQrPayloadException::class, 'qr_signature_invalid');
});

test('manifest hash preserves page ordering', function (): void {
    $qr = new OmrQrPayload(['current-v1' => 'current-secret']);
    $one = hash('sha256', 'page-one');
    $two = hash('sha256', 'page-two');

    expect($qr->manifestHash([$one, $two]))
        ->not->toBe($qr->manifestHash([$two, $one]))
        ->and($qr->manifestHash([$one, $two]))->toBe($qr->manifestHash([$one, $two]));
});
