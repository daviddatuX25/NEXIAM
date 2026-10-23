<?php

namespace App\Admission\Exceptions;

use App\Models\Admission\AdmissionAttempt;
use RuntimeException;

class DuplicateAdmissionAttemptException extends RuntimeException
{
    public function __construct(
        string $message = 'An admission attempt already exists for this cycle.',
        public readonly ?AdmissionAttempt $existingAttempt = null,
    ) {
        parent::__construct($message);
    }
}
