<?php

namespace App\Notifications\Templates\Email;

use InvalidArgumentException;

class NoticeEmailSchemaValidationException extends InvalidArgumentException
{
    /**
     * @param  list<array{code: string, message: string, path: string}>  $diagnostics
     */
    public function __construct(string $message, public readonly array $diagnostics = [])
    {
        parent::__construct($message);
    }

    /**
     * @return list<array{code: string, message: string, path: string}>
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }
}
