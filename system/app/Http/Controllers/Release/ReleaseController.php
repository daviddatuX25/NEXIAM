<?php

namespace App\Http\Controllers\Release;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Release\ReleaseResultRequest;
use App\Http\Requests\Release\UnreleaseResultRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Institution;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\ReleaseResult;
use App\Release\Actions\UnreleaseResult;
use App\ResultSheet\Queries\ReleaseOverviewQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Domain 7: Results Release, Printing & University Enrollment Clearance (Part 1: Results Release)
 *
 * Controls the formal publication and dissemination of candidate entrance examination results.
 *
 * Two-Stage Release Protocol:
 * 1. Verification Stage: Testing Officers audit raw OMR bubble scores and verify result sheet renders.
 * 2. Executive Release Stage: Authorizers sign off on the batch release, unlocking online viewing for applicants.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'release-batch-releasing' tour via Xiam NPC (staff-copilot persona).
 */
class ReleaseController extends Controller
{
    public function prepareDocument(Request $request, AdmissionAttempt $attempt, PrepareResultDocument $action): RedirectResponse|JsonResponse
    {
        try {
            $document = $action->handle($attempt, $request->user());
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'prepared',
                    'document_id' => $document->id,
                    'attempt_id' => $attempt->id,
                ]);
            }
        } catch (RuntimeException $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'prepare_failed',
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return back()->withErrors(['prepare' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Result document prepared.');
    }

    public function release(ReleaseResultRequest $request, AdmissionAttempt $attempt, ReleaseResult $action): RedirectResponse
    {
        $document = ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->latest('id')
            ->first();

        if ($document === null) {
            return back()->withErrors(['release' => 'No ready result document found for this admission attempt.'])->with('page_state', 'validation');
        }

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

    public function unrelease(UnreleaseResultRequest $request, ResultRelease $release, UnreleaseResult $action): RedirectResponse
    {
        try {
            $action->handle($release, $request->user(), $request->string('reason')->toString());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['unrelease' => $exception->getMessage()]);
        }

        return back()->with('status', 'Result unreleased.');
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer, ReleaseOverviewQuery $overviewQuery): Response
    {
        if (! $authorizer->allowsAny($request->user(), [CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE])) {
            return Inertia::render('Release/Index', [
                'page_state' => 'denied',
                'sessions' => [],
                'sort_options' => ReleaseOverviewQuery::sortOptions(),
                'filters' => ['search' => '', 'status' => 'unprinted', 'sort' => 'exam_date', 'dir' => 'desc'],
                'active_template' => null,
                'can_manage_templates' => false,
            ]);
        }

        $template = ResultSheetTemplate::active();

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => in_array($request->input('status'), ['unprinted', 'printed', 'all'], true) ? (string) $request->input('status') : 'unprinted',
            'sort' => (string) $request->input('sort', 'exam_date'),
            'dir' => strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $institutionId = (int) ($request->user()?->actorScope()?->institutionId ?? Institution::query()->value('id') ?? 1);
        $overview = $overviewQuery->forInertia($institutionId, 50, $filters);

        return Inertia::render('Release/Index', [
            'page_state' => 'ready',
            'sessions' => array_values($overview['sessions'] ?? $overview),
            'sort_options' => $overview['sort_options'] ?? ReleaseOverviewQuery::sortOptions(),
            'filters' => $overview['filters'] ?? $filters,
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
