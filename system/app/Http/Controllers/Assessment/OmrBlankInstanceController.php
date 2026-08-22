<?php

namespace App\Http\Controllers\Assessment;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Actions\Omr\Canonical\RegisterOmrBlankInstanceCapture;
use App\Assessment\Actions\Omr\Canonical\ResolveOmrBlankInstanceManually;
use App\Http\Controllers\Controller;
use App\Http\Requests\Omr\Canonical\RegisterOmrBlankInstanceCaptureRequest;
use App\Http\Requests\Omr\Canonical\ResolveOmrBlankInstanceManualRequest;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OmrBlankInstanceController extends Controller
{
    public function register(
        RegisterOmrBlankInstanceCaptureRequest $request,
        RegisterOmrBlankInstanceCapture $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.blank_instance.capture_register', (string) $payload['capture_page_id'], $payload, (string) $payload['idempotency_key']);
        $templateVersion = OmrAnswerSheetTemplateVersion::query()->findOrFail($payload['template_version_id']);
        $capturePage = OmrCaptureSheetPage::query()->findOrFail($payload['capture_page_id']);
        $instance = $operation->wasRecentlyCreated
            ? $action->handle($actor, $payload['qr_payload'] ?? null, $templateVersion, (int) $payload['page_no'], $capturePage)
            : null;

        return response()->json([
            'status' => $instance?->status ?? 'replayed',
            'instance_id' => $instance?->id,
            'operation_id' => $operation->id,
        ]);
    }

    public function manualResolve(
        ResolveOmrBlankInstanceManualRequest $request,
        ResolveOmrBlankInstanceManually $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.blank_instance.manual_resolve', (string) $payload['display_code'], $payload, (string) $payload['idempotency_key']);
        $result = $operation->wasRecentlyCreated
            ? $action->handle($actor, $payload['qr_state'], $payload['display_code'], (bool) $payload['confirmed'])
            : ['instance' => null, 'recognition_blocked' => true];

        return response()->json([
            'status' => 'resolved_pending_confirmation',
            'instance_id' => $result['instance']?->id,
            'recognition_blocked' => $result['recognition_blocked'],
            'operation_id' => $operation->id,
        ]);
    }
}
