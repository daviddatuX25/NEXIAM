<?php

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use Tests\Fixtures\Omr\NoGoPolicyTable;

/**
 * POLICY FIXTURE TEST (OpenSpec tasks 35.3/35.43): pins the evidence-backed
 * no-go policy table for clean + six mild exact decided-vector recoveries
 * versus severe/partial/missing/wrong marker typed rejection, while keeping
 * ambiguity as explicit evidence.
 *
 * No recognition math or OMRChecker thresholds are changed by this fixture.
 */
test('policy fixture covers exactly the runner variants without drift', function (): void {
    $rows = NoGoPolicyTable::rows();

    expect(array_keys($rows))->toBe(OmrNoGoMatrixRunner::VARIANTS)
        ->and(count($rows))->toBe(count(OmrNoGoMatrixRunner::VARIANTS));
});

test('policy table maps clean and every mild perturbation to exact recovery', function (): void {
    $rows = NoGoPolicyTable::rows();

    foreach (['clean', 'perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution'] as $variant) {
        expect($rows[$variant]['severity'])->toBeIn(['clean', 'mild'])
            ->and($rows[$variant]['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_EXACT_RECOVERY)
            ->and($rows[$variant]['currently_enforced'])->toBe('pass')
            ->and($rows[$variant]['evidence'])->toBe(NoGoPolicyTable::EVIDENCE_FIXTURE);
    }
});

test('policy table maps severe, partial, missing and wrong marker to typed rejection', function (): void {
    $rows = NoGoPolicyTable::rows();

    foreach (['partial_page', 'missing_marker', 'wrong_marker'] as $variant) {
        expect($rows[$variant]['severity'])->toBe(NoGoPolicyTable::SEVERITY_SEVERE)
            ->and($rows[$variant]['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION)
            ->and($rows[$variant]['currently_enforced'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION)
            ->and($rows[$variant]['evidence'])->toBe(NoGoPolicyTable::EVIDENCE_FIXTURE);
    }

    foreach (NoGoPolicyTable::severeParameterizations() as $family => $row) {
        expect($family)->toBeIn(['perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution'])
            ->and($row['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION)
            ->and($row['evidence'])->toBe(NoGoPolicyTable::EVIDENCE_FIXTURE);
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

test('current enforcement is pinned: clean and every mild variant pass, others reject', function (): void {
    $rows = NoGoPolicyTable::rows();
    $exact = ['clean', 'perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution'];

    foreach ($exact as $variant) {
        expect($rows[$variant]['currently_enforced'])->toBe('pass');
    }
    foreach (array_diff(OmrNoGoMatrixRunner::VARIANTS, $exact) as $variant) {
        expect($rows[$variant]['currently_enforced'])->toBe(NoGoPolicyTable::OUTCOME_TYPED_REJECTION);
    }
});

test('mild exact recovery is fixture-backed and ambiguity stays explicit', function (): void {
    foreach (NoGoPolicyTable::rows() as $variant => $row) {
        if ($row['severity'] !== NoGoPolicyTable::SEVERITY_MILD) {
            continue;
        }
        expect($row['policy_expected'])->toBe(NoGoPolicyTable::OUTCOME_EXACT_RECOVERY)
            ->and($row['currently_enforced'])->toBe('pass')
            ->and($row['evidence'])->toBe(NoGoPolicyTable::EVIDENCE_FIXTURE)
            ->and($row['note'])->toContain('ambiguity remains item evidence');
    }
});

test('severe parameterizations never invent threshold numbers', function (): void {
    foreach (NoGoPolicyTable::severeParameterizations() as $family => $row) {
        expect($row)->toHaveKeys(['policy_expected', 'evidence', 'note'])
            ->and($row)->not->toHaveKey('threshold')
            ->and($row)->not->toHaveKey('severity_degrees')
            ->and($row)->not->toHaveKey('severity_px');
    }
});
