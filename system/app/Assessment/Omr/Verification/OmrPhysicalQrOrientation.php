<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrQrPayloadException;
use App\Assessment\Omr\Canonical\OmrQrReader;
use App\Assessment\Omr\OmrCaptureOrientationPort;
use RuntimeException;

final readonly class OmrPhysicalQrOrientation implements OmrCaptureOrientationPort
{
    public function __construct(
        private OmrQrReader $reader,
        private OmrCalibrationQrPayload $payloads,
    ) {}

    public function inspect(
        string $bytes,
        string $mime,
        string $challengeCode,
        int $pageNo,
    ): ?int {
        try {
            $observation = $this->reader->inspect($bytes, $mime);
        } catch (RuntimeException) {
            // Orientation is an optimization only. An unavailable or
            // untrusted observation falls back to bounded quarter-turn search.
            return null;
        }
        if (! $observation->found()) {
            return null;
        }

        $this->assertPayload($observation->payload, $challengeCode, $pageNo);

        return $observation->correctionQuarterTurn;
    }

    public function assertPayload(?string $payload, string $challengeCode, int $pageNo): void
    {
        if ($payload === null || trim($payload) === '') {
            return;
        }
        $payload = trim($payload);
        if (str_starts_with($payload, 'nexiam:blank:')) {
            throw new RuntimeException('qr_purpose_mismatch: A blank-instance QR cannot satisfy a calibration challenge capture.');
        }
        if (! str_starts_with($payload, OmrCalibrationQrPayload::PREFIX.':')) {
            throw new RuntimeException('qr_signature_invalid: The capture QR payload format is invalid.');
        }
        try {
            $verified = $this->payloads->verify($payload);
        } catch (OmrQrPayloadException $exception) {
            throw new RuntimeException($exception->errorCode.': '.$exception->getMessage());
        }
        if ((string) $verified['challenge_code'] !== $challengeCode) {
            throw new RuntimeException('wrong_challenge: The capture QR belongs to a different challenge.');
        }
        if ((int) $verified['page_no'] !== $pageNo) {
            throw new RuntimeException('wrong_challenge_page: The capture QR page does not match the submitted page.');
        }
    }
}
