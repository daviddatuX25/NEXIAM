<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Staffing\StaffAccountOperationReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Queries\StaffAccountPreparationFingerprint;
use App\Staffing\Support\StaffRoleCeiling;
use App\Staffing\Support\StaffTargetManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PrepareStaffAccountRecipients
{
    public function __construct(
        private readonly CreateStaffAccount $createStaffAccount,
        private readonly UpdateStaffAssignment $updateStaffAssignment,
        private readonly StaffRoleCeiling $ceiling,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly StaffAccountPreparationFingerprint $fingerprints,
    ) {}

    /** @param array<string, mixed> $payload @return array{review_token: string, fingerprint: string, expires_at: string, review: array<string, mixed>} */
    public function preview(User $actor, array $payload): array
    {
        $this->authorizeActor($actor);
        if (isset($payload['recipients'])) {
            foreach ($payload['recipients'] as $recipient) {
                $this->assertDemoMode($actor, $recipient['demo_mode'] ?? false);
                $office = $this->ceiling->resolveCreateOffice($actor, $recipient['office'] ?? null);
                $roles = $this->ceiling->resolveCreateRoles($actor, ['roles' => $recipient['roles']], $office);
                if ($roles === [] || $roles !== $recipient['roles']) {
                    abort(403);
                }
                $this->assertPreparationScope($actor, $office, $roles, $recipient['program_codes'] ?? []);
            }

            return $this->signedReview($actor, $payload);
        }

        $this->assertDemoMode($actor, $payload['demo_mode'] ?? false);
        $mode = $payload['mode'] ?? 'create';
        $payload['mode'] = $mode;
        if ($mode === 'assign_existing') {
            if (! $this->ceiling->isFullHr($actor)) {
                abort(403);
            }
            $this->assertExistingProgramHeadAssignment($actor, (int) $payload['target_user_id'], $payload['program_codes'][0]);
        } elseif ($mode === 'prepare_dean') {
            $this->assertDeanPreparation($actor, (int) $payload['department_id']);
        } else {
            $office = $this->ceiling->resolveCreateOffice($actor, $payload['office'] ?? null);
            $roles = $this->ceiling->resolveCreateRoles($actor, ['roles' => $payload['roles']], $office);
            if ($roles === [] || $roles !== $payload['roles']) {
                abort(403);
            }
            $this->assertPreparationScope($actor, $office, $roles, $payload['program_codes'] ?? []);
        }

        return $this->signedReview($actor, $payload);
    }

    /** @param array<string, mixed> $payload @return array{kind: string, message?: string, results?: list<array<string, mixed>>} */
    public function commit(User $actor, array $payload): array
    {
        $this->authorizeActor($actor);

        return isset($payload['recipients'])
            ? $this->commitBatch($actor, $payload)
            : $this->commitOne($actor, $payload);
    }

    private function authorizeActor(User $actor): void
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            abort(403);
        }
        $profile = $actor->staffProfile;
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        abort_unless($profile?->isActive() && (int) $profile->institution_id === $institutionId && $institutionId > 0, 403);
    }

    /** @param array<string, mixed> $payload @return array{review_token:string,fingerprint:string,expires_at:string,review:array<string,mixed>} */
    private function signedReview(User $actor, array $payload): array
    {
        $fingerprint = $this->fingerprints->handle($actor, $payload);
        $expires = now()->addMinutes(10);
        $token = Crypt::encryptString(json_encode([
            'actor' => $actor->id,
            'institution' => $actor->actorScope()->institutionId,
            'fingerprint' => $fingerprint,
            'expires' => $expires->timestamp,
        ], JSON_THROW_ON_ERROR));

        return ['review_token' => $token, 'fingerprint' => $fingerprint, 'expires_at' => $expires->toIso8601String(), 'review' => $payload];
    }

    /** @param array<string, mixed> $payload @return array{kind:string,message:string} */
    private function commitOne(User $actor, array $payload): array
    {
        $this->assertDemoMode($actor, $payload['demo_mode'] ?? false);
        $reviewToken = $payload['review_token'];
        unset($payload['review_token']);
        $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $prior = StaffAccountOperationReceipt::query()->where('operation_id', $payload['operation_id'])->first();
        if ($prior) {
            $this->assertReceiptMatch($prior, $actor, $digest);

            return ['kind' => 'single', 'message' => 'Preparation was already committed; no account was created again.'];
        }
        $claims = $this->decodeReview($reviewToken);
        $this->assertReviewCurrent($actor, $payload, $claims);
        $mode = $payload['mode'] ?? 'create';
        if ($mode === 'assign_existing') {
            if (! $this->ceiling->isFullHr($actor)) {
                abort(403);
            }
            $office = 'program';
            $roles = ['program_head'];
            $this->assertExistingProgramHeadAssignment($actor, (int) $payload['target_user_id'], $payload['program_codes'][0]);
        } elseif ($mode === 'prepare_dean') {
            if (! $this->ceiling->isFullHr($actor)) {
                abort(403);
            }
            $office = 'program';
            $roles = ['program_head'];
            $this->assertDeanPreparation($actor, (int) $payload['department_id']);
        } else {
            $office = $this->ceiling->resolveCreateOffice($actor, $payload['office'] ?? null);
            $roles = $this->ceiling->resolveCreateRoles($actor, ['roles' => $payload['roles']], $office);
            $this->assertPreparationScope($actor, $office, $roles, $payload['program_codes'] ?? []);
        }
        try {
            DB::transaction(function () use ($actor, $payload, $office, $roles, $mode, $digest, $claims): void {
                $institutionId = (int) $actor->actorScope()->institutionId;
                Institution::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();
                $this->lockInstitutionStructure($institutionId);
                $prior = StaffAccountOperationReceipt::query()->where('operation_id', $payload['operation_id'])->lockForUpdate()->first();
                if ($prior) {
                    $this->assertReceiptMatch($prior, $actor, $digest);

                    return;
                }
                if (! hash_equals((string) $claims['fingerprint'], $this->fingerprints->handle($actor, $payload))) {
                    throw new RuntimeException('Institutional structure or coverage changed after review. Review again.');
                }
                if ($mode === 'assign_existing') {
                    $target = User::query()->whereKey($payload['target_user_id'])->lockForUpdate()->with(['roles', 'staffProfile', 'authenticationState'])->firstOrFail();
                    $programCode = $payload['program_codes'][0];
                    $this->assertExistingProgramHeadAssignment($actor, (int) $target->id, $programCode);
                    $programCodes = array_values(array_unique([...(array) ($target->staffProfile?->program_codes ?? []), $programCode]));
                    $this->updateStaffAssignment->handle($actor, $target, $target->name, null, 'program', $programCodes, $target->staffProfile?->active_cycle_code);
                    StaffAccountOperationReceipt::query()->create([
                        'operation_id' => $payload['operation_id'], 'actor_id' => $actor->id, 'institution_id' => $institutionId,
                        'purpose' => 'prepare', 'payload_digest' => $digest, 'outcome' => 'committed', 'target_user_id' => $target->id,
                        'details' => ['mode' => 'assign_existing', 'program_code' => $programCode],
                    ]);

                    return;
                }
                if ($mode === 'prepare_dean') {
                    $department = Department::query()->whereKey($payload['department_id'])
                        ->where('institution_id', $institutionId)->where('is_active', true)->lockForUpdate()->firstOrFail();
                    if ($department->dean_user_id !== null) {
                        throw new RuntimeException('This department now has a Dean assignment. Review current coverage.');
                    }
                    $this->createRecipient($actor, [...$payload, 'office' => 'program', 'roles' => ['program_head'], 'program_codes' => []], $institutionId, $office, $roles, $digest);

                    return;
                }
                $this->createRecipient($actor, $payload, $institutionId, $office, $roles, $digest);
            });
        } catch (QueryException) {
            throw new RuntimeException('That email address is already assigned to another account.');
        }

        return ['kind' => 'single', 'message' => $mode === 'prepare_dean'
            ? 'Recipient prepared for Dean assignment. The department remains uncovered until an authorized Admin Office Setup user assigns this person in Departments.'
            : 'Staff account is awaiting recipient setup.'];
    }

    /** @param array<string, mixed> $payload @return array{kind:string,results:list<array<string,mixed>>} */
    private function commitBatch(User $actor, array $payload): array
    {
        $reviewToken = $payload['review_token'];
        unset($payload['review_token']);
        $claims = $this->decodeReview($reviewToken);
        if (($claims['actor'] ?? null) !== $actor->id
            || ($claims['institution'] ?? null) !== $actor->actorScope()->institutionId
            || ($claims['expires'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['review_token' => 'Batch review expired or institutional scope changed. Review again.']);
        }
        $results = [];
        DB::transaction(function () use ($actor, $payload, $claims, &$results): void {
            $institutionId = (int) $actor->actorScope()->institutionId;
            Institution::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();
            $this->lockInstitutionStructure($institutionId);
            $operationIds = collect($payload['recipients'])->pluck('operation_id')->all();
            $priorReceipts = StaffAccountOperationReceipt::query()->whereIn('operation_id', $operationIds)->lockForUpdate()->get()->keyBy('operation_id');
            $priorForRow = [];
            $excludedUserIds = [];
            foreach ($payload['recipients'] as $recipient) {
                $prior = $priorReceipts->get($recipient['operation_id']);
                if (! $prior) {
                    continue;
                }
                $digest = hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR));
                try {
                    $this->assertReceiptMatch($prior, $actor, $digest);
                } catch (RuntimeException) {
                    $results[$recipient['operation_id']] = ['operation_id' => $recipient['operation_id'], 'target_user_id' => $prior->target_user_id, 'outcome' => 'conflicted', 'message' => 'Operation identity was reused with different details.'];

                    continue;
                }
                $priorForRow[$recipient['operation_id']] = $prior;
                $results[$recipient['operation_id']] = ['operation_id' => $recipient['operation_id'], 'target_user_id' => $prior->target_user_id, 'outcome' => 'already_committed', 'message' => 'Previously committed; no account was created again.'];
                if ($prior->target_user_id) {
                    $excludedUserIds[] = (int) $prior->target_user_id;
                }
            }
            if (! hash_equals((string) ($claims['fingerprint'] ?? ''), $this->fingerprints->handle($actor, $payload, $excludedUserIds))) {
                foreach ($payload['recipients'] as $recipient) {
                    if (! isset($results[$recipient['operation_id']]) && ! isset($priorForRow[$recipient['operation_id']])) {
                        $results[$recipient['operation_id']] = ['operation_id' => $recipient['operation_id'], 'target_user_id' => null, 'outcome' => 'conflicted', 'message' => 'Institutional structure or coverage changed after review. Review this recipient again.'];
                    }
                }

                return;
            }
            foreach ($payload['recipients'] as $recipient) {
                $operationId = $recipient['operation_id'];
                if (isset($results[$operationId])) {
                    continue;
                }
                try {
                    $results[$operationId] = DB::transaction(function () use ($actor, $recipient, $operationId, $institutionId): array {
                        $this->assertDemoMode($actor, $recipient['demo_mode'] ?? false);
                        $office = $this->ceiling->resolveCreateOffice($actor, $recipient['office'] ?? null);
                        $roles = $this->ceiling->resolveCreateRoles($actor, ['roles' => $recipient['roles']], $office);
                        if ($roles === [] || $roles !== $recipient['roles']) {
                            throw new RuntimeException('Recipient role is outside the current creation ceiling.');
                        }
                        $this->assertPreparationScope($actor, $office, $roles, $recipient['program_codes'] ?? []);
                        $digest = hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR));
                        $user = $this->createRecipient($actor, $recipient, $institutionId, $office, $roles, $digest);

                        return ['operation_id' => $operationId, 'target_user_id' => $user->id, 'outcome' => 'committed', 'message' => 'Awaiting recipient setup.'];
                    });
                } catch (QueryException) {
                    $results[$operationId] = ['operation_id' => $operationId, 'target_user_id' => null, 'outcome' => 'failed', 'message' => 'Email uniqueness conflict; review this recipient and retry.'];
                } catch (Throwable $exception) {
                    $results[$operationId] = ['operation_id' => $operationId, 'target_user_id' => null, 'outcome' => 'failed', 'message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Preparation failed; review current state and retry.'];
                }
            }
        });

        return ['kind' => 'batch', 'results' => array_values($results)];
    }

    /** @param array<string,mixed> $recipient @param list<string> $roles */
    private function createRecipient(User $actor, array $recipient, int $institutionId, string $office, array $roles, string $digest): User
    {
        $demoMode = (bool) ($recipient['demo_mode'] ?? false);
        if (! $demoMode && isset($recipient['email'])
            && DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($recipient['email']))])->exists()) {
            throw new RuntimeException('That email address is already assigned to another account.');
        }
        $programCodes = $recipient['program_codes'] ?? [];
        $this->assertProgramsUncovered($institutionId, $programCodes);
        $name = $demoMode ? 'Demo Staff '.Str::upper(Str::random(6)) : $recipient['name'];
        $email = $demoMode ? 'demo+'.Str::lower(Str::random(20)).'@example.invalid' : ($recipient['email'] ?? null);
        $user = $this->createStaffAccount->handle($actor, $name, $email, $roles, $office, $institutionId, $programCodes, $actor->actorScope()->activeCycleCode);
        StaffAccountOperationReceipt::query()->create([
            'operation_id' => $recipient['operation_id'], 'actor_id' => $actor->id, 'institution_id' => $institutionId,
            'purpose' => 'prepare', 'payload_digest' => $digest, 'outcome' => 'committed', 'target_user_id' => $user->id,
            'details' => ['name' => $user->name, 'office' => $office, 'roles' => $roles, 'program_codes' => $programCodes, 'email_pending' => $user->email === null],
        ]);

        return $user;
    }

    /** @param array<string, mixed> $payload */
    private function assertDemoMode(User $actor, mixed $demoMode): void
    {
        if ($demoMode && (! app()->environment(['local', 'testing']) || ! $this->ceiling->isFullHr($actor))) {
            abort(403);
        }
    }

    private function decodeReview(string $token): array
    {
        try {
            return json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages(['review_token' => 'Review expired or is invalid. Review the recipients again.']);
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $claims */
    private function assertReviewCurrent(User $actor, array $payload, array $claims): void
    {
        if (($claims['actor'] ?? null) !== $actor->id
            || ($claims['institution'] ?? null) !== $actor->actorScope()->institutionId
            || ($claims['expires'] ?? 0) < now()->timestamp
            || ! hash_equals((string) ($claims['fingerprint'] ?? ''), $this->fingerprints->handle($actor, $payload))) {
            throw ValidationException::withMessages(['review_token' => 'The reviewed recipients or institutional scope changed. Review again.']);
        }
    }

    private function assertReceiptMatch(StaffAccountOperationReceipt $receipt, User $actor, string $digest): void
    {
        if ((int) $receipt->actor_id !== (int) $actor->id
            || (int) $receipt->institution_id !== (int) $actor->actorScope()->institutionId
            || $receipt->purpose !== 'prepare'
            || ! hash_equals($receipt->payload_digest, $digest)) {
            throw new RuntimeException('This operation identity was already used for a different action.');
        }
    }

    private function lockInstitutionStructure(int $institutionId): void
    {
        DB::table('departments')->where('institution_id', $institutionId)->orderBy('id')->lockForUpdate()->get(['id']);
        DB::table('programs')->where('institution_id', $institutionId)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** @param list<string> $roles @param list<string> $programCodes */
    private function assertPreparationScope(User $actor, string $office, array $roles, array $programCodes): void
    {
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        $isProgramHead = in_array('program_head', $roles, true);
        if (($isProgramHead && $office !== 'program') || (! $isProgramHead && $programCodes !== []) || ($isProgramHead && $programCodes === [])) {
            throw ValidationException::withMessages(['program_codes' => 'Program Head preparation requires active program assignments; other roles cannot receive program assignments here.']);
        }
        $available = Program::query()->where('institution_id', $institutionId)->where('is_active', true)
            ->whereHas('department', fn ($query) => $query->where('is_active', true))
            ->whereIn('code', $programCodes)->pluck('code')->all();
        if (count(array_unique($programCodes)) !== count($programCodes) || array_diff($programCodes, $available) !== []) {
            throw ValidationException::withMessages(['program_codes' => 'Select only currently active programs in your institution. Review coverage again.']);
        }
    }

    /** @param list<string> $programCodes */
    private function assertProgramsUncovered(int $institutionId, array $programCodes): void
    {
        foreach ($programCodes as $programCode) {
            if (StaffProfile::query()->where('institution_id', $institutionId)->where('office', 'program')->where('status', 'active')
                ->whereJsonContains('program_codes', $programCode)->whereHas('user.roles', fn ($query) => $query->where('name', 'program_head'))->exists()) {
                throw ValidationException::withMessages(['program_codes' => "Program [{$programCode}] already has active Program Head coverage. Review current assignments."]);
            }
        }
    }

    private function assertDeanPreparation(User $actor, int $departmentId): void
    {
        if (! $this->ceiling->isFullHr($actor)) {
            abort(403);
        }
        $department = Department::query()->whereKey($departmentId)
            ->where('institution_id', (int) $actor->actorScope()->institutionId)
            ->where('is_active', true)->first();
        if (! $department || $department->dean_user_id !== null) {
            throw ValidationException::withMessages(['department_id' => 'Choose a currently active department that still needs a Dean.']);
        }
    }

    private function assertExistingProgramHeadAssignment(User $actor, int $targetUserId, string $programCode): void
    {
        $institutionId = (int) $actor->actorScope()->institutionId;
        $target = User::query()->whereKey($targetUserId)->with(['staffProfile', 'roles', 'authenticationState'])->firstOrFail();
        $profile = $target->staffProfile;
        if (! $this->ceiling->isFullHr($actor) || ! $profile || ! $profile->isActive()
            || (int) $profile->institution_id !== $institutionId || $profile->office !== 'program'
            || ! $target->roles->contains('name', 'program_head') || ! $this->targetScope->allows($actor, $target)) {
            throw ValidationException::withMessages(['target_user_id' => 'Choose an active Program Head within your institution and management scope.']);
        }
        $activeProgram = Program::query()->where('institution_id', $institutionId)->where('code', $programCode)->where('is_active', true)
            ->whereHas('department', fn ($query) => $query->where('is_active', true))->exists();
        if (! $activeProgram) {
            throw ValidationException::withMessages(['program_codes' => 'This program is no longer active in your institution. Refresh the review.']);
        }
        if (StaffProfile::query()->where('institution_id', $institutionId)->where('office', 'program')->where('status', 'active')
            ->where('user_id', '!=', $targetUserId)->whereJsonContains('program_codes', $programCode)
            ->whereHas('user.roles', fn ($query) => $query->where('name', 'program_head'))->exists()) {
            throw ValidationException::withMessages(['program_codes' => 'Another Program Head already covers this program. Refresh coverage before assigning.']);
        }
    }
}
