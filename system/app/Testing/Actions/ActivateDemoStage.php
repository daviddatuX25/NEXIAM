<?php

namespace App\Testing\Actions;

use App\Models\User;

final class ActivateDemoStage
{
    public function __construct(private readonly PreparePracticeOperation $operations) {}

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function handle(User $operator, array $input, ?string $operationId = null): array
    {
        return $operationId === null
            ? $this->operations->preview($operator, $input)
            : $this->operations->confirm($operator, $operationId);
    }
}
