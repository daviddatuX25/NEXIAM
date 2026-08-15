<?php

namespace App\Ai\Contracts;

/**
 * NEXIAM-owned provider port (AD-13). No SDK types cross this boundary.
 */
interface AiGateway
{
    /**
     * @param  array{prompt: string, conversation_id: string, run_id: string, mode: string}  $request
     * @return array{text: string, finish_reason: string, usage?: array{input_tokens?: int, output_tokens?: int}|null, tool_requests?: list<array{name: string, arguments: array<string, mixed>}>}
     */
    public function generate(array $request): array;

    /**
     * @param  array{prompt: string, conversation_id: string, run_id: string, mode: string}  $request
     * @return iterable<int, array{type: string, text?: string, finish_reason?: string, usage?: array<string, int>|null}>
     */
    public function stream(array $request): iterable;

    public function health(): array;

    public function requestCancel(string $runId): bool;
}
