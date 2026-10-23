<?php

use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

uses(RefreshDatabase::class);

function verificationRunRow(User $actor, string $status = OmrTemplateVerificationRun::STATUS_QUEUED, array $overrides = [], ?int $draftId = null): OmrTemplateVerificationRun
{
    static $draftSequence = 0;
    $draftId ??= OmrTemplateAuthoringDraft::query()->create([
        'code' => 'VERIFY-RUN-'.(++$draftSequence),
        'name' => 'Verification run draft',
        'source_kind' => 'html',
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [],
        'lock_version' => 0,
        'owner_user_id' => $actor->id,
    ])->id;

    $values = [
        'draft_id' => $draftId,
        'status' => $status,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'progress_done' => 0,
        'progress_total' => 0,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ];

    return OmrTemplateVerificationRun::query()->create([...$values, ...$overrides]);
}

test('every run phase transition advances status, progress, and optimistic version', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor);
    $runs = app(OmrVerificationRunService::class);

    expect($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_QUEUED, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF, ['last_heartbeat_at' => now()]))->toBeTrue()
        ->and($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF, OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY, ['progress_total' => 14]))->toBeTrue()
        ->and($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, ['progress_done' => 1]))->toBeTrue();

    for ($done = 2; $done <= 14; $done++) {
        expect($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, [
            'progress_done' => $done,
            'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'variant-'.$done],
        ]))->toBeTrue();
    }

    expect($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, OmrTemplateVerificationRun::STATUS_PASSED, [
        'result_summary' => ['ok' => true],
        'phase_detail' => ['stage' => 'passed', 'current_variant' => null],
        'finished_at' => now(),
    ]))->toBeTrue();

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->progress_done)->toBe(14)
        ->and($run->progress_total)->toBe(14)
        ->and($run->phase_detail['current_variant'])->toBeNull()
        ->and($run->optimistic_version)->toBe(17)
        ->and($run->isTerminal())->toBeTrue()
        ->and($run->isActive())->toBeFalse();
});

test('queued claim is idempotent: a second worker cannot advance a claimed run', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor);
    $runs = app(OmrVerificationRunService::class);

    expect($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_QUEUED, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF))->toBeTrue();
    expect($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_QUEUED, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF))->toBeFalse();
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_PREPARING_PROOF);
});

test('a terminal run rejects any further transition', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, ['finished_at' => now()]);
    $runs = app(OmrVerificationRunService::class);

    expect($runs->transition($run->id, OmrTemplateVerificationRun::STATUS_FAILED, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF))->toBeFalse()
        ->and($runs->fail($run->id, 'another_failure', 'ignored'))->toBeFalse();
});

test('fail preserves evidence rows and records a safe error', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, ['progress_done' => 3, 'progress_total' => 14]);
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => 'digital',
        'variant' => 'clean',
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $runs = app(OmrVerificationRunService::class);

    expect($runs->fail($run->id, 'no_go_variant_failed', 'Variants failed the declared outcome.'))->toBeTrue();

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->error_code)->toBe('no_go_variant_failed')
        ->and($run->safe_error)->toBe('Variants failed the declared outcome.')
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->captures()->count())->toBe(1);
});

test('retry creates the next attempt against the same pinned plan and preserves prior evidence', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, ['finished_at' => now()]);
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => 'digital',
        'variant' => 'clean',
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $pinned = $run->only(['plan_hash', 'artifact_hash', 'native_bundle_hash', 'manifest_hash', 'config_hash', 'mapping_hash']);
    $runs = app(OmrVerificationRunService::class);

    $retried = $runs->retry($run->id);

    expect($retried->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($retried->attempt)->toBe(2)
        ->and($retried->only(['plan_hash', 'artifact_hash', 'native_bundle_hash', 'manifest_hash', 'config_hash', 'mapping_hash']))->toBe($pinned)
        ->and($retried->progress_done)->toBe(0)
        ->and($retried->error_code)->toBeNull()
        ->and($retried->safe_error)->toBeNull()
        ->and($retried->finished_at)->toBeNull()
        ->and($retried->captures()->count())->toBe(1) // attempt-1 evidence retained
        ->and($retried->captures()->first()->attempt)->toBe(1);
});

