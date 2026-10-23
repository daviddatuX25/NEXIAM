<?php

namespace Database\Seeders;

use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;

final class AdminOfficeDemoSeeder extends Seeder
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

        $this->call([
            InstitutionProfileSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            XiamModelTariffSeeder::class,
            PublicHomepageSeeder::class,
            ReportTemplateSeeder::class,
        ]);

        XiamInstitutionPolicy::query()->updateOrCreate(
            ['institution_id' => $institution->id],
            [
                'emergency_stop' => false,
                'applicant_inquiry_enabled' => true,
                'applicant_self_booking_enabled' => true,
                'daily_institution_ask_ceiling' => 1000,
                'daily_applicant_pool_asks' => 300,
                'office_ask_allocations' => [
                    'admin' => 100,
                    'registrar' => 300,
                    'guidance' => 300,
                    'program' => 100,
                ],
                'daily_institution_budget_micro' => 50000000,
                'office_budget_allocations_micro' => [
                    'admin' => 5000000,
                    'registrar' => 15000000,
                    'guidance' => 15000000,
                    'program' => 5000000,
                ],
                'per_staff_daily_ask_limit' => 200,
                'per_applicant_daily_ask_limit' => 15,
            ],
        );
    }
}
