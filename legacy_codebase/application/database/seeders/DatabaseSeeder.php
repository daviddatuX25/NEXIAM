<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Production Foundation Data ──────────────────────────────
        // These seeders populate the essential lookup tables required
        // for a fresh deployment. Safe to run on any environment.
        $this->call([
            RoleSeeder::class,              // 1. User roles (super_admin, staff, etc.)
            CourseSeeder::class,            // 2. Academic programs (BSEd, BSIT, etc.)
            RoomSeeder::class,              // 3. Exam rooms (Room 101, Lab Room 1, etc.)
            AptitudeAreaSeeder::class,      // 4. Test domains (GA, VA, NAP, SPA, PA, MD)
            RatingScaleSeeder::class,       // 5. Score interpretation scale
            AdmissionSlipTemplateSeeder::class,  // 6. Default admission slip template
            ResultSheetTemplateSeeder::class,    // 7. Default result sheet template
            AcademicYearSeeder::class,      // 8. Current academic year
            PrivacyPolicySeeder::class,     // 9. Data privacy notice (RA 10173)
            DemoAccountSeeder::class,       // 10. Super admin account (from .env)
        ]);

        // ── Development / Demo Data ────────────────────────────────
        // Only runs when DEMO=true in .env. Seeds fake applicants,
        // exam sessions, scores, etc. for testing and demonstrations.
        // NEVER enable in production.
        if (config('demo.enabled', false)) {
            $this->command?->info('DEMO=true detected — running DefenseDemoSeeder...');
            $this->call(DefenseDemoSeeder::class);
        }
    }
}
