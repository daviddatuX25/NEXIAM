<?php

namespace App\Ai\Conversation;

use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Jobs\Ai\ProcessNexiRunJob;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiTurn;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TurnSubmitter
{
    public function __construct(
        private readonly ContextEpochService $epochs,
        private readonly UsageBudgetService $budgets,
        private readonly EmergencyStop $emergencyStop,
        private readonly TaskGrantService $grants,
    ) {}

    /**
     * @param  array{page_pack_key?: string|null, page_pack_version?: int|null, mode?: string, idempotency_key?: string|null}  $meta
     * @return array{conversation_id: string, turn_id: string, run_id: string, context_epoch: string}
     */
    public function submit(
        Authenticatable $actor,
        string $surfaceKey,
        string $inputText,
        array $meta = [],
    ): array {
        if ($this->emergencyStop->active()) {
            throw new RuntimeException('emergency_stop');
        }

        $text = trim($inputText);
        if ($text === '') {
            throw new RuntimeException('empty_input');
        }

        $actorId = (string) $actor->getAuthIdentifier();
        if (! $actor instanceof User) {
            throw new RuntimeException('surface_denied');
        }

        $isApplicantAccount = $actor->applicantAccount()->exists();
        $isStaffProfile = $actor->staffProfile()->exists();

        $actorType = match (true) {
            $surfaceKey === 'applicant' && $isApplicantAccount => 'applicant',
            $surfaceKey === 'staff' && $isStaffProfile => 'staff',
            $isApplicantAccount && ! $isStaffProfile => 'applicant',
            $isStaffProfile => 'staff',
            default => null,
        };
        if ($actorType === null) {
            throw new RuntimeException('surface_denied');
        }
        if ($surfaceKey === 'applicant' && $actorType !== 'applicant') {
            throw new RuntimeException('surface_denied');
        }
        if ($surfaceKey === 'staff' && $actorType !== 'staff') {
            throw new RuntimeException('surface_denied');
        }

        $institutionId = null;
        if ($actorType === 'staff') {
            $institutionId = $actor->actorScope()->institutionId;
        } else {
            $account = $actor->applicantAccount()->with('identity')->first();
            $institutionId = $account?->identity?->institution_id;
        }

        $idempotency = $meta['idempotency_key'] ?? null;
        if (is_string($idempotency) && $idempotency !== '') {
            $existing = NexiTurn::query()->with(['run', 'conversation'])->where('idempotency_key', $idempotency)->first();
            if ($existing !== null && $existing->run !== null) {
                return [
                    'conversation_id' => $existing->conversation_id,
                    'turn_id' => $existing->id,
                    'run_id' => $existing->run->id,
                    'context_epoch' => $existing->conversation->context_epoch,
                ];
            }
        }

        if (! $this->budgets->canReserve($actorType, $actorId)) {
            throw new RuntimeException('quota_exhausted');
        }

        $result = DB::transaction(function () use ($actorType, $actorId, $institutionId, $surfaceKey, $text, $meta, $idempotency): array {
            $conversation = $this->epochs->resolveOpenConversation(
                $actorType,
                $actorId,
                $institutionId,
                $surfaceKey,
            );

            $seq = (int) NexiTurn::query()->where('conversation_id', $conversation->id)->max('seq') + 1;
            $turnId = (string) Str::uuid();
            $runId = (string) Str::uuid();

            $turn = NexiTurn::query()->create([
                'id' => $turnId,
                'conversation_id' => $conversation->id,
                'seq' => $seq,
                'mode' => (string) ($meta['mode'] ?? 'inquiry'),
                'page_pack_key' => $meta['page_pack_key'] ?? null,
                'page_pack_version' => $meta['page_pack_version'] ?? null,
                'input_text' => $text,
                'idempotency_key' => is_string($idempotency) && $idempotency !== '' ? $idempotency : null,
            ]);

            NexiRun::query()->create([
                'id' => $runId,
                'turn_id' => $turn->id,
                'conversation_id' => $conversation->id,
                'status' => NexiRun::STATUS_QUEUED,
                'publication_version' => 'openspec3.v1',
                'gateway_route' => 'fake',
            ]);

            $reservation = $this->budgets->reserve($actorType, $actorId, $institutionId, $runId);
            if ($reservation === null) {
                throw new RuntimeException('quota_exhausted');
            }

            return [
                'conversation_id' => $conversation->id,
                'turn_id' => $turn->id,
                'run_id' => $runId,
                'context_epoch' => $conversation->context_epoch,
            ];
        });

        ProcessNexiRunJob::dispatch($result['run_id']);

        if (
            app()->environment('testing')
            || config('queue.default') === 'sync'
            || (bool) config('nexi.process_inline', false)
        ) {
            app(\App\Ai\Orchestration\ProcessNexiRun::class)->handle($result['run_id']);
        }

        return $result;
    }

    public function cancelRun(string $runId, Authenticatable $actor): bool
    {
        $run = NexiRun::query()->find($runId);
        if ($run === null) {
            return false;
        }

        $conversation = $run->turn?->conversation;
        if ($conversation === null) {
            return false;
        }

        if ((string) $actor->getAuthIdentifier() !== $conversation->actor_id) {
            return false;
        }

        if ($run->isTerminal()) {
            return true;
        }

        $run->forceFill(['cancel_requested_at' => now()])->save();

        return true;
    }
}
