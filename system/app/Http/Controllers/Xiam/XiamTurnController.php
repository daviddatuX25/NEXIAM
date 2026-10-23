<?php

namespace App\Http\Controllers\Xiam;

use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Conversation\TurnSubmitter;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Policy\XiamModePreference;
use App\Ai\Surface\SurfaceProfileKey;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class XiamTurnController extends Controller
{
    public function store(
        Request $request,
        TurnSubmitter $submitter,
        SurfaceProfileResolver $surfaces,
        PageCapabilityPackRegistry $packs,
        XiamModePreference $modePreference,
        UsageBudgetService $budgets,
    ): JsonResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'page_pack_key' => ['nullable', 'string', 'max:128'],
            'page_pack_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $resolved = $surfaces->resolve($request);
        $profile = $resolved['profile'] ?? null;
        if ($profile === null || ! $profile->mountable) {
            return response()->json(['error' => 'surface_denied'], 403);
        }

        $pack = ! empty($validated['page_pack_key']) ? $packs->forKey($validated['page_pack_key']) : null;
        if ($pack !== null && $pack->surface !== $profile->key) {
            $pack = null;
        }
        $actorType = match ($profile->key) {
            SurfaceProfileKey::Applicant => 'applicant',
            SurfaceProfileKey::Staff => 'staff',
            default => null,
        };
        if ($actorType === null) {
            return response()->json(['error' => 'surface_denied'], 403);
        }
        $pagePackKey = $pack?->key;
        $pagePackVersion = $pack?->version;
        if (! (bool) config('xiam.enabled', true)
            || in_array((string) config('xiam.mode', 'degraded'), ['disabled', 'suspended'], true)) {
            return response()->json(['error' => 'mode_unavailable'], 403);
        }

        try {
            $modeView = $modePreference->view($request);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'surface_denied' ? 403 : 503;

            return response()->json(['error' => $exception->getMessage()], $status);
        }

        $browserSessionId = $request->session()->getId();

        try {
            $result = $submitter->submit(
                actor: $request->user(),
                surfaceKey: $profile->key->value,
                inputText: $validated['message'],
                meta: [
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                    'browser_session_id' => $browserSessionId,
                    'page_pack_key' => $pagePackKey,
                    'page_pack_version' => $pagePackVersion,
                    'mode' => $modeView['active_mode'],
                    'route_name' => $pagePackKey,
                    'surface_profile_id' => $profile->key->value,
                ],
            );
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'quota_exhausted' => 429,
                'emergency_stop', 'surface_denied' => 403,
                'empty_input' => 422,
                default => 422,
            };

            $error = ['error' => $code];
            if ($code === 'quota_exhausted') {
                $error['availability'] = 'quota_exhausted';
                $error['usage'] = $budgets->view($actorType, (string) $request->user()->getAuthIdentifier());
            }

            return response()->json($error, $status);
        }

        $result['usage'] = $budgets->view($actorType, (string) $request->user()->getAuthIdentifier());

        return response()->json($result, 202);
    }
}
