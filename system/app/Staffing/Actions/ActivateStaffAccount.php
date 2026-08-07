<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
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
    ) {
    }

    public function handle(User $actor, User $target): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        $profile = $target->staffProfile;
        if ($profile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->isFullHr($actor) && $profile->office !== $actor->actorScope()->office) {
            throw new RuntimeException('Cannot activate staff outside actor office.');
        }

        return DB::transaction(function () use ($actor, $target, $profile): User {
            $profile->update([
                'status' => StaffProfile::STATUS_ACTIVE,
                'deactivated_at' => null,
            ]);

            $this->recordAuditEvent->handle('hr.staff.activated', [
                'user_id' => $target->id,
                'email' => $target->email,
            ], (string) $actor->id);

            return $target->fresh(['staffProfile']);
        });
    }
}
