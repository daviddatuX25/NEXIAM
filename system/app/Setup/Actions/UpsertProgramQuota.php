<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-087: Program Head PROGRAM-scoped write, or Guidance Counselor (TA) write
 * with optional notice visible to Program Heads via change history.
 */
final class UpsertProgramQuota
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        int $slots,
        ?string $notice = null,
    ): ProgramQuota {
        $this->authorize($actor, $program);

        if ($slots < 0) {
            throw new RuntimeException('Quota slots cannot be negative.');
        }

        $trimmedNotice = $notice !== null ? trim($notice) : null;
        if ($trimmedNotice === '') {
            $trimmedNotice = null;
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $slots, $trimmedNotice): ProgramQuota {
            /** @var ProgramQuota $quota */
            $quota = ProgramQuota::query()->firstOrNew([
                'program_id' => $program->id,
                'academic_cycle_id' => $cycle->id,
            ]);

            $fromSlots = $quota->exists ? (int) $quota->slots : null;

            $quota->slots = $slots;
            $quota->last_notice = $trimmedNotice;
            $quota->last_mutated_by_user_id = $actor->id;
            $quota->save();

            ProgramQuotaChange::query()->create([
                'program_quota_id' => $quota->id,
                'actor_user_id' => $actor->id,
                'from_slots' => $fromSlots,
                'to_slots' => $slots,
                'notice' => $trimmedNotice,
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('setup.program_quota_upserted', [
                'program_quota_id' => $quota->id,
                'program_id' => $program->id,
                'program_code' => $program->code,
                'academic_cycle_id' => $cycle->id,
                'from_slots' => $fromSlots,
                'to_slots' => $slots,
                'notice' => $trimmedNotice,
                'path' => $actor->hasRole('program_head') ? 'program_head' : 'test_administrator',
            ], (string) $actor->id);

            return $quota->fresh(['program', 'academicCycle', 'changes.actor']) ?? $quota;
        });
    }

    private function authorize(User $actor, Program $program): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
            throw new RuntimeException('Quota mutation denied.');
        }

        // Program Head is PROGRAM-scoped; TA (Guidance Counselor) may adjust any program.
        if ($actor->hasRole('program_head')) {
            if (! $this->authorizer->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, [
                'program' => $program->code,
            ])) {
                throw new RuntimeException('Quota mutation denied for this program.');
            }
        }
    }
}
