<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Testing\Seeders\TestKitBrowserQaBootstrap;
use Illuminate\Database\Seeder;

final class TestKitBrowserQaSeeder extends Seeder
{
    /** @return array<string, int|string> */
    public function run(bool $setupOnly = false): array
    {
        return app(TestKitBrowserQaBootstrap::class)->run($setupOnly);
    }
}
