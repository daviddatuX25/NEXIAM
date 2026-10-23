<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\Staffing\StaffAccountOperationReceipt;
use App\Models\User;
use App\Staffing\Support\StaffTargetManagementScope;
use App\Staffing\Support\UsableHrAdministratorPool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

final class ResetStaffAccountForSetup
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly InvalidateStaffAuthentication $invalidate,
        private readonly RecordAuditEvent $audit,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly UsableHrAdministratorPool $hrAdministratorPool,
    ) {}

    /** @param list<User> $targets @param array<string, mixed> $input @return list<array{user_id:int,outcome:string,message:string}> */
    public function handle(?User $operator, array $targets, array $input, Request $request): array
    {
        if (! $operator) {
            throw ValidationException::withMessages(['targets' => 'An authenticated personnel operator is required.']);
        }
        $current = User::query()->whereKey($operator->id)->with(['roles', 'staffProfile', 'authenticationState'])->firstOrFail();
        $institutionId = (int) ($current->staffProfile?->institution_id ?? 0);
        if (! $this->targetScope->actorIsActiveInInstitution($current, $institutionId) || ! $this->canManagePersonnelFresh($current, $institutionId)) {
            throw ValidationException::withMessages(['targets' => 'Only currently authorized personnel managers may perform a full setup reset.']);
        }
        $session = $request->hasSession() ? $request->session() : session();
        $state = $current->authenticationState;
        if (! $state || ! Hash::check((string) ($input['current_password'] ?? ''), (string) $current->password)
            || ! $state->activation_completed_at || ! $state->password_set_at || $state->must_change_password
            || ! $state->hasConfirmedTwoFactor() || ! is_string($state->two_factor_secret)
            || (int) $session->get('auth.session_version', 0) !== (int) $state->session_version) {
            throw ValidationException::withMessages(['current_password' => 'Fresh password and enrolled authenticator proof are required.']);
        }
        $targetIds = collect($targets)->map(fn (User $target) => (int) $target->id)->sort()->values()->all();
        $batchBinding = hash('sha256', json_encode([
            'targets' => $targetIds,
            'operations' => $input['operation_ids'] ?? [],
            'generations' => $input['expected_generations'] ?? [],
            'reason' => $input['reason'] ?? '',
        ], JSON_THROW_ON_ERROR));
        $storedProof = $session->get('staff.full_reset.proof');
        $proofIsCurrent = is_array($storedProof)
            && ($storedProof['actor_id'] ?? null) === $current->id
            && ($storedProof['session_id'] ?? null) === $session->getId()
            && ($storedProof['binding'] ?? null) === $batchBinding
            && ($storedProof['factor_generation'] ?? null) === $state->two_factor_generation
            && ($storedProof['recovery_generation'] ?? null) === $state->recovery_generation
            && ($storedProof['password_set_at'] ?? null) === $state->password_set_at?->toIso8601String()
            && (int) ($storedProof['session_version'] ?? 0) === (int) $state->session_version
            && ($storedProof['created_at'] ?? 0) >= now()->subMinutes(5)->timestamp;
        if (! $proofIsCurrent) {
            $this->consumeProofStep(
                $current,
                (string) $input['current_password'],
                (string) ($input['totp_code'] ?? ''),
                $session,
                $batchBinding,
                $institutionId,
                (int) $state->session_version,
                (string) $state->two_factor_generation,
                $state->recovery_generation,
                $state->password_set_at?->toIso8601String() ?? '',
            );
        }
        $proof = $session->get('staff.full_reset.proof');
        $results = [];
        foreach ($targets as $target) {
            $results[] = $this->resetOne($current, $target, $input, $institutionId, is_array($proof) ? $proof : [], $session, $batchBinding);
        }

        return $results;
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $proof @return array{user_id:int,outcome:string,message:string} */
    private function resetOne(User $operator, User $target, array $input, int $institutionId, array $proof, mixed $session, string $batchBinding): array
    {
        $userId = (int) $target->id;
        try {
            $result = DB::transaction(function () use ($operator, $userId, $input, $institutionId, $proof, $session, $batchBinding): array {
                Institution::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();
                $lockedOperator = User::query()->whereKey($operator->id)->lockForUpdate()->with(['roles', 'staffProfile'])->firstOrFail();
                $operatorState = StaffAuthenticationState::query()->where('user_id', $lockedOperator->id)->lockForUpdate()->firstOrFail();
                $operatorProofIsCurrent = ($proof['actor_id'] ?? null) === $lockedOperator->id
                    && ($proof['session_id'] ?? null) === $session->getId()
                    && ($proof['binding'] ?? null) === $batchBinding
                    && (int) ($proof['session_version'] ?? 0) === (int) $operatorState->session_version
                    && (int) $session->get('auth.session_version', 0) === (int) $operatorState->session_version
                    && ($proof['factor_generation'] ?? null) === $operatorState->two_factor_generation
                    && ($proof['recovery_generation'] ?? null) === $operatorState->recovery_generation
                    && ($proof['password_set_at'] ?? null) === $operatorState->password_set_at?->toIso8601String()
                    && $operatorState->activation_completed_at !== null
                    && $operatorState->password_set_at !== null
                    && ! $operatorState->must_change_password
                    && $operatorState->hasConfirmedTwoFactor()
                    && is_string($operatorState->two_factor_secret)
                    && $operatorState->two_factor_secret !== ''
                    && ($proof['created_at'] ?? 0) >= now()->subMinutes(5)->timestamp
                    && $this->targetScope->actorIsActiveInInstitution($lockedOperator, $institutionId)
                    && $this->canManagePersonnelFresh($lockedOperator, $institutionId);
                if (! $operatorProofIsCurrent) {
                    return ['outcome' => 'conflicted', 'message' => 'Operator security or authority changed; verify again before continuing.'];
                }
                $locked = User::query()->whereKey($userId)->lockForUpdate()->with('staffProfile', 'authenticationState')->firstOrFail();
                $profile = $locked->staffProfile;
                $state = StaffAuthenticationState::query()->where('user_id', $locked->id)->lockForUpdate()->firstOrFail();
                $operationId = (string) (($input['operation_ids'][(string) $userId] ?? null) ?: $input['operation_id'] ?? '');
                $digest = hash('sha256', json_encode(['target' => $userId, 'generation' => $input['expected_generations'][(string) $userId] ?? null, 'reason' => $input['reason'] ?? ''], JSON_THROW_ON_ERROR));
                $prior = StaffAccountOperationReceipt::query()->where('operation_id', $operationId)->lockForUpdate()->first();
                if ($prior) {
                    if ((int) $prior->actor_id !== (int) $operator->id
                        || (int) $prior->institution_id !== $institutionId
                        || $prior->purpose !== 'full_reset'
                        || (int) $prior->target_user_id !== $userId
                        || ! hash_equals($prior->payload_digest, $digest)) {
                        return ['outcome' => 'conflicted', 'message' => 'Operation identity was reused with different details.'];
                    }

                    return ['outcome' => $prior->outcome, 'message' => 'Previously completed operation; no changes repeated.'];
                }
                if (! $operationId || ! $profile || (int) $profile->institution_id !== $institutionId
                    || ! $this->targetScope->allows($operator, $locked)) {
                    return ['outcome' => 'failed', 'message' => 'Target is outside the permitted personnel scope.'];
                }
                if ($state->protected_from_reset) {
                    return ['outcome' => 'protected', 'message' => 'This recovery account is protected from full reset.'];
                }
                $expectedGeneration = (string) $state->session_version.':'.(string) $state->two_factor_generation;
                if (($input['expected_generations'][(string) $userId] ?? null) !== $expectedGeneration) {
                    return ['outcome' => 'conflicted', 'message' => 'Target security state changed; review the selection again.'];
                }
                if ($this->authorizer->decideFresh($locked, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed
                    && ! $this->hrAdministratorPool->hasUsableAdministratorRemaining($institutionId, [$userId])) {
                    return ['outcome' => 'protected', 'message' => 'This reset would remove the last usable HR recovery route.'];
                }
                $this->invalidate->handle($locked, $state, factorChanged: true);
                $locked->forceFill(['password' => Str::random(64), 'remember_token' => Str::random(60)])->save();
                $state->forceFill([
                    'activation_completed_at' => null,
                    'password_set_at' => null,
                    'must_change_password' => false,
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_last_accepted_step' => null,
                    'two_factor_generation' => (string) Str::uuid(),
                    'recovery_generation' => (string) Str::uuid(),
                    'pending_two_factor_secret' => null,
                    'pending_two_factor_expires_at' => null,
                    'pending_enrollment_attempt_id' => null,
                ])->save();
                $state->providerIdentities()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                if (Schema::hasTable(config('session.table', 'sessions'))) {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $locked->id)->delete();
                }
                DB::table('staff_session_leases')->where('user_id', $locked->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $state->recoveryCodes()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                DB::table('staff_email_changes')->where('user_id', $locked->id)->whereNull('confirmed_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
                if (Schema::hasTable('staff_setup_email_verifications')) {
                    DB::table('staff_setup_email_verifications')->where('user_id', $locked->id)->whereNull('confirmed_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
                }
                $this->audit->handle('hr.staff.full_reset', ['target_user_id' => $locked->id, 'reason' => $input['reason']], (string) $operator->id);
                StaffAccountOperationReceipt::query()->create([
                    'operation_id' => $operationId,
                    'actor_id' => $operator->id,
                    'institution_id' => $institutionId,
                    'purpose' => 'full_reset',
                    'payload_digest' => $digest,
                    'outcome' => 'committed',
                    'target_user_id' => $locked->id,
                    'details' => ['reason' => $input['reason']],
                ]);

                return ['outcome' => 'committed', 'message' => 'Account returned to Awaiting setup.'];
            });

            return ['user_id' => $userId, ...$result];
        } catch (Throwable) {
            return ['user_id' => $userId, 'outcome' => 'failed', 'message' => 'Reset did not complete; review current state and retry.'];
        }
    }

    private function canManagePersonnelFresh(User $actor, int $institutionId): bool
    {
        if ($this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed) {
            return true;
        }
        $office = (string) ($actor->staffProfile?->office ?? '');
        if ($office !== '' && $this->authorizer->decideFresh($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, [
            'institution_id' => $institutionId,
            'office' => $office,
        ])->allowed) {
            return true;
        }

        return $this->targetScope->ownedActiveDepartmentIds($actor, $institutionId) !== [];
    }

    private function consumeProofStep(
        User $operator,
        string $currentPassword,
        string $totpCode,
        mixed $session,
        string $binding,
        int $institutionId,
        int $expectedSessionVersion,
        string $expectedFactorGeneration,
        ?string $expectedRecoveryGeneration,
        string $expectedPasswordSetAt,
    ): void {
        DB::transaction(function () use ($operator, $currentPassword, $totpCode, $session, $binding, $institutionId, $expectedSessionVersion, $expectedFactorGeneration, $expectedRecoveryGeneration, $expectedPasswordSetAt): void {
            $lockedOperator = User::query()->whereKey($operator->id)->lockForUpdate()->with(['roles', 'staffProfile'])->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedOperator->id)->lockForUpdate()->firstOrFail();
            if (! $this->targetScope->actorIsActiveInInstitution($lockedOperator, $institutionId)
                || ! $this->canManagePersonnelFresh($lockedOperator, $institutionId)
                || ! Hash::check($currentPassword, (string) $lockedOperator->password)
                || (int) $state->session_version !== $expectedSessionVersion
                || (int) $session->get('auth.session_version', 0) !== $expectedSessionVersion
                || $state->two_factor_generation !== $expectedFactorGeneration
                || $state->recovery_generation !== $expectedRecoveryGeneration
                || $state->password_set_at?->toIso8601String() !== $expectedPasswordSetAt
                || $state->activation_completed_at === null
                || $state->password_set_at === null
                || $state->must_change_password
                || ! $state->hasConfirmedTwoFactor()
                || ! is_string($state->two_factor_secret)
                || $state->two_factor_secret === '') {
                throw ValidationException::withMessages(['current_password' => 'Operator proof or authorization changed; verify again.']);
            }
            $step = (new Google2FA)->verifyKeyNewer($state->two_factor_secret, $totpCode, $state->two_factor_last_accepted_step ?? -1);
            if ($step === false) {
                throw ValidationException::withMessages(['totp_code' => 'The authenticator code is invalid or already used.']);
            }
            $state->forceFill(['two_factor_last_accepted_step' => $step])->save();
            $session->put('staff.full_reset.proof', [
                'actor_id' => $lockedOperator->id,
                'session_id' => $session->getId(),
                'binding' => $binding,
                'factor_generation' => $state->two_factor_generation,
                'session_version' => (int) $state->session_version,
                'recovery_generation' => $state->recovery_generation,
                'password_set_at' => $state->password_set_at?->toIso8601String(),
                'created_at' => now()->timestamp,
            ]);
        });
    }
}
