<?php

use App\Ai\Adapters\Fake\FakeAiGateway;
use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\NexiInquiryAgent;
use App\Ai\Contracts\AiGateway;
use Illuminate\Support\Str;

test('default AiGateway binding remains FakeAiGateway', function (): void {
    config(['nexi.ai.gateway' => 'fake']);
    app()->forgetInstance(AiGateway::class);

    expect(app(AiGateway::class))->toBeInstanceOf(FakeAiGateway::class);
});

test('laravel gateway health degrades without API key', function (): void {
    config([
        'nexi.ai.gateway' => 'laravel',
        'nexi.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    $gateway = new LaravelAiGateway;
    $health = $gateway->health();

    expect($health['ok'])->toBeFalse()
        ->and($health['degraded'])->toBeTrue()
        ->and($health['reason'])->toBe('missing_api_key');

    expect(fn () => $gateway->generate([
        'prompt' => 'hello',
        'conversation_id' => (string) Str::uuid(),
        'run_id' => (string) Str::uuid(),
        'mode' => 'inquiry',
    ]))->toThrow(RuntimeException::class, 'provider_unavailable');
});

test('laravel adapter streams NEXIAM chunk DTOs under Agent fake', function (): void {
    config([
        'nexi.ai.gateway' => 'laravel',
        'nexi.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    NexiInquiryAgent::fake([
        'Synthetic spike reply about setup readiness.',
    ]);

    $gateway = new LaravelAiGateway;
    $chunks = iterator_to_array($gateway->stream([
        'prompt' => 'What is setup readiness?',
        'conversation_id' => (string) Str::uuid(),
        'run_id' => (string) Str::uuid(),
        'mode' => 'inquiry',
    ]));

    expect($chunks)->not->toBeEmpty();
    $types = collect($chunks)->pluck('type')->all();
    expect($types)->toContain('delta')
        ->and($types)->toContain('done');

    foreach ($chunks as $chunk) {
        expect($chunk)->toHaveKey('type');
        if (($chunk['type'] ?? '') === 'delta') {
            expect($chunk)->toHaveKey('text')
                ->and($chunk['text'])->toBeString();
        }
        if (($chunk['type'] ?? '') === 'done') {
            expect($chunk)->toHaveKey('finish_reason');
        }
    }

    $assembled = collect($chunks)->where('type', 'delta')->pluck('text')->implode('');
    expect($assembled)->toContain('Synthetic spike reply');
});

test('nexi inquiry agent declares no mutation tools in this cut', function (): void {
    $agent = new NexiInquiryAgent;
    expect(method_exists($agent, 'tools'))->toBeFalse()
        ->and($agent->instructions())->toBeString()
        ->and($agent->instructions())->toContain('Inquiry');
});
