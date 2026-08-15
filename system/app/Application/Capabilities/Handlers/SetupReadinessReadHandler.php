<?php

namespace App\Application\Capabilities\Handlers;

use App\Setup\Queries\ComputeSetupReadinessQuery;

final class SetupReadinessReadHandler
{
    public function __construct(
        private readonly ComputeSetupReadinessQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        unset($input);

        return $this->query->handle();
    }
}
