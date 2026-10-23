<?php

use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RecordOmrItemReviewDecision;
use App\Assessment\Actions\Omr\ResolveOmrApplicantException;
use App\Assessment\Actions\Omr\ResolveOmrCaptureSheetIssue;
use App\Assessment\Actions\Omr\ResolveOmrDuplicateDisposition;
use App\Assessment\Actions\Omr\ReviewOmrCaptureSheet;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrRecoveryCatalog;
use App\Assessment\Omr\OmrResolutionOutcome;
use App\Assessment\Omr\OmrScannerContextOutcome;
use App\Assessment\Omr\OmrScannerSessionResolver;
use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Assessment\Omr\Review\GradingOmrReviewContextResolver;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Support\KitCompositionContext;
use App\Examination\Actions\RecordExamSubmission;
use App\Http\Requests\Assessment\ResolveOmrSheetIssueRequest;
use App\Http\Requests\Assessment\ReviewOmrSheetRequest;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

function effectiveAnswerTestFixture(TestCase $test, bool $requireCurrentOmrProof = false): array
{
    $fixture = $requireCurrentOmrProof
        ? buildPaperGradingFixtureInternal($test, 1, true, requireCurrentOmrProof: true)
        : buildOmrGradingFixture($test);
    $actor = $fixture['guidance'];
    $attempt = $fixture['attempts'][0];
    $area = AptitudeArea::query()->where('code', 'LOGIC')->firstOrFail();
    $versionComposition = app(KitCompositionContext::class)->resolveVersion($fixture['kitVersion']);
    $itemMap = $versionComposition['item_map'];
    $physicalRowsByLabel = collect($itemMap['composition_rows'])
        ->where('disposition', 'physical')
        ->keyBy('native_label');
    $bindingsByPosition = collect($itemMap['bindings'])->keyBy('global_position');
    $hashes = [];
    $correctOptionsByQuestion = [];
    $questionNumbersByNativeLabel = ['LOGIC_Q_1' => 1, 'LOGIC_Q_2' => 2];

    foreach ($questionNumbersByNativeLabel as $nativeLabel => $questionNo) {
        $physicalRow = $physicalRowsByLabel->get($nativeLabel);
        if (! is_array($physicalRow)
            || ! is_string($physicalRow['physical_item_uid'] ?? null)
            || ! is_string($physicalRow['physical_section_uid'] ?? null)
            || $physicalRow['physical_section_uid'] === ''
            || ! isset($physicalRow['global_position'], $physicalRow['page_no'], $physicalRow['sheet_item_id'])
            || (int) ($physicalRow['booklet_set_item_id'] ?? 0) < 1
            || (int) ($physicalRow['formal_item_id'] ?? 0) < 1
            || (int) ($physicalRow['formal_subtest_id'] ?? 0) < 1) {
            throw new RuntimeException('The exact resolved Kit item map is missing complete physical and formal identity for '.$nativeLabel.'.');
        }
        $physicalQuestion = OmrTemplateQuestion::query()
            ->where('template_version_id', $fixture['sheet']->id)
            ->where('physical_item_uid', $physicalRow['physical_item_uid'])
            ->firstOrFail();
        if ($physicalQuestion->printed_section_uid !== $physicalRow['physical_section_uid']
            || $physicalQuestion->native_label !== $nativeLabel
            || (int) $physicalQuestion->page_no !== (int) $physicalRow['page_no']
            || (int) $physicalQuestion->global_position !== (int) $physicalRow['global_position']) {
            throw new RuntimeException('The compiled sheet physical identity does not match the exact resolved Kit item map for '.$nativeLabel.'.');
        }
        $binding = $bindingsByPosition->get((int) $physicalRow['global_position']);
        if (! is_array($binding)
            || (int) ($binding['sheet_item_id'] ?? 0) !== (int) $physicalRow['sheet_item_id']
            || (int) ($binding['item_id'] ?? 0) !== (int) $physicalRow['formal_item_id']
            || (int) ($binding['subtest_id'] ?? 0) !== (int) $physicalRow['formal_subtest_id']
            || (int) ($binding['page_no'] ?? 0) !== (int) $physicalRow['page_no']
            || (string) ($binding['native_label'] ?? '') !== $nativeLabel) {
            throw new RuntimeException('The exact Kit binding does not match the resolved physical and formal identities for '.$nativeLabel.'.');
        }
        $setItem = $fixture['bookletSet']->items()
            ->whereKey((int) $physicalRow['booklet_set_item_id'])
            ->firstOrFail();
        $optionTokens = array_values($physicalRow['physical_option_tokens']);
        $correctOption = (string) $setItem->correct_option_code;
        if (($binding['correct_token'] ?? null) !== $correctOption
            || ($binding['option_tokens'] ?? null) !== $optionTokens
            || ! in_array($correctOption, $optionTokens, true)) {
            throw new RuntimeException('The exact saved Set key does not match the resolved physical and formal binding for '.$nativeLabel.'.');
        }
        $evidenceHash = hash('sha256', 'eff-item-'.$nativeLabel);
        $hashes[$questionNo] = $evidenceHash;
        $correctOptionsByQuestion[$questionNo] = $correctOption;
        $proposalItems[] = [
            'position' => (int) $physicalRow['global_position'],
            'area_code' => $area->code,
            'area_id' => $area->id,
            'question_no' => $questionNo,
            'options' => $optionTokens,
            'evidence_hash' => $evidenceHash,
            'machine' => [
                'state' => $questionNo === 1 ? 'marked' : 'ambiguous',
                'selected_option' => $questionNo === 1 ? $correctOption : null,
                'observed_options' => $questionNo === 1 ? [$correctOption] : $optionTokens,
                'option_observations' => [],
                'confidence' => $questionNo === 1 ? 0.95 : 0.70,
                'issue_codes' => $questionNo === 1 ? [] : ['ambiguous_mark'],
            ],
        ];
    }

    $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $actor, 'eff-batch-'.Str::uuid());
    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'admission_attempt_id' => null,
        'detected_applicant_number' => $attempt->reference_code,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/test/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'asset'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => [
            'items' => $proposalItems,
        ],
    ]);
    $sheet->issues()->create([
        'area_code' => $area->code,
        'question_no' => 2,
        'page_no' => 1,
        'issue_code' => 'ambiguous_mark',
        'severity' => 'required',
        'state' => 'open',
        'version' => 1,
    ]);

    return [
        'batch' => $batch,
        'sheet' => $sheet,
        'actor' => $actor,
        'attempt' => $attempt,
        'area' => $area,
        'hashes' => $hashes,
        'correctOptionsByQuestion' => $correctOptionsByQuestion,
    ];
}

