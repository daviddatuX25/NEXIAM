<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

/**
 * Typed QR payload failure carrying a machine-readable error code
 * (`qr_signature_invalid`, `qr_key_unknown`, ...).
 */
class OmrQrPayloadException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($errorCode.': '.$message);
    }
}
