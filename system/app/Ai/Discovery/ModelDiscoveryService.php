<?php

namespace App\Ai\Discovery;

use App\Ai\Support\EndpointSecurityGuard;
use App\Models\Ai\XiamModelTariff;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class ModelDiscoveryService
{
    public function __construct(private EndpointSecurityGuard $securityGuard) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function discover(string $driver, ?string $baseUrl, string $apiKey): array
    {
        $normalizedUrl = $this->securityGuard->normalizeVersionedBaseUrl($driver, $baseUrl);
        $resolved = $this->securityGuard->assertAndResolveSafeEndpoint($normalizedUrl, $driver);
        $endpoint = rtrim($normalizedUrl, '/').'/models';
        $request = Http::withoutRedirecting()
            ->timeout(10)
            ->withOptions(['curl' => $resolved['curl_options']])
            ->acceptJson();

        try {
            $response = $this->sendRequest($request, $endpoint, $driver, $apiKey);
            $this->securityGuard->enforceResponseSizeCeiling(
                $response->body(),
                (int) ($response->header('Content-Length') ?? 0),
            );
            if ($response->status() === 401 || $response->status() === 403) {
                throw new InvalidArgumentException('Provider credentials were rejected for model discovery (HTTP '.$response->status().').');
            }
            if ($response->status() === 404) {
                throw new InvalidArgumentException('Endpoint does not support /models discovery. You may type your model ID manually.');
            }
            $response->throw();
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new InvalidArgumentException('Connection to model discovery endpoint timed out. You may type your model ID manually.');
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Model discovery returned an invalid response payload.');
        }

        if ($driver === 'openrouter') {
            return $this->discoverOpenRouterModels($payload);
        }

        $models = match ($driver) {
            'gemini' => $this->parseGeminiModels($payload),
            'anthropic' => $this->parseAnthropicModels($payload),
            'openai', 'deepseek', 'groq', 'ollama', 'openai-compatible' => $this->parseOpenAiCompatibleModels($payload),
            default => throw new InvalidArgumentException("Model discovery is not supported for driver [{$driver}]."),
        };

        return $this->attachTariffInformation($models, $driver);
    }

    private function sendRequest(PendingRequest $request, string $endpoint, string $driver, string $apiKey): Response
    {
        if ($driver === 'gemini') {
            $separator = str_contains($endpoint, '?') ? '&' : '?';

            return $request->get($endpoint.$separator.http_build_query(['key' => $apiKey]));
        }

        if ($driver === 'anthropic') {
            return $request
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->get($endpoint);
        }

        if ($driver !== 'ollama' && $apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        return $request->get($endpoint);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function discoverOpenRouterModels(array $payload): array
    {
        $models = [];
        foreach ($payload['data'] ?? [] as $model) {
            if (! is_array($model) || ! is_string($model['id'] ?? null)) {
                continue;
            }

            $modelId = $model['id'];
            $pricing = is_array($model['pricing'] ?? null) ? $model['pricing'] : [];
            $rates = [
                'prompt_micro_per_token' => $this->rateInMicroDollars($pricing['prompt'] ?? null),
                'completion_micro_per_token' => $this->rateInMicroDollars($pricing['completion'] ?? null),
                'cache_read_micro_per_token' => $this->rateInMicroDollars($pricing['input_cache_read'] ?? null),
                'cache_write_micro_per_token' => $this->rateInMicroDollars($pricing['input_cache_write'] ?? null),
                'reasoning_micro_per_token' => $this->rateInMicroDollars($pricing['internal_reasoning'] ?? null),
            ];

            $hasPricingData = isset($pricing['prompt']) || str_ends_with($modelId, ':free');
            if ($hasPricingData) {
                XiamModelTariff::query()->updateOrCreate(
                    [
                        'model_id' => $modelId,
                        'provider_driver' => 'openrouter',
                    ],
                    [
                        'name' => (string) ($model['name'] ?? $modelId),
                        'supports_tools' => is_array($model['supported_parameters'] ?? null) && in_array('tools', $model['supported_parameters'], true),
                        'context_window' => (int) ($model['context_length'] ?? 128_000),
                        ...$rates,
                        'pricing_source' => str_ends_with($modelId, ':free') ? 'free_tier' : 'openrouter_api',
                        'is_active' => true,
                    ],
                );
            }

            $models[] = [
                'id' => $modelId,
                'name' => (string) ($model['name'] ?? $modelId),
                'supports_tools' => is_array($model['supported_parameters'] ?? null) && in_array('tools', $model['supported_parameters'], true),
                'context_window' => isset($model['context_length']) ? (int) $model['context_length'] : null,
                'has_tariff' => $hasPricingData,
            ];
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function parseGeminiModels(array $payload): array
    {
        $models = [];
        foreach ($payload['models'] ?? [] as $model) {
            if (! is_array($model) || ! is_string($model['name'] ?? null)) {
                continue;
            }

            $supportedMethods = $model['supportedGenerationMethods'] ?? [];
            if (is_array($supportedMethods) && ! in_array('generateContent', $supportedMethods, true)) {
                continue;
            }

            $id = preg_replace('#^models/#', '', $model['name']);
            if (! is_string($id) || $id === '') {
                continue;
            }

            $models[] = [
                'id' => $id,
                'name' => (string) ($model['displayName'] ?? $id),
                'supports_tools' => is_array($supportedMethods) && in_array('generateContent', $supportedMethods, true),
                'context_window' => isset($model['inputTokenLimit']) ? (int) $model['inputTokenLimit'] : null,
            ];
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function parseAnthropicModels(array $payload): array
    {
        $models = [];
        foreach ($payload['data'] ?? [] as $model) {
            if (! is_array($model) || ! is_string($model['id'] ?? null)) {
                continue;
            }

            $models[] = [
                'id' => $model['id'],
                'name' => (string) ($model['display_name'] ?? $model['id']),
                'supports_tools' => true,
                'context_window' => isset($model['context_window']) ? (int) $model['context_window'] : null,
            ];
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function parseOpenAiCompatibleModels(array $payload): array
    {
        $models = [];
        foreach ($payload['data'] ?? [] as $model) {
            if (! is_array($model) || ! is_string($model['id'] ?? null)) {
                continue;
            }
            $id = $model['id'];
            if ($this->isNonChatModel($id)) {
                continue;
            }

            $models[] = [
                'id' => $id,
                'name' => (string) ($model['name'] ?? $id),
                'supports_tools' => (bool) ($model['supports_tools'] ?? true),
                'context_window' => isset($model['context_length']) ? (int) $model['context_length'] : null,
            ];
        }

        return $models;
    }

    private function isNonChatModel(string $modelId): bool
    {
        $id = strtolower($modelId);
        $nonChatPatterns = [
            'embedding', 'whisper', 'dall-e', 'tts', 'moderation',
            'davinci', 'babbage', 'curie', 'canary',
        ];

        foreach ($nonChatPatterns as $pattern) {
            if (str_contains($id, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $models
     * @return list<array<string, mixed>>
     */
    private function attachTariffInformation(array $models, string $driver): array
    {
        $modelIds = array_values(array_unique(array_column($models, 'id')));
        $tariffs = XiamModelTariff::query()
            ->where('provider_driver', $driver)
            ->whereIn('model_id', $modelIds)
            ->get()
            ->keyBy('model_id');

        foreach ($models as &$model) {
            $tariff = $tariffs->get($model['id']);
            $model['has_tariff'] = $tariff !== null;
            $model['pricing'] = $tariff === null ? null : [
                'prompt_micro_per_token' => $tariff->prompt_micro_per_token,
                'completion_micro_per_token' => $tariff->completion_micro_per_token,
                'cache_read_micro_per_token' => $tariff->cache_read_micro_per_token,
                'cache_write_micro_per_token' => $tariff->cache_write_micro_per_token,
                'reasoning_micro_per_token' => $tariff->reasoning_micro_per_token,
            ];
        }
        unset($model);

        return $models;
    }

    private function rateInMicroDollars(mixed $rate): float
    {
        return is_numeric($rate) ? (float) $rate * 1_000_000 : 0.0;
    }
}
