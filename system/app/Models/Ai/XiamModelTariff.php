<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

final class XiamModelTariff extends Model
{
    protected $table = 'xiam_model_tariffs';

    protected $fillable = [
        'model_id',
        'provider_driver',
        'name',
        'supports_tools',
        'context_window',
        'prompt_micro_per_token',
        'completion_micro_per_token',
        'cache_read_micro_per_token',
        'cache_write_micro_per_token',
        'reasoning_micro_per_token',
        'pricing_source',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports_tools' => 'boolean',
            'context_window' => 'integer',
            'prompt_micro_per_token' => 'float',
            'completion_micro_per_token' => 'float',
            'cache_read_micro_per_token' => 'float',
            'cache_write_micro_per_token' => 'float',
            'reasoning_micro_per_token' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  array{
     *     uncached_input_tokens?: int|float,
     *     cache_read_tokens?: int|float,
     *     cache_write_tokens?: int|float,
     *     non_reasoning_output_tokens?: int|float,
     *     reasoning_tokens?: int|float
     * }  $disjointTokens
     */
    public function calculateStepCostMicro(array $disjointTokens): int
    {
        $uncachedInput = (float) ($disjointTokens['uncached_input_tokens'] ?? 0);
        $cacheRead = (float) ($disjointTokens['cache_read_tokens'] ?? 0);
        $cacheWrite = (float) ($disjointTokens['cache_write_tokens'] ?? 0);
        $nonReasoningOutput = (float) ($disjointTokens['non_reasoning_output_tokens'] ?? 0);
        $reasoningOutput = (float) ($disjointTokens['reasoning_tokens'] ?? 0);
        $reasoningRate = $this->reasoning_micro_per_token > 0
            ? $this->reasoning_micro_per_token
            : $this->completion_micro_per_token;

        return (int) round(
            $uncachedInput * $this->prompt_micro_per_token
            + $cacheRead * $this->cache_read_micro_per_token
            + $cacheWrite * $this->cache_write_micro_per_token
            + $nonReasoningOutput * $this->completion_micro_per_token
            + $reasoningOutput * $reasoningRate,
        );
    }
}
