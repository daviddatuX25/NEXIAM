<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramPoolOption;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-089: assigned Program Head clears / declines / requests appearance on a
 * targeted follow-up. A decline writes the immutable decline authority and
 * terminalizes any eligible provisional selection; `enrolling` stays intact.
 * request_appearance is non-terminal.
 */
class ResolveProgramFollowUp
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(
        ProgramFollowUp $followUp,
        User $ph,
        string $outcome,
        ?string $reason,
        string $idempotencyKey,
    ): ProgramFollowUp {

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) AdmissionAttempt::query()->whereKey($followUp->admission_attempt_id)->value('academic_cycle_id'), fn (): ProgramFollowUp => DB::transaction(function () use ($followUp, $ph, $outcome, $reason, $idempotencyKey): ProgramFollowUp {
            $attempt = AdmissionAttempt::query()->whereKey($followUp->admission_attempt_id)->lockForUpdate()->firstOrFail();
            $locked = ProgramFollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->admission_attempt_id !== (int) $attempt->id) {
                throw new RuntimeException('Follow-up does not belong to the locked admission attempt.');
            }
            $this->idempotency->claim($ph, 'program_head.follow_up.resolve', 'follow_up:'.$followUp->id, [
                'outcome' => $outcome,
                'reason' => $reason,
            ], $idempotencyKey);

            if (! $locked->isOpen()) {
                throw new RuntimeException('This follow-up is already resolved.');
            }

            if (! in_array($outcome, [
                ProgramFollowUp::STATUS_CLEARED_BY_PH,
                ProgramFollowUp::STATUS_DECLINED_BY_PH,
                ProgramFollowUp::STATUS_APPEARANCE_REQUESTED,
            ], true)) {
                throw new RuntimeException('Unknown follow-up outcome.');
            }

            if ($outcome !== ProgramFollowUp::STATUS_APPEARANCE_REQUESTED && blank($reason)) {
                throw new RuntimeException('A resolution reason is required for a terminal outcome.');
            }

            if ($outcome === ProgramFollowUp::STATUS_CLEARED_BY_PH) {
                $pool = $locked->program_pool_id !== null
                    ? ProgramPool::query()->find($locked->program_pool_id)
                    : ProgramPool::currentFor($attempt->id);
                $option = $pool?->options()
                    ->where('program_id', $locked->program_id)
                    ->first();

                $standing = ProgramSelection::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                    ->lockForUpdate()
                    ->first();

                $standingTargetsFollowUp = $standing !== null
                    && (int) $standing->program_id === (int) $locked->program_id;
                $standingOptionExists = $standingTargetsFollowUp && ProgramPoolOption::query()
                    ->whereKey($standing->program_pool_option_id)
                    ->where('program_pool_id', $standing->program_pool_id)
                    ->where('program_id', $standing->program_id)
                    ->exists();

                if ($standingTargetsFollowUp && ! $standingOptionExists) {
                    throw new RuntimeException('Invariant violation: the standing selection target is missing from its referenced program pool option.');
                }
                if ($pool !== null && (int) $pool->admission_attempt_id !== (int) $attempt->id) {
                    throw new RuntimeException('Invariant violation: the follow-up pool does not belong to the locked admission attempt.');
                }

                $currentPool = ProgramPool::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->orderByDesc('version')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $targetDeclinedInLinkedPool = $pool !== null && ProgramDecline::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('program_id', $locked->program_id)
                    ->where('program_pool_id', $pool->id)
                    ->exists();
                $targetDeclinedInCurrentPool = $currentPool !== null && ProgramDecline::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('program_id', $locked->program_id)
                    ->where('program_pool_id', $currentPool->id)
                    ->exists();
                $targetDeclinedInPool = $targetDeclinedInLinkedPool || $targetDeclinedInCurrentPool;

                $isEnrolling = AttemptProgramFacts::isEnrolling($attempt);

                $poolOptions = null;
                if ($targetDeclinedInPool && ! $isEnrolling) {
                    if ($currentPool === null || (int) $currentPool->admission_attempt_id !== (int) $attempt->id) {
                        throw new RuntimeException('Invariant violation: a declined follow-up pool has no current program pool predecessor.');
                    }
                    $poolOptions = $currentPool->options()->orderBy('display_order')->get();
                    if ($poolOptions->isEmpty()) {
                        throw new RuntimeException('Invariant violation: the current declined follow-up pool has no options.');
                    }
                }

                // PH clearance is a separate approval, not applicant acknowledgement.
                // A pending advised default becomes active without replacing its provenance.
                if ($pool === null || ($option === null && ! $targetDeclinedInPool)) {
                    $targetCode = Program::query()->whereKey($locked->program_id)->value('code');
                    $isPreferenceTarget = $targetCode !== null && in_array($targetCode, [
                        $attempt->program_preference_1_code,
                        $attempt->program_preference_2_code,
                        $attempt->program_preference_3_code,
                    ], true);

                    if (! $isPreferenceTarget && ! $standingTargetsFollowUp) {
                        throw new RuntimeException('The referred program must be present in an active Guidance program pool before it can be confirmed.');
                    }
                }

                if ($targetDeclinedInPool && ! $isEnrolling) {
                    $successor = ProgramPool::query()->create([
                        'admission_attempt_id' => $attempt->id,
                        'version' => $currentPool->version + 1,
                        'predecessor_id' => $currentPool->id,
                        'actor_user_id' => $ph->id,
                        'reason' => 'Program Head reconsideration of declined referral target.',
                        'source' => 'follow_up_resolution',
                        'idempotency_key' => $idempotencyKey.'-pool-v'.($currentPool->version + 1),
                        'created_at' => now(),
                    ]);

                    $targetOptionExists = false;
                    $maxDisplayOrder = 0;
                    foreach ($poolOptions as $poolOption) {
                        $isTargetOption = (int) $poolOption->program_id === (int) $locked->program_id;
                        if ($isTargetOption && $targetOptionExists) {
                            continue;
                        }

                        $successor->options()->create([
                            'program_id' => $poolOption->program_id,
                            'display_order' => $poolOption->display_order,
                            'basis' => $poolOption->basis,
                        ]);
                        $maxDisplayOrder = max($maxDisplayOrder, (int) $poolOption->display_order);
                        $targetOptionExists = $targetOptionExists || $isTargetOption;
                    }
                    if (! $targetOptionExists) {
                        $successor->options()->create([
                            'program_id' => $locked->program_id,
                            'display_order' => $maxDisplayOrder + 1,
                            'basis' => 'referral_target',
                        ]);
                    }

                    $pool = $successor;
                    $option = $pool->options()
                        ->where('program_id', $locked->program_id)
                        ->firstOrFail();
                    $locked->program_pool_id = $pool->id;
                }

                if (! $isEnrolling && ! $standingTargetsFollowUp && $pool !== null && $option !== null) {
                    $quotaProgramIds = [$locked->program_id];
                    if ($standing !== null) {
                        $quotaProgramIds[] = $standing->program_id;
                    }
                    QuotaReservation::lockQuotaRows($attempt, $quotaProgramIds);
                    QuotaReservation::assertCapacityFor($attempt, $locked->program_id);
                }

                if (! $isEnrolling) {
                    if ($pool === null || $option === null) {
                        // An ordinary preference/standing referral outside the pool
                        // is approval only; it has no option to reserve or select.
                    } elseif (! $standingTargetsFollowUp) {
                        if ($standing !== null) {
                            SelectionGuard::terminalize($standing, ProgramSelection::STATUS_SUPERSEDED, 'Department Head cleared a different referred program.');
                        }

                        ProgramSelection::query()->create([
                            'admission_attempt_id' => $attempt->id,
                            'program_pool_id' => $pool->id,
                            'program_pool_option_id' => $option->id,
                            'program_id' => $locked->program_id,
                            'academic_cycle_id' => $attempt->academic_cycle_id,
                            'status' => ProgramSelection::STATUS_ACTIVE,
                            'source' => ProgramSelection::SOURCE_GUIDANCE_DEFAULT,
                            'actor_user_id' => $ph->id,
                            'applicant_acknowledged_at' => null,
                            'idempotency_key' => 'follow-up-clear-'.$locked->id,
                            'version' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } elseif ($standing->status === ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT) {
                        $standing->forceFill([
                            'status' => ProgramSelection::STATUS_ACTIVE,
                            'version' => $standing->version + 1,
                            'updated_at' => now(),
                        ])->save();
                    }
                }

                $latestDecision = ConsultationDecision::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->orderByDesc('version')
                    ->orderByDesc('id')
                    ->first();

                ConsultationDecision::query()->create([
                    'admission_attempt_id' => $attempt->id,
                    'counselor_user_id' => $ph->id,
                    'decision_type' => 'approve',
                    'notes' => 'Cleared and endorsed by Department Head',
                    'idempotency_key' => 'follow-up-clear-'.$locked->id.'-advice',
                    'version' => $latestDecision === null ? 1 : $latestDecision->version + 1,
                ]);
            }

            if ($outcome === ProgramFollowUp::STATUS_DECLINED_BY_PH) {
                $pool = $locked->program_pool_id !== null
                    ? ProgramPool::query()->find($locked->program_pool_id)
                    : ProgramPool::currentFor($attempt->id);

                if ($pool === null) {
                    $pool = ProgramPool::query()->create([
                        'admission_attempt_id' => $attempt->id,
                        'version' => 1,
                        'predecessor_id' => null,
                        'actor_user_id' => $ph->id,
                        'reason' => 'Referral resolution pool',
                        'source' => 'follow_up_resolution',
                        'idempotency_key' => $idempotencyKey.'-pool',
                        'created_at' => now(),
                    ]);
                    $pool->options()->create([
                        'program_id' => $locked->program_id,
                        'display_order' => 1,
                        'basis' => 'referral_target',
                    ]);
                }

                ProgramDecline::query()->create([
                    'admission_attempt_id' => $attempt->id,
                    'program_id' => $locked->program_id,
                    'program_pool_id' => $pool->id,
                    'follow_up_id' => $locked->id,
                    'declined_by_user_id' => $ph->id,
                    'source' => ProgramDecline::SOURCE_FOLLOW_UP,
                    'reason' => (string) $reason,
                    'idempotency_key' => $idempotencyKey.'-decline',
                    'created_at' => now(),
                ]);

                QuotaReservation::lockQuotaRows($attempt, [$locked->program_id]);
                $standing = ProgramSelection::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('program_id', $locked->program_id)
                    ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                    ->lockForUpdate()
                    ->first();

                if ($standing !== null) {
                    SelectionGuard::terminalize($standing, ProgramSelection::STATUS_PROGRAM_DECLINED, 'Assigned Program Head declined this program.');
                }
            }

            $locked->forceFill([
                'status' => $outcome,
                'resolved_by_user_id' => $ph->id,
                'resolution_reason' => $reason,
                'version' => $locked->version + 1,
                'resolved_at' => $outcome === ProgramFollowUp::STATUS_APPEARANCE_REQUESTED ? null : now(),
                'updated_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle('program.follow_up_resolved', [
                'follow_up_id' => $locked->id,
                'attempt_id' => $attempt->id,
                'program_id' => $locked->program_id,
                'outcome' => $outcome,
                'reason' => $reason,
            ], (string) $ph->id);

            return $locked->fresh();
        }));
    }
}