test('retry refuses when another run is already active for the same target', function (): void {
    $actor = User::factory()->create();
    $draftId = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'VERIFY-RUN-CONFLICT', 'name' => 'Conflict', 'source_kind' => 'html',
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, 'authoring_json' => [],
        'lock_version' => 0, 'owner_user_id' => $actor->id,
    ])->id;
    $failed = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, ['finished_at' => now()], $draftId);
    verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, [], $draftId);
    $runs = app(OmrVerificationRunService::class);

    expect(fn () => $runs->retry($failed->id))->toThrow(RuntimeException::class, 'verification_active_run_in_progress');
});

test('retry refuses non-failed runs', function (): void {
    $actor = User::factory()->create();
    $passed = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_PASSED, ['finished_at' => now()]);
    $runs = app(OmrVerificationRunService::class);

    expect(fn () => $runs->retry($passed->id))->toThrow(RuntimeException::class, 'verification_retry_status_invalid');
});

test('worker timeout finalizes only stale-heartbeat worker-owned runs', function (): void {
    $actor = User::factory()->create();
    $stale = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, ['last_heartbeat_at' => now()->subHours(2)]);
    $fresh = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, ['last_heartbeat_at' => now()]);
    $runs = app(OmrVerificationRunService::class);

    expect($runs->finalizeTimedOut($stale->id))->toBeTrue();
    expect($stale->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($stale->fresh()->error_code)->toBe('worker_timeout');
    expect($runs->finalizeTimedOut($fresh->id))->toBeFalse();
    expect($fresh->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX);
});

test('human-controlled waiting states never timeout from an old heartbeat', function (): void {
    $actor = User::factory()->create();
    $awaitingPrint = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_AWAITING_PRINT, ['last_heartbeat_at' => now()->subHours(2)]);
    $awaitingCapture = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE, ['last_heartbeat_at' => now()->subHours(2)]);
    $needsReview = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW, ['last_heartbeat_at' => now()->subHours(2)]);
    $runs = app(OmrVerificationRunService::class);

    expect($runs->finalizeTimedOut($awaitingPrint->id))->toBeFalse()
        ->and($runs->finalizeTimedOut($awaitingCapture->id))->toBeFalse()
        ->and($runs->finalizeTimedOut($needsReview->id))->toBeFalse()
        ->and($awaitingPrint->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($awaitingCapture->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($needsReview->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW);
});

test('physical retry resumes an unprinted challenge at awaiting_print with the same challenge', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, [
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'challenge_code' => 'CHAL-A3620EF501',
        'finished_at' => now(),
    ]);
    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'mode' => OmrPhysicalChallenge::MODE_QUICK,
        'challenge_code' => $run->challenge_code,
        'plan_hash' => $run->plan_hash,
        'expected_vector_hash' => $run->expected_vector_hash,
        'status' => OmrPhysicalChallenge::STATUS_CREATED,
        'created_by_user_id' => $actor->id,
    ]);

    $retried = app(OmrPhysicalAssuranceCoordinator::class)->retryRun($run, $actor);

    expect($retried->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($retried->attempt)->toBe(2)
        ->and($retried->challenge_code)->toBe($challenge->challenge_code)
        ->and($retried->challenges()->first()->id)->toBe($challenge->id)
        ->and($retried->challenges()->first()->status)->toBe(OmrPhysicalChallenge::STATUS_CREATED);
});

