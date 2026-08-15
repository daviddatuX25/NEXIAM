<?php

namespace App\Ai\Proposals;

use App\Application\Capabilities\CapabilityInvocationRequest;
use App\Application\Capabilities\CapabilityKernel;
use App\Ai\Orchestration\RunEventAppender;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiProposal;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiTaskGrant;
use App\Models\User;
use App\Ai\Skills\SkillEngine;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Durable proposal create/confirm — confirm re-invokes Command capabilities (AD-7/20).
 */
final class ProposalService
{
    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_INVALIDATED = 'invalidated';

    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly CapabilityKernel $kernel,
        private readonly SkillEngine $skills,
        private readonly RunEventAppender $events,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $evidence
     */
    public function create(
        User $actor,
        ?string $runId,
        string $conversationId,
        string $capabilityId,
        array $summary,
        array $evidence,
        ?NexiTaskGrant $grant = null,
        ?int $expectedVersion = null,
    ): NexiProposal {
        $conversation = NexiConversation::query()->find($conversationId);
        if ($conversation === null || (string) $conversation->actor_id !== (string) $actor->id) {
            throw new RuntimeException('conversation_denied');
        }

        if ($runId === null) {
            $turnId = (string) Str::uuid();
            $runId = (string) Str::uuid();
            $seq = (int) \App\Models\Ai\NexiTurn::query()->where('conversation_id', $conversationId)->max('seq') + 1;
            \App\Models\Ai\NexiTurn::query()->create([
                'id' => $turnId,
                'conversation_id' => $conversationId,
                'seq' => $seq,
                'mode' => 'assistant',
                'input_text' => '[skill proposal]',
            ]);
            \App\Models\Ai\NexiRun::query()->create([
                'id' => $runId,
                'turn_id' => $turnId,
                'conversation_id' => $conversationId,
                'status' => \App\Models\Ai\NexiRun::STATUS_SUCCEEDED,
                'publication_version' => 'openspec8.v1',
                'gateway_route' => 'skill',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $proposal = NexiProposal::query()->create([
            'id' => (string) Str::uuid(),
            'run_id' => $runId,
            'conversation_id' => $conversationId,
            'status' => self::STATUS_PROPOSED,
            'capability_id' => $capabilityId,
            'expected_version' => $expectedVersion,
            'summary' => [
                'impact' => $summary,
                'evidence' => $evidence,
                'actor_id' => (string) $actor->id,
                'surface_key' => $grant?->surface_key ?? 'staff',
            ],
            'expires_at' => now()->addMinutes(30),
        ]);

        if ($grant !== null) {
            $this->skills->markAwaitingConfirm($grant, $proposal->id);
        }

        return $proposal;
    }

    /**
     * @return array{proposal: NexiProposal, result: array<string, mixed>}
     */
    public function confirm(User $actor, string $proposalId, string $idempotencyKey): array
    {
        unset($idempotencyKey); // reserved for Action-level idempotency keys in handlers

        $proposal = NexiProposal::query()->find($proposalId);
        if ($proposal === null) {
            throw new RuntimeException('proposal_not_found');
        }

        if ($proposal->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException('proposal_not_confirmable');
        }

        if ($proposal->expires_at !== null && $proposal->expires_at->isPast()) {
            $proposal->forceFill(['status' => self::STATUS_INVALIDATED])->save();
            throw new RuntimeException('proposal_expired');
        }

        $summary = $proposal->summary ?? [];
        if ((string) ($summary['actor_id'] ?? '') !== (string) $actor->id) {
            throw new RuntimeException('proposal_actor_mismatch');
        }

        $surfaceKey = (string) ($summary['surface_key'] ?? 'staff');

        $capabilityId = (string) $proposal->capability_id;
        if (! str_contains($capabilityId, '@')) {
            throw new RuntimeException('capability_id_invalid');
        }
        [$key, $version] = explode('@', $capabilityId, 2);

        $impact = is_array($summary['impact'] ?? null) ? $summary['impact'] : [];
        $commandInput = is_array($impact['_command_input'] ?? null)
            ? $impact['_command_input']
            : array_intersect_key($impact, array_flip([
                'session_id',
                'expected_version',
                'admission_attempt_id',
                'reason',
                'exam_session_id',
                'idempotency_key',
            ]));
        $input = $commandInput;
        $input['execute'] = true;

        try {
            $result = $this->kernel->invoke(new CapabilityInvocationRequest(
                key: $key,
                version: (int) $version,
                input: $input,
                actor: $actor,
                channel: 'nexi_safe',
                surfaceKey: $surfaceKey,
                pagePackKey: null,
                correlationId: $proposal->id,
            ));
        } catch (RuntimeException $e) {
            $proposal->forceFill(['status' => self::STATUS_INVALIDATED])->save();
            $this->clearGrantAwaiting($proposal);
            $this->appendProposalEvent($proposal, 'skill.proposal_invalidated', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (! $result->ok) {
            $proposal->forceFill(['status' => self::STATUS_INVALIDATED])->save();
            $this->clearGrantAwaiting($proposal);
            $this->appendProposalEvent($proposal, 'skill.proposal_invalidated', [
                'proposal_id' => $proposal->id,
                'error' => $result->reason ?? 'confirm_denied',
            ]);
            throw new RuntimeException($result->reason ?? 'confirm_denied');
        }

        $proposal->forceFill(['status' => self::STATUS_EXECUTED])->save();

        $this->clearGrantAwaiting($proposal);
        $this->appendProposalEvent($proposal, 'skill.proposal_confirmed', [
            'proposal_id' => $proposal->id,
            'capability_id' => $capabilityId,
            'result' => is_array($result->data) ? $result->data : [],
        ]);

        return [
            'proposal' => $proposal->fresh() ?? $proposal,
            'result' => is_array($result->data) ? $result->data : [],
        ];
    }

    public function cancel(User $actor, string $proposalId): NexiProposal
    {
        $proposal = NexiProposal::query()->find($proposalId);
        if ($proposal === null) {
            throw new RuntimeException('proposal_not_found');
        }

        $summary = $proposal->summary ?? [];
        if ((string) ($summary['actor_id'] ?? '') !== (string) $actor->id) {
            throw new RuntimeException('proposal_actor_mismatch');
        }

        if ($proposal->status === self::STATUS_PROPOSED) {
            $proposal->forceFill(['status' => self::STATUS_CANCELLED])->save();
            $this->appendProposalEvent($proposal, 'skill.proposal_cancelled', [
                'proposal_id' => $proposal->id,
            ]);
        }

        $this->clearGrantAwaiting($proposal);

        return $proposal->fresh() ?? $proposal;
    }

    private function appendProposalEvent(NexiProposal $proposal, string $type, array $payload): void
    {
        $run = NexiRun::query()->find($proposal->run_id);
        if ($run === null) {
            return;
        }

        $this->events->append($run, $type, $payload);
    }

    private function clearGrantAwaiting(NexiProposal $proposal): void
    {
        $grant = NexiTaskGrant::query()
            ->where('status', NexiTaskGrant::STATUS_ACTIVE)
            ->where('actor_id', (string) (($proposal->summary['actor_id'] ?? '')))
            ->get()
            ->first(function (NexiTaskGrant $grant) use ($proposal): bool {
                return ($grant->scope_bounds['pending_proposal_id'] ?? null) === $proposal->id;
            });

        if ($grant !== null) {
            $this->skills->clearAwaitingConfirm($grant);
        }
    }
}
