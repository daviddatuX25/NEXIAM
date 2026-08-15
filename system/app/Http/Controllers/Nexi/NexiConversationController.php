<?php

namespace App\Http\Controllers\Nexi;

use App\Ai\Conversation\ContextEpochService;
use App\Http\Controllers\Controller;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiRunEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NexiConversationController extends Controller
{
    public function snapshot(Request $request, string $conversation): JsonResponse
    {
        $model = $this->authorizedConversation($request, $conversation);
        if ($model === null) {
            return response()->json(['error' => 'denied'], 403);
        }

        $after = max(0, (int) $request->query('after', 0));
        $events = NexiRunEvent::query()
            ->where('conversation_id', $model->id)
            ->where('seq', '>', $after)
            ->orderBy('seq')
            ->limit(500)
            ->get()
            ->map(fn (NexiRunEvent $event) => [
                'id' => $event->id,
                'run_id' => $event->run_id,
                'seq' => $event->seq,
                'type' => $event->type,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->values();

        $latestRun = $model->runs()->latest('created_at')->first();
        $cursor = (int) NexiRunEvent::query()->where('conversation_id', $model->id)->max('seq');

        $messages = [];
        foreach ($model->turns()->orderBy('seq')->with('run.events')->get() as $turn) {
            $messages[] = [
                'role' => 'user',
                'content' => $turn->input_text,
                'turn_id' => $turn->id,
            ];
            $assistant = $turn->run?->events
                ->firstWhere('type', 'assistant.message');
            if ($assistant !== null) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => (string) ($assistant->payload['content'] ?? ''),
                    'run_id' => $turn->run?->id,
                    'citations' => $assistant->payload['citations'] ?? [],
                    'finish_reason' => $assistant->payload['finish_reason'] ?? null,
                ];
            } else {
                $refusal = $turn->run?->events->firstWhere('type', 'assistant.refusal');
                if ($refusal !== null) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => 'No approved policy citation is available for that question.',
                        'run_id' => $turn->run?->id,
                        'citations' => [],
                        'finish_reason' => 'refuse',
                    ];
                }
            }
        }

        return response()->json([
            'conversation_id' => $model->id,
            'context_epoch' => $model->context_epoch,
            'surface_key' => $model->surface_key,
            'cursor' => $cursor,
            'run' => $latestRun === null ? null : [
                'id' => $latestRun->id,
                'status' => $latestRun->status,
            ],
            'events' => $events,
            'messages' => $messages,
        ]);
    }

    public function stream(Request $request, string $conversation): StreamedResponse|JsonResponse
    {
        $model = $this->authorizedConversation($request, $conversation);
        if ($model === null) {
            return response()->json(['error' => 'denied'], 403);
        }

        $lastEventId = (int) ($request->headers->get('Last-Event-ID') ?? $request->query('after', 0));

        return response()->eventStream(function () use ($model, $lastEventId) {
            $after = $lastEventId;
            $started = time();
            $maxSeconds = 15;

            while (time() - $started < $maxSeconds) {
                $batch = NexiRunEvent::query()
                    ->where('conversation_id', $model->id)
                    ->where('seq', '>', $after)
                    ->orderBy('seq')
                    ->limit(50)
                    ->get();

                foreach ($batch as $event) {
                    $after = $event->seq;
                    yield new StreamedEvent(
                        event: $event->type,
                        data: json_encode([
                            'id' => $event->id,
                            'run_id' => $event->run_id,
                            'seq' => $event->seq,
                            'type' => $event->type,
                            'payload' => $event->payload,
                        ], JSON_THROW_ON_ERROR),
                        id: (string) $event->seq,
                    );
                }

                yield new StreamedEvent(event: 'heartbeat', data: '{}');

                if ($batch->isEmpty()) {
                    usleep(200_000);
                }
            }
        });
    }


    public function clear(Request $request, ContextEpochService $epochs): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'denied'], 403);
        }

        $actorType = $user->applicantAccount()->exists() && ! $user->staffProfile()->exists()
            ? 'applicant'
            : ($user->staffProfile()->exists() ? 'staff' : null);
        if ($actorType === null) {
            return response()->json(['error' => 'denied'], 403);
        }

        $surfaceKey = $actorType === 'applicant' ? 'applicant' : 'staff';
        $open = NexiConversation::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', (string) $user->getAuthIdentifier())
            ->where('surface_key', $surfaceKey)
            ->where('status', 'open')
            ->latest('created_at')
            ->first();

        if ($open === null) {
            return response()->json([
                'cleared' => false,
                'conversation_id' => null,
            ]);
        }

        $next = $epochs->segment($open, 'user_clear');

        return response()->json([
            'cleared' => true,
            'closed_conversation_id' => $open->id,
            'conversation_id' => $next->id,
            'context_epoch' => $next->context_epoch,
        ]);
    }

    private function authorizedConversation(Request $request, string $conversationId): ?NexiConversation
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $model = NexiConversation::query()->find($conversationId);
        if ($model === null) {
            return null;
        }

        if ($model->actor_id !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        if (! in_array($model->actor_type, ['staff', 'applicant'], true)) {
            return null;
        }

        return $model;
    }
}
