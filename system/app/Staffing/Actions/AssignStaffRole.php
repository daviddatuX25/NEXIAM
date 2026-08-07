<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignStaffRole
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

        $this->ceiling->assertRolesAllowed($actor, [$role]);

        if ($target->staffProfile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->isFullHr($actor) && $target->staffProfile->office !== $actor->actorScope()->office) {
            throw new RuntimeException('Cannot assign roles outside actor office.');
        }

        return DB::transaction(function () use ($actor, $target, $role): User {
            $target->assignRole($role);

            $this->recordAuditEvent->handle('hr.staff.role_assigned', [
                'user_id' => $target->id,
                'role' => $role,
            ], (string) $actor->id);

            return $target->fresh(['roles']);
        });
    }
}
