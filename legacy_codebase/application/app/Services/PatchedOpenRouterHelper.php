<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;
use MoeMizrak\LaravelOpenrouter\DTO\ChoiceData;
use MoeMizrak\LaravelOpenrouter\DTO\CompletionTokensDetailsData;
use MoeMizrak\LaravelOpenrouter\DTO\DeltaData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\NonChatChoiceData;
use MoeMizrak\LaravelOpenrouter\DTO\NonStreamingChoiceData;
use MoeMizrak\LaravelOpenrouter\DTO\PromptTokensDetailsData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseData;
use MoeMizrak\LaravelOpenrouter\DTO\StreamingChoiceData;
use MoeMizrak\LaravelOpenrouter\DTO\UsageData;
use ReflectionException;

/**
 * Patched OpenRouter helper that properly maps raw choice arrays to DTO instances.
 *
 * Background: OpenRouterHelper::formChatResponse() passes raw arrays directly to
 * ResponseData::$choices (e.g. choices: Arr::get($response, 'choices')), which causes
 * Spatie's Data transformation to fail with "Argument #1 ($data) must be of type
 * Spatie\LaravelData\Contracts\BaseData, array given".
 *
 * This patch properly maps each raw choice to the appropriate ChoiceData subclass.
 *
 * Class PatchedOpenRouterHelper
 */
final class PatchedOpenRouterHelper
{
    /**
     * Forms a ResponseData from the OpenRouter API response array.
     * Properly maps choices to ChoiceData DTOs to prevent Spatie transformation errors.
     *
     * @throws ReflectionException
     */
    public function formChatResponseFromArray(?array $response): ResponseData
    {
        $usageArray = Arr::get($response, 'usage');

        $promptDetailsData = Arr::get($usageArray, 'prompt_tokens_details');
        $promptTokensDetails = $promptDetailsData
            ? new PromptTokensDetailsData(
                cached_tokens: Arr::get($promptDetailsData, 'cached_tokens'),
                cache_write_tokens: Arr::get($promptDetailsData, 'cache_write_tokens'),
                audio_tokens: Arr::get($promptDetailsData, 'audio_tokens'),
                video_tokens: Arr::get($promptDetailsData, 'video_tokens'),
            )
            : null;

        $completionDetailsData = Arr::get($usageArray, 'completion_tokens_details');
        $completionTokensDetails = $completionDetailsData
            ? new CompletionTokensDetailsData(
                reasoning_tokens: Arr::get($completionDetailsData, 'reasoning_tokens'),
                audio_tokens: Arr::get($completionDetailsData, 'audio_tokens'),
                image_tokens: Arr::get($completionDetailsData, 'image_tokens'),
                accepted_prediction_tokens: Arr::get($completionDetailsData, 'accepted_prediction_tokens'),
                rejected_prediction_tokens: Arr::get($completionDetailsData, 'rejected_prediction_tokens'),
            )
            : null;

        $usage = new UsageData(
            prompt_tokens: Arr::get($usageArray, 'prompt_tokens'),
            completion_tokens: Arr::get($usageArray, 'completion_tokens'),
            total_tokens: Arr::get($usageArray, 'total_tokens'),
            cost: Arr::get($usageArray, 'cost'),
            prompt_tokens_details: $promptTokensDetails,
            completion_tokens_details: $completionTokensDetails,
        );

        $choices = $this->mapChoices(Arr::get($response, 'choices', []));

        return new ResponseData(
            id: Arr::get($response, 'id'),
            model: Arr::get($response, 'model'),
            object: Arr::get($response, 'object'),
            created: Arr::get($response, 'created'),
            provider: Arr::get($response, 'provider'),
            citations: Arr::get($response, 'citations'),
            choices: $choices,
            usage: $usage,
        );
    }

    /**
     * Map raw choice arrays to proper ChoiceData DTO instances.
     *
     * @param  array<array>  $rawChoices
     * @return array<NonStreamingChoiceData|NonChatChoiceData|StreamingChoiceData>
     */
    private function mapChoices(array $rawChoices): array
    {
        $choices = [];
        foreach ($rawChoices as $raw) {
            $type = $raw['type'] ?? null;

            if ($type === 'streaming') {
                // streaming choices have delta instead of message
                if (isset($raw['delta'])) {
                    $delta = new DeltaData(
                        role: $raw['delta']['role'] ?? null,
                        content: $raw['delta']['content'] ?? null,
                    );
                    $choices[] = new StreamingChoiceData(
                        delta: $delta,
                        finish_reason: $raw['finish_reason'] ?? null,
                    );
                }
            } elseif ($type === 'non_chat') {
                // non-chat completions
                $choices[] = new NonChatChoiceData(
                    text: $raw['text'] ?? '',
                    finish_reason: $raw['finish_reason'] ?? null,
                );
            } else {
                // Default: non-streaming chat completion
                $messageData = null;
                if (isset($raw['message'])) {
                    $messageArr = $raw['message'];
                    // Handle content that might be a string or array of content parts
                    $content = $messageArr['content'] ?? '';
                    if (is_array($content)) {
                        // AI may return content blocks — just use first text block
                        foreach ($content as $block) {
                            if (($block['type'] ?? '') === 'text') {
                                $content = $block['text'] ?? '';
                                break;
                            }
                        }
                    }
                    $messageData = new MessageData(
                        role: $messageArr['role'] ?? null,
                        content: is_string($content) ? $content : null,
                        refusal: $messageArr['refusal'] ?? null,
                        reasoning: $messageArr['reasoning'] ?? null,
                    );
                }
                $choices[] = new NonStreamingChoiceData(
                    message: $messageData ?? new MessageData,
                    finish_reason: $raw['finish_reason'] ?? null,
                );
            }
        }

        return $choices;
    }
}
