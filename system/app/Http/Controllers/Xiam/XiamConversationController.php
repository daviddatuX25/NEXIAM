<?php

namespace App\Http\Controllers\Xiam;

use App\Ai\Conversation\ContextEpochService;
use App\Ai\Conversation\XiamConversationSnapshotQuery;
use App\Ai\Streaming\VercelUiMessageStreamProjector;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Http\Controllers\Controller;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamTaskGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XiamConversationController extends Controller
{
    public function snapshot(
        Request $request,
        string $conversation,
        XiamConversationSnapshotQuery $snapshot,
    ): JsonResponse {
        $after = max(0, (int) $request->query('after', 0));
        $payload = $snapshot->handle($request->user(), $conversation, $after);
        if ($payload === null) {
            return response()->json(['error' => 'denied'], 403);
        }

        return response()->json($payload);
    }

    public function stream(
        Request $request,
        string $conversation,
        XiamConversationSnapshotQuery $snapshot,
    ): StreamedResponse|JsonResponse {
        $model = $snapshot->authorizedConversation($request->user(), $conversation);
        if ($model === null) {
            return response()->json(['error' => 'denied'], 403);
        }

        $lastEventId = (int) ($request->headers->get('Last-Event-ID') ?? $request->query('after', 0));
        $projector = new VercelUiMessageStreamProjector;

        $actor = $request->user();

        return response()->stream(function () use ($model, $lastEventId, $projector, $actor): void {
            $events = (function () use ($model, $lastEventId): \Generator {
                $after = $lastEventId;
                $started = time();
                $maxSeconds = max(30, (int) config('xiam.execution_budget.run_deadline_seconds', 90));

                while (time() - $started < $maxSeconds) {
                    $batch = XiamRunEvent::query()
                        ->where('conversation_id', $model->id)
                        ->where('seq', '>', $after)
                        ->orderBy('seq')
                        ->limit(50)
                        ->get();

                    foreach ($batch as $event) {
                        $after = $event->seq;
                        yield $event;

                        if (in_array($event->type, ['run.completed', 'run.failed', 'run.cancelled'], true)) {
                            return;
                        }
                    }

                    if ($batch->isEmpty()) {
                        $latestRun = $model->runs()->latest('created_at')->first();
                        if ($latestRun !== null && $latestRun->isTerminal() && $after >= (int) XiamRunEvent::query()->where('conversation_id', $model->id)->max('seq')) {
                            return;
                        }

                        usleep(200_000);
                    }
                }
            })();

            foreach ($projector->project($events, $actor, (string) $model->actor_type) as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, $projector->headers());
    }

    public function clear(
        Request $request,
        ContextEpochService $epochs,
        SurfaceProfileResolver $surfaces,
    ): JsonResponse {
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

        $profile = $surfaces->resolve($request)['profile'] ?? null;
        $surfaceKey = $profile?->key->value
            ?? ($actorType === 'applicant' ? 'applicant' : 'staff');
        $open = XiamConversation::query()
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

        $next = DB::transaction(function () use ($open, $epochs): XiamConversation {
            XiamProposal::query()
                ->where('conversation_id', $open->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'invalidated',
                    'failure_reason' => 'user_clear',
                ]);

            XiamTaskGrant::query()
                ->where('actor_type', $open->actor_type)
                ->where('actor_id', $open->actor_id)
                ->where('context_epoch', $open->context_epoch)
                ->where('status', XiamTaskGrant::STATUS_ACTIVE)
                ->update([
                    'status' => XiamTaskGrant::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoke_reason' => 'user_clear',
                ]);

            return $epochs->segment($open, 'user_clear');
        });

        return response()->json([
            'cleared' => true,
            'closed_conversation_id' => $open->id,
            'conversation_id' => $next->id,
            'context_epoch' => $next->context_epoch,
        ]);
    }
}
