<?php

namespace App\Assessment\Omr\Canonical;

final class OmrCanonicalCompileException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [])
    {
        parent::__construct(sprintf('%s: %s', $errorCode, $message));
    }
}
