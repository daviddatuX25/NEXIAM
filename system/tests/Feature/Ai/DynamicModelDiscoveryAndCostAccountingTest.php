<?php

use App\Ai\Policy\UsageBudgetService;
use App\Ai\Support\EndpointSecurityGuard;
use App\Models\Ai\XiamModelTariff;
use App\Models\Ai\XiamProviderConfiguration;
use App\Models\Ai\XiamUsageReservation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\AiManager;

test('endpoint security rejects unsafe schemes and private or special-use addresses', function (): void {
    $guard = app(EndpointSecurityGuard::class);

    expect(fn () => $guard->assertAndResolveSafeEndpoint('http://api.example.test/v1/models', 'openai'))
        ->toThrow(InvalidArgumentException::class);

    foreach ([
        'http://169.254.169.254/latest/meta-data',
        'https://10.0.0.1/v1/models',
        'https://192.168.1.1/v1/models',
        'https://172.16.0.1/v1/models',
        'https://100.64.0.1/v1/models',
        'https://[fc00::1]/v1/models',
        'https://[fe80::1]/v1/models',
        'https://[::1]/v1/models',
        'https://[::ffff:10.0.0.1]/v1/models',
    ] as $url) {
        expect(fn () => $guard->assertAndResolveSafeEndpoint($url, 'openai'))->toThrow(InvalidArgumentException::class);
    }
});

test('endpoint security normalizes API roots once pins public IPs and caps response bytes', function (): void {
    $guard = app(EndpointSecurityGuard::class);

    expect($guard->normalizeVersionedBaseUrl('deepseek', 'https://api.deepseek.com/v1'))
        ->toBe('https://api.deepseek.com/v1')
        ->and($guard->normalizeVersionedBaseUrl('gemini', 'https://generativelanguage.googleapis.com/v1beta'))
        ->toBe('https://generativelanguage.googleapis.com/v1beta');

    expect(defined('CURLOPT_RESOLVE'))->toBeTrue();
    $resolved = $guard->assertAndResolveSafeEndpoint(
        'https://api.example.test/v1/models',
        'openai',
        static fn (string $host): array => ['104.18.0.1'],
    );
    expect($resolved['host'])->toBe('api.example.test')
        ->and($resolved['pinned_ip'])->toBe('104.18.0.1');

    $guard->enforceResponseSizeCeiling(str_repeat('x', EndpointSecurityGuard::MAX_DISCOVERY_RESPONSE_BYTES));
    expect(fn () => $guard->enforceResponseSizeCeiling(str_repeat('x', EndpointSecurityGuard::MAX_DISCOVERY_RESPONSE_BYTES + 1)))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $guard->enforceResponseSizeCeiling('short', EndpointSecurityGuard::MAX_DISCOVERY_RESPONSE_BYTES + 1))
        ->toThrow(InvalidArgumentException::class);
});

test('model discovery imports OpenRouter tariffs and returns DeepSeek models through secured fake HTTP', function (): void {
    seedE2Foundation();
    Http::preventStrayRequests();
    Http::fake([
        'https://104.18.0.1/api/v1/models' => Http::response(['data' => [[
            'id' => 'openai/gpt-4o-mini',
            'name' => 'GPT-4o mini',
            'context_length' => 128000,
            'supported_parameters' => ['tools'],
            'pricing' => [
                'prompt' => '0.00000015',
                'completion' => '0.00000060',
                'input_cache_read' => '0.00000005',
                'input_cache_write' => '0.00000010',
                'internal_reasoning' => '0.00000070',
            ],
        ]]], 200),
        'https://104.18.0.1/v1/models' => Http::response(['data' => [
            ['id' => 'deepseek-chat', 'name' => 'DeepSeek Chat'],
            ['id' => 'deepseek-reasoner', 'name' => 'DeepSeek Reasoner'],
        ]], 200),
    ]);
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $this->actingAs($admin);

    $openRouter = $this->postJson(route('setup.ai-center.models.discover'), [
        'driver' => 'openrouter',
        'base_url' => 'https://104.18.0.1/api/v1',
        'key_mode' => 'custom',
        'custom_api_key' => 'discovery-secret',
    ])->assertOk()->json('models');

    expect($openRouter)->toHaveCount(1)
        ->and($openRouter[0]['id'])->toBe('openai/gpt-4o-mini')
        ->and($openRouter[0]['has_tariff'])->toBeTrue();
    $tariff = XiamModelTariff::query()->where('model_id', 'openai/gpt-4o-mini')->where('provider_driver', 'openrouter')->firstOrFail();
    expect($tariff->pricing_source)->toBe('openrouter_api')
        ->and($tariff->prompt_micro_per_token)->toBe(0.15)
        ->and($tariff->completion_micro_per_token)->toBe(0.6);

    $deepSeek = $this->postJson(route('setup.ai-center.models.discover'), [
        'driver' => 'deepseek',
        'base_url' => 'https://104.18.0.1/v1',
        'key_mode' => 'custom',
        'custom_api_key' => 'deepseek-secret',
    ])->assertOk()->json('models');
    expect(collect($deepSeek)->pluck('id')->all())->toBe(['deepseek-chat', 'deepseek-reasoner']);
});

