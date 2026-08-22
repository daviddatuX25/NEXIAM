<?php

namespace App\Http\Controllers\Release;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Release\ConsultationDecisionRequest;
use App\Http\Requests\Release\ReleaseResultRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use App\Release\Actions\UnreleaseResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReleaseController extends Controller
{
    public function consultation(Request $request, CapabilityAuthorizer $authorizer, \App\Consultation\Queries\ConsultationDashboardQuery $dashboard): Response|\Illuminate\Http\RedirectResponse
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            return Inertia::render('Release/Consultation', ['page_state' => 'denied', 'dashboard' => null]);
        }

        $cycle = $request->user()->actorScope()->activeCycleCode;
        $query = trim((string) $request->string('q'));

        $searchResults = [];
        if ($query !== '') {
            $matches = AdmissionAttempt::query()
                ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
                ->where(function ($inner) use ($query) {
                    $inner->where('reference_code', 'ilike', "%{$query}%")
                        ->orWhereHas('identity', fn ($id) => $id
                            ->where('first_name', 'ilike', "%{$query}%")
                            ->orWhere('last_name', 'ilike', "%{$query}%"));
                })
                ->limit(20)
                ->with('identity')
                ->get();

            if ($matches->count() === 1) {
                return redirect()->route('admission-support.show', $matches->first());
            }

            $searchResults = $matches->map(fn (AdmissionAttempt $a) => [
                'attempt_id' => $a->id,
                'reference_code' => $a->reference_code,
                'name' => $a->identity ? trim($a->identity->first_name.' '.$a->identity->last_name) : $a->reference_code,
            ])->values()->all();
        }

        return Inertia::render('Release/Consultation', [
            'page_state' => 'ready',
            'dashboard' => $dashboard->for($cycle),
            'search_query' => $query,
            'search_results' => $searchResults,
            'can_plan' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN),
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
            $action->handle(
                $attempt,
                $document,
                $request->user(),
                $request->string('idempotency_key')->toString(),
                (string) ($request->validated()['mode'] ?? ReleaseResult::MODE_F2F),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['release' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Release accepted; notification queued.');
    }

    public function unrelease(Request $request, ResultRelease $release, UnreleaseResult $action): RedirectResponse
    {
        $action->handle($release, $request->user(), $request->string('reason')->toString());

        return back()->with('status', 'Result unreleased.');
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_RELEASE)) {
            return Inertia::render('Release/Index', ['page_state' => 'denied', 'sessions' => [], 'active_template' => null]);
        }

        $template = \App\Models\ResultSheet\ResultSheetTemplate::active();

        $sessions = \App\Models\Assessment\GradingSession::query()
            ->with(['examSession.room'])
            ->latest('id')
            ->get()
            ->map(function (\App\Models\Assessment\GradingSession $gs) {
                $attemptIds = $gs->scoreRecords()->select('admission_attempt_id')->distinct()->pluck('admission_attempt_id');
                $total = $attemptIds->count();
                $released = \App\Models\Release\ResultRelease::query()
                    ->whereIn('admission_attempt_id', $attemptIds)
                    ->where('is_current', true)
                    ->whereNull('unreleased_at')
                    ->count();

                return [
                    'id' => $gs->id,
                    'exam_code' => (string) ($gs->examSession?->code ?? "Session #{$gs->id}"),
                    'room_name' => (string) ($gs->examSession?->room?->name ?? '—'),
                    'starts_at' => $gs->examSession?->starts_at?->format('M d, Y h:i A') ?? '—',
                    'status' => $gs->status,
                    'examinees_count' => $total,
                    'released_count' => $released,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Release/Index', [
            'page_state' => 'ready',
            'sessions' => $sessions,
            'active_template' => $template ? [
                'id' => $template->id,
                'name' => $template->name,
                'mode' => $template->mode,
                'paper_size' => $template->paper_size,
                'orientation' => $template->orientation,
            ] : null,
            'can_manage_templates' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
        ]);
    }
}
