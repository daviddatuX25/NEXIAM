<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\Omr\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator;
use App\Assessment\Omr\Queries\OmrEvidenceQuery;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\StartExamSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function evidenceQueryActor(): User
{
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    return $actor;
}

/** @return array{actor:User,run:OmrTemplateVerificationRun,captures:array<string,OmrTemplateVerificationCapture>} */
function evidenceQueryFixture(): array
{
    Cache::flush();
    $actor = evidenceQueryActor();
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => Institution::current()->id,
        'is_synthetic' => true,
        'code' => 'EVIDENCE-'.uniqid(),
        'name' => 'Evidence query fixture',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_IMAGE,
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => [],
        'owner_user_id' => $actor->id,
    ]);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'started_by_user_id' => $actor->id,
    ]);
    $captures = [];
    foreach (OmrTemplateVerificationMethod::METHODS as $methodName) {
        $method = OmrTemplateVerificationMethod::query()->create([
            'run_id' => $run->id,
            'method' => $methodName,
            'method_state' => OmrTemplateVerificationMethod::STATE_PASSED,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => $draft->id,
            'target_version' => 'v1',
            'target_hash' => str_repeat('a', 64),
            'plan_version' => 'v1',
            'plan_hash' => str_repeat('p', 64),
            'expected_vector_hash' => str_repeat('e', 64),
            'required_page_count' => 1,
        ]);
        $summaryItems = [];
        for ($area = 1; $area <= 6; $area++) {
            for ($question = 1; $question <= 25; $question++) {
                $state = match ($question) {
                    1 => OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    2 => OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    default => OmrTemplateVerificationItemEvidence::STATE_MATCH,
                };
                $detected = $state === OmrTemplateVerificationItemEvidence::STATE_MATCH ? 'A' : ($state === OmrTemplateVerificationItemEvidence::STATE_MISMATCH ? 'B' : null);
                $optionRegions = array_map(
                    static fn (string $code, int $index): array => [
                        'code' => $code,
                        'region' => [
                            'x' => 0.04 + (($area - 1) * 0.15) + ($index * 0.02),
                            'y' => 0.2 + (($question - 1) * 0.02),
                            'w' => 0.015,
                            'h' => 0.012,
                        ],
                    ],
                    ['A', 'B', 'C', 'D'],
                    range(0, 3),
                );
                $summaryItems[] = [
                    'position' => (($area - 1) * 25) + $question,
                    'area_code' => 'AREA_'.$area,
                    'question_no' => $question,
                    'native_label' => 'AREA_'.$area.'_Q_'.$question,
                    'page_no' => 1,
                    'region' => [
                        'x' => $optionRegions[0]['region']['x'],
                        'y' => $optionRegions[0]['region']['y'],
                        'w' => 0.075,
                        'h' => 0.012,
                    ],
                    'options' => $optionRegions,
                    'detected' => [
                        'state' => $state === OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS ? 'ambiguous' : 'marked',
                        'selected_option' => $detected,
                        'confidence' => null,
                    ],
                    'expected' => $question === 2 ? null : 'A',
                ];
            }
        }
        $capture = OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'method_id' => $method->id,
            'method' => $methodName,
            'medium' => $methodName === OmrTemplateVerificationMethod::METHOD_HAND_SHADED
                ? OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL
                : null,
            'attempt' => $method->id,
            'attempt_no' => 1,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => $draft->id,
            'target_version' => 'v1',
            'target_hash' => str_repeat('a', 64),
            'plan_version' => 'v1',
            'plan_hash' => str_repeat('p', 64),
            'page_manifest_hash' => str_repeat('h', 64),
            'challenge_hash' => str_repeat('c', 64),
            'expected_vector_hash' => str_repeat('e', 64),
            'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
            'page_no' => 1,
            'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
            'expected' => 'pass',
            'actual' => 'pass',
            'status' => OmrTemplateVerificationCapture::STATUS_PASS,
            'input_hash' => str_repeat('i', 64),
            'evidence_hash' => hash('sha256', $methodName),
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
            'summary' => ['evidence' => ['items' => $summaryItems]],
        ]);
        OmrTemplateVerificationMethodPage::query()->create([
            'method_id' => $method->id,
            'page_no' => 1,
            'required' => true,
            'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
            'selected_attempt_id' => $capture->id,
        ]);
        for ($area = 1; $area <= 6; $area++) {
            $areaKey = 'AREA_'.$area;
            for ($question = 1; $question <= 25; $question++) {
                $state = match ($question) {
                    1 => OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    2 => OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    default => OmrTemplateVerificationItemEvidence::STATE_MATCH,
                };
                $itemId = $areaKey.'_Q_'.$question;
                $payload = [
                    'capture_id' => $capture->id,
                    'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
                    'area_key' => $areaKey,
                    'area_position' => $area,
                    'question_no' => $question,
                    'item_id' => $itemId,
                    'options' => [['code' => 'A'], ['code' => 'B'], ['code' => 'C'], ['code' => 'D']],
                    'expected' => $question === 2 ? null : 'A',
                    'detected' => $state === OmrTemplateVerificationItemEvidence::STATE_MATCH ? 'A' : ($state === OmrTemplateVerificationItemEvidence::STATE_MISMATCH ? 'B' : null),
                    'state' => $state,
                    'means' => ['A' => 10.0, 'B' => 200.0],
                    'delta' => 1.0,
                    'status' => $state,
                    'evidence_version' => 'v1',
                    'row_version' => 0,
                ];
                $payload['evidence_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                OmrTemplateVerificationItemEvidence::query()->create($payload);
            }
        }
        $captures[$methodName] = $capture;
    }

    return compact('actor', 'run', 'captures');
}

