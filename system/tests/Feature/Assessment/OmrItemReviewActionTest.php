<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RecordOmrItemReviewDecision;
use App\Assessment\Omr\Review\GradingOmrReviewContextResolver;
use App\Assessment\Omr\Review\OmrReviewContext;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * @return array{sheet:OmrCaptureSheet,actor:User,evidence_hash:string,item_key:string}
 */
function omrReviewActionFixture(TestCase $test): array
{
    $fixture = buildOmrGradingFixture($test);
    $actor = $fixture['guidance'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->firstOrFail();
    $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $actor, 'review-action-'.Str::uuid());

    $evidenceHash = hash('sha256', 'review-action-evidence');
    $itemKey = 'AREA_Q_1';
    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/review-action/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'review-action-asset'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => [
            'items' => [[
                'area_code' => 'AREA',
                'question_no' => 1,
                'options' => ['A', 'B', 'C'],
                'evidence_hash' => $evidenceHash,
                'machine' => [
                    'state' => 'marked',
                    'selected_option' => 'A',
                    'observed_options' => ['A'],
                    'option_observations' => [],
                    'confidence' => 0.96,
                    'issue_codes' => ['machine_uncertain'],
                ],
            ]],
        ],
    ]);

    return [
        'sheet' => $sheet,
        'actor' => $actor,
        'evidence_hash' => $evidenceHash,
        'item_key' => $itemKey,
    ];
}

function omrReviewActionContext(array $fixture): OmrReviewContext
{
    return app(GradingOmrReviewContextResolver::class)->resolve(
        (int) $fixture['sheet']->getKey(),
        $fixture['item_key'],
    );
}

test('item review appends one revision, bumps the parent, and records a redacted audit', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);
    $key = 'review-success-'.Str::uuid();
    $note = 'The reviewer compared the crop with the marked bubble.';

    $result = app(RecordOmrItemReviewDecision::class)->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        $context->parentVersion,
        $context->itemRevision,
        $fixture['evidence_hash'],
        $key,
        $fixture['actor'],
        null,
        null,
        $note,
    );

    expect($result->revision)->toBeInstanceOf(OmrItemReviewRevision::class)
        ->and($result->revision->revision)->toBe(1)
        ->and($result->revision->decision)->toBe(OmrReviewDecision::ConfirmMachine)
        ->and($result->revision->selected_option)->toBe('A')
        ->and($result->parentVersion)->toBe(1)
        ->and($result->itemRevision)->toBe(1);

    expect($fixture['sheet']->fresh()->version)->toBe(1)
        ->and(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(1);

    $audit = AuditEvent::query()->where('event_type', RecordOmrItemReviewDecision::AUDIT_EVENT)->latest('id')->firstOrFail();
    expect($audit->payload)->not->toHaveKey('note')
        ->and($audit->payload)->not->toHaveKey('idempotency_key')
        ->and($audit->payload)->not->toHaveKey('expected')
        ->and($audit->payload)->not->toHaveKey('correct')
        ->and($audit->payload['note_present'])->toBeTrue();

    $receipt = DB::table('omr_command_receipts')
        ->where('idempotency_key', $key)
        ->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('succeeded')
        ->and((string) $receipt->safe_result_json)->not->toContain($note);
});

test('stale parent and item versions fail before appending a revision', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);
    $action = app(RecordOmrItemReviewDecision::class);

    $action->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $fixture['evidence_hash'],
        'review-stale-first-'.Str::uuid(),
        $fixture['actor'],
    );

    expect(fn () => $action->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $fixture['evidence_hash'],
        'review-stale-parent-'.Str::uuid(),
        $fixture['actor'],
    ))->toThrow(RuntimeException::class, 'omr_review_parent_version_stale');

    $fresh = omrReviewActionContext($fixture);
    expect(fn () => $action->handle(
        $fresh,
        OmrReviewDecision::ConfirmMachine,
        $fresh->parentVersion,
        0,
        $fixture['evidence_hash'],
        'review-stale-item-'.Str::uuid(),
        $fixture['actor'],
    ))->toThrow(RuntimeException::class, 'omr_review_item_revision_stale');

    expect(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(1);
});

test('changed evidence hash fails closed without a mutation', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);

    expect(fn () => app(RecordOmrItemReviewDecision::class)->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        $context->parentVersion,
        $context->itemRevision,
        hash('sha256', 'different-evidence'),
        'review-hash-mismatch-'.Str::uuid(),
        $fixture['actor'],
    ))->toThrow(RuntimeException::class, 'omr_review_evidence_hash_mismatch');

    expect(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(0)
        ->and($fixture['sheet']->fresh()->version)->toBe(0);
});
test('changed option sets fail closed without appending a revision', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);
    $proposal = $fixture['sheet']->proposal_json;
    $proposal['items'][0]['options'] = ['A', 'B'];
    $fixture['sheet']->update(['proposal_json' => $proposal]);

    expect(fn () => app(RecordOmrItemReviewDecision::class)->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        $context->parentVersion,
        $context->itemRevision,
        $fixture['evidence_hash'],
        'review-option-set-stale-'.Str::uuid(),
        $fixture['actor'],
    ))->toThrow(RuntimeException::class, 'omr_review_option_set_stale');

    expect(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(0)
        ->and($fixture['sheet']->fresh()->version)->toBe(0);
});

