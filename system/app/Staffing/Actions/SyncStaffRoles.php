<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sync the full role-helper set for a staff account within the actor ceiling.
 */
class SyncStaffRoles
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $actor, User $target, array $roles): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if ($roles === []) {
            throw new RuntimeException('Staff must retain at least one role helper.');
        }

        $this->ceiling->assertRolesAllowed($actor, $roles);

        if ($target->staffProfile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->isFullHr($actor) && $target->staffProfile->office !== $actor->actorScope()->office) {
            throw new RuntimeException('Cannot sync roles outside actor office.');
        }

        if ($target->id === $actor->id && $target->hasRole('hr_administrator') && ! in_array('hr_administrator', $roles, true)) {
            throw new RuntimeException('Cannot revoke your own HR role helper.');
        }

        return DB::transaction(function () use ($actor, $target, $roles): User {
            $hadHr = $target->hasRole('hr_administrator');
            $keepsHr = in_array('hr_administrator', $roles, true);
            if ($hadHr && ! $keepsHr) {
                $remainingHr = User::role('hr_administrator')
                    ->whereHas('staffProfile', fn ($q) => $q->where('status', 'active'))
                    ->where('id', '!=', $target->id)
                    ->count();
                if ($remainingHr < 1) {
                    throw new RuntimeException('Cannot revoke the last active HR administrator.');
                }
            }

            $target->syncRoles($roles);

            $this->recordAuditEvent->handle('hr.staff.roles_synced', [
                'user_id' => $target->id,
                'roles' => $roles,
            ], (string) $actor->id);

            return $target->fresh(['roles']);
        });
    }
}
