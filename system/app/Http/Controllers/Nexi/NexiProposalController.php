<?php

namespace App\Http\Controllers\Nexi;

use App\Ai\Proposals\ProposalService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NexiProposalController extends Controller
{
    public function confirm(Request $request, string $proposal, ProposalService $proposals): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        try {
            $result = $proposals->confirm($actor, $proposal, $validated['idempotency_key']);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'proposal_not_found' => 404,
                'proposal_actor_mismatch' => 403,
                'proposal_not_confirmable', 'proposal_expired', 'stale_session_version',
                'only_draft_may_publish', 'assign_required_before_publish',
                'attempt_not_exam_eligible', 'target_not_found', 'session_not_found' => 422,
                default => 422,
            };

            return response()->json(['error' => $code], $status);
        }

        return response()->json([
            'ok' => true,
            'proposal' => [
                'id' => $result['proposal']->id,
                'status' => $result['proposal']->status,
                'capability_id' => $result['proposal']->capability_id,
            ],
            'result' => $result['result'],
        ]);
    }

    public function cancel(Request $request, string $proposal, ProposalService $proposals): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        try {
            $cancelled = $proposals->cancel($actor, $proposal);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'proposal_not_found' => 404,
                'proposal_actor_mismatch' => 403,
                default => 422,
            };

            return response()->json(['error' => $code], $status);
        }

        return response()->json([
            'ok' => true,
            'proposal' => [
                'id' => $cancelled->id,
                'status' => $cancelled->status,
            ],
        ]);
    }
}
