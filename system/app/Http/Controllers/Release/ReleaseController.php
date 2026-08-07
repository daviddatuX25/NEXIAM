<?php

namespace App\Http\Controllers\Release;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Release\ConsultationDecisionRequest;
use App\Http\Requests\Release\ReleaseResultRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use App\Release\Actions\UnreleaseResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReleaseController extends Controller
{
    public function consultation(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            return Inertia::render('Release/Consultation', ['page_state' => 'denied', 'attempts' => []]);
        }

        $attempts = AdmissionAttempt::query()->where('status', AdmissionAttempt::STATUS_ACCEPTED)->limit(30)->get(['id', 'reference_code']);

        return Inertia::render('Release/Consultation', [
            'page_state' => $attempts->isEmpty() ? 'empty' : 'ready',
            'attempts' => $attempts,
        ]);
    }

    public function recordDecision(
        ConsultationDecisionRequest $request,
        AdmissionAttempt $attempt,
        RecordConsultationDecision $action,
    ): RedirectResponse {
        $action->handle(
            $attempt,
            $request->user(),
            $request->string('decision_type')->toString(),
            $request->input('notes'),
            $request->string('idempotency_key')->toString(),
        );

        return back()->with('status', 'Consultation decision recorded.');
    }

    public function prepareDocument(Request $request, AdmissionAttempt $attempt, PrepareResultDocument $action): RedirectResponse
    {
        $action->handle($attempt, $request->user());

        return back()->with('status', 'Result document prepared.');
    }

    public function release(ReleaseResultRequest $request, AdmissionAttempt $attempt, ReleaseResult $action): RedirectResponse
    {
        $document = ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->latest()
            ->firstOrFail();

        try {
            $action->handle($attempt, $document, $request->user(), $request->string('idempotency_key')->toString());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['release' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Release accepted; notification queued.');
    }

    public function unrelease(Request $request, \App\Models\Release\ResultRelease $release, UnreleaseResult $action): RedirectResponse
    {
        $action->handle($release, $request->user(), $request->string('reason')->toString());

        return back()->with('status', 'Result unreleased.');
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_RELEASE)) {
            return Inertia::render('Release/Index', ['page_state' => 'denied']);
        }

        return Inertia::render('Release/Index', ['page_state' => 'ready']);
    }
}
