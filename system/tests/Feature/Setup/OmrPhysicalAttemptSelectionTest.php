<?php

use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\SelectOmrPhysicalAttempt;
use App\Assessment\Actions\Omr\Verification\SubmitOmrPhysicalCapture;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrDetectionPort;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function selectionChallengeSetup(): void
{
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'queue.default' => 'sync',
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);
}

function selectionChallengePlan(OmrTemplateAuthoringDraft $draft): OmrProofPlan
{
    $profile = $draft->markerProfile;
    $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
    $materialized = app(CanonicalArtifactMaterializer::class)
        ->materialize($draft->fresh(['markerProfile']), $profile, $candidateVersion > 0 ? $candidateVersion : null);
    $target = new CandidateArtifactDiagnosticTarget($draft->fresh(['markerProfile']), $materialized, $profile);

    return app(OmrProofPlanner::class)->plan($target, ProofPurpose::QuickRehearsal, OmrProofPlanner::CURRENT_PLAN_VERSION, OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
}

test('selection is method/page bound and supersedes only an explicit prior pass', function (): void {
    selectionChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $pack = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $run = $pack['run']->fresh();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), [
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'challenge_id' => (int) $pack['challenge']->id,
        'idempotency_key' => 'selection-print-'.$run->id,
    ])->assertAccepted();
    $plan = selectionChallengePlan($draft->fresh());
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();

    $submit = static function (string $key) use ($run, $actor, $pack, $plan): array {
        return app(SubmitOmrPhysicalCapture::class)->handle(
            run: $run->fresh(),
            actor: $actor,
            bytes: physicalChallengePng(),
            mime: 'image/png',
            captureSource: 'upload',
            pageNo: 1,
            methodName: OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            challengeId: (int) $pack['challenge']->id,
            targetHash: (string) $plan->targetHashes['artifact'],
            planHash: $plan->planHash(),
            expectedVectorHash: hash('sha256', json_encode($plan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            idempotencyKey: $key,
            autoSelect: false,
        );
    };

    $first = $submit('selection-first');
    $firstId = (int) $first['capture']->id;
    // First selection defaults structured reason without requiring non-empty text
    app(SelectOmrPhysicalAttempt::class)->handle(
        run: $run->fresh(),
        actor: $actor,
        method: OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        pageNo: 1,
        captureId: $firstId,
        reason: '',
    );
    expect(OmrTemplateVerificationCapture::query()->findOrFail($firstId)->selection_reason)
        ->toBe('staff_confirmed_exact_pass');

    app(SelectOmrPhysicalAttempt::class)->handle(
        run: $run->fresh(),
        actor: $actor,
        method: OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        pageNo: 1,
        captureId: $firstId,
        reason: 'Initial reviewed pass.',
        idempotencyKey: 'selection-first-command',
    );
    $methodPage = $run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->firstOrFail()->pages()->where('page_no', 1)->firstOrFail();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.pages.retake', [
        'run' => $run,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'page' => 1,
    ]), ['page_version' => $methodPage->page_version, 'idempotency_key' => 'retake-selection-replace'])->assertOk();
    $second = $submit('selection-replacement');
    $secondId = (int) $second['capture']->id;
    $methodPage = $methodPage->fresh();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.captures.select', [
        'run' => $run,
        'capture' => $secondId,
    ]), [
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'page_no' => 1,
        'reason' => '',
        'page_version' => $methodPage->page_version,
        'idempotency_key' => 'selection-after-retake-command',
    ])->assertOk()->assertJson([
        'selected_attempt_id' => $secondId,
        'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
    ]);

    $firstCapture = OmrTemplateVerificationCapture::query()->findOrFail($firstId);
    $secondCapture = OmrTemplateVerificationCapture::query()->findOrFail($secondId);
    expect($firstCapture->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED)
        ->and($firstCapture->superseded_by_attempt_id)->toBeNull()
        ->and($secondCapture->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED)
        ->and($secondCapture->selection_reason)->toBe('staff_confirmed_exact_pass')
        ->and($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);
});

test('failed attempts stay retained and cannot become selected', function (): void {
    selectionChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $pack = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $run = $pack['run']->fresh();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), [
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'challenge_id' => (int) $pack['challenge']->id,
        'idempotency_key' => 'selection-print-'.$run->id,
    ])->assertAccepted();
    $plan = selectionChallengePlan($draft->fresh());
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'mismatch'));
    physicalChallengeFakeWorker();
    $result = app(SubmitOmrPhysicalCapture::class)->handle(
        run: $run->fresh(),
        actor: $actor,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        pageNo: 1,
        methodName: OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        challengeId: (int) $pack['challenge']->id,
        idempotencyKey: 'failed-capture',
        autoSelect: false,
    );
    $capture = $result['capture']->fresh();

    expect($capture->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED)
        ->and(fn () => app(SelectOmrPhysicalAttempt::class)->handle(
            run: $run->fresh(),
            actor: $actor,
            method: OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            pageNo: 1,
            captureId: (int) $capture->id,
            reason: 'Do not select failed evidence.',
        ))->toThrow(RuntimeException::class, 'selection_attempt_not_passed')
        ->and(OmrTemplateVerificationCapture::query()->findOrFail($capture->id)->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED);
});
