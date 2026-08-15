<?php

namespace App\Ai\Adapters\Fake;

use App\Ai\Contracts\AiGateway;

/**
 * Deterministic fake gateway — OpenSpec 3/6 (no network).
 */
final class FakeAiGateway implements AiGateway
{
    /** @var array<string, bool> */
    private array $cancelRequested = [];

    public function generate(array $request): array
    {
        if ($this->cancelRequested[$request['run_id'] ?? ''] ?? false) {
            return [
                'text' => '',
                'finish_reason' => 'cancelled',
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
                'tool_requests' => [],
            ];
        }

        $prompt = trim((string) ($request['prompt'] ?? ''));
        $text = $this->replyFor($prompt, $request);

        return [
            'text' => $text,
            'finish_reason' => 'stop',
            'usage' => [
                'input_tokens' => max(1, strlen($prompt)),
                'output_tokens' => max(1, strlen($text)),
            ],
            'tool_requests' => [],
        ];
    }

    public function stream(array $request): iterable
    {
        $result = $this->generate($request);
        $text = (string) $result['text'];
        $chunks = str_split($text, max(12, (int) ceil(strlen($text) / 3))) ?: [''];

        foreach ($chunks as $chunk) {
            if ($this->cancelRequested[$request['run_id'] ?? ''] ?? false) {
                yield ['type' => 'cancelled', 'finish_reason' => 'cancelled'];

                return;
            }
            yield ['type' => 'delta', 'text' => $chunk];
        }

        yield [
            'type' => 'done',
            'finish_reason' => (string) $result['finish_reason'],
            'usage' => $result['usage'] ?? null,
        ];
    }

    public function health(): array
    {
        return [
            'ok' => true,
            'provider' => 'fake',
            'degraded' => false,
        ];
    }

    public function requestCancel(string $runId): bool
    {
        $this->cancelRequested[$runId] = true;

        return true;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function replyFor(string $prompt, array $request): string
    {
        /** @var list<array<string, mixed>> $evidence */
        $evidence = is_array($request['evidence'] ?? null) ? $request['evidence'] : [];
        /** @var array<string, mixed>|null $ownData */
        $ownData = is_array($request['own_data'] ?? null) ? $request['own_data'] : null;
        $needle = mb_strtolower($prompt);
        $actorType = (string) ($request['actor_type'] ?? 'staff');

        if ($ownData !== null && (bool) ($ownData['available'] ?? false)) {
            $asksScore = str_contains($needle, 'score')
                || str_contains($needle, 'result')
                || str_contains($needle, 'grade');
            if ($asksScore && ! (bool) ($ownData['results']['released'] ?? false)) {
                return 'Your exam results are not released yet. I cannot disclose scores until an official result release is current. Use the ordinary portal when results are published.';
            }

            $status = (string) ($ownData['attempt']['status'] ?? 'unknown');
            $ref = (string) ($ownData['attempt']['reference_code'] ?? '');
            $schedule = (string) ($ownData['schedule']['summary'] ?? '');
            $refBit = $ref !== '' ? " (reference {$ref})" : '';
            $scheduleBit = $schedule !== '' ? ' '.$schedule : '';

            return "Your application status is “{$status}”{$refBit}.{$scheduleBit}";
        }

        if ($evidence !== []) {
            $title = (string) ($evidence[0]['source_title'] ?? 'policy source');
            $excerpt = trim((string) ($evidence[0]['excerpt'] ?? ''));
            $snippet = $excerpt !== '' ? mb_substr($excerpt, 0, 160) : 'the cited policy text';

            return "Based on “{$title}”: {$snippet}";
        }

        if ($prompt === '') {
            return 'I did not receive a question. Ask about this page or use the ordinary controls.';
        }

        if (str_contains($needle, 'readiness')) {
            return 'Setup readiness is available on the Setup hub. I can orient you, but catalog changes use the ordinary Setup pages.';
        }

        if ($actorType === 'applicant') {
            return 'I do not have approved policy support for that, and it is not part of your authorized application status. Use the ordinary portal pages, or ask about your application status or published policy.';
        }

        return 'I do not have approved institutional policy support for that in the corpus. Use Knowledge admin to publish policy, or use ordinary page controls.';
    }
}
