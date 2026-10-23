<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivateStaffAccount
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
    ) {}

    public function handle(User $actor, User $target): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot activate staff of equal/higher rank or outside actor office.');
        }

        return DB::transaction(function () use ($actor, $target): User {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedTarget->id)->lockForUpdate()->first();
            $profile = $lockedTarget->staffProfile()->lockForUpdate()->first();

            if ($profile === null || $state === null) {
                throw new RuntimeException('Target is not an onboarded staff account.');
            }

            $wasInactive = ! $profile->isActive();
            $profile->update([
                'status' => StaffProfile::STATUS_ACTIVE,
                'deactivated_at' => null,
            ]);

            if ($wasInactive) {
                $this->invalidateStaffAuthentication->handle($lockedTarget, $state);
            }

            $this->recordAuditEvent->handle('hr.staff.activated', [
                'user_id' => $lockedTarget->id,
                'email' => $lockedTarget->email,
            ], (string) $actor->id);

            return $lockedTarget->fresh(['staffProfile']);
        });
    }
}