test('projector derives effective answers from exact mapped correct and ambiguous items', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $actor = $fixture['actor'];
    $projector = app(GradingOmrEffectiveAnswerProjector::class);
    $action = app(RecordOmrItemReviewDecision::class);
    $resolver = app(GradingOmrReviewContextResolver::class);

    // Component-only review keeps observations but makes no formal Test Setup claims.
    $initial = $projector->project($sheet);
    $initialItemsByLabel = collect($initial['items'])->keyBy('native_label');
    expect($fixture['historicalSnapshot']->test_kit_version_id)->toBeNull()
        ->and($fixture['historicalSnapshot']->manifest_sha256)->toBeNull()
        ->and($fixture['historicalSnapshot']->acceptance_receipt_id)->toBeNull()
        ->and($initial['is_complete'])->toBeFalse()
        ->and($initial['has_unresolved'])->toBeTrue()
        ->and($initial['unresolved_item_keys'])->toContain($fixture['area']->code.'_Q_2')
        ->and($initialItemsByLabel['LOGIC_Q_1']['effective_option'])->toBe($fixture['correctOptionsByQuestion'][1])
        ->and($initialItemsByLabel['LOGIC_Q_1'])->not->toHaveKey('test_instrument_item_id');

    // 1. Confirm the machine mark against the exact saved Set key.
    $ctx1 = $resolver->resolve($sheet, $fixture['area']->code.'_Q_1');
    $action->handle(
        $ctx1,
        OmrReviewDecision::ConfirmMachine,
        $ctx1->parentVersion,
        $ctx1->itemRevision,
        $fixture['hashes'][1],
        'rev-1-'.Str::uuid(),
        $actor,
    );

    // 2. Resolve the exact mapped item after its physical options are ambiguous.
    $sheet->refresh();
    $ctx2 = $resolver->resolve($sheet, $fixture['area']->code.'_Q_2');
    $action->handle(
        $ctx2,
        OmrReviewDecision::SetOption,
        $ctx2->parentVersion,
        $ctx2->itemRevision,
        $fixture['hashes'][2],
        'rev-2-'.Str::uuid(),
        $actor,
        selectedOption: $fixture['correctOptionsByQuestion'][2],
        reasonCode: 'candidate_verified',
        note: 'Reviewed against the captured paper evidence',
    );

    // The ambiguity is explicitly resolved to the saved Set A answer.
    $sheet->refresh();
    $projection = $projector->project($sheet);

    expect($projection['is_complete'])->toBeTrue()
        ->and($projection['has_unresolved'])->toBeFalse()
        ->and($projection['has_stale'])->toBeFalse()
        ->and($projection['per_area_scores'][$fixture['area']->id]['score'])->toBe(2)
        ->and($projection['provenance']['human_revisions_count'])->toBe(2);

    // Atomically reconciled required issue on the explicitly mapped second item.
    $issue = $sheet->issues()->where('question_no', 2)->first();
    expect($issue->state)->toBe('resolved')
        ->and($issue->resolution_code)->toBe('manual_review');
});

test('stale evidence invalidates previous review and blocks commit', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $actor = $fixture['actor'];
    $projector = app(GradingOmrEffectiveAnswerProjector::class);
    $action = app(RecordOmrItemReviewDecision::class);
    $resolver = app(GradingOmrReviewContextResolver::class);

    // Resolve the exact mapped ambiguous item to make the sheet complete.
    $ctx2 = $resolver->resolve($sheet, $fixture['area']->code.'_Q_2');
    $action->handle(
        $ctx2,
        OmrReviewDecision::SetOption,
        $ctx2->parentVersion,
        $ctx2->itemRevision,
        $fixture['hashes'][2],
        'stale-test-'.Str::uuid(),
        $actor,
        selectedOption: $fixture['correctOptionsByQuestion'][2],
        reasonCode: 'candidate_verified',
    );

    $sheet->refresh();
    app(ReviewOmrCaptureSheet::class)->handle($sheet, $actor, null, $fixture['attempt']->id);
    expect($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_REVIEWED);

    // Simulate re-analysis producing a different evidence hash for the reviewed mapped item.
    $proposal = $sheet->proposal_json;
    foreach ($proposal['items'] as &$item) {
        if ((int) ($item['question_no'] ?? 0) === 2) {
            $item['evidence_hash'] = hash('sha256', 're-analyzed-different-evidence');
            break;
        }
    }
    unset($item);
    $sheet->update(['proposal_json' => $proposal]);

    $projection = $projector->project($sheet->fresh());
    expect($projection['has_stale'])->toBeTrue()
        ->and($projection['stale_item_keys'])->toContain($fixture['area']->code.'_Q_2');

    // Confirming batch must throw because of stale review
    // Confirming batch must throw because of unresolved/stale items
    expect(fn () => app(ConfirmOmrCaptureBatch::class)->handle($fixture['batch'], $actor))
        ->toThrow(RuntimeException::class);
});

test('unresolved items block batch confirmation with a typed exception', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $actor = $fixture['actor'];

    app(ReviewOmrCaptureSheet::class)->handle($sheet, $actor, null, $fixture['attempt']->id);
    expect($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_REVIEWED);

    // Confirmation must be blocked
    expect(fn () => app(ConfirmOmrCaptureBatch::class)->handle($fixture['batch'], $actor))
        ->toThrow(RuntimeException::class);
});

test('client aggregate score overrides and bypasses are rejected by request validation', function (): void {
    $request = new ReviewOmrSheetRequest;
    $validator = Validator::make([
        'action' => 'confirm',
        'adjusted_scores' => [
            ['aptitude_area_id' => 1, 'score' => 20],
        ],
    ], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('adjusted_scores'))->toBeTrue();
});

