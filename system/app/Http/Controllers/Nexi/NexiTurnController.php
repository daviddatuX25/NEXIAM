<?php

namespace App\Http\Controllers\Nexi;

use App\Ai\Conversation\TurnSubmitter;
use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NexiTurnController extends Controller
{
    public function store(
        Request $request,
        TurnSubmitter $submitter,
        SurfaceProfileResolver $surfaces,
        PageCapabilityPackRegistry $packs,
    ): JsonResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'page_pack_key' => ['nullable', 'string', 'max:128'],
            'page_pack_version' => ['nullable', 'integer', 'min:1'],
            'mode' => ['nullable', 'string', 'in:inquiry,assistant'],
        ]);

        $resolved = $surfaces->resolve($request);
        $profile = $resolved['profile'] ?? null;
        if ($profile === null || ! $profile->mountable) {
            return response()->json(['error' => 'surface_denied'], 403);
        }

        $mode = $validated['mode'] ?? 'inquiry';
        $pack = ! empty($validated['page_pack_key']) ? $packs->forKey($validated['page_pack_key']) : null;
        $modes = $pack?->allowedModes ?? $profile->allowedModes;
        if ($modes === []) {
            $modes = ['inquiry'];
        }

        if (! in_array($mode, $modes, true)) {
            $mode = $modes[0];
        }

        if ($mode === 'assistant') {
            $request->session()->put('nexi.active_mode', 'assistant');
        } elseif ($mode === 'inquiry') {
            $request->session()->put('nexi.active_mode', 'inquiry');
        }

        try {
            $result = $submitter->submit(
                actor: $request->user(),
                surfaceKey: $profile->key->value,
                inputText: $validated['message'],
                meta: [
                    'mode' => $mode,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                    'page_pack_key' => $validated['page_pack_key'] ?? null,
                    'page_pack_version' => $validated['page_pack_version'] ?? null,
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

            return response()->json([
                'error' => $code,
                'availability' => $code === 'quota_exhausted' ? 'quota_exhausted' : null,
            ], $status);
        }

        return response()->json($result, 202);
    }
}
