<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PublicIntakeDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
        $this->call(PublicIntakeConsentDemoSeeder::class);
        $this->call(JourneyFixtureSeeder::class);
    }
}
