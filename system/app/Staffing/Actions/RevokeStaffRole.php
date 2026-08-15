<?php

namespace App\Staffing\Actions;

use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RevokeStaffRole
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(User $actor, User $target, string $role): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if ($target->id === $actor->id && $role === 'hr_administrator') {
            throw new RuntimeException('Cannot revoke your own HR role helper.');
        }

        if ($target->staffProfile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot revoke roles from staff of equal/higher rank or outside actor office.');
        }

        // Only revoke helpers the actor could assign (ceiling).
        $this->ceiling->assertRolesAllowed($actor, [$role]);

        return DB::transaction(function () use ($actor, $target, $role): User {
            if ($role === 'hr_administrator') {
                $remainingHr = User::role('hr_administrator')
                    ->whereHas('staffProfile', fn ($q) => $q->where('status', 'active'))
                    ->where('id', '!=', $target->id)
                    ->count();
                if ($remainingHr < 1) {
                    throw new RuntimeException('Cannot revoke the last active HR administrator.');
                }
            }

            $target->removeRole($role);

            if ($target->roles()->count() < 1) {
                throw new RuntimeException('Staff must retain at least one role helper.');
            }

            $this->recordAuditEvent->handle('hr.staff.role_revoked', [
                'user_id' => $target->id,
                'role' => $role,
                'capability_ref' => CapabilityCatalog::HR_STAFF_MANAGE,
            ], (string) $actor->id);

            return $target->fresh(['roles']);
        });
    }
}
