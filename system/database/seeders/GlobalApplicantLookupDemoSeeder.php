<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class GlobalApplicantLookupDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Applicant lookup demo data is restricted to an isolated local/testing runtime.');
        }

        (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
        $this->call([PublicIntakeConsentDemoSeeder::class, JourneyFixtureSeeder::class]);
    }
}
