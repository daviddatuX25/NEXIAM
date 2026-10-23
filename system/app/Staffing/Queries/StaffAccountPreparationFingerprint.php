<?php

declare(strict_types=1);

namespace App\Staffing\Queries;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StaffAccountPreparationFingerprint
{
    /** @param array<string, mixed> $payload @param list<int> $excludedUserIds */
    public function handle(User $actor, array $payload, array $excludedUserIds = []): string
    {
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        $programs = DB::table('programs')->where('institution_id', $institutionId)->orderBy('id')->get(['id', 'department_id', 'code', 'is_active'])->all();
        $departments = DB::table('departments')->where('institution_id', $institutionId)->orderBy('id')->get(['id', 'dean_user_id', 'is_active'])->all();
        $personnel = StaffProfile::query()->where('institution_id', $institutionId)
            ->when($excludedUserIds !== [], fn ($query) => $query->whereNotIn('user_id', $excludedUserIds))
            ->with('user.roles:id,name')->orderBy('user_id')->get()
            ->map(fn (StaffProfile $profile) => [
                'user_id' => $profile->user_id,
                'status' => $profile->status,
                'office' => $profile->office,
                'department_id' => $profile->department_id,
                'program_codes' => $profile->program_codes ?? [],
                'roles' => $profile->user?->roles->pluck('name')->sort()->values()->all() ?? [],
            ])->all();

        return hash('sha256', json_encode([$payload, $programs, $departments, $personnel], JSON_THROW_ON_ERROR));
    }
}
