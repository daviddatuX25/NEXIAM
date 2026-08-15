<?php

namespace App\Ai\Orchestration;

use App\Ai\Applicant\ApplicantOwnDataProjector;
use App\Ai\Contracts\AiGateway;
use App\Ai\Knowledge\KnowledgeRouter;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Skills\ApplicantChooseSlotTurnService;
use App\Ai\Skills\ExamSessionAssistantTurnService;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProcessNexiRun
{
    public function __construct(
        private readonly AiGateway $gateway,
        private readonly OutputFirewall $firewall,
        private readonly RunEventAppender $events,
        private readonly UsageBudgetService $budgets,
        private readonly EmergencyStop $emergencyStop,
        private readonly KnowledgeRouter $knowledgeRouter,
        private readonly ApplicantOwnDataProjector $ownData,
        private readonly ExamSessionAssistantTurnService $examAssistant,
        private readonly ApplicantChooseSlotTurnService $slotAssistant,
    ) {}

    public function handle(string $runId): void
    {
        $run = NexiRun::query()->with(['turn', 'conversation'])->find($runId);
        if ($run === null) {
            return;
        }

        if ($run->isTerminal()) {
            return;
        }

        if ($this->emergencyStop->active()) {
            $this->fail($run, 'emergency_stop', release: true);

            return;
        }

        if ($run->cancel_requested_at !== null) {
            $this->gateway->requestCancel($run->id);
            $this->cancel($run);

            return;
        }

        $run->forceFill([
            'status' => NexiRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $this->events->append($run, 'run.started', [
            'gateway' => (string) config('nexi.ai.gateway', 'fake'),
        ]);

        $turn = $run->turn;
        if ($turn === null) {
            $this->fail($run, 'turn_missing', release: true);

            return;
        }

        /** @var NexiConversation|null $conversation */
        $conversation = $run->conversation;

        if ($turn->mode === 'assistant') {
            $this->handleAssistantTurn($run, $turn, $conversation);

            return;
        }

        $actorType = $conversation?->actor_type ?? 'staff';
        $retrieval = $this->knowledgeRouter->resolvePolicyInquiry($turn->input_text, [
            'actor_type' => $actorType,
            'institution_id' => $conversation?->institution_id,
            'purpose' => 'policy_inquiry',
        ]);

        $this->events->append($run, 'knowledge.retrieval', [
            'decision_id' => $retrieval->decisionId,
            'outcome' => $retrieval->outcome,
            'reason' => $retrieval->reason,
            'hit_count' => count($retrieval->hits),
            'hits' => array_map(fn (array $hit) => [
                'version_id' => $hit['version_id'],
                'source_id' => $hit['source_id'],
                'source_title' => $hit['source_title'] ?? null,
                'excerpt' => $hit['excerpt'],
                'score' => $hit['score'],
            ], $retrieval->hits),
        ]);

        $ownData = null;
        $ownDataRelevant = false;
        if ($actorType === 'applicant' && $conversation !== null) {
            $ownData = $this->ownData->forActorId($conversation->actor_id);
            $ownDataRelevant = $this->ownData->relevantTo($turn->input_text, $ownData);
            $this->events->append($run, 'own_data.projection', [
                'available' => (bool) ($ownData['available'] ?? false),
                'reason' => $ownData['reason'] ?? null,
                'relevant' => $ownDataRelevant,
                'results_released' => (bool) ($ownData['results']['released'] ?? false),
                'attempt_status' => $ownData['attempt']['status'] ?? null,
            ]);
        }

        $citations = [];
        if ($retrieval->hasSupport()) {
            $citations = array_map(fn (array $hit) => [
                'version_id' => $hit['version_id'],
                'source_id' => $hit['source_id'],
                'source_title' => $hit['source_title'] ?? null,
                'excerpt' => $hit['excerpt'],
            ], $retrieval->hits);
            $this->events->append($run, 'assistant.citations', [
                'decision_id' => $retrieval->decisionId,
                'citations' => $citations,
            ]);
        } elseif (! $ownDataRelevant) {
            $this->events->append($run, 'assistant.refusal', [
                'reason' => $retrieval->reason ?? 'no_support',
                'decision_id' => $retrieval->decisionId,
                'mode' => 'no_policy_citation',
            ]);
        }

        $assembled = '';
        try {
            foreach ($this->gateway->stream([
                'prompt' => $turn->input_text,
                'conversation_id' => $run->conversation_id,
                'run_id' => $run->id,
                'mode' => $turn->mode,
                'actor_type' => $actorType,
                'evidence' => $retrieval->hits,
                'own_data' => $ownDataRelevant ? $ownData : null,
                'require_evidence' => $retrieval->hasSupport(),
            ]) as $chunk) {
                $run->refresh();
                if ($run->cancel_requested_at !== null || $this->emergencyStop->active()) {
                    $this->gateway->requestCancel($run->id);
                    $this->cancel($run);

                    return;
                }

                $type = (string) ($chunk['type'] ?? '');
                if ($type === 'delta') {
                    $text = (string) ($chunk['text'] ?? '');
                    $assembled .= $text;
                    $this->events->append($run, 'assistant.delta', ['text' => $text]);
                }

                if ($type === 'cancelled') {
                    $this->cancel($run);

                    return;
                }

                if ($type === 'done') {
                    $safe = $this->firewall->assembleAssistantText($assembled);
                    $this->events->append($run, 'assistant.message', [
                        'role' => 'assistant',
                        'content' => $safe,
                        'finish_reason' => $chunk['finish_reason'] ?? 'stop',
                        'usage' => $chunk['usage'] ?? null,
                        'citations' => $citations,
                    ]);
                    $this->events->append($run, 'run.completed', ['status' => 'succeeded']);

                    DB::transaction(function () use ($run): void {
                        $run->forceFill([
                            'status' => NexiRun::STATUS_SUCCEEDED,
                            'finished_at' => now(),
                        ])->save();
                        $this->budgets->settle($run->id);
                    });

                    return;
                }
            }
        } catch (\Throwable $e) {
            $reason = str_starts_with($e->getMessage(), 'provider_unavailable')
                ? 'provider_unavailable'
                : 'gateway_error';
            $this->fail($run, $reason, release: true);

            return;
        }

        if ($assembled !== '') {
            $safe = $this->firewall->assembleAssistantText($assembled);
            $this->events->append($run, 'assistant.message', [
                'role' => 'assistant',
                'content' => $safe,
                'finish_reason' => 'stop',
                'citations' => $citations,
            ]);
            $this->events->append($run, 'run.completed', ['status' => 'succeeded']);
            $run->forceFill([
                'status' => NexiRun::STATUS_SUCCEEDED,
                'finished_at' => now(),
            ])->save();
            $this->budgets->settle($run->id);

            return;
        }

        $this->fail($run, 'empty_gateway_output', release: true);
    }

    private function handleAssistantTurn(
        NexiRun $run,
        \App\Models\Ai\NexiTurn $turn,
        ?NexiConversation $conversation,
    ): void {
        $actorId = $conversation?->actor_id;
        $actor = is_string($actorId) ? User::query()->find($actorId) : null;
        if (! $actor instanceof User) {
            $this->fail($run, 'actor_missing', release: true);

            return;
        }

        try {
            $outcome = $conversation?->actor_type === 'applicant'
                ? $this->slotAssistant->handle($run, $turn, $actor)
                : $this->examAssistant->handle($run, $turn, $actor);
        } catch (\Throwable $e) {
            $this->events->append($run, 'skill.result', [
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
            $this->fail($run, 'assistant_skill_error', release: true);

            return;
        }

        $safe = $this->firewall->assembleAssistantText((string) $outcome['content']);
        $this->events->append($run, 'assistant.message', [
            'role' => 'assistant',
            'content' => $safe,
            'finish_reason' => 'stop',
            'citations' => [],
            'proposal_id' => $outcome['proposal']['id'] ?? null,
        ]);
        $this->events->append($run, 'run.completed', [
            'status' => 'succeeded',
            'mode' => 'assistant',
            'proposal_id' => $outcome['proposal']['id'] ?? null,
        ]);

        DB::transaction(function () use ($run): void {
            $run->forceFill([
                'status' => NexiRun::STATUS_SUCCEEDED,
                'finished_at' => now(),
            ])->save();
            $this->budgets->settle($run->id);
        });
    }

    private function cancel(NexiRun $run): void
    {
        if ($run->isTerminal()) {
            return;
        }

        $this->events->append($run, 'run.cancelled', []);
        $run->forceFill([
            'status' => NexiRun::STATUS_CANCELLED,
            'finished_at' => now(),
            'failure_reason' => 'cancelled',
        ])->save();
        $this->budgets->release($run->id);
    }

    private function fail(NexiRun $run, string $reason, bool $release): void
    {
        if ($run->isTerminal()) {
            return;
        }

        $this->events->append($run, 'run.failed', ['reason' => $reason]);
        $run->forceFill([
            'status' => NexiRun::STATUS_FAILED,
            'finished_at' => now(),
            'failure_reason' => $reason,
        ])->save();

        if ($release) {
            $this->budgets->release($run->id);
        } else {
            $this->budgets->settle($run->id);
        }
    }
}
