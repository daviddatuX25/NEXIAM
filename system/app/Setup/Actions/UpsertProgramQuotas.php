<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpsertProgramQuotas
{
    public function __construct(
        private readonly UpsertProgramQuota $upsert,
        private readonly CapabilityAuthorizer $capabilities,
        private readonly GovernanceAuthorizer $governance,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    /**
     * @param  list<array{program_id:int,slots:int,reason:string,expected_version:int}>  $members
     * @return list<ProgramQuotaChange>
     */
    public function handle(User $actor, AcademicCycle $cycle, array $members, string $idempotencyKey): array
    {
        if (! config('admission.quota_writes_enabled', false) || ! Str::isUuid($idempotencyKey) || $members === []) {
            throw new AuthorizationException('Quota bulk mutation is unavailable.');
        }
        usort($members, static fn (array $left, array $right): int => $left['program_id'] <=> $right['program_id']);
        if (count(array_unique(array_column($members, 'program_id'))) !== count($members)) {
            throw ValidationException::withMessages(['quotas' => 'Each program may appear only once in a bulk quota command.']);
        }
        $payloadHash = hash('sha256', json_encode([
            'actor_user_id' => (int) $actor->getKey(),
            'command' => 'setup.program_quota.bulk_upsert',
            'cycle_id' => (int) $cycle->getKey(),
            'members' => $members,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $cycle->getKey(), function () use ($actor, $cycle, $members, $idempotencyKey, $payloadHash): array {
            return DB::transaction(function () use ($actor, $cycle, $members, $idempotencyKey, $payloadHash): array {
                $cycle = AcademicCycle::query()->find($cycle->getKey());
                $principal = User::query()->find($actor->getKey());
                if ($cycle === null || $principal === null || ! config('admission.quota_writes_enabled', false)
                    || ! $this->capabilities->allows($principal, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
                    throw new AuthorizationException('Quota bulk mutation denied.');
                }

                foreach ($members as $member) {
                    $program = Program::query()->find($member['program_id']);
                    if ($program === null || ! $this->governance->allows($principal, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, true)) {
                        throw new AuthorizationException('Quota bulk mutation denied for a selected program.');
                    }
                }

                $receipt = DB::table('program_quota_bulk_command_receipts')
                    ->where('actor_user_id', $principal->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($receipt !== null) {
                    if (! hash_equals((string) $receipt->payload_hash, $payloadHash)) {
                        throw new QuotaCommandConflict(null, 'This bulk idempotency key was already used for a different quota command.');
                    }

                    $ids = json_decode((string) $receipt->quota_change_ids, true, 512, JSON_THROW_ON_ERROR);

                    return ProgramQuotaChange::query()->with('programQuota.program', 'actor')->whereIn('id', $ids)->orderBy('id')->get()->all();
                }

                $changes = [];
                foreach ($members as $member) {
                    $program = Program::query()->findOrFail($member['program_id']);
                    $changes[] = $this->upsert->handle(
                        $principal,
                        $program,
                        $cycle,
                        $member['slots'],
                        $member['reason'],
                        $member['expected_version'],
                        $this->memberKey($idempotencyKey, (int) $program->getKey()),
                    );
                }

                try {
                    DB::table('program_quota_bulk_command_receipts')->insert([
                        'actor_user_id' => $principal->getKey(),
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash' => $payloadHash,
                        'quota_change_ids' => json_encode(array_map(static fn (ProgramQuotaChange $change): int => (int) $change->getKey(), $changes), JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    throw new QuotaCommandConflict(null, 'This bulk idempotency key was committed by another request. Retry to receive its original receipt.');
                }

                return $changes;
            });
        });
    }

    private function memberKey(string $parentKey, int $programId): string
    {
        $namespace = hex2bin(str_replace('-', '', $parentKey));
        if ($namespace === false) {
            throw new RuntimeException('Bulk idempotency key is invalid.');
        }
        $bytes = substr(sha1($namespace.$programId, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
