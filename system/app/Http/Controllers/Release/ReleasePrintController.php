<?php

namespace App\Http\Controllers\Release;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResultSheet\MarkResultSheetsPrintedRequest;
use App\Http\Requests\ResultSheet\RequestResultSheetPrintRequest;
use App\Models\Assessment\GradingSession;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin release-printing surface: batch page (per grading session), single +
 * bulk job requests, manual printed toggle. Status and downloads ride the
 * shared printing.jobs.show/download routes.
 */
class ReleasePrintController extends Controller
{
    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        GradingSession $gradingSession,
        ResultSheetDataProvider $dataProvider,
    ): Response {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_RELEASE), 403);

        $template = ResultSheetTemplate::active();

        $rows = [];

        foreach ($gradingSession->scoreRecords()->with('admissionAttempt.identity')->get()->groupBy('admission_attempt_id') as $attemptId => $records) {
            $release = ResultRelease::query()
                ->where('admission_attempt_id', (int) $attemptId)
                ->where('is_current', true)
                ->whereNull('unreleased_at')
                ->latest('id')
                ->first();

            $attempt = $records->first()->admissionAttempt;
            $data = $dataProvider->forAttempt($attempt);

            $rows[] = [
                'attempt_id' => $attempt->id,
                'release_id' => $release?->id,
                'name' => $data['name'],
                'reference' => $data['reference'],
                'released' => $release !== null,
                'printed' => $release?->result_printed_at !== null,
                'mode' => $release?->mode,
            ];
        }

        return Inertia::render('Release/PrintBatch', [
            'page_state' => 'ready',
            'session' => [
                'id' => $gradingSession->id,
                'code' => (string) $gradingSession->examSession?->code,
                'exam_date' => $gradingSession->examSession?->starts_at?->format('F d, Y'),
                'room_name' => (string) ($gradingSession->examSession?->room?->name ?? '—'),
            ],
            'applicants' => array_values($rows),
            'template_present' => $template !== null,
            'job_poll_route' => route('printing.jobs.show', ['printJob' => 'JOB_ID_PLACEHOLDER']),
        ]);
    }

    public function bulkPreview(
        Request $request,
        CapabilityAuthorizer $authorizer,
        GradingSession $gradingSession,
        ResultSheetDataProvider $dataProvider,
        ResultSheetPlaceholderService $placeholders,
        ResultSheetCssService $css,
    ): Response {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_RELEASE), 403);

        $template = ResultSheetTemplate::active();

        if ($template === null) {
            return Inertia::render('Release/ResultSheetBulk', [
                'page_state' => 'error',
                'session' => ['id' => $gradingSession->id],
                'sheetsHtml' => [],
                'paper_size' => 'a4',
                'template_error' => 'No active result sheet template.',
            ]);
        }

        $releaseIds = array_values(array_unique(array_map('intval', explode(',', (string) $request->query('ids', '')))));

        $releases = ResultRelease::query()
            ->whereIn('id', $releaseIds)
            ->where('is_current', true)
            ->with('admissionAttempt.identity')
            ->get();

        $sheets = [];

        foreach ($releases as $release) {
            $data = $dataProvider->forAttempt($release->admissionAttempt);
            $replacements = $placeholders->buildReplacements([$data]);
            $raw = $placeholders->renderRaw((string) $template->content, $replacements);
            $sheets[] = $css->wrap($raw);
        }

        return Inertia::render('Release/ResultSheetBulk', [
            'page_state' => 'ready',
            'session' => ['id' => $gradingSession->id],
            'sheetsHtml' => $sheets,
            'paper_size' => $template->paper_size,
            'template_error' => null,
        ]);
    }

    public function request(
        RequestResultSheetPrintRequest $request,
        CapabilityAuthorizer $authorizer,
        RequestResultSheetPrint $action,
    ): JsonResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_RELEASE), 403);

        $job = $action->handle(
            $request->user(),
            array_map('intval', $request->validated('release_ids')),
            (int) $request->integer('copies', 1),
        );

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'artifact_type' => $job->artifact_type,
            'progress_total' => $job->progress_total,
            'progress_done' => $job->progress_done,
        ], 202);
    }

    public function markPrinted(
        MarkResultSheetsPrintedRequest $request,
        CapabilityAuthorizer $authorizer,
        MarkResultSheetsPrinted $action,
    ): RedirectResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_RELEASE), 403);

        $count = $action->handle(
            $request->user(),
            array_map('intval', $request->validated('release_ids')),
            (bool) $request->boolean('printed'),
        );

        return back()->with('status', $request->boolean('printed')
            ? "Marked {$count} result sheets as printed."
            : "Cleared printed status on {$count} result sheets.");
    }
}
