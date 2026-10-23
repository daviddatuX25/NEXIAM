<?php

namespace App\Scheduling\Exceptions;

use RuntimeException;

class StalePlanConflictException extends RuntimeException
{
    /**
     * @param  list<int>  $staleSessionIds
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'The distribution plan is stale. Target sessions or eligible candidates have changed since preview.',
        public readonly array $staleSessionIds = [],
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