test('provider activation requires tariff unless on-premises and encrypts private credentials', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $this->actingAs($admin);
    $configuration = [
        'driver' => 'deepseek',
        'base_url' => 'https://104.18.0.1/v1',
        'key_mode' => 'custom',
        'custom_api_key' => 'never-serialize-this-key',
        'active_model_id' => 'custom-deepseek-model',
        'timeout_seconds' => 45,
        'temperature' => 0.2,
        'is_on_premises' => false,
    ];

    $this->post(route('setup.ai-center.provider.update'), $configuration)->assertSessionHasErrors('active_model_id');
    expect(XiamProviderConfiguration::query()->where('institution_id', $admin->actorScope()->institutionId)->exists())->toBeFalse();

    $this->post(route('setup.ai-center.tariffs.upsert'), [
        'model_id' => 'custom-deepseek-model',
        'provider_driver' => 'deepseek',
        'name' => 'Custom DeepSeek Model',
        'supports_tools' => true,
        'context_window' => 64000,
        'prompt_micro_per_token' => 0.2,
        'completion_micro_per_token' => 0.8,
        'cache_read_micro_per_token' => 0.05,
        'cache_write_micro_per_token' => 0.3,
        'reasoning_micro_per_token' => 0.4,
        'pricing_source' => 'manual',
    ])->assertSessionHas('success');
    $institutionId = (int) $admin->actorScope()->institutionId;
    $aiManager = Mockery::mock(AiManager::class);
    $aiManager->shouldReceive('forgetInstance')->once()->with("inst_{$institutionId}_v0_deepseek");
    app()->instance(AiManager::class, $aiManager);
    $this->post(route('setup.ai-center.provider.update'), $configuration)->assertSessionHas('success');

    $provider = XiamProviderConfiguration::query()->where('institution_id', $admin->actorScope()->institutionId)->firstOrFail();
    expect($provider->config_version)->toBe(1)
        ->and($provider->custom_api_key)->toBe('never-serialize-this-key')
        ->and($provider->toArray())->not->toHaveKey('custom_api_key')
        ->and((string) $provider->getRawOriginal('custom_api_key'))->not->toBe('never-serialize-this-key');

    $this->post(route('setup.ai-center.tariffs.upsert'), [
        'model_id' => 'private-model-with-zero-rate',
        'provider_driver' => 'deepseek',
        'name' => 'On-premises model',
        'supports_tools' => true,
        'context_window' => 64000,
        'prompt_micro_per_token' => 0,
        'completion_micro_per_token' => 0,
        'cache_read_micro_per_token' => 0,
        'cache_write_micro_per_token' => 0,
        'reasoning_micro_per_token' => 0,
        'pricing_source' => 'on_premises',
    ])->assertSessionHas('success');
    $configuration['active_model_id'] = 'private-model-with-zero-rate';
    $configuration['is_on_premises'] = true;
    $aiManager->shouldReceive('forgetInstance')->once()->with("inst_{$institutionId}_v1_deepseek");
    $this->post(route('setup.ai-center.provider.update'), $configuration)->assertSessionHas('success');
    expect($provider->fresh()->config_version)->toBe(2)
        ->and((float) XiamModelTariff::query()->where('model_id', 'private-model-with-zero-rate')->value('prompt_micro_per_token'))->toBe(0.0);
});

