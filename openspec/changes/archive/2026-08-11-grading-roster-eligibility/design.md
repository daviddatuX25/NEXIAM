# Design: Grading Roster Eligibility

## Domain shape

New support class under `app/Assessment/Support/`:

- `GradingRoster::queryFor(GradingSession): Builder` — `ExamAssignment` where `exam_session_id`, `active()`, `whereHas('attendanceRecord', outcome=present)`, `whereHas('submissionRecord')`.
- `GradingRoster::includesAttempt(GradingSession, int $attemptId): bool` — existence check used by controllers + Actions.

Attendance outcome literals move to constants on `ExamAttendanceRecord` (`OUTCOME_PRESENT`, `OUTCOME_ABSENT`) so the shared derivation never uses magic strings.

## Writes

| Path | Change |
|---|---|
| `CloseExamSession` | After status/version guards: count active assignments `whereDoesntHave('attendanceRecord')`; if > 0 throw `RuntimeException('Attendance is not recorded for N assigned applicant(s).')`. Transactional, audited as today. |
| `RecordScores` | Roster guard → `GradingRoster::includesAttempt`. |
| `ClearScores` | Roster guard → `GradingRoster::includesAttempt`. |
| `FinalizeGradingSession` | `ensureAllRosterApplicantsScored` iterates `GradingRoster::queryFor` attempt ids. Empty eligible roster → existing "no applicants to grade" rejection. |

## Reads

| Controller | Change |
|---|---|
| `GradingController::index` | Dashboard progress + totals computed over `GradingRoster` per session; expose `applicants_absent` per row. |
| `GradingController::show` | Roster = `GradingRoster::queryFor(...)->with('admissionAttempt.identity')`. Add `stats`: `assigned`, `present_and_submitted`, `absent`, `no_submission`, `pending_attendance`. |
| `GradingScoreController::show/update/destroy` | `assertInRoster` → `GradingRoster::includesAttempt`. |

## UI

- `Assessment/GradingSession.svelte`: replace the `progress_stats` strip with `assigned / present & submitted / absent / no submission`; keep `total/scored/pending` derived over the eligible roster; show an inline note when `absent + no_submission > 0`.
- `Assessment/Grading.svelte`: row shows eligible `applicants_total` and `applicants_absent` (secondary text); empty state unchanged.

## Tests (Pest)

- `GradingWorkflowTest` fixture records attendance (present) + submission per assignment before close (domain Actions directly; fixture not exercising proctor authz).
- New cases: absent excluded from roster + finalize succeeds without their scores; present-no-submission excluded; close rejected when attendance missing; attendance guard passes with present+absent mix; dashboard/session stats correct.
- `ReleaseFlowTest` gains the same pre-close attendance+submission recording.

## Verification

`docker compose exec app php artisan test --compact tests/Feature/Assessment tests/Feature/Release tests/Feature/Examination`
