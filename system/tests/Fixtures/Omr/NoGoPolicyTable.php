<?php

namespace Tests\Fixtures\Omr;

/**
 * FIXTURE (OpenSpec task 35.3): evidence-backed no-go policy table for the
 * digital verification matrix.
 *
 * The table defines, per matrix variant:
 *   - `severity`: clean | mild | severe | lifecycle
 *   - `policy_expected`: the declared outcome the variant MUST produce
 *     (`exact_recovery` = the clean answer vector is recovered exactly;
 *     `typed_rejection` = a typed failure, never a false accept)
 *   - `currently_enforced`: what `OmrNoGoMatrixRunner` enforces TODAY
 *     (`pass` | `typed_rejection`) — recorded honestly, NOT weakened
 *   - `evidence`: fixture | observed | gap
 *       fixture = committed automated evidence pins the outcome
 *       observed = real-worker run observed the outcome (browser proof),
 *                  no committed fixture test reproduces it
 *       gap     = no evidence exists; the expectation must NOT be enforced
 *                 until evidence is produced
 *
 * Policy source (task 35.3): clean and MILD perspective/rotation/light/blur/
 * low-resolution perturbations expect exact recovery; SEVERE perturbations,
 * partial page, missing marker, and wrong marker expect typed rejection.
 * The review decision [Review][Decision] (task 33) groups glare with the
 * accepted mild perturbations ("current real worker accepts them").
 *
 * STOP-GATE: do not make the matrix green by changing `currently_enforced`
 * without fixture evidence. Mild exact-recovery expectations below are
 * currently enforced as typed rejection because the only evidence is
 * observed worker acceptance, not a committed fixture test.
 */
final class NoGoPolicyTable
{
    public const OUTCOME_EXACT_RECOVERY = 'exact_recovery';
    public const OUTCOME_TYPED_REJECTION = 'typed_rejection';

    public const SEVERITY_CLEAN = 'clean';
    public const SEVERITY_MILD = 'mild';
    public const SEVERITY_SEVERE = 'severe';
    public const SEVERITY_LIFECYCLE = 'lifecycle';

    public const EVIDENCE_FIXTURE = 'fixture';
    public const EVIDENCE_OBSERVED = 'observed';
    public const EVIDENCE_GAP = 'gap';

    /**
     * Policy table, one row per `OmrNoGoMatrixRunner::VARIANTS` entry.
     *
     * @return array<string,array{severity:string,policy_expected:string,currently_enforced:string,evidence:string,note:string}>
     */
    public static function rows(): array
    {
        return [
            'clean' => [
                'severity' => self::SEVERITY_CLEAN,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'Clean candidate through the real layout worker; exact answer-vector recovery is the pass condition (OmrNoGoMatrixTest, task 31.10).',
            ],
            'perspective' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_OBSERVED,
                'note' => 'Mild perspective: real worker accepted the current single-severity variant in browser proof (2026-08-24); no committed fixture test pins exact recovery.',
            ],
            'rotation' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_OBSERVED,
                'note' => 'Mild rotation: real worker accepted the current single-severity variant in browser proof (2026-08-24); no committed fixture test pins exact recovery.',
            ],
            'uneven_light' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_OBSERVED,
                'note' => 'Mild uneven light: real worker accepted the current single-severity variant in browser proof (2026-08-24); no committed fixture test pins exact recovery.',
            ],
            'blur' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_OBSERVED,
                'note' => 'Mild blur: real worker accepted the current single-severity variant in browser proof (2026-08-24); no committed fixture test pins exact recovery.',
            ],
            'glare' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_GAP,
                'note' => 'Not named in task 35.3; grouped with accepted mild perturbations by review decision 33. Only observed worker acceptance; no fixture evidence.',
            ],
            'low_resolution' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_OBSERVED,
                'note' => 'Mild low resolution: real worker accepted the current single-severity variant in browser proof (2026-08-24); no committed fixture test pins exact recovery.',
            ],
            'partial_page' => [
                'severity' => self::SEVERITY_SEVERE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_GAP,
                'note' => 'Partial page: policy expects typed rejection and the runner enforces it, but only fake-port unit coverage exists; no real-worker fixture evidence.',
            ],
            'missing_marker' => [
                'severity' => self::SEVERITY_SEVERE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'test_corner_false_positive.py proves clipped/absent marker patterns classify as marker_missing, never registration; OmrNoGoMatrixTest asserts typed_rejection.',
            ],
            'wrong_marker' => [
                'severity' => self::SEVERITY_SEVERE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'test_corner_false_positive.py proves border-like/wrong-marker patterns classify as marker_mismatch, never registration; OmrNoGoMatrixTest asserts typed_rejection.',
            ],
            'wrong_instance' => [
                'severity' => self::SEVERITY_LIFECYCLE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'Deterministic NEXIAM capture-lifecycle gate (signed instance identity); OmrNoGoMatrixTest asserts the gate execution.',
            ],
            'superseded_instance' => [
                'severity' => self::SEVERITY_LIFECYCLE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'Deterministic NEXIAM capture-lifecycle gate (superseded instance); OmrNoGoMatrixTest asserts the gate execution.',
            ],
            'duplicate_capture' => [
                'severity' => self::SEVERITY_LIFECYCLE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'Deterministic NEXIAM capture-lifecycle gate (duplicate capture); OmrNoGoMatrixTest asserts the gate execution.',
            ],
            'multipage_order' => [
                'severity' => self::SEVERITY_LIFECYCLE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => 'Deterministic NEXIAM capture-lifecycle gate (page order); OmrNoGoMatrixTest asserts the gate execution.',
            ],
        ];
    }

    /**
     * SEVERE parameterizations of the mild perturbation families. The runner
     * has no severity axis today (`bin/omr-variant.php` applies one fixed
     * perturbation per variant), so no severe fixture exists and no threshold
     * numbers are invented here. Policy-only rows: expected outcome is typed
     * rejection; evidence is a gap until a severe generator + fixture land.
     *
     * @return array<string,array{policy_expected:string,evidence:string,note:string}>
     */
    public static function severeParameterizations(): array
    {
        $families = [
            'perspective' => 'Severe perspective (heavy warp)',
            'rotation' => 'Severe rotation',
            'uneven_light' => 'Severe uneven light',
            'blur' => 'Severe blur',
            'glare' => 'Severe glare',
            'low_resolution' => 'Severe low resolution',
        ];

        $rows = [];
        foreach ($families as $family => $label) {
            $rows[$family] = [
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_GAP,
                'note' => "{$label}: policy expects typed rejection. No severity parameterization exists in `bin/omr-variant.php`; no fixture evidence; no threshold numbers invented.",
            ];
        }

        return $rows;
    }
}
