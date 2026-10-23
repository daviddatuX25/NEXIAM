<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrCheckerDetection;
use RuntimeException;

class OmrQrReader
{
    public function __construct(
        private readonly OmrCheckerDetection $detection,
    ) {}

    public function inspect(string $imageBytes, string $imageMime): OmrQrObservation
    {
        $fake = config('omr.fake.qr_payload');
        if (is_string($fake)) {
            if ($fake === 'qr_reader_unavailable') {
                throw new RuntimeException('qr_reader_unavailable: The QR extraction service is unavailable.');
            }
            if ($fake === 'qr_reader_response_invalid') {
                throw new RuntimeException('qr_reader_response_invalid: The QR extraction response is invalid.');
            }

            return new OmrQrObservation(payload: $fake !== '' ? $fake : null);
        }

        if (! (bool) config('omr.omrchecker.enabled', false)) {
            throw new RuntimeException('qr_reader_unavailable: The OMR worker QR extraction is disabled.');
        }

        return $this->detection->inspectQr($imageBytes, $imageMime);
    }
}
