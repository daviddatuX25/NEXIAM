<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RecordOmrItemReviewDecision;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

function endpointAuthorizeStaffSession(TestCase $test, User $user): void
{
    $state = StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $user->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'session_version' => 1,
            'mfa_required' => false,
            'must_change_password' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_generation' => (string) Str::uuid(),
        ],
    );

    $test->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => 'omr-item-review-test',
    ]);
}

/**
 * @return array{
 *     guidance: User,
 *     registrar: User,
 *     gradingSession: GradingSession,
 *     batch: OmrCaptureBatch,
 *     sheet: OmrCaptureSheet,
 *     evidence_hash_1: string,
 *     evidence_hash_2: string,
 *     item_key_1: string,
 *     item_key_2: string
 * }
 */
function endpointGradingFixture(TestCase $test): array
{
    $fixture = buildOmrGradingFixture($test);
    $guidance = $fixture['guidance'];
    endpointAuthorizeStaffSession($test, $guidance);
    endpointAuthorizeStaffSession($test, $fixture['registrar']);
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, 'endpoint-batch-'.Str::uuid());

    $evidenceHash1 = hash('sha256', 'endpoint-grading-evidence-q1');
    $evidenceHash2 = hash('sha256', 'endpoint-grading-evidence-q2');

    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/endpoint/sheet.png',
        'asset_hmac' => hash('sha256', 'asset-hmac'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => [
            'items' => [
                [
                    'area_code' => 'AREA',
                    'question_no' => 1,
                    'options' => ['A', 'B', 'C', 'D'],
                    'evidence_hash' => $evidenceHash1,
                    'machine' => [
                        'state' => 'marked',
                        'selected_option' => 'A',
                        'observed_options' => ['A'],
                        'option_observations' => [],
                        'confidence' => 0.98,
                        'issue_codes' => [],
                    ],
                ],
                [
                    'area_code' => 'AREA',
                    'question_no' => 2,
                    'options' => ['A', 'B', 'C', 'D'],
                    'evidence_hash' => $evidenceHash2,
                    'machine' => [
                        'state' => 'ambiguous',
                        'selected_option' => null,
                        'observed_options' => ['B', 'C'],
                        'option_observations' => [],
                        'confidence' => 0.55,
                        'issue_codes' => ['ambiguous_mark'],
                    ],
                ],
            ],
        ],
    ]);

    return [
        'guidance' => $guidance,
        'registrar' => $fixture['registrar'],
        'gradingSession' => $fixture['session'],
        'batch' => $batch,
        'sheet' => $sheet,
        'evidence_hash_1' => $evidenceHash1,
        'evidence_hash_2' => $evidenceHash2,
        'item_key_1' => 'AREA_Q_1',
        'item_key_2' => 'AREA_Q_2',
    ];
}

/**
 * @return array{
 *     actor: User,
 *     draft: OmrTemplateAuthoringDraft,
 *     run: OmrTemplateVerificationRun,
 *     challenge: OmrPhysicalChallenge,
 *     method: OmrTemplateVerificationMethod,
 *     page: OmrTemplateVerificationMethodPage,
 *     capture: OmrTemplateVerificationCapture,
 *     item: OmrTemplateVerificationItemEvidence,
 *     evidenceHash: string
 * }
 */
