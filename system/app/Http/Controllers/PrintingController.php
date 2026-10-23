<?php

namespace App\Http\Controllers;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Foundation\AcademicCycle;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\JsonResponse;
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
    public function show(
        Request $request,
        PrintJob $printJob,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        $capabilities = $this->capabilitiesFor($printJob);
        $scope = $this->authorizationContext($request, $printJob);
        abort_unless(
            $capabilities !== []
                && $scope !== null
                && $authorizer->allowsAny($request->user(), $capabilities, $scope),
            403,
        );

        $items = $printJob->items()->with('admissionAttempt.identity')->orderBy('item_index')->get();
        $stageCounts = $items->groupBy('status')->map->count()->all();

        return response()->json([
            'id' => $printJob->id,
            'artifact_type' => $printJob->artifact_type,
            'is_preview' => $printJob->is_preview,
            'status' => $printJob->status,
            'progress_total' => $printJob->progress_total,
            'progress_done' => $printJob->progress_done,
            'safe_error' => $printJob->safe_error,
            'ready' => $printJob->isReady(),
            'outputs' => $this->outputFormats($printJob),
            'stage_counts' => $stageCounts,
            'items' => $items->map(fn ($item): array => [
                'id' => $item->id,
                'attempt_id' => $item->admission_attempt_id,
                'release_id' => $item->result_release_id,
                'name' => $item->admissionAttempt?->identity
                    ? trim($item->admissionAttempt->identity->first_name.' '.$item->admissionAttempt->identity->last_name)
                    : '—',
                'status' => $item->status,
                'safe_error' => $item->safe_error,
            ])->values()->all(),
        ]);
    }

    public function download(
        Request $request,
        PrintJob $printJob,
        CapabilityAuthorizer $authorizer,
        SensitiveAssetStore $assetStore,
        RecordAuditEvent $recordAuditEvent,
    ): Response|JsonResponse {
        $capabilities = $this->capabilitiesFor($printJob);
        $scope = $this->authorizationContext($request, $printJob);

        if ($capabilities === []
            || $scope === null
            || ! $authorizer->allowsAny($request->user(), $capabilities, $scope)) {
            $recordAuditEvent->handle('printing.job_download_denied', [
                'print_job_id' => $printJob->id,
                'artifact_type' => $printJob->artifact_type,
                'scope' => $printJob->scope_type.':'.$printJob->scope_id,
            ], (string) $request->user()->id);

            abort(403);
        }

        $manifest = is_array($printJob->output_manifest) ? $printJob->output_manifest : [];
        $format = strtolower($request->string('format', $this->defaultOutputFormat($printJob))->toString());

        if (! in_array($format, ['pdf', 'docx', 'zip'], true)) {
            return response()->json(['error' => 'unsupported_print_format'], 422);
        }

        $output = $this->resolveOutput($printJob, $manifest, $format);
        if (! $printJob->isReady() || $output === null) {
            return response()->json(['error' => 'print_output_not_ready'], 409);
        }

        try {
            $bytes = $printJob->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET
                ? $assetStore->read((string) $output['path'])
                : (string) Storage::disk('local')->get((string) $output['path']);
        } catch (RuntimeException) {
            abort(404);
        }

        if (hash('sha256', $bytes) !== ($output['checksum'] ?? null)) {
            abort(500);
        }

        $contentTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
        ];
        $extension = $format === 'pdf' ? 'pdf' : $format;
        $contentType = $contentTypes[$format] ?? 'application/octet-stream';

        $recordAuditEvent->handle('printing.job_downloaded', [
            'print_job_id' => $printJob->id,
            'artifact_type' => $printJob->artifact_type,
            'scope' => $printJob->scope_type.':'.$printJob->scope_id,
            'format' => $format,
            'checksum' => $output['checksum'] ?? null,
        ], (string) $request->user()->id);

        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline; filename="print-'.$printJob->id.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadItem(
        Request $request,
        PrintJob $printJob,
        PrintJobItem $printJobItem,
        CapabilityAuthorizer $authorizer,
        SensitiveAssetStore $assetStore,
        RecordAuditEvent $recordAuditEvent,
    ): Response {
        abort_unless(
            $printJob->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET
                && $printJobItem->print_job_id === $printJob->id,
            404,
        );
        $capabilities = $this->capabilitiesFor($printJob);
        $scope = $this->authorizationContext($request, $printJob);
        abort_unless(
            $capabilities !== []
                && $scope !== null
                && $authorizer->allowsAny($request->user(), $capabilities, $scope),
            403,
        );

        $format = strtolower($request->string('format', 'pdf')->toString());
        if (! in_array($format, ['pdf', 'docx'], true)) {
            return response()->json(['error' => 'unsupported_print_item_format'], 422);
        }
        $path = $format === 'docx' ? $printJobItem->docx_path : $printJobItem->file_path;
        $checksum = $format === 'docx' ? $printJobItem->docx_checksum : $printJobItem->file_checksum;
        if ($printJobItem->status !== PrintJobItem::STATUS_READY || $path === null || $checksum === null) {
            return response()->json(['error' => 'print_item_not_ready'], 409);
        }

        try {
            $bytes = $assetStore->read($path);
        } catch (RuntimeException) {
            abort(404);
        }
        abort_unless(hash('sha256', $bytes) === $checksum, 500);

        $contentType = $format === 'docx'
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/pdf';
        $extension = $format === 'docx' ? 'docx' : 'pdf';
        $recordAuditEvent->handle('printing.job_item_downloaded', [
            'print_job_id' => $printJob->id,
            'print_job_item_id' => $printJobItem->id,
            'format' => $format,
            'checksum' => $checksum,
        ], (string) $request->user()->id);

        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline; filename="print-item-'.$printJobItem->id.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<string>
     */
    private function capabilitiesFor(PrintJob $printJob): array
    {
        if ($printJob->is_preview) {
            return [CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG];
        }

        return match ($printJob->artifact_type) {
            PrintJob::ARTIFACT_RESULT_SHEET => [
                CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
                CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
            ],
            PrintJob::ARTIFACT_BLANK_SHEET => [CapabilityCatalog::GUIDANCE_SCORING_OMR],
            default => [],
        };
    }

    /**
     * @return array{office: string, institution_id: int, cycle: string}|null
     */
    private function authorizationContext(Request $request, PrintJob $printJob): ?array
    {
        if ($printJob->is_preview) {
            $actor = $request->user();
            if (! $actor instanceof User || (int) $printJob->requested_by_user_id !== (int) $actor->id) {
                return null;
            }

            return $this->activeActorContext($actor);
        }

        return match ($printJob->artifact_type) {
            PrintJob::ARTIFACT_RESULT_SHEET => $this->resultSheetContext($printJob),
            PrintJob::ARTIFACT_BLANK_SHEET => $this->blankSheetContext($printJob),
            default => null,
        };
    }

    /**
     * @return array{office: string, institution_id: int, cycle: string}|null
     */
    private function activeActorContext(User $actor): ?array
    {
        $scope = $actor->actorScope();
        if ($scope->office !== 'guidance'
            || $scope->institutionId === null
            || trim((string) $scope->activeCycleCode) === '') {
            return null;
        }

        $cycle = AcademicCycle::query()
            ->where('institution_id', $scope->institutionId)
            ->where('code', trim((string) $scope->activeCycleCode))
            ->first();

        return $cycle === null ? null : [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ];
    }

    /**
     * @return array{office: string, institution_id: int, cycle: string}|null
     */
    private function resultSheetContext(PrintJob $printJob): ?array
    {
        $pinned = is_array($printJob->pinned_input_json) ? $printJob->pinned_input_json : [];

        if (isset($pinned['document_ids'])) {
            if ($printJob->scope_type !== 'result_document') {
                return null;
            }

            $rawDocumentIds = $pinned['document_ids'];
            if (! is_array($rawDocumentIds) || $rawDocumentIds === []) {
                return null;
            }

            $documentIds = [];
            foreach ($rawDocumentIds as $rawDocumentId) {
                $documentId = $this->positiveInteger($rawDocumentId);
                if ($documentId === null) {
                    return null;
                }
                $documentIds[] = $documentId;
            }

            $documentIds = array_values(array_unique($documentIds));
            if (count($documentIds) !== count($rawDocumentIds)
                || ! in_array((int) $printJob->scope_id, $documentIds, true)) {
                return null;
            }

            $documents = ResultDocument::query()
                ->with('admissionAttempt')
                ->whereIn('id', $documentIds)
                ->get();

            if ($documents->count() !== count($documentIds)) {
                return null;
            }

            $attempts = $documents->map(fn (ResultDocument $document) => $document->admissionAttempt);
            if ($attempts->contains(fn ($attempt): bool => $attempt === null)) {
                return null;
            }
        } else {
            if ($printJob->scope_type !== 'result_release') {
                return null;
            }

            $rawReleaseIds = $pinned['release_ids'] ?? [$printJob->scope_id];
            if (! is_array($rawReleaseIds) || $rawReleaseIds === []) {
                return null;
            }

            $releaseIds = [];
            foreach ($rawReleaseIds as $rawReleaseId) {
                $releaseId = $this->positiveInteger($rawReleaseId);
                if ($releaseId === null) {
                    return null;
                }
                $releaseIds[] = $releaseId;
            }

            $releaseIds = array_values(array_unique($releaseIds));
            if (count($releaseIds) !== count($rawReleaseIds)
                || ! in_array((int) $printJob->scope_id, $releaseIds, true)) {
                return null;
            }

            $releases = ResultRelease::query()
                ->with('admissionAttempt')
                ->whereIn('id', $releaseIds)
                ->get();

            if ($releases->count() !== count($releaseIds)) {
                return null;
            }

            $attempts = $releases->map(fn (ResultRelease $release) => $release->admissionAttempt);
            if ($attempts->contains(fn ($attempt): bool => $attempt === null)) {
                return null;
            }
        }

        $institutionIds = $attempts->map(fn ($attempt): int => (int) $attempt->institution_id)->unique();
        $cycleIds = $attempts->map(fn ($attempt): int => (int) $attempt->academic_cycle_id)->unique();
        if ($institutionIds->count() !== 1 || $cycleIds->count() !== 1) {
            return null;
        }

        return $this->academicCycleContext($institutionIds->first(), $cycleIds->first());
    }

    /**
     * @return array{office: string, institution_id: int, cycle: string}|null
     */
    private function blankSheetContext(PrintJob $printJob): ?array
    {
        if ($printJob->scope_type !== 'template_version' || (int) $printJob->scope_id < 1) {
            return null;
        }

        $pinned = is_array($printJob->pinned_input_json) ? $printJob->pinned_input_json : [];
        $institutionId = $this->positiveInteger($pinned['institution_id'] ?? null);
        $academicCycleId = $this->positiveInteger($pinned['academic_cycle_id'] ?? null);
        if ($institutionId === null || $academicCycleId === null) {
            return null;
        }

        if (array_key_exists('qr_disabled_issuance_id', $pinned)) {
            $issuanceId = $this->positiveInteger($pinned['qr_disabled_issuance_id']);
            if ($issuanceId === null) {
                return null;
            }

            $issuance = OmrQrDisabledIssuance::query()
                ->whereKey($issuanceId)
                ->where('print_job_id', $printJob->id)
                ->where('template_version_id', $printJob->scope_id)
                ->where('institution_id', $institutionId)
                ->where('academic_cycle_id', $academicCycleId)
                ->first();
            if ($issuance === null) {
                return null;
            }
        }

        return $this->academicCycleContext($institutionId, $academicCycleId);
    }

    /**
     * @return array{office: string, institution_id: int, cycle: string}|null
     */
    private function academicCycleContext(int $institutionId, int $academicCycleId): ?array
    {
        if ($institutionId < 1 || $academicCycleId < 1) {
            return null;
        }

        $cycle = AcademicCycle::query()
            ->whereKey($academicCycleId)
            ->where('institution_id', $institutionId)
            ->first();

        return $cycle === null ? null : [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ];
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function defaultOutputFormat(PrintJob $printJob): string
    {
        $extension = strtolower(pathinfo((string) $printJob->file_path, PATHINFO_EXTENSION));

        return in_array($extension, ['pdf', 'docx', 'zip'], true) ? $extension : 'pdf';
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{path: string, checksum?: string|null}|null
     */
    private function resolveOutput(PrintJob $printJob, array $manifest, string $format): ?array
    {
        $output = $manifest[$format] ?? null;
        if (is_array($output) && is_string($output['path'] ?? null) && $output['path'] !== '') {
            return $output;
        }

        $path = (string) $printJob->file_path;
        if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== $format) {
            return null;
        }

        return [
            'path' => $path,
            'checksum' => $printJob->file_checksum,
        ];
    }

    /**
     * @return list<string>
     */
    private function outputFormats(PrintJob $printJob): array
    {
        if ($printJob->artifact_type !== PrintJob::ARTIFACT_BLANK_SHEET) {
            return is_array($printJob->output_manifest)
                ? array_keys($printJob->output_manifest)
                : ['pdf'];
        }

        $manifest = is_array($printJob->output_manifest) ? $printJob->output_manifest : [];
        $formats = array_values(array_filter(
            ['pdf', 'docx', 'zip'],
            fn (string $format): bool => is_array($manifest[$format] ?? null)
                && ! empty($manifest[$format]['path']),
        ));
        if ($formats !== []) {
            return $formats;
        }

        return $printJob->file_path === null ? [] : [$this->defaultOutputFormat($printJob)];
    }
}