test('projects both methods with complete six-area pagination and stable filters', function (): void {
    $fixture = evidenceQueryFixture();
    $query = app(OmrEvidenceQuery::class);

    foreach ($fixture['captures'] as $method => $capture) {
        $all = $query->paginate($fixture['run'], $fixture['actor'], [
            'method' => $method,
            'capture_id' => $capture->id,
            'area' => 'all',
            'filter' => 'all',
            'page' => 1,
            'per_page' => 25,
        ]);
        expect($all->total)->toBe(150)
            ->and($all->mismatchTotal)->toBe(6)
            ->and($all->items)->toHaveCount(25)
            ->and($all->items[0]['item_id'])->toBe('AREA_1_Q_1')
            ->and($all->items[24]['item_id'])->toBe('AREA_1_Q_25')
            ->and($all->items[0])->not->toHaveKeys(['provider_json', 'score', 'base64', 'bytes'])
            ->and($all->sheetMap['items'])->toHaveCount(150)
            ->and($all->sheetMap['items'][0]['geometry']['options'])->toHaveCount(4)
            ->and($all->sheetMap['items'][0]['geometry']['geometry_manifest_hash'])->toBe(str_repeat('h', 64))
            ->and($all->sheetMap['items'][0]['geometry']['evidence_version'])->toBe('v1');

        $pageTwo = $query->paginate($fixture['run'], $fixture['actor'], [
            'method' => $method,
            'capture_id' => $capture->id,
            'area' => 'all',
            'filter' => 'all',
            'page' => 2,
            'per_page' => 25,
        ]);
        expect($pageTwo->items[0]['item_id'])->toBe('AREA_2_Q_1')
            ->and($pageTwo->items)->toHaveCount(25);

        $area = $query->paginate($fixture['run'], $fixture['actor'], [
            'method' => $method,
            'capture_id' => $capture->id,
            'area' => 'AREA_6',
            'filter' => 'all',
            'page' => 1,
            'per_page' => 50,
        ]);
        expect($area->total)->toBe(25)->and($area->pageCount)->toBe(1)->and($area->items)->toHaveCount(25);

        $mismatches = $query->paginate($fixture['run'], $fixture['actor'], [
            'method' => $method,
            'capture_id' => $capture->id,
            'area' => 'all',
            'filter' => 'mismatch',
            'page' => 1,
            'per_page' => 50,
        ]);
        expect($mismatches->total)->toBe(150)
            ->and($mismatches->mismatchTotal)->toBe(6)
            ->and($mismatches->items)->toHaveCount(6);
    }
});

test('evidence query authorizes before resolving immutable rows', function (): void {
    $fixture = evidenceQueryFixture();
    $outsider = User::factory()->create();

    expect(fn () => app(OmrEvidenceQuery::class)->paginate(
        $fixture['run'],
        $outsider,
        ['method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED, 'capture_id' => $fixture['captures']['pre_shaded']->id],
    ))->toThrow(AuthorizationException::class);
});

test('grading evidence projects answer keys and compares detected marks', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->firstOrFail();
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $fixture['grading']->id,
        'template_version_id' => $pair->template_version_id,
        'booklet_version_id' => $pair->booklet_version_id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 0,
        'idempotency_key' => 'grading-evidence-'.uniqid('', true),
        'uploaded_by_user_id' => $fixture['guidance']->id,
        'opened_by_user_id' => $fixture['guidance']->id,
        'opened_at' => now(),
    ]);
    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 1,
        'client_capture_id' => 'grading-evidence-sheet',
        'proposal_json' => [
            'items' => [
                ['position' => 1, 'question_no' => 1, 'area_code' => 'verbal', 'correct_option_code' => 'A', 'detected_answer' => 'A', 'state' => 'marked'],
                ['position' => 2, 'question_no' => 2, 'area_code' => 'verbal', 'correct_option_code' => 'B', 'detected_answer' => 'C', 'state' => 'marked'],
            ],
        ],
        'captured_by_user_id' => $fixture['guidance']->id,
        'captured_at' => now(),
    ]);

    $page = app(OmrEvidenceQuery::class)->paginateGradingSheet(
        $sheet,
        $fixture['guidance'],
        filter: 'all',
    );

    expect($page->mismatchTotal)->toBe(1)
        ->and($page->items[0]['machine']['selected_option'])->toBe('A')
        ->and($page->items[0]['expected']['option'])->toBe('A')
        ->and($page->items[0]['effective']['selected_option'])->toBe('A')
        ->and($page->items[0]['effective']['source'])->toBe('machine')
        ->and($page->items[1]['machine']['selected_option'])->toBe('C')
        ->and($page->items[1]['expected']['option'])->toBe('B')
        ->and($page->items[1]['effective']['selected_option'])->toBe('C')
        ->and($page->items[1]['effective']['source'])->toBe('machine');
});