function endpointVerificationFixture(TestCase $test, ?User $actor = null): array
{
    $actor ??= User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $institution = Institution::query()->firstOrCreate(
        ['code' => 'DEFAULT'],
        ['name' => 'Default University', 'legal_name' => 'Default University'],
    );
    StaffProfile::query()->updateOrCreate(
        ['user_id' => $actor->id],
        [
            'institution_id' => $institution->id,
            'office' => 'guidance',
            'status' => 'active',
        ],
    );
    endpointAuthorizeStaffSession($test, $actor);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'code' => 'DRAFT-VERIF-'.Str::uuid(),
        'name' => 'Verification Endpoint Draft',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_IMAGE,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
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

    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'mode' => OmrPhysicalChallenge::MODE_QUICK,
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'challenge_code' => 'CHAL-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10)),
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $draft->id,
        'target_version' => 'v1',
        'target_hash' => str_repeat('a', 64),
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'created_by_user_id' => $actor->id,
    ]);

    $method = OmrTemplateVerificationMethod::query()->create([
        'run_id' => $run->id,
        'method' => OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        'method_state' => OmrTemplateVerificationMethod::STATE_PASSED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $draft->id,
        'target_version' => 'v1',
        'target_hash' => str_repeat('a', 64),
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'challenge_id' => $challenge->id,
        'required_page_count' => 1,
    ]);

    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $method->id,
        'challenge_id' => $challenge->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
        'target_id' => $draft->id,
        'target_version' => 'v1',
        'target_hash' => str_repeat('a', 64),
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'expected' => 'pass',
        'actual' => 'pass',
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => hash('sha256', 'verification-endpoint-capture-evidence'),
        'captured_by_user_id' => $actor->id,
        'summary' => [
            'verification_result' => ['gates_pass' => true, 'review_count' => 1],
            'review_count' => 1,
            'evidence' => ['items' => [[
                'native_label' => 'VERBAL_Q_1',
                'page_no' => 1,
                'area_code' => 'verbal',
                'question_no' => 1,
                'options' => [['code' => 'A'], ['code' => 'B'], ['code' => 'C'], ['code' => 'D']],
                'detected' => [
                    'state' => 'marked',
                    'selected_option' => 'A',
                    'observed_options' => ['A'],
                    'option_observations' => [],
                    'confidence' => 0.9,
                    'issue_codes' => [],
                ],
            ]]],
        ],
        'captured_at' => now(),
        'lock_version' => 0,
    ]);

    $page = OmrTemplateVerificationMethodPage::query()->create([
        'method_id' => $method->id,
        'page_no' => 1,
        'required' => true,
        'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
        'selected_attempt_id' => $capture->id,
    ]);

    $evidenceHash = hash('sha256', 'verification-endpoint-item-evidence');
    $item = OmrTemplateVerificationItemEvidence::query()->create([
        'capture_id' => $capture->id,
        'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
        'area_key' => 'verbal',
        'area_position' => 1,
        'question_no' => 1,
        'item_id' => 'VERBAL_Q_1',
        'options' => [['code' => 'A'], ['code' => 'B'], ['code' => 'C'], ['code' => 'D']],
        'expected' => 'A',
        'detected' => 'A',
        'state' => 'marked',
        'means' => ['A' => 0.8, 'B' => 0.1, 'C' => 0.1, 'D' => 0.1],
        'delta' => 0.7,
        'status' => 'match',
        'evidence_version' => 'v1',
        'evidence_hash' => $evidenceHash,
        'row_version' => 0,
    ]);

    return compact('actor', 'draft', 'run', 'challenge', 'method', 'page', 'capture', 'item', 'evidenceHash');
}

// ---------------------------------------------------------------------------
// 1. Grading item review endpoint tests
// ---------------------------------------------------------------------------

test('grading item review named route requires authentication and capability', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    $payload = [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'unauth-test-'.Str::uuid(),
    ];

    // Unauthenticated request is rejected
    auth()->logout();
    $this->postJson($url, $payload)->assertUnauthorized();

    // Authenticated user lacking GUIDANCE_SCORING_OMR receives 403
    $unprivileged = User::factory()->create();
    $this->actingAs($unprivileged)->postJson($url, $payload)->assertForbidden();
});

test('grading item review form request rejects prohibited fields with 422', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    $base = [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'prohibited-test-'.Str::uuid(),
    ];
    $revisionCount = OmrItemReviewRevision::query()->count();
    $receiptCount = DB::table('omr_command_receipts')->count();

    foreach (['expected', 'expected_option', 'expected_answer', 'correct', 'correct_option', 'correctness', 'is_correct', 'effective', 'effective_answer', 'effective_state', 'score', 'scores'] as $prohibited) {
        $this->actingAs($fixture['guidance'])
            ->postJson($url, array_merge($base, [$prohibited => 'tampered']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$prohibited]);
    }
    expect(OmrItemReviewRevision::query()->count())->toBe($revisionCount)
        ->and(DB::table('omr_command_receipts')->count())->toBe($receiptCount);
});

