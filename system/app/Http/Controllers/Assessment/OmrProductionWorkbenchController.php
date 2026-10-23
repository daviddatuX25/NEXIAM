<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\Omr\AdoptOmrPendingCaptureSet;
use App\Assessment\Actions\Omr\AllocateOmrSupplierStockToBatch;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\ConfirmOmrPendingCaptureIdentity;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\Operational\OmrPendingCaptureTarget;
use App\Assessment\Omr\Operational\OmrWorkbenchCommand;
use App\Assessment\Omr\Operational\ProductionOmrAuthorityAdapter;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\AdoptOmrProductionCaptureSetRequest;
use App\Http\Requests\Assessment\CaptureStartOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\CompleteOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\OmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\ResolveApplicantOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\ResolveDuplicateOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\RetryOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\ReviewOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\ShowOmrProductionWorkbenchRequest;
use App\Http\Requests\Assessment\StartOmrProductionCaptureSetRequest;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ValueError;

use function array_key_exists;
use function str_contains;
use function strtolower;
use function trim;

final class OmrProductionWorkbenchController extends Controller
{
    public function show(
        ShowOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        abort_unless(
            $examSession->status === ExamSession::STATUS_COMPLETED &&
                (int) $batch->gradingSession?->exam_session_id === (int) $examSession->id,
            404,
        );

        $gradingSession = $contexts->resolveForRead(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless(
            $gradingSession !== null && (int) $batch->grading_session_id === (int) $gradingSession->id,
            404,
        );

        $filters = $request->validated();

        return response()->json(
            $authority->project(
                $this->workbenchId($gradingSession, $batch),
                (int) $actor->getAuthIdentifier(),
                isset($filters['capture_sheet_id']) ? (int) $filters['capture_sheet_id'] : null,
                isset($filters['capture_operation_id']) ? (string) $filters['capture_operation_id'] : null,
                isset($filters['capture_idempotency_key']) ? (string) $filters['capture_idempotency_key'] : null,
            )->toSafeArray(),
        );
    }

    public function startPendingCapture(
        StartOmrProductionCaptureSetRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        StartOmrPendingCaptureSet $startPendingCaptureSet,
        ProductionOmrAuthorityAdapter $authority,
        OmrPendingCaptureTarget $target,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->failureCode('omr_production_workbench_unauthenticated', 401);
        }
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        }

        $payload = $request->validated();

