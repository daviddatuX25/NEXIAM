<?php

declare(strict_types=1);

namespace App\Ai\Orchestration;

use RuntimeException;

final class XiamRunBudgetExceeded extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
