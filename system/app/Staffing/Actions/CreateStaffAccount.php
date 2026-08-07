<?php

namespace App\Staffing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateStaffAccount
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>|null  $programCodes
     */
    public function handle(
        User $actor,
        string $name,
        string $email,
        string $password,
        array $roles,
        string $office,
        int $institutionId,
        ?array $programCodes = null,
        ?string $activeCycleCode = null,
    ): User {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if ($roles === []) {
            throw new RuntimeException('At least one role helper is required.');
        }

        $this->ceiling->assertRolesAllowed($actor, $roles);

        if ($institutionId < 1) {
            throw new RuntimeException('Institution is required.');
        }

        if (! $this->ceiling->isFullHr($actor)) {
            $actorOffice = $actor->actorScope()->office;
            if ($office !== $actorOffice) {
                throw new RuntimeException('Office support staff must be created in the actor office.');
            }
        }

        return DB::transaction(function () use ($actor, $name, $email, $password, $roles, $office, $institutionId, $programCodes, $activeCycleCode): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            StaffProfile::query()->create([
                'user_id' => $user->id,
                'institution_id' => $institutionId,
                'office' => $office,
                'program_codes' => $programCodes ?? [],
                'active_cycle_code' => $activeCycleCode ?? $actor->actorScope()->activeCycleCode,
                'status' => StaffProfile::STATUS_ACTIVE,
                'deactivated_at' => null,
            ]);

            $user->syncRoles($roles);

            $this->recordAuditEvent->handle('hr.staff.created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $roles,
                'office' => $office,
                'by_hr' => $this->authorizer->allows($actor, CapabilityCatalog::HR_STAFF_MANAGE),
            ], (string) $actor->id);

            return $user->fresh(['staffProfile', 'roles']);
        });
    }
}