test('grading item review form request validates required fields and formats', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    // Missing all required parameters
    $this->actingAs($fixture['guidance'])
        ->postJson($url, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'decision',
            'expected_parent_version',
            'expected_item_revision',
            'reviewed_machine_evidence_hash',
            'idempotency_key',
        ]);

    // Invalid decision enum
    $this->actingAs($fixture['guidance'])
        ->postJson($url, [
            'decision' => 'unsupported_decision_value',
            'expected_parent_version' => 0,
            'expected_item_revision' => 0,
            'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
            'idempotency_key' => 'invalid-decision-'.Str::uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['decision']);

    // Invalid evidence hash format (not 64 hex chars)
    $this->actingAs($fixture['guidance'])
        ->postJson($url, [
            'decision' => OmrReviewDecision::ConfirmMachine->value,
            'expected_parent_version' => 0,
            'expected_item_revision' => 0,
            'reviewed_machine_evidence_hash' => 'invalid-short-hash',
            'idempotency_key' => 'invalid-hash-'.Str::uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reviewed_machine_evidence_hash']);

    // Invalid reason_code format
    $this->actingAs($fixture['guidance'])
        ->postJson($url, [
            'decision' => OmrReviewDecision::SetOption->value,
            'selected_option' => 'A',
            'reason_code' => 'INVALID-CODE-WITH-CAPS!',
            'expected_parent_version' => 0,
            'expected_item_revision' => 0,
            'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
            'idempotency_key' => 'invalid-reason-code-'.Str::uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason_code']);
});

test('grading item review executes confirm_machine on definite mark and returns projection', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    $key = 'confirm-machine-'.Str::uuid();
    $response = $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => $key,
    ]);

    $response->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'receipt' => [
                'context_type' => 'grading',
                'parent_id' => $fixture['sheet']->id,
                'item_key' => $fixture['item_key_1'],
                'revision' => 1,
                'parent_version' => 1,
                'decision' => 'confirm_machine',
                'selected_option' => 'A',
                'evidence_hash' => $fixture['evidence_hash_1'],
            ],
            'review' => [
                'revision' => 1,
                'decision' => 'confirm_machine',
                'selected_option' => 'A',
                'reason_code' => null,
                'evidence_hash' => $fixture['evidence_hash_1'],
                'reviewer' => [
                    'id' => $fixture['guidance']->id,
                    'display_name' => $fixture['guidance']->name,
                ],
            ],
        ]);

    expect($fixture['sheet']->fresh()->version)->toBe(1)
        ->and(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(1);

    // Audit event recorded
    $audit = AuditEvent::query()->where('event_type', RecordOmrItemReviewDecision::AUDIT_EVENT)->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['context_type'])->toBe('grading')
        ->and($audit->payload['item_key'])->toBe($fixture['item_key_1'])
        ->and($audit->payload['decision'])->toBe('confirm_machine');
});

test('grading item review executes set_option on ambiguous mark with reason and note', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_2'],
    ]);

    $key = 'set-option-ambiguous-'.Str::uuid();
    $note = 'Reviewer observed intentional B stroke over accidental smudge.';

    // Setting option without reason fails with 422
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => 'B',
        'reason_code' => null,
        'note' => $note,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_2'],
        'idempotency_key' => 'missing-reason-'.Str::uuid(),
    ])->assertStatus(422)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_reason_required']);

    // Setting option with valid reason and note succeeds
    $response = $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => 'B',
        'reason_code' => 'ambiguous_resolved',
        'note' => $note,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_2'],
        'idempotency_key' => $key,
    ]);

    $response->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'review' => [
                'revision' => 1,
                'decision' => 'set_option',
                'selected_option' => 'B',
                'reason_code' => 'ambiguous_resolved',
                'note' => $note,
            ],
        ]);
});

test('grading item review requires note when overriding a definite machine mark', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'], // machine selected option is A
    ]);

    // Overriding machine option A with option B requires note
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => 'B',
        'reason_code' => 'corrected_mark',
        'note' => null,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'override-without-note-'.Str::uuid(),
    ])->assertStatus(422)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_note_required']);
});

