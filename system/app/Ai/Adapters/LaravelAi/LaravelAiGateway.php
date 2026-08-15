<?php

namespace App\Ai\Adapters\LaravelAi;

use App\Ai\Contracts\AiGateway;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use RuntimeException;
use Throwable;

/**
 * laravel/ai adapter behind AiGateway (AD-13). SDK types stay in this namespace.
 */
final class LaravelAiGateway implements AiGateway
{
    /** @var array<string, bool> */
    private array $cancelRequested = [];

    public function generate(array $request): array
    {
        $this->assertReady();

        if ($this->cancelRequested[$request['run_id'] ?? ''] ?? false) {
            return [
                'text' => '',
                'finish_reason' => 'cancelled',
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
                'tool_requests' => [],
            ];
        }

        try {
            $response = NexiInquiryAgent::make()->prompt(
                $this->promptFrom($request),
                provider: $this->provider(),
                model: $this->model(),
                timeout: $this->timeout(),
            );
        } catch (Throwable $e) {
            throw new RuntimeException('provider_unavailable:'.$e->getMessage(), 0, $e);
        }

        return [
            'text' => (string) $response->text,
            'finish_reason' => 'stop',
            'usage' => [
                'input_tokens' => (int) $response->usage->promptTokens,
                'output_tokens' => (int) $response->usage->completionTokens,
            ],
            'tool_requests' => [],
        ];
    }

    public function stream(array $request): iterable
    {
        $this->assertReady();

        $runId = (string) ($request['run_id'] ?? '');

        try {
            $stream = NexiInquiryAgent::make()->stream(
                $this->promptFrom($request),
                provider: $this->provider(),
                model: $this->model(),
                timeout: $this->timeout(),
            );

            foreach ($stream as $event) {
                if ($this->cancelRequested[$runId] ?? false) {
                    yield ['type' => 'cancelled', 'finish_reason' => 'cancelled'];

                    return;
                }

                if ($event instanceof TextDelta) {
                    yield ['type' => 'delta', 'text' => $event->delta];

                    continue;
                }

                if ($event instanceof StreamEnd) {
                    yield [
                        'type' => 'done',
                        'finish_reason' => (string) ($event->reason ?: 'stop'),
                        'usage' => [
                            'input_tokens' => (int) $event->usage->promptTokens,
                            'output_tokens' => (int) $event->usage->completionTokens,
                        ],
                    ];

                    return;
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException('provider_unavailable:'.$e->getMessage(), 0, $e);
        }
    }

    public function health(): array
    {
        $provider = $this->provider();
        $ready = $this->apiKeyConfigured();

        return [
            'ok' => $ready,
            'provider' => 'laravel:'.$provider,
            'degraded' => ! $ready,
            'reason' => $ready ? null : 'missing_api_key',
            'model' => $this->model(),
        ];
    }

    public function requestCancel(string $runId): bool
    {
        $this->cancelRequested[$runId] = true;

        return true;
    }


    /**
     * @param  array<string, mixed>  $request
     */
    private function promptFrom(array $request): string
    {
        $prompt = trim((string) ($request['prompt'] ?? ''));
        $evidence = is_array($request['evidence'] ?? null) ? $request['evidence'] : [];
        if ($evidence === []) {
            return $prompt;
        }

        $blocks = [];
        foreach ($evidence as $i => $hit) {
            if (! is_array($hit)) {
                continue;
            }
            $title = (string) ($hit['source_title'] ?? 'policy');
            $excerpt = (string) ($hit['excerpt'] ?? '');
            $blocks[] = ($i + 1).'. '.$title.': '.$excerpt;
        }

        return "User question:\n{$prompt}\n\nApproved policy evidence (cite only these):\n".implode("\n", $blocks);
    }

    private function assertReady(): void
    {
        if ($this->apiKeyConfigured() || NexiInquiryAgent::isFaked()) {
            return;
        }

        throw new RuntimeException('provider_unavailable:missing_api_key');
    }

    private function apiKeyConfigured(): bool
    {
        $provider = $this->provider();
        $key = config('ai.providers.'.$provider.'.key');

        return is_string($key) && trim($key) !== '';
    }

    private function provider(): string
    {
        return (string) config('nexi.ai.provider', config('ai.default', 'openai'));
    }

    private function model(): string
    {
        return (string) config('nexi.ai.model', 'gpt-4o-mini');
    }

    private function timeout(): int
    {
        return max(1, (int) config('nexi.ai.timeout', 30));
    }
}
