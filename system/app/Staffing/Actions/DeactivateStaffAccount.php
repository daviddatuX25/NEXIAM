<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeactivateStaffAccount
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(User $actor, User $target): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if ($target->id === $actor->id) {
            throw new RuntimeException('Cannot deactivate your own account.');
        }

        $profile = $target->staffProfile;
        if ($profile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot deactivate staff of equal/higher rank or outside actor office.');
        }

        if ($target->hasRole('hr_administrator')) {
            $remainingHr = User::role('hr_administrator')
                ->whereHas('staffProfile', fn ($q) => $q->where('status', StaffProfile::STATUS_ACTIVE))
                ->where('id', '!=', $target->id)
                ->count();
            if ($remainingHr < 1) {
                throw new RuntimeException('Cannot deactivate the last active HR administrator.');
            }
        }

        return DB::transaction(function () use ($actor, $target, $profile): User {
            $profile->update([
                'status' => StaffProfile::STATUS_INACTIVE,
                'deactivated_at' => now(),
            ]);

            $this->recordAuditEvent->handle('hr.staff.deactivated', [
                'user_id' => $target->id,
                'email' => $target->email,
            ], (string) $actor->id);

            return $target->fresh(['staffProfile']);
        });
    }
}
