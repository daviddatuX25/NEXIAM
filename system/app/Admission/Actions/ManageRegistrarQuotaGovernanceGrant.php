<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\RegistrarQuotaGovernanceGrant;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Permission;

final class ManageRegistrarQuotaGovernanceGrant
{
    /** @var list<string> */
    public const GRANTED_CAPABILITIES = [
        CapabilityCatalog::PROGRAM_QUOTA_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
    ];

    public function __construct(
        private readonly CapabilityAuthorizer $capabilities,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function issue(
        User $issuer,
        User $recipient,
        Program $program,
        AcademicCycle $cycle,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
    ): RegistrarQuotaGovernanceGrant {
        return $this->change('issue', $issuer, $recipient, $program, $cycle, $expectedVersion, $reason, $idempotencyKey);
    }

    public function revoke(
        User $issuer,
        User $recipient,
        Program $program,
        AcademicCycle $cycle,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
    ): RegistrarQuotaGovernanceGrant {
        return $this->change('revoke', $issuer, $recipient, $program, $cycle, $expectedVersion, $reason, $idempotencyKey);
    }

    private function change(
        string $change,
        User $issuer,
        User $recipient,
        Program $program,
        AcademicCycle $cycle,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
    ): RegistrarQuotaGovernanceGrant {
        if (! in_array($change, ['issue', 'revoke'], true)) {
            throw new InvalidArgumentException('Unsupported Registrar quota grant operation.');
        }

        if ($expectedVersion < 0 || trim($reason) === '' || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('An expected version, reason, and idempotency key are required.');
        }

        $freshIssuer = User::query()->find($issuer->getKey());
        $freshRecipient = User::query()->find($recipient->getKey());
        $freshProgram = Program::query()->find($program->getKey());
        $freshCycle = AcademicCycle::query()->find($cycle->getKey());

        if (
            $freshIssuer === null
            || $freshRecipient === null
            || $freshProgram === null
            || $freshCycle === null
            || ! $freshProgram->is_active
            || (int) $freshProgram->institution_id !== (int) $freshCycle->institution_id
        ) {
            throw new AuthorizationException('The current grant participants and program/cycle scope are not valid.');
        }

        $issuerProfile = $freshIssuer->staffProfile()->first();
        $recipientProfile = $freshRecipient->staffProfile()->first();
        $issuerScope = $freshIssuer->actorScope();

        if (
            $issuerProfile === null
            || ! $issuerProfile->isActive()
            || ! in_array($issuerProfile->office, ['admin', 'registrar', 'guidance'], true)
            || (int) $issuerProfile->institution_id !== (int) $freshProgram->institution_id
            || $issuerScope->institutionId === null
            || (int) $issuerScope->institutionId !== (int) $freshProgram->institution_id
            || (
                ! $this->capabilities->decideFresh($freshIssuer, CapabilityCatalog::SETUP_PROGRAMS_MANAGE)->allowed
                && ! $this->capabilities->decideFresh($freshIssuer, CapabilityCatalog::SETUP_INSTITUTION_MANAGE)->allowed
            )
        ) {
            throw new AuthorizationException('Only an active in-institution Setup-authorized Registrar or Guidance staff member may manage this grant.');
        }

        if (
            $recipientProfile === null
            || ! $recipientProfile->isActive()
            || $recipientProfile->office !== 'registrar'
            || (int) $recipientProfile->institution_id !== (int) $freshProgram->institution_id
        ) {
            throw new InvalidArgumentException('The grant recipient must be an active Registrar in the scoped institution.');
        }

        return DB::transaction(function () use ($change, $freshIssuer, $freshRecipient, $freshProgram, $freshCycle, $expectedVersion, $reason, $idempotencyKey): RegistrarQuotaGovernanceGrant {
            User::query()->whereKey($freshRecipient->getKey())->lockForUpdate()->firstOrFail();

            $lockedIssuerProfile = StaffProfile::query()
                ->where('user_id', $freshIssuer->getKey())
                ->lockForUpdate()
                ->first();
            $lockedRecipientProfile = StaffProfile::query()
                ->where('user_id', $freshRecipient->getKey())
                ->lockForUpdate()
                ->first();
            $lockedProgram = Program::query()->whereKey($freshProgram->getKey())->lockForUpdate()->firstOrFail();
            $lockedCycle = AcademicCycle::query()->whereKey($freshCycle->getKey())->lockForUpdate()->firstOrFail();

            $lockedIssuerScope = $freshIssuer->actorScope();

            if (
                $lockedIssuerProfile === null
                || ! $lockedIssuerProfile->isActive()
                || ! in_array($lockedIssuerProfile->office, ['admin', 'registrar', 'guidance'], true)
                || (int) $lockedIssuerProfile->institution_id !== (int) $lockedProgram->institution_id
                || $lockedIssuerScope->institutionId === null
                || (int) $lockedIssuerScope->institutionId !== (int) $lockedProgram->institution_id
                || (
                    ! $this->capabilities->decideFresh($freshIssuer, CapabilityCatalog::SETUP_PROGRAMS_MANAGE)->allowed
                    && ! $this->capabilities->decideFresh($freshIssuer, CapabilityCatalog::SETUP_INSTITUTION_MANAGE)->allowed
                )
                || $lockedRecipientProfile === null
                || ! $lockedRecipientProfile->isActive()
                || $lockedRecipientProfile->office !== 'registrar'
                || (int) $lockedRecipientProfile->institution_id !== (int) $lockedProgram->institution_id
                || ! $lockedProgram->is_active
                || (int) $lockedProgram->institution_id !== (int) $lockedCycle->institution_id
            ) {
                throw new AuthorizationException('Current Setup authority or Registrar grant scope changed before the operation committed.');
            }

            $aggregate = implode(':', [
                'registrar-quota-governance',
                $freshRecipient->getKey(),
                $freshProgram->getKey(),
                $freshCycle->getKey(),
            ]);
            $command = 'registrar.quota-governance-grant.'.$change;
            $payload = [
                'action' => $change,
                'actor_user_id' => (int) $freshIssuer->getKey(),
                'recipient_user_id' => (int) $freshRecipient->getKey(),
                'institution_id' => (int) $freshProgram->institution_id,
                'program_id' => (int) $freshProgram->getKey(),
                'academic_cycle_id' => (int) $freshCycle->getKey(),
                'expected_version' => $expectedVersion,
                'reason' => trim($reason),
                'capabilities' => self::GRANTED_CAPABILITIES,
            ];
            $operation = $this->idempotency->claim($freshIssuer, $command, $aggregate, $payload, $idempotencyKey);
            $operationPayload = $operation->payload;

            if (isset($operationPayload['grant_receipt']['grant_id'])) {
                return RegistrarQuotaGovernanceGrant::query()->findOrFail($operationPayload['grant_receipt']['grant_id']);
            }

            $current = RegistrarQuotaGovernanceGrant::query()
                ->where('recipient_user_id', $freshRecipient->getKey())
                ->where('program_id', $freshProgram->getKey())
                ->where('academic_cycle_id', $freshCycle->getKey())
                ->latestVersion()
                ->lockForUpdate()
                ->first();
            $currentVersion = (int) ($current?->version ?? 0);

            if ($currentVersion !== $expectedVersion) {
                throw new RuntimeException('Registrar quota grant version is stale.');
            }

            if ($change === 'issue' && $current?->status === RegistrarQuotaGovernanceGrant::STATUS_ACTIVE) {
                throw new RuntimeException('An active Registrar quota grant already exists for this exact scope.');
            }

            if ($change === 'revoke' && $current?->status !== RegistrarQuotaGovernanceGrant::STATUS_ACTIVE) {
                throw new RuntimeException('Only an active Registrar quota grant can be revoked.');
            }

            $status = $change === 'issue'
                ? RegistrarQuotaGovernanceGrant::STATUS_ACTIVE
                : RegistrarQuotaGovernanceGrant::STATUS_REVOKED;
            $grant = RegistrarQuotaGovernanceGrant::query()->create([
                'recipient_user_id' => $freshRecipient->getKey(),
                'institution_id' => $freshProgram->institution_id,
                'program_id' => $freshProgram->getKey(),
                'academic_cycle_id' => $freshCycle->getKey(),
                'version' => $currentVersion + 1,
                'status' => $status,
                'actor_user_id' => $freshIssuer->getKey(),
                'reason' => trim($reason),
                'predecessor_id' => $current?->getKey(),
                'operation_id' => $operation->getKey(),
                'receipt' => [
                    ...$payload,
                    'version' => $currentVersion + 1,
                    'predecessor_id' => $current?->getKey(),
                    'actor_user_id' => (int) $freshIssuer->getKey(),
                    'status' => $status,
                ],
            ]);

            if ($change === 'issue') {
                foreach (self::GRANTED_CAPABILITIES as $capability) {
                    Permission::findOrCreate($capability, 'web');
                    $freshRecipient->givePermissionTo($capability);
                }
            }

            $auditEvent = $this->recordAuditEvent->handle('registrar.quota_governance_grant_'.$change, [
                'grant_id' => $grant->getKey(),
                'recipient_user_id' => $freshRecipient->getKey(),
                'institution_id' => $freshProgram->institution_id,
                'program_id' => $freshProgram->getKey(),
                'academic_cycle_id' => $freshCycle->getKey(),
                'version' => $grant->version,
                'status' => $status,
                'reason' => trim($reason),
                'operation_id' => $operation->getKey(),
                'capabilities' => self::GRANTED_CAPABILITIES,
            ], (string) $freshIssuer->getKey());

            $grant->forceFill(['audit_event_id' => $auditEvent->getKey()])->save();

            $operation->forceFill([
                'payload' => [
                    ...$operationPayload,
                    'grant_receipt' => [
                        'grant_id' => $grant->getKey(),
                        'version' => $grant->version,
                        'status' => $status,
                        'audit_event_id' => $auditEvent->getKey(),
                        'operation_id' => $operation->getKey(),
                    ],
                ],
            ])->save();

            return $grant->refresh();
        });
    }
}
