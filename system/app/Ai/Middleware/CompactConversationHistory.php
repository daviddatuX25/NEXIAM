<?php

namespace App\Ai\Middleware;

use App\Ai\Adapters\LaravelAi\XiamAgent;
use Closure;
use Laravel\Ai\Agents\SummarizeAgent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;

final class CompactConversationHistory
{
    private const int MESSAGE_THRESHOLD = 10;

    private const int RECENT_MESSAGE_COUNT = 6;

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        if (! $prompt->agent instanceof XiamAgent) {
            return $next($prompt);
        }

        $messages = collect($prompt->agent->messages())->values()->all();

        if (count($messages) <= self::MESSAGE_THRESHOLD) {
            return $next($prompt);
        }

        $olderMessages = array_slice($messages, 0, -self::RECENT_MESSAGE_COUNT);
        $transcript = collect($olderMessages)
            ->map(fn (Message $message): string => sprintf(
                '%s: %s',
                $message->role->value,
                $message->content ?? '',
            ))
            ->implode("\n");

        try {
            $summary = trim((new SummarizeAgent(sentences: 3))->prompt(
                $transcript,
                provider: $prompt->provider,
                model: $prompt->model,
                timeout: 5,
            )->text);
        } catch (\Throwable) {
            return $next($prompt);
        }

        if ($summary === '') {
            return $next($prompt);
        }

        $agent = $prompt->agent->withMessages([
            new Message('assistant', "Summary of earlier conversation: {$summary}"),
            ...array_slice($messages, -self::RECENT_MESSAGE_COUNT),
        ]);

        return $next(new AgentPrompt(
            agent: $agent,
            prompt: $prompt->prompt,
            attachments: $prompt->attachments,
            provider: $prompt->provider,
            model: $prompt->model,
            timeout: $prompt->timeout,
            invocationId: $prompt->invocationId,
            approvalDecisions: $prompt->approvalDecisions,
            parentInvocationId: $prompt->parentInvocationId,
            parentToolInvocationId: $prompt->parentToolInvocationId,
            isFinalAttempt: $prompt->isFinalAttempt(),
        ));
    }
}
