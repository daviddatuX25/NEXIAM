<?php

namespace Tests\Fixtures\Omr;

/**
 * FIXTURE (OpenSpec task 35.3/35.43): evidence-backed no-go policy table
 * for the digital verification matrix.
 *
 * The table defines, per matrix variant:
 *   - `severity`: clean | mild | severe | lifecycle
 *   - `policy_expected`: the declared outcome the variant MUST produce
 *     (`exact_recovery` = the decided answer vector is recovered exactly;
 *     `typed_rejection` = a typed failure, never a false accept)
 *   - `currently_enforced`: what `OmrNoGoMatrixRunner` enforces
 *     (`pass` | `typed_rejection`)
 *   - `evidence`: fixture | observed | gap
 *       fixture = committed automated real-worker evidence pins the outcome
 *       observed = real-worker run observed the outcome (browser proof),
 *                  no committed fixture test reproduces it
 *       gap     = no evidence exists; the expectation must NOT be enforced
 *                 until evidence is produced
 *
 * Policy source (task 35.3): clean and MILD perspective/rotation/light/blur/
 * glare/low-resolution perturbations expect exact decided-vector recovery;
 * SEVERE perturbations, partial page, missing marker, and wrong marker expect
 * typed rejection. The four lifecycle variants remain application-owned typed
 * rejection gates.
 *
 * The committed 35.43 characterization pins clean + six mild recoveries and
 * seven severe/lifecycle rejections. Ambiguity is retained as evidence and
 * never converted into a false exact decision.
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
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: mild perspective recovers the exact decided vector; ambiguity remains item evidence and is not counted as a decision.',
            ],
            'rotation' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: mild rotation recovers the exact decided vector; ambiguity remains item evidence and is not counted as a decision.',
            ],
            'uneven_light' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: mild uneven light recovers the exact decided vector; ambiguity remains item evidence and is not counted as a decision.',
            ],
            'blur' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: mild blur recovers the exact decided vector; ambiguity remains item evidence and is not counted as a decision.',
            ],
            'glare' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: mild glare (center 0.52w, 0.46h; size 0.62w, 0.24h; alpha 96/24.4% specular reflection) recovers the exact decided vector; ambiguity remains item evidence and is not counted as a decision.',
            ],
            'low_resolution' => [
                'severity' => self::SEVERITY_MILD,
                'policy_expected' => self::OUTCOME_EXACT_RECOVERY,
                'currently_enforced' => 'pass',
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: mild low resolution recovers the exact decided vector; ambiguity remains item evidence and is not counted as a decision.',
            ],
            'partial_page' => [
                'severity' => self::SEVERITY_SEVERE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43 real-worker fixture: partial page is rejected with a typed sheet failure; no layout success is accepted.',
            ],
            'missing_marker' => [
                'severity' => self::SEVERITY_SEVERE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43/corner fixture: missing marker is rejected with marker_missing and never registers.',
            ],
            'wrong_marker' => [
                'severity' => self::SEVERITY_SEVERE,
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'currently_enforced' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => '35.43/corner fixture: wrong marker is rejected with marker_mismatch and never registers.',
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
     * SEVERE parameterizations of the mild perturbation families. The
     * characterization fixture runs a deterministic aggressive input for each
     * family and pins a typed worker rejection without inventing thresholds.
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
            'glare' => 'Severe glare (full-page opaque specular reflection; size 1.3w, 1.3h; 100% white)',
            'low_resolution' => 'Severe low resolution',
        ];

        $rows = [];
        foreach ($families as $family => $label) {
            $rows[$family] = [
                'policy_expected' => self::OUTCOME_TYPED_REJECTION,
                'evidence' => self::EVIDENCE_FIXTURE,
                'note' => "35.43 real-worker fixture: {$label} is a typed rejection; no recognition threshold or math changed.",
            ];
        }

        return $rows;
    }
}
