<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Staffing\InstitutionalSetupDelegation;
use App\Models\User;
use App\Staffing\Support\EligibleSetupRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class RevokeInstitutionalSetupDelegation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target, string $reason = 'Institutional setup delegation revoked.'): User
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('setup_revoke_reason_required');
        }

        if ($actor->id === $target->id) {
            throw new RuntimeException('setup_delegate_self_denied');
        }

        return DB::transaction(function () use ($actor, $target, $reason): User {
            $freshActor = User::query()
                ->with(['roles', 'permissions', 'staffProfile'])
                ->whereKey($actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $institutionId = $freshActor->actorScope()->institutionId;

            if ($institutionId === null || ! $this->authorizer->decideFresh(
                $freshActor,
                CapabilityCatalog::SETUP_INSTITUTION_DELEGATE,
                ['institution_id' => $institutionId],
            )->allowed) {
                throw new RuntimeException('setup_delegate_denied');
            }

            $freshTarget = User::query()
                ->with(['roles', 'permissions', 'staffProfile'])
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) ($freshTarget->staffProfile?->institution_id ?? 0) !== (int) $institutionId) {
                throw new RuntimeException('setup_recipient_ineligible');
            }

            $this->revokeLocked($freshTarget, $freshActor, $reason, 'staff.setup_delegation_revoked');

            return $freshTarget->fresh(['roles', 'permissions', 'staffProfile']) ?? $freshTarget;
        });
    }

    public function revokeForLifecycleChange(User $target, User $actor, string $systemReason): void
    {
        DB::transaction(function () use ($target, $actor, $systemReason): void {
            $freshTarget = User::query()
                ->with(['roles', 'permissions', 'staffProfile'])
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->revokeLocked($freshTarget, $actor, $systemReason, 'staff.setup_delegation_revoked_lifecycle');
        });
    }

    private function revokeLocked(User $freshTarget, User $actor, string $reason, string $auditEvent): void
    {
        $activeDelegations = InstitutionalSetupDelegation::query()
            ->where('recipient_id', $freshTarget->id)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->get();

        $hasDirectSetupPermission = false;
        foreach (EligibleSetupRecipients::DELEGATED_CAPABILITIES as $capability) {
            if ($freshTarget->hasDirectPermission($capability)) {
                $hasDirectSetupPermission = true;
                break;
            }
        }

        if ($activeDelegations->isEmpty() && ! $hasDirectSetupPermission) {
            return;
        }

        foreach ($activeDelegations as $delegation) {
            $delegation->forceFill([
                'revoked_at' => now(),
                'revoked_by_id' => $actor->id,
                'revocation_reason' => $reason,
            ])->save();
        }

        if (! $freshTarget->hasRole('institutional_admin')) {
            $freshTarget->revokePermissionTo(EligibleSetupRecipients::DELEGATED_CAPABILITIES);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->recordAuditEvent->handle($auditEvent, [
            'user_id' => $freshTarget->id,
            'institution_id' => $freshTarget->staffProfile?->institution_id,
            'delegation_ids' => $activeDelegations->modelKeys(),
            'scopes' => $activeDelegations->flatMap(fn (InstitutionalSetupDelegation $delegation): array => $delegation->scopes ?? [])
                ->unique()
                ->values()
                ->all(),
            'capabilities' => EligibleSetupRecipients::DELEGATED_CAPABILITIES,
            'reason' => $reason,
        ], (string) $actor->id);
    }
}
