<?php

namespace App\Admission\Support;

use App\Authorization\CapabilityCatalog;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Applicant-owned capabilities (Slice 9 — applicant.choose_slot skill).
 *
 * These are NOT role-granted. They are granted directly to the applicant
 * portal user so the Capability Kernel can authorize OWN-scope skill rows
 * (exams.slots.list@1, exams.slot.reserve@1) without inventing office roles.
 * Grant is idempotent and re-applied on login for pre-existing accounts.
 */
final class ApplicantCapabilityGrants
{
    /**
     * @return list<string>
     */
    public static function capabilityKeys(): array
    {
        return [
            CapabilityCatalog::APPLICANT_EXAM_SLOTS_VIEW,
            CapabilityCatalog::APPLICANT_EXAM_SLOT_RESERVE,
            CapabilityCatalog::NOTIFY_READ_OWN,
        ];
    }

    public static function sync(User $user): void
    {
        $user->givePermissionTo(self::capabilityKeys());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