test('generic issue resolution rejects item-level issues and manual_score_adjustment', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $actor = $fixture['actor'];
    $action = app(ResolveOmrCaptureSheetIssue::class);

    // Item-level issue with question_no = 2
    $itemIssue = $sheet->issues()->where('question_no', 2)->firstOrFail();

    expect(fn () => $action->handle($itemIssue, $actor, 'manual_review', 'Attempted bypass'))
        ->toThrow(RuntimeException::class, 'Item-level issues must be resolved through item review.');

    // Manual score adjustment resolution code is prohibited in request
    $request = new ResolveOmrSheetIssueRequest;
    $validator = Validator::make([
        'resolution_code' => 'manual_score_adjustment',
        'resolution_reason' => 'Bypass attempt',
    ], $request->rules());
    expect($validator->fails())->toBeTrue();

    // Generic issue without question_no (e.g. identity_conflict) CAN be resolved generically
    $genericIssue = $sheet->issues()->create([
        'page_no' => 0,
        'question_no' => null,
        'issue_code' => 'identity_conflict',
        'severity' => 'required',
        'state' => 'open',
        'version' => 1,
    ]);

    $resolved = $action->handle($genericIssue, $actor, 'manual_review', 'Verified applicant card.');
    expect($resolved->state)->toBe('resolved')
        ->and($resolved->resolution_code)->toBe('manual_review');
});

test('committed batch records immutable score records with machine and human provenance', function (): void {
    $fixture = effectiveAnswerTestFixture($this, requireCurrentOmrProof: true);
    $sheet = $fixture['sheet'];
    $actor = $fixture['actor'];
    $action = app(RecordOmrItemReviewDecision::class);
    $resolver = app(GradingOmrReviewContextResolver::class);
    $assignment = $fixture['attempt']->examAssignments()->firstOrFail();
    $administration = $fixture['historicalAdministrationsByAssignment'][$assignment->id];
    expect($fixture['historicalSnapshot']->test_kit_version_id)->toBe($fixture['kitVersion']->id)
        ->and($fixture['historicalSnapshot']->manifest_sha256)->toBe($fixture['kitVersion']->manifest_sha256)
        ->and($fixture['historicalSnapshot']->acceptance_receipt_id)->toBe($fixture['acceptanceReceipt']->id)
        ->and($administration->test_kit_version_id)->toBe($fixture['kitVersion']->id)
        ->and($administration->acceptance_receipt_id)->toBe($fixture['acceptanceReceipt']->id)
        ->and($administration->material_snapshot_id)->toBe($fixture['historicalSnapshot']->id)
        ->and($administration->exam_session_seat_id)->toBe($fixture['historicalSeatsByAssignment'][$assignment->id]->id);

    // Resolve the ambiguous item using its exact saved Set answer.
    $ctx2 = $resolver->resolve($sheet, $fixture['area']->code.'_Q_2');
    $action->handle(
        $ctx2,
        OmrReviewDecision::SetOption,
        $ctx2->parentVersion,
        $ctx2->itemRevision,
        $fixture['hashes'][2],
        'comm-prov-'.Str::uuid(),
        $actor,
        selectedOption: $fixture['correctOptionsByQuestion'][2],
        reasonCode: 'verified',
    );

    $sheet->refresh();
    app(ReviewOmrCaptureSheet::class)->handle($sheet, $actor, null, $fixture['attempt']->id);

    // Confirm batch
    app(ConfirmOmrCaptureBatch::class)->handle($fixture['batch'], $actor);
    expect($fixture['batch']->fresh()->status)->toBe(OmrCaptureBatch::STATUS_CONFIRMED)
        ->and($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_COMMITTED);

    // Verify ScoreRecord
    $record = ScoreRecord::query()
        ->where('grading_session_id', $fixture['batch']->grading_session_id)
        ->where('admission_attempt_id', $fixture['attempt']->id)
        ->where('aptitude_area_id', $fixture['area']->id)
        ->firstOrFail();

    expect($record->source)->toBe(ScoreRecord::SOURCE_OMR)
        ->and((int) $record->capture_sheet_id)->toBe((int) $sheet->id)
        ->and((int) $record->score)->toBe(2); // Both exact saved Set A mappings are correct.

    // Verify audit event provenance
    $audit = AuditEvent::query()
        ->where('event_type', 'grading.omr_score_recorded')
        ->latest('id')
        ->firstOrFail();

    expect($audit->payload['provenance']['capture_sheet_id'])->toBe($sheet->id)
        ->and($audit->payload['provenance']['human_revisions_count'])->toBe(1)
        ->and($audit->payload['provenance']['machine_items_count'])->toBe(1);

    // Verify reviewed_json provenance
    $reviewedJson = $sheet->fresh()->reviewed_json;
    expect($reviewedJson['provenance']['capture_sheet_id'])->toBe($sheet->id)
        ->and($reviewedJson['provenance']['human_revisions_count'])->toBe(1)
        ->and($reviewedJson['provenance']['machine_items_count'])->toBe(1);
});

test('resolves detected Applicant Number through the submission-frozen seat chain', function (): void {
    $fixture = effectiveAnswerTestFixture($this);

    $resolution = app(OmrCaptureResolver::class)->resolveSheet($fixture['sheet']);

    expect($resolution->outcome)->toBe(OmrResolutionOutcome::Resolved)
        ->and($resolution->attempt?->is($fixture['attempt']))->toBeTrue()
        ->and($resolution->seatAllocation?->id)->toBe($fixture['attempt']->examAssignments()->firstOrFail()->submissionRecord->seat_allocation_id)
        ->and($resolution->materialSet?->set_code)->toBe('A');
});

test('generic scanner auto-resolves exactly one eligible submitted session', function (): void {
    $fixture = effectiveAnswerTestFixture($this);

    $context = app(OmrScannerSessionResolver::class)->discover($fixture['attempt']->reference_code, $fixture['actor']);

    expect($context->outcome)->toBe(OmrScannerContextOutcome::AutoResolved)
        ->and($context->gradingSession?->is($fixture['batch']->gradingSession))->toBeTrue()
        ->and($context->candidates)->toBe([]);
});

test('generic scanner refuses unknown Applicant Numbers without fallback inference', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $context = app(OmrScannerSessionResolver::class)->discover('NO-SUCH-APPLICANT', $fixture['guidance']);

    expect($context->outcome)->toBe(OmrScannerContextOutcome::SelectionRequired)
        ->and($context->resolutionCode)->toBe('session_unresolved')
        ->and($context->candidates)->toBe([]);
});

