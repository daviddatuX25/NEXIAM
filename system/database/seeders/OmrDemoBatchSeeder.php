<?php

namespace Database\Seeders;

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

use function array_map;
use function base64_decode;
use function count;
use function range;
use function sprintf;
use function strlen;

/**
 * Seeds ONE demo OMR batch containing a single needs-review sheet for one
 * whole examinee (bound to an exam assignment) against the active profile
 * pair — the focused near-production dataset. Founder decision 2026-08-17:
 * the default seeded state must look like production (one answer sheet, one
 * pair, one booklet), not a synthetic state gallery.
 *
 * The multi-state fixture (pending-pages + terminal-failed sheets for the
 * capture/review/retry surfaces) lives in {@see OmrDemoMultiStateBatchSeeder}.
 */
class OmrDemoBatchSeeder extends Seeder
{
    public function run(): void
    {
        $ctx = $this->context();
        if ($ctx === null) {
            return;
        }

        $batch = $this->batchFor($ctx);
        if ($batch === null) {
            $batch = $this->createBatch($ctx);
            $this->createNeedsReviewSheet($ctx, $batch);
        }

        $this->command?->info(sprintf('OMR demo batch #%d ready: 1 needs-review examinee sheet (focused near-production dataset).', $batch->id));
    }

    /**
     * Resolves the demo context shared by the focused and multi-state seeders.
     *
     * @return array<string, mixed>|null
     */
    protected function context(): ?array
    {
        $session = GradingSession::query()
            ->with('examSession.currentMaterialSnapshot.templateVersion.template')
            ->where('status', '!=', GradingSession::STATUS_FINALIZED)
            ->orderByDesc('id')
            ->first();
        $snapshot = $session?->examSession?->currentMaterialSnapshot;
        if ($session === null || $snapshot === null) {
            $this->command?->warn('No grading session with published material found; skipping OMR demo batch.');

            return null;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $pair = OmrTemplateBookletPair::query()
            ->where('template_version_id', $snapshot->template_version_id)
            ->where('booklet_version_id', $snapshot->booklet_version_id)
            ->first();
        if ($pair === null) {
            $this->command?->warn('The grading session material has no matching OMR reference pair; skipping OMR demo batch.');

            return null;
        }
        $profilePair = $snapshot->templateVersion?->template?->code === 'SYN-OMR-PROFILE-150';

        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $assetStore = app(SensitiveAssetStore::class);
        $assetHmac = $assetStore->hmac($bytes);

        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $session->exam_session_id)
            ->whereHas('admissionAttempt', fn ($query) => $query->whereNotNull('applicant_identity_id'))
            ->first();

        $pageCount = $profilePair ? 1 : 2;
        $items = $profilePair ? $this->profileItems() : $this->items(200);
        $pageItems = $profilePair ? $items : $this->items(100);

        return [
            'session' => $session,
            'actor' => $actor,
            'pair' => $pair,
            'profilePair' => $profilePair,
            'pageCount' => $pageCount,
            'items' => $items,
            'pageItems' => $pageItems,
            'bytes' => $bytes,
            'assetStore' => $assetStore,
            'assetHmac' => $assetHmac,
            'assignment' => $assignment,
        ];
    }

    /**
     * Existing needs-review batch for this session+pair, or null to rebuild.
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function batchFor(array $ctx): ?OmrCaptureBatch
    {
        /** @var GradingSession $session */
        $session = $ctx['session'];
        /** @var OmrTemplateBookletPair $pair */
        $pair = $ctx['pair'];

        $batch = OmrCaptureBatch::query()
            ->where('grading_session_id', $session->id)
            ->where('template_version_id', (int) $pair->template_version_id)
            ->first();

        if ($batch === null) {
            return null;
        }

