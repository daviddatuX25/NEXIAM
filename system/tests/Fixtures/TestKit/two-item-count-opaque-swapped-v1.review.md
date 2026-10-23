# Independent AI software review: `two-item-count-opaque-swapped-v1`

**Reviewer:** `OpaqueTruthIndependentReview` (AI software reviewer; model identity not exposed in this review context)
**Review date:** 2026-10-16
**Decision:** Accepted for the exact synthetic count vectors, strict authored-plan shape, and explicitly associated synthetic item map described below.
**Reviewer reference:** `tests/Fixtures/TestKit/two-item-count-opaque-swapped-v1.review.md`

This is a fresh, independent, read-only AI software review of the exact current packet bytes. It is separate from the packet authoring agent and does not reuse prior approval of changed bytes. The associated fixture helper explicitly describes an AI-authored synthetic software-only procedure. The reviewer is not a human supervisor, institution, publisher, or source-rights authority. No institutional approval, source rights, physical-device/printer qualification, runtime success, detector/worker certification, activation approval, norm conversion, or end-to-end bundle acceptance is claimed.

## Exact source and strict authored-plan schema

- Source: `tests/Fixtures/TestKit/two-item-count-opaque-swapped-v1.json`.
- Actual SHA-256, independently computed with `sha256sum` over the checked-in bytes: `2044aaa7aa3d63b832f2f51a51d6e376099d38f01265fdf64f825d839653ffdb`.
- The complete current source was read directly. Its exact top-level authored keys are `schema_version` and `cases`; it has schema version integer `1` and four case objects.
- Compared the authored root with `TestKitAcceptancePlan::assertPlanShape`: the loader's allowed keys are `schema_version`, `truth_source`, `cases`, and optional `source_evidence`; required keys are `schema_version`, `truth_source`, and `cases`; schema version is 1; cases must be a list of at least 3 and no more than 128. `loadReviewedSource` injects `truth_source` and `source_evidence` around the decoded authored packet before freezing. Thus the authored packet correctly does not carry either injected field or unrelated top-level provenance/mapping fields. The actual downstream freeze path was not run.
- Case keys and value shapes were read against `canonicalCases`/`canonicalCase`: each case has exactly `case_id`, `coverage`, `pages`, `expected_metrics`, and `recorded_measures`; the four IDs are unique; each has one page 1 with two response objects, one expected metric, and an empty recorded-measures list. The source uses the required response labels, exact decimal string outputs, and unique coverage values. This is a static source-contract inspection, not a claim of runtime acceptance.
- Approval binds only these exact bytes. Any source change requires fresh independent review and hash.

## Explicit synthetic item map and procedure facts

The associated fixture helper `tests/Support/TestKitHelpers.php` explicitly documents the synthetic administration map: physical slot KAPPA / printed number 1 maps to `LOGIC_Q_2` / formal item 2 / key token `token::cobalt`; physical slot OMEGA / printed number 2 maps to `LOGIC_Q_1` / formal item 1 / key token `token::amber`. The booklet intrinsic formal order is item 2 then item 1. This is a deliberate nonordinal physical/native-to-formal identity association, not an inference from native-label names or evaluator behavior.

The helper also authors the formal subtest with `item_order_policy` set to `within_subtest_permutable`, opaque token choices `token::cobalt` and `token::amber` on both items, and a required `RAW_COUNT` correct-item-count metric with bounds 0–2 and integer precision. These inspected helper facts and explicit synthetic procedure reference are the basis for the hand-work below. The packet itself contains response vectors and expected counts, not a duplicate metadata map.

This is AI-authored synthetic software acceptance material only. The helper's source/permitted-use references explicitly disclaim institutional source or rights claims; no human, publisher, institutional, physical-paper, physical-device/printer, or official qualification claim is made.

## Independently hand-worked raw outputs

I compared the literal response vectors to the explicitly documented synthetic per-item key mapping above and counted exact matches, one point per match. No production evaluator, scoring kernel, application, test, or key-under-test was run or consulted to generate expected values.

| Case | Exact physical responses | Hand-work | Expected `RAW_COUNT` |
|---|---|---:|---:|
| `correct` | KAPPA / `LOGIC_Q_2` = `token::cobalt`; OMEGA / `LOGIC_Q_1` = `token::amber` | 1 + 1 | `2` |
| `incorrect` | KAPPA / `LOGIC_Q_2` = `token::amber`; OMEGA / `LOGIC_Q_1` = `token::cobalt` | 0 + 0 | `0` |
| `blank` | KAPPA / `LOGIC_Q_2` = `null`; OMEGA / `LOGIC_Q_1` = `null` | 0 + 0 | `0` |
| `mixed` | KAPPA / `LOGIC_Q_2` = `token::cobalt`; OMEGA / `LOGIC_Q_1` = `token::cobalt` | 1 + 0 | `1` |

All expected decimal-string values in the packet agree with this arithmetic. All four cases have exactly page 1 and include `LOGIC_Q_2` followed by `LOGIC_Q_1` once each, matching the helper's stated KAPPA/OMEGA physical placement. The vectors cover correct, incorrect, and blank; mixed declares both correct and incorrect coverage. Both opaque tokens occur at both item positions across correct/incorrect cases; blanks are explicit at both positions. For the stated one-page/two-item count-only scope, no required case is omitted.

## Review JSON and protocol convention

Inspected `TestKitAcceptancePlan::loadReviewedSource` and the configured registry/native sidecar convention. The loader requires same-key source `.json`, `.review.json`, `.review.md`, exact registered hashes, exact review sidecar keys, schema 1, accepted decision, reviewer reference, and matching canonical protocol identity. This review JSON uses the native schema-1 fields, binds the exact packet SHA-256 above, and references current protocol `test-kit-acceptance-protocol-v1`, SHA-256 `e2be57fa029e14e3b3660cd7197d3159072abbc325de139ecb1e2d43cb2127c5`.

No tests, checks, formatters, migrations, seed/reset commands, evaluator/application execution, or database writes were performed. This is not evidence of connected component persistence, a production source-to-material binding, institutional/source rights, human review, physical qualification, acceptance receipt, or activation.
