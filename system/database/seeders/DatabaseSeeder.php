<?php

namespace Database\Seeders;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'SYN-INST-001'],
            ['name' => 'Synthetic NEXIAM Institution'],
        );

        AcademicCycle::query()->updateOrCreate(
            ['code' => 'SYN-CYCLE-2026'],
            [
                'institution_id' => $institution->id,
                'name' => '2025-2026 1st Semester',
                'school_year' => '2025-2026',
                'semester' => '1',
                'opens_on' => '2026-01-01',
                'closes_on' => '2026-12-31',
                'is_active' => true,
            ],
        );

        $this->call([
            ReferenceCatalogSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            PrivacyPolicySeeder::class,
            IntakeGradeFieldSeeder::class,
            JourneyFixtureSeeder::class,
            OmrDemoSeeder::class,
        ]);

        app(RecordAuditEvent::class)->handle('foundation.seed_applied', [
            'institution_code' => 'SYN-INST-001',
            'cycle_code' => 'SYN-CYCLE-2026',
            'staff_seeded' => true,
            'reference_catalogs_seeded' => true,
            'journey_fixture_seeded' => true,
        ], 'seeder');
    }
}
