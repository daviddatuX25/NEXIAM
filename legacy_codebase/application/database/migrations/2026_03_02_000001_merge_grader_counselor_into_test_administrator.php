<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Merge grader and counselor roles into test_administrator.
     * - Ensure test_administrator role exists
     * - Reassign users with grader/counselor to test_administrator
     * - Remove grader and counselor roles
     */
    public function up(): void
    {
        $testAdmin = Role::firstOrCreate(
            ['name' => 'test_administrator'],
            [
                'display_name' => 'Test Administrator',
                'description' => 'Guidance office, inputs scores and releases consultations',
            ]
        );

        $grader = Role::where('name', 'grader')->first();
        $counselor = Role::where('name', 'counselor')->first();

        $oldRoleIds = array_filter([$grader?->id, $counselor?->id]);
        if (! empty($oldRoleIds)) {
            $users = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['grader', 'counselor']))
                ->get();
            foreach ($users as $user) {
                $user->roles()->detach($oldRoleIds);
                $user->roles()->syncWithoutDetaching([$testAdmin->id]);
            }
        }

        Role::whereIn('name', ['grader', 'counselor'])->delete();
    }

    /**
     * Reverse: recreate grader and counselor roles. User assignments are not restored.
     */
    public function down(): void
    {
        Role::insert([
            [
                'name' => 'grader',
                'display_name' => 'Grader',
                'description' => 'Guidance office, inputs scores',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'counselor',
                'display_name' => 'Counselor',
                'description' => 'Guidance office, releases consultations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
