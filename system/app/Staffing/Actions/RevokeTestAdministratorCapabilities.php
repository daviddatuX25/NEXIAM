<?php

namespace App\Staffing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\EligibleTaRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class RevokeTestAdministratorCapabilities
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly EligibleTaRecipients $eligible,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target): User
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_TA_DELEGATE)) {
            throw new RuntimeException('ta_delegate_denied');
        }

        if (! $this->eligible->isEligible($target) || ! $this->eligible->canDelegate($actor, $target)) {
            throw new RuntimeException('ta_recipient_ineligible');
        }

        return DB::transaction(function () use ($actor, $target): User {
            $freshActor = User::query()->find($actor->getKey());
            if ($freshActor === null || ! $this->authorizer->allows($freshActor, CapabilityCatalog::GUIDANCE_TA_DELEGATE)) {
                throw new RuntimeException('ta_delegate_denied');
            }

            $freshTarget = User::query()
                ->with(['roles', 'permissions', 'staffProfile'])
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! $this->eligible->isEligible($freshTarget) || ! $this->eligible->canDelegate($freshActor, $freshTarget)) {
                throw new RuntimeException('ta_recipient_ineligible');
            }
            if (! $this->eligible->hasDelegatedTa($freshTarget) && ! $this->eligible->hasLegacyDelegatedTa($freshTarget)) {
                throw new RuntimeException('ta_not_designated');
            }

            $caps = CapabilityCatalog::delegatedTestAdministratorCapabilities();
            $freshTarget->revokePermissionTo($caps);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->recordAuditEvent->handle('staff.ta_capabilities_revoked', [
                'user_id' => $freshTarget->id,
                'capabilities' => $caps,
            ], (string) $freshActor->id);

            return $freshTarget->fresh(['roles', 'permissions', 'staffProfile']) ?? $freshTarget;
        });
    }
}
