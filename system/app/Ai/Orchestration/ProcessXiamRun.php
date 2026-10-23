<?php

declare(strict_types=1);

namespace App\Ai\Orchestration;

use App\Ai\Adapters\LaravelAi\DynamicAiProviderResolver;
use App\Ai\Context\InvocationContext;
use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Context\XiamNavigationTargets;
use App\Ai\Contracts\AiGateway;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Telemetry\GenAiTelemetryContext;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunStep;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcessXiamRun
{
    public function __construct(
        private readonly AiGateway $gateway,
        private readonly OutputFirewall $firewall,
        private readonly RunEventAppender $events,
        private readonly UsageBudgetService $budgets,
        private readonly EmergencyStop $emergencyStop,
        private readonly TaskGrantService $grants,
        private readonly ?AssistantToolCatalog $catalog = null,
        private readonly ?XiamNavigationTargets $navigation = null,
    ) {}

    public function handle(string $runId): void
    {
        $run = XiamRun::query()->with(['turn', 'conversation'])->find($runId);
        if ($run === null || $run->isTerminal()) {
            return;
        }
        if ($this->emergencyStop->active($run->conversation?->institution_id === null ? null : (int) $run->conversation->institution_id)) {
            $this->fail($run, 'emergency_stop', release: true);

            return;
        }
        if ($run->cancel_requested_at !== null) {
            $this->gateway->requestCancel($run->id);
            $this->cancel($run);

            return;
        }

        $run->forceFill([
            'status' => XiamRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
        ])->save();
        $this->events->append($run, 'run.started', [
            'gateway' => (string) config('xiam.ai.gateway', 'fake'),
        ]);

        $turn = $run->turn;
        if ($turn === null) {
            $this->fail($run, 'turn_missing', release: true);

            return;
        }

        /** @var XiamConversation|null $conversation */
        $conversation = $run->conversation;
        $trusted = $this->resolveTrustedRunActorAndContext($run, $turn, $conversation);
        if ($trusted === null) {
            return;
        }
        [$actor, $context] = $trusted;
        $actorType = $context->actorType;
        $activeSkill = null;
        if ($context->grantId !== null && $context->grantId !== '') {
            $grant = $this->grants->findActive($context->grantId);
            $activeSkill = $grant?->skill_name;
            if ($activeSkill === null) {
                $activeSkill = $this->resolveActiveSkill($grant?->skill_key);
            }
        }
        if ($activeSkill === null) {
            $activeSkill = $this->resolveActiveSkill($context->routeName);
        }

        GenAiTelemetryContext::set($run->id, $context->correlationId);

        try {
            $assembled = '';
            $providerConfiguration = app(DynamicAiProviderResolver::class)->resolveForInstitution($context->institutionId ?? 1);
            $providerStep = app(XiamRunExecutionBudget::class)->beginProviderStep(
                $run->id,
                $providerConfiguration['provider'],
                $providerConfiguration['model'],
                (string) Str::uuid(),
                1,
                true,
                $providerConfiguration,
            );
            $providerStartedAt = microtime(true);
            $bufferedBytes = 0;
            try {
                foreach ($this->gateway->stream([
                    'prompt' => $turn->input_text,
                    'conversation_id' => $run->conversation_id,
                    'run_id' => $run->id,
                    'turn_id' => $turn->id,
                    'actor' => $actor,
                    'context' => $context,
                    'active_skill' => $activeSkill,
                ]) as $chunk) {
                    $run->refresh();
                    if ($run->cancel_requested_at !== null || $this->emergencyStop->active($context->institutionId)) {
                        $this->gateway->requestCancel($run->id);
                        $this->cancel($run);

                        return;
                    }
                    if (! is_array($chunk) || ! is_string($chunk['type'] ?? null)) {
                        $this->fail($run, 'gateway_invalid_event', release: true);

                        return;
                    }
                    $type = $chunk['type'];
                    if ($type === 'cancelled' || ($type === 'done' && ($chunk['finish_reason'] ?? null) === 'cancelled')) {
                        $this->cancel($run);

                        return;
                    }
                    if ($type === 'timeout' || $type === 'error') {
                        $this->fail($run, $type === 'timeout' ? 'provider_timeout' : 'gateway_error', release: true);

                        return;
                    }
                    if ($type === 'delta') {
                        $text = $chunk['text'] ?? null;
                        if (! is_string($text)) {
                            $this->fail($run, 'gateway_invalid_event', release: true);

                            return;
                        }
                        $chunkBytes = strlen($text);
                        if ($chunkBytes > OutputFirewall::MAX_BUFFER_BYTES - $bufferedBytes) {
                            $this->gateway->requestCancel($run->id);
                            $this->completeWithSafeRefusal($run, 'output_limit_exceeded');

                            return;
                        }
                        $assembled .= $text;
                        $bufferedBytes += $chunkBytes;

                        continue;
                    }
                    if ($type === 'tool_call') {
                        $this->events->append($run, 'tool.call', [
                            'tool' => $this->resolveAllowlistedToolName($chunk['tool'] ?? null),
                            'tool_call_id' => is_string($chunk['id'] ?? null) ? $chunk['id'] : null,
                        ]);

                        continue;
                    }
                    if ($type === 'tool_result') {
                        $this->events->append($run, 'tool.result', [
                            'tool' => $this->resolveAllowlistedToolName($chunk['tool'] ?? null),
                            'tool_call_id' => is_string($chunk['id'] ?? null) ? $chunk['id'] : null,
                            'successful' => (bool) ($chunk['successful'] ?? false),
                        ]);

                        continue;
                    }
                    if ($type !== 'done') {
                        $this->fail($run, 'gateway_invalid_event', release: true);

                        return;
                    }
                    $usage = is_array($chunk['usage'] ?? null) ? $chunk['usage'] : [];
                    $tariff = is_array($chunk['tariff'] ?? null) ? $chunk['tariff'] : ($providerConfiguration['tariff'] ?? []);
                    $costMicro = $this->calculateCostMicro($usage, $tariff);
                    app(XiamRunExecutionBudget::class)->completeProviderStep(
                        $providerStep,
                        (int) ($usage['input_tokens'] ?? 0),
                        (int) ($usage['output_tokens'] ?? 0),
                        (microtime(true) - $providerStartedAt) * 1000,
                        ['usage' => $usage, 'driver' => $chunk['driver'] ?? $providerConfiguration['driver'], 'model' => $chunk['model'] ?? $providerConfiguration['model'], 'tariff' => $tariff],
                    );
                    $this->budgets->settleWithUsage($run->id, $usage, $costMicro);
                    if (($chunk['finish_reason'] ?? null) !== 'stop') {
                        $this->fail($run, 'incomplete_gateway_output', release: true);

                        return;
                    }
                    if ($assembled === '') {
                        $this->fail($run, 'empty_gateway_output', release: true);

                        return;
                    }
                    $assembledContent = $assembled;
                    $extractedMood = null;
                    if (preg_match('/<!--\s*xiam:mood\s*=\s*(["\'])([a-zA-Z0-9_-]+)\1\s*-->/i', $assembledContent, $matches)) {
                        $extractedMood = strtolower($matches[2]);
                        $assembledContent = preg_replace('/<!--\s*xiam:mood\s*=\s*(["\'])[a-zA-Z0-9_-]+\1\s*-->/i', '', $assembledContent) ?? $assembledContent;
                    }

                    $safe = $this->firewall->validateProtectedResponse(trim($assembledContent));
                    if ($safe !== null) {
                        $projectedResponse = ($this->navigation ?? app(XiamNavigationTargets::class))->projectResponse($safe, $actor, $actorType);
                        $safe = $projectedResponse['text'];
                    } else {
                        $projectedResponse = null;
                    }
                    if ($safe === null || $safe === '') {
                        $this->completeWithSafeRefusal($run, 'output_validation_rejected');

                        return;
                    }

                    $citationsById = [];
                    foreach (RunEvidenceCollection::forRun($run->id) as $citation) {
                        $citationId = (string) ($citation['citation_id'] ?? '');
                        if ($citationId !== '') {
                            $citationsById[$citationId] = $citation;
                        }
                    }
                    $citations = array_values($citationsById);
                    if ($citations !== []) {
                        $this->events->append($run, 'assistant.citations', ['citations' => $citations]);
                    }
                    $allProposalIds = XiamProposal::query()->where('run_id', $run->id)->orderBy('created_at')->pluck('id')->all();
                    $proposalId = $allProposalIds !== [] ? $allProposalIds[count($allProposalIds) - 1] : null;
                    $assistantPayload = [
                        'role' => 'assistant',
                        'content' => $safe,
                        'finish_reason' => 'stop',
                        'usage' => is_array($chunk['usage'] ?? null) ? $chunk['usage'] : null,
                        'citations' => $citations,
                        'navigation' => $projectedResponse['navigation'],
                        'proposal_id' => $proposalId,
                        'proposal_ids' => $allProposalIds,
                    ];
                    if ($extractedMood !== null) {
                        $assistantPayload['mood'] = $extractedMood;
                    }
                    $this->completeAssistantMessage($run, $assistantPayload, [
                        'status' => 'succeeded',
                        'mode' => $turn->mode,
                        'proposal_id' => $proposalId,
                        'proposal_ids' => $allProposalIds,
                    ]);

                    return;
                }
            } catch (XiamRunBudgetExceeded $e) {
                $this->completeWithSafeRefusal($run, $e->reason);

                return;
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $reason = str_starts_with($message, 'provider_timeout')
                    ? 'provider_timeout'
                    : (str_starts_with($message, 'provider_unavailable')
                        ? (str_contains($message, 'insufficient_credits') ? 'provider_insufficient_credits' : 'provider_unavailable')
                        : 'gateway_error');
                $this->fail($run, $reason, release: true);

                return;
            }
            $this->fail($run, 'incomplete_gateway_output', release: true);
        } finally {
            RunEvidenceCollection::clear($run->id);
            GenAiTelemetryContext::clear();
        }
    }

    private function calculateCostMicro(array $usage, array $tariff): int
    {
        $reasoningRate = (float) ($tariff['reasoning_micro_per_token'] ?? 0);
        if ($reasoningRate <= 0) {
            $reasoningRate = (float) ($tariff['completion_micro_per_token'] ?? 0);
        }

        return (int) round(
            (int) ($usage['uncached_input_tokens'] ?? 0) * (float) ($tariff['prompt_micro_per_token'] ?? 0)
            + (int) ($usage['cache_read_tokens'] ?? 0) * (float) ($tariff['cache_read_micro_per_token'] ?? 0)
            + (int) ($usage['cache_write_tokens'] ?? 0) * (float) ($tariff['cache_write_micro_per_token'] ?? 0)
            + (int) ($usage['non_reasoning_output_tokens'] ?? 0) * (float) ($tariff['completion_micro_per_token'] ?? 0)
            + (int) ($usage['reasoning_tokens'] ?? 0) * $reasoningRate,
        );
    }

    private function completeWithSafeRefusal(XiamRun $run, string $reason, ?string $decisionId = null): void
    {
        $this->events->append($run, 'assistant.refusal', [
            'reason' => $reason,
            'decision_id' => $decisionId,
            'mode' => 'safe_output_refusal',
        ]);
        $content = match ($reason) {
            'run_deadline_exhausted', 'provider_timeout' => 'The assistant operation took longer than the allocated time to complete. Please try again or use ordinary page controls.',
            default => 'I cannot safely provide a generated answer for that request yet. Use ordinary page controls or approved staff channels.',
        };
        $content = $this->firewall->assembleAssistantText($content) ?? $content;
        $this->completeAssistantMessage($run, [
            'role' => 'assistant',
            'content' => $content,
            'finish_reason' => 'stop',
            'citations' => [],
        ], [
            'status' => 'succeeded',
            'refusal' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     * @param  array<string, mixed>  $completionPayload
     */
    private function completeAssistantMessage(XiamRun $run, array $messagePayload, array $completionPayload): void
    {
        if (is_array($messagePayload['navigation'] ?? null) && $messagePayload['navigation'] !== []) {
            $this->events->append($run, 'assistant.navigation', [
                'navigation' => $messagePayload['navigation'],
            ]);
        }
        $this->events->append($run, 'assistant.message', $messagePayload);
        $this->events->append($run, 'run.completed', $completionPayload);
        DB::transaction(function () use ($run): void {
            $run->forceFill([
                'status' => XiamRun::STATUS_SUCCEEDED,
                'finished_at' => now(),
            ])->save();
            $this->budgets->settle($run->id);
        });
    }

    /**
     * @return array{0: User, 1: InvocationContext}|null
     */
    private function resolveTrustedRunActorAndContext(
        XiamRun $run,
        XiamTurn $turn,
        ?XiamConversation $conversation,
    ): ?array {
        if (
            $conversation === null
            || (string) $conversation->id !== (string) $run->conversation_id
            || (string) $turn->conversation_id !== (string) $run->conversation_id
            || $conversation->status !== 'open'
            || trim((string) $conversation->context_epoch) === ''
        ) {
            $this->fail($run, 'conversation_invalid', release: true);

            return null;
        }

        $actorId = trim((string) $conversation->actor_id);
        $actorType = trim((string) ($conversation->actor_type ?: 'staff'));
        $actor = $actorId !== '' ? User::query()->find($actorId) : null;
        if (! $actor instanceof User) {
            $this->fail($run, 'actor_missing', release: true);

            return null;
        }
        if ($actorType === 'staff') {
            $profile = $actor->staffProfile()->first();
            if ($profile === null || ! $profile->isActive() || ($conversation->institution_id !== null && (int) $profile->institution_id !== (int) $conversation->institution_id)) {
                $this->grants->revokeAllForActor('staff', $actorId, 'actor_revoked');
                $this->fail($run, 'actor_revoked', release: true);

                return null;
            }
        } elseif ($actorType === 'applicant') {
            $account = $actor->applicantAccount()->with('identity')->first();
            $accountInstitutionId = $account?->identity?->institution_id;
            if ($account === null || ($conversation->institution_id !== null && $accountInstitutionId !== null && (int) $accountInstitutionId !== (int) $conversation->institution_id)) {
                $this->grants->revokeAllForActor('applicant', $actorId, 'actor_revoked');
                $this->fail($run, 'actor_revoked', release: true);

                return null;
            }
        } else {
            $this->fail($run, 'actor_revoked', release: true);

            return null;
        }

        $pagePack = null;
        $registry = app(PageCapabilityPackRegistry::class);
        $pagePack = $turn->page_pack_key === null ? null : $registry->forKey($turn->page_pack_key);
        if ($pagePack?->surface->value !== $actorType) {
            $pagePack = null;
        }
        $grant = $this->grants->findActiveForActor(
            actorType: $actorType,
            actorId: $actorId,
            surfaceKey: (string) ($conversation->surface_key ?: $actorType),
            contextEpoch: (string) $conversation->context_epoch,
        );
        $context = new InvocationContext(
            actorId: (string) $actor->id,
            actorType: $actorType,
            institutionId: $conversation->institution_id === null ? null : (int) $conversation->institution_id,
            surfaceProfile: (string) ($conversation->surface_profile_id ?? $conversation->surface_key ?? $actorType),
            mode: (string) $turn->mode,
            contextEpoch: (string) $conversation->context_epoch,
            purpose: $turn->mode === 'assistant' ? 'assistant_turn' : 'policy_inquiry',
            correlationId: (string) $run->id,
            browserSessionId: $conversation->browser_session_id,
            routeName: $pagePack !== null && $pagePack->surface->value === $actorType ? $pagePack->key : null,
            grantId: $grant?->id,
            conversationId: (string) $conversation->id,
        );

        return [$actor, $context];
    }

    private function resolveActiveSkill(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = strtolower(trim($key));

        return match (true) {
            $key === 'applicant.choose_slot@1', $key === 'applicant-slot-booking' => 'applicant-slot-booking',
            $key === 'policy-inquiry' => 'policy-inquiry',
            str_contains($key, 'consultation') => 'consultation-scheduling',
            $key === 'schedule.exam_session@1',
            $key === 'exams.sessions.catalog@1',
            str_contains($key, 'exam'),
            $key === 'exam-scheduling' => 'exam-scheduling',
            default => null,
        };
    }

    private function resolveAllowlistedToolName(mixed $tool): ?string
    {
        if (! is_string($tool)) {
            return null;
        }
        $candidate = trim($tool);
        if ($candidate === '') {
            return null;
        }
        $catalog = $this->catalog ?? app(AssistantToolCatalog::class);
        foreach ($catalog->all() as $definition) {
            $id = $definition->id();
            $key = $definition->key;
            $bridgeName = str_replace(['.', '@', '-'], '_', $id);
            $bridgeKey = str_replace(['.', '@', '-'], '_', $key);
            if ($candidate === $id || $candidate === $bridgeName) {
                return $id;
            }
            if ($candidate === $key || $candidate === $bridgeKey) {
                return $key;
            }
        }

        return null;
    }

    private function cancel(XiamRun $run): void
    {
        if ($run->isTerminal()) {
            return;
        }
        $this->events->append($run, 'run.cancelled', []);
        $run->forceFill([
            'status' => XiamRun::STATUS_CANCELLED,
            'finished_at' => now(),
            'failure_reason' => 'cancelled',
        ])->save();
        $this->settleOrRelease($run->id);
    }

    private function fail(XiamRun $run, string $reason, bool $release): void
    {
        if ($run->isTerminal()) {
            return;
        }
        $this->events->append($run, 'run.failed', ['reason' => $reason]);
        $run->forceFill([
            'status' => XiamRun::STATUS_FAILED,
            'finished_at' => now(),
            'failure_reason' => $reason,
        ])->save();
        if ($release) {
            $this->settleOrRelease($run->id, $reason);
        } else {
            $this->budgets->settle($run->id);
        }
    }

    private function settleOrRelease(string $runId, ?string $reason = null): void
    {
        $steps = XiamRunStep::query()
            ->where('run_id', $runId)
            ->where('step_kind', XiamRunStep::KIND_PROVIDER)
            ->get(['status', 'output', 'step_cost_micro']);
        $paid = $steps->contains(static fn (XiamRunStep $step): bool => (int) $step->step_cost_micro > 0
            || (int) ($step->output['input_tokens'] ?? 0) > 0);
        if ($paid) {
            return;
        }

        $running = $steps->contains(static fn (XiamRunStep $step): bool => $step->status === XiamRunStep::STATUS_RUNNING);
        if ($running && in_array($reason, ['provider_timeout', 'provider_unavailable'], true)) {
            $this->budgets->markUnknown($runId);

            return;
        }

        $this->budgets->release($runId);
    }
}
