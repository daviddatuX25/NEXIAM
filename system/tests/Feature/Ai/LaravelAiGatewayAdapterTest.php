<?php

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Adapters\LaravelAi\XiamInquiryAgent;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\StreamErrorException;
use Laravel\Ai\Streaming\Events\Error as ProviderError;
use RuntimeException;
use Throwable;

/**
 * @return array{prompt: string, conversation_id: string, run_id: string, mode: string}
 */
function laravelAiGatewayAdapterRequest(string $runId): array
{
    return [
        'prompt' => 'What does the setup policy say?',
        'conversation_id' => (string) Str::uuid(),
        'run_id' => $runId,
        'mode' => 'inquiry',
    ];
}

test('laravel adapter omits raw provider exception details in generated and streamed errors', function (): void {
    config([
        'xiam.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    $providerMessage = 'provider response leaked a bearer-secret';
    XiamInquiryAgent::fake(static function (...$arguments) use ($providerMessage): never {
        throw new StreamErrorException(new ProviderError(
            id: 'provider-event-id',
            type: 'error',
            message: $providerMessage,
            recoverable: false,
            timestamp: time(),
        ));
    });

    $gateway = new LaravelAiGateway;

    $generateException = null;
    try {
        $gateway->generate(laravelAiGatewayAdapterRequest((string) Str::uuid()));
    } catch (Throwable $exception) {
        $generateException = $exception;
    }

    $streamException = null;
    try {
        iterator_to_array($gateway->stream(laravelAiGatewayAdapterRequest((string) Str::uuid())));
    } catch (Throwable $exception) {
        $streamException = $exception;
    }

    foreach ([$generateException, $streamException] as $exception) {
        expect($exception)->toBeInstanceOf(RuntimeException::class)
            ->and($exception->getMessage())->toBe('provider_unavailable:stream_error')
            ->and($exception->getMessage())->not->toContain($providerMessage)
            ->and($exception->getPrevious())->toBeNull();
    }
});

test('laravel adapter preserves application errors instead of classifying them as provider failures', function (): void {
    config([
        'xiam.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    XiamInquiryAgent::fake(static function (...$arguments): never {
        throw new RuntimeException('room_not_suitable_for_exam');
    });

    $exception = null;
    try {
        (new LaravelAiGateway)->generate(laravelAiGatewayAdapterRequest((string) Str::uuid()));
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('room_not_suitable_for_exam')
        ->and($exception->getMessage())->not->toStartWith('provider_unavailable')
        ->and($exception->getPrevious())->toBeNull();
});

test('laravel adapter reports provider abort unsupported and does not suppress streaming', function (): void {
    config([
        'xiam.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    $reply = 'The provider stream continues after an unsupported cancellation request.';
    XiamInquiryAgent::fake([$reply]);

    $gateway = new LaravelAiGateway;
    $runId = (string) Str::uuid();

    expect($gateway->requestCancel($runId))->toBeFalse();

    $chunks = iterator_to_array($gateway->stream(laravelAiGatewayAdapterRequest($runId)));
    $types = array_column($chunks, 'type');
    $assembled = collect($chunks)->where('type', 'delta')->pluck('text')->implode('');

    expect($types)->toContain('delta')
        ->and($types)->toContain('done')
        ->and($types)->not->toContain('cancelled')
        ->and($assembled)->toBe($reply);
});

test('laravel adapter normalizes SDK responses without provider usage to zero token counts', function (): void {
    config([
        'xiam.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    XiamAgent::fake(static fn (...$arguments): string => 'A response without provider usage.');
    XiamInquiryAgent::fake(static fn (...$arguments): string => 'A response without provider usage.');

    $gateway = new LaravelAiGateway;
    $generated = $gateway->generate(laravelAiGatewayAdapterRequest((string) Str::uuid()));
    $chunks = iterator_to_array($gateway->stream(laravelAiGatewayAdapterRequest((string) Str::uuid())));
    $done = collect($chunks)->firstWhere('type', 'done');

    $zeroUsage = [
        'input_tokens' => 0,
        'output_tokens' => 0,
        'uncached_input_tokens' => 0,
        'cache_read_tokens' => 0,
        'cache_write_tokens' => 0,
        'non_reasoning_output_tokens' => 0,
        'reasoning_tokens' => 0,
        'total_tokens' => 0,
    ];

    expect($generated['usage'])->toBe($zeroUsage)
        ->and($done['usage'])->toBe($zeroUsage);
});

test('keyless local Ollama readiness accepts loopback endpoints', function (): void {
    config([
        'xiam.ai.provider' => 'ollama',
        'xiam.eval.local_provider_hosts' => [],
        'xiam.connectors.allowlist.local-ollama.allowed_hosts' => [],
        'ai.providers.ollama' => [
            'driver' => 'ollama',
            'key' => '',
            'url' => 'http://127.0.0.1:11434',
        ],
    ]);

    expect((new LaravelAiGateway)->health()['ok'])->toBeTrue();
});

test('keyless Ollama readiness accepts only an explicitly listed eval host', function (): void {
    config([
        'xiam.ai.provider' => 'ollama',
        'xiam.eval.local_provider_hosts' => ['HOST.DOCKER.INTERNAL'],
        'xiam.connectors.allowlist.local-ollama.allowed_hosts' => [],
        'ai.providers.ollama' => [
            'driver' => 'ollama',
            'key' => '',
            'url' => 'http://host.docker.internal:11434',
        ],
    ]);

    expect((new LaravelAiGateway)->health()['ok'])->toBeTrue();

    config(['ai.providers.ollama.url' => 'http://host.docker.internal.evil:11434']);

    expect((new LaravelAiGateway)->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);

    config([
        'xiam.eval.local_provider_hosts' => [],
        'ai.providers.ollama.url' => 'http://host.docker.internal:11434',
    ]);

    $gateway = new LaravelAiGateway;
    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);

    config(['ai.providers.ollama.url' => 'http://ollama.remote.example:11434']);

    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);
});

test('keyless Ollama readiness refuses credentials and query parameters', function (): void {
    config([
        'xiam.ai.provider' => 'ollama',
        'xiam.eval.local_provider_hosts' => ['host.docker.internal'],
        'xiam.connectors.allowlist.local-ollama.allowed_hosts' => [],
        'ai.providers.ollama' => [
            'driver' => 'ollama',
            'key' => '',
            'url' => 'http://user:password@host.docker.internal:11434',
        ],
    ]);

    $gateway = new LaravelAiGateway;
    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);

    config(['ai.providers.ollama.url' => 'http://host.docker.internal:11434?token=secret']);

    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);

    config(['ai.providers.ollama.url' => 'https://host.docker.internal:11434']);

    expect($gateway->health())
        ->toMatchArray(['ok' => false, 'degraded' => true, 'reason' => 'unsafe_local_endpoint']);
});
