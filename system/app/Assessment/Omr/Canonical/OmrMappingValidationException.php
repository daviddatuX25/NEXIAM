<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

final class OmrMappingValidationException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [])
    {
        parent::__construct($errorCode.': '.$message);
    }
}