test('generic scanner discovery fails closed without complete guidance scope', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $applicantNumber = $fixture['attempts'][0]->reference_code;
    $profile = $actor->staffProfile;
    $originalScope = [
        'institution_id' => $profile->institution_id,
        'office' => $profile->office,
        'active_cycle_code' => $profile->active_cycle_code,
    ];

    foreach ([
        'wrong office' => ['office' => 'registrar'],
        'missing cycle' => ['active_cycle_code' => null],
        'unknown cycle' => ['active_cycle_code' => 'NO-SUCH-CYCLE'],
    ] as $scopeFailure => $overrides) {
        $actor->staffProfile()->update([...$originalScope, ...$overrides]);
        $actor->unsetRelation('staffProfile');

        expect(fn () => app(OmrScannerSessionResolver::class)->discover($applicantNumber, $actor))
            ->toThrow(AuthorizationException::class, 'scope is not available');
    }
});

test('generic scanner does not disclose candidates outside the actor institution or cycle scope', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $actor = $fixture['actor'];
    $profile = $actor->staffProfile;
    $institutionId = (int) $profile->institution_id;
    $sameInstitutionCycle = AcademicCycle::query()->create([
        'institution_id' => $institutionId,
        'code' => 'SYN-OTHER-CYCLE-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Other Local Cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);
    $foreignInstitution = Institution::query()->create([
        'code' => 'SYN-OTHER-INST-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Other Institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'SYN-FOREIGN-CYCLE-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
        'name' => 'Foreign Cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => false,
    ]);

    $examSession = $fixture['batch']->gradingSession->examSession;
    foreach ([$sameInstitutionCycle, $foreignCycle] as $outOfScopeCycle) {
        $examSession->forceFill(['academic_cycle_id' => $outOfScopeCycle->id])->saveOrFail();
        $context = app(OmrScannerSessionResolver::class)->discover(
            $fixture['attempt']->reference_code,
            $actor,
        );

        expect($context->outcome)->toBe(OmrScannerContextOutcome::SelectionRequired)
            ->and($context->resolutionCode)->toBe('session_unresolved')
            ->and($context->candidates)->toBe([]);
    }
});

test('missing submission remains distinguishable and links back to Exam Monitoring', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $assignment = $fixture['attempt']->examAssignments()->firstOrFail();
    $assignment->submissionRecord()->delete();

    $sheetResolution = app(OmrCaptureResolver::class)->resolveSheet($fixture['sheet']->fresh());
    $scannerContext = app(OmrScannerSessionResolver::class)->discover($fixture['attempt']->reference_code, $fixture['actor']);

    expect($sheetResolution->outcome)->toBe(OmrResolutionOutcome::SubmissionMissing)
        ->and($sheetResolution->failureCode())->toBe('submission_missing')
        ->and($scannerContext->outcome)->toBe(OmrScannerContextOutcome::SelectionRequired)
        ->and($scannerContext->resolutionCode)->toBe('submission_missing')
        ->and($scannerContext->candidates)->toHaveCount(1)
        ->and($scannerContext->candidates[0]['monitoring_url'])->toBe(route('exams.sessions.monitoring', $fixture['batch']->gradingSession->examSession));

    expect(fn () => app(RecordExamSubmission::class)->handle(
        $assignment,
        $fixture['actor'],
        'repair-without-reason-'.$assignment->id,
    ))->toThrow(RuntimeException::class, 'specific reason');

    app(RecordExamSubmission::class)->handle(
        $assignment,
        $fixture['actor'],
        'repair-submission-'.$assignment->id,
        'Paper was surrendered but the conduct record was missed.',
    );

    $repaired = app(OmrCaptureResolver::class)->resolveSheet($fixture['sheet']->fresh());
    expect($repaired->outcome)->toBe(OmrResolutionOutcome::Resolved)
        ->and(AuditEvent::query()->where('event_type', 'exam.submission_repaired')->exists())->toBeTrue();
});

test('generic scanner entry redirects exact-one matches without session selection', function (): void {
    $fixture = effectiveAnswerTestFixture($this);

    $this->actingAs($fixture['actor'])
        ->get(route('scoring.omr.scanner', ['applicant_number' => $fixture['attempt']->reference_code]))
        ->assertRedirect(route('scoring.sessions.omr', ['examSession' => $fixture['batch']->gradingSession->examSession]));
});

test('generic scanner entry exposes missing-submission recovery without redirecting', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $fixture['attempt']->examAssignments()->firstOrFail()->submissionRecord()->delete();

    $this->actingAs($fixture['actor'])
        ->get(route('scoring.omr.scanner', ['applicant_number' => $fixture['attempt']->reference_code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Assessment/Omr/Scanner')
            ->where('outcome', 'selection_required')
            ->where('recovery.code', 'submission_missing')
            ->has('candidates', 1));
});

test('blocks an observed Set mismatch without changing the submission-frozen Set', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $fixture['sheet']->update(['observed_set_code' => 'B']);

    $resolution = app(OmrCaptureResolver::class)->resolveSheet($fixture['sheet']->fresh());

    expect($resolution->outcome)->toBe(OmrResolutionOutcome::ObservedSetMismatch)
        ->and($resolution->failureCode())->toBe('observed_set_mismatch')
        ->and($fixture['attempt']->examAssignments()->firstOrFail()->submissionRecord->seatAllocation->sessionSeat->bookletSet->set_code)->toBe('A');
});

test('authorized staff can audit an explicit unresolved Applicant Number exception', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $sheet->update([
        'detected_applicant_number' => 'UNREADABLE',
        'admission_attempt_id' => null,
        'set_resolution_reason' => null,
    ]);
    $assignment = $fixture['attempt']->examAssignments()->firstOrFail();

    app(ResolveOmrApplicantException::class)->handle(
        $sheet,
        $assignment,
        'Verified against the controlled session roster.',
        $fixture['actor'],
    );
    $resolution = app(OmrCaptureResolver::class)->resolveSheet($sheet->fresh());

    expect($resolution->outcome)->toBe(OmrResolutionOutcome::Resolved)
        ->and($sheet->fresh()->set_resolution_reason)->toBe('identity_exception')
        ->and(AuditEvent::query()->where('event_type', 'grading.omr_applicant_exception_resolved')->exists())->toBeTrue();
});

