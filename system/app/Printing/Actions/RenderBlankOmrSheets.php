<?php

namespace App\Printing\Actions;

use App\Assessment\Omr\NativeBundle;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Printing\PrintJob;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Queues blank answer sheets for one template version through the print
 * pipeline. Deep module: callers only provide a version and an actor; the
 * action pins the bundle identity, dedupes open jobs, and dispatches.
 */
final class RenderBlankOmrSheets
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrAnswerSheetTemplateVersion $templateVersion, User $actor): PrintJob
    {
        if (! $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [])->allowed) {
            throw new RuntimeException('The OMR capability is required to print answer sheets.');
        }

        if ($templateVersion->status === OmrAnswerSheetTemplateVersion::STATUS_DRAFT) {
            $openBatchPinned = $templateVersion->pairs()
                ->whereHas('captureBatches', fn ($query) => $query->whereIn('status', ['open', 'closing']))
                ->exists();

            if (! $openBatchPinned) {
                throw new RuntimeException('Blank sheets require an active version or a version pinned by an open batch.');
            }
        }

        $bundle = NativeBundle::load((string) $templateVersion->template->code, (int) $templateVersion->version);

        $pinnedInput = [
            'bundle_code' => $bundle->code,
            'bundle_version' => $bundle->version,
            'bundle_hash' => $bundle->bundleHash,
        ];

        return DB::transaction(function () use ($templateVersion, $actor, $pinnedInput): PrintJob {
            $existing = PrintJob::query()
                ->where('artifact_type', PrintJob::ARTIFACT_BLANK_SHEET)
                ->where('scope_type', 'template_version')
                ->where('scope_id', $templateVersion->id)
                ->whereIn('status', [PrintJob::STATUS_QUEUED, PrintJob::STATUS_RENDERING, PrintJob::STATUS_READY])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
                'scope_type' => 'template_version',
                'scope_id' => $templateVersion->id,
                'requested_by_user_id' => $actor->id,
                'pinned_input_json' => $pinnedInput,
                'progress_total' => 1,
                'progress_done' => 0,
            ]);

            $this->recordAuditEvent->handle('printing.blank_sheets_requested', [
                'print_job_id' => $job->id,
                'template_version_id' => $templateVersion->id,
                'bundle_hash' => $pinnedInput['bundle_hash'],
            ], (string) $actor->id);

            RenderPrintJob::dispatch($job->id)->afterCommit();

            return $job;
        });
    }
}
