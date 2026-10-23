<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

function prepareResetProof(User $operator, string $password, string $secret): StaffAuthenticationState
{
    $operator->forceFill(['password' => Hash::make($password)])->save();
    $state = StaffAuthenticationState::query()->where('user_id', $operator->id)->firstOrFail();
    $state->forceFill([
        'password_set_at' => now(),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();
    $operator->setRelation('authenticationState', $state);

    return $state;
}

it('requires fresh password and TOTP, revokes old access, preserves identity, and retries idempotently', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = app(CreateStaffAccount::class)->handle($hr, 'Reset Preservation Target', 'reset-preserve@example.test', ['registrar_clerk'], 'registrar', (int) $hr->actorScope()->institutionId);
    $target->forceFill(['password' => Hash::make('OldTargetPass!42')])->save();
    $targetState = $target->authenticationState;
    $targetState->forceFill(['activation_completed_at' => now(), 'password_set_at' => now()])->save();
    $target->setRelation('authenticationState', $targetState);
    $expected = (string) $targetState->session_version.':'.(string) $targetState->two_factor_generation;
    $operatorPassword = 'FreshOperatorPass!42';
    $operatorSecret = 'JBSWY3DPEHPK3PXP';
    $operatorState = prepareResetProof($hr, $operatorPassword, $operatorSecret);
    $batch = [
        'targets' => [$target->id],
        'operation_ids' => [(string) $target->id => (string) Str::uuid()],
        'expected_generations' => [(string) $target->id => $expected],
        'reason' => 'Recipient requested a full account setup restart.',
        'current_password' => $operatorPassword,
        'totp_code' => (new Google2FA)->getCurrentOtp($operatorSecret),
    ];

    $this->actingAs($hr)->withSession(['auth.surface' => 'staff', 'auth.session_version' => (int) $operatorState->session_version])
        ->post(route('staff.personnel.full-reset.batch'), $batch)->assertRedirect();
    $newSessionVersion = $target->fresh('authenticationState')->authenticationState->session_version;
    $this->post(route('staff.personnel.full-reset.batch'), $batch)->assertRedirect();

    $resetTarget = $target->fresh(['staffProfile', 'authenticationState', 'roles']);
    expect($resetTarget->id)->toBe($target->id)
        ->and($resetTarget->email)->toBe('reset-preserve@example.test')
        ->and($resetTarget->hasRole('registrar_clerk'))->toBeTrue()
        ->and($resetTarget->staffProfile->isActive())->toBeTrue()
        ->and($resetTarget->authenticationState->activation_completed_at)->toBeNull()
        ->and($resetTarget->authenticationState->password_set_at)->toBeNull()
        ->and($resetTarget->authenticationState->session_version)->toBe($newSessionVersion)
        ->and(Hash::check('OldTargetPass!42', $resetTarget->password))->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'hr.staff.full_reset')->count())->toBe(1);
});

it('preserves one usable HR recovery route when another ready HR account is reset', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = app(CreateStaffAccount::class)->handle($hr, 'Second Usable HR Recovery Account', 'second-hr-recovery@example.test', ['hr_administrator'], 'admin', (int) $hr->actorScope()->institutionId);
    $target->forceFill(['password' => Hash::make('TargetRecoveryPass!42')])->save();
    $targetState = $target->authenticationState;
    $targetState->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'recovery_generation' => (string) Str::uuid(),
    ])->save();
    $target->setRelation('authenticationState', $targetState);
    $originalPassword = $target->fresh()->password;
    $expectedGeneration = (string) $targetState->session_version.':'.(string) $targetState->two_factor_generation;
    $operatorPassword = 'RecoveryOperatorPass!42';
    $operatorSecret = 'JBSWY3DPEHPK3PXP';
    $operatorState = prepareResetProof($hr, $operatorPassword, $operatorSecret);

    $this->actingAs($hr)->withSession(['auth.surface' => 'staff', 'auth.session_version' => (int) $operatorState->session_version])
        ->post(route('staff.personnel.full-reset.batch'), [
            'targets' => [$target->id],
            'operation_ids' => [(string) $target->id => (string) Str::uuid()],
            'expected_generations' => [(string) $target->id => $expectedGeneration],
            'reason' => 'Verify another usable HR route remains after recovery.',
            'current_password' => $operatorPassword,
            'totp_code' => (new Google2FA)->getCurrentOtp($operatorSecret),
        ])->assertRedirect()->assertSessionHas('reset_results', fn (array $results): bool => ($results[0]['outcome'] ?? null) === 'committed');

    expect($target->fresh()->password)->not->toBe($originalPassword)
        ->and($target->fresh()->authenticationState->activation_completed_at)->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'hr.staff.full_reset')->count())->toBe(1);
});

