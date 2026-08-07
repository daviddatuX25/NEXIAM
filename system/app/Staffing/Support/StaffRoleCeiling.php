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
            return CapabilityCatalog::personnelRoleHelpers();
        }

        if (! $this->authorizer->allows($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE)) {
            return [];
        }

        $office = $actor->actorScope()->office;

        return match ($office) {
            'registrar' => ['registrar_support_staff'],
            'guidance' => ['guidance_support_staff'],
            default => [],
        };
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