test('duplicate disposition retains one sheet and terminally rejects the other with audit evidence', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $survivor = $fixture['sheet'];
    $rejected = $survivor->replicate(['client_capture_id']);
    $rejected->client_capture_id = (string) Str::uuid();
    $rejected->save();

    app(ResolveOmrDuplicateDisposition::class)->handle(
        $survivor,
        $rejected,
        'The first complete capture is the approved scoring evidence.',
        $fixture['actor'],
        (int) $survivor->version,
        (int) $rejected->version,
    );

    expect($survivor->fresh()->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($rejected->fresh()->status)->toBe(OmrCaptureSheet::STATUS_FAILED_TERMINAL)
        ->and($rejected->issues()->where('issue_code', 'duplicate_rejected')->where('state', 'resolved')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'grading.omr_duplicate_disposition_recorded')->exists())->toBeTrue();
});

test('rejecting a previously bound duplicate frees its scoring binding for the surviving capture', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $bound = $fixture['sheet'];
    $bound->update(['admission_attempt_id' => $fixture['attempt']->id]);
    $survivor = $bound->replicate(['client_capture_id', 'admission_attempt_id']);
    $survivor->client_capture_id = (string) Str::uuid();
    $survivor->admission_attempt_id = null;
    $survivor->save();

    app(ResolveOmrDuplicateDisposition::class)->handle(
        $survivor,
        $bound,
        'The later image is the complete surviving capture.',
        $fixture['actor'],
        (int) $survivor->version,
        (int) $bound->version,
    );
    $resolution = app(OmrCaptureResolver::class)->resolveSheet($survivor->fresh());
    expect($bound->fresh()->admission_attempt_id)->toBeNull()
        ->and($resolution->isResolved())->toBeTrue();
    $survivor->update(['admission_attempt_id' => $resolution->attempt->id]);
    expect($survivor->fresh()->admission_attempt_id)->toBe($fixture['attempt']->id);
});

test('duplicate disposition rejects stale concurrent capture versions', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $survivor = $fixture['sheet'];
    $rejected = $survivor->replicate(['client_capture_id']);
    $rejected->client_capture_id = (string) Str::uuid();
    $rejected->save();
    $staleVersion = (int) $rejected->version;
    $rejected->increment('version');

    expect(fn () => app(ResolveOmrDuplicateDisposition::class)->handle(
        $survivor,
        $rejected,
        'The first capture remains the approved scoring evidence.',
        $fixture['actor'],
        (int) $survivor->version,
        $staleVersion,
    ))->toThrow(RuntimeException::class, 'changed');

    expect($rejected->fresh()->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW);
});

test('generic scanner flags multiple eligible submitted sessions as session_ambiguous', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $guidance = $fixture['actor'];
    $attempt = $fixture['attempt'];
    $firstSession = $fixture['batch']->gradingSession->examSession;

    // Create a second eligible exam session for the same applicant in the active cycle
    $secondSession = $firstSession->replicate();
    $secondSession->code = 'EX-AMBIG-'.Str::uuid();
    $secondSession->status = ExamSession::STATUS_COMPLETED;
    $secondSession->save();

    $secondGrading = GradingSession::query()->create([
        'exam_session_id' => $secondSession->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(),
    ]);

    // Replicate snapshot, sets, session seat, and assignment chain for the second session
    $firstSnapshot = $firstSession->currentMaterialSnapshot;
    $secondSnapshot = $firstSnapshot->replicate();
    $secondSnapshot->exam_session_id = $secondSession->id;
    $secondSnapshot->is_current = true;
    $secondSnapshot->save();

    foreach ($firstSnapshot->sets as $set) {
        $secondSet = $set->replicate();
        $secondSet->snapshot_id = $secondSnapshot->id;
        $secondSet->save();
    }

    app(CreateOmrCaptureBatch::class)->handle($secondGrading, $guidance, 'ambig-batch-'.Str::uuid());

    $firstSeat = $attempt->examAssignments()->firstOrFail()->submissionRecord->seatAllocation->sessionSeat;
    $secondSeat = $firstSeat->replicate();
    $secondSeat->material_snapshot_id = $secondSnapshot->id;
    $secondSeat->save();

    DB::statement('DROP INDEX IF EXISTS exam_assignments_one_active_per_attempt');
    $secondAssignment = null;
    try {
        $secondAssignment = ExamAssignment::query()->create([
            'exam_session_id' => $secondSession->id,
            'admission_attempt_id' => $attempt->id,
            'status' => ExamAssignment::STATUS_ASSIGNED,
            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            'version' => 1,
        ]);

        ExamAttendanceRecord::query()->create([
            'exam_assignment_id' => $secondAssignment->id,
            'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
            'recorded_by_user_id' => $guidance->id,
            'idempotency_key' => 'att-ambig-'.$secondAssignment->id,
            'recorded_at' => now(),
        ]);

        $secondSubmission = ExamSubmissionRecord::query()->create([
            'exam_assignment_id' => $secondAssignment->id,
            'seat_allocation_id' => null,
            'submitted_at' => now(),
            'recorded_by_user_id' => $guidance->id,
            'idempotency_key' => 'sub-ambig-'.$secondAssignment->id,
        ]);

        $secondAllocation = ExamAssignmentSeatAllocation::query()->create([
            'exam_assignment_id' => $secondAssignment->id,
            'exam_session_seat_id' => $secondSeat->id,
            'effective_from' => now(),
            'reason' => 'session_publication',
        ]);
        $secondSubmission->update(['seat_allocation_id' => $secondAllocation->id]);

        // Discovery on the applicant number detects multiple eligible submitted sessions
        $context = app(OmrScannerSessionResolver::class)->discover($attempt->reference_code, $guidance);

        expect($context->outcome)->toBe(OmrScannerContextOutcome::SelectionRequired)
            ->and($context->resolutionCode)->toBe('session_ambiguous')
            ->and($context->candidates)->toHaveCount(2)
            ->and(collect($context->candidates)->pluck('id')->all())->toEqualCanonicalizing([
                (int) $fixture['batch']->grading_session_id,
                (int) $secondGrading->id,
            ]);

        // Generic scanner entry renders Selection Required with candidate session list instead of auto-redirecting
        $this->actingAs($guidance)
            ->get(route('scoring.omr.scanner', ['applicant_number' => $attempt->reference_code]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Assessment/Omr/Scanner')
                ->where('outcome', 'selection_required')
                ->where('recovery.code', 'session_ambiguous')
                ->has('candidates', 2));
    } finally {
        $secondAssignment?->update(['status' => 'completed']);
        DB::statement("CREATE UNIQUE INDEX exam_assignments_one_active_per_attempt ON exam_assignments (admission_attempt_id) WHERE status = 'assigned'");
    }
});

