# Spec — Grading Roster Eligibility

**Spec ID:** `grading-roster`
**Change:** `grading-roster-eligibility`

## Requirements

### GRE-1 — Roster derives from present-and-submitted assignments only

- The grading session roster is the set of `ExamAssignment`s of the exam session that are **active**, have an attendance record with `outcome = present`, **and** have a submission record (GS-2 amended).
- Absent (attendance `absent`) and not-submitted (no submission record) attempts are excluded — they never appear as roster rows and never require scores.
- No applicant pivot table; one core (assignments are the roster).

### GRE-2 — Shared eligibility is used everywhere

- Roster listing (`GET /grading/sessions/{gs}`), dashboard progress (`GET /grading`), per-attempt access + score save + clear (`RecordScores`, `ClearScores`, `GradingScoreController`), and finalize completeness all resolve eligibility through one shared derivation (`GradingRoster` support class). A non-eligible attempt is treated as not-in-roster (404/validation).

### GRE-3 — Close requires attendance recorded for every active assignment

- `CloseExamSession` rejects closing while any active assignment has no attendance record (message names the count).
- Attendance `present` or `absent` both satisfy the guard; submission does **not** gate close.
- Already-completed sessions remain idempotent (no re-check).

### GRE-4 — Finalize completeness applies to the eligible roster

- `FinalizeGradingSession` requires every **eligible** attempt to have a score record for every active aptitude area (REQ-VS-009). Absent/not-submitted attempts are outside this requirement.
- An empty eligible roster still rejects finalize ("no applicants to grade") — all-absent handling stays with the no-show policy (master §6.6).

### GRE-5 — Absence is visible, not hidden

- Session page exposes `assigned`, `present_and_submitted` (roster), `absent`, and `no_submission` counts so staff see why someone is not being graded.
- Dashboard row keeps `applicants_total`/`applicants_scored` over the **eligible** roster.

## Non-goals (this spec)

- No-show reassignment/re-exam policy (master §6.6).
- Submission outcome states `not_submitted`/`reconciled` (§5.2).
- Conversion table, encryption, OMR, printing, Nexi assistant.
