# D-080 — Program Head candidate and protected-record visibility

**Status:** APPROVED — founder decisions reconciled 2026-08-15
**Closes:** GF-04 CAP-002/024/036 individual-data gate · REQ-REP-001 individual-view hold
**OpenSpec:** `2026-08-15-admission-model-program-head`

## Decision

### Tier A — Potential overview

An assigned-program PH may see an identified Potential row only after Registrar acceptance and when the program occurs in application preferences or the Guidance pool. Allowed: applicant name/reference, preference/pool basis, and pipeline tags (accepted, exam done, result released, consulted/advised, selected, follow-up, enrolling, enrolled).

Preference alone does **not** expose scores, result document, counselor notes, model inputs, contact information, or internal threads. Submitted/pre-accept candidates remain aggregate-only.

### Tier B — Protected admission-support record

Full record access requires:

1. assigned program scope;
2. released result; and
3. an applicant-acknowledged current selection for this program **or** an explicit targeted follow-up routed to this program by Guidance.

Preference/pool membership alone never crosses this gate. A Guidance default remains insufficient until the applicant acknowledges it, unless Guidance separately opens a targeted follow-up.

Allowed:

- converted per-domain scores;
- result/remark sheet;
- consultation outcome;
- program-relevant counselor notes (bounded exception approved by founder);
- assigned-program preference/pool basis tag, current relationship, selection/follow-up/reconsideration outcome, and Registrar stamp state;
- attributable assigned-program analysis evidence only when a separately approved future plugin has produced it; the current cut exposes none.

Never exposed:

- raw answer sheets / bubble scans;
- confidential conversion tables;
- pre-release scores;
- counseling notes unrelated to program requirements/decision;
- other program names/options, cross-program selection history, case records, or internal threads.

### Audit and applicant visibility

Every protected open emits an audit event with actor, applicant, program, cycle, correlation id, and timestamp. Applicant access history retains all events. A user-facing notice is deduplicated to the first protected open by the same PH/program in a 24-hour window.

PH-applicant messaging requires current selection or targeted follow-up (D-092); potential preference alone is insufficient.

## Rationale

This preserves the founder's visual-ground requirement (preference/pool candidates + stage tags) while preventing every PH from receiving sensitive records merely because an applicant listed several preferences.
