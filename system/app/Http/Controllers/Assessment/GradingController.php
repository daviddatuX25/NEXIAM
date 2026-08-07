<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordManualScore;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\RecordScoreRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GradingController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_MANUAL)) {
            return Inertia::render('Assessment/Grading', ['page_state' => 'denied', 'sessions' => []]);
        }

        $sessions = GradingSession::query()->with('examSession:id,code')->latest()->limit(20)->get();

        $pageState = config('nexi.mode') === 'degraded' ? 'degraded' : ($sessions->isEmpty() ? 'empty' : 'ready');

        return Inertia::render('Assessment/Grading', [
            'page_state' => $pageState,
            'sessions' => $sessions,
            'degraded' => config('nexi.mode') === 'degraded',
        ]);
    }

    public function recordScore(RecordScoreRequest $request, GradingSession $gradingSession, RecordManualScore $action): RedirectResponse
    {
        try {
            $action->handle(
                $gradingSession,
                AdmissionAttempt::query()->findOrFail($request->integer('admission_attempt_id')),
                AptitudeArea::query()->findOrFail($request->integer('aptitude_area_id')),
                $request->user(),
                $request->string('score')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['score' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Score saved.');
    }

    public function finalize(Request $request, GradingSession $gradingSession, FinalizeGradingSession $action): RedirectResponse
    {
        try {
            $action->handle($gradingSession, $request->user(), (int) $gradingSession->version);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['finalize' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Grading finalized.');
    }
}
