<?php

use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use Tests\Fixtures\Omr\ProofPlanFixtureTarget;

/**
 * OmrProofPlanner unit/golden tests (OpenSpec 35.17 + 35.20).
 *
 * Pins: repeated-plan determinism, option balance, full label coverage,
 * per-page coverage, no identity when unmapped, seed binding, and plan
 * round-trip serialization.
 */
function proofPlanner(): OmrProofPlanner
{
    return app(OmrProofPlanner::class);
}

test('repeated planning is deterministic and binds purpose, version, and target', function (): void {
    $target = ProofPlanFixtureTarget::standard(identityMapped: true, multiPage: true);
    $planner = proofPlanner();

    $first = $planner->plan($target, ProofPurpose::Digital);
    $second = $planner->plan($target, ProofPurpose::Digital);

    expect($second->planHash())->toBe($first->planHash())
        ->and($first->planHash())->toHaveLength(64)
        ->and($first->seed)->toBe($second->seed)
        ->and($first->challengeCode)->toBe($second->challengeCode)
        ->and($first->expectedVector)->toBe($second->expectedVector)
        ->and($first->calibrationDigits)->toBe($second->calibrationDigits)
        ->and($first->bindsTo($first->targetHashes))->toBeTrue();

    $quick = $planner->plan($target, ProofPurpose::QuickRehearsal);
    expect($quick->planHash())->not->toBe($first->planHash())
        ->and($quick->seed)->not->toBe($first->seed);

    $v2 = $planner->plan($target, ProofPurpose::Digital, OmrProofPlanner::PLAN_VERSION_V2);
    expect($v2->planHash())->toBe($first->planHash());

    $otherTarget = ProofPlanFixtureTarget::standard(identityMapped: true, multiPage: true)
        ->withArtifactHash(str_repeat('a', 64));
    $different = $planner->plan($otherTarget, ProofPurpose::Digital);
    expect($different->planHash())->not->toBe($first->planHash());

    expect($first->challengeCode)->toMatch('/^CHAL-[0-9A-F]{10}$/');
});

test('plan balances declared options across every expanded label', function (): void {
    $target = ProofPlanFixtureTarget::standard(identityMapped: false, multiPage: true);
    $plan = proofPlanner()->plan($target, ProofPurpose::FullQualification);

    // LOGIC_Q_1..10 (MCQ4): each of A-D must be used 2-3 times.
    $logic = array_values(array_filter(
        $plan->expectedVector,
        static fn (string $label): bool => str_starts_with($label, 'LOGIC_Q_'),
        ARRAY_FILTER_USE_KEY,
    ));
    expect(count($logic))->toBe(10);
    $counts = array_count_values($logic);
    foreach (['A', 'B', 'C', 'D'] as $option) {
        expect($counts[$option] ?? 0)->toBeGreaterThanOrEqual(2)
            ->and($counts[$option] ?? 0)->toBeLessThanOrEqual(3);
    }
    expect(max($counts) - min($counts))->toBeLessThanOrEqual(1);

    // VERBAL_Q_1..4 (MCQ5): each of A-E used at most once.
    $verbal = array_values(array_filter(
        $plan->expectedVector,
        static fn (string $label): bool => str_starts_with($label, 'VERBAL_Q_'),
        ARRAY_FILTER_USE_KEY,
    ));
    expect(count($verbal))->toBe(4)
        ->and(array_unique($verbal))->toHaveCount(4);
});

test('plan covers every page and every expanded label', function (): void {
    $target = ProofPlanFixtureTarget::standard(identityMapped: true, multiPage: true);
    $plan = proofPlanner()->plan($target, ProofPurpose::FullQualification);

    $pages = array_map(static fn (array $instruction): int => $instruction['page_no'], $plan->pageInstructions);
    expect($pages)->toBe([1, 2]);

    $covered = [];
    foreach ($plan->pageInstructions as $instruction) {
        foreach ($instruction['labels'] as $label) {
            $covered[] = $label;
        }
    }
    sort($covered);
    $natural = [...array_map(static fn (int $n): string => 'LOGIC_Q_'.$n, range(1, 10)), ...array_map(static fn (int $n): string => 'VERBAL_Q_'.$n, range(1, 4))];
    $expected = $natural;
    sort($expected);

    expect($covered)->toBe($expected)
        ->and(array_keys($plan->expectedVector))->toBe($natural)
        ->and($plan->expectedVectorForPage(1))->toHaveCount(10)
        ->and($plan->expectedVectorForPage(2))->toHaveCount(4)
        ->and($plan->expectedVectorForPage(1))->not->toHaveKey('LOGIC_Q_11');
});

test('calibration digits are generated only when an ID grid is mapped', function (): void {
    $mapped = proofPlanner()->plan(ProofPlanFixtureTarget::standard(identityMapped: true), ProofPurpose::Digital);
    expect($mapped->calibrationDigits)->toHaveCount(10);
    foreach ($mapped->calibrationDigits as $label => $digit) {
        expect($label)->toMatch('/^APPLICANT_NUMBER_(?:[1-9]|10)$/')
            ->and($digit)->toBeInt()->toBeBetween(0, 9);
    }
    // Expected vector never carries identity labels.
    $identityInVector = array_filter(
        array_keys($mapped->expectedVector),
        static fn (string $label): bool => str_starts_with($label, 'APPLICANT_NUMBER_'),
    );
    expect($mapped->expectedVector)->not->toHaveKey('APPLICANT_NUMBER_1')
        ->and($identityInVector)->toBe([]);

    $unmapped = proofPlanner()->plan(ProofPlanFixtureTarget::standard(identityMapped: false), ProofPurpose::Digital);
    expect($unmapped->calibrationDigits)->toBe([])
        ->and($unmapped->expectedVector)->toHaveCount(14);
});