test('physical retry resumes a printed challenge at awaiting_capture with the same challenge', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, [
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'challenge_code' => 'CHAL-B3620EF501',
        'finished_at' => now(),
    ]);
    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'mode' => OmrPhysicalChallenge::MODE_QUICK,
        'challenge_code' => $run->challenge_code,
        'plan_hash' => $run->plan_hash,
        'expected_vector_hash' => $run->expected_vector_hash,
        'status' => OmrPhysicalChallenge::STATUS_PRINTED,
        'created_by_user_id' => $actor->id,
    ]);

    $retried = app(OmrPhysicalAssuranceCoordinator::class)->retryRun($run, $actor);

    expect($retried->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($retried->attempt)->toBe(2)
        ->and($retried->challenge_code)->toBe($challenge->challenge_code)
        ->and($retried->challenges()->first()->id)->toBe($challenge->id)
        ->and($retried->challenges()->first()->status)->toBe(OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE);
});

test('physical retry refuses when its challenge is missing', function (): void {
    $actor = User::factory()->create();
    $run = verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, [
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'challenge_code' => 'CHAL-MISSING01',
        'finished_at' => now(),
    ]);

    expect(fn () => app(OmrVerificationRunService::class)->retry($run->id))
        ->toThrow(RuntimeException::class, 'physical_challenge_missing');

    $unchanged = $run->fresh();
    expect($unchanged->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($unchanged->attempt)->toBe(1);
});

test('the active-run partial unique index allows only one active run per target', function (): void {
    $actor = User::factory()->create();
    $draftId = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'VERIFY-RUN-UNIQUE', 'name' => 'Unique', 'source_kind' => 'html',
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, 'authoring_json' => [],
        'lock_version' => 0, 'owner_user_id' => $actor->id,
    ])->id;
    verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_QUEUED, [], $draftId);
    verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_FAILED, ['finished_at' => now()], $draftId);

    // A second ACTIVE run for the same target violates the partial unique index.
    // On PostgreSQL a failed INSERT poisons the surrounding transaction; run it
    // inside its own savepoint so the rest of the test keeps executing.
    DB::beginTransaction();
    try {
        verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_QUEUED, [], $draftId);
        DB::rollBack();
        $this->fail('Expected the partial unique index to reject a second active run.');
    } catch (QueryException) {
        DB::rollBack();
    }

    // Terminal runs release the slot.
    OmrTemplateVerificationRun::query()->where('draft_id', $draftId)->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)->update(['status' => OmrTemplateVerificationRun::STATUS_FAILED, 'finished_at' => now()]);
    expect(verificationRunRow($actor, OmrTemplateVerificationRun::STATUS_QUEUED, [], $draftId)->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED);
});

test('run status CHECKs reject invalid values on PostgreSQL (exercised in Compose runtime)', function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('PostgreSQL CHECK constraints are exercised in the Compose runtime.');
    }
    $actor = User::factory()->create();
    $draftId = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'VERIFY-RUN-CHECK', 'name' => 'Check', 'source_kind' => 'html',
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, 'authoring_json' => [],
        'lock_version' => 0, 'owner_user_id' => $actor->id,
    ])->id;

    expect(fn () => DB::table('omr_template_verification_runs')->insert([
        'draft_id' => $draftId,
        'status' => 'not-a-status',
        'mode' => 'digital',
        'attempt' => 0,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'started_by_user_id' => $actor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('digital no-go false_accept evidence is allowed by the capture contract', function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('PostgreSQL CHECK constraints are exercised in the Compose runtime.');
    }
    $actor = User::factory()->create();
    $run = verificationRunRow($actor);
    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'variant' => 'missing_marker',
        'expected' => OmrTemplateVerificationCapture::ACTUAL_TYPED_REJECTION,
        'actual' => OmrTemplateVerificationCapture::ACTUAL_FALSE_ACCEPT,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'input_hash' => str_repeat('i', 64),
        'evidence_hash' => str_repeat('h', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    expect($capture->actual)->toBe(OmrTemplateVerificationCapture::ACTUAL_FALSE_ACCEPT);
});
