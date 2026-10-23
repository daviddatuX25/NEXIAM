<?php

namespace App\Http\Controllers\Xiam;

use App\Ai\Policy\XiamModePreference;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class XiamModeController extends Controller
{
    public function show(Request $request, XiamModePreference $preference): JsonResponse
    {
        try {
            return $this->noStore(response()->json($preference->view($request)));
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'surface_denied' ? 403 : 503;

            return $this->noStore(response()->json(['error' => $exception->getMessage()], $status));
        }
    }

    public function update(Request $request, XiamModePreference $preference): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mode' => ['required', 'string', 'in:inquiry,assistant'],
                'context_epoch' => ['sometimes', 'nullable', 'string', 'uuid'],
            ]);
        } catch (ValidationException $exception) {
            return $this->noStore(response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422));

        }

        try {
            return $this->noStore(response()->json($preference->change(
                request: $request,
                mode: $validated['mode'],
                expectedEpoch: $validated['context_epoch'] ?? null,
                hasExpectedEpoch: array_key_exists('context_epoch', $validated),
            )));
        } catch (RuntimeException $exception) {
            $status = match ($exception->getMessage()) {
                'mode_change_busy', 'context_epoch_conflict' => 409,
                'mode_unavailable', 'surface_denied' => 403,
                'mode_session_unavailable' => 503,
                default => 503,
            };

            return $this->noStore(response()->json(['error' => $exception->getMessage()], $status));
        }
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store');
    }
}