test('grading item review executes blank and unresolved decisions with reason validation', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    // Clearing definite machine mark without reason fails
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => null,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'blank-missing-reason-'.Str::uuid(),
    ])->assertStatus(422)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_reason_required']);

    // Declaring blank with reason succeeds
    $blankResponse = $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => 'erased_bubble',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'blank-success-'.Str::uuid(),
    ]);

    $blankResponse->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'review' => [
                'revision' => 1,
                'decision' => 'blank',
                'selected_option' => null,
                'reason_code' => 'erased_bubble',
            ],
        ]);

    // Leaving unresolved on item 2 without reason fails
    $url2 = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_2'],
    ]);

    $this->actingAs($fixture['guidance'])->postJson($url2, [
        'decision' => OmrReviewDecision::Unresolved->value,
        'selected_option' => null,
        'reason_code' => null,
        'expected_parent_version' => 1, // parent version bumped by previous test
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_2'],
        'idempotency_key' => 'unresolved-missing-reason-'.Str::uuid(),
    ])->assertStatus(422)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_reason_required']);

    // Leaving unresolved with reason succeeds
    $unresolvedResponse = $this->actingAs($fixture['guidance'])->postJson($url2, [
        'decision' => OmrReviewDecision::Unresolved->value,
        'selected_option' => null,
        'reason_code' => 'scanner_artifact',
        'note' => 'Needs secondary inspection under high magnification.',
        'expected_parent_version' => 1,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_2'],
        'idempotency_key' => 'unresolved-success-'.Str::uuid(),
    ]);

    $unresolvedResponse->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'review' => [
                'revision' => 1,
                'decision' => 'unresolved',
                'selected_option' => null,
                'reason_code' => 'scanner_artifact',
            ],
        ]);
});

test('grading item review rejects stale versions, changed evidence, and closed lifecycle with appropriate status codes', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    // 1. Initial successful review
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'first-review-'.Str::uuid(),
    ])->assertOk();

    // 2. Stale parent version returns 409
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0, // stale, now 1
        'expected_item_revision' => 1,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'stale-parent-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_parent_version_stale']);

    // 3. Stale item revision returns 409
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 1,
        'expected_item_revision' => 0, // stale, now 1
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'stale-item-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_item_revision_stale']);

    // 4. Evidence hash mismatch returns 409
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 1,
        'expected_item_revision' => 1,
        'reviewed_machine_evidence_hash' => hash('sha256', 'mismatched-evidence'),
        'idempotency_key' => 'hash-mismatch-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_evidence_hash_mismatch']);

    // 5. Closed lifecycle (sheet committed) returns 422
    $fixture['sheet']->update(['status' => OmrCaptureSheet::STATUS_COMMITTED]);

    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 1,
        'expected_item_revision' => 1,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'closed-lifecycle-'.Str::uuid(),
    ])->assertStatus(422)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_not_reviewable']);
});

test('grading item review replays safe receipt on exact key match and conflicts on changed payload', function (): void {
    $fixture = endpointGradingFixture($this);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    $key = 'replay-key-'.Str::uuid();
    $payload = [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => $key,
    ];

    // First request
    $first = $this->actingAs($fixture['guidance'])->postJson($url, $payload)->assertOk()->json();

    // Exact replay returns 200 with identical receipt
    $replay = $this->actingAs($fixture['guidance'])->postJson($url, $payload)->assertOk()->json();

    expect($replay['receipt']['revision_id'])->toBe($first['receipt']['revision_id'])
        ->and(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(1)
        ->and(DB::table('omr_command_receipts')->where('idempotency_key', $key)->count())->toBe(1);

    // Key reuse with changed decision returns 409
    $this->actingAs($fixture['guidance'])->postJson($url, [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => 'erased',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => $key,
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_idempotency_conflict']);
});

test('grading item review denies access on scope mismatch and cross-session identifiers', function (): void {
    $fixture = endpointGradingFixture($this);

    // 1. User with GUIDANCE_SCORING_OMR but office = registrar receives forbidden or not found
    $registrarUser = $fixture['registrar'];
    $registrarUser->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $url = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    $res = $this->actingAs($registrarUser)->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'scope-test-'.Str::uuid(),
    ])->assertNotFound();
    // 2. Cross-session ID: sheet belongs to session A, route requests session B
    $otherSessionId = $fixture['gradingSession']->id + 99999;
    $crossUrl = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $otherSessionId,
        'sheet' => $fixture['sheet']->id,
        'item' => $fixture['item_key_1'],
    ]);

    $this->actingAs($fixture['guidance'])->postJson($crossUrl, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'cross-session-'.Str::uuid(),
    ])->assertNotFound();

    // 3. Non-existent item key returns 404
    $nonExistentItemUrl = route('scoring.sessions.omr.sheets.items.review', [
        'examSession' => $fixture['gradingSession']->id,
        'sheet' => $fixture['sheet']->id,
        'item' => 'NON_EXISTENT_Q_99',
    ]);

    $this->actingAs($fixture['guidance'])->postJson($nonExistentItemUrl, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidence_hash_1'],
        'idempotency_key' => 'missing-item-'.Str::uuid(),
    ])->assertNotFound();
});

