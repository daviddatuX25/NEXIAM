<?php

namespace App\Staffing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateStaffAccount
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<string>  $roles
     * @param  list<string>|null  $programCodes
     */
    public function handle(
        User $actor,
        string $name,
        ?string $email,
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

        $allowed = $this->ceiling->assignableRoleHelpers($actor);
        $support = StaffRoleCeiling::supportRoleForOffice($office);
        if ($support !== null) {
            $allowed[] = $support;
        }
        foreach ($roles as $role) {
            if (! in_array($role, $allowed, true)) {
                throw new RuntimeException("Role helper [{$role}] is outside the actor ceiling.");
            }
        }

        if ($institutionId < 1) {
            throw new RuntimeException('Institution is required.');
        }

        if (! $this->ceiling->isFullHr($actor)) {
            $actorOffice = $actor->actorScope()->office;
            if ($office !== $actorOffice) {
                throw new RuntimeException('Office support staff must be created in the actor office.');
            }
        }

        $programCodes = $office === 'program' ? ($programCodes ?? []) : [];
        $departmentId = null;

        return DB::transaction(function () use ($actor, $name, $email, $roles, $office, $institutionId, $programCodes, $departmentId, $activeCycleCode): User {
            $canonicalEmail = is_string($email) ? mb_strtolower(trim($email)) : null;
            if ($canonicalEmail !== null && User::query()->whereRaw('LOWER(email) = ?', [$canonicalEmail])->lockForUpdate()->exists()) {
                throw new RuntimeException('That email address is already in use.');
            }
            $user = User::query()->create([
                'name' => $name,
                'email' => $canonicalEmail,
                'password' => Str::random(64),
            ]);

            StaffProfile::query()->create([
                'user_id' => $user->id,
                'institution_id' => $institutionId,
                'department_id' => $departmentId,
                'office' => $office,
                'program_codes' => $programCodes,
                'active_cycle_code' => $activeCycleCode ?? $actor->actorScope()->activeCycleCode,
                'status' => StaffProfile::STATUS_ACTIVE,
                'deactivated_at' => null,
            ]);

            StaffAuthenticationState::query()->create([
                'user_id' => $user->id,
                'activation_completed_at' => null,
                'session_version' => 1,
                'two_factor_generation' => (string) Str::uuid(),
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
