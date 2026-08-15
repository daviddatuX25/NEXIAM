<?php

namespace App\Examination\Support;

use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * D-015 eligible Proctor duty assignees — Guidance clerk/support only.
 */
final class EligibleProctorCandidates
{
    public const ELIGIBLE_ROLE_HELPERS = [
        'guidance_clerk',
        'guidance_support_staff',
    ];

    /**
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    public function forSession(ExamSession $session): array
    {
        $activeUserIds = ProctorDuty::query()
            ->where('exam_session_id', $session->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->pluck('user_id')
            ->all();

        return $this->eligibleUsers()
            ->reject(fn (User $user) => in_array($user->id, $activeUserIds, true))
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => (string) ($user->getRoleNames()->first() ?? ''),
            ])
            ->values()
            ->all();
    }

    public function isEligible(User $candidate): bool
    {
        $profile = $candidate->staffProfile;
        if ($profile === null || $profile->status !== StaffProfile::STATUS_ACTIVE) {
            return false;
        }

        if ($profile->office !== 'guidance') {
            return false;
        }

        return $candidate->hasAnyRole(self::ELIGIBLE_ROLE_HELPERS);
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleUsers(): Collection
    {
        return User::query()
            ->whereHas('staffProfile', function ($q): void {
                $q->where('office', 'guidance')
                    ->where('status', StaffProfile::STATUS_ACTIVE);
            })
            ->role(self::ELIGIBLE_ROLE_HELPERS)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
