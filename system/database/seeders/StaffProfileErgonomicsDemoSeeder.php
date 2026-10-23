<?php

namespace Database\Seeders;

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffProfileErgonomicsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        $user = User::query()->firstOrCreate(
            ['email' => 'profile.ergonomics@nexiam.test'],
            ['name' => 'Profile Ergonomics Demo', 'password' => 'password'],
        );
        $user->syncRoles(['program_head']);

        StaffProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'institution_id' => $institution->id,
                'office' => 'program',
                'program_codes' => ['BSIT'],
                'active_cycle_code' => $cycle->code,
                'status' => StaffProfile::STATUS_ACTIVE,
                'avatar_type' => 'preset',
                'avatar_preset' => 'academic_exec',
                'phone_extension' => '221',
                'room_number' => 'Administration Hall, Room 204',
                'consultation_hours' => 'Monday-Friday, 9:00 AM-4:00 PM',
            ],
        );

        StaffAuthenticationState::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'activation_completed_at' => now(),
                'password_set_at' => now(),
                'must_change_password' => false,
                'session_version' => 1,
                'mfa_required' => false,
            ],
        );
    }
}
