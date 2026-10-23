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

class GrantInstitutionalSetupDelegation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly EligibleSetupRecipients $eligible,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        User $actor,
        User $target,
        string $reason = 'Institutional setup delegation granted.',
        ?int $academicCycleId = null,
        array $scopes = ['cycles', 'rooms', 'programs'],
    ): InstitutionalSetupDelegation {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('setup_delegate_reason_required');
        }

        $normalizedScopes = $this->eligible->normalizeScopes($scopes);
        $capabilities = $this->eligible->capabilitiesForScopes($normalizedScopes);
        if ($normalizedScopes === ['cycles', 'rooms', 'programs']) {
            $capabilities[] = CapabilityCatalog::SETUP_INSTITUTION_MANAGE;
        }

        if ($actor->id === $target->id) {
            throw new RuntimeException('setup_delegate_self_denied');
        }

        return DB::transaction(function () use ($actor, $target, $reason, $academicCycleId, $normalizedScopes, $capabilities): InstitutionalSetupDelegation {
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
            if (! $this->eligible->isEligible($freshTarget, $institutionId)) {
                throw new RuntimeException('setup_recipient_ineligible');
            }

            $existing = InstitutionalSetupDelegation::query()
                ->where('recipient_id', $freshTarget->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            $existingScopes = $existing !== null && is_array($existing->scopes)
                ? $this->eligible->normalizeScopes($existing->scopes)
                : [];

            if ($existing !== null && $existingScopes === $normalizedScopes) {
                $deselectedCapabilities = array_values(array_diff(EligibleSetupRecipients::DELEGATED_CAPABILITIES, $capabilities));
                if ($deselectedCapabilities !== []) {
                    $freshTarget->revokePermissionTo($deselectedCapabilities);
                }
                $freshTarget->givePermissionTo($capabilities);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return $existing;
            }

            if ($existing !== null) {
                $existing->forceFill([
                    'revoked_at' => now(),
                    'revoked_by_id' => $freshActor->id,
                    'revocation_reason' => 'Setup delegation replaced with a different scope set.',
                ])->save();
            }
            $deselectedCapabilities = array_values(array_diff(EligibleSetupRecipients::DELEGATED_CAPABILITIES, $capabilities));
            if ($deselectedCapabilities !== []) {
                $freshTarget->revokePermissionTo($deselectedCapabilities);
            }

            $freshTarget->givePermissionTo($capabilities);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $delegation = InstitutionalSetupDelegation::query()->create([
                'institution_id' => $institutionId,
                'actor_id' => $freshActor->id,
                'recipient_id' => $freshTarget->id,
                'academic_cycle_id' => $academicCycleId,
                'scopes' => $normalizedScopes,
                'reason' => $reason,
                'granted_at' => now(),
            ]);

            $this->recordAuditEvent->handle('staff.setup_delegation_granted', [
                'user_id' => $freshTarget->id,
                'institution_id' => $institutionId,
                'delegation_id' => $delegation->id,
                'previous_scopes' => $existingScopes,
                'replaced_delegation_id' => $existing?->id,
                'scopes' => $normalizedScopes,
                'capabilities' => $capabilities,
                'reason' => $reason,
            ], (string) $freshActor->id);

            return $delegation;
        });
    }
}
