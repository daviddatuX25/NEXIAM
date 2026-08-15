<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateStaffAssignment
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  list<string>|null  $programCodes
     */
    public function handle(
        User $actor,
        User $target,
        string $name,
        string $email,
        ?string $password,
        string $office,
        ?array $programCodes = null,
        ?string $activeCycleCode = null,
    ): User {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        $profile = $target->staffProfile;
        if ($profile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot update staff of equal/higher rank or outside actor office.');
        }

        if ($password !== null && $password !== '' && ! $this->ceiling->canResetPassword($actor, $target)) {
            throw new RuntimeException('Only HR or the office lead may reset this account password.');
        }

        return DB::transaction(function () use ($actor, $target, $profile, $name, $email, $password, $office, $programCodes, $activeCycleCode): User {
            $target->name = $name;
            $target->email = $email;
            if ($password !== null && $password !== '') {
                $target->password = $password;
            }
            $target->save();

            $profile->update([
                'office' => $this->ceiling->isFullHr($actor) ? $office : $profile->office,
                'program_codes' => $programCodes ?? $profile->program_codes,
                'active_cycle_code' => $activeCycleCode ?? $profile->active_cycle_code,
            ]);

            $this->recordAuditEvent->handle('hr.staff.updated', [
                'user_id' => $target->id,
                'email' => $target->email,
                'office' => $profile->fresh()->office,
            ], (string) $actor->id);

            return $target->fresh(['staffProfile', 'roles']);
        });
    }
}
