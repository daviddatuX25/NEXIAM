<?php

declare(strict_types=1);

namespace App\Ai\Adapters\LaravelAi;

use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Orchestration\XiamRunBudgetExceeded;
use App\Ai\Orchestration\XiamRunExecutionBudget;
use App\Ai\Policy\InputMinimizer;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Exceptions\StreamErrorException;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use RuntimeException;
use Throwable;

/**
 * laravel/ai adapter behind AiGateway (AD-13). SDK types stay in this namespace.
 */
final class LaravelAiGateway implements AiGateway
{
    public function generate(array $request): array
    {
        $context = $request['context'] ?? null;
        $resolved = $this->resolvedProvider($context);
        $this->assertReady($resolved['provider']);

        $actor = $request['actor'] ?? null;
        $agent = ($actor instanceof User && $context instanceof InvocationContext)
            ? XiamAgent::forRun($actor, $context)
            : XiamInquiryAgent::make();

        $historyMessages = $this->historyMessagesFrom($request);
        if ($historyMessages !== []) {
            $agent = $agent->withMessages($historyMessages);
        }

        try {
            $response = $agent->prompt(
                $this->promptFrom($request),
                provider: $resolved['provider'],
                model: $resolved['model'],
                timeout: min($resolved['timeout'], $this->timeout($request)),
            );
        } catch (ProviderConnectionException|ProviderOverloadedException|RateLimitedException|InsufficientCreditsException|StreamErrorException $exception) {
            throw $this->providerUnavailable($exception);
        }

        return [
            'text' => (string) $response->text,
            'finish_reason' => 'stop',
            'usage' => $this->normalizeUsage($response->usage ?? null, $resolved['driver']),
            'tool_requests' => [],
        ];
    }

