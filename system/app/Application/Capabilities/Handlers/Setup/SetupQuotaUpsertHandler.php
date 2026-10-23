<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\Setup\Actions\UpsertProgramQuota;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupQuotaUpsertHandler
{
    private const CAPABILITY_ID = 'setup.quotas.upsert@1';

    public function __construct(private readonly UpsertProgramQuota $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $programId = $input['program_id'] ?? null;
        $cycleId = $input['academic_cycle_id'] ?? null;
        $reason = $input['reason'] ?? null;
        $expectedVersion = $input['expected_version'] ?? null;
        $idempotencyKey = $input['idempotency_key'] ?? null;
        if (! is_int($programId) || $programId < 1) {
            throw new RuntimeException('program_id_invalid');
        }
        if (! is_int($cycleId) || $cycleId < 1) {
            throw new RuntimeException('academic_cycle_id_invalid');
        }
        if (! is_int($slots) || $slots < 0) {
            throw new RuntimeException('slots_invalid');
        }
        if (! is_string($reason) || trim($reason) === '') {
            throw new RuntimeException('reason_required');
        }
        if (! is_int($expectedVersion) || $expectedVersion < 0) {
            throw new RuntimeException('expected_version_invalid');
        }
        if (! is_string($idempotencyKey) || ! \Illuminate\Support\Str::isUuid($idempotencyKey)) {
            throw new RuntimeException('idempotency_key_invalid');
        }

        $program = Program::query()->find($programId);
        $cycle = AcademicCycle::query()->find($cycleId);
        if (! $program instanceof Program || ! $cycle instanceof AcademicCycle) {
            throw new RuntimeException('program_or_academic_cycle_not_found');
        }

        $existingQuota = ProgramQuota::query()
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id)
            ->first();
        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'program_id' => (int) $program->id,
                'academic_cycle_id' => (int) $cycle->id,
                'slots' => (int) $slots,
                'reason' => trim($reason),
                'expected_version' => $expectedVersion,
                'idempotency_key' => $idempotencyKey,
            ];
            $programRecord = [
                'type' => 'record',
                'label' => (string) $program->name,
                'context' => [(string) $program->code],
            ];

            $cycleRecord = [
                'type' => 'record',
                'label' => (string) $cycle->code,
                'context' => array_values(array_filter([(string) $cycle->staffLabel()])),
            ];

            $beforeSlots = $existingQuota !== null
                ? ['type' => 'integer', 'value' => (int) $existingQuota->slots, 'semantic' => 'count', 'unit' => 'slots']
                : ['type' => 'missing'];

            $afterSlots = [
                'type' => 'integer',
                'value' => (int) $slots,
                'semantic' => 'count',
                'unit' => 'slots',
            ];

            $beforeNotice = ($existingQuota !== null && $existingQuota->last_notice !== null)
                ? ['type' => 'text', 'value' => (string) $existingQuota->last_notice, 'multiline' => true]
                : ['type' => 'null'];

            $afterReason = ['type' => 'text', 'value' => trim($reason), 'multiline' => true];

            $checks = [
                [
                    'key' => 'program_exists',
                    'label' => 'Academic program',
                    'status' => 'pass',
                    'detail' => "Program {$program->code} exists",
                ],
                [
                    'key' => 'academic_cycle_exists',
                    'label' => 'Academic cycle',
                    'status' => 'pass',
                    'detail' => "Cycle {$cycle->code} exists",
                ],
                [
                    'key' => 'quota_slots_valid',
                    'label' => 'Quota slots',
                    'status' => 'pass',
                    'detail' => "Target quota is {$slots} slots",
                ],
            ];

            $result = [
                'mode' => 'propose',
                'ok' => true,
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'program_id' => (int) $program->id,
                    'academic_cycle_id' => (int) $cycle->id,
                    'slots' => (int) $slots,
                    'reason' => trim($reason),
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'update',
                    'title' => 'Update program quota',
                    'sections' => [
                        ProposalViewContract::fieldsSection('program_quota', 'Program quota allocation', [
                            ProposalViewContract::field('program', 'Academic program', $programRecord, $programRecord, 'context'),
                            ProposalViewContract::field('cycle', 'Academic cycle', $cycleRecord, $cycleRecord, 'context'),
                            ProposalViewContract::field('slots', 'Quota slots', $beforeSlots, $afterSlots, 'choice'),
                            ProposalViewContract::field('reason', 'Reason for change', $beforeNotice, $afterReason, 'choice'),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight('pass', $checks),
                    'coverage' => [
                        'program_id' => ['classification' => 'display', 'field' => 'program'],
                        'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                        'slots' => ['classification' => 'display', 'field' => 'slots'],
                        'reason' => ['classification' => 'display', 'field' => 'reason'],
                    ],
                ],
                'diff' => [[
                    'key' => 'slots',
                    'label' => 'Program quota',
                    'before' => $existingQuota === null ? 'None' : (string) $existingQuota->slots,
                    'after' => (string) $slots,
                ]],
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => $checks,
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $change = $this->action->handle($actor, $program, $cycle, $slots, trim($reason), $expectedVersion, $idempotencyKey);

        return [
            'mode' => 'execute',
            'ok' => true,
            'program_quota_id' => (int) $change->program_quota_id,
            'quota_change_id' => (int) $change->getKey(),
            'program_id' => (int) $program->id,
            'academic_cycle_id' => (int) $cycle->id,
            'slots' => (int) $change->to_slots,
            'version' => (int) $change->version,
        ];
    }
}
