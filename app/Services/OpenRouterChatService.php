<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use JsonException;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ErrorData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseFormatData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;

/**
 * Patched OpenRouter chat service that correctly maps raw API responses to ResponseData DTOs.
 *
 * Replaces ExamSchedulingAssistantService::chat() — this wrapper handles the raw HTTP response
 * from Guzzle and maps it properly using PatchedOpenRouterHelper, avoiding the Spatie
 * "array given" error that occurs when OpenRouterHelper::formChatResponse passes raw arrays
 * to ResponseData::$choices.
 *
 * Class OpenRouterChatService
 */
final class OpenRouterChatService
{
    private PatchedOpenRouterHelper $helper;

    public function __construct()
    {
        $this->helper = new PatchedOpenRouterHelper;
    }

    /**
     * Send a chat request to OpenRouter and return the parsed ResponseData.
     *
     * @throws ReflectionException|GuzzleException
     */
    public function chatRequest(ChatData $chatData): ErrorData|ResponseData
    {
        // The path for the chat completion request.
        $chatCompletionPath = 'chat/completions';

        // Detect if stream chat completion is requested
        if ($chatData->stream) {
            return new ErrorData(
                code: 400,
                message: 'For stream chat completion please use "chatStreamRequest" method instead!',
            );
        }

        // Convert ChatData to array for the HTTP request
        $requestData = $this->prepareRequestData($chatData);

        // Make the HTTP request
        $response = LaravelOpenRouter::request(
            method: 'POST',
            path: $chatCompletionPath,
            data: $requestData,
        );

        // Decode JSON response
        $decoded = $this->decodeResponse($response);

        // Handle error responses
        if (Arr::get($decoded, 'error')) {
            return new ErrorData(
                code: Arr::get($decoded, 'error.code', 500),
                message: Arr::get($decoded, 'error.message', 'Unknown error from OpenRouter API.'),
                metadata: Arr::get($decoded, 'error.metadata'),
            );
        }

        // Use the patched helper to form ResponseData correctly (maps choices to DTOs)
        return $this->helper->formChatResponseFromArray($decoded);
    }

    /**
     * Prepare ChatData for HTTP request.
     */
    private function prepareRequestData(ChatData $chatData): array
    {
        $data = array_filter([
            'model' => $chatData->model,
            'messages' => array_map(
                fn ($m) => $m->convertToArray(),
                $chatData->messages ?? []
            ),
            'max_tokens' => $chatData->max_tokens,
            'temperature' => $chatData->temperature,
            'top_p' => $chatData->top_p,
            'top_k' => $chatData->top_k,
            'stop' => $chatData->stop,
            'ripple' => $chatData->ripple,
            'provider' => $chatData->provider?->require_parameters !== null ? [
                'require_parameters' => $chatData->provider->require_parameters,
            ] : null,
            'response_format' => $chatData->response_format
                ? $this->convertResponseFormat($chatData->response_format)
                : null,
        ], fn ($value) => $value !== null);

        if ($chatData->stream) {
            $data['stream'] = true;
        }

        return $data;
    }

    /**
     * Convert ResponseFormatData to array for HTTP request.
     */
    private function convertResponseFormat(ResponseFormatData $responseFormat): array
    {
        if ($responseFormat->type === 'json_schema' && $responseFormat->json_schema) {
            return [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $responseFormat->json_schema->name,
                    'strict' => $responseFormat->json_schema->strict ?? false,
                    'schema' => $responseFormat->json_schema->schema ?? [],
                ],
            ];
        }

        return ['type' => $responseFormat->type ?? 'text'];
    }

    /**
     * Decode the HTTP response body to an array.
     */
    private function decodeResponse(?ResponseInterface $response): array
    {
        if (! $response) {
            return [];
        }

        try {
            return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
    }
}
