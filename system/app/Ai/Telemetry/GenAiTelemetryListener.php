<?php

namespace App\Ai\Telemetry;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;

/**
 * Zero-body GenAI telemetry listener on laravel/ai events (D-XIAM-03).
 * Records run IDs, correlation IDs, model, token usage, latency, and status
 * while strictly omitting prompt/response bodies, tool arguments, and results.
 */
final class GenAiTelemetryListener
{
    /**
     * In-memory record for testing and diagnostics.
     *
     * @var list<array<string, mixed>>
     */
    private static array $recorded = [];

    /**
     * @return list<array<string, mixed>>
     */
    public static function recorded(): array
    {
        return self::$recorded;
    }

    public static function clear(): void
    {
        self::$recorded = [];
    }

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $payload = [
            'event' => 'genai.prompting',
            'run_id' => GenAiTelemetryContext::runId(),
            'correlation_id' => GenAiTelemetryContext::correlationId(),
            'invocation_id' => $event->invocationId,
            'model' => $event->prompt->model,
            'agent' => $event->prompt->agent::class,
            'provider' => (string) config('xiam.ai.provider', 'fake'),
            'timestamp' => now()->toIso8601String(),
        ];

        self::$recorded[] = $payload;
        Log::info('genai.prompting', $payload);
    }

    public function handleAgentStreamed(AgentStreamed $event): void
    {
        $payload = [
            'event' => 'genai.streamed',
            'run_id' => GenAiTelemetryContext::runId(),
            'correlation_id' => GenAiTelemetryContext::correlationId(),
            'invocation_id' => $event->invocationId,
            'model' => $event->prompt->model,
            'agent' => $event->prompt->agent::class,
            'usage' => $event->response->usage?->toArray() ?? [],
            'finish_reason' => $event->response->meta?->finishReason ?? 'stop',
            'timestamp' => now()->toIso8601String(),
        ];

        self::$recorded[] = $payload;
        Log::info('genai.streamed', $payload);
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        if ($event instanceof AgentStreamed) {
            return;
        }

        $payload = [
            'event' => 'genai.prompted',
            'run_id' => GenAiTelemetryContext::runId(),
            'correlation_id' => GenAiTelemetryContext::correlationId(),
            'invocation_id' => $event->invocationId,
            'model' => $event->prompt->model,
            'agent' => $event->prompt->agent::class,
            'usage' => $event->response->usage?->toArray() ?? [],
            'finish_reason' => $event->response->meta?->finishReason ?? 'stop',
            'timestamp' => now()->toIso8601String(),
        ];

        self::$recorded[] = $payload;
        Log::info('genai.prompted', $payload);
    }

    public function handleInvokingTool(InvokingTool $event): void
    {
        $toolName = method_exists($event->tool, 'name')
            ? $event->tool->name()
            : ($event->tool->name ?? $event->tool::class);

        $payload = [
            'event' => 'genai.tool_invoking',
            'run_id' => GenAiTelemetryContext::runId(),
            'correlation_id' => GenAiTelemetryContext::correlationId(),
            'invocation_id' => $event->invocationId,
            'tool_invocation_id' => $event->toolInvocationId,
            'tool_name' => $toolName,
            'agent' => $event->agent::class,
            'timestamp' => now()->toIso8601String(),
        ];

        self::$recorded[] = $payload;
        Log::info('genai.tool_invoking', $payload);
    }

    public function handleToolInvoked(ToolInvoked $event): void
    {
        $toolName = method_exists($event->tool, 'name')
            ? $event->tool->name()
            : ($event->tool->name ?? $event->tool::class);

        $payload = [
            'event' => 'genai.tool_invoked',
            'run_id' => GenAiTelemetryContext::runId(),
            'correlation_id' => GenAiTelemetryContext::correlationId(),
            'invocation_id' => $event->invocationId,
            'tool_invocation_id' => $event->toolInvocationId,
            'tool_name' => $toolName,
            'agent' => $event->agent::class,
            'latency_ms' => $event->time,
            'status' => 'completed',
            'timestamp' => now()->toIso8601String(),
        ];

        self::$recorded[] = $payload;
        Log::info('genai.tool_invoked', $payload);
    }

    public function handleAgentFailed(AgentFailed $event): void
    {
        $payload = [
            'event' => 'genai.agent_failed',
            'run_id' => GenAiTelemetryContext::runId(),
            'correlation_id' => GenAiTelemetryContext::correlationId(),
            'invocation_id' => $event->invocationId,
            'model' => $event->prompt->model,
            'agent' => $event->prompt->agent::class,
            'error_class' => $event->exception::class,
            'status' => 'failed',
            'timestamp' => now()->toIso8601String(),
        ];

        self::$recorded[] = $payload;
        Log::error('genai.agent_failed', $payload);
    }
}