        try {
            if (! empty($payload['supplier_stock_allocation_id'])) {
                $allocation = OmrSupplierStockAllocation::query()->findOrFail($payload['supplier_stock_allocation_id']);
                $set = $startPendingCaptureSet->forSupplierStock(
                    $gradingSession,
                    $batch,
                    $allocation,
                    $actor,
                    (string) $payload['idempotency_key'],
                );
            } else {
                $issuance = OmrQrDisabledIssuance::query()->findOrFail($payload['issuance_id']);
                $set = $startPendingCaptureSet->forProduction(
                    $gradingSession,
                    $batch,
                    $issuance,
                    $actor,
                    (string) $payload['idempotency_key'],
                );
            }

            return response()->json($target->withStartedSet(
                $authority->project(
                    $this->workbenchId($gradingSession, $batch),
                    (int) $actor->getAuthIdentifier(),
                ),
                $set,
            )->toSafeArray());
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function adoptPendingCapture(
        AdoptOmrProductionCaptureSetRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        AdoptOmrPendingCaptureSet $adopt,
        ProductionOmrAuthorityAdapter $authority,
        OmrPendingCaptureTarget $target,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->failureCode('omr_production_workbench_unauthenticated', 401);
        }
        $gradingSession = $contexts->requireForWrite($examSession, $actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        }
        $payload = $request->validated();

        try {
            $set = OmrPendingCaptureSet::query()
                ->where('public_id', $payload['pending_capture_id'])
                ->where('production_batch_id', $batch->id)
                ->where('created_by_user_id', $actor->id)
                ->firstOrFail();
            $adopt->handle($set, $actor, (int) $payload['pending_capture_revision']);

            return response()->json($target->withStartedSet(
                $authority->project($this->workbenchId($gradingSession, $batch), (int) $actor->getAuthIdentifier()),
                $set->fresh(),
            )->toSafeArray());
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function confirmPendingCaptureIdentity(
        Request $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        ConfirmOmrPendingCaptureIdentity $confirmIdentity,
        ProductionOmrAuthorityAdapter $authority,
        OmrPendingCaptureTarget $target,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->failureCode('omr_production_workbench_unauthenticated', 401);
        }
        $gradingSession = $contexts->requireForWrite($examSession, $actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        }
        $payload = $request->validate([
            'pending_capture_id' => ['required', 'uuid'],
            'pending_capture_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.page_no' => ['required', 'integer', 'between:1,4'],
            'selections.*.applicant_number' => ['nullable', 'string', 'max:64'],
            'selections.*.admission_attempt_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $set = OmrPendingCaptureSet::query()
                ->where('public_id', $payload['pending_capture_id'])
                ->where('production_batch_id', $batch->id)
                ->where('created_by_user_id', $actor->id)
                ->firstOrFail();
            $updated = $confirmIdentity->handle(
                $set,
                $actor,
                (int) $payload['pending_capture_revision'],
                $payload['selections'],
                (string) $payload['idempotency_key'],
            );

            return response()->json($target->withStartedSet(
                $authority->project($this->workbenchId($gradingSession, $batch), (int) $actor->getAuthIdentifier()),
                $updated->fresh(),
            )->toSafeArray());
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function allocateSupplierStock(
        Request $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        AllocateOmrSupplierStockToBatch $allocate,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->failureCode('omr_production_workbench_unauthenticated', 401);
        }
        $gradingSession = $contexts->requireForWrite($examSession, $actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        }
        $payload = $request->validate([
            'lot_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        try {
            $lot = OmrSupplierStockLot::query()->findOrFail($payload['lot_id']);
            $allocation = $allocate->handle(
                $gradingSession,
                $batch,
                $lot,
                $actor,
                (int) $payload['quantity'],
                (string) $payload['idempotency_key'],
            );

            return response()->json([
                'id' => $allocation->id,
                'lot_id' => $allocation->lot_id,
                'quantity' => $allocation->quantity,
                'used_quantity' => $allocation->used_quantity,
                'remaining_claims' => $allocation->remainingQuantity(),
                'status' => $allocation->status,
            ], 201);
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function captureStart(
        CaptureStartOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
        OmrCameraFrameFinalization $cameraFrames,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);
        $workbenchId = $this->workbenchId($gradingSession, $batch);
        $serverPayload = [];
        if (is_string($request->input('staged_frame_id'))) {
            abort_unless(Schema::hasTable('omr_camera_commands'), 503);
            $existingOperation = $cameraFrames->findOperationForReplay(
                $actor,
                $workbenchId,
                (string) $request->input('operation_id'),
                (string) $request->input('idempotency_key'),
            );
            if ($existingOperation?->domain_outcome === 'accepted') {
                $serverPayload['_camera_controller_authenticated'] = true;
            } else {
                abort_unless($request->header('X-Omr-Camera-Role') === 'controller', 403);
                $token = $request->header('X-Omr-Camera-Token');
                $generation = $request->header('X-Omr-Camera-Generation');
                abort_unless(is_string($token) && is_string($generation) && ctype_digit($generation), 403);
                $pairing = $cameraFrames->assertControllerCredential((string) $request->input('pairing_id'), $actor, $token, (int) $generation);
                abort_unless(hash_equals((string) $pairing->workbench_key, hash('sha256', $workbenchId)), 404);
                $serverPayload['_camera_controller_authenticated'] = true;
            }
        }

        return $this->execute(
            $request,
            $gradingSession,
            $batch,
            $authority,
            OmrWorkbenchCommand::CaptureStart,
            serverPayload: $serverPayload,
        );
    }

    public function captureRetry(
        RetryOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        int $sheet,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        return $this->execute(
            $request,
            $gradingSession,
            $batch,
            $authority,
            OmrWorkbenchCommand::CaptureRetry,
            $sheet,
        );
    }

    public function reviewSave(
        ReviewOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        int $sheet,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        return $this->execute(
            $request,
            $gradingSession,
            $batch,
            $authority,
            OmrWorkbenchCommand::ReviewSave,
            $sheet,
        );
    }

    public function resolveApplicant(
        ResolveApplicantOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        int $sheet,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        return $this->execute(
            $request,
            $gradingSession,
            $batch,
            $authority,
            OmrWorkbenchCommand::ResolveApplicant,
            $sheet,
        );
    }

    public function resolveDuplicate(
        ResolveDuplicateOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        int $sheet,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        return $this->execute(
            $request,
            $gradingSession,
            $batch,
            $authority,
            OmrWorkbenchCommand::ResolveDuplicate,
            $sheet,
        );
    }

    public function productionComplete(
        CompleteOmrProductionWorkbenchRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $gradingSession = $contexts->requireForWrite(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        return $this->execute(
            $request,
            $gradingSession,
            $batch,
            $authority,
            OmrWorkbenchCommand::ProductionComplete,
        );
    }

    private function execute(
        OmrProductionWorkbenchRequest $request,
        GradingSession $gradingSession,
        OmrCaptureBatch $batch,
        ProductionOmrAuthorityAdapter $authority,
        OmrWorkbenchCommand $command,
        ?int $sheetId = null,
        array $serverPayload = [],
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->failureCode('omr_production_workbench_unauthenticated', 401);
        }

        if ((int) $batch->grading_session_id !== (int) $gradingSession->getKey()) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        }

        try {
            $sheet = $sheetId === null
                ? null
                : OmrCaptureSheet::query()->whereKey($sheetId)->firstOrFail();

            if ($sheet !== null && (int) $sheet->batch_id !== (int) $batch->getKey()) {
                return $this->failureCode('omr_production_workbench_resource_not_found', 404);
            }

            $payload = [...$request->validated(), ...$serverPayload];
            $resourceError = $this->attachResourceIdentity($payload, $sheet, $command);
            if ($resourceError !== null) {
                return $resourceError;
            }

            if ($command === OmrWorkbenchCommand::CaptureStart) {
                $this->normalizeCapturePayload($payload, $request);
            }

            return response()->json(
                $authority->execute(
                    $this->workbenchId($gradingSession, $batch),
                    $command,
                    $payload,
                    (int) $actor->getAuthIdentifier(),
                )->toSafeArray(),
            );
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_production_workbench_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attachResourceIdentity(
        array &$payload,
        ?OmrCaptureSheet $sheet,
        OmrWorkbenchCommand $command,
    ): ?JsonResponse {
        if ($sheet === null) {
            return null;
        }

        foreach (['sheet_id', 'survivor_sheet_id'] as $field) {
            if (array_key_exists($field, $payload)
                && (int) $payload[$field] !== (int) $sheet->getKey()) {
                return $this->failureCode('omr_production_workbench_route_mismatch', 422);
            }
        }

        if ($command === OmrWorkbenchCommand::ResolveDuplicate) {
            $payload['survivor_sheet_id'] = (int) $sheet->getKey();
        } else {
            $payload['sheet_id'] = (int) $sheet->getKey();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeCapturePayload(
        array &$payload,
        OmrProductionWorkbenchRequest $request,
    ): void {
        $image = $payload['image'] ?? null;
        if ($image instanceof UploadedFile) {
            $payload['image_bytes'] = (string) $image->get();
            $payload['mime'] = (string) ($image->getMimeType() ?: $image->getClientMimeType());
            unset($payload['image']);
        }

        foreach (['page_no', 'pending_capture_revision'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = (int) $payload[$field];
            }
        }

        if (array_key_exists('manual_confirmed', $payload)) {
            $payload['manual_confirmed'] = $request->boolean('manual_confirmed');
        }
    }

    private function workbenchId(GradingSession $gradingSession, OmrCaptureBatch $batch): string
    {
        return sprintf(
            'production:%d:%d',
            (int) $gradingSession->getKey(),
            (int) $batch->getKey(),
        );
    }

    private function failure(Throwable $exception): JsonResponse
    {
        $message = strtolower(trim($exception->getMessage()));
        $status = $this->statusFor($message);
        $code = str_starts_with($message, 'omr_capture_set_')
            || in_array($message, [
                'qr_disabled_stock_exhausted', 'qr_disabled_stock_invalid',
                'qr_disabled_issuance_invalid', 'qr_disabled_claim_invalid',
                'supplier_stock_exhausted', 'supplier_stock_invalid',
                'supplier_stock_insufficient', 'supplier_stock_scope_mismatch',
                'supplier_stock_material_mismatch', 'supplier_stock_batch_closed',
                'qr_disabled_claim_missing', 'qr_disabled_claim_stale',
                'qr_disabled_claim_mixed', 'qr_disabled_provenance_invalid',
                'analysis_evidence_invalid', 'applicant_duplicate_prior_batch',
                'applicant_unresolved', 'applicant_conflict', 'applicant_missing',
                'submission_missing', 'seat_missing', 'seat_snapshot_missing',
                'key_missing', 'observed_set_mismatch',
            ], true)
            ? $message
            : match ($status) {
                404 => 'omr_production_workbench_not_found',
                403 => 'omr_production_workbench_denied',
                409 => str_contains($message, 'conflict')
                    ? 'omr_production_workbench_conflict'
                    : 'omr_production_workbench_stale',
                default => 'omr_production_workbench_invalid',
            };

        return $this->failureCode($code, $status);
    }

    private function failureCode(string $code, int $status): JsonResponse
    {
        return response()->json([
            'outcome' => 'failed',
            'code' => $code,
        ], $status);
    }

    private function statusFor(string $message): int
    {
        if (str_contains($message, 'not found')
            || str_contains($message, 'does not belong')
            || str_contains($message, 'not part of')) {
            return 404;
        }

        if (str_contains($message, 'authorized')
            || str_contains($message, 'denied')
            || str_contains($message, 'forbidden')
            || str_contains($message, 'capability')
            || str_contains($message, 'not available')) {
            return 403;
        }

        if (str_contains($message, 'stale')
            || str_contains($message, 'changed')
            || str_contains($message, 'concurrent')
            || str_contains($message, 'conflict')
            || str_contains($message, 'version')) {
            return 409;
        }

        return 422;
    }
}