test('verification item projection returns complete named sections and dedicated crop transforms', function (): void {
    $fixture = evidenceQueryFixture();
    $capture = $fixture['captures']['pre_shaded'];
    $draft = $fixture['run']->draft;

    $alignedAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => 'omr/evidence/aligned-page-1.png',
        'mime' => 'image/png',
        'bytes' => 4096,
        'sha256' => str_repeat('1', 64),
        'metadata' => ['width' => 1200, 'height' => 1600, 'run_id' => $fixture['run']->id],
    ]);
    DB::table('omr_template_verification_captures')
        ->where('id', $capture->id)
        ->update([
            'aligned_asset_id' => $alignedAsset->id,
            'aligned_asset_hash' => $alignedAsset->sha256,
        ]);
    $capture->refresh();

    $cropAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => 'omr/evidence/crop-q1.png',
        'mime' => 'image/png',
        'bytes' => 512,
        'sha256' => str_repeat('2', 64),
        'metadata' => [
            'width' => 320,
            'height' => 80,
            'run_id' => $fixture['run']->id,
            'transform' => [
                'source_rect' => ['x' => 0.05, 'y' => 0.1, 'w' => 0.4, 'h' => 0.04],
                'rect' => ['x' => 0.05, 'y' => 0.1, 'w' => 0.4, 'h' => 0.04],
                'scale_x' => 1.25,
                'scale_y' => 1.25,
                'translate_x' => 0.0,
                'translate_y' => 0.0,
            ],
        ],
    ]);
    $cropRect = ['x' => 0.05, 'y' => 0.1, 'w' => 0.4, 'h' => 0.04];
    DB::table('omr_template_verification_item_evidence')
        ->where('capture_id', $capture->id)
        ->where('item_id', 'AREA_1_Q_1')
        ->update([
            'crop_asset_id' => $cropAsset->id,
            'crop_rect' => json_encode($cropRect),
        ]);

    $page = app(OmrEvidenceQuery::class)->paginate($fixture['run'], $fixture['actor'], [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 25,
    ]);

    $rowWithCrop = $page->items[0];
    expect($rowWithCrop['geometry']['crop_asset'])->not->toBeNull()
        ->and($rowWithCrop['geometry']['crop_asset']['sha256'])->toBe(str_repeat('2', 64))
        ->and($rowWithCrop['geometry']['crop_source']['kind'])->toBe('dedicated_crop')
        ->and($rowWithCrop['geometry']['crop_source']['transform']['scale_x'])->toBe(1.25)
        ->and($rowWithCrop['provenance']['asset']['sha256'])->toBe(str_repeat('1', 64))
        ->and($rowWithCrop['provenance']['crop_source']['kind'])->toBe('dedicated_crop')
        ->and($rowWithCrop['effective']['state'])->toBe('not_applicable')
        ->and($rowWithCrop['effective']['source'])->toBe('none');

    $rowWithoutCrop = $page->items[1];
    expect($rowWithoutCrop['geometry']['crop_asset'])->toBeNull()
        ->and($rowWithoutCrop['geometry']['crop_source']['kind'])->toBe('aligned_page')
        ->and($rowWithoutCrop['provenance']['crop_source']['kind'])->toBe('aligned_page');
});

test('verification canonical filters segregate all, mismatch, needs_review, and reviewed without leaking resolved reviews', function (): void {
    $fixture = evidenceQueryFixture();
    $capture = $fixture['captures']['pre_shaded'];
    $query = app(OmrEvidenceQuery::class);

    $initialPage = $query->paginate($fixture['run'], $fixture['actor'], [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 50,
    ]);
    expect($initialPage->total)->toBe(150)
        ->and($initialPage->mismatchTotal)->toBe(6)
        ->and($initialPage->needsReviewTotal)->toBe(12)
        ->and($initialPage->reviewedTotal)->toBe(0);

    // Review item 2 (ambiguous mark) with set_option
    $ambiguousItem = OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->where('item_id', 'AREA_1_Q_2')
        ->firstOrFail();

    OmrItemReviewRevision::query()->create([
        'verification_item_evidence_id' => $ambiguousItem->id,
        'item_key' => $ambiguousItem->item_id,
        'revision' => 1,
        'decision' => OmrReviewDecision::SetOption,
        'selected_option' => 'A',
        'reason_code' => 'manual_clarification',
        'note' => 'Reviewer clarified ambiguous mark',
        'reviewed_machine_evidence_hash' => (string) $ambiguousItem->evidence_hash,
        'parent_version' => 1,
        'reviewed_by_user_id' => $fixture['actor']->id,
        'command_name' => 'test_review',
        'idempotency_key' => 'idem-1',
        'correlation_id' => (string) Str::uuid(),
    ]);

    $reviewedPage = $query->paginate($fixture['run'], $fixture['actor'], [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'reviewed',
        'page' => 1,
        'per_page' => 50,
    ]);
    expect($reviewedPage->filteredTotal)->toBe(1)
        ->and($reviewedPage->items)->toHaveCount(1)
        ->and($reviewedPage->items[0]['item_id'])->toBe('AREA_1_Q_2')
        ->and($reviewedPage->items[0]['review']['decision'])->toBe('set_option')
        ->and($reviewedPage->items[0]['review']['selected_option'])->toBe('A')
        ->and($reviewedPage->items[0]['provenance']['review']['status'])->toBe('current');

    // Needs review page now excludes the resolved item
    $needsReviewPage = $query->paginate($fixture['run'], $fixture['actor'], [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'needs_review',
        'page' => 1,
        'per_page' => 50,
    ]);
    expect($needsReviewPage->filteredTotal)->toBe(11)
        ->and(array_column($needsReviewPage->items, 'item_id'))->not->toContain('AREA_1_Q_2');

    // Item with unresolved review continues to need review
    $unresolvedItem = OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->where('item_id', 'AREA_2_Q_2')
        ->firstOrFail();
    OmrItemReviewRevision::query()->create([
        'verification_item_evidence_id' => $unresolvedItem->id,
        'item_key' => $unresolvedItem->item_id,
        'revision' => 1,
        'decision' => OmrReviewDecision::Unresolved,
        'selected_option' => null,
        'reason_code' => 'cannot_determine',
        'note' => 'Unclear smudge',
        'reviewed_machine_evidence_hash' => (string) $unresolvedItem->evidence_hash,
        'parent_version' => 1,
        'reviewed_by_user_id' => $fixture['actor']->id,
        'command_name' => 'test_review',
        'idempotency_key' => 'idem-2',
        'correlation_id' => (string) Str::uuid(),
    ]);

    $needsReviewAfterUnresolved = $query->paginate($fixture['run'], $fixture['actor'], [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'needs_review',
        'page' => 1,
        'per_page' => 50,
    ]);
    expect($needsReviewAfterUnresolved->filteredTotal)->toBe(11)
        ->and(array_column($needsReviewAfterUnresolved->items, 'item_id'))->toContain('AREA_2_Q_2');

    // Stale review detection
    $staleItem = OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->where('item_id', 'AREA_3_Q_2')
        ->firstOrFail();
    OmrItemReviewRevision::query()->create([
        'verification_item_evidence_id' => $staleItem->id,
        'item_key' => $staleItem->item_id,
        'revision' => 1,
        'decision' => OmrReviewDecision::SetOption,
        'selected_option' => 'C',
        'reason_code' => 'manual_clarification',
        'note' => 'Old review before re-analysis',
        'reviewed_machine_evidence_hash' => str_repeat('f', 64),
        'parent_version' => 1,
        'reviewed_by_user_id' => $fixture['actor']->id,
        'command_name' => 'test_review',
        'idempotency_key' => 'idem-3',
        'correlation_id' => (string) Str::uuid(),
    ]);

    $allPage = $query->paginate($fixture['run'], $fixture['actor'], [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'AREA_3',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 25,
    ]);
    $staleRow = collect($allPage->items)->firstWhere('item_id', 'AREA_3_Q_2');
    expect($staleRow['review']['stale'])->toBeTrue()
        ->and($staleRow['issues'])->toContain('stale_review')
        ->and($staleRow['provenance']['review']['status'])->toBe('stale');
});