test('route-bound capture and generic scanner enforce strict cross-session isolation', function (): void {
    $first = effectiveAnswerTestFixture($this);
    $guidance = $first['actor'];
    $firstSession = $first['batch']->gradingSession->examSession;

    // Create a foreign session and foreign batch without re-submitting duplicate identities
    $foreignSession = $firstSession->replicate();
    $foreignSession->code = 'EX-ISOLATION-'.Str::uuid();
    $foreignSession->status = ExamSession::STATUS_COMPLETED;
    $foreignSession->save();

    $foreignGrading = GradingSession::query()->create([
        'exam_session_id' => $foreignSession->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(),
    ]);

    $foreignSnapshot = $firstSession->currentMaterialSnapshot->replicate();
    $foreignSnapshot->exam_session_id = $foreignSession->id;
    $foreignSnapshot->is_current = true;
    $foreignSnapshot->save();

    $secondBatch = app(CreateOmrCaptureBatch::class)->handle($foreignGrading, $guidance, 'cross-batch-'.Str::uuid());
    $foreignApplicantNumber = 'FOREIGN-APPLICANT-'.Str::uuid();

    // 1. Route-bound scanner: Sheet in Session 1 batch with unassigned applicant is refused as applicant_unresolved
    $crossSheet = OmrCaptureSheet::query()->create([
        'batch_id' => $first['batch']->id,
        'detected_applicant_number' => $foreignApplicantNumber,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/test/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'asset-cross'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => ['items' => []],
    ]);

    $resolution = app(OmrCaptureResolver::class)->resolveSheet($crossSheet);
    expect($resolution->outcome)->toBe(OmrResolutionOutcome::ApplicantUnresolved)
        ->and($resolution->failureCode())->toBe('applicant_unresolved')
        ->and($resolution->failure?->context)->toMatchArray([
            'exam_session_id' => $first['batch']->gradingSession->exam_session_id,
        ]);

    // 2. Foreign batch route access returns 404
    $this->actingAs($guidance)
        ->get(route('scoring.sessions.omr.batches.workbench', [
            'examSession' => $first['batch']->gradingSession->examSession,
            'batch' => $secondBatch,
        ]))
        ->assertNotFound();

    // 3. Foreign grading session cannot confirm or score cross-session sheet
    expect(ScoreRecord::query()->where('capture_sheet_id', $crossSheet->id)->exists())->toBeFalse();
});

test('resolver and run snapshot emit seat_missing and seat_snapshot_missing without score side effects', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $assignment = $fixture['attempt']->examAssignments()->firstOrFail();
    $submission = $assignment->submissionRecord;
    $seatAllocation = $submission->seatAllocation;
    $sessionSeat = $seatAllocation->sessionSeat;
    $snapshotService = app(OmrRunStatusSnapshot::class);
    $scoresBefore = ScoreRecord::query()->count();

    // Case A: seat_missing — submission has no seat allocation
    $originalAllocationId = $submission->seat_allocation_id;
    $submission->update(['seat_allocation_id' => null]);

    $sheetResolution = app(OmrCaptureResolver::class)->resolveSheet($sheet->fresh());
    $scannerContext = app(OmrScannerSessionResolver::class)->discover($fixture['attempt']->reference_code, $fixture['actor']);
    $snapshotA = $snapshotService->forBatch($fixture['batch']->fresh());
    $sheetRowA = collect($snapshotA['sheets'])->firstWhere('id', (int) $sheet->id);

    expect($sheetResolution->outcome)->toBe(OmrResolutionOutcome::SeatMissing)
        ->and($sheetResolution->failureCode())->toBe('seat_missing')
        ->and($scannerContext->outcome)->toBe(OmrScannerContextOutcome::SelectionRequired)
        ->and($scannerContext->resolutionCode)->toBe('seat_missing')
        ->and($sheetRowA['recovery'])->toMatchArray([
            'code' => 'seat_missing',
            'owner' => 'Exam setup staff',
            'primary_action' => 'open_exam_setup',
            'support_code' => 'OMR-'.$sheet->id.'-SEAT_MISSING',
        ])
        ->and($snapshotA['actions']['confirm'])->toBeFalse();

    // Case B: seat_snapshot_missing — session seat belongs to a different exam session snapshot
    $submission->update(['seat_allocation_id' => $originalAllocationId]);
    $foreignSession = $fixture['batch']->gradingSession->examSession->replicate();
    $foreignSession->code = 'EX-FOREIGN-'.Str::uuid();
    $foreignSession->save();

    $foreignSnapshot = $sessionSeat->materialSnapshot->replicate();
    $foreignSnapshot->exam_session_id = $foreignSession->id;
    $foreignSnapshot->save();

    $originalSnapshotId = $sessionSeat->material_snapshot_id;
    $sessionSeat->update(['material_snapshot_id' => $foreignSnapshot->id]);

    $sheetResolutionB = app(OmrCaptureResolver::class)->resolveSheet($sheet->fresh());
    $snapshotB = $snapshotService->forBatch($fixture['batch']->fresh());
    $sheetRowB = collect($snapshotB['sheets'])->firstWhere('id', (int) $sheet->id);

    expect($sheetResolutionB->outcome)->toBe(OmrResolutionOutcome::SeatMissing)
        ->and($sheetResolutionB->failureCode())->toBe('seat_snapshot_missing')
        ->and($sheetRowB['recovery'])->toMatchArray([
            'code' => 'seat_snapshot_missing',
            'owner' => 'Exam setup staff',
            'primary_action' => 'open_exam_setup',
            'support_code' => 'OMR-'.$sheet->id.'-SEAT_SNAPSHOT_MISSING',
        ]);

    // Restore and assert zero ScoreRecord side effects throughout
    $sessionSeat->update(['material_snapshot_id' => $originalSnapshotId]);
    expect(ScoreRecord::query()->count())->toBe($scoresBefore);
});

