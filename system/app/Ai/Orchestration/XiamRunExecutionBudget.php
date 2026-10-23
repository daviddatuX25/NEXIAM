<?php

declare(strict_types=1);

namespace App\Ai\Orchestration;

use App\Ai\Policy\EmergencyStop;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class XiamRunExecutionBudget
{
    public function __construct(private readonly EmergencyStop $emergencyStop) {}

    public function beginProviderStep(
        string $runId,
        string $provider,
        string $model,
        string $invocationId,
        int $stepNumber,
        bool $isFinalStep,
        array $metadata = [],
    ): XiamRunStep {
        return DB::transaction(function () use ($runId, $provider, $model, $invocationId, $stepNumber, $isFinalStep, $metadata): XiamRunStep {
            $run = $this->lockRun($runId);
            $this->assertCanContinue($run);

            $limit = max(0, (int) config('xiam.execution_budget.provider_generations', 4));
            $count = XiamRunStep::query()
                ->where('run_id', $run->id)
                ->where('step_kind', XiamRunStep::KIND_PROVIDER)
                ->count();

            if ($count >= $limit) {
                throw new XiamRunBudgetExceeded('provider_generation_budget_exhausted');
            }

            return $this->appendStep($run, [
                'step_kind' => XiamRunStep::KIND_PROVIDER,
                'status' => XiamRunStep::STATUS_RUNNING,
                'provider' => $this->metadataIdentifier($provider),
                'provider_request_id' => $this->metadataIdentifier($invocationId),
                'provider_driver' => $metadata['driver'] ?? null,
                'model_id' => $metadata['model'] ?? $model,
                'tariff_id' => $metadata['tariff']['id'] ?? null,
                'rate_snapshot_json' => $metadata['tariff'] ?? null,
                'input' => [
                    'model' => $this->metadataIdentifier($model),
                    'invocation_id' => $this->metadataIdentifier($invocationId),
                    'step_number' => $stepNumber,
                    'is_final_step' => $isFinalStep,
                ],
                'started_at' => now(),
            ]);
        });
    }

    public function completeProviderStep(XiamRunStep $step, int $inputTokens, int $outputTokens, float $elapsedMs, array $metadata = []): void
    {
        DB::transaction(function () use ($step, $inputTokens, $outputTokens, $elapsedMs, $metadata): void {
            $this->lockRun($step->run_id);
            $lockedStep = $this->lockStep($step, XiamRunStep::KIND_PROVIDER);
            if ($lockedStep->status === XiamRunStep::STATUS_SUCCEEDED) {
                return;
            }
            $usage = $metadata['usage'] ?? [];
            $rates = $metadata['tariff'] ?? $lockedStep->rate_snapshot_json ?? [];
            $tokens = [
                'uncached_input_tokens' => max(0, (int) ($usage['uncached_input_tokens'] ?? $inputTokens)),
                'cache_read_tokens' => max(0, (int) ($usage['cache_read_tokens'] ?? 0)),
                'cache_write_tokens' => max(0, (int) ($usage['cache_write_tokens'] ?? 0)),
                'non_reasoning_output_tokens' => max(0, (int) ($usage['non_reasoning_output_tokens'] ?? $outputTokens)),
                'reasoning_tokens' => max(0, (int) ($usage['reasoning_tokens'] ?? 0)),
            ];
            $reasoningRate = (float) ($rates['reasoning_micro_per_token'] ?? 0);
            if ($reasoningRate <= 0) {
                $reasoningRate = (float) ($rates['completion_micro_per_token'] ?? 0);
            }
            $costMicro = (int) round(
                $tokens['uncached_input_tokens'] * (float) ($rates['prompt_micro_per_token'] ?? 0)
                + $tokens['cache_read_tokens'] * (float) ($rates['cache_read_micro_per_token'] ?? 0)
                + $tokens['cache_write_tokens'] * (float) ($rates['cache_write_micro_per_token'] ?? 0)
                + $tokens['non_reasoning_output_tokens'] * (float) ($rates['completion_micro_per_token'] ?? 0)
                + $tokens['reasoning_tokens'] * $reasoningRate,
            );
            $lockedStep->forceFill([
                'status' => XiamRunStep::STATUS_SUCCEEDED,
                'provider_driver' => $metadata['driver'] ?? $lockedStep->provider_driver,
                'model_id' => $metadata['model'] ?? $lockedStep->model_id,
                'tariff_id' => $rates['id'] ?? $lockedStep->tariff_id,
                'rate_snapshot_json' => $rates !== [] ? $rates : $lockedStep->rate_snapshot_json,
                'cache_read_tokens' => $tokens['cache_read_tokens'],
                'cache_write_tokens' => $tokens['cache_write_tokens'],
                'reasoning_tokens' => $tokens['reasoning_tokens'],
                'step_cost_micro' => max(0, $costMicro),
                'output' => [
                    'input_tokens' => max(0, $inputTokens),
                    'output_tokens' => max(0, $outputTokens),
                    ...$tokens,
                    'total_tokens' => (int) ($usage['total_tokens'] ?? (array_sum($tokens))),
                    'elapsed_ms' => max(0, $elapsedMs),
                ],
                'failure_reason' => null,
                'finished_at' => now(),
            ])->save();
        });
    }

    public function failProviderStep(XiamRunStep $step, string $reason, float $elapsedMs): void
    {
        DB::transaction(function () use ($step, $reason, $elapsedMs): void {
            $this->lockRun($step->run_id);
            $lockedStep = $this->lockStep($step, XiamRunStep::KIND_PROVIDER);
            $lockedStep->forceFill([
                'status' => XiamRunStep::STATUS_FAILED,
                'output' => ['elapsed_ms' => max(0, $elapsedMs)],
                'failure_reason' => $this->safeFailureCode($reason, 'provider_failed'),
                'finished_at' => now(),
            ])->save();
        });
    }

    public function beginToolCall(string $runId, string $toolId, ?string $toolCallId): XiamRunStep
    {
        return DB::transaction(function () use ($runId, $toolId, $toolCallId): XiamRunStep {
            $run = $this->lockRun($runId);
            $this->assertCanContinue($run);

            $limit = max(0, (int) config('xiam.execution_budget.tool_calls', 4));
            $count = XiamRunStep::query()
                ->where('run_id', $run->id)
                ->where('step_kind', XiamRunStep::KIND_TOOL_CALL)
                ->count();

            if ($count >= $limit) {
                throw new XiamRunBudgetExceeded('tool_call_budget_exhausted');
            }

            return $this->appendStep($run, [
                'step_kind' => XiamRunStep::KIND_TOOL_CALL,
                'status' => XiamRunStep::STATUS_RUNNING,
                'tool_id' => $this->metadataIdentifier($toolId),
                'provider_request_id' => $toolCallId === null ? null : $this->metadataIdentifier($toolCallId),
                'input' => null,
                'output' => null,
                'started_at' => now(),
            ]);
        });
    }

    public function completeToolCall(XiamRunStep $step, int $serializedBytes, bool $successful): bool
    {
        return DB::transaction(function () use ($step, $serializedBytes, $successful): bool {
            $run = $this->lockRun($step->run_id);
            $lockedStep = $this->lockStep($step, XiamRunStep::KIND_TOOL_CALL);
            $bytes = max(0, $serializedBytes);
            $existingBytes = 0;

            foreach (XiamRunStep::query()
                ->where('run_id', $run->id)
                ->where('id', '!=', $lockedStep->getKey())
                ->get(['output']) as $runStep) {
                $existingBytes += max(0, (int) ($runStep->output['serialized_bytes'] ?? 0));
            }

            $aggregateLimit = max(0, (int) config('xiam.execution_budget.tool_results_bytes', 32768));
            $withinBudget = $existingBytes + $bytes <= $aggregateLimit;
            $lockedStep->forceFill([
                'status' => $withinBudget && $successful ? XiamRunStep::STATUS_SUCCEEDED : XiamRunStep::STATUS_FAILED,
                'output' => [
                    'serialized_bytes' => $bytes,
                    'successful' => $withinBudget && $successful,
                ],
                'failure_reason' => $withinBudget ? ($successful ? null : 'tool_failed') : 'tool_result_budget_exhausted',
                'finished_at' => now(),
            ])->save();

            return $withinBudget;
        });
    }

    public function assertRunCanContinue(string $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $this->assertCanContinue($this->lockRun($runId));
        });
    }

    public function providerTimeout(string $runId): int
    {
        return DB::transaction(function () use ($runId): int {
            $run = $this->lockRun($runId);
            $this->assertCanContinue($run);
            $remainingSeconds = $this->remainingSeconds($run);
            if ($remainingSeconds <= 0) {
                throw new XiamRunBudgetExceeded('run_deadline_exhausted');
            }

            $limits = [
                max(1, (int) config('xiam.ai.timeout', 30)),
                max(1, (int) config('xiam.execution_budget.provider_step_timeout_seconds', 30)),
                max(1, $remainingSeconds),
            ];

            return min($limits);
        });
    }

    private function appendStep(XiamRun $run, array $attributes): XiamRunStep
    {
        $lastSeq = (int) XiamRunStep::query()
            ->where('run_id', $run->id)
            ->max('seq');

        return XiamRunStep::query()->create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
            'conversation_id' => $run->conversation_id,
            'seq' => $lastSeq + 1,
            ...$attributes,
        ]);
    }

    private function lockRun(string $runId): XiamRun
    {
        $run = XiamRun::query()->whereKey($runId)->lockForUpdate()->first();

        if ($run === null) {
            throw new XiamRunBudgetExceeded('run_not_found');
        }

        return $run;
    }

    private function lockStep(XiamRunStep $step, string $kind): XiamRunStep
    {
        $lockedStep = XiamRunStep::query()
            ->whereKey($step->getKey())
            ->where('run_id', $step->run_id)
            ->where('step_kind', $kind)
            ->first();

        if ($lockedStep === null) {
            throw new XiamRunBudgetExceeded('run_step_not_found');
        }

        return $lockedStep;
    }

    private function assertCanContinue(XiamRun $run): void
    {
        if ($run->status !== XiamRun::STATUS_RUNNING) {
            throw new XiamRunBudgetExceeded('run_not_running');
        }

        if ($run->cancel_requested_at !== null) {
            throw new XiamRunBudgetExceeded('run_cancelled');
        }

        $conversation = XiamConversation::query()->find($run->conversation_id);
        if ($this->emergencyStop->active($conversation?->institution_id)) {
            throw new XiamRunBudgetExceeded('emergency_stop');
        }

        if ($this->remainingSeconds($run) <= 0) {
            throw new XiamRunBudgetExceeded('run_deadline_exhausted');
        }
    }

    private function remainingSeconds(XiamRun $run): int
    {
        if ($run->started_at === null) {
            return 0;
        }

        $deadline = Carbon::parse($run->started_at)->addSeconds(
            max(0, (int) config('xiam.execution_budget.run_deadline_seconds', 30))
        );

        return (int) ceil(max(0, now()->diffInMilliseconds($deadline, false)) / 1000);
    }

    private function metadataIdentifier(string $value): string
    {
        return preg_match('/^[A-Za-z0-9_.:@-]{1,128}$/D', $value) === 1 ? $value : 'unknown';
    }

    private function safeFailureCode(string $reason, string $fallback): string
    {
        return preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $reason) === 1 ? $reason : $fallback;
    }
}
