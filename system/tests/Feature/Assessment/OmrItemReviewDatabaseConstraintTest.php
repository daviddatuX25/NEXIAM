<?php

use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/OmrItemReviewEndpointTest.php';

uses(RefreshDatabase::class);

/**
 * @return array{actor:User,sheet:OmrCaptureSheet,item:OmrTemplateVerificationItemEvidence}
 */
function omrReviewConstraintFixture(TestCase $test): array
{
    $actor = User::factory()->create();
    $gradingFixture = endpointGradingFixture($test);
    $sheet = $gradingFixture['sheet'];

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'DRAFT-'.Str::uuid(),
        'name' => 'Constraint Draft',
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
        'evidence_hash' => hash('sha256', 'capture-evidence'),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
        'lock_version' => 0,
    ]);

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
        'means' => ['A' => 0.8],
        'delta' => 0.7,
        'status' => 'match',
        'evidence_version' => 'v1',
        'evidence_hash' => hash('sha256', 'item-evidence'),
        'row_version' => 0,
    ]);

    return compact('actor', 'sheet', 'item');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validReviewRevisionPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'verification_item_evidence_id' => null,
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 1,
        'supersedes_revision_id' => null,
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
        'reason_code' => null,
        'note' => 'Reviewed against bubble crop.',
        'reviewed_machine_evidence_hash' => hash('sha256', 'evidence-hash-1'),
        'parent_version' => 1,
        'reviewed_by_user_id' => $fixture['actor']->id,
        'command_name' => 'omr.item_review.record',
        'idempotency_key' => 'idempotency-'.Str::uuid(),
        'correlation_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

test('review revisions require exclusive parent context in model and PostgreSQL check constraint', function (): void {
    $fixture = omrReviewConstraintFixture($this);

    // Valid verification-only parent
    $verificationRevision = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => null,
        'item_key' => 'VERBAL_Q_1',
    ]));
    expect($verificationRevision)->toBeInstanceOf(OmrItemReviewRevision::class)
        ->and($verificationRevision->verification_item_evidence_id)->toBe($fixture['item']->id)
        ->and($verificationRevision->capture_sheet_id)->toBeNull();

    // Valid grading-only parent
    $gradingRevision = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => null,
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
    ]));
    expect($gradingRevision)->toBeInstanceOf(OmrItemReviewRevision::class)
        ->and($gradingRevision->capture_sheet_id)->toBe($fixture['sheet']->id)
        ->and($gradingRevision->verification_item_evidence_id)->toBeNull();

    // Model rejects dual parent
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => $fixture['sheet']->id,
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_context_invalid');

    // Model rejects null parent
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => null,
        'capture_sheet_id' => null,
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_context_invalid');

    // Database check constraint on PostgreSQL
    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'verification_item_evidence_id' => $fixture['item']->id,
            'capture_sheet_id' => $fixture['sheet']->id,
        ])))->toThrow(QueryException::class);

        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'verification_item_evidence_id' => null,
            'capture_sheet_id' => null,
        ])))->toThrow(QueryException::class);
    }
});