test('grading context projects derived effective answers and handles review decisions', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $pair = OmrTemplateBookletPair::query()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->firstOrFail();

    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $fixture['grading']->id,
        'template_version_id' => $pair->template_version_id,
        'booklet_version_id' => $pair->booklet_version_id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 0,
        'idempotency_key' => 'grading-evidence-review-'.uniqid('', true),
        'uploaded_by_user_id' => $guidance->id,
        'opened_by_user_id' => $guidance->id,
        'opened_at' => now(),
    ]);

    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 1,
        'client_capture_id' => 'grading-sheet-decisions',
        'proposal_json' => [
            'items' => [
                ['position' => 1, 'question_no' => 1, 'area_code' => 'verbal', 'correct_option_code' => 'A', 'detected_answer' => 'A', 'state' => 'marked'],
                ['position' => 2, 'question_no' => 2, 'area_code' => 'verbal', 'correct_option_code' => 'B', 'detected_answer' => null, 'state' => 'ambiguous', 'observed_options' => ['B', 'C']],
                ['position' => 3, 'question_no' => 3, 'area_code' => 'verbal', 'correct_option_code' => 'C', 'detected_answer' => null, 'state' => 'blank'],
            ],
        ],
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);

    $query = app(OmrEvidenceQuery::class);

    // Initial projection before human reviews
    $initial = $query->paginateGradingSheet($sheet, $guidance, filter: 'all');
    expect($initial->items[0]['effective'])->toBe(['state' => 'selected', 'selected_option' => 'A', 'source' => 'machine'])
        ->and($initial->items[1]['effective'])->toBe(['state' => 'unresolved', 'selected_option' => null, 'source' => 'none'])
        ->and($initial->items[2]['effective'])->toBe(['state' => 'blank', 'selected_option' => null, 'source' => 'machine']);

    // Append human review: set_option on ambiguous question 2
    $q2Hash = (string) $initial->items[1]['machine']['evidence_hash'];
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => 'VERBAL_Q_2',
        'revision' => 1,
        'decision' => OmrReviewDecision::SetOption,
        'selected_option' => 'B',
        'reason_code' => 'manual_clarification',
        'note' => 'Darker pencil mark',
        'reviewed_machine_evidence_hash' => $q2Hash,
        'parent_version' => 2,
        'reviewed_by_user_id' => $guidance->id,
        'command_name' => 'test_review',
        'idempotency_key' => 'idem-q2',
        'correlation_id' => (string) Str::uuid(),
    ]);
    $sheet->update(['version' => 2]);

    $reviewed = $query->paginateGradingSheet($sheet, $guidance, filter: 'all');
    expect($reviewed->items[1]['effective'])->toBe(['state' => 'selected', 'selected_option' => 'B', 'source' => 'review'])
        ->and($reviewed->items[1]['review']['decision'])->toBe('set_option')
        ->and($reviewed->items[1]['provenance']['review']['status'])->toBe('current');
});

test('stale geometry in grading context sets typed issues and fail-closed state', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $pair = OmrTemplateBookletPair::query()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->firstOrFail();

    // Invalidate booklet geometry by clearing questions
    $booklet = $pair->bookletVersion;
    $booklet->questions()->delete();

    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $fixture['grading']->id,
        'template_version_id' => $pair->template_version_id,
        'booklet_version_id' => $booklet->id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 0,
        'idempotency_key' => 'grading-stale-geometry-'.uniqid('', true),
        'uploaded_by_user_id' => $guidance->id,
        'opened_by_user_id' => $guidance->id,
        'opened_at' => now(),
    ]);

    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 1,
        'client_capture_id' => 'grading-stale-sheet',
        'proposal_json' => [
            'items' => [
                ['position' => 1, 'question_no' => 1, 'area_code' => 'verbal', 'detected_answer' => 'A', 'state' => 'marked'],
            ],
        ],
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);

    $page = app(OmrEvidenceQuery::class)->paginateGradingSheet($sheet, $guidance, filter: 'all');
    expect($page->items[0]['geometry']['aligned_asset'])->toBeNull()
        ->and($page->items[0]['issues'])->toContain('geometry_stale')
        ->and($page->items[0]['provenance']['geometry']['status'])->toBe('stale');
});