test('seed binds artifact and native-bundle hashes plus purpose and version', function (): void {
    $target = ProofPlanFixtureTarget::standard(identityMapped: false, multiPage: false);
    $base = proofPlanner()->plan($target, ProofPurpose::Digital);
    $differentBundle = $target->withBundleHash(str_repeat('b', 64));
    expect(proofPlanner()->plan($differentBundle, ProofPurpose::Digital)->seed)->not->toBe($base->seed);
    expect(proofPlanner()->plan($target, ProofPurpose::QuickRehearsal)->seed)->not->toBe($base->seed);
    expect(proofPlanner()->plan($target, ProofPurpose::Digital, 'v9')->seed)->not->toBe($base->seed);

    $v1 = proofPlanner()->plan($target, ProofPurpose::Digital, OmrProofPlanner::PLAN_VERSION_V1);
    $expectedSeed = hash('sha256', implode('|', [$target->artifactHash(), $target->nativeBundleHash(), ProofPurpose::Digital->value, 'v1']));
    expect($v1->seed)->toBe($expectedSeed)
        ->and($base->seed)->not->toBe($v1->seed);
});

test('plan round-trips through fromArray with the same canonical hash', function (): void {
    $plan = proofPlanner()->plan(ProofPlanFixtureTarget::standard(identityMapped: true), ProofPurpose::QuickRehearsal);

    $rebuilt = OmrProofPlan::fromArray([
        'plan_version' => $plan->planVersion,
        'purpose' => $plan->purpose->value,
        'seed' => $plan->seed,
        'challenge_code' => $plan->challengeCode,
        'expected_vector' => $plan->expectedVector,
        'calibration_digits' => $plan->calibrationDigits,
        'page_instructions' => $plan->pageInstructions,
        'target_hashes' => $plan->targetHashes,
        'method' => $plan->method,
    ]);

    expect($rebuilt->planHash())->toBe($plan->planHash())
        ->and($rebuilt->expectedVector)->toBe($plan->expectedVector)
        ->and($rebuilt->calibrationDigits)->toBe($plan->calibrationDigits)
        ->and($rebuilt->bindsTo($plan->targetHashes))->toBeTrue();
});

test('hand-shaded plans select six-area first-middle-last marks and balance A-D', function (): void {
    $target = ProofPlanFixtureTarget::sixAreas();
    $planner = proofPlanner();

    $first = $planner->plan($target, ProofPurpose::QuickRehearsal, OmrProofPlan::METHOD_HAND_SHADED);
    $second = $planner->plan($target, ProofPurpose::QuickRehearsal, OmrProofPlan::METHOD_HAND_SHADED);
    $pre = $planner->plan($target, ProofPurpose::QuickRehearsal, OmrProofPlan::METHOD_PRE_SHADED);

    expect($first->expectedVector)->toHaveCount(18)
        ->and($first->sampleManifest)->toHaveCount(18)
        ->and($first->allowedMedia)->toBe(OmrProofPlan::HAND_SHADED_MEDIA)
        ->and($first->expectedVectorHash)->toBe(hash('sha256', json_encode($first->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))
        ->and($first->planHash())->toBe($second->planHash())
        ->and($first->expectedVector)->toBe($second->expectedVector)
        ->and($pre->expectedVector)->toHaveCount(30);

    foreach (range(1, 6) as $area) {
        expect($first->expectedVector)->toHaveKeys([
            'AREA_'.$area.'_Q_1',
            'AREA_'.$area.'_Q_3',
            'AREA_'.$area.'_Q_5',
        ]);
        expect($first->expectedVector)->not->toHaveKey('AREA_'.$area.'_Q_2');
    }

    $counts = array_count_values(array_values($first->expectedVector));
    expect(max($counts) - min($counts))->toBeLessThanOrEqual(1)
        ->and(array_keys($counts))->toEqualCanonicalizing(['A', 'B', 'C', 'D']);
    expect($first->calibrationDigits)->toHaveCount(10);
});

test('plan rejects a target with no scorable fields', function (): void {
    $target = new ProofPlanFixtureTarget(
        pages: [['page_no' => 1, 'blocks' => ['ID' => [
            'fieldType' => 'QTYPE_INT',
            'direction' => 'vertical',
            'fieldLabels' => ['APPLICANT_NUMBER_1..APPLICANT_NUMBER_10'],
            'origin' => [100, 200],
            'bubblesGap' => 70,
            'labelsGap' => 90,
            'bubbleDimensions' => [36, 36],
        ]]]],
        identityMapped: true,
    );

    proofPlanner()->plan($target, ProofPurpose::Digital);
})->throws(RuntimeException::class, 'plan_instructions_missing');
