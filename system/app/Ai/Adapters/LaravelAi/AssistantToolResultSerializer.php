<?php

namespace App\Ai\Adapters\LaravelAi;

use App\Ai\Policy\InputMinimizer;
use App\Application\Capabilities\CapabilityResult;

final class AssistantToolResultSerializer
{
    private const MAX_SERIALIZED_RESULT_BYTES = 8192;

    public function __construct(private readonly InputMinimizer $inputMinimizer) {}

    /**
     * @param  array<string, mixed>|null  $safeProposal
     */
    public function serialize(
        string $capabilityId,
        CapabilityResult $result,
        mixed $safeData,
        ?array $safeProposal = null,
    ): string {
        $responsePayload = [
            'ok' => $result->ok,
            'status' => $result->status,
            'capability_id' => $result->capabilityId,
            'data' => $this->untrustedPayload($safeData),
            'reason' => $result->reason !== null
                ? str_replace(['<', '>'], ['&lt;', '&gt;'], $this->inputMinimizer->minimize($result->reason))
                : null,
        ];

        if (is_array($safeProposal)) {
            if (array_key_exists('summary', $safeProposal)) {
                $safeProposal['summary'] = $this->untrustedPayload($safeProposal['summary']);
            }
            if (array_key_exists('content', $safeProposal)) {
                $safeProposal['content'] = $this->untrustedPayload($safeProposal['content']);
            }
            $responsePayload['proposal'] = $safeProposal;
        }

        $response = (string) json_encode($responsePayload, JSON_THROW_ON_ERROR);

        if (strlen($response) > self::MAX_SERIALIZED_RESULT_BYTES) {
            return $this->toolResultTooLarge($capabilityId);
        }

        return $response;
    }

    private function untrustedPayload(mixed $content): string
    {
        $minimized = $this->minimizeStrings($content);
        $serialized = (string) json_encode(
            $minimized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $escaped = str_replace(['<', '>'], ['&lt;', '&gt;'], $serialized);

        return '<xiam_untrusted_tool_result>'.$escaped.'</xiam_untrusted_tool_result>';
    }

    private function minimizeStrings(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->inputMinimizer->minimize($value);
        }
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->minimizeStrings($item);
        }

        return $value;
    }

    private function toolResultTooLarge(string $capabilityId): string
    {
        return (string) json_encode([
            'ok' => false,
            'status' => 'tool_result_too_large',
            'capability_id' => $capabilityId,
            'data' => null,
            'reason' => 'Tool result exceeded the safe response size limit.',
        ], JSON_THROW_ON_ERROR);
    }
}
