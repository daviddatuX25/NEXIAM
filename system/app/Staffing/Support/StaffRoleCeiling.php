<?php

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\User;
use RuntimeException;

/**
 * Server-side D-020 role-helper ceilings for personnel commands.
 */
final class StaffRoleCeiling
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function canManagePersonnel(User $actor): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::HR_STAFF_MANAGE)
            || $this->authorizer->allows($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE)
            || $this->isDean($actor);
    }

    public function isFullHr(User $actor): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::HR_STAFF_MANAGE);
    }

    /**
     * @return list<string>
     */
    public function assignableRoleHelpers(User $actor): array
    {
        if ($this->isFullHr($actor)) {
            return $this->permanentRoleHelpers();
        }

        if (! $this->authorizer->allows($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE)) {
            return [];
        }

        $support = self::supportRoleForOffice($actor->actorScope()->office ?? '');

        return $support === null ? [] : [$support];
    }

    /**
     * Permanent role helpers (no office support roles — those are office-derived).
     *
     * @return list<string>
     */
    public function permanentRoleHelpers(): array
    {
        return array_values(array_diff(CapabilityCatalog::personnelRoleHelpers(), [
            'registrar_support_staff',
            'guidance_support_staff',
        ]));
    }

    /**
     * The natural support role for an office (founder rule: derived, not picked).
     */
    public static function supportRoleForOffice(string $office): ?string
    {
        return match ($office) {
            'registrar' => 'registrar_support_staff',
            'guidance' => 'guidance_support_staff',
            default => null,
        };
    }

    public function isSupportRole(string $role): bool
    {
        return in_array($role, ['registrar_support_staff', 'guidance_support_staff'], true);
    }

    /**
     * Office rank for hierarchy enforcement (founder lock 2026-08-10):
     * support (1) < clerk (2) < office lead (3) < HR (4).
     *
     * @var array<string, int>
     */
    public const ROLE_RANKS = [
        'registrar_support_staff' => 1,
        'guidance_support_staff' => 1,
        'registrar_clerk' => 2,
        'guidance_clerk' => 2,
        'program_head' => 2,
        'registrar_admin' => 3,
        'guidance_counselor' => 3,
        'dean' => 3,
        'institutional_admin' => 4,
        'hr_administrator' => 4,
    ];

    public function isDean(User $user): bool
    {
        return Department::query()
            ->where('dean_user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function rankOf(User $user): int
    {
        $roleRank = $user->roles->pluck('name')
            ->map(fn (string $role) => self::ROLE_RANKS[$role] ?? 0)
            ->max() ?? 0;

        if ($this->isDean($user)) {
            $roleRank = max($roleRank, 3);
        }

        return $roleRank;
    }

    public function isOfficeHead(User $actor): bool
    {
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        if ($institutionId === 0) {
            return false;
        }

        $canManageHr = $this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed;
        $canManageOffice = $this->authorizer->decideFresh($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed;

        return $canManageHr
            || $this->isDean($actor)
            || ($canManageOffice && $this->rankOf($actor) >= 3)
            || $actor->hasRole('institutional_admin');
    }

    public function canManageMfa(User $actor, User $target): bool
    {
        $targetInstitutionId = (int) ($target->staffProfile?->institution_id ?? 0);
        if (
            $targetInstitutionId === 0
            || (int) ($actor->actorScope()->institutionId ?? 0) !== $targetInstitutionId
            || ! $this->isOfficeHead($actor)
        ) {
            return false;
        }

        if ($actor->id === $target->id) {
            return true;
        }

        $canManage = $this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $targetInstitutionId])->allowed
            || $this->authorizer->decideFresh($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, ['institution_id' => $targetInstitutionId])->allowed
            || $this->isDean($actor);

        return $canManage && $this->canManageUser($actor, $target);
    }

    /**
     * Hierarchy gate for office actors (founder lock 2026-08-10):
     * an actor may manage only strictly-lower ranks inside their own office.
     * HR may manage everyone. Peers and higher ranks are out of reach, and
     * self-management is a profile-page concern, not a directory action.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($this->isFullHr($actor)) {
            return true;
        }

        if (! $this->canManagePersonnel($actor)) {
            return false;
        }

        $profile = $target->staffProfile;
        if ($profile === null) {
            return false;
        }

        if ($this->isDean($actor) && $profile->office === 'program') {
            $deanDepartmentIds = Department::query()
                ->where('dean_user_id', $actor->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            $targetInDepartment = in_array($profile->department_id, $deanDepartmentIds, true)
                || Program::query()
                    ->whereIn('department_id', $deanDepartmentIds)
                    ->whereIn('code', $profile->program_codes ?? [])
                    ->exists();

            if ($targetInDepartment) {
                return $this->rankOf($actor) > $this->rankOf($target);
            }
        }

        if ($profile->office !== $actor->actorScope()->office) {
            return false;
        }

        return $this->rankOf($actor) > $this->rankOf($target);
    }

    /**
     * Password reset gate (founder lock 2026-08-10): HR may reset anyone's
     * password; office leads may reset passwords for their own office staff
     * (strictly lower rank). Clerks/support never reset another account's
     * password — self-service password change is a profile-page feature.
     */
    public function canResetPassword(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($this->isFullHr($actor)) {
            return true;
        }

        if ($this->rankOf($actor) < 3) {
            return false;
        }

        $profile = $target->staffProfile;
        if ($profile === null) {
            return false;
        }

        if ($this->isDean($actor) && $profile->office === 'program') {
            $deanDepartmentIds = Department::query()
                ->where('dean_user_id', $actor->id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            $targetInDepartment = in_array($profile->department_id, $deanDepartmentIds, true)
                || Program::query()
                    ->whereIn('department_id', $deanDepartmentIds)
                    ->whereIn('code', $profile->program_codes ?? [])
                    ->exists();

            if ($targetInDepartment) {
                return $this->rankOf($actor) > $this->rankOf($target);
            }
        }

        if ($profile->office !== $actor->actorScope()->office) {
            return false;
        }

        return $this->rankOf($actor) > $this->rankOf($target);
    }

    /**
     * Resolve the office for a create: office actors are locked to their own
     * office; HR may choose any office.
     */
    public function resolveCreateOffice(User $actor, ?string $requestedOffice): string
    {
        if ($this->isFullHr($actor)) {
            return (string) ($requestedOffice ?? $actor->actorScope()->office ?? 'admin');
        }

        return (string) ($actor->actorScope()->office ?? 'admin');
    }

    /**
     * Resolve the office for an update: office actors keep the target's office;
     * HR may change it.
     */
    public function resolveUpdateOffice(User $actor, ?string $requestedOffice, ?string $currentOffice): string
    {
        if ($this->isFullHr($actor)) {
            return (string) ($requestedOffice ?? $currentOffice ?? $actor->actorScope()->office ?? 'admin');
        }

        return (string) ($currentOffice ?? $actor->actorScope()->office ?? 'admin');
    }

    /**
     * Resolve the role helpers for a create. Support roles are office-derived
     * (never picked); permanent roles only from HR's checklist.
     *
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    public function resolveCreateRoles(User $actor, array $input, string $office): array
    {
        if (! $this->isFullHr($actor)) {
            $support = self::supportRoleForOffice($office);

            return $support === null ? [] : [$support];
        }

        $type = $input['account_type'] ?? 'permanent';

        if ($type === 'support') {
            $support = self::supportRoleForOffice($office);

            return $support === null ? [] : [$support];
        }

        $roles = $input['roles'] ?? [];

        return array_values(array_filter($roles, fn ($role) => is_string($role) && $role !== ''));
    }

    /**
     * Resolve permanent-role changes or the office-derived support role for an
     * update. Returns [roles, changed].
     *
     * @param  array<string, mixed>  $input
     * @return array{0: list<string>, 1: bool}
     */
    public function resolveUpdateRoles(User $actor, User $target, array $input, ?string $office = null): array
    {
        $currentRoles = $target->roles->pluck('name')->all();
        $currentSupportRole = collect($currentRoles)->first(fn (string $role): bool => $this->isSupportRole($role));

        if ($currentSupportRole !== null) {
            if (! $this->isFullHr($actor) || $office === null || $office === $target->staffProfile?->office) {
                return [$currentRoles, false];
            }

            $derivedSupportRole = self::supportRoleForOffice($office);
            if ($derivedSupportRole === null) {
                throw new RuntimeException('Support staff must remain in the Registrar or Guidance office.');
            }

            return [[$derivedSupportRole], $currentRoles !== [$derivedSupportRole]];
        }

        if (! $this->isFullHr($actor) || ! $this->requestWantsRoleChange($input)) {
            return [$currentRoles, false];
        }

        $roles = $input['roles'] ?? [];
        $roles = array_values(array_filter($roles, fn ($role) => is_string($role) && $role !== ''));

        if ($roles === []) {
            throw new RuntimeException('Staff must retain at least one role helper.');
        }

        return [$roles, true];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requestWantsRoleChange(array $input): bool
    {
        return array_key_exists('roles', $input);
    }

    /**
     * @param  list<string>  $roles
     */
    public function assertRolesAllowed(User $actor, array $roles): void
    {
        $allowed = $this->assignableRoleHelpers($actor);
        foreach ($roles as $role) {
            if (! in_array($role, $allowed, true)) {
                throw new RuntimeException("Role helper [{$role}] is outside the actor ceiling.");
            }
        }
    }

    /**
     * Default office for a primary role helper when HR does not override.
     */
    public static function defaultOfficeForRole(string $role): string
    {
        return match ($role) {
            'hr_administrator' => 'admin',
            'registrar_admin', 'registrar_clerk', 'registrar_support_staff' => 'registrar',
            'guidance_counselor', 'guidance_clerk', 'guidance_support_staff' => 'guidance',
            'program_head' => 'program',
            default => 'admin',
        };
    }
}
