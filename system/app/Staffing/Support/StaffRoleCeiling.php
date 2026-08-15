<?php

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\User;

/**
 * Server-side D-020 role-helper ceilings for personnel commands.
 */
final class StaffRoleCeiling
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer)
    {
    }

    public function canManagePersonnel(User $actor): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::HR_STAFF_MANAGE)
            || $this->authorizer->allows($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE);
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
        'registrar_admin' => 3,
        'guidance_counselor' => 3,
        'hr_administrator' => 4,
    ];

    public function rankOf(User $user): int
    {
        return $user->roles->pluck('name')
            ->map(fn (string $role) => self::ROLE_RANKS[$role] ?? 0)
            ->max() ?? 0;
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
        if ($profile === null || $profile->office !== $actor->actorScope()->office) {
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
        if ($profile === null || $profile->office !== $actor->actorScope()->office) {
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
            return (string) ($requestedOffice ?? $actor->actorScope()->office ?? 'hr');
        }

        return (string) ($actor->actorScope()->office ?? 'hr');
    }

    /**
     * Resolve the office for an update: office actors keep the target's office;
     * HR may change it.
     */
    public function resolveUpdateOffice(User $actor, ?string $requestedOffice, ?string $currentOffice): string
    {
        if ($this->isFullHr($actor)) {
            return (string) ($requestedOffice ?? $currentOffice ?? $actor->actorScope()->office ?? 'hr');
        }

        return (string) ($currentOffice ?? $actor->actorScope()->office ?? 'hr');
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
     * Resolve role reassignment for an update: HR-only. Returns [roles, changed].
     *
     * @param  array<string, mixed>  $input
     * @return array{0: list<string>, 1: bool}
     */
    public function resolveUpdateRoles(User $actor, User $target, array $input): array
    {
        if (! $this->isFullHr($actor) || ! $this->requestWantsRoleChange($input)) {
            return [$target->roles->pluck('name')->all(), false];
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
                throw new \RuntimeException("Role helper [{$role}] is outside the actor ceiling.");
            }
        }
    }

    /**
     * Default office for a primary role helper when HR does not override.
     */
    public static function defaultOfficeForRole(string $role): string
    {
        return match ($role) {
            'hr_administrator' => 'hr',
            'registrar_admin', 'registrar_clerk', 'registrar_support_staff' => 'registrar',
            'guidance_counselor', 'guidance_clerk', 'guidance_support_staff' => 'guidance',
            'program_head' => 'program',
            default => 'hr',
        };
    }
}
