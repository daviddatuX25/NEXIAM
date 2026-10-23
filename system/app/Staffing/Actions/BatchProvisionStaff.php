<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffVoucherBatchRowReceipt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Staffing\StaffAccountOperationReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use App\Staffing\Support\StaffVoucherLifetime;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BatchProvisionStaff
{
    private const PURPOSE = 'staff_provisioning';

    /** @var list<string> */
    private const OFFICES = ['admin', 'registrar', 'guidance', 'program'];

    /** @var array<string, string> */
    private const ROLE_OFFICES = [
        'institutional_admin' => 'admin',
        'hr_administrator' => 'admin',
        'registrar_admin' => 'registrar',
        'registrar_clerk' => 'registrar',
        'registrar_support_staff' => 'registrar',
        'guidance_counselor' => 'guidance',
        'guidance_clerk' => 'guidance',
        'guidance_support_staff' => 'guidance',
        'program_head' => 'program',
    ];

    public function __construct(
        private readonly CreateStaffAccount $createStaffAccount,
        private readonly IssueStaffActivationVoucher $issueVoucher,
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<array{operation_id: string, name: string, office: string, roles: list<string>, program_codes?: list<string>}>  $recipients
     * @return array{operation_id: string, rows: list<array<string, mixed>>}
     */
    public function handle(
        User $actor,
        string $destinationCycleCode,
        string $voucherMode,
        string $operationId,
        array $recipients,
    ): array {
        $supportedModes = array_values(array_diff(StaffVoucherLifetime::ACTIVATION_MODES, ['custom']));
        if (! in_array($voucherMode, $supportedModes, true)) {
            throw ValidationException::withMessages(['voucher_mode' => 'Choose a supported voucher lifetime. Custom expiry is not available for batch provisioning.']);
        }

        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'A valid batch operation UUID is required.']);
        }

        if (trim($destinationCycleCode) !== $destinationCycleCode
            || $destinationCycleCode === '' || mb_strlen($destinationCycleCode) > 64) {
            throw ValidationException::withMessages(['destination_cycle_code' => 'Choose a valid destination cycle in your institution.']);
        }

        if (count($recipients) < 1 || count($recipients) > 50) {
            throw ValidationException::withMessages(['recipients' => 'Submit between 1 and 50 recipient rows.']);
        }

        $seenOperationIds = [];
        foreach ($recipients as $index => $row) {
            $rowOperationId = is_array($row) && is_string($row['operation_id'] ?? null)
                ? $row['operation_id']
                : '';
            if (! Str::isUuid($rowOperationId) || isset($seenOperationIds[$rowOperationId])) {
                throw ValidationException::withMessages(["recipients.{$index}.operation_id" => 'Each recipient requires a unique valid operation UUID.']);
            }

            $seenOperationIds[$rowOperationId] = true;
        }

        $initialActor = User::query()->with('staffProfile')->whereKey($actor->id)->first();
        $initialProfile = $initialActor?->staffProfile;
        if ($initialProfile === null || ! $initialProfile->isActive()) {
            throw new AuthorizationException('An active staff account is required to provision personnel.');
        }

        $institutionId = (int) $initialProfile->institution_id;
        if ($institutionId < 1) {
            throw new AuthorizationException('The actor has no active institution membership.');
        }

        $timezone = (string) config('app.timezone', 'UTC');

        return DB::transaction(function () use ($actor, $institutionId, $destinationCycleCode, $voucherMode, $operationId, $recipients, $timezone): array {
            Institution::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();

            $freshActor = User::query()
                ->with(['roles', 'permissions', 'staffProfile.department'])
                ->whereKey($actor->id)
                ->first();
            $freshProfile = $freshActor?->staffProfile;
            if ($freshActor === null || $freshProfile === null || ! $freshProfile->isActive()
                || (int) $freshProfile->institution_id !== $institutionId
                || ! $this->ceiling->canManagePersonnel($freshActor)) {
                throw new AuthorizationException('Current staff authority is required to provision personnel.');
            }

            $cycleExists = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('code', $destinationCycleCode)
                ->exists();
            if (! $cycleExists) {
                throw ValidationException::withMessages(['destination_cycle_code' => 'Choose a destination cycle in your institution.']);
            }

            $recipientDigests = [];
            foreach ($recipients as $row) {
                $recipientDigests[] = $this->payloadDigest(
                    $freshActor,
                    $institutionId,
                    $destinationCycleCode,
                    $voucherMode,
                    $row,
                );
            }

            $priorBatchReceipts = StaffVoucherBatchRowReceipt::query()
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->get(['actor_user_id', 'institution_id', 'payload_hash']);
            foreach ($priorBatchReceipts as $priorBatchReceipt) {
                $payloadMatches = false;
                foreach ($recipientDigests as $recipientDigest) {
                    if (hash_equals((string) $priorBatchReceipt->payload_hash, $recipientDigest)) {
                        $payloadMatches = true;
                        break;
                    }
                }

                if ((int) $priorBatchReceipt->actor_user_id !== (int) $freshActor->id
                    || (int) $priorBatchReceipt->institution_id !== $institutionId
                    || ! $payloadMatches) {
                    return [
                        'operation_id' => $operationId,
                        'rows' => array_map(fn (array $row): array => [
                            'operation_id' => (string) ($row['operation_id'] ?? ''),
                            'status' => 'conflicted',
                            'message' => 'Batch operation identity was reused with different provisioning details or authority.',
                        ], $recipients),
                    ];
                }
            }

            $rowResults = [];

            foreach ($recipients as $row) {
                $rowOperationId = (string) $row['operation_id'];
                $rowDigest = $this->payloadDigest(
                    $freshActor,
                    $institutionId,
                    $destinationCycleCode,
                    $voucherMode,
                    $row,
                );

                $priorReceipt = StaffAccountOperationReceipt::query()
                    ->where('operation_id', $rowOperationId)
                    ->lockForUpdate()
                    ->first();

                if ($priorReceipt !== null) {
                    if ((int) $priorReceipt->actor_id !== (int) $freshActor->id
                        || (int) $priorReceipt->institution_id !== $institutionId
                        || $priorReceipt->purpose !== self::PURPOSE
                        || $priorReceipt->outcome !== 'committed'
                        || $priorReceipt->target_user_id === null
                        || ! hash_equals((string) $priorReceipt->payload_digest, $rowDigest)) {
                        $rowResults[] = [
                            'operation_id' => $rowOperationId,
                            'status' => 'conflicted',
                            'message' => 'Operation identity was reused with different provisioning details or authority.',
                        ];

                        continue;
                    }

                    $rowResults[] = [
                        'operation_id' => $rowOperationId,
                        'user_id' => $priorReceipt->target_user_id,
                        'status' => 'already_committed',
                        'message' => 'This account was already provisioned in a prior operation. One-time voucher details cannot be recovered.',
                    ];

                    continue;
                }

                try {
                    $recipient = $this->validateRecipient($freshActor, $institutionId, $row);
                    $rowResult = DB::transaction(function () use (
                        $freshActor,
                        $recipient,
                        $rowOperationId,
                        $institutionId,
                        $destinationCycleCode,
                        $rowDigest,
                        $voucherMode,
                        $timezone,
                        $operationId,
                    ): array {
                        $user = $this->createStaffAccount->handle(
                            actor: $freshActor,
                            name: $recipient['name'],
                            email: null,
                            roles: $recipient['roles'],
                            office: $recipient['office'],
                            institutionId: $institutionId,
                            programCodes: $recipient['program_codes'],
                            activeCycleCode: $destinationCycleCode,
                        );

                        StaffAccountOperationReceipt::query()->create([
                            'operation_id' => $rowOperationId,
                            'actor_id' => $freshActor->id,
                            'institution_id' => $institutionId,
                            'purpose' => self::PURPOSE,
                            'target_user_id' => $user->id,
                            'outcome' => 'committed',
                            'payload_digest' => $rowDigest,
                            'created_at' => now(),
                        ]);

                        $voucher = $this->issueVoucher->handle(
                            actor: $freshActor,
                            target: $user,
                            purpose: 'activation',
                            mode: $voucherMode,
                            timezone: $timezone,
                            replaceExisting: false,
                        );

                        StaffVoucherBatchRowReceipt::query()->create([
                            'actor_user_id' => $freshActor->id,
                            'operation_id' => $operationId,
                            'target_user_id' => $user->id,
                            'institution_id' => $institutionId,
                            'payload_hash' => $rowDigest,
                            'public_reference' => $voucher['public_reference'],
                            'outcome' => 'issued',
                            'created_at' => now(),
                        ]);

                        $printHash = hash_hmac(
                            'sha256',
                            implode(':', [(string) $user->id, $voucher['public_reference'], $voucher['token'], $voucher['fallback']]),
                            (string) config('app.key'),
                        );

                        $this->recordAuditEvent->handle('hr.personnel.fast_provisioned', [
                            'target_user_id' => $user->id,
                            'operation_id' => $rowOperationId,
                            'office' => $recipient['office'],
                            'roles' => $recipient['roles'],
                            'active_cycle_code' => $destinationCycleCode,
                            'public_reference' => $voucher['public_reference'],
                        ], (string) $freshActor->id);

                        return [
                            'operation_id' => $rowOperationId,
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'office' => $recipient['office'],
                            'roles' => $recipient['roles'],
                            'program_codes' => $recipient['program_codes'],
                            'status' => 'issued',
                            'public_reference' => $voucher['public_reference'],
                            'token' => $voucher['token'],
                            'fallback' => $voucher['fallback'],
                            'mode' => $voucherMode,
                            'expires_at' => $voucher['expires_at'],
                            'expires_at_display' => $voucher['expires_at'] === null
                                ? 'No calendar expiry · Single-use · Revocable'
                                : Carbon::parse($voucher['expires_at'])->setTimezone($timezone)->format('M j, Y g:i A T (e)'),
                            'print_hash' => $printHash,
                        ];
                    });

                    $rowResults[] = $rowResult;
                } catch (RuntimeException|ValidationException $exception) {
                    $message = $exception instanceof ValidationException
                        ? (string) collect($exception->errors())->flatten()->first()
                        : $exception->getMessage();

                    $rowResults[] = [
                        'operation_id' => $rowOperationId,
                        'status' => 'failed',
                        'message' => $message !== '' ? $message : 'This row could not be provisioned.',
                    ];
                }
            }

            return [
                'operation_id' => $operationId,
                'rows' => $rowResults,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: string, office: string, roles: list<string>, program_codes: list<string>}
     */
    private function validateRecipient(User $actor, int $institutionId, array $row): array
    {
        $name = is_string($row['name'] ?? null) ? trim($row['name']) : '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new RuntimeException('Enter a staff name between 2 and 100 characters.');
        }

        $requestedOffice = is_string($row['office'] ?? null) ? $row['office'] : '';
        $office = $this->ceiling->resolveCreateOffice($actor, $requestedOffice);
        if (! in_array($office, self::OFFICES, true) || $office !== $requestedOffice) {
            throw new RuntimeException('Personnel must be created in an office within the actor authority.');
        }

        $requestedRoles = $row['roles'] ?? null;
        if (! is_array($requestedRoles) || $requestedRoles === []
            || count(array_filter($requestedRoles, 'is_string')) !== count($requestedRoles)
            || count(array_unique($requestedRoles)) !== count($requestedRoles)) {
            throw new RuntimeException('Choose one or more valid role helpers.');
        }

        $roles = $this->ceiling->resolveCreateRoles($actor, ['roles' => $requestedRoles], $office);
        if ($roles === [] || (! $this->ceiling->isFullHr($actor) && $roles !== $requestedRoles)) {
            throw new RuntimeException('Role helper is outside the actor creation ceiling.');
        }

        $allowedRoles = $this->ceiling->assignableRoleHelpers($actor);
        $supportRole = StaffRoleCeiling::supportRoleForOffice($office);
        if ($supportRole !== null) {
            $allowedRoles[] = $supportRole;
        }

        foreach ($roles as $role) {
            if (! in_array($role, CapabilityCatalog::personnelRoleHelpers(), true)
                || ! in_array($role, $allowedRoles, true)
                || (self::ROLE_OFFICES[$role] ?? null) !== $office) {
                throw new RuntimeException('Choose only role helpers available to the actor in the selected office.');
            }
        }

        $programCodes = $row['program_codes'] ?? [];
        if ($programCodes === null) {
            $programCodes = [];
        }
        if (! is_array($programCodes)
            || count(array_filter($programCodes, 'is_string')) !== count($programCodes)
            || count(array_unique($programCodes)) !== count($programCodes)) {
            throw new RuntimeException('Program assignments must contain unique program codes.');
        }
        $programCodes = array_values($programCodes);
        foreach ($programCodes as $programCode) {
            if ($programCode === '' || mb_strlen($programCode) > 32) {
                throw new RuntimeException('Choose valid active program assignments.');
            }
        }

        $isProgramHead = in_array('program_head', $roles, true);
        if (($isProgramHead && ($office !== 'program' || $programCodes === []))
            || (! $isProgramHead && $programCodes !== [])) {
            throw new RuntimeException('Only a Program Head may receive active program assignments, and at least one is required.');
        }

        if ($programCodes !== []) {
            $validCodes = Program::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->whereHas('department', fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('is_active', true))
                ->whereIn('code', $programCodes)
                ->pluck('code')
                ->all();

            if (count($validCodes) !== count($programCodes)) {
                throw new RuntimeException('Select only active programs in your institution.');
            }

            foreach ($programCodes as $programCode) {
                $alreadyCovered = StaffProfile::query()
                    ->where('institution_id', $institutionId)
                    ->where('office', 'program')
                    ->where('status', StaffProfile::STATUS_ACTIVE)
                    ->whereJsonContains('program_codes', $programCode)
                    ->whereHas('user.roles', fn ($query) => $query->where('name', 'program_head'))
                    ->exists();
                if ($alreadyCovered) {
                    throw new RuntimeException("Program [{$programCode}] already has active Program Head coverage.");
                }
            }
        }

        return [
            'name' => $name,
            'office' => $office,
            'roles' => array_values($roles),
            'program_codes' => $programCodes,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function payloadDigest(
        User $actor,
        int $institutionId,
        string $destinationCycleCode,
        string $voucherMode,
        array $row,
    ): string {
        $recipient = [
            'operation_id' => $row['operation_id'] ?? null,
            'name' => is_string($row['name'] ?? null) ? trim($row['name']) : ($row['name'] ?? null),
            'office' => $row['office'] ?? null,
            'roles' => $row['roles'] ?? [],
            'program_codes' => $row['program_codes'] ?? [],
        ];

        foreach (['roles', 'program_codes'] as $field) {
            if (is_array($recipient[$field]) && count(array_filter($recipient[$field], 'is_string')) === count($recipient[$field])) {
                sort($recipient[$field], SORT_STRING);
            }
        }

        return hash('sha256', json_encode([
            'actor_id' => (int) $actor->id,
            'institution_id' => $institutionId,
            'purpose' => self::PURPOSE,
            'destination_cycle_code' => $destinationCycleCode,
            'voucher_mode' => $voucherMode,
            'recipient' => $recipient,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