test('cache versioning invalidates evidence cache when review revision is appended without invalidating machine assets', function (): void {
    $fixture = evidenceQueryFixture();
    $capture = $fixture['captures']['pre_shaded'];
    $query = app(OmrEvidenceQuery::class);
    $actor = $fixture['actor'];

    $firstPage = $query->paginate($fixture['run'], $actor, [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 25,
    ]);
    expect($firstPage->items[0]['review'])->toBeNull();

    // Review item 1
    $item = OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->where('item_id', 'AREA_1_Q_1')
        ->firstOrFail();

    OmrItemReviewRevision::query()->create([
        'verification_item_evidence_id' => $item->id,
        'item_key' => $item->item_id,
        'revision' => 1,
        'decision' => OmrReviewDecision::ConfirmMachine,
        'selected_option' => 'A',
        'reason_code' => null,
        'note' => null,
        'reviewed_machine_evidence_hash' => (string) $item->evidence_hash,
        'parent_version' => 1,
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'test_review',
        'idempotency_key' => 'cache-idem-1',
        'correlation_id' => (string) Str::uuid(),
    ]);
    $capture->update(['lock_version' => 1]);

    $secondPage = $query->paginate($fixture['run'], $actor, [
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
        'area' => 'all',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 25,
    ]);

    expect($secondPage->items[0]['review'])->not->toBeNull()
        ->and($secondPage->items[0]['review']['decision'])->toBe('confirm_machine')
        ->and($secondPage->items[0]['machine']['evidence_hash'])->toBe($firstPage->items[0]['machine']['evidence_hash'])
        ->and($capture->fresh()->evidence_hash)->toBe($firstPage->evidenceHash);
});

test('evidence query enforces bounded pagination and runs bounded queries without N+1', function (): void {
    $fixture = evidenceQueryFixture();
    $query = app(OmrEvidenceQuery::class);
    $actor = $fixture['actor'];

    expect(fn () => $query->paginate($fixture['run'], $actor, [
        'method' => 'pre_shaded',
        'capture_id' => $fixture['captures']['pre_shaded']->id,
        'per_page' => 51,
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $query->paginate($fixture['run'], $actor, [
        'method' => 'pre_shaded',
        'capture_id' => $fixture['captures']['pre_shaded']->id,
        'per_page' => 0,
    ]))->toThrow(InvalidArgumentException::class);

    $queryCounts = [];
    foreach ([25, 50] as $perPage) {
        $run = OmrTemplateVerificationRun::query()->findOrFail($fixture['run']->id);
        $iterationActor = $fixture['actor']->fresh();
        $capture = $fixture['captures']['pre_shaded'];
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $queries = [];

        try {
            $page = $query->paginate($run, $iterationActor, [
                'method' => 'pre_shaded',
                'capture_id' => $capture->id,
                'area' => 'all',
                'filter' => 'all',
                'page' => 1,
                'per_page' => $perPage,
            ]);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $queryCounts[$perPage] = count($queries);
        fwrite(STDOUT, sprintf("omr_evidence_query_count per_page=%d queries=%d\n", $perPage, $queryCounts[$perPage]));

        expect($page->items)->toHaveCount($perPage)
            ->and($queryCounts[$perPage])->toBeLessThanOrEqual(20);
    }

    expect($queryCounts[50])->toBe($queryCounts[25]);
});

test('private asset endpoints enforce capability scoping, run ownership, and immutable etags', function (): void {
    Storage::fake('local');
    $fixture = evidenceQueryFixture();
    $actor = $fixture['actor'];
    $run = $fixture['run'];
    $draft = $run->draft;

    $bytes = 'dummy-png-evidence-content';
    $sha256 = hash('sha256', $bytes);
    $path = 'omr/evidence/test-asset.png';
    Storage::disk('local')->put($path, $bytes);

    $asset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => $path,
        'mime' => 'image/png',
        'bytes' => strlen($bytes),
        'sha256' => $sha256,
        'metadata' => ['run_id' => $run->id],
    ]);

    // Authorized request with matching sha256 gets 200 with immutable headers
    $url = route('setup.omr.templates.canonical.verification_runs.assets.show', [
        'run' => $run->id,
        'asset' => $asset->id,
        'sha256' => $sha256,
    ]);

    $response = $this->actingAs($actor)->get($url);
    $response->assertStatus(200);
    $response->assertHeader('ETag', '"'.$sha256.'"');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('immutable');

    // Conditional request with matching If-None-Match gets 304
    $revalidate = $this->actingAs($actor)->withHeaders(['If-None-Match' => '"'.$sha256.'"'])->get($url);
    $revalidate->assertStatus(304);

    // Mismatched sha256 parameter gets 404
    $wrongHashUrl = route('setup.omr.templates.canonical.verification_runs.assets.show', [
        'run' => $run->id,
        'asset' => $asset->id,
        'sha256' => str_repeat('0', 64),
    ]);
    $this->actingAs($actor)->get($wrongHashUrl)->assertStatus(404);

    // Unauthorized user gets 403
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get($url)->assertStatus(403);
    // A content mutation behind an immutable URL is rejected rather than served.
    Storage::disk('local')->put($path, 'tampered-evidence-content');
    $this->actingAs($actor)->get($url)->assertStatus(404);

    $foreignDraft = OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => Institution::current()->id,
        'is_synthetic' => true,
        'code' => 'FOREIGN-ASSET-'.uniqid(),
        'name' => 'Foreign asset draft',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_IMAGE,
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => [],
        'owner_user_id' => $actor->id,
    ]);
    $foreignRun = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $foreignDraft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'started_by_user_id' => $actor->id,
    ]);
    $foreignBytes = 'foreign-run-evidence-content';
    $foreignHash = hash('sha256', $foreignBytes);
    $foreignPath = 'omr/evidence/foreign-run-asset.png';
    Storage::disk('local')->put($foreignPath, $foreignBytes);
    $foreignAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $foreignDraft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => $foreignPath,
        'mime' => 'image/png',
        'bytes' => strlen($foreignBytes),
        'sha256' => $foreignHash,
        'metadata' => ['run_id' => $foreignRun->id],
    ]);
    $foreignUrl = route('setup.omr.templates.canonical.verification_runs.assets.show', [
        'run' => $run->id,
        'asset' => $foreignAsset->id,
        'sha256' => $foreignHash,
    ]);

    // A valid asset in another verification run cannot cross the run boundary.
    $this->actingAs($actor)->get($foreignUrl)->assertStatus(404);
});

