<?php

namespace Database\Seeders;

use App\Models\Ai\XiamModelTariff;
use Illuminate\Database\Seeder;

final class XiamModelTariffSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->baselineTariffs() as $tariff) {
            XiamModelTariff::query()->updateOrCreate(
                [
                    'model_id' => $tariff['model_id'],
                    'provider_driver' => $tariff['provider_driver'],
                ],
                $tariff,
            );
        }
    }

    /**
     * @return list<array{
     *     model_id: string,
     *     provider_driver: string,
     *     name: string,
     *     prompt_micro_per_token: float,
     *     completion_micro_per_token: float,
     *     cache_read_micro_per_token: float,
     *     cache_write_micro_per_token: float,
     *     reasoning_micro_per_token: float,
     *     pricing_source: string,
     *     is_active: bool
     * }>
     */
    private function baselineTariffs(): array
    {
        $tariffs = [];

        foreach ([
            'gemini' => [
                ['gemini-2.5-flash', 0.15, 0.60, 0.0375, 0, 0.60],
                ['gemini-2.5-pro', 1.25, 10.00, 0.3125, 0, 10.00],
                ['gemini-2.0-flash', 0.10, 0.40, 0.025, 0, 0],
            ],
            'openai' => [
                ['gpt-4o-mini', 0.15, 0.60, 0.075, 0, 0],
                ['gpt-4o', 2.50, 10.00, 1.25, 0, 0],
            ],
            'deepseek' => [
                ['deepseek-chat', 0.27, 1.10, 0.07, 0, 0],
                ['deepseek-reasoner', 0.55, 2.19, 0.14, 0, 2.19],
            ],
            'openai-compatible' => [
                ['deepseek-chat', 0.27, 1.10, 0.07, 0, 0],
                ['deepseek-reasoner', 0.55, 2.19, 0.14, 0, 2.19],
                ['gpt-4o-mini', 0.15, 0.60, 0.075, 0, 0],
            ],
            'anthropic' => [
                ['claude-sonnet-4-20250514', 3.00, 15.00, 0.30, 3.75, 0],
            ],
            'openrouter' => [
                ['google/gemini-2.5-flash', 0.15, 0.60, 0.0375, 0, 0],
                ['deepseek/deepseek-chat', 0.27, 1.10, 0.07, 0, 0],
                ['openai/gpt-4o-mini', 0.15, 0.60, 0.075, 0, 0],
            ],
        ] as $driver => $models) {
            foreach ($models as [$modelId, $prompt, $completion, $cacheRead, $cacheWrite, $reasoning]) {
                $tariffs[] = [
                    'model_id' => $modelId,
                    'provider_driver' => $driver,
                    'name' => $modelId,
                    'prompt_micro_per_token' => $prompt,
                    'completion_micro_per_token' => $completion,
                    'cache_read_micro_per_token' => $cacheRead,
                    'cache_write_micro_per_token' => $cacheWrite,
                    'reasoning_micro_per_token' => $reasoning,
                    'pricing_source' => 'official_baseline',
                    'is_active' => true,
                ];
            }
        }

        return $tariffs;
    }
}
