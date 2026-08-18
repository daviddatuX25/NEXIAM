<?php

namespace App\Http\Controllers\Release;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResultSheet\MarkResultSheetsPrintedRequest;
use App\Http\Requests\ResultSheet\RequestResultSheetPrintRequest;
use App\Models\Printing\PrintJob;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/**
 * Thin release-printing surface: request result-sheet print jobs (single +
 * bulk). Status and downloads ride the shared printing.jobs.show/download
 * routes; printed-state toggling lives on MarkResultSheetsPrinted.
 */
class ReleasePrintController extends Controller
{
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
