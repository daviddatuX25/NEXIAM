# Change Proposal: Grading Roster Eligibility (attendance + submission)

**Change ID:** `grading-roster-eligibility`
**Status:** ACTIVE
**Phase:** E2 connected admission-to-release slice
**Depends on:** `grading-workflow` PASS (manual grading core), `exam-proctor-duty-assign` PASS (attendance/submission recording), E2 connected-slice scaffold PASS
**Archived prior:** `openspec/changes/archive/2026-08-11-grading-workflow/`

## Intent

The grading roster must contain only **score-eligible** applicants: applicants who were **present** on exam day **and submitted** an answer sheet. Absent (no-show) applicants and present-but-no-submission applicants must be excluded from the roster so finalize never demands blank grades for them — "absence does not silently create a zero score" (§5.2 GF-06, master §6.6 no-show rule). This also makes the roster derivation deterministic: attendance + submission are recorded **before** the session closes, and `CloseExamSession` gains a guard that blocks closing until attendance is recorded for every active assignment.

## Decisions (founder, 2026-08-11)

1. **Roster eligibility = attendance `present` AND a submission record exists.** Absent or not-submitted attempts are not score-eligible.
2. **Applicants are never removed from the exam session** on absence. The `ExamAssignment` remains the durable audited no-show record; exclusion happens only in the derived grading roster.
3. **`CloseExamSession` guard:** require an attendance record (present or absent) for every active assignment before closing. Submission is **not** a close prerequisite — a present applicant without a submission record is simply not score-eligible (no `not_submitted` outcome exists yet; §5.2 submission `not_submitted`/`reconciled` states are a follow-on).
4. The no-show **reassignment/re-exam policy** stays parked at master §6.6 (`DECISION REQUIRED — E2 BLOCKER`). This cut only makes grading correct; it does not decide what happens to absent applicants afterwards.

## Disposition from legacy

| Legacy | Disposition |
|---|---|
| `GradingSessionService::openForExamSession` copies **every** `examSession->applicants` into the roster pivot regardless of presence | **REJECT** — roster is a live derived query filtered to present+submitted; no pivot, no blank-score demand |
| `ensureAllApplicantsScored` requires every copied applicant to have all domains | **REWRITE** — completeness applies to the **eligible** roster only (REQ-VS-009) |
| Legacy tracked printed/no-show via pivot flags | Not used — absence is read from `ExamAttendanceRecord.outcome` (one core) |

## Out of scope (explicit)

- No-show reassignment/re-exam policy (master §6.6 E2 blocker).
- `not_submitted` / `reconciled` submission outcomes (§5.2) — follow-on with the proctor submission UX.
- Score conversion table, encryption, OMR, printing, Nexi assistant — separate cuts.