test('review capability denial occurs before a receipt or revision is created', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $key = 'review-denied-'.Str::uuid();

    expect(fn () => app(RecordOmrItemReviewDecision::class)->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        $context->parentVersion,
        $context->itemRevision,
        $fixture['evidence_hash'],
        $key,
        $registrar,
    ))->toThrow(RuntimeException::class, 'omr_review_denied');

    expect(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(0)
        ->and(DB::table('omr_command_receipts')->where('idempotency_key', $key)->count())->toBe(0);
});

test('same review key replays the safe result and changed payload conflicts', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);
    $action = app(RecordOmrItemReviewDecision::class);
    $key = 'review-replay-'.Str::uuid();

    $first = $action->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $fixture['evidence_hash'],
        $key,
        $fixture['actor'],
    );
    $replay = $action->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $fixture['evidence_hash'],
        $key,
        $fixture['actor'],
    );

    expect($replay->revision->getKey())->toBe($first->revision->getKey())
        ->and($replay->correlationId)->toBe($first->correlationId)
        ->and(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', RecordOmrItemReviewDecision::AUDIT_EVENT)->where('payload->parent_id', $fixture['sheet']->id)->count())->toBe(1);

    expect(fn () => $action->handle(
        $context,
        OmrReviewDecision::Blank,
        0,
        0,
        $fixture['evidence_hash'],
        $key,
        $fixture['actor'],
        null,
        'cleared_by_reviewer',
    ))->toThrow(RuntimeException::class, 'omr_review_idempotency_conflict');

    expect(OmrItemReviewRevision::query()->where('capture_sheet_id', $fixture['sheet']->id)->count())->toBe(1);
});
test('same-parent saves reject a stale snapshot while independent sheets both commit', function (): void {
    $fixture = omrReviewActionFixture($this);
    $firstContext = omrReviewActionContext($fixture);
    $secondEvidenceHash = hash('sha256', 'independent-review-evidence');
    $secondSheet = OmrCaptureSheet::query()->create([
        'batch_id' => $fixture['sheet']->batch_id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/review-action/'.Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'independent-review-asset'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => [
            'items' => [[
                'area_code' => 'AREA',
                'question_no' => 1,
                'options' => ['A', 'B', 'C'],
                'evidence_hash' => $secondEvidenceHash,
                'machine' => [
                    'state' => 'marked',
                    'selected_option' => 'A',
                    'observed_options' => ['A'],
                    'option_observations' => [],
                    'confidence' => 0.96,
                    'issue_codes' => ['machine_uncertain'],
                ],
            ]],
        ],
    ]);
    $secondContext = app(GradingOmrReviewContextResolver::class)->resolve($secondSheet, $fixture['item_key']);
    $action = app(RecordOmrItemReviewDecision::class);

    // Two workers loaded the same parent snapshot. The lock and version check
    // allow one append, then reject the stale worker without a second row.
    $action->handle(
        $firstContext,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $fixture['evidence_hash'],
        'same-parent-first-'.Str::uuid(),
        $fixture['actor'],
    );
    expect(fn () => $action->handle(
        $firstContext,
        OmrReviewDecision::Blank,
        0,
        0,
        $fixture['evidence_hash'],
        'same-parent-stale-'.Str::uuid(),
        $fixture['actor'],
    ))->toThrow(RuntimeException::class, 'omr_review_parent_version_stale');

    $independent = $action->handle(
        $secondContext,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $secondEvidenceHash,
        'independent-sheet-'.Str::uuid(),
        $fixture['actor'],
    );

    expect($fixture['sheet']->fresh()->version)->toBe(1)
        ->and($secondSheet->fresh()->version)->toBe(1)
        ->and($independent->revision->revision)->toBe(1)
        ->and(OmrItemReviewRevision::query()->whereIn('capture_sheet_id', [$fixture['sheet']->id, $secondSheet->id])->count())->toBe(2);
});

test('review revisions remain immutable after they are appended', function (): void {
    $fixture = omrReviewActionFixture($this);
    $context = omrReviewActionContext($fixture);
    $result = app(RecordOmrItemReviewDecision::class)->handle(
        $context,
        OmrReviewDecision::ConfirmMachine,
        0,
        0,
        $fixture['evidence_hash'],
        'review-immutable-'.Str::uuid(),
        $fixture['actor'],
    );

    expect(fn () => $result->revision->update(['note' => 'tampered']))
        ->toThrow(RuntimeException::class, 'omr_review_revision_immutable');
    expect(fn () => $result->revision->delete())
        ->toThrow(RuntimeException::class, 'omr_review_revision_immutable');
});
