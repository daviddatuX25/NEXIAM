<?php

namespace App\Http\Controllers;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Printing\PrintJob;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Print job status + authorized downloads. Downloads re-authorize by artifact
 * type and stream with private, no-store.
 */
class PrintingController extends Controller
{
    public function show(Request $request, PrintJob $printJob): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'id' => $printJob->id,
            'artifact_type' => $printJob->artifact_type,
            'status' => $printJob->status,
            'progress_total' => $printJob->progress_total,
            'progress_done' => $printJob->progress_done,
            'safe_error' => $printJob->safe_error,
            'ready' => $printJob->isReady(),
        ]);
    }

    public function download(
        Request $request,
        PrintJob $printJob,
        CapabilityAuthorizer $authorizer,
        SensitiveAssetStore $assetStore,
    ): Response|\Illuminate\Http\JsonResponse {
        $capability = match ($printJob->artifact_type) {
            PrintJob::ARTIFACT_RESULT_SHEET => CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
            default => CapabilityCatalog::GUIDANCE_SCORING_OMR,
        };

        if (! $authorizer->decide($request->user(), $capability, [])->allowed) {
            abort(403);
        }

        if (! $printJob->isReady() || $printJob->file_path === null) {
            return response()->json(['error' => 'print_job_not_ready'], 409);
        }

        try {
            $bytes = $printJob->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET
                ? $assetStore->read((string) $printJob->file_path)
                : (string) Storage::disk('local')->get((string) $printJob->file_path);
        } catch (RuntimeException) {
            abort(404);
        }

        if (hash('sha256', $bytes) !== $printJob->file_checksum) {
            abort(500);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline; filename="print-'.$printJob->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