// ---------------------------------------------------------------------------
// 2. Verification item review endpoint tests
// ---------------------------------------------------------------------------

test('verification item review named route requires authentication and capability', function (): void {
    $fixture = endpointVerificationFixture($this);
    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $fixture['item']->id,
    ]);

    $payload = [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'verif-unauth-'.Str::uuid(),
    ];

    // Unauthenticated request is rejected
    $this->postJson($url, $payload)->assertUnauthorized();

    // User lacking GUIDANCE_SCORING_OMR receives 403
    $unprivileged = User::factory()->create();
    $this->actingAs($unprivileged)->postJson($url, $payload)->assertForbidden();
});

test('verification item review form request rejects prohibited fields with 422', function (): void {
    $fixture = endpointVerificationFixture($this);
    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $fixture['item']->id,
    ]);

    $base = [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'verif-prohibited-'.Str::uuid(),
    ];
    $revisionCount = OmrItemReviewRevision::query()->count();
    $receiptCount = DB::table('omr_command_receipts')->count();

    foreach (['expected', 'expected_option', 'expected_answer', 'correct', 'correct_option', 'correctness', 'is_correct', 'effective', 'effective_answer', 'effective_state', 'score', 'scores'] as $prohibited) {
        $this->actingAs($fixture['actor'])
            ->postJson($url, array_merge($base, [$prohibited => 'spoofed']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$prohibited]);
    }
    expect(OmrItemReviewRevision::query()->count())->toBe($revisionCount)
        ->and(DB::table('omr_command_receipts')->count())->toBe($receiptCount);
});

test('verification item review executes set_option and confirm_machine decisions and returns projection', function (): void {
    $fixture = endpointVerificationFixture($this);
    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $fixture['item']->id,
    ]);

    // 1. Confirm machine decision
    $key1 = 'verif-confirm-'.Str::uuid();
    $response = $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => $key1,
    ]);

    $response->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'receipt' => [
                'context_type' => 'verification',
                'parent_id' => $fixture['item']->id,
                'item_key' => 'VERBAL_Q_1',
                'revision' => 1,
                'parent_version' => 1,
                'decision' => 'confirm_machine',
                'selected_option' => 'A',
            ],
            'review' => [
                'revision' => 1,
                'decision' => 'confirm_machine',
                'selected_option' => 'A',
                'reason_code' => null,
                'reviewer' => [
                    'id' => $fixture['actor']->id,
                    'display_name' => $fixture['actor']->name,
                ],
            ],
        ]);

    expect($fixture['capture']->fresh()->lock_version)->toBe(1)
        ->and(OmrItemReviewRevision::query()->count())->toBe(1);

    // 2. Correct with set_option on the next revision
    $key2 = 'verif-set-option-'.Str::uuid();
    $setOptionResponse = $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => 'B',
        'reason_code' => 'rehearsal_correction',
        'note' => 'Staff reviewer corrected mark during authoring rehearsal.',
        'expected_parent_version' => 1,
        'expected_item_revision' => 1,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => $key2,
    ]);

    $setOptionResponse->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'review' => [
                'revision' => 2,
                'decision' => 'set_option',
                'selected_option' => 'B',
                'reason_code' => 'rehearsal_correction',
            ],
        ]);

    expect($fixture['capture']->fresh()->lock_version)->toBe(2)
        ->and(OmrItemReviewRevision::query()->count())->toBe(2);
});

test('verification item review enforces lifecycle, concurrency, and idempotency constraints', function (): void {
    $fixture = endpointVerificationFixture($this);
    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $fixture['item']->id,
    ]);

    // 1. Stale parent version returns 409
    $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 999, // stale
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'verif-stale-parent-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_parent_version_stale']);

    // 2. Stale item revision returns 409
    $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 999, // stale
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'verif-stale-item-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_item_revision_stale']);

    // 3. Evidence hash mismatch returns 409
    $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => hash('sha256', 'other-evidence'),
        'idempotency_key' => 'verif-hash-mismatch-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_evidence_hash_mismatch']);

    // 4. Successful review for replay test
    $replayKey = 'verif-replay-'.Str::uuid();
    $validPayload = [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => $replayKey,
    ];

    $first = $this->actingAs($fixture['actor'])->postJson($url, $validPayload)->assertOk()->json();
    $replay = $this->actingAs($fixture['actor'])->postJson($url, $validPayload)->assertOk()->json();

    expect($replay['receipt']['revision_id'])->toBe($first['receipt']['revision_id'])
        ->and(OmrItemReviewRevision::query()->count())->toBe(1);

    // 5. Idempotency conflict on payload change returns 409
    $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => 'faint_bubble',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => $replayKey,
    ])->assertStatus(409)->assertJson(['outcome' => 'failed', 'code' => 'omr_review_idempotency_conflict']);

});

