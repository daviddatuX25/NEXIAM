<?php

use App\Assessment\Omr\OmrCheckerDetection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('extractQr throws qr_reader_unavailable on connection failure', function (): void {
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);

    Http::fake(['*/v1/qr-extract' => function () {
        throw new ConnectionException('Connection refused');
    }]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_unavailable');
});

test('extractQr throws qr_reader_unavailable on 503 or 504 response', function (): void {
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);

    Http::fake(['*/v1/qr-extract' => Http::response('{"code":"worker_busy"}', 503)]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_unavailable');
});

test('extractQr throws qr_reader_response_invalid on non-200 non-503 response', function (): void {
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);

    Http::fake(['*/v1/qr-extract' => Http::response('{"code":"image_decode_failed"}', 422)]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_response_invalid');
});

test('extractQr throws qr_reader_response_invalid on invalid response signature or hash', function (): void {
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);

    Http::fake(['*/v1/qr-extract' => function ($request) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $body = json_encode(['contract_version' => 'nexiam.omr.detection.v3', 'found' => false, 'payload' => null, 'run_id' => $runId]);
        $hash = hash('sha256', $body);
        $badSig = str_repeat('0', 64);

        return Http::response($body, 200, [
            'Content-Type' => 'application/json',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => (string) time(),
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $badSig,
        ]);
    }]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_response_invalid');
});

test('extractQr throws qr_reader_response_invalid on wrong response content type', function (): void {
    $secret = str_repeat('s', 40);
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => $secret,
    ]);

    Http::fake(['*/v1/qr-extract' => function ($request) use ($secret) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $timestamp = (string) time();
        $body = json_encode(['contract_version' => 'nexiam.omr.detection.v3', 'found' => false, 'payload' => null, 'run_id' => $runId]);
        $hash = hash('sha256', $body);
        $sig = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $hash]), $secret);

        return Http::response($body, 200, [
            'Content-Type' => 'text/application/json-evil',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $sig,
        ]);
    }]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_response_invalid');
});

test('extractQr throws qr_reader_response_invalid on extra keys in response payload', function (): void {
    $secret = str_repeat('s', 40);
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => $secret,
    ]);

    Http::fake(['*/v1/qr-extract' => function ($request) use ($secret) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $timestamp = (string) time();
        $body = json_encode([
            'contract_version' => 'nexiam.omr.detection.v3',
            'extra_field' => 'forbidden',
            'found' => false,
            'payload' => null,
            'run_id' => $runId,
        ]);
        $hash = hash('sha256', $body);
        $sig = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $hash]), $secret);

        return Http::response($body, 200, [
            'Content-Type' => 'application/json',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $sig,
        ]);
    }]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_response_invalid');
});

test('extractQr throws qr_reader_response_invalid on found and payload mismatch', function (): void {
    $secret = str_repeat('s', 40);
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => $secret,
    ]);

    // Case 1: found=true but payload=null
    Http::fake(['*/v1/qr-extract' => function ($request) use ($secret) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $timestamp = (string) time();
        $body = json_encode([
            'contract_version' => 'nexiam.omr.detection.v3',
            'found' => true,
            'payload' => null,
            'run_id' => $runId,
        ]);
        $hash = hash('sha256', $body);
        $sig = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $hash]), $secret);

        return Http::response($body, 200, [
            'Content-Type' => 'application/json',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $sig,
        ]);
    }]);

    $detector = app(OmrCheckerDetection::class);
    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_response_invalid');

    // Case 2: found=false but payload is non-null string
    Http::fake(['*/v1/qr-extract' => function ($request) use ($secret) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $timestamp = (string) time();
        $body = json_encode([
            'contract_version' => 'nexiam.omr.detection.v3',
            'found' => false,
            'payload' => 'unexpected-payload',
            'run_id' => $runId,
        ]);
        $hash = hash('sha256', $body);
        $sig = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $hash]), $secret);

        return Http::response($body, 200, [
            'Content-Type' => 'application/json',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $sig,
        ]);
    }]);

    expect(fn () => $detector->inspectQr('png-bytes', 'image/png'))
        ->toThrow(RuntimeException::class, 'qr_reader_response_invalid');
});

test('inspectQr returns an empty observation on a valid signed response without a QR', function (): void {
    $secret = str_repeat('s', 40);
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => $secret,
    ]);

    Http::fake(['*/v1/qr-extract' => function ($request) use ($secret) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $timestamp = (string) time();
        $body = json_encode([
            'contract_version' => 'nexiam.omr.detection.v3',
            'correction_quarter_turn' => null,
            'corners' => [],
            'found' => false,
            'payload' => null,
            'quarter_turn_residual_degrees' => null,
            'run_id' => $runId,
            'top_edge_angle_degrees' => null,
        ]);
        $hash = hash('sha256', $body);
        $sig = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $hash]), $secret);

        return Http::response($body, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $sig,
        ]);
    }]);

    $detector = app(OmrCheckerDetection::class);
    $observation = $detector->inspectQr('png-bytes', 'image/png');
    expect($observation->found())->toBeFalse()
        ->and($observation->payload)->toBeNull()
        ->and($observation->correctionQuarterTurn)->toBeNull();
});

test('inspectQr returns a typed payload and reliable correction turn', function (): void {
    $secret = str_repeat('s', 40);
    $payload = 'nexiam:blank:v1:NI-0123456789ABCDEFGHJKMNPQRS:p1:p1:kdev-v1:sig';
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference-test:8100',
        'omr.omrchecker.signing_secret' => $secret,
    ]);

    Http::fake(['*/v1/qr-extract' => function ($request) use ($secret, $payload) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $timestamp = (string) time();
        $body = json_encode([
            'contract_version' => 'nexiam.omr.detection.v3',
            'correction_quarter_turn' => 270,
            'corners' => [[471, 128], [471, 472], [127, 472], [127, 128]],
            'found' => true,
            'payload' => $payload,
            'quarter_turn_residual_degrees' => 0.0,
            'run_id' => $runId,
            'top_edge_angle_degrees' => 90.0,
        ]);
        $hash = hash('sha256', $body);
        $sig = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $hash]), $secret);

        return Http::response($body, 200, [
            'Content-Type' => 'application/json',
            'X-Nexiam-Contract' => 'nexiam.omr.detection.v3',
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $hash,
            'X-Nexiam-Signature' => $sig,
        ]);
    }]);

    $detector = app(OmrCheckerDetection::class);
    $observation = $detector->inspectQr('png-bytes', 'image/png');
    expect($observation->payload)->toBe($payload)
        ->and($observation->correctionQuarterTurn)->toBe(270)
        ->and($observation->corners)->toHaveCount(4);
});
