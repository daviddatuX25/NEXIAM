<?php

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use Tests\Fixtures\Omr\NoGoPolicyTable;

/**
 * POLICY FIXTURE TEST (OpenSpec task 35.3): pins the evidence-backed no-go
 * policy table for clean, mild perspective/rotation/light/blur/low-resolution
 * (exact recovery) versus severe/partial/missing/wrong marker (typed
 * rejection), and pins the runner's CURRENT enforcement honestly.
 *
 * The test deliberately does NOT change `OmrNoGoMatrixRunner`: its current
 * expectations (clean → pass; every other variant → typed_rejection) are
 * recorded as `currently_enforced` in the fixture, so the matrix stays
 * honestly red until fixture evidence lands. No recognition math or
 * OMRChecker-pinned behavior is touched.
 */

test('policy fixture covers exactly the runner variants without drift', function (): void {
    $rows = NoGoPolicyTable::rows();

    expect(array_keys($rows))->toBe(OmrNoGoMatrixRunner::VARIANTS)
        ->and(count($rows))->toBe(count(OmrNoGoMatrixRunner::VARIANTS));
});

test('policy table maps clean and mild perturbations to exact recovery', function (): void {
    $rows = NoGoPolicyTable::rows();

    foreach (['clean', 'perspective', 'rotation', 'uneven_light', 'blur', 'low_resolution'] as $variant) {
        expect($rows[$variant]['severity'])->toBeIn(['clean', 'mild'])
            ->and($rows[$variant]['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_EXACT_RECOVERY);
    }
});

test('policy table maps severe, partial, missing and wrong marker to typed rejection', function (): void {
    $rows = NoGoPolicyTable::rows();

    foreach (['partial_page', 'missing_marker', 'wrong_marker'] as $variant) {
        expect($rows[$variant]['severity'])->toBe(NoGoPolicyTable::SEVERITY_SEVERE)
            ->and($rows[$variant]['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION);
    }

    foreach (NoGoPolicyTable::severeParameterizations() as $family => $row) {
        expect($family)->toBeIn(['perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution'])
            ->and($row['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION)
            ->and($row['evidence'])->toBe(NoGoPolicyTable::EVIDENCE_GAP);
    }
});

test('lifecycle variants are typed-rejection application gates', function (): void {
    $rows = NoGoPolicyTable::rows();

    foreach (['wrong_instance', 'superseded_instance', 'duplicate_capture', 'multipage_order'] as $variant) {
        expect($rows[$variant]['severity'])->toBe(NoGoPolicyTable::SEVERITY_LIFECYCLE)
            ->and($rows[$variant]['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION)
            ->and($rows[$variant]['evidence'])->toBe(NoGoPolicyTable::EVIDENCE_FIXTURE);
    }
});

test('current enforcement is pinned: clean passes, every other variant expects typed rejection', function (): void {
    $rows = NoGoPolicyTable::rows();

    expect($rows['clean']['currently_enforced'])->toBe('pass');

    foreach (array_diff(OmrNoGoMatrixRunner::VARIANTS, ['clean']) as $variant) {
        expect($rows[$variant]['currently_enforced'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION);
    }
});

test('no mild expectation is made green without fixture evidence', function (): void {
    $rows = NoGoPolicyTable::rows();

    // Mild exact-recovery expectations are NOT enforced today. The stop-gate:
    // any unenforced exact-recovery expectation must be evidence `observed`
    // or `gap` — never `fixture` — so nobody can flip the matrix green
    // without a committed fixture test.
    foreach ($rows as $variant => $row) {
        if ($row['severity'] === NoGoPolicyTable::SEVERITY_MILD
            && $row['policy_expected'] === NoGoPolicyTable::OUTCOME_EXACT_RECOVERY
            && $row['currently_enforced'] === NoGoPolicyTable::OUTCOME_TYPED_REJECTION) {
            expect($row['evidence'])->not->toBe(NoGoPolicyTable::EVIDENCE_FIXTURE)
                ->and($row['evidence'])->toBeIn([NoGoPolicyTable::EVIDENCE_OBSERVED, NoGoPolicyTable::EVIDENCE_GAP]);
        }
    }

    // Sanity: the fixture actually contains mild rows that are unenforced today.
    $unenforcedMild = array_filter($rows, static fn (array $row): bool => $row['severity'] === NoGoPolicyTable::SEVERITY_MILD
        && $row['policy_expected'] === NoGoPolicyTable::OUTCOME_EXACT_RECOVERY
        && $row['currently_enforced'] === NoGoPolicyTable::OUTCOME_TYPED_REJECTION);

    expect($unenforcedMild)->not->toBeEmpty();
});

test('severe parameterizations never invent threshold numbers', function (): void {
    foreach (NoGoPolicyTable::severeParameterizations() as $family => $row) {
        expect($row)->toHaveKeys(['policy_expected', 'evidence', 'note'])
            ->and($row)->not->toHaveKey('threshold')
            ->and($row)->not->toHaveKey('severity_degrees')
            ->and($row)->not->toHaveKey('severity_px');
    }
});