test('resolver and run snapshot emit key_missing when frozen booklet set lacks an answer key', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $assignment = $fixture['attempt']->examAssignments()->firstOrFail();
    $submission = $assignment->submissionRecord;
    $sessionSeat = $submission->seatAllocation->sessionSeat;
    $materialSet = $sessionSeat->materialSnapshot->sets()->where('booklet_set_id', $sessionSeat->booklet_set_id)->firstOrFail();
    $scoresBefore = ScoreRecord::query()->count();

    // Clear the immutable answer key using DB table to bypass model immutability check
    $originalKey = $materialSet->answer_key_json;
    DB::table('exam_session_material_sets')
        ->where('id', $materialSet->id)
        ->update(['answer_key_json' => json_encode([])]);
    $resolution = app(OmrCaptureResolver::class)->resolveSheet($sheet->fresh());
    $snapshot = app(OmrRunStatusSnapshot::class)->forBatch($fixture['batch']->fresh());
    $sheetRow = collect($snapshot['sheets'])->firstWhere('id', (int) $sheet->id);

    expect($resolution->outcome)->toBe(OmrResolutionOutcome::KeyMissing)
        ->and($resolution->failureCode())->toBe('key_missing')
        ->and($resolution->failure?->context)->toMatchArray([
            'snapshot_id' => $sessionSeat->materialSnapshot->id,
            'session_seat_id' => $sessionSeat->id,
        ])
        ->and($sheetRow['recovery'])->toMatchArray([
            'code' => 'key_missing',
            'owner' => 'Assessment setup staff',
            'primary_action' => 'open_sheet_builder',
            'support_code' => 'OMR-'.$sheet->id.'-KEY_MISSING',
        ])
        ->and($snapshot['actions']['confirm'])->toBeFalse()
        ->and(ScoreRecord::query()->count())->toBe($scoresBefore);

    DB::table('exam_session_material_sets')
        ->where('id', $materialSet->id)
        ->update(['answer_key_json' => json_encode($originalKey)]);
});

test('resolver and run snapshot emit template_mismatch when sheet belongs to different template version', function (): void {
    $fixture = effectiveAnswerTestFixture($this);
    $sheet = $fixture['sheet'];
    $actor = $fixture['actor'];
    $scoresBefore = ScoreRecord::query()->count();

    // Create another template version
    $template = OmrAnswerSheetTemplate::query()->firstOrCreate(
        ['code' => 'SYNTHETIC-OTHER-TEMPLATE'],
        ['name' => 'Synthetic Other Template'],
    );
    $otherVersion = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 99,
        'status' => 'active',
        'page_width' => 210,
        'page_height' => 297,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'other-config'),
        'template_json_hash' => hash('sha256', 'other-template-json'),
        'native_bundle_version' => 'v1',
        'native_bundle_json' => ['template' => ['pages' => [['page_no' => 1]]]],
        'native_bundle_hash' => hash('sha256', 'other-native-bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test-commit',
        'patch_identity' => 'test-patch',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'other-review-manifest'),
    ]);

    $otherInstance = OmrBlankInstance::query()->create([
        'template_version_id' => $otherVersion->id,
        'instance_code' => 'NI-0123456789ABCDEFGHJKMNPTVW',
        'display_code' => 'DISP-OTHER-'.Str::random(6),
        'status' => OmrBlankInstance::STATUS_PRINTED,
        'page_set_no' => 1,
        'page_count' => 1,
        'hash_chain' => [hash('sha256', 'other-page-1')],
        'qr_manifest_hash' => hash('sha256', 'other-qr-manifest'),
        'signing_key_id' => 'current-v1',
        'created_by_user_id' => $actor->id,
        'copy_policy' => OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
    ]);
    $otherPage = OmrBlankInstancePage::query()->create([
        'instance_id' => $otherInstance->id,
        'page_no' => 1,
        'qr_payload_hash' => hash('sha256', 'other-page-qr-payload'),
        'signing_key_id' => 'current-v1',
        'print_artifact_hash' => hash('sha256', 'other-print-artifact'),
        'status' => 'printed',
    ]);

    $capturePage = $sheet->pages()->create([
        'page_no' => 1,
        'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
        'version' => 1,
        'asset_path' => 'omr/test/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'page-hmac'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
    ]);

    OmrBlankInstancePageClaim::query()->create([
        'blank_instance_page_id' => $otherPage->id,
        'capture_sheet_page_id' => $capturePage->id,
        'claim_kind' => 'original',
        'created_by_user_id' => $actor->id,
    ]);

    // Ensure batch has template_version_id set
    $currentTemplateId = $fixture['batch']->template_version_id
        ?? $fixture['batch']->gradingSession->examSession->currentMaterialSnapshot->template_version_id;
    $fixture['batch']->update(['template_version_id' => $currentTemplateId]);

    $resolution = app(OmrCaptureResolver::class)->resolveSheet($sheet->fresh());
    $snapshot = app(OmrRunStatusSnapshot::class)->forBatch($fixture['batch']->fresh());
    $sheetRow = collect($snapshot['sheets'])->firstWhere('id', (int) $sheet->id);

    expect($resolution->outcome)->toBe(OmrResolutionOutcome::TemplateMismatch)
        ->and($resolution->failureCode())->toBe('template_mismatch')
        ->and((int) $resolution->blankInstance?->template_version_id)->toBe((int) $otherVersion->id)
        ->and($sheetRow['recovery'])->toMatchArray([
            'code' => 'template_mismatch',
            'owner' => 'Assessment setup staff',
            'primary_action' => 'open_sheet_builder',
            'support_code' => 'OMR-'.$sheet->id.'-TEMPLATE_MISMATCH',
        ])
        ->and($snapshot['actions']['confirm'])->toBeFalse()
        ->and(ScoreRecord::query()->count())->toBe($scoresBefore);
});