test('review revisions enforce decision and selection integrity constraints', function (): void {
    $fixture = omrReviewConstraintFixture($this);

    // Unknown decision rejected by model and PostgreSQL check constraint
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'decision' => 'unapproved_status',
        'selected_option' => 'A',
    ])))->toThrow(ValueError::class);

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'decision' => 'unapproved_status',
            'selected_option' => 'A',
        ])))->toThrow(QueryException::class);
    }

    // confirm_machine requires selected_option
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => null,
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_selection_required');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'decision' => OmrReviewDecision::ConfirmMachine->value,
            'selected_option' => null,
        ])))->toThrow(QueryException::class);
    }

    // set_option requires selected_option
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => null,
        'reason_code' => 'manual_correction',
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_selection_required');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'decision' => OmrReviewDecision::SetOption->value,
            'selected_option' => null,
            'reason_code' => 'manual_correction',
        ])))->toThrow(QueryException::class);
    }

    // blank forbids selected_option
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => 'A',
        'reason_code' => 'reviewer_cleared',
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_selection_forbidden');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'decision' => OmrReviewDecision::Blank->value,
            'selected_option' => 'A',
            'reason_code' => 'reviewer_cleared',
        ])))->toThrow(QueryException::class);
    }

    // unresolved forbids selected_option
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'decision' => OmrReviewDecision::Unresolved->value,
        'selected_option' => 'B',
        'reason_code' => 'cannot_determine',
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_selection_forbidden');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'decision' => OmrReviewDecision::Unresolved->value,
            'selected_option' => 'B',
            'reason_code' => 'cannot_determine',
        ])))->toThrow(QueryException::class);
    }

    // unresolved requires reason_code
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'decision' => OmrReviewDecision::Unresolved->value,
        'selected_option' => null,
        'reason_code' => null,
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_reason_required');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'decision' => OmrReviewDecision::Unresolved->value,
            'selected_option' => null,
            'reason_code' => null,
        ])))->toThrow(QueryException::class);
    }

    // Invalid reason_code pattern (capital letters, symbols)
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'reason_code' => 'INVALID-REASON-123!',
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_reason_code_invalid');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'reason_code' => 'INVALID-REASON-123!',
        ])))->toThrow(QueryException::class);
    }

    // Note exceeding 2000 characters
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'note' => str_repeat('n', 2001),
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_note_too_long');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'note' => str_repeat('n', 2001),
        ])))->toThrow(QueryException::class);
    }

    // Blank item_key
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'item_key' => '   ',
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_item_key_invalid');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'item_key' => '   ',
        ])))->toThrow(QueryException::class);
    }

    // Revision < 1
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'revision' => 0,
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_version_invalid');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'revision' => 0,
        ])))->toThrow(QueryException::class);
    }

    // Invalid evidence hash format
    expect(fn () => OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'reviewed_machine_evidence_hash' => 'short-hash',
    ])))->toThrow(RuntimeException::class, 'omr_review_revision_evidence_hash_invalid');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
            'reviewed_machine_evidence_hash' => 'short-hash',
        ])))->toThrow(QueryException::class);
    }
});

test('review revision uniqueness is strictly enforced per parent and item key', function (): void {
    $fixture = omrReviewConstraintFixture($this);

    // 1. Grading context uniqueness
    $firstGrading = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 1,
    ]));
    expect($firstGrading->revision)->toBe(1);

    // Same sheet + item_key + revision 1 must be rejected by unique index
    expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 1,
    ])))->toThrow(QueryException::class);

    // Same sheet + item_key + revision 2 is allowed
    $secondGrading = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 2,
        'supersedes_revision_id' => $firstGrading->id,
    ]));
    expect($secondGrading->revision)->toBe(2)
        ->and($secondGrading->supersedes_revision_id)->toBe($firstGrading->id);

    // Same sheet + different item_key + revision 1 is allowed
    $otherItemGrading = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_2',
        'revision' => 1,
    ]));
    expect($otherItemGrading->item_key)->toBe('AREA_Q_2');

    // 2. Verification context uniqueness
    $firstVerification = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => null,
        'item_key' => 'VERBAL_Q_1',
        'revision' => 1,
    ]));
    expect($firstVerification->revision)->toBe(1);

    // Same verification item + item_key + revision 1 must be rejected
    expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => null,
        'item_key' => 'VERBAL_Q_1',
        'revision' => 1,
    ])))->toThrow(QueryException::class);

    // Same verification item + item_key + revision 2 is allowed
    $secondVerification = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => null,
        'item_key' => 'VERBAL_Q_1',
        'revision' => 2,
        'supersedes_revision_id' => $firstVerification->id,
    ]));
    expect($secondVerification->revision)->toBe(2);
});

test('review revision foreign keys enforce existence and restrictOnDelete parent deletion', function (): void {
    $fixture = omrReviewConstraintFixture($this);

    // Invalid verification item FK rejected
    expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => 999999,
        'capture_sheet_id' => null,
    ])))->toThrow(QueryException::class);

    // Invalid capture sheet FK rejected
    expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => null,
        'capture_sheet_id' => 999999,
    ])))->toThrow(QueryException::class);

    // Invalid reviewer user FK rejected
    expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
        'reviewed_by_user_id' => 999999,
    ])))->toThrow(QueryException::class);

    // Invalid supersedes_revision_id FK rejected
    expect(fn () => DB::table('omr_item_review_revisions')->insert(validReviewRevisionPayload($fixture, [
        'supersedes_revision_id' => 999999,
    ])))->toThrow(QueryException::class);

    // Create real revisions for sheet, item, and user
    OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'verification_item_evidence_id' => null,
    ]));

    OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => null,
        'verification_item_evidence_id' => $fixture['item']->id,
        'item_key' => 'VERBAL_Q_1',
    ]));

    // restrictOnDelete prevents deleting parent sheet while review revision exists
    expect(fn () => $fixture['sheet']->delete())->toThrow(QueryException::class);

    // verification item model blocks deleting on model level (verification_item_evidence_immutable)
    expect(fn () => $fixture['item']->delete())->toThrow(RuntimeException::class, 'verification_item_evidence_immutable');

    // restrictOnDelete prevents deleting reviewer user while review revision exists
    expect(fn () => $fixture['actor']->delete())->toThrow(QueryException::class);
});