    public function stream(array $request): iterable
    {
        $context = $request['context'] ?? null;
        $resolved = $this->resolvedProvider($context);
        $this->assertReady($resolved['provider']);

        $actor = $request['actor'] ?? null;
        $activeSkill = is_string($request['active_skill'] ?? null) ? $request['active_skill'] : null;
        $agent = ($actor instanceof User && $context instanceof InvocationContext)
            ? XiamAgent::forRun($actor, $context, activeSkill: $activeSkill)
            : XiamInquiryAgent::make();

        $historyMessages = $this->historyMessagesFrom($request);
        if ($historyMessages !== []) {
            $agent = $agent->withMessages($historyMessages);
        }

        try {
            $providers = [$resolved['provider'] => $resolved['model']];
            if ($resolved['fallback_provider'] !== null && $resolved['fallback_model'] !== null) {
                $providers[$resolved['fallback_provider']] = $resolved['fallback_model'];
            }
            $stream = $agent->stream(
                $this->promptFrom($request),
                provider: $providers,
                timeout: min($resolved['timeout'], $this->timeout($request)),
            );
            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    yield ['type' => 'delta', 'text' => $event->delta];

                    continue;
                }
                if ($event instanceof ToolCall) {
                    yield [
                        'type' => 'tool_call',
                        'id' => $event->toolCall->id,
                        'tool' => $event->toolCall->name,
                        'arguments' => $event->toolCall->arguments,
                    ];

                    continue;
                }

                if ($event instanceof ToolResult) {
                    $successful = $event->successful;
                    if ($successful && is_string($event->toolResult->result)) {
                        $decoded = json_decode($event->toolResult->result, true);
                        if (is_array($decoded) && isset($decoded['ok'])) {
                            $successful = (bool) $decoded['ok'];
                        }
                    }

                    yield [
                        'type' => 'tool_result',
                        'id' => $event->toolResult->id,
                        'tool' => $event->toolResult->name,
                        'result' => $event->toolResult->result,
                        'successful' => $successful,
                    ];

                    continue;
                }

                if ($event instanceof StreamEnd) {
                    yield [
                        'type' => 'done',
                        'finish_reason' => $this->normalizeFinishReason($event->reason ?? null),
                        'usage' => $this->normalizeUsage($event->usage ?? null, $resolved['driver']),
                        'provider' => $resolved['provider'],
                        'driver' => $resolved['driver'],
                        'model' => $resolved['model'],
                        'tariff' => $resolved['tariff'],
                    ];

                    return;
                }
            }
        } catch (ProviderConnectionException|ProviderOverloadedException|RateLimitedException|InsufficientCreditsException|StreamErrorException $exception) {
            throw $this->providerUnavailable($exception);
        }

        throw new RuntimeException('gateway_stream_incomplete');
    }

    public function health(): array
    {
        $provider = $this->provider();
        $ready = $this->apiKeyConfigured($provider);

        return [
            'ok' => $ready,
            'provider' => 'laravel:'.$provider,
            'degraded' => ! $ready,
            'reason' => $ready ? null : $this->readinessFailureReason($provider),
            'model' => $this->model(),
        ];
    }

    public function requestCancel(string $runId): bool
    {
        return false;
    }

    /**
     * @return array{input_tokens: int, output_tokens: int, uncached_input_tokens: int, cache_read_tokens: int, cache_write_tokens: int, non_reasoning_output_tokens: int, reasoning_tokens: int, total_tokens: int}
     */
    private function normalizeUsage(mixed $usage, string $driver = 'openai'): array
    {
        $promptTokens = is_object($usage) && isset($usage->promptTokens) ? max(0, (int) $usage->promptTokens) : 0;
        $completionTokens = is_object($usage) && isset($usage->completionTokens) ? max(0, (int) $usage->completionTokens) : 0;
        $cacheRead = is_object($usage) && isset($usage->cacheReadInputTokens) ? max(0, (int) $usage->cacheReadInputTokens) : 0;
        $cacheWrite = is_object($usage) && isset($usage->cacheWriteInputTokens) ? max(0, (int) $usage->cacheWriteInputTokens) : 0;
        $reasoning = is_object($usage) && isset($usage->reasoningTokens) ? max(0, (int) $usage->reasoningTokens) : 0;
        $nonReasoningOutput = $driver === 'gemini'
            ? $completionTokens
            : max(0, $completionTokens - $reasoning);
        $total = $promptTokens + $cacheRead + $cacheWrite + $nonReasoningOutput + $reasoning;

        return [
            'input_tokens' => $promptTokens,
            'output_tokens' => $completionTokens,
            'uncached_input_tokens' => $promptTokens,
            'cache_read_tokens' => $cacheRead,
            'cache_write_tokens' => $cacheWrite,
            'non_reasoning_output_tokens' => $nonReasoningOutput,
            'reasoning_tokens' => $reasoning,
            'total_tokens' => $total,
        ];
    }

    private function normalizeFinishReason(mixed $reason): string
    {
        return match ($reason) {
            'stop' => 'stop',
            'tool_calls' => 'tool_calls',
            'continue' => 'continue',
            'length' => 'length',
            'content_filter' => 'content_filter',
            'error' => 'error',
            'unknown' => 'unknown',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<Message>
     */
    private function historyMessagesFrom(array $request): array
    {
        $conversationId = $request['conversation_id'] ?? null;
        $currentTurnId = $request['turn_id'] ?? null;
        $runId = $request['run_id'] ?? null;

        if (! is_string($conversationId) || $conversationId === '') {
            return [];
        }

        $minimizer = app(InputMinimizer::class);
        $turns = XiamTurn::query()
            ->where('conversation_id', $conversationId)
            ->with('run.events')
            ->orderBy('seq')
            ->get()
            ->reject(fn (XiamTurn $turn): bool => (
                is_string($currentTurnId) && $turn->id === $currentTurnId
            ) || (
                is_string($runId) && $turn->run?->id === $runId
            ))
            ->filter(fn (XiamTurn $turn): bool => $turn->run?->status === 'succeeded'
                && $turn->run?->events?->contains('type', 'assistant.message'))
            ->take(-10);

        $messages = [];
        foreach ($turns as $turn) {
            $assistantEvent = $turn->run?->events?->firstWhere('type', 'assistant.message');
            $assistantContent = $assistantEvent?->payload['content'] ?? null;
            if (! is_string($assistantContent) || $assistantContent === '') {
                continue;
            }

            $messages[] = new Message('user', $minimizer->minimize((string) $turn->input_text));
            $messages[] = new Message('assistant', $assistantContent);
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function promptFrom(array $request): string
    {
        $prompt = trim((string) ($request['prompt'] ?? ''));
        $prompt = str_replace(['<', '>'], '', $prompt);
        $evidence = is_array($request['evidence'] ?? null) ? $request['evidence'] : [];
        $ownData = is_array($request['own_data'] ?? null) ? $request['own_data'] : null;
        $operationalEvidence = is_array($request['operational_evidence'] ?? null)
            ? $request['operational_evidence']
            : [];
        $minimizer = app(InputMinimizer::class);
        $sanitizeServerText = fn (string $text): string => str_replace(['<', '>'], ['&lt;', '&gt;'], $minimizer->minimize($text));

        $sections = ["<untrusted_user_question>\n{$prompt}\n</untrusted_user_question>"];

        if ($evidence !== []) {
            $blocks = [];
            foreach ($evidence as $i => $hit) {
                if (! is_array($hit)) {
                    continue;
                }
                $title = $sanitizeServerText((string) ($hit['source_title'] ?? 'policy'));
                $excerpt = $sanitizeServerText((string) ($hit['excerpt'] ?? ''));
                $blocks[] = ($i + 1).'. '.$title.': '.$excerpt;
            }
            $sections[] = "<approved_policy_evidence>\n".implode("\n", $blocks)."\n</approved_policy_evidence>";
        }
        if ($operationalEvidence !== []) {
            $blocks = [];
            foreach ($operationalEvidence as $tool => $data) {
                $encodedData = json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}';
                $blocks[] = $sanitizeServerText((string) $tool).': '.$sanitizeServerText($encodedData);
            }
            $sections[] = "<approved_operational_evidence>\n".implode("\n", $blocks)."\n</approved_operational_evidence>";
        }

        if ($ownData !== null && ($ownData['available'] ?? false)) {
            $attemptStatus = $sanitizeServerText((string) ($ownData['attempt']['status'] ?? 'unknown'));
            $ref = $sanitizeServerText((string) ($ownData['attempt']['reference_code'] ?? ''));
            $schedule = $sanitizeServerText((string) ($ownData['schedule']['summary'] ?? ''));
            $released = (bool) ($ownData['results']['released'] ?? false);
            $resultsSummary = $sanitizeServerText((string) ($ownData['results']['summary'] ?? ''));

            $ownSummary = "Status: {$attemptStatus}";
            if ($ref !== '') {
                $ownSummary .= ', Reference: '.$ref;
            }
            if ($schedule !== '') {
                $ownSummary .= ', Schedule: '.$schedule;
            }
            $ownSummary .= ', Results released: '.($released ? 'yes' : 'no');
            if ($resultsSummary !== '') {
                $ownSummary .= ' ('.$resultsSummary.')';
            }

            $sections[] = "<applicant_own_record>\n{$ownSummary}\n</applicant_own_record>";
        }

        return implode("\n\n", $sections);
    }

    private function assertReady(string $provider): void
    {
        if ($this->apiKeyConfigured($provider) || XiamInquiryAgent::isFaked() || XiamAgent::isFaked()) {
            return;
        }

        throw new RuntimeException('provider_unavailable:'.$this->readinessFailureReason($provider));
    }

    private function apiKeyConfigured(string $provider): bool
    {
        $key = config('ai.providers.'.$provider.'.key');
        if (is_string($key) && trim($key) !== '') {
            return true;
        }

        return (config('ai.providers.'.$provider.'.driver') ?? $provider) === 'ollama'
            && $this->localOllamaConfigured();
    }

    private function localOllamaConfigured(): bool
    {
        $provider = config('ai.providers.ollama');
        $url = is_array($provider) && is_string($provider['url'] ?? null)
            ? parse_url($provider['url'])
            : false;
        if (! is_array($provider) || ($provider['driver'] ?? null) !== 'ollama'
            || ! is_array($url) || ($url['scheme'] ?? null) !== 'http'
            || ! is_string($url['host'] ?? null) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment'])) {
            return false;
        }

        $host = strtolower(rtrim($url['host'], '.'));
        $networkHost = trim($host, '[]');
        if ($networkHost === 'localhost' || $this->isLoopbackAddress($networkHost)) {
            return true;
        }

        $deploymentHosts = config('xiam.connectors.allowlist.local-ollama.allowed_hosts', []);
        $evalHosts = config('xiam.eval.local_provider_hosts', []);

        return $this->hostIsExplicitlyAllowed($host, $deploymentHosts)
            || $this->hostIsExplicitlyAllowed($host, $evalHosts);
    }

    private function hostIsExplicitlyAllowed(string $host, mixed $allowedHosts): bool
    {
        if (! is_array($allowedHosts)) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (is_string($allowedHost)
                && strtolower(rtrim(trim($allowedHost), '.')) === $host) {
                return true;
            }
        }

        return false;
    }

    private function isLoopbackAddress(string $host): bool
    {
        $packedAddress = inet_pton($host);
        if ($packedAddress === false) {
            return false;
        }

        return strlen($packedAddress) === 4
            ? ord($packedAddress[0]) === 127
            : $packedAddress === str_repeat("\0", 15)."\1";
    }

    private function readinessFailureReason(string $provider): string
    {
        $key = config('ai.providers.'.$provider.'.key');

        return (config('ai.providers.'.$provider.'.driver') ?? $provider) === 'ollama' && (! is_string($key) || trim($key) === '')
            ? 'unsafe_local_endpoint'
            : 'missing_api_key';
    }

    private function providerUnavailable(Throwable $exception): RuntimeException
    {
        $cause = match (true) {
            $exception instanceof RateLimitedException => 'rate_limited',
            $exception instanceof InsufficientCreditsException => 'insufficient_credits',
            $exception instanceof ProviderOverloadedException => 'overloaded',
            $exception instanceof ProviderConnectionException => 'connection',
            default => 'stream_error',
        };

        return new RuntimeException('provider_unavailable:'.$cause);
    }

    /**
     * @return array{provider: string, driver: string, model: string, fallback_model: ?string, timeout: int, tariff: ?array, fallback_provider: ?string, fallback_tariff: ?array, curl_options: array, max_tokens: ?int}
     */
    private function resolvedProvider(mixed $context): array
    {
        if ($context instanceof InvocationContext) {
            return app(DynamicAiProviderResolver::class)->resolveForInstitution($context->institutionId ?? 1);
        }

        $provider = $this->provider();
        $driver = (string) config("ai.providers.{$provider}.driver", $provider);

        return [
            'provider' => $provider,
            'driver' => $driver,
            'model' => $this->model(),
            'fallback_model' => null,
            'timeout' => max(1, (int) config('xiam.ai.timeout', 30)),
            'tariff' => null,
            'fallback_provider' => null,
            'fallback_tariff' => null,
            'curl_options' => [],
            'max_tokens' => null,
        ];
    }

    private function provider(): string
    {
        return (string) config('xiam.ai.provider', config('ai.default', 'openai'));
    }

    private function model(): string
    {
        return (string) config('xiam.ai.model', 'gpt-4o-mini');
    }

    private function timeout(array $request = []): int
    {
        $context = $request['context'] ?? null;
        $runId = is_string($request['run_id'] ?? null) && $request['run_id'] !== ''
            ? $request['run_id']
            : ($context instanceof InvocationContext ? $context->correlationId : '');

        if ($runId !== '') {
            try {
                return app(XiamRunExecutionBudget::class)->providerTimeout($runId);
            } catch (XiamRunBudgetExceeded $exception) {
                if ($exception->getMessage() !== 'run_not_found') {
                    throw $exception;
                }
            } catch (Throwable) {
                // Fall back to the standard timeout when no run budget is available.
            }
        }

        return max(1, (int) config('xiam.ai.timeout', 30));
    }
}
