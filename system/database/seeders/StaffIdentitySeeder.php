<?php

namespace Database\Seeders;

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StaffIdentitySeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        $adminEmail = (string) config('seed.admin.email', 'admin.lead@nexiam.test');
        $adminName = (string) config('seed.admin.name', 'Synthetic Institutional Administrator');
        $adminPassword = (string) config('seed.admin.password', 'password');

        $hrEmail = (string) config('seed.hr.email');
        $hrName = (string) config('seed.hr.name');
        $hrPassword = (string) config('seed.hr.password');

        $staff = [
            [
                'email' => $adminEmail,
                'name' => $adminName,
                'role' => 'institutional_admin',
                'office' => 'admin',
                'programs' => [],
                'password' => $adminPassword,
            ],
            [
                'email' => 'registrar.clerk@nexiam.test',
                'name' => 'Synthetic Registrar Clerk',
                'role' => 'registrar_clerk',
                'office' => 'registrar',
                'programs' => [],
                'password' => 'password',
            ],
            [
                'email' => 'registrar.admin@nexiam.test',
                'name' => 'Synthetic Registrar Admin',
                'role' => 'registrar_admin',
                'office' => 'registrar',
                'programs' => [],
                'password' => 'password',
            ],
            [
                'email' => 'registrar.support@nexiam.test',
                'name' => 'Synthetic Registrar Support',
                'role' => 'registrar_support_staff',
                'office' => 'registrar',
                'programs' => [],
                'password' => 'password',
            ],
            [
                'email' => 'guidance.clerk@nexiam.test',
                'name' => 'Synthetic Guidance Clerk',
                'role' => 'guidance_clerk',
                'office' => 'guidance',
                'programs' => [],
                'password' => 'password',
            ],
            [
                'email' => 'guidance.support@nexiam.test',
                'name' => 'Synthetic Guidance Support',
                'role' => 'guidance_support_staff',
                'office' => 'guidance',
                'programs' => [],
                'password' => 'password',
            ],
            [
                'email' => 'guidance.counselor@nexiam.test',
                'name' => 'Synthetic Guidance Counselor',
                'role' => 'guidance_counselor',
                'office' => 'guidance',
                'programs' => [],
                'password' => 'password',
            ],
            [
                'email' => 'cas.dean@nexiam.test',
                'name' => 'Dean of Arts & Sciences (CAS)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSIT', 'BSMath', 'BA-EL', 'BA-Psych', 'BASS'],
                'password' => 'password',
            ],
            [
                'email' => 'cte.dean@nexiam.test',
                'name' => 'Dean of Teacher Education (CTE)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSEd', 'BSEd-Eng', 'BSEd-Math', 'BEEd', 'BPEd'],
                'password' => 'password',
            ],
            [
                'email' => 'cbme.dean@nexiam.test',
                'name' => 'Dean of Business Management & Entrepreneurship (CBME)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSBA', 'BSEntrep', 'BPA'],
                'password' => 'password',
            ],
            [
                'email' => 'bsit.head@nexiam.test',
                'name' => 'Program Head — BS Information Technology (BSIT)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSIT'],
                'password' => 'password',
            ],
            [
                'email' => 'bsmath.head@nexiam.test',
                'name' => 'Program Head — BS Mathematics (BSMath)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSMath'],
                'password' => 'password',
            ],
            [
                'email' => 'bael.head@nexiam.test',
                'name' => 'Program Head — BA English Language (BA-EL)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BA-EL'],
                'password' => 'password',
            ],
            [
                'email' => 'bapsych.head@nexiam.test',
                'name' => 'Program Head — BA Psychology (BA-Psych)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BA-Psych'],
                'password' => 'password',
            ],
            [
                'email' => 'bass.head@nexiam.test',
                'name' => 'Program Head — BA Social Science (BASS)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BASS'],
                'password' => 'password',
            ],
            [
                'email' => 'bsba.head@nexiam.test',
                'name' => 'Program Head — BS Business Administration (BSBA)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSBA'],
                'password' => 'password',
            ],
            [
                'email' => 'bsentrep.head@nexiam.test',
                'name' => 'Program Head — BS Entrepreneurship (BSEntrep)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSEntrep'],
                'password' => 'password',
            ],
            [
                'email' => 'bpa.head@nexiam.test',
                'name' => 'Program Head — Bachelor in Public Administration (BPA)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BPA'],
                'password' => 'password',
            ],
            [
                'email' => 'bsed.head@nexiam.test',
                'name' => 'Program Head — Bachelor of Secondary Education (BSEd)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BSEd', 'BSEd-Eng', 'BSEd-Math'],
                'password' => 'password',
            ],
            [
                'email' => 'beed.head@nexiam.test',
                'name' => 'Program Head — Bachelor of Elementary Education (BEEd)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BEEd'],
                'password' => 'password',
            ],
            [
                'email' => 'bped.head@nexiam.test',
                'name' => 'Program Head — Bachelor of Physical Education (BPEd)',
                'role' => 'program_head',
                'office' => 'program',
                'programs' => ['BPEd'],
                'password' => 'password',
            ],
            [
                'email' => $hrEmail,
                'name' => $hrName,
                'role' => 'hr_administrator',
                'office' => 'admin',
                'programs' => [],
                'password' => $hrPassword,
            ],
        ];

        if (app()->environment('testing')) {
            $staff = [
                ...$staff,
                [
                    'email' => 'program.head.bsit@nexiam.test',
                    'name' => 'Synthetic Program Head BSIT',
                    'role' => 'program_head',
                    'office' => 'program',
                    'programs' => ['BSIT'],
                    'password' => 'password',
                ],
                [
                    'email' => 'dean.ccit@nexiam.test',
                    'name' => 'Dean of Computing & IT (CCIT)',
                    'role' => 'program_head',
                    'office' => 'program',
                    'programs' => ['BSIT'],
                    'password' => 'password',
                ],
                [
                    'email' => 'dean.cas@nexiam.test',
                    'name' => 'Dean of Arts & Sciences (CAS)',
                    'role' => 'program_head',
                    'office' => 'program',
                    'programs' => ['BSIT', 'BSMath', 'BA-EL', 'BA-Psych', 'BASS'],
                    'password' => 'password',
                ],
                [
                    'email' => 'dean.cte@nexiam.test',
                    'name' => 'Dean of Teacher Education (CTE)',
                    'role' => 'program_head',
                    'office' => 'program',
                    'programs' => ['BSEd', 'BSEd-Eng', 'BSEd-Math', 'BEEd', 'BPEd'],
                    'password' => 'password',
                ],
                [
                    'email' => 'dean.cbme@nexiam.test',
                    'name' => 'Dean of Business Management & Entrepreneurship (CBME)',
                    'role' => 'program_head',
                    'office' => 'program',
                    'programs' => ['BSBA', 'BSEntrep', 'BPA'],
                    'password' => 'password',
                ],
            ];
        }

        foreach ($staff as $row) {
            $user = User::query()->firstOrNew(['email' => $row['email']]);
            if (! $user->exists) {
                $user->password = $row['password'];
            }
            $user->name = $row['name'];
            $user->save();

            $user->syncRoles([$row['role']]);

            $centralOffice = in_array($row['office'], ['admin', 'registrar', 'guidance'], true);
            $profileAttributes = [
                'institution_id' => $institution->id,
                'office' => $row['office'],
                'program_codes' => $centralOffice ? [] : $row['programs'],
                'active_cycle_code' => $cycle->code,
                'status' => StaffProfile::STATUS_ACTIVE,
                'deactivated_at' => null,
            ];
            if ($centralOffice) {
                $profileAttributes['department_id'] = null;
            }
            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $profileAttributes,
            );

            $authenticationState = StaffAuthenticationState::query()->firstOrNew(['user_id' => $user->id]);
            if (! $authenticationState->exists) {
                $authenticationState->forceFill([
                    'user_id' => $user->id,
                    'activation_completed_at' => now(),
                    'password_set_at' => now(),
                    'must_change_password' => false,
                    'session_version' => 1,
                    'mfa_required' => false,
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_generation' => (string) Str::uuid(),
                ]);
            } elseif ($authenticationState->two_factor_generation === null && ! $authenticationState->hasConfirmedTwoFactor()) {
                $authenticationState->forceFill(['two_factor_generation' => (string) Str::uuid()]);
            }

            $authenticationState->save();

        }
    }
}
