<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ai\Evaluation\XiamEvalIsolationGuard;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seed only deterministic fixtures in an explicitly approved Xiam eval database.
 */
class XiamEvalSeeder extends Seeder
{
    public function run(): void
    {
        if (app(XiamEvalIsolationGuard::class)->failure() !== null) {
            throw new RuntimeException('Xiam evaluation seeding requires an explicitly approved isolated evaluation database.');
        }

        (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    }
}