test('verification item review denies cross-scope and cross-owner access with 404', function (): void {
    $fixture = endpointVerificationFixture($this);

    // 1. Outsider actor accessing another user's draft run receives 404
    $outsider = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $outsider->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    StaffProfile::query()->create([
        'user_id' => $outsider->id,
        'institution_id' => Institution::query()->firstOrCreate(['code' => 'DEFAULT'], ['name' => 'Default University', 'legal_name' => 'Default University'])->id,
        'office' => 'guidance',
        'status' => 'active',
    ]);
    endpointAuthorizeStaffSession($this, $outsider);

    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $fixture['item']->id,
    ]);

    $this->actingAs($outsider)->postJson($url, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'outsider-test-'.Str::uuid(),
    ])->assertNotFound();

    // 2. Cross-run capture ID: capture belonging to run A requested via run B URL returns 404
    $otherFixture = endpointVerificationFixture($this, $fixture['actor']);
    $crossUrl = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $otherFixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $fixture['item']->id,
    ]);

    $this->actingAs($fixture['actor'])->postJson($crossUrl, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'cross-run-test-'.Str::uuid(),
    ])->assertNotFound();

    // 3. Cross-capture item ID: item belonging to capture A requested with capture B URL returns 404
    $crossItemUrl = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $fixture['capture']->id,
        'item' => $otherFixture['item']->id,
    ]);

    $this->actingAs($fixture['actor'])->postJson($crossItemUrl, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'cross-item-test-'.Str::uuid(),
    ])->assertNotFound();
});

