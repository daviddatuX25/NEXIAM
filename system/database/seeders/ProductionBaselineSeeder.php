<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;

/**
 * Clean, reset-ready ISPSC Tagudin campus baseline seeder.
 *
 * Seeds the complete institutional foundation, 4 campus venues with verified
 * WGS84 coordinates, 3 colleges (CTE, CBME including BPA, CAS) and 13 active
 * undergraduate programs with published public descriptions, official ICAT
 * Word result sheet template, intake requirements, privacy policies, Guidance
 * and Registrar public FAQs, and the locked + active TK-ICAT-2026 v1 Test Kit
 * (6-aptitude OASIS-3 Test Setup + Norm Package + 190-item ICAT materials)
 * without synthetic demo applicant sessions.
 */
final class ProductionBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'SYN-INST-001'],
            ['name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College')],
        );

        AcademicCycle::query()->updateOrCreate(
            ['code' => 'SYN-CYCLE-2026'],
            [
                'institution_id' => $institution->id,
                'name' => '2025-2026 1st Semester',
                'school_year' => '2025-2026',
                'semester' => '1',
                'opens_on' => '2026-08-01',
                'closes_on' => '2026-12-31',
                'is_active' => true,
            ],
        );

        $this->call(DatabaseSeeder::productionSeeders());

        app(RecordAuditEvent::class)->handle('foundation.seed_applied', [
            'institution_code' => 'SYN-INST-001',
            'cycle_code' => 'SYN-CYCLE-2026',
            'staff_seeded' => true,
            'reference_catalogs_seeded' => true,
            'production_baseline_seeded' => true,
        ], 'seeder');
    }
}