test('DeepSeek OpenAI and Gemini tariffs price disjoint token buckets and settle exact usage', function (): void {
    seedE2Foundation();
    $rates = [
        'prompt_micro_per_token' => 0.2,
        'completion_micro_per_token' => 0.8,
        'cache_read_micro_per_token' => 0.05,
        'cache_write_micro_per_token' => 0.3,
        'reasoning_micro_per_token' => 0.4,
    ];
    $openAi = new XiamModelTariff(['provider_driver' => 'openai', ...$rates]);
    $deepSeek = new XiamModelTariff(['provider_driver' => 'deepseek', ...$rates]);
    $gemini = new XiamModelTariff(['provider_driver' => 'gemini', ...$rates]);

    // OpenAI/DeepSeek completionTokens already includes reasoningTokens: 8 output - 2 reasoning = 6 standard.
    $openAiDisjoint = [
        'uncached_input_tokens' => 10,
        'cache_read_tokens' => 5,
        'cache_write_tokens' => 2,
        'non_reasoning_output_tokens' => 6,
        'reasoning_tokens' => 2,
    ];
    expect($openAi->calculateStepCostMicro($openAiDisjoint))->toBe(8)
        ->and($deepSeek->calculateStepCostMicro($openAiDisjoint))->toBe(8);

    // Gemini completion and thought counts are disjoint, so all 8 completion tokens use the standard rate.
    $geminiDisjoint = [...$openAiDisjoint, 'non_reasoning_output_tokens' => 8];
    expect($gemini->calculateStepCostMicro($geminiDisjoint))->toBe(10);

    $runId = (string) Str::uuid();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => (string) $actor->id,
        'institution_id' => $actor->actorScope()->institutionId,
        'office' => 'guidance',
        'run_id' => $runId,
        'unit' => 'asks',
        'amount' => 1,
        'state' => XiamUsageReservation::STATE_RESERVED,
        'reserved_tokens' => 0,
        'reserved_cost_micro' => 50,
    ]);

    app(UsageBudgetService::class)->settleWithUsage($runId, [
        'input_tokens' => 17,
        'output_tokens' => 8,
        'reasoning_tokens' => 2,
        'cache_read_tokens' => 5,
        'cache_write_tokens' => 2,
        'total_tokens' => 27,
    ], 8);

    $settled = XiamUsageReservation::query()->where('run_id', $runId)->firstOrFail();
    expect($settled->state)->toBe(XiamUsageReservation::STATE_SETTLED)
        ->and((int) $settled->reserved_tokens)->toBe(27)
        ->and((int) $settled->reserved_cost_micro)->toBe(8);
});

test('model discovery filters non-chat models and allows custom model configuration with on-premises or tariff', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $this->actingAs($admin);

    Http::preventStrayRequests();
    Http::fake([
        'https://104.18.0.1/v1/models' => Http::response(['data' => [
            ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
            ['id' => 'text-embedding-3-small', 'name' => 'Text Embedding Small'],
            ['id' => 'whisper-1', 'name' => 'Whisper Audio'],
            ['id' => 'dall-e-3', 'name' => 'DALL-E 3'],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini'],
        ]], 200),
    ]);

    $models = $this->postJson(route('setup.ai-center.models.discover'), [
        'driver' => 'openai',
        'base_url' => 'https://104.18.0.1/v1',
        'key_mode' => 'custom',
        'custom_api_key' => 'sk-test-secret',
    ])->assertOk()->json('models');

    $ids = collect($models)->pluck('id')->all();
    expect($ids)->toContain('gpt-4o', 'gpt-4o-mini')
        ->and($ids)->not->toContain('text-embedding-3-small')
        ->and($ids)->not->toContain('whisper-1')
        ->and($ids)->not->toContain('dall-e-3');

    $customConfig = [
        'driver' => 'openai-compatible',
        'base_url' => 'https://104.18.0.1/v1',
        'key_mode' => 'custom',
        'custom_api_key' => 'custom-key',
        'active_model_id' => 'ft:gpt-4o-mini:my-org:custom-checkpoint-v1',
        'timeout_seconds' => 30,
        'temperature' => 0.1,
        'is_on_premises' => true,
    ];

    $this->post(route('setup.ai-center.provider.update'), $customConfig)->assertSessionHas('success');
    $saved = XiamProviderConfiguration::query()->where('institution_id', $admin->actorScope()->institutionId)->firstOrFail();
    expect($saved->active_model_id)->toBe('ft:gpt-4o-mini:my-org:custom-checkpoint-v1')
        ->and($saved->is_on_premises)->toBeTrue();
});