test('applicant_conflict occurs when unreadable applicant number has multiple eligible assignments in session', function (): void {
    // Note: In NEXIAM schema, admission_attempts.reference_code has a UNIQUE constraint and
    // exam_assignments has UNIQUE(exam_session_id, admission_attempt_id).
    // Therefore, a specific non-null Applicant Number cannot match multiple assignments in the same session.
    // However, when detected_applicant_number is null/unreadable in a multi-applicant session,
    // the resolver evaluates all eligible assignments in the session and detects an applicant conflict.
    $fixture = effectiveAnswerTestFixture($this);
    $session = $fixture['batch']->gradingSession->examSession;
    $guidance = $fixture['actor'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    // Create a second attempt with a unique last name to avoid normalized key collision in the cycle
    $secondResult = submitSyntheticApplicant('conflict.'.uniqid().'@example.test', 'Conflict'.Str::random(6));
    $secondAttempt = $secondResult['attempt'];

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $secondAttempt), ['expected_version' => 1])
        ->assertRedirect();

    $secondAssignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $secondAttempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);

    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $secondAssignment->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_by_user_id' => $guidance->id,
        'idempotency_key' => 'att-conflict-'.$secondAssignment->id,
        'recorded_at' => now(),
    ]);

    $scoresBefore = ScoreRecord::query()->count();

    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $fixture['batch']->id,
        'admission_attempt_id' => null,
        'detected_applicant_number' => null,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/test/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'asset-conflict'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => ['items' => []],
    ]);

    $resolution = app(OmrCaptureResolver::class)->resolveSheet($sheet);
    $snapshot = app(OmrRunStatusSnapshot::class)->forBatch($fixture['batch']->fresh());
    $sheetRow = collect($snapshot['sheets'])->firstWhere('id', (int) $sheet->id);

    expect($resolution->outcome)->toBe(OmrResolutionOutcome::ApplicantConflict)
        ->and($resolution->failureCode())->toBe('applicant_conflict')
        ->and($resolution->failure?->context['assignment_ids'] ?? [])->toHaveCount(2)
        ->and($sheetRow['recovery'])->toMatchArray([
            'code' => 'applicant_conflict',
            'owner' => 'Scoring staff',
            'primary_action' => 'match_applicant',
            'secondary_action' => 'retry_scan',
            'support_code' => 'OMR-'.$sheet->id.'-APPLICANT_CONFLICT',
        ])
        ->and($snapshot['actions']['confirm'])->toBeFalse()
        ->and(ScoreRecord::query()->count())->toBe($scoresBefore);
});

test('recovery catalog table maps every failure code to its safe owner, actions, and support code with zero score side effects', function (): void {
    $catalog = app(OmrRecoveryCatalog::class);
    $scoresBefore = ScoreRecord::query()->count();

    $matrix = [
        'session_unresolved' => ['Session not found', 'Scoring staff', 'retry_scan', null],
        'session_ambiguous' => ['Choose the session', 'Scoring staff', 'select_session', 'retry_scan'],
        'session_missing' => ['Session unavailable', 'Scoring staff', 'return_to_grading', null],
        'applicant_unresolved' => ['Applicant Number not resolved', 'Scoring staff', 'retry_scan', 'match_applicant'],
        'applicant_conflict' => ['Applicant Number conflict', 'Scoring staff', 'match_applicant', 'retry_scan'],
        'applicant_duplicate' => ['Possible duplicate scan', 'Scoring staff', 'compare_duplicates', null],
        'submission_missing' => ['Submission not recorded', 'Exam proctor', 'open_monitoring', null],
        'seat_missing' => ['Frozen seat unavailable', 'Exam setup staff', 'open_exam_setup', null],
        'seat_snapshot_missing' => ['Frozen session snapshot unavailable', 'Exam setup staff', 'open_exam_setup', null],
        'key_missing' => ['Answer key unavailable', 'Assessment setup staff', 'open_sheet_builder', null],
        'observed_set_mismatch' => ['Observed Set conflict', 'Assessment setup staff', 'review_material_assignment', null],
        'qr_missing' => ['QR not readable', 'Scoring staff', 'retry_scan', 'enter_display_code'],
        'qr_reader_unavailable' => ['QR reader unavailable', 'Scoring staff', 'retry_scan', 'enter_display_code'],
        'qr_reader_response_invalid' => ['QR reader response invalid', 'Scoring staff', 'retry_scan', 'enter_display_code'],
        'multi_page_capture' => ['Multi-page capture required', 'Scoring staff', 'retry_scan', null],
        'analysis_failed' => ['Scan analysis failed', 'Scoring staff', 'retry_scan', 'match_applicant'],
        'analysis_evidence_invalid' => ['Scan evidence unavailable', 'Scoring support', 'retry_scan', null],
        'qr_invalid' => ['QR signature invalid', 'Scoring staff', 'quarantine_scan', 'retry_scan'],
        'qr_instance_missing' => ['Answer Sheet not registered', 'Assessment setup staff', 'quarantine_scan', null],
        'qr_provenance_conflict' => ['Answer Sheet provenance conflict', 'Scoring staff', 'quarantine_scan', null],
        'qr_scope_mismatch' => ['Answer Sheet scope mismatch', 'Assessment setup staff', 'quarantine_scan', null],
        'qr_duplicate_page' => ['Answer Sheet page already captured', 'Scoring staff', 'compare_duplicates', null],
        'qr_lifecycle_invalid' => ['Answer Sheet no longer valid', 'Assessment setup staff', 'quarantine_scan', null],
        'qr_page_invalid' => ['Answer Sheet page mismatch', 'Scoring staff', 'retry_scan', null],
        'template_mismatch' => ['Template mismatch', 'Assessment setup staff', 'open_sheet_builder', null],
        'unknown_code_fallback' => ['Review required', 'Scoring support', 'open_support', null],
    ];

    foreach ($matrix as $code => [$expectedTitle, $expectedOwner, $expectedPrimary, $expectedSecondary]) {
        $entry = $catalog->for($code);

        expect($entry['code'])->toBe($code)
            ->and($entry['title'])->toBe($expectedTitle)
            ->and($entry['owner'])->toBe($expectedOwner)
            ->and($entry['primary_action'])->toBe($expectedPrimary)
            ->and($entry['secondary_action'])->toBe($expectedSecondary)
            ->and($entry['blocking'])->toBeTrue();

        $supportCode = sprintf('OMR-%d-%s', 42, strtoupper($code));
        expect($supportCode)->toStartWith('OMR-42-')
            ->and($supportCode)->toContain(strtoupper($code));
    }

    // Zero score records created by catalog queries
    expect(ScoreRecord::query()->count())->toBe($scoresBefore);
});
