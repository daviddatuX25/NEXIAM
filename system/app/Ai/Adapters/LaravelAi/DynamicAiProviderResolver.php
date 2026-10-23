<?php

namespace App\Ai\Adapters\LaravelAi;

use App\Ai\Support\EndpointSecurityGuard;
use App\Models\Ai\XiamModelTariff;
use App\Models\Ai\XiamProviderConfiguration;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiManager;
use RuntimeException;

final class DynamicAiProviderResolver
{
    public function __construct(private readonly EndpointSecurityGuard $securityGuard) {}

    /** @return array{provider: string, driver: string, model: string, fallback_model: ?string, timeout: int, tariff: ?array<string, mixed>, fallback_provider: ?string, fallback_tariff: ?array<string, mixed>, curl_options: array<int, mixed>, max_tokens: ?int} */
    public function resolveForInstitution(int $institutionId): array
    {
        $configuration = Schema::hasTable('xiam_provider_configurations')
            ? XiamProviderConfiguration::query()->where('institution_id', $institutionId)->first()
            : null;

        if ($configuration === null) {
            $provider = (string) config('xiam.ai.provider', config('ai.default', 'openai'));
            $providerConfig = (array) config("ai.providers.{$provider}", []);
            $driver = (string) ($providerConfig['driver'] ?? $provider);
            $model = (string) config('xiam.ai.model', 'gpt-4o-mini');
            $tariff = XiamModelTariff::query()->where('model_id', $model)->where('provider_driver', $driver)->where('is_active', true)->first();

            return [
                'provider' => $provider,
                'driver' => $driver,
                'model' => $model,
                'fallback_model' => null,
                'timeout' => max(1, (int) config('xiam.ai.timeout', 30)),
                'tariff' => $tariff?->only(['id', 'model_id', 'provider_driver', 'prompt_micro_per_token', 'completion_micro_per_token', 'cache_read_micro_per_token', 'cache_write_micro_per_token', 'reasoning_micro_per_token']),
                'fallback_provider' => null,
                'fallback_tariff' => null,
                'curl_options' => [],
                'max_tokens' => null,
            ];
        }

        $driver = (string) $configuration->driver;
        $providerKey = "inst_{$institutionId}_v{$configuration->config_version}_{$driver}";
        $baseUrl = $this->securityGuard->normalizeVersionedBaseUrl($driver, $configuration->base_url);
        $endpoint = $this->securityGuard->assertAndResolveSafeEndpoint($baseUrl, $driver);
        $configuredKey = $configuration->key_mode === 'custom'
            ? (string) $configuration->custom_api_key
            : (string) config("ai.providers.{$driver}.key", '');
        $tariff = XiamModelTariff::query()->where('model_id', $configuration->active_model_id)->where('provider_driver', $driver)->where('is_active', true)->first();
        if ($tariff === null && str_ends_with($configuration->active_model_id, ':free')) {
            $tariff = XiamModelTariff::query()->firstOrCreate(
                ['model_id' => $configuration->active_model_id, 'provider_driver' => $driver],
                [
                    'name' => $configuration->active_model_id,
                    'supports_tools' => true,
                    'context_window' => 128000,
                    'prompt_micro_per_token' => 0,
                    'completion_micro_per_token' => 0,
                    'cache_read_micro_per_token' => 0,
                    'cache_write_micro_per_token' => 0,
                    'reasoning_micro_per_token' => 0,
                    'pricing_source' => 'free_tier',
                    'is_active' => true,
                ]
            );
        }
        if ($tariff === null && ! $configuration->is_on_premises) {
            throw new RuntimeException('Model pricing tariff is required before running inference.');
        }
        $fallbackTariff = $configuration->fallback_model_id === null ? null : XiamModelTariff::query()
            ->where('model_id', $configuration->fallback_model_id)->where('provider_driver', $driver)->where('is_active', true)->first();
        if ($fallbackTariff === null && $configuration->fallback_model_id !== null && str_ends_with($configuration->fallback_model_id, ':free')) {
            $fallbackTariff = XiamModelTariff::query()->firstOrCreate(
                ['model_id' => $configuration->fallback_model_id, 'provider_driver' => $driver],
                [
                    'name' => $configuration->fallback_model_id,
                    'supports_tools' => true,
                    'context_window' => 128000,
                    'prompt_micro_per_token' => 0,
                    'completion_micro_per_token' => 0,
                    'cache_read_micro_per_token' => 0,
                    'cache_write_micro_per_token' => 0,
                    'reasoning_micro_per_token' => 0,
                    'pricing_source' => 'free_tier',
                    'is_active' => true,
                ]
            );
        }
        if ($configuration->fallback_model_id !== null && $fallbackTariff === null && ! $configuration->is_on_premises) {
            throw new RuntimeException('Model pricing tariff is required before running inference.');
        }

        config(["ai.providers.{$providerKey}" => [
            'driver' => $driver,
            'url' => $baseUrl,
            'key' => $configuredKey,
            'curl_options' => $endpoint['curl_options'],
            'models' => ['text' => ['default' => $configuration->active_model_id]],
        ]]);
        if ($configuration->config_version > 1) {
            app(AiManager::class)->forgetInstance("inst_{$institutionId}_v".($configuration->config_version - 1)."_{$driver}");
        }
        $fallbackProvider = $configuration->fallback_model_id === null ? null : $providerKey.'_fallback';
        if ($fallbackProvider !== null) {
            config(["ai.providers.{$fallbackProvider}" => config("ai.providers.{$providerKey}")]);
        }

        return [
            'provider' => $providerKey,
            'driver' => $driver,
            'model' => (string) $configuration->active_model_id,
            'fallback_model' => $configuration->fallback_model_id,
            'timeout' => max(1, (int) $configuration->timeout_seconds),
            'tariff' => $tariff?->only(['id', 'model_id', 'provider_driver', 'prompt_micro_per_token', 'completion_micro_per_token', 'cache_read_micro_per_token', 'cache_write_micro_per_token', 'reasoning_micro_per_token']),
            'fallback_provider' => $fallbackProvider,
            'fallback_tariff' => $fallbackTariff?->only(['id', 'model_id', 'provider_driver', 'prompt_micro_per_token', 'completion_micro_per_token', 'cache_read_micro_per_token', 'cache_write_micro_per_token', 'reasoning_micro_per_token']),
            'curl_options' => $endpoint['curl_options'],
            'max_tokens' => $configuration->max_tokens,
        ];
    }
}
