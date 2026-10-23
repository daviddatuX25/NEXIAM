<?php

namespace App\Authorization\Actions;

use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reconciles the D-087 baseline program.quota.manage capability strictly for eligible active Program Head and College Dean staff profiles.
 * Preserves explicit direct permissions and revocations. Never performs a blanket role sync or password/assignment change.
 */
final class ReconcileProgramQuotaGrants
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @return array{granted_count: int, preserved_count: int, affected_users: list<int>}
     */
    public function handle(?User $operator = null): array
    {
        Permission::findOrCreate(CapabilityCatalog::PROGRAM_QUOTA_MANAGE, 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::transaction(function () use ($operator): array {
            $eligibleProfiles = StaffProfile::query()
                ->with('user.permissions')
                ->where('office', 'program')
                ->where(fn ($query) => $query->where('status', StaffProfile::STATUS_ACTIVE)->orWhereNull('status'))
                ->whereHas('user')
                ->get();

            $grantedCount = 0;
            $preservedCount = 0;
            $affectedUsers = [];

            foreach ($eligibleProfiles as $profile) {
                $user = $profile->user;
                if ($user === null) {
                    continue;
                }

                // If already directly granted, preserve without altering
                if ($user->hasDirectPermission(CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
                    $preservedCount++;

                    continue;
                }

                $user->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
                $grantedCount++;
                $affectedUsers[] = (int) $user->getKey();

                $this->audit->handle('authorization.capability_granted_reconciled', [
                    'user_id' => $user->getKey(),
                    'capability' => CapabilityCatalog::PROGRAM_QUOTA_MANAGE,
                    'staff_profile_id' => $profile->getKey(),
                    'office' => $profile->office,
                    'reason' => 'Targeted D-087 quota-manage baseline grant reconciliation.',
                ], $operator ? (string) $operator->getKey() : 'system');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'granted_count' => $grantedCount,
                'preserved_count' => $preservedCount,
                'affected_users' => $affectedUsers,
            ];
        });
    }
}
