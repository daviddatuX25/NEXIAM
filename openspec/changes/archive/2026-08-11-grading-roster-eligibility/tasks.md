# Tasks: Grading Roster Eligibility

## A — Open

- [x] A1 Open OpenSpec change + confirm no active change already in `openspec/changes/`

## B — Shared eligibility

- [x] B1 `ExamAttendanceRecord::OUTCOME_PRESENT/OUTCOME_ABSENT` constants
- [x] B2 `GradingRoster` support class (`queryFor` + `includesAttempt`) (GRE-1/GRE-2)

## C — Writes

- [x] C1 `CloseExamSession` attendance guard (GRE-3)
- [x] C2 `RecordScores` / `ClearScores` / `FinalizeGradingSession` use shared eligibility (GRE-2/GRE-4)

## D — Reads + UI

- [x] D1 `GradingController::index` + `show` expose eligible roster + absent/no-submission stats (GRE-5)
- [x] D2 `GradingSession.svelte` stats strip + absent note; `Grading.svelte` absent count (GRE-5)

## E — Proof

- [x] E1 Update `buildGradingFixture` + `ReleaseFlowTest` to record attendance+submission pre-close
- [x] E2 New Pest cases: absent excluded; no-submission excluded; close guard; stats (GRE-1/3/4/5)
- [x] E3 Run `tests/Feature/Assessment tests/Feature/Release tests/Feature/Examination`
- [x] E4 HANDOFF + evidence note; archive README + project-context update on PASS
