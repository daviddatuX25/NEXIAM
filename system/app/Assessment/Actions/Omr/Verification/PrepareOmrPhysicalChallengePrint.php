<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function count;
use function getimagesizefromstring;
use function is_array;
use function is_string;

/**
 * Idempotent prepare-print action for physical challenges (OpenSpec 35.21 / Cut F).
 *
 * Converts legacy single/multi-page PNG challenge sheets into an Actual-Size
 * printable PDF, updates the challenge sheet_asset_id reference, and audits.
 * Replays cleanly without re-rendering if the sheet is already a PDF.
 */
final class PrepareOmrPhysicalChallengePrint
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrPhysicalChallengeRenderer $renderer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly OmrVerificationTargetLoader $targets,
    ) {}

    /**
     * @return array{
     *     run_id: int,
     *     challenge_id: int,
     *     challenge_code: string,
     *     status: string,
     *     print_url: string,
     *     guide_url: ?string,
     *     status_url: string,
     * }
     */
    public function handle(OmrTemplateVerificationRun $run, User $actor, ?string $methodName = null): array
    {
        OmrSourcePolicy::assertSystemDesignedRun($run);
        if ($run->draft_id !== null) {
            $draft = $run->draft ?? OmrTemplateAuthoringDraft::query()->findOrFail($run->draft_id);
            $this->drafts->assertOwner($draft, $actor);
        }
        if ($methodName !== null && ! in_array($methodName, OmrPhysicalChallenge::METHODS, true)) {
            throw new RuntimeException('verification_method_invalid');
        }
        $challenge = $run->challenges()
            ->when($methodName !== null, fn ($query) => $query->where('method', $methodName))
            ->with(['sheetAsset', 'guideAsset'])
            ->latest('id')
            ->first();
        if ($challenge === null) {
            throw new RuntimeException('physical_challenge_missing');
        }

        if (! in_array($run->status, [
            OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            OmrTemplateVerificationRun::STATUS_FAILED,
        ], true)) {
            throw new RuntimeException('challenge_prepare_print_invalid_status');
        }
        $resolvedMethod = (string) ($methodName ?? $challenge->method ?? OmrPhysicalChallenge::METHOD_PRE_SHADED);
        $this->targets->loadPhysical($run, $resolvedMethod);

        // Idempotent: if already an Actual-Size PDF, return immediately without mutation.
        if ($challenge->sheetAsset !== null && $challenge->sheetAsset->mime === 'application/pdf') {
            return $this->resultPayload($run, $challenge);
        }

        // Legacy PNG pack: locate page raster assets and build the Actual-Size PDF.
        $pages = OmrTemplateAsset::query()
            ->where(function ($query) use ($run): void {
                if ($run->draft_id !== null) {
                    $query->where('authoring_draft_id', $run->draft_id);
                } else {
                    $query->where('template_version_id', $run->template_version_id);
                }
            })
            ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
            ->where(function ($query) use ($challenge, $run): void {
                $query->whereJsonContains('metadata->challenge_code', $challenge->challenge_code)
                    ->orWhereJsonContains('metadata->run_id', $run->id)
                    ->orWhere('id', $challenge->sheet_asset_id);
            })
            ->when($methodName !== null, fn ($query) => $query->whereJsonContains('metadata->method', $methodName))
            ->orderBy('page_no')
            ->get();

        if ($pages->isEmpty() && $challenge->sheetAsset !== null) {
            $pages = collect([$challenge->sheetAsset]);
        }

        if ($pages->isEmpty()) {
            throw new RuntimeException('challenge_page_assets_missing');
        }

        $pageData = [];
        foreach ($pages as $pageAsset) {
            $bytes = Storage::disk($pageAsset->disk)->get($pageAsset->path);
            if (! is_string($bytes) || $bytes === '') {
                throw new RuntimeException('challenge_page_asset_unreadable');
            }
            $pageNo = $pageAsset->page_no ?? (count($pageData) + 1);
            $dimensions = @getimagesizefromstring($bytes);
            $width = is_array($dimensions) ? (int) $dimensions[0] : 2480;
            $height = is_array($dimensions) ? (int) $dimensions[1] : 3508;
            $pageData[] = [
                'page_no' => (int) $pageNo,
                'bytes' => $bytes,
                'width' => $width,
                'height' => $height,
            ];
        }

        $pdfBytes = $this->renderer->imageBackedPdf($pageData);

        $runTarget = $run->draft_id !== null
            ? ['kind' => 'draft', 'id' => (int) $run->draft_id]
            : ['kind' => 'version', 'id' => (int) $run->template_version_id];

        if ($runTarget['kind'] === 'version') {
            $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($runTarget['id']);
            $pdfAsset = $this->assets->putVersionAsset($version, OmrTemplateAsset::KIND_PRINT_PDF, $pdfBytes, 'application/pdf', null, [
                'run_id' => $run->id,
                'challenge_code' => $challenge->challenge_code,
                'method' => $challenge->method,
                'plan_hash' => $challenge->plan_hash,
                'expected_vector_hash' => $challenge->expected_vector_hash,
                'role' => 'challenge_sheet',
                'converted_from_legacy' => true,
                'sheet_hash' => $challenge->print_metadata['sheet_hash'] ?? $challenge->plan_hash,
                'renderer_version' => $challenge->renderer_version,
            ]);
        } else {
            $draft = OmrTemplateAuthoringDraft::query()->findOrFail($runTarget['id']);
            $pdfAsset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, $pdfBytes, 'application/pdf', null, [
                'run_id' => $run->id,
                'challenge_code' => $challenge->challenge_code,
                'method' => $challenge->method,
                'plan_hash' => $challenge->plan_hash,
                'expected_vector_hash' => $challenge->expected_vector_hash,
                'role' => 'challenge_sheet',
                'converted_from_legacy' => true,
                'sheet_hash' => $challenge->print_metadata['sheet_hash'] ?? $challenge->plan_hash,
                'renderer_version' => $challenge->renderer_version,
            ]);
        }

        $updated = $this->coordinator->updateChallengeAssets(
            run: $run,
            challenge: $challenge,
            actor: $actor,
            attributes: [
                'sheet_asset_id' => $pdfAsset->id,
                'sheet_asset_hash' => $pdfAsset->sha256,
                'print_metadata' => [
                    ...(array) ($challenge->print_metadata ?? []),
                    'method' => $challenge->method,
                    'plan_hash' => $challenge->plan_hash,
                    'expected_vector_hash' => $challenge->expected_vector_hash,
                    'sheet_asset_hash' => $pdfAsset->sha256,
                    'converted_from_legacy' => true,
                    'reason' => 'print_prepared',
                ],
            ],
            action: 'omr.challenge.print_prepared',
        );

        return $this->resultPayload($run, $updated);
    }

    /**
     * @return array{
     *     run_id: int,
     *     challenge_id: int,
     *     challenge_code: string,
     *     status: string,
     *     print_url: string,
     *     guide_url: ?string,
     *     status_url: string,
     * }
     */
    private function resultPayload(OmrTemplateVerificationRun $run, OmrPhysicalChallenge $challenge): array
    {
        return [
            'run_id' => $run->id,
            'challenge_id' => $challenge->id,
            'challenge_code' => $challenge->challenge_code,
            'method' => $challenge->method,
            'status' => $run->status,
            'print_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                'run' => $run->id,
                'asset' => $challenge->sheet_asset_id,
            ]),
            'guide_url' => $challenge->guide_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $challenge->guide_asset_id,
                ])
                : null,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
        ];
    }
}
