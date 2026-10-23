<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

final class StaffPlatformOverhaulDemoSeeder extends Seeder
{
    private const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    /** @var list<array{email: string, name: string, role: string, office: string, factor: 'enrolled'|'pending'|'exempt', department: ?string, programs: list<string>}> */
    private const PERSONAS = [
        ['email' => 'staff.hr@nexiam.test', 'name' => 'Staff Platform HR Administrator', 'role' => 'hr_administrator', 'office' => 'admin', 'factor' => 'enrolled', 'department' => null, 'programs' => []],
        ['email' => 'staff.guidance@nexiam.test', 'name' => 'Staff Platform Guidance Counselor · Test Administrator', 'role' => 'guidance_counselor', 'office' => 'guidance', 'factor' => 'enrolled', 'department' => null, 'programs' => []],
        ['email' => 'staff.clerk@nexiam.test', 'name' => 'Staff Platform Guidance Clerk · Test Administrator · Delegated', 'role' => 'guidance_clerk', 'office' => 'guidance', 'factor' => 'enrolled', 'department' => null, 'programs' => []],
        ['email' => 'staff.regclerk@nexiam.test', 'name' => 'Staff Platform Registrar Clerk', 'role' => 'registrar_clerk', 'office' => 'registrar', 'factor' => 'pending', 'department' => null, 'programs' => []],
        ['email' => 'staff.dean@nexiam.test', 'name' => 'Staff Platform Dean CAS', 'role' => 'program_head', 'office' => 'program', 'factor' => 'exempt', 'department' => 'CAS', 'programs' => ['BSIT', 'BA-EL', 'BA-Psych', 'BSMath', 'BASS']],
        ['email' => 'staff.proghead@nexiam.test', 'name' => 'Staff Platform Program Head BSIT', 'role' => 'program_head', 'office' => 'program', 'factor' => 'exempt', 'department' => null, 'programs' => ['BSIT']],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Staff platform demo identities may only be seeded in local/testing environments.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        foreach (self::PERSONAS as $persona) {
            $user = User::query()->firstOrNew(['email' => $persona['email']]);
            if (! $user->exists) {
                $user->password = 'password';
            }
            $user->name = $persona['name'];
            $user->email_verified_at ??= now();
            $user->save();
            $user->syncRoles([$persona['role']]);
            if ($persona['email'] === 'staff.guidance@nexiam.test') {
                $user->givePermissionTo(CapabilityCatalog::testAdministratorCapabilities());
            } elseif ($persona['email'] === 'staff.clerk@nexiam.test') {
                $user->givePermissionTo(CapabilityCatalog::delegatedTestAdministratorCapabilities());
            }

            $department = $persona['department'] === null
                ? null
                : Department::query()->where('institution_id', $institution->id)->where('code', $persona['department'])->firstOrFail();
            foreach ($persona['programs'] as $code) {
                if (! Program::query()->where('institution_id', $institution->id)->where('code', $code)->where('is_active', true)->exists()) {
                    throw new RuntimeException("Staff platform demo program {$code} is unavailable.");
                }
            }
            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'institution_id' => $institution->id,
                    'office' => $persona['office'],
                    'department_id' => $department?->id,
                    'program_codes' => $persona['programs'],
                    'active_cycle_code' => $cycle->code,
                    'status' => StaffProfile::STATUS_ACTIVE,
                    'deactivated_at' => null,
                ],
            );

            $state = StaffAuthenticationState::query()->firstOrNew(['user_id' => $user->id]);
            if (! $state->exists) {
                $state->forceFill([
                    'user_id' => $user->id,
                    'activation_completed_at' => now(),
                    'password_set_at' => now(),
                    'must_change_password' => false,
                    'session_version' => 1,
                    'mfa_required' => false,
                    'two_factor_generation' => (string) Str::uuid(),
                ]);
            }
            if ($persona['factor'] === 'enrolled' && ! $state->hasConfirmedTwoFactor()) {
                $state->forceFill(['two_factor_secret' => self::TOTP_SECRET, 'two_factor_confirmed_at' => now()]);
            } elseif ($persona['factor'] === 'pending' && ! $state->hasConfirmedTwoFactor()) {
                $state->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
            } elseif ($persona['factor'] === 'exempt' && ! $state->hasConfirmedTwoFactor()) {
                $state->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
            }
            $state->save();
        }
    }
}
