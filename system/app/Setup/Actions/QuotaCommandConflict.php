<?php

namespace App\Setup\Actions;

use RuntimeException;

final class QuotaCommandConflict extends RuntimeException
{
    /** @param array{program_id:int,slots:int,version:int}|null $current */
    public function __construct(public readonly ?array $current, string $message = 'The quota changed before this save.')
    {
        parent::__construct($message);
    }
}
