<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Versioned and idempotent quota command. */
final class UpsertProgramQuota
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly GovernanceAuthorizer $governance,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        int $slots,
        string $reason,
        int $expectedVersion,
        string $idempotencyKey,
    ): ProgramQuotaChange {
        $this->authorize($actor, $program, $cycle);

        if (! config('admission.quota_writes_enabled', false)) {
            throw new AuthorizationException('Quota changes are temporarily disabled.');
        }
        if ($slots < 0 || $slots > 100000 || $expectedVersion < 0 || blank($reason) || Str::length(trim($reason)) > 1000 || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['quota' => 'Quota command fields are invalid.']);
        }

        $reason = trim($reason);
        $payloadHash = hash('sha256', json_encode([
            'actor_user_id' => (int) $actor->getKey(),
            'command' => 'setup.program_quota.upsert',
            'program_id' => (int) $program->getKey(),
            'academic_cycle_id' => (int) $cycle->getKey(),
            'slots' => $slots,
            'reason' => $reason,
            'expected_version' => $expectedVersion,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $created = false;
        $change = $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $cycle->getKey(), function () use ($actor, $program, $cycle, $slots, $reason, $expectedVersion, $idempotencyKey, $payloadHash, &$created): ProgramQuotaChange {
            $this->authorize($actor, $program, $cycle);
            if (! config('admission.quota_writes_enabled', false)) {
                throw new AuthorizationException('Quota changes are temporarily disabled.');
            }

            return DB::transaction(function () use ($actor, $program, $cycle, $slots, $reason, $expectedVersion, $idempotencyKey, $payloadHash, &$created): ProgramQuotaChange {
                $replay = ProgramQuotaChange::query()
                    ->where('actor_user_id', $actor->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($replay !== null) {
                    if (! hash_equals((string) $replay->payload_hash, $payloadHash)) {
                        throw new QuotaCommandConflict(null, 'This idempotency key was already used for a different quota command.');
                    }

                    return $replay->load('programQuota.program', 'actor');
                }

                $quota = ProgramQuota::query()
                    ->where('program_id', $program->getKey())
                    ->where('academic_cycle_id', $cycle->getKey())
                    ->lockForUpdate()
                    ->first();
                $currentVersion = (int) ($quota?->version ?? 0);
                if ($currentVersion !== $expectedVersion) {
                    throw new QuotaCommandConflict($quota === null ? null : [
                        'program_id' => (int) $program->getKey(),
                        'slots' => (int) $quota->slots,
                        'version' => $currentVersion,
                    ]);
                }

                $quota ??= new ProgramQuota([
                    'program_id' => $program->getKey(),
                    'academic_cycle_id' => $cycle->getKey(),
                ]);
                $fromSlots = $quota->exists ? (int) $quota->slots : null;
                $version = $currentVersion + 1;
                $quota->forceFill([
                    'slots' => $slots,
                    'last_notice' => $reason,
                    'last_mutated_by_user_id' => $actor->getKey(),
                    'version' => $version,
                ])->save();
                try {
                    $change = ProgramQuotaChange::query()->create([
                        'program_quota_id' => $quota->getKey(),
                        'actor_user_id' => $actor->getKey(),
                        'from_slots' => $fromSlots,
                        'to_slots' => $slots,
                        'notice' => null,
                        'reason' => $reason,
                        'version' => $version,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash' => $payloadHash,
                        'created_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    throw new QuotaCommandConflict(null, 'This idempotency key was committed by another concurrent quota command.');
                }
                $this->recordAuditEvent->handle('setup.program_quota_upserted', [
                    'program_quota_id' => $quota->getKey(),
                    'quota_change_id' => $change->getKey(),
                    'program_id' => $program->getKey(),
                    'program_code' => $program->code,
                    'academic_cycle_id' => $cycle->getKey(),
                    'from_slots' => $fromSlots,
                    'to_slots' => $slots,
                    'version' => $version,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                ], (string) $actor->getKey());

                $created = true;

                return $change->load('programQuota.program', 'actor');
            });
        });

        if ($created) {
            $notify = fn (): null => $this->notifyCurrentProgramHeads($actor, $program, $cycle, $change);
            if (DB::transactionLevel() > 0) {
                DB::afterCommit($notify);
            } else {
                $notify();
            }
        }

        return $change;
    }

    private function notifyCurrentProgramHeads(User $actor, Program $program, AcademicCycle $cycle, ProgramQuotaChange $change): void
    {
        $freshActor = User::query()->find($actor->getKey());
        if ($freshActor === null || $freshActor->actorScope()->office !== 'guidance') {
            return;
        }
        foreach (CapabilityCatalog::legacyTestAdministratorCapabilities() as $capability) {
            if (! $this->authorizer->allows($freshActor, $capability)) {
                return;
            }
        }

        try {
            $recipients = User::query()
                ->whereHas('staffProfile', fn ($query) => $query
                    ->where('institution_id', $program->institution_id)
                    ->where('office', 'program')
                    ->where(fn ($status) => $status->where('status', StaffProfile::STATUS_ACTIVE)->orWhereNull('status')))
                ->get()
                ->filter(fn (User $candidate): bool => (int) $candidate->getKey() !== (int) $freshActor->getKey()
                    && $this->governance->allows($candidate, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, true))
                ->values();
            if ($recipients->isEmpty()) {
                return;
            }

            $intent = NotificationIntent::v1(
                'admission.program_quota_changed.v1',
                'admission.program_quota_changed',
                [
                    'institution_id' => (int) $program->institution_id,
                    'program_id' => (int) $program->getKey(),
                    'academic_cycle_id' => (int) $cycle->getKey(),
                    'quota_change_id' => (int) $change->getKey(),
                    'subject' => 'Program quota updated',
                    'body' => 'A Guidance Test Administrator updated this program quota.',
                    'action_url' => route('setup.quotas', ['program_id' => $program->getKey(), 'cycle_id' => $cycle->getKey()]),
                ],
                [NotificationDelivery::CHANNEL_IN_APP],
            );
            $deliveries = $this->dispatcher->dispatch(
                $intent,
                hash('sha256', 'program_quota_change:'.$change->getKey()),
                $recipients,
                [NotificationDelivery::CHANNEL_IN_APP],
            );
            NotificationIntentDispatcher::enqueueAfterCommit($deliveries);
        } catch (Throwable) {
            // The quota receipt is authoritative; dispatcher retries own delivery recovery.
        }
    }

    private function authorize(User $actor, Program $program, AcademicCycle $cycle): void
    {
        $principal = User::query()->find($actor->getKey());
        $freshProgram = Program::query()->find($program->getKey());
        $freshCycle = AcademicCycle::query()->find($cycle->getKey());
        if ($principal === null || $freshProgram === null || $freshCycle === null
            || (int) $freshProgram->institution_id !== (int) $freshCycle->institution_id
            || ! $this->authorizer->allows($principal, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)
            || ! $this->governance->allows($principal, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $freshProgram, $freshCycle, true)) {
            throw new AuthorizationException('Quota mutation denied.');
        }
    }
}
