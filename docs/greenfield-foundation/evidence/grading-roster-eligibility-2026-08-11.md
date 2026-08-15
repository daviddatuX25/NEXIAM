# Grading Roster Eligibility — Implementation Evidence

**Date:** 2026-08-11
**OpenSpec:** `openspec/changes/2026-08-11-grading-roster-eligibility/` (archived PASS)
**Runtime:** `system/` (Laravel 13 / Inertia 3 / Svelte 5)

## Status

**CUT SHIPPED (PASS).** The grading roster is now score-eligibility-driven: an attempt is gradable only when its assignment is active, the applicant was marked `present`, and a submission record exists. Absent and present-without-submission attempts are excluded so finalize never demands blank grades for them — "absence does not silently create a zero score" (GF-06 §5.2, master §6.6). `CloseExamSession` gains an attendance-completeness guard.

## What was built

| Feature | Entry | Notes |
|---|---|---|
| Eligibility constants | `ExamAttendanceRecord::OUTCOME_PRESENT/OUTCOME_ABSENT` | No magic strings |
| Roster support class | `app/Assessment/Support/GradingRoster.php` | `queryFor`/`queryForSessionIds`/`includesAttempt`/`isEligible`/`filterEligible` — shared SQL + PHP predicates |
| Close guard | `CloseExamSession` | Rejects close while any active assignment has no attendance record (GRE-3) |
| Writes on shared eligibility | `RecordScores`/`ClearScores`/`FinalizeGradingSession` | Roster membership + completeness over eligible roster only (GRE-2/GRE-4) |
| Read projection | `GradingController::index`/`show` | `roster_stats` = assigned / present_and_submitted / absent / no_submission / pending_attendance; dashboard `applicants_absent` |
| UI | `Assessment/GradingSession.svelte`, `Assessment/Grading.svelte` | Stats strip + absent/no-submission note; dashboard absent-excluded tag |

## Pest evidence (2026-08-11)

```text
tests/Feature/Assessment/GradingWorkflowTest.php → 22 passed (328 assertions) incl. GRE-1/3/4/5
tests/Feature/Release/ReleaseFlowTest.php        → passed (fixture records attendance + submission pre-close)
tests/Feature/Examination/                        → 19 passed (235 assertions) incl. close-attendance guard
```

Run:

```bash
cd system
docker compose build app && docker compose up -d app
docker compose exec app php artisan test --compact \
  tests/Feature/Assessment/GradingWorkflowTest.php tests/Feature/Release tests/Feature/Examination
```

## REQ-VS / GF map

| Ref | Evidence |
|---|---|
| GRE-1 (present+submitted = eligible) | absent + no-submission exclusion tests |
| GRE-2 (no blank-score demand) | finalize succeeds with only the present applicant scored |
| GRE-3 (close requires attendance) | close-rejected-until-attendance test |
| GRE-4 (writes over shared eligibility) | RecordScores/ClearScores roster guard tests |
| GRE-5 (stats/read projection) | dashboard + session stats assertions |
| GF-06 §5.2 / master §6.6 | no-show does not silently create a zero score |

## Gated non-goals (follow-ons, not this cut)

- No-show reassignment / re-exam policy (master §6.6 `DECISION REQUIRED — E2 BLOCKER`).
- `not_submitted` / `reconciled` submission outcomes (§5.2) — follow-on with proctor submission UX.
- Proctor attendance/submission recording UX beyond the guard (separate proctor-session cut).