test('verification evidence cache key and etag invalidate when asset metadata dimensions are repaired without changing capture hash', function (): void {
    $fixture = evidenceQueryFixture();
    $actor = $fixture['actor'];
    $run = $fixture['run'];
    $draft = $run->draft;
    $capture = $fixture['captures']['pre_shaded'];

    $alignedAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => 'omr/evidence/aligned-page-1.png',
        'mime' => 'image/png',
        'bytes' => 4096,
        'sha256' => str_repeat('1', 64),
        'metadata' => [
            'run_id' => $run->id,
            'width' => null,
            'height' => null,
        ],
    ]);

    DB::table('omr_template_verification_captures')
        ->where('id', $capture->id)
        ->update([
            'aligned_asset_id' => $alignedAsset->id,
            'aligned_asset_hash' => $alignedAsset->sha256,
        ]);
    $capture->refresh();

    $initialEvidenceHash = (string) $capture->evidence_hash;
    $initialEvidenceVersion = (string) ($capture->evidence_version ?: 'v1');

    $evidenceUrl = route('setup.omr.templates.canonical.verification_runs.evidence', [
        'run' => $run->id,
        'method' => 'pre_shaded',
        'capture_id' => $capture->id,
    ]);

    // First request captures initial ETag and initial dimensions state
    $initialResponse = $this->actingAs($actor)->getJson($evidenceUrl);
    $initialResponse->assertStatus(200);

    $initialEtag = (string) $initialResponse->headers->get('ETag');
    expect($initialEtag)->not->toBeEmpty()
        ->and($initialResponse->json('evidence_hash'))->toBe($initialEvidenceHash)
        ->and($initialResponse->json('evidence_version'))->toBe($initialEvidenceVersion)
        ->and($initialResponse->json('items.0.geometry.aligned_asset.width'))->toBeNull()
        ->and($initialResponse->json('items.0.geometry.aligned_asset.height'))->toBeNull();

    // Conditional request against unchanged representation returns 304
    $this->actingAs($actor)
        ->withHeaders(['If-None-Match' => $initialEtag])
        ->getJson($evidenceUrl)
        ->assertStatus(304);

    // Mutate aligned asset metadata dimensions (repairing missing/null dimensions)
    DB::table('omr_template_assets')
        ->where('id', $alignedAsset->id)
        ->update([
            'metadata' => json_encode([
                'run_id' => $run->id,
                'width' => 1200,
                'height' => 1600,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);

    // Assert capture evidence_hash and evidence_version remain unchanged across metadata repair
    $freshCapture = $capture->fresh();
    expect((string) $freshCapture->evidence_hash)->toBe($initialEvidenceHash)
        ->and((string) ($freshCapture->evidence_version ?: 'v1'))->toBe($initialEvidenceVersion);

    // Request with old If-None-Match must return 200 (not 304) with a different ETag and repaired dimensions
    $repairedResponse = $this->actingAs($actor)
        ->withHeaders(['If-None-Match' => $initialEtag])
        ->getJson($evidenceUrl);

    $repairedResponse->assertStatus(200);

    $repairedEtag = (string) $repairedResponse->headers->get('ETag');
    expect($repairedEtag)->not->toBeEmpty()
        ->and($repairedEtag)->not->toBe($initialEtag)
        ->and($repairedResponse->json('evidence_hash'))->toBe($initialEvidenceHash)
        ->and($repairedResponse->json('evidence_version'))->toBe($initialEvidenceVersion)
        ->and($repairedResponse->json('items.0.geometry.aligned_asset.width'))->toBe(1200)
        ->and($repairedResponse->json('items.0.geometry.aligned_asset.height'))->toBe(1600)
        ->and($repairedResponse->json('items.0.provenance.asset.width'))->toBe(1200)
        ->and($repairedResponse->json('items.0.provenance.asset.height'))->toBe(1600);

    // Follow-up conditional request with the new ETag returns 304
    $this->actingAs($actor)
        ->withHeaders(['If-None-Match' => $repairedEtag])
        ->getJson($evidenceUrl)
        ->assertStatus(304);
});

test('identity evidence reuses pinned bubble geometry and hides calibration rows', function (): void {
    $fixture = evidenceQueryFixture();
    $capture = $fixture['captures'][OmrTemplateVerificationMethod::METHOD_PRE_SHADED];
    $fields = array_map(
        static fn (int $digit): array => [
            'page' => 1,
            'label' => 'APPLICANT_NUMBER_1',
            'option' => (string) $digit,
            'region' => [
                'x' => 0.75,
                'y' => 0.08 + ($digit * 0.02),
                'w' => 0.02,
                'h' => 0.015,
            ],
        ],
        range(0, 9),
    );
    $fixture['run']->draft()->update([
        'authoring_json' => [
            'canonical' => [
                'settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297],
                'artifact_set' => ['review_manifest' => ['fields' => $fields]],
            ],
            'studio' => ['components' => [[
                'id' => 'candidate-name',
                'type' => 'candidate_name',
                'label' => 'Full name',
                'posX' => 16,
                'posY' => 34,
                'w_mm' => 88,
                'h_mm' => 18,
            ]]],
        ],
    ]);
    foreach ([
        ['group' => OmrTemplateVerificationItemEvidence::GROUP_IDENTITY, 'item' => 'APPLICANT_NUMBER_1'],
        ['group' => OmrTemplateVerificationItemEvidence::GROUP_CALIBRATION, 'item' => 'CALIBRATION_1'],
    ] as $row) {
        OmrTemplateVerificationItemEvidence::query()->create([
            'capture_id' => $capture->id,
            'group_type' => $row['group'],
            'question_no' => 1,
            'item_id' => $row['item'],
            'options' => array_map(static fn (int $digit): array => ['code' => (string) $digit], range(0, 9)),
            'expected' => '4',
            'detected' => '7',
            'state' => OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
            'status' => OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
            'crop_rect' => ['x' => 0.75, 'y' => 0.08, 'w' => 0.02, 'h' => 0.195],
            'evidence_version' => 'v1',
            'evidence_hash' => hash('sha256', $row['item']),
            'row_version' => 0,
        ]);
    }
    $summary = (array) $capture->summary;
    $summary['evidence']['identity'] = [['position' => 1]];
    DB::table('omr_template_verification_captures')
        ->where('id', $capture->id)
        ->update(['summary' => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
    Cache::flush();

    $page = app(OmrEvidenceQuery::class)->paginate($fixture['run']->fresh(), $fixture['actor'], [
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'capture_id' => $capture->id,
        'area' => 'identity',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 25,
    ]);

    expect($page->total)->toBe(1)
        ->and(array_column($page->groups, 'key'))->not->toContain('calibration')
        ->and($page->items[0]['geometry']['options'])->toHaveCount(10)
        ->and($page->items[0]['issues'])->not->toContain('missing_option_geometry')
        ->and($page->sheetMap['items'])->toHaveCount(151)
        ->and($page->sheetMap['document_fields'])->toHaveCount(1)
        ->and($page->sheetMap['document_fields'][0]['transcription'])->toBeFalse();

    $authoring = (array) $fixture['run']->draft->fresh()->authoring_json;
    $authoring['studio']['document_fields'] = [[
        'id' => 'birthdate-field',
        'type' => 'birthdate',
        'label' => 'Birthdate',
        'page_no' => 1,
        'x_mm' => 16,
        'y_mm' => 58,
        'w_mm' => 40,
        'h_mm' => 12,
    ]];
    $fixture['run']->draft->update(['authoring_json' => $authoring]);
    $updatedPage = app(OmrEvidenceQuery::class)->paginate($fixture['run']->fresh(), $fixture['actor'], [
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'capture_id' => $capture->id,
        'area' => 'identity',
        'filter' => 'all',
        'page' => 1,
        'per_page' => 25,
    ]);

    expect($updatedPage->sheetMap['document_fields'])->toHaveCount(2)
        ->and(array_column($updatedPage->sheetMap['document_fields'], 'id'))->toContain('birthdate-field');
});

test('grading evidence renders exact frozen Kit geometry for a persisted null-area Set capture', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    require_once __DIR__.'/../../Support/TestKitSoftwareAcceptanceFixtures.php';
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::current();
    $kitFixture = testKitAcceptanceFixture($actor, $institution);
    $accepted = testKitAcceptedSoftwareFixture($kitFixture, $actor, $institution);
    $version = $accepted['fixture']['version']->fresh([
        'templateVersion',
        'bookletVersion',
        'bookletSet',
        'testInstrumentVersion',
    ]);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'OMR-KIT-EVIDENCE-'.Str::upper(Str::random(8)),
        now()->subHours(2)->toDateTimeString(),
        now()->addHour()->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        $version->bookletVersion,
        null,
        null,
        $version->testInstrumentVersion,
        (int) $version->id,
    );
    $attemptData = submitSyntheticApplicant(
        'omr-kit-evidence-'.Str::lower(Str::random(8)).'@example.test',
        'OMR Kit Evidence',
    );
    $attempt = app(AcceptAdmissionAttempt::class)->handle(
        $attemptData['attempt'],
        $actor,
        1,
    );
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);
    $session = app(PublishExamSession::class)->handle(
        $session->fresh(),
        $actor,
        $session->fresh()->version,
    );
    $session = app(StartExamSession::class)->handle(
        $session->fresh(),
        $actor,
        $session->fresh()->version,
    );
    $assignment = $assignment->fresh();
    app(RecordExamAttendance::class)->handle(
        $assignment,
        $actor,
        ExamAttendanceRecord::OUTCOME_PRESENT,
        'omr-kit-evidence-attendance-'.$assignment->id,
    );
    app(RecordExamSubmission::class)->handle(
        $assignment->fresh(),
        $actor,
        'omr-kit-evidence-submission-'.$assignment->id,
    );
    $this->actingAs($actor)->post(route('exams.sessions.close', $session->fresh()), [
        'expected_version' => $session->fresh()->version,
    ])->assertRedirect();
    $gradingSession = GradingSession::query()
        ->where('exam_session_id', $session->id)
        ->firstOrFail();
    // Persist a labelled historical exact material/seat/admin chain; session publication intentionally remains logistics-only.
    $materialSnapshot = ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $session->id,
        'revision' => 1,
        'is_current' => true,
        'template_version_id' => $version->template_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $accepted['receipt']->id,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'capacity_limit' => (int) $room->capacity,
        'required_set_codes' => ['A'],
        'scoring_policy_identity' => 'historical-kit-evidence-fixture',
        'material_hash' => hash('sha256', 'historical-kit-evidence-fixture'),
        'published_by_user_id' => $actor->id,
        'published_at' => now(),
    ]);
    $setItems = $version->bookletSet->items()->orderBy('global_position')->get();
    $materialSet = $materialSnapshot->sets()->create([
        'booklet_set_id' => $version->booklet_set_id,
        'set_code' => $version->bookletSet->set_code,
        'order_hash' => $version->bookletSet->order_hash,
        'answer_key_hash' => $version->bookletSet->answer_key_hash,
        'answer_key_json' => $setItems->map(static fn ($item): array => [
            'position' => (int) $item->global_position,
            'booklet_item_id' => (int) $item->booklet_item_id,
            'correct_option_code' => (string) $item->correct_option_code,
        ])->values()->all(),
    ]);
    $sessionSeat = ExamSessionSeat::query()->create([
        'material_snapshot_id' => $materialSnapshot->id,
        'ordinal' => 1,
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => 'HISTORICAL-KIT-SEAT-1',
        'booklet_set_id' => $version->booklet_set_id,
    ]);
    $seatAllocation = ExamAssignmentSeatAllocation::query()->create([
        'exam_assignment_id' => $assignment->id,
        'exam_session_seat_id' => $sessionSeat->id,
        'effective_from' => now()->subHour(),
        'reason' => 'historical exact Kit evidence fixture',
        'assigned_by_user_id' => $actor->id,
    ]);
    $assignment->forceFill(['legacy_seat_allocation_status' => 'backfilled'])->save();
    ExamSubmissionRecord::query()->where('exam_assignment_id', $assignment->id)->firstOrFail()
        ->forceFill([
            'seat_allocation_id' => $seatAllocation->id,
            'legacy_seat_allocation_status' => ExamSubmissionRecord::LEGACY_SEAT_ALLOCATION_BACKFILLED,
        ])->save();
    AssessmentAdministration::query()->create([
        'exam_assignment_id' => $assignment->id,
        'admission_attempt_id' => $attempt->id,
        'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
        'binding_revision' => 1,
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $accepted['receipt']->id,
        'procedure_snapshot_json' => ['material_requirement' => 'paper_backed'],
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'material_snapshot_id' => $materialSnapshot->id,
        'exam_session_seat_id' => $sessionSeat->id,
        'material_set_id' => $materialSet->id,
        'booklet_set_id' => $version->booklet_set_id,
        'required_metric_set' => [],
        'binding_state' => AssessmentAdministration::STATE_READY,
        'bound_by_user_id' => $actor->id,
        'bound_at' => now(),
    ]);

    app(PrintOmrBlankInstances::class)->handle(
        $version->templateVersion,
        $actor,
        1,
        'omr-kit-evidence-print',
    );
    $printJob = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob((int) $printJob->id, (int) $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    $issuance = OmrQrDisabledIssuance::query()->firstOrFail();
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $gradingSession->id,
        'template_version_id' => $version->template_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 1,
        'opened_by_user_id' => $actor->id,
    ]);
    $captureSet = app(StartOmrPendingCaptureSet::class)->forProduction(
        $gradingSession,
        $batch,
        $issuance,
        $actor,
        (string) Str::uuid(),
    );
    $sheet = $batch->sheets()->create([
        'admission_attempt_id' => null,
        'booklet_set_id' => $version->booklet_set_id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'expected_page_count' => (int) $version->templateVersion->page_count,
        'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
        'version' => 0,
        'client_capture_id' => $captureSet->public_id,
        'detected_applicant_number' => $attempt->reference_code,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $captureSet->update([
        'status' => OmrPendingCaptureSet::STATUS_READY,
        'revision' => (int) $captureSet->revision + 1,
    ]);
    $captureSet->update([
        'adopted_sheet_id' => $sheet->id,
        'revision' => (int) $captureSet->revision + 1,
    ]);
    $kitContext = app(KitCompositionContext::class)->resolveVersion($version);
    $binding = array_reverse($kitContext['item_map']['bindings'])[0];
    $labelParts = [];
    preg_match('/^([A-Z0-9_]+)_Q_(\d+)$/', (string) $binding['native_label'], $labelParts);
    $hash = hash('sha256', 'omr-kit-evidence-page');
    $evidence = OmrPendingCapturePageEvidence::query()->create([
        'set_id' => $captureSet->id,
        'page_no' => (int) $binding['page_no'],
        'client_capture_id' => (string) Str::uuid(),
        'input_hash' => $hash,
        'asset_path' => 'omr/evidence/'.$captureSet->public_id.'.png',
        'mime_type' => 'image/png',
        'byte_count' => 1,
        'captured_by_user_id' => $actor->id,
    ]);
    OmrPendingCapturePageAnalysis::query()->create([
        'evidence_id' => $evidence->id,
        'detected_applicant_number' => $attempt->reference_code,
        'evidence_json' => ['input_hash' => $hash, 'page_no' => (int) $binding['page_no']],
    ]);
    $capturePage = $sheet->pages()->create([
        'page_no' => (int) $binding['page_no'],
        'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
        'asset_path' => $evidence->asset_path,
        'proposal_json' => ['input_hash' => $hash, 'page_no' => (int) $binding['page_no']],
    ]);
    OmrQrDisabledPageClaim::query()->create([
        'issuance_id' => $issuance->id,
        'set_id' => $captureSet->id,
        'evidence_id' => $evidence->id,
        'capture_sheet_page_id' => $capturePage->id,
        'page_no' => (int) $binding['page_no'],
        'input_hash' => $hash,
        'created_by_user_id' => $actor->id,
    ]);
    $sheet->update(['proposal_json' => [
        'items' => [[
            'position' => (int) $binding['global_position'],
            'page_no' => (int) $binding['page_no'],
            'area_code' => $labelParts[1],
            'question_no' => (int) $labelParts[2],
            'detected_answer' => $binding['correct_token'],
            'state' => 'marked',
        ]],
    ]]);

    $page = app(OmrEvidenceQuery::class)->paginateGradingSheet($sheet->fresh(), $actor, filter: 'all');

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]['provenance']['geometry']['status'])->toBe('available')
        ->and($page->items[0]['expected']['option'])->toBe($binding['correct_token'])
        ->and($page->items[0]['geometry']['region'])->toBeArray()
        ->and($page->items[0]['geometry']['options'])->not->toBeEmpty()
        ->and($captureSet->fresh()->adopted_sheet_id)->toBe($sheet->id)
        ->and($version->bookletSet->items->every(static fn ($item): bool => $item->aptitude_area_id === null))->toBeTrue();
});
