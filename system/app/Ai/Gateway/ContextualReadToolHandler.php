<?php

namespace App\Ai\Gateway;

use App\Ai\Context\InvocationContext;
use App\Models\User;

interface ContextualReadToolHandler
{
    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array;
}
