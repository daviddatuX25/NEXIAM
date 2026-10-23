<?php

declare(strict_types=1);

namespace App\Ai\Adapters\Fake;

use App\Ai\Contracts\AiGateway;
use RuntimeException;

/**
 * Safe deterministic fake gateway. It never fabricates model output.
 */
final class FakeAiGateway implements AiGateway
{
    /** @var array<string, bool> */
    private array $cancelRequested = [];

    public function generate(array $request): array
    {
        if ($this->cancelRequested[$request['run_id'] ?? ''] ?? false) {
            return [
                'text' => '',
                'finish_reason' => 'cancelled',
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
                'tool_requests' => [],
            ];
        }

        throw new RuntimeException('provider_unavailable');
    }

    public function stream(array $request): iterable
    {
        if ($this->cancelRequested[$request['run_id'] ?? ''] ?? false) {
            yield ['type' => 'cancelled', 'finish_reason' => 'cancelled'];

            return;
        }

        throw new RuntimeException('provider_unavailable');
    }

    public function health(): array
    {
        return [
            'ok' => false,
            'provider' => 'fake',
            'degraded' => true,
            'reason' => 'provider_unavailable',
        ];
    }

    public function requestCancel(string $runId): bool
    {
        $this->cancelRequested[$runId] = true;

        return true;
    }
}