test('review revisions are append-only and trigger rejects update and delete on PostgreSQL', function (): void {
    $fixture = omrReviewConstraintFixture($this);

    $revision = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 1,
    ]));

    // Model booted checks reject update and delete on all platforms
    expect(fn () => $revision->update(['note' => 'attempted-modification']))
        ->toThrow(RuntimeException::class, 'omr_review_revision_immutable');

    expect(fn () => $revision->delete())
        ->toThrow(RuntimeException::class, 'omr_review_revision_immutable');

    // Direct SQL bypassing Eloquent
    if (DB::connection()->getDriverName() === 'pgsql') {
        // Trigger omr_item_review_revision_immutable_guard fires BEFORE UPDATE
        expect(fn () => DB::table('omr_item_review_revisions')
            ->where('id', $revision->id)
            ->update(['note' => 'sql-update-attempt'])
        )->toThrow(QueryException::class);

        // Trigger omr_item_review_revision_immutable_guard fires BEFORE DELETE
        expect(fn () => DB::table('omr_item_review_revisions')
            ->where('id', $revision->id)
            ->delete()
        )->toThrow(QueryException::class);

        // Row must be untouched
        $fresh = DB::table('omr_item_review_revisions')->where('id', $revision->id)->first();
        expect($fresh)->not->toBeNull()
            ->and($fresh->note)->toBe('Reviewed against bubble crop.');
    }
});

test('latest revision queries resolve current state across revisions without N+1 regression', function (): void {
    $fixture = omrReviewConstraintFixture($this);

    $rev1 = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 1,
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
    ]));

    $rev2 = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_1',
        'revision' => 2,
        'supersedes_revision_id' => $rev1->id,
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => 'B',
        'reason_code' => 'reviewer_corrected',
    ]));

    $item2Rev1 = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'capture_sheet_id' => $fixture['sheet']->id,
        'item_key' => 'AREA_Q_2',
        'revision' => 1,
        'decision' => OmrReviewDecision::Blank->value,
        'selected_option' => null,
        'reason_code' => 'mark_erased',
    ]));

    // Query latest for grading items
    $gradingReviews = OmrItemReviewRevision::latestForGradingItems(
        (int) $fixture['sheet']->id,
        ['AREA_Q_1', 'AREA_Q_2', 'AREA_Q_3'],
    );

    expect($gradingReviews)->toHaveKey('AREA_Q_1')
        ->and($gradingReviews['AREA_Q_1']->id)->toBe($rev2->id)
        ->and($gradingReviews['AREA_Q_1']->revision)->toBe(2)
        ->and($gradingReviews['AREA_Q_1']->selected_option)->toBe('B')
        ->and($gradingReviews)->toHaveKey('AREA_Q_2')
        ->and($gradingReviews['AREA_Q_2']->id)->toBe($item2Rev1->id)
        ->and($gradingReviews['AREA_Q_2']->revision)->toBe(1)
        ->and($gradingReviews['AREA_Q_2']->decision)->toBe(OmrReviewDecision::Blank)
        ->and($gradingReviews)->not->toHaveKey('AREA_Q_3');

    // Query latest for verification items
    $verRev1 = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => null,
        'item_key' => 'VERBAL_Q_1',
        'revision' => 1,
        'decision' => OmrReviewDecision::ConfirmMachine->value,
        'selected_option' => 'A',
    ]));

    $verRev2 = OmrItemReviewRevision::query()->create(validReviewRevisionPayload($fixture, [
        'verification_item_evidence_id' => $fixture['item']->id,
        'capture_sheet_id' => null,
        'item_key' => 'VERBAL_Q_1',
        'revision' => 2,
        'supersedes_revision_id' => $verRev1->id,
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => 'C',
        'reason_code' => 'bubble_recheck',
    ]));

    $verificationReviews = OmrItemReviewRevision::latestForVerificationItems([(int) $fixture['item']->id, 999999]);
    expect($verificationReviews)->toHaveKey((int) $fixture['item']->id)
        ->and($verificationReviews[(int) $fixture['item']->id]->id)->toBe($verRev2->id)
        ->and($verificationReviews[(int) $fixture['item']->id]->revision)->toBe(2)
        ->and($verificationReviews[(int) $fixture['item']->id]->selected_option)->toBe('C')
        ->and($verificationReviews)->not->toHaveKey(999999);
});
