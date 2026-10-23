<?php

namespace App\Http\Controllers\Xiam;

use App\Ai\Conversation\TurnSubmitter;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\EmergencyStop;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XiamRunController extends Controller
{
    public function cancel(Request $request, string $run, TurnSubmitter $submitter): JsonResponse
    {
        $ok = $submitter->cancelRun($run, $request->user());

        return response()->json(['ok' => $ok], $ok ? 200 : 404);
    }

    public function emergencyStop(
        Request $request,
        EmergencyStop $stop,
        TaskGrantService $grants,
    ): JsonResponse {
        // Operator/test seam: enable via config write is env-only; this endpoint revokes grants when stop already active
        // or when explicitly requested in local/testing.
        if (! app()->environment(['local', 'testing'])) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        config(['xiam.emergency_stop' => true]);
        $revoked = $grants->revokeAllActive('emergency_stop');

        return response()->json([
            'emergency_stop' => $stop->active(),
            'grants_revoked' => $revoked,
        ]);
    }
}