it('excludes the operator and explicitly protected targets from a mixed reset batch', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $protected = app(CreateStaffAccount::class)->handle($hr, 'Protected Recovery Target', 'protected-recovery@example.test', ['registrar_clerk'], 'registrar', (int) $hr->actorScope()->institutionId);
    $protectedState = $protected->authenticationState;
    $protectedState->forceFill(['activation_completed_at' => now(), 'password_set_at' => now(), 'protected_from_reset' => true])->save();
    $protected->setRelation('authenticationState', $protectedState);
    $protectedPassword = $protected->password;
    $operatorPassword = 'ExclusionOperatorPass!42';
    $operatorSecret = 'JBSWY3DPEHPK3PXP';
    $operatorState = prepareResetProof($hr, $operatorPassword, $operatorSecret);
    $targets = [$hr, $protected];
    $operationIds = collect($targets)->mapWithKeys(fn (User $user) => [(string) $user->id => (string) Str::uuid()])->all();
    $expectedGenerations = collect($targets)->mapWithKeys(fn (User $user) => [(string) $user->id => (string) $user->authenticationState->session_version.':'.(string) $user->authenticationState->two_factor_generation])->all();

    $this->actingAs($hr)->withSession(['auth.surface' => 'staff', 'auth.session_version' => (int) $operatorState->session_version])
        ->post(route('staff.personnel.full-reset.batch'), [
            'targets' => collect($targets)->pluck('id')->all(),
            'operation_ids' => $operationIds,
            'expected_generations' => $expectedGenerations,
            'reason' => 'Review selected targets for reset exclusion behavior.',
            'current_password' => $operatorPassword,
            'totp_code' => (new Google2FA)->getCurrentOtp($operatorSecret),
        ])->assertRedirect()->assertSessionHas('reset_results', function (array $results) use ($hr, $protected): bool {
            $outcomes = collect($results)->keyBy('user_id');

            return ($outcomes[$hr->id]['outcome'] ?? null) === 'failed'
                && ($outcomes[$protected->id]['outcome'] ?? null) === 'protected';
        });

    expect($protected->fresh()->password)->toBe($protectedPassword)
        ->and($protected->fresh()->authenticationState->activation_completed_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'hr.staff.full_reset')->count())->toBe(0);
});

it('rolls back a target reset when the required audit write fails', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = app(CreateStaffAccount::class)->handle($hr, 'Audit Rollback Target', 'audit-rollback@example.test', ['registrar_clerk'], 'registrar', (int) $hr->actorScope()->institutionId);
    $target->forceFill(['password' => Hash::make('UnchangedTargetPass!42')])->save();
    $targetState = $target->authenticationState;
    $targetState->forceFill(['activation_completed_at' => now(), 'password_set_at' => now()])->save();
    $target->setRelation('authenticationState', $targetState);
    $originalPassword = $target->password;
    $expectedGeneration = (string) $targetState->session_version.':'.(string) $targetState->two_factor_generation;
    $operatorPassword = 'AuditOperatorPass!42';
    $operatorSecret = 'JBSWY3DPEHPK3PXP';
    $operatorState = prepareResetProof($hr, $operatorPassword, $operatorSecret);
    $this->instance(RecordAuditEvent::class, new class extends RecordAuditEvent
    {
        public function handle(string $eventType, array $payload, string $actor = 'system'): AuditEvent
        {
            throw new RuntimeException('The audit store is unavailable.');
        }
    });
    $operationId = (string) Str::uuid();

    $this->actingAs($hr)->withSession(['auth.surface' => 'staff', 'auth.session_version' => (int) $operatorState->session_version])
        ->post(route('staff.personnel.full-reset.batch'), [
            'targets' => [$target->id],
            'operation_ids' => [(string) $target->id => $operationId],
            'expected_generations' => [(string) $target->id => $expectedGeneration],
            'reason' => 'Rollback when an audit write cannot be recorded.',
            'current_password' => $operatorPassword,
            'totp_code' => (new Google2FA)->getCurrentOtp($operatorSecret),
        ])->assertRedirect()->assertSessionHas('reset_results.0.outcome', 'failed');

    expect($target->fresh()->password)->toBe($originalPassword)
        ->and($target->fresh()->authenticationState->activation_completed_at)->not->toBeNull()
        ->and(DB::table('staff_account_operation_receipts')->where('operation_id', $operationId)->exists())->toBeFalse();
});
