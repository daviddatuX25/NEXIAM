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

class GrantTestAdministratorCapabilities
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

        if ($actor->id === $target->id) {
            throw new RuntimeException('ta_delegate_self_denied');
        }

        if (! $this->eligible->isEligible($target)) {
            throw new RuntimeException('ta_recipient_ineligible');
        }

        if ($this->eligible->hasDelegatedTa($target)) {
            return $target->fresh(['roles', 'permissions', 'staffProfile']) ?? $target;
        }

        return DB::transaction(function () use ($actor, $target): User {
            $caps = CapabilityCatalog::testAdministratorCapabilities();
            $target->givePermissionTo($caps);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->recordAuditEvent->handle('staff.ta_capabilities_granted', [
                'user_id' => $target->id,
                'capabilities' => $caps,
            ], (string) $actor->id);

            return $target->fresh(['roles', 'permissions', 'staffProfile']) ?? $target;
        });
    }
}
