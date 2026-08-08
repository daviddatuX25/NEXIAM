<?php

namespace Database\Seeders;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffIdentitySeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        $hrEmail = (string) config('seed.hr.email');
        $hrName = (string) config('seed.hr.name');
        $hrPassword = (string) config('seed.hr.password');

        $staff = [
            [
                'email' => 'registrar.clerk@nexiam.test',
                'name' => 'Synthetic Registrar Clerk',
                'role' => 'registrar_clerk',
                'office' => 'registrar',
                'programs' => ['BSN'],
                'password' => 'password',
            ],
            [
                'email' => 'registrar.admin@nexiam.test',
                'name' => 'Synthetic Registrar Admin',
                'role' => 'registrar_admin',
                'office' => 'registrar',
                'programs' => ['BSN'],
                'password' => 'password',
            ],
            [
                'email' => 'guidance.clerk@nexiam.test',
                'name' => 'Synthetic Guidance Clerk',
                'role' => 'guidance_clerk',
                'office' => 'guidance',
                'programs' => ['BSN', 'BSPSY'],
                'password' => 'password',
            ],
            [
                'email' => 'guidance.counselor@nexiam.test',
                'name' => 'Synthetic Guidance Counselor',
                'role' => 'guidance_counselor',
                'office' => 'guidance',
                'programs' => ['BSN', 'BSPSY'],
                'password' => 'password',
            ],
            [
                'email' => 'program.head.bsn@nexiam.test',
                'name' => 'Synthetic Program Head BSN',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSN'],
                'password' => 'password',
            ],
            [
                'email' => $hrEmail,
                'name' => $hrName,
                'role' => 'hr_administrator',
                'office' => 'hr',
                'programs' => [],
                'password' => $hrPassword,
            ],
        ];

        foreach ($staff as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $row['password'],
                ],
            );

            $user->syncRoles([$row['role']]);

            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'institution_id' => $institution->id,
                    'office' => $row['office'],
                    'program_codes' => $row['programs'],
                    'active_cycle_code' => $cycle->code,
                    'status' => StaffProfile::STATUS_ACTIVE,
                    'deactivated_at' => null,
                ],
            );
        }
    }
}
