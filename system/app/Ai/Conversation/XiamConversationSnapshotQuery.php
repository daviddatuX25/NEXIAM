<?php

namespace App\Ai\Conversation;

use App\Ai\Adapters\LaravelAi\XiamProjectorRegistry;
use App\Ai\Context\XiamNavigationTargets;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Privacy\PiiTokenVault;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRunEvent;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class XiamConversationSnapshotQuery
{
    public function __construct(
        private readonly PiiTokenVault $piiTokenVault,
        private readonly UsageBudgetService $usageBudget,
        private readonly ?XiamNavigationTargets $navigation = null,
        private readonly ?XiamProjectorRegistry $projector = null,
    ) {}

    /**
     * @return array{
     *     conversation_id: string,
     *     context_epoch: string,
     *     surface_key: string,
     *     status: string,
     *     cursor: int,
     *     run: array{id: string, status: string}|null,
     *     proposals: list<array{id: string, status: string}>,
     *     proposals_by_id?: array<string, array<string, mixed>>,
     *     proposals_by_run?: array<string, array<string, mixed>>,
     *     events: list<array{id: string, run_id: string, seq: int, type: string, payload: mixed, created_at: string|null}>,
     *     messages: list<array<string, mixed>>,
     *     usage: array{state: 'available'|'near_limit'|'exhausted', used: int, limit: int, remaining: int|null, credit_balance: int, unit: string, period: string, resets_at: string|null}
     * }|null
     */
    public function handle(?Authenticatable $actor, string $conversationId, int $after = 0): ?array
    {
        $model = $this->authorizedConversation($actor, $conversationId);
        if ($model === null) {
            return null;
        }

        $after = max(0, $after);

        return DB::transaction(function () use ($actor, $model, $after): ?array {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('set transaction isolation level repeatable read');
            }

            $locked = XiamConversation::query()->lockForUpdate()->find($model->id);
            if ($locked === null || ! $this->matchesActorScope($locked, $actor)) {
                return null;
            }

            $usage = $this->usageBudget->view((string) $locked->actor_type, (string) $actor->getAuthIdentifier());
            $events = XiamRunEvent::query()
                ->where('conversation_id', $locked->id)
                ->where('seq', '>', $after)
                ->orderBy('seq')
                ->limit(500)
                ->get()
                ->map(function (XiamRunEvent $event) use ($actor, $locked): array {
                    $payload = is_array($event->payload) ? $event->payload : [];
                    if ($event->type === 'assistant.message') {
                        $payload = $this->projectAssistantPayload($payload, $actor, (string) $locked->actor_type);
                    } elseif ($event->type === 'assistant.navigation') {
                        $payload['navigation'] = ($this->navigation ?? app(XiamNavigationTargets::class))->reproject(
                            $actor instanceof User ? $actor : null,
                            is_array($payload['navigation'] ?? null) ? $payload['navigation'] : [],
                            (string) $locked->actor_type,
                        );
                    }

                    return [
                        'id' => $event->id,
                        'run_id' => $event->run_id,
                        'seq' => $event->seq,
                        'type' => $event->type,
                        'payload' => $payload,
                        'created_at' => $event->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();

            $latestRun = $locked->runs()->latest('created_at')->first();
            $cursor = (int) XiamRunEvent::query()->where('conversation_id', $locked->id)->max('seq');

            $projector = $this->projector ?? app(XiamProjectorRegistry::class);
            $conversationProposals = XiamProposal::query()
                ->where('conversation_id', $locked->id)
                ->orderBy('created_at')
                ->get();

            $proposalsById = [];
            $proposalsByRunId = [];
            $allProposalsByRunId = [];
            foreach ($conversationProposals as $proposal) {
                $rawSummary = is_array($proposal->summary) ? $proposal->summary : [];
                $rawReview = is_array($proposal->proposal_view) ? $proposal->proposal_view : [];

                $safeView = $projector->safeProposalView([
                    'id' => $proposal->id,
                    'proposal_id' => $proposal->id,
                    'run_id' => (string) ($proposal->run_id ?? ''),
                    'capability_id' => (string) $proposal->capability_id,
                    'status' => (string) $proposal->status,
                    'safety_tier' => $proposal->safety_tier,
                    'confirmation_phrase' => $proposal->confirmation_phrase,
                    'requires_reauth' => (bool) $proposal->requires_reauth,
                    'expires_at' => $proposal->expires_at?->toIso8601String(),
                    'proposal_view' => $rawReview,
                    'receipt' => is_array($proposal->receipt) ? $proposal->receipt : null,
                    'summary' => $rawSummary,
                ]);

                $entry = [
                    'id' => $proposal->id,
                    'run_id' => $proposal->run_id,
                    'status' => $proposal->status,
                    'capability_id' => $proposal->capability_id,
                    'diff' => $rawReview['diff'] ?? null,
                    'preflight_checks' => $rawReview['preflight_checks'] ?? ($safeView['preflight']['checks'] ?? null),
                    'proposal_view' => $safeView,
                    'proposal_review' => $safeView['proposal_review'] ?? $safeView,
                ];

                $proposalsById[$proposal->id] = $entry;
                if ($proposal->run_id !== null && $proposal->run_id !== '') {
                    $proposalsByRunId[$proposal->run_id] = $entry;
                    $allProposalsByRunId[$proposal->run_id][] = $entry;
                }
            }

            $messages = [];
            foreach ($locked->turns()->orderBy('seq')->with('run.events')->get() as $turn) {
                $assistant = $turn->run?->events->firstWhere('type', 'assistant.message');
                $refusal = $turn->run?->events->firstWhere('type', 'assistant.refusal');

                if ($turn->run?->status === 'failed' && $assistant === null && $refusal === null) {
                    continue;
                }

                $messages[] = [
                    'role' => 'user',
                    'content' => $this->piiTokenVault->detokenize(
                        (string) $turn->input_text,
                        $locked->id,
                        $locked->context_epoch,
                    ),
                    'turn_id' => $turn->id,
                ];

                $runId = $turn->run?->id;
                $matchedProposal = null;
                $matchedProposals = [];
                if ($assistant !== null && isset($assistant->payload['proposal_ids']) && is_array($assistant->payload['proposal_ids'])) {
                    foreach ($assistant->payload['proposal_ids'] as $pid) {
                        if (is_string($pid) && isset($proposalsById[$pid])) {
                            $matchedProposals[] = $proposalsById[$pid];
                        }
                    }
                }
                if ($matchedProposals === [] && $runId !== null && isset($allProposalsByRunId[$runId])) {
                    $matchedProposals = $allProposalsByRunId[$runId];
                }
                if ($matchedProposals !== []) {
                    $matchedProposal = $matchedProposals[count($matchedProposals) - 1];
                } elseif ($assistant !== null && isset($assistant->payload['proposal_id']) && is_string($assistant->payload['proposal_id'])) {
                    $matchedProposal = $proposalsById[$assistant->payload['proposal_id']] ?? null;
                    if ($matchedProposal !== null) {
                        $matchedProposals = [$matchedProposal];
                    }
                } elseif ($runId !== null && isset($proposalsByRunId[$runId])) {
                    $matchedProposal = $proposalsByRunId[$runId];
                    if ($matchedProposal !== null) {
                        $matchedProposals = [$matchedProposal];
                    }
                }
                if ($assistant !== null) {
                    $assistantMsg = [
                        ...$this->projectAssistantPayload([
                            'content' => $this->piiTokenVault->detokenize(
                                (string) ($assistant->payload['content'] ?? ''),
                                $locked->id,
                                $locked->context_epoch,
                            ),
                            'citations' => $assistant->payload['citations'] ?? [],
                            'navigation' => $assistant->payload['navigation'] ?? [],
                            'finish_reason' => $assistant->payload['finish_reason'] ?? null,
                        ], $actor, (string) $locked->actor_type),
                        'role' => 'assistant',
                        'run_id' => $runId,
                        'turn_id' => $turn->id,
                    ];
                    if ($matchedProposal !== null) {
                        $assistantMsg['proposal_id'] = $matchedProposal['id'];
                        $assistantMsg['proposal'] = $matchedProposal['proposal_view'];
                    }
                    if ($matchedProposals !== []) {
                        $assistantMsg['proposal_ids'] = array_column($matchedProposals, 'id');
                        $assistantMsg['proposals'] = array_map(fn (array $p): array => $p['proposal_view'], $matchedProposals);
                    }
                    $messages[] = $assistantMsg;
                } else {
                    if ($refusal !== null) {
                        $refusalMsg = [
                            'role' => 'assistant',
                            'content' => 'No approved policy citation is available for that question.',
                            'run_id' => $runId,
                            'turn_id' => $turn->id,
                            'citations' => [],
                            'finish_reason' => 'refuse',
                        ];
                        if ($matchedProposal !== null) {
                            $refusalMsg['proposal_id'] = $matchedProposal['id'];
                            $refusalMsg['proposal'] = $matchedProposal['proposal_view'];
                        }
                        $messages[] = $refusalMsg;
                    }
                }
            }

            return [
                'conversation_id' => $locked->id,
                'context_epoch' => $locked->context_epoch,
                'surface_key' => $locked->surface_key,
                'status' => $locked->status,
                'cursor' => $cursor,
                'run' => $latestRun === null ? null : [
                    'id' => $latestRun->id,
                    'status' => $latestRun->status,
                    'failure_reason' => $latestRun->failure_reason,
                ],
                'proposals' => array_values(array_map(fn (array $p): array => [
                    'id' => $p['id'],
                    'status' => $p['status'],
                ], $proposalsById)),
                'proposals_by_id' => $proposalsById,
                'proposals_by_run' => $proposalsByRunId,
                'all_proposals_by_run' => $allProposalsByRunId,
                'events' => $events,
                'messages' => $messages,
                'usage' => $usage,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function projectAssistantPayload(array $payload, ?Authenticatable $actor, string $actorType): array
    {
        $navigation = $this->navigation ?? app(XiamNavigationTargets::class);
        $principal = $actor instanceof User ? $actor : null;
        $projected = $navigation->projectResponse((string) ($payload['content'] ?? ''), $principal, $actorType);
        $payload['content'] = $projected['text'];
        $payload['navigation'] = $navigation->reproject(
            $principal,
            is_array($payload['navigation'] ?? null) ? $payload['navigation'] : [],
            $actorType,
        );

        return $payload;
    }

    public function authorizedConversation(?Authenticatable $actor, string $conversationId): ?XiamConversation
    {
        if ($actor === null) {
            return null;
        }

        $model = XiamConversation::query()->find($conversationId);
        if ($model === null || ! $this->matchesActorScope($model, $actor)) {
            return null;
        }

        return $model;
    }

    private function matchesActorScope(XiamConversation $conversation, ?Authenticatable $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($conversation->actor_id !== (string) $actor->getAuthIdentifier()) {
            return false;
        }

        return in_array($conversation->actor_type, ['staff', 'applicant'], true);
    }
}