test('gate-valid failed verification evidence is reviewable without changing machine outcome and closes after retake', function (): void {
    $fixture = endpointVerificationFixture($this);
    $capture = $fixture['capture'];
    $summary = [
        'verification_result' => ['gates_pass' => true, 'failure_reason' => 'capture_needs_review', 'needs_review_count' => 1],
        'needs_review_count' => 1,
        'evidence' => ['items' => [[
            'native_label' => 'VERBAL_Q_1',
            'page_no' => 1,
            'area_code' => 'verbal',
            'question_no' => 1,
            'options' => [['code' => 'A'], ['code' => 'B'], ['code' => 'C'], ['code' => 'D']],
            'detected' => [
                'state' => 'ambiguous',
                'selected_option' => null,
                'observed_options' => ['A', 'B'],
                'option_observations' => [],
                'confidence' => 0.4,
                'issue_codes' => ['multiple_marks'],
            ],
        ]]],
    ];
    $capture = $capture->replicate();
    $capture->attempt = 1;
    $capture->attempt_no = 2;
    $capture->attempt_state = OmrTemplateVerificationCapture::ATTEMPT_FAILED;
    $capture->status = OmrTemplateVerificationCapture::STATUS_FAIL;
    $capture->actual = 'answer_vector_mismatch';
    $capture->failure_code = 'capture_needs_review';
    $capture->vector_match = false;
    $capture->summary = $summary;
    $capture->lock_version = 0;
    $capture->save();

    $item = $fixture['item']->replicate();
    $item->capture_id = $capture->getKey();
    $item->state = OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS;
    $item->status = OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS;
    $item->detected = null;
    $item->save();

    DB::table('omr_template_verification_method_pages')->where('id', $fixture['page']->id)->update([
        'page_state' => OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW,
        'selected_attempt_id' => null,
    ]);

    $evidenceUrl = route('setup.omr.templates.canonical.verification_runs.evidence', [
        'run' => $fixture['run']->id,
        'method' => $capture->method,
        'capture_id' => $capture->id,
    ]);
    $evidence = $this->actingAs($fixture['actor'])->getJson($evidenceUrl)->assertOk()->json();
    expect($evidence['reviewable'])->toBeTrue()
        ->and($evidence['parent_version'])->toBe(0)
        ->and($evidence['review_reasons'][0]['code'])->toBe('answer_vector_mismatch')
        ->and($evidence['review_note_max'])->toBe(2000)
        ->and($evidence['items'][0]['reviewable'])->toBeTrue()
        ->and($evidence['items'][0]['item_revision'])->toBe(0);

    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $capture->id,
        'item' => $item->id,
    ]);
    $payload = [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => 'faint_mark',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'gate-valid-blank-'.Str::uuid(),
    ];
    $first = $this->actingAs($fixture['actor'])->postJson($url, $payload)->assertOk()->json();
    $replay = $this->actingAs($fixture['actor'])->postJson($url, $payload)->assertOk()->json();
    $freshCapture = $capture->fresh();
    $reviewedEvidence = $this->actingAs($fixture['actor'])->getJson($evidenceUrl)->assertOk()->json();
    expect($reviewedEvidence['reviewable'])->toBeTrue()
        ->and($reviewedEvidence['parent_version'])->toBe(1)
        ->and($reviewedEvidence['items'][0]['parent_version'])->toBe(1)
        ->and($reviewedEvidence['items'][0]['item_revision'])->toBe(1);
    expect($replay['receipt']['revision_id'])->toBe($first['receipt']['revision_id'])
        ->and($freshCapture->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED)
        ->and($freshCapture->status)->toBe(OmrTemplateVerificationCapture::STATUS_FAIL)
        ->and($freshCapture->actual)->toBe('answer_vector_mismatch')
        ->and($freshCapture->failure_code)->toBe('capture_needs_review')
        ->and($freshCapture->vector_match)->toBeFalse()
        ->and($freshCapture->evidence_hash)->toBe($fixture['capture']->evidence_hash)
        ->and($freshCapture->lock_version)->toBe(1)
        ->and($freshCapture->summary)->toBe($summary)
        ->and(OmrItemReviewRevision::query()->count())->toBe(1)
        ->and($fixture['run']->fresh()->status)->toBe($fixture['run']->status)
        ->and($fixture['method']->fresh()->method_state)->toBe($fixture['method']->method_state);

    $retake = $capture->replicate();
    $retake->attempt = 1;
    $retake->attempt_no = 3;
    $retake->lock_version = 0;
    $retake->save();
    $closed = $this->actingAs($fixture['actor'])->getJson($evidenceUrl)->assertOk()->json();
    expect($closed['reviewable'])->toBeFalse()
        ->and($closed['items'][0]['reviewable'])->toBeFalse()
        ->and($closed['review_reasons'][0]['code'])->toBe('verification_capture_binding_stale');
    $this->actingAs($fixture['actor'])->postJson($url, [
        ...$payload,
        'idempotency_key' => 'gate-valid-before-retake-'.Str::uuid(),
    ])->assertStatus(409)->assertJson(['code' => 'omr_review_not_reviewable']);
});

