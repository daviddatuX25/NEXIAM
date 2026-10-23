<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationItemReviewRevision;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class DiscardOmrSandboxDraft
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrTemplateAssetStore $assetStore,
    ) {}

    /**
     * Discard an authoring draft and purge its sandbox verification runs,
     * temporary captures, assets, and any unused compiled test version.
     *
     * @return array{draft_id: int, compiled_version_id: ?int}
     */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor): array
    {
        $actorId = (int) $actor->id;
        $draftId = (int) $draft->id;

        [$compiledVersionId, $assetsToPurge] = DB::transaction(function () use ($draftId, $actorId): array {
            /** @var OmrTemplateAuthoringDraft $lockedDraft */
            $lockedDraft = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draftId);

            abort_unless(
                (int) $lockedDraft->owner_user_id === $actorId,
                404,
                'Draft not found or not owned by user.'
            );

            /** @var ?OmrAnswerSheetTemplateVersion $version */
            $version = $lockedDraft->compiled_version_id !== null
                ? OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->find($lockedDraft->compiled_version_id)
                : null;

            if ($version !== null) {
                // Ensure compiled version is not bound to booklets, exam sessions, or active scoring
                $this->assertVersionIsDisposableSandbox($version, $lockedDraft);
            }

            // Enable sandbox purge mode in PostgreSQL to allow delete past immutability triggers
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL nexiam.sandbox_purge = 'on'");
            }

            // Gather all verification runs for draft (and its version if deleting version)
            $runIds = OmrTemplateVerificationRun::query()
                ->where('draft_id', $draftId)
                ->when($version !== null, fn ($q) => $q->orWhere('template_version_id', $version->id))
                ->pluck('id')
                ->all();

            if ($runIds !== []) {
                $this->purgeVerificationTree($runIds);
            }

            // Gather all assets to purge from filesystem
            $assetsQuery = OmrTemplateAsset::query()
                ->where('authoring_draft_id', $draftId);
            if ($version !== null) {
                $assetsQuery->orWhere('template_version_id', $version->id);
            }
            $assets = $assetsQuery->get(['disk', 'path'])->all();
            $assetsQuery->delete();

            $deletedVersionId = null;
            if ($version !== null) {
                $deletedVersionId = (int) $version->id;
                $lockedDraft->update(['compiled_version_id' => null]);
                $version->delete();
            }

            $this->recordAuditEvent->handle('omr.canonical_draft_abandoned', [
                'draft_id' => $draftId,
                'purged_compiled_version_id' => $deletedVersionId,
                'purged_runs_count' => count($runIds),
            ], (string) $actorId);

            $lockedDraft->delete();

            return [$deletedVersionId, $assets];
        });

        // Clean up files after transaction commits
        foreach ($assetsToPurge as $asset) {
            Storage::disk($asset->disk)->delete($asset->path);
        }
        $this->assetStore->purgeDraftStorage($draft);

        return [
            'draft_id' => $draftId,
            'compiled_version_id' => $compiledVersionId,
        ];
    }

    /**
     * Check if a draft or compiled version is safe to discard.
     */
    public function isDiscardable(OmrTemplateAuthoringDraft $draft, ?User $actor = null): bool
    {
        if ($actor !== null && (int) $draft->owner_user_id !== (int) $actor->id) {
            return false;
        }

        if ($draft->compiled_version_id === null) {
            return true;
        }

        $version = OmrAnswerSheetTemplateVersion::query()->find($draft->compiled_version_id);
        if ($version === null) {
            return true;
        }

        return $this->isVersionDisposableSandbox($version, $draft);
    }

    private function assertVersionIsDisposableSandbox(
        OmrAnswerSheetTemplateVersion $version,
        OmrTemplateAuthoringDraft $draft,
    ): void {
        if (! $this->isVersionDisposableSandbox($version, $draft)) {
            abort(409, 'This template version is in use by booklets, exam sessions, or production captures and cannot be discarded.');
        }
    }

    private function isVersionDisposableSandbox(
        OmrAnswerSheetTemplateVersion $version,
        OmrTemplateAuthoringDraft $draft,
    ): bool {
        // A version pinned by a Test Kit is immutable and cannot be discarded.
        if (TestKitVersion::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if bound to any Question Booklet version
        if (OmrQuestionBookletVersion::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if in a booklet pair
        if (OmrTemplateBookletPair::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if an exam session material snapshot exists
        if (ExamSessionMaterialSnapshot::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if captures or grading batches exist
        if (OmrCaptureBatch::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if printed blank instances exist
        if (OmrBlankInstance::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if supplier stock lots exist
        if (OmrSupplierStockLot::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if pending capture sets or captures exist
        if (OmrPendingCapture::query()->where('template_version_id', $version->id)->exists()
            || OmrPendingCaptureSet::query()->where('template_version_id', $version->id)->exists()
            || OmrDiagnosticRun::query()->where('template_version_id', $version->id)->exists()) {
            return false;
        }

        // Cannot discard if referenced by other drafts
        if (OmrTemplateAuthoringDraft::query()
            ->where('compiled_version_id', $version->id)
            ->where('id', '!=', $draft->id)
            ->exists()) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<int>  $runIds
     */
    private function purgeVerificationTree(array $runIds): void
    {
        $captureIds = OmrTemplateVerificationCapture::query()
            ->whereIn('run_id', $runIds)
            ->pluck('id')
            ->all();

        if ($captureIds !== []) {
            $evidenceIds = OmrTemplateVerificationItemEvidence::query()
                ->whereIn('capture_id', $captureIds)
                ->pluck('id')
                ->all();

            if ($evidenceIds !== []) {
                OmrTemplateVerificationItemReviewRevision::query()
                    ->whereIn('verification_item_evidence_id', $evidenceIds)
                    ->delete();

                OmrTemplateVerificationItemEvidence::query()
                    ->whereIn('id', $evidenceIds)
                    ->delete();
            }

            // Clear supersession links between captures to avoid FK restrict
            OmrTemplateVerificationCapture::query()
                ->whereIn('id', $captureIds)
                ->update(['superseded_by_attempt_id' => null]);
        }

        $methodIds = OmrTemplateVerificationMethod::query()
            ->whereIn('run_id', $runIds)
            ->pluck('id')
            ->all();

        if ($methodIds !== []) {
            // Clear selected_attempt_id pointers on method pages
            OmrTemplateVerificationMethodPage::query()
                ->whereIn('method_id', $methodIds)
                ->update(['selected_attempt_id' => null]);

            OmrTemplateVerificationMethodPage::query()
                ->whereIn('method_id', $methodIds)
                ->delete();

            // Now safe to delete captures and methods
            if ($captureIds !== []) {
                OmrTemplateVerificationCapture::query()
                    ->whereIn('id', $captureIds)
                    ->delete();
            }

            OmrTemplateVerificationMethod::query()
                ->whereIn('id', $methodIds)
                ->delete();
        } elseif ($captureIds !== []) {
            OmrTemplateVerificationCapture::query()
                ->whereIn('id', $captureIds)
                ->delete();
        }

        // Delete physical challenges
        OmrPhysicalChallenge::query()
            ->whereIn('run_id', $runIds)
            ->delete();

        // Finally delete the verification runs
        OmrTemplateVerificationRun::query()
            ->whereIn('id', $runIds)
            ->delete();
    }
}
