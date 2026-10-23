<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\Omr\Camera\ExecuteOmrWorkbenchCaptureAction;
use App\Assessment\Omr\Operational\DiagnosticOmrAuthorityAdapter;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Assessment\Omr\Operational\ProductionOmrAuthorityAdapter;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\OmrWorkbenchCaptureActionRequest;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class OmrWorkbenchCaptureActionController extends Controller
{
    public function production(
        OmrWorkbenchCaptureActionRequest $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
        ExecuteOmrWorkbenchCaptureAction $action,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        abort_unless($examSession->status === ExamSession::STATUS_COMPLETED
            && (int) $batch->gradingSession?->exam_session_id === (int) $examSession->id, 404);

        $gradingSession = $contexts->resolveForRead($examSession, $actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        abort_unless($gradingSession !== null && (int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        $workbenchId = sprintf('production:%d:%d', (int) $gradingSession->getKey(), (int) $batch->getKey());
        $projection = $authority->project($workbenchId, (int) $actor->getAuthIdentifier());
        try {
            $action->handle(
                $actor,
                $workbenchId,
                ['production_batch_id' => (int) $batch->getKey()],
                $projection,
                $request->validated(),
                array_fill_keys(['next_page', 'next_set', 'redo_last'], route('scoring.sessions.omr.batches.workbench.capture_actions', [$examSession, $batch], false)),
            );
        } catch (RuntimeException $exception) {
            return $this->failure($exception);
        }

        return response()->json($authority->project($workbenchId, (int) $actor->getAuthIdentifier())->toSafeArray());
    }

    public function diagnostic(
        OmrWorkbenchCaptureActionRequest $request,
        string $run,
        DiagnosticOmrAuthorityAdapter $authority,
        OmrDiagnosticRunStatusSnapshot $snapshot,
        ExecuteOmrWorkbenchCaptureAction $action,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $runModel = OmrDiagnosticRun::query()->where('public_id', $run)->firstOrFail();
        $snapshot->assertCanProject($runModel, $actor);
        $workbenchId = 'diagnostic:'.$run;
        $projection = $authority->project($workbenchId, (int) $actor->getAuthIdentifier());
        try {
            $action->handle(
                $actor,
                $workbenchId,
                ['diagnostic_run_id' => (int) $runModel->getKey()],
                $projection,
                $request->validated(),
                array_fill_keys(['next_page', 'next_set', 'redo_last', 'capture_retake'], route('setup.exam-materials.omr-test.runs.capture-actions', ['run' => $run], false)),
            );
        } catch (RuntimeException $exception) {
            return $this->failure($exception);
        }

        return response()->json($authority->project($workbenchId, (int) $actor->getAuthIdentifier())->toSafeArray());
    }

    private function failure(RuntimeException $exception): JsonResponse
    {
        $code = $exception->getMessage();
        $status = match ($code) {
            'omr_camera_accepted_capture_missing' => 404,
            'omr_camera_capture_action_transition_unavailable' => 409,
            'omr_camera_capture_replacement_reason_required' => 422,
            default => 409,
        };

        return response()->json(['code' => $code], $status);
    }
}