test('verification failed-capture review rejects active discarded binding-rejected unknown-gate and wrong-page evidence', function (): void {
    foreach (['active', 'discarded', 'binding_rejected', 'unknown_gates', 'wrong_page', 'wrong_method'] as $case) {
        $fixture = endpointVerificationFixture($this);
        $capture = $fixture['capture'];
        $summary = [
            'verification_result' => [
                'gates_pass' => $case === 'unknown_gates' ? null : true,
                'failure_reason' => 'capture_needs_review',
                'needs_review_count' => 1,
            ],
            'needs_review_count' => 1,
            'evidence' => ['items' => [[
                'native_label' => 'VERBAL_Q_1',
                'page_no' => 1,
                'area_code' => 'verbal',
                'question_no' => 1,
                'options' => [['code' => 'A'], ['code' => 'B'], ['code' => 'C'], ['code' => 'D']],
                'detected' => [
                    'state' => 'ambiguous',
                    'selected_option' => null,
                    'observed_options' => ['A', 'B'],
                    'option_observations' => [],
                    'confidence' => 0.4,
                    'issue_codes' => ['multiple_marks'],
                ],
            ]]],
        ];
        $capture = $capture->replicate();
        $capture->attempt = 1;
        $capture->attempt_no = 2;
        $capture->attempt_state = $case === 'active'
            ? OmrTemplateVerificationCapture::ATTEMPT_ACTIVE
            : OmrTemplateVerificationCapture::ATTEMPT_FAILED;
        $capture->status = OmrTemplateVerificationCapture::STATUS_FAIL;
        $capture->actual = $case === 'binding_rejected' ? 'typed_rejection' : 'answer_vector_mismatch';
        $capture->failure_code = $case === 'binding_rejected' ? 'binding_rejected' : 'capture_needs_review';
        $capture->vector_match = false;
        $capture->summary = $summary;
        $capture->lock_version = 0;
        if ($case === 'wrong_page') {
            $capture->page_no = 2;
        }
        if ($case === 'wrong_method') {
            $capture->method = OmrTemplateVerificationMethod::METHOD_HAND_SHADED;
            $capture->medium = OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL;
        }
        $capture->save();

        $item = $fixture['item']->replicate();
        $item->capture_id = $capture->getKey();
        $item->save();
        DB::table('omr_template_verification_method_pages')->where('id', $fixture['page']->id)->update([
            'page_state' => OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW,
            'selected_attempt_id' => null,
        ]);
        if ($case === 'discarded') {
            DB::table('omr_template_verification_captures')->where('id', $capture->id)->update([
                'discarded_at' => now(),
                'discarded_by_user_id' => $fixture['actor']->id,
                'discard_reason' => 'test discard',
            ]);
        }

        $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
            'run' => $fixture['run']->id,
            'capture' => $capture->id,
            'item' => $item->id,
        ]);
        $this->actingAs($fixture['actor'])->postJson($url, [
            'decision' => OmrReviewDecision::Blank->value,
            'selected_option' => null,
            'reason_code' => 'faint_mark',
            'expected_parent_version' => 0,
            'expected_item_revision' => 0,
            'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
            'idempotency_key' => 'ineligible-'.$case.'-'.Str::uuid(),
        ])->assertStatus(409)->assertJson([
            'outcome' => 'failed',
            'code' => 'omr_review_not_reviewable',
        ]);

        expect(OmrItemReviewRevision::query()->count())->toBe(0)
            ->and($capture->fresh()->lock_version)->toBe(0);
    }
});

test('unreadable persisted machine evidence can receive a reasoned blank annotation', function (): void {
    $fixture = endpointVerificationFixture($this);
    $summary = [
        'verification_result' => ['gates_pass' => true, 'failure_reason' => 'capture_needs_review', 'needs_review_count' => 1],
        'needs_review_count' => 1,
        'evidence' => ['items' => [[
            'native_label' => 'VERBAL_Q_1',
            'page_no' => 1,
            'area_code' => 'verbal',
            'question_no' => 1,
            'options' => [['code' => 'A'], ['code' => 'B'], ['code' => 'C'], ['code' => 'D']],
            'detected' => [
                'state' => 'unreadable',
                'selected_option' => null,
                'observed_options' => [],
                'option_observations' => [],
                'confidence' => null,
                'issue_codes' => ['faint_mark'],
            ],
        ]]],
    ];
    $capture = $fixture['capture']->replicate();
    $capture->attempt = 1;
    $capture->attempt_no = 2;
    $capture->attempt_state = OmrTemplateVerificationCapture::ATTEMPT_FAILED;
    $capture->status = OmrTemplateVerificationCapture::STATUS_FAIL;
    $capture->actual = 'answer_vector_mismatch';
    $capture->failure_code = 'capture_needs_review';
    $capture->vector_match = false;
    $capture->summary = $summary;
    $capture->lock_version = 0;
    $capture->save();
    $item = $fixture['item']->replicate();
    $item->capture_id = $capture->getKey();
    $item->state = 'unreadable';
    $item->status = 'unreadable';
    $item->detected = null;
    $item->save();
    DB::table('omr_template_verification_method_pages')->where('id', $fixture['page']->id)->update([
        'page_state' => OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW,
        'selected_attempt_id' => null,
    ]);

    $url = route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $fixture['run']->id,
        'capture' => $capture->id,
        'item' => $item->id,
    ]);
    $this->actingAs($fixture['actor'])->postJson($url, [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => 'unreadable_mark',
        'expected_parent_version' => 0,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => $fixture['evidenceHash'],
        'idempotency_key' => 'unreadable-blank-'.Str::uuid(),
    ])->assertOk()->assertJson([
        'outcome' => 'succeeded',
        'review' => ['decision' => 'blank', 'reason_code' => 'unreadable_mark'],
    ]);

    expect($capture->fresh()->actual)->toBe('answer_vector_mismatch')
        ->and($capture->fresh()->status)->toBe(OmrTemplateVerificationCapture::STATUS_FAIL)
        ->and($capture->fresh()->lock_version)->toBe(1)
        ->and($capture->fresh()->summary)->toBe($summary);
});