        if ($batch->sheets()->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW)->exists()) {
            return $batch;
        }

        // Partial batch from an interrupted seed: remove and rebuild.
        foreach ($batch->sheets as $sheet) {
            $sheet->pages()->delete();
        }
        $batch->sheets()->delete();
        $batch->delete();

        return null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function createBatch(array $ctx): OmrCaptureBatch
    {
        /** @var GradingSession $session */
        $session = $ctx['session'];
        /** @var User $actor */
        $actor = $ctx['actor'];
        /** @var OmrTemplateBookletPair $pair */
        $pair = $ctx['pair'];

        return app(CreateOmrCaptureBatch::class)->handle($session, $actor, 'omr-demo-batch');
    }

    /**
     * The one whole examinee: a single needs-review sheet bound to the exam
     * assignment with private evidence and profile-aligned proposal.
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function createNeedsReviewSheet(array $ctx, OmrCaptureBatch $batch): OmrCaptureSheet
    {
        $profilePair = (bool) $ctx['profilePair'];
        $pageCount = (int) $ctx['pageCount'];
        $items = $ctx['items'];
        $bytes = $ctx['bytes'];
        $assetHmac = $ctx['assetHmac'];
        $assignment = $ctx['assignment'];
        $actor = $ctx['actor'];

        $reviewClientCaptureId = (string) Str::uuid();
        $reviewAssetPath = $pageCount === 1 ? 'omr/demo/review-'.$reviewClientCaptureId.'.bin' : null;
        $reviewSheet = OmrCaptureSheet::query()->create([
            'batch_id' => $batch->id,
            'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
            'capture_mode' => OmrCaptureSheet::MODE_AUTO,
            'version' => 1,
            'expected_page_count' => $pageCount,
            'client_capture_id' => $reviewClientCaptureId,
            ...($pageCount === 1 ? [
                'asset_path' => $reviewAssetPath,
                'asset_hmac' => $assetHmac,
                'asset_hmac_key_id' => 'omr-demo-v1',
                'asset_format_version' => 'v1',
                'asset_key_id' => 'omr-demo-v1',
                'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($bytes),
            ] : []),
            'admission_attempt_id' => $assignment?->admission_attempt_id,
            'detected_applicant_number' => '1234567890',
            'detected_applicant_confidence' => $profilePair ? null : 0.97,
            'detected_birthdate' => $profilePair ? null : '01012010',
            'sheet_confidence' => $profilePair ? null : 0.92,
            'proposal_json' => $this->proposal($items, $pageCount, ! $profilePair),
            'captured_by_user_id' => (int) $actor->id,
            'captured_at' => now()->subMinutes(20),
        ]);
        if ($reviewAssetPath !== null) {
            /** @var SensitiveAssetStore $assetStore */
            $assetStore = $ctx['assetStore'];
            $assetStore->store($bytes, $reviewAssetPath);
        }
        foreach (range(1, $pageCount) as $pageNo) {
            $pageAssetPath = 'omr/demo/'.$reviewSheet->id.'-p'.$pageNo.'.bin';
            /** @var SensitiveAssetStore $assetStore */
            $assetStore = $ctx['assetStore'];
            $assetStore->store($bytes, $pageAssetPath);
            OmrCaptureSheetPage::query()->create([
                'sheet_id' => $reviewSheet->id,
                'page_no' => $pageNo,
                'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
                'asset_path' => $pageAssetPath,
                'asset_hmac' => $assetHmac,
                'asset_hmac_key_id' => 'omr-demo-v1',
                'asset_format_version' => 'v1',
                'asset_key_id' => 'omr-demo-v1',
                'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($bytes),
                'proposal_json' => $this->pageProposal($pageNo, $ctx['pageItems'], $pageCount, ! $profilePair),
            ]);
        }

        return $reviewSheet;
    }

    /**
     * Incomplete resumable set (queued page 1, missing remaining pages).
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function createPendingSheet(array $ctx, OmrCaptureBatch $batch): void
    {
        $pageCount = (int) $ctx['pageCount'];
        $bytes = $ctx['bytes'];
        $assetHmac = $ctx['assetHmac'];

        $pendingClientCaptureId = (string) Str::uuid();
        $pendingAssetPath = $pageCount === 1 ? 'omr/demo/pending-'.$pendingClientCaptureId.'.bin' : null;
        $pendingSheet = OmrCaptureSheet::query()->create([
            'batch_id' => $batch->id,
            'status' => OmrCaptureSheet::STATUS_PENDING_PAGES,
            'capture_mode' => OmrCaptureSheet::MODE_AUTO,
            'version' => 0,
            'expected_page_count' => $pageCount,
            'client_capture_id' => $pendingClientCaptureId,
            ...($pageCount === 1 ? [
                'asset_path' => $pendingAssetPath,
                'asset_hmac' => $assetHmac,
                'asset_hmac_key_id' => 'omr-demo-v1',
                'asset_format_version' => 'v1',
                'asset_key_id' => 'omr-demo-v1',
                'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($bytes),
            ] : []),
            'captured_by_user_id' => (int) $ctx['actor']->id,
            'captured_at' => now()->subMinutes(8),
        ]);
        if ($pendingAssetPath !== null) {
            /** @var SensitiveAssetStore $assetStore */
            $assetStore = $ctx['assetStore'];
            $assetStore->store($bytes, $pendingAssetPath);
        }
        $pendingPageAssetPath = 'omr/demo/'.$pendingSheet->id.'-p1.bin';
        /** @var SensitiveAssetStore $assetStore */
        $assetStore = $ctx['assetStore'];
        $assetStore->store($bytes, $pendingPageAssetPath);
        OmrCaptureSheetPage::query()->create([
            'sheet_id' => $pendingSheet->id,
            'page_no' => 1,
            'status' => OmrCaptureSheetPage::STATUS_QUEUED,
            'asset_path' => $pendingPageAssetPath,
            'asset_hmac' => $assetHmac,
            'asset_hmac_key_id' => 'omr-demo-v1',
            'asset_format_version' => 'v1',
            'asset_key_id' => 'omr-demo-v1',
            'asset_state' => OmrCaptureSheet::ASSET_LIVE,
            'original_mime' => 'image/png',
            'original_bytes' => strlen($bytes),
        ]);
    }

    /**
     * Terminal-failed sheet (retry-able from the gallery card).
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function createFailedSheet(array $ctx, OmrCaptureBatch $batch): void
    {
        $bytes = $ctx['bytes'];
        $assetHmac = $ctx['assetHmac'];

        $failedAssetPath = 'omr/demo/'.$batch->id.'-failed.bin';
        $failedSheet = OmrCaptureSheet::query()->create([
            'batch_id' => $batch->id,
            'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
            'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
            'version' => 1,
            'expected_page_count' => 1,
            'client_capture_id' => (string) Str::uuid(),
            'asset_path' => $failedAssetPath,
            'asset_hmac' => $assetHmac,
            'asset_hmac_key_id' => 'omr-demo-v1',
            'asset_format_version' => 'v1',
            'asset_key_id' => 'omr-demo-v1',
            'asset_state' => OmrCaptureSheet::ASSET_LIVE,
            'original_mime' => 'image/png',
            'original_bytes' => strlen($bytes),
            'captured_by_user_id' => (int) $ctx['actor']->id,
            'captured_at' => now()->subMinutes(30),
        ]);
        /** @var SensitiveAssetStore $assetStore */
        $assetStore = $ctx['assetStore'];
        $assetStore->store($bytes, $failedAssetPath);
        $failedPageAssetPath = 'omr/demo/'.$failedSheet->id.'-p1.bin';
        $assetStore->store($bytes, $failedPageAssetPath);
        OmrCaptureSheetPage::query()->create([
            'sheet_id' => $failedSheet->id,
            'page_no' => 1,
            'status' => OmrCaptureSheetPage::STATUS_FAILED_TERMINAL,
            'asset_path' => $failedPageAssetPath,
            'asset_hmac' => $assetHmac,
            'asset_hmac_key_id' => 'omr-demo-v1',
            'asset_format_version' => 'v1',
            'asset_key_id' => 'omr-demo-v1',
            'asset_state' => OmrCaptureSheet::ASSET_LIVE,
            'original_mime' => 'image/png',
            'original_bytes' => strlen($bytes),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function profileItems(): array
    {
        $areaCodes = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
        $areas = AptitudeArea::query()->whereIn('code', $areaCodes)->where('is_active', true)->get()->keyBy('code');
        if ($areas->count() !== count($areaCodes)) {
            throw new RuntimeException('Synthetic OMR profile capture requires all six active aptitude areas.');
        }

        $items = [];
        $position = 1;
        foreach ($areaCodes as $areaCode) {
            for ($questionNo = 1; $questionNo <= 25; $questionNo++) {
                $option = ['A', 'B', 'C', 'D'][($position - 1) % 4];
                $items[] = [
                    'position' => $position,
                    'question_no' => $questionNo,
                    'area_id' => (int) $areas[$areaCode]->id,
                    'area_code' => $areaCode,
                    'detected_answer' => $option,
                    'correct_option_code' => $option,
                    'confidence' => null,
                    'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.02, 'h' => 0.02],
                ];
                $position++;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(int $count): array
    {
        $items = [];
        foreach (range(1, $count) as $position) {
            $items[] = [
                'position' => $position,
                'question_no' => (($position - 1) % 100) + 1,
                'area_id' => $position <= 100 ? 1 : 2,
                'area_code' => $position <= 100 ? 'VERBAL' : 'QUANT',
                'detected_answer' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
                'correct_option_code' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
                'confidence' => 0.9,
                'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.02, 'h' => 0.02],
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function proposal(array $items, int $pageCount, bool $includeProviderConfidence = true): array
    {
        $perAreaScores = [];
        $perAreaIndexes = [];
        foreach ($items as $item) {
            $areaCode = (string) ($item['area_code'] ?? '');
            if (! isset($perAreaIndexes[$areaCode])) {
                $perAreaIndexes[$areaCode] = count($perAreaIndexes) + 1;
                $perAreaScores[(string) $perAreaIndexes[$areaCode]] = [
                    'score' => 0,
                    'confidence' => $includeProviderConfidence ? 0.9 : null,
                    'area_code' => $areaCode,
                ];
            }
            $perAreaScores[(string) $perAreaIndexes[$areaCode]]['score']++;
        }

        return [
            'envelope' => OmrDetectionRequest::ENVELOPE_VERSION,
            'provider' => 'nexiam.fake',
            'input_hash' => str_repeat('0', 64),
            'native_bundle_hash' => str_repeat('1', 64),
            'manifest_hash' => str_repeat('2', 64),
            'config_hash' => str_repeat('3', 64),
            'patch_identity' => 'nexiam-headless-overlay-v1',
            'image' => null,
            'field_block_shifts' => [],
            'aligned_image' => null,
            'applicant_number_proposal' => '1234567890',
            'applicant_number_confidence' => $includeProviderConfidence ? 0.97 : null,
            'birthdate_proposal' => $includeProviderConfidence ? '01012010' : null,
            'sheet_confidence' => $includeProviderConfidence ? 0.92 : null,
            'per_area_scores' => $perAreaScores,
            'items' => $items,
            'pages' => $pageCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pageItems
     * @return array<string, mixed>
     */
    private function pageProposal(int $pageNo, array $pageItems, int $pageCount, bool $includeProviderConfidence = true): array
    {
        $items = array_map(fn (array $item): array => [...$item, 'page' => $pageNo], $pageItems);

        return [
            'envelope' => OmrDetectionRequest::ENVELOPE_VERSION,
            'provider' => 'nexiam.fake',
            'input_hash' => str_repeat('0', 64),
            'native_bundle_hash' => str_repeat('1', 64),
            'manifest_hash' => str_repeat('2', 64),
            'config_hash' => str_repeat('3', 64),
            'patch_identity' => 'nexiam-headless-overlay-v1',
            'image' => null,
            'field_block_shifts' => [],
            'aligned_image' => null,
            'applicant_number_proposal' => '1234567890',
            'applicant_number_confidence' => $includeProviderConfidence ? 0.97 : null,
            'birthdate_proposal' => $includeProviderConfidence ? '01012010' : null,
            'sheet_confidence' => $includeProviderConfidence ? 0.92 : null,
            'per_area_scores' => [],
            'items' => $items,
            'pages' => $pageCount,
        ];
    }
}
