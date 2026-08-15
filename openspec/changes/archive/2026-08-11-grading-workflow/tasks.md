# Tasks: E2 Grading Workflow

## A — Open

- [x] A1 Confirm active OpenSpec pointer (project-context, master reference) → `grading-workflow`; verify grading scaffold baseline green before overlay work (`GradingWorkflowTest` seeds; existing `GradingAccessTest`/release-flow scores still pass)

## B — Sessions + roster

- [x] B1 Migration: add `opened_by_user_id` + `finalized_by_user_id` to `grading_sessions` (nullable FK users)
- [x] B2 `OpenGradingSession` Action (idempotent open per exam session) + `OpenGradingSessionRequest` + `POST /grading` route (GS-1)
- [x] B3 `GET /grading` index: session list with progress (total/scored, status, finalized_at) — rewrite `Assessment/Grading.svelte` to legacy `Grading/Dashboard` shape (GS-2)
- [x] B4 `GET /grading/sessions/{gradingSession}` show: roster from exam assignments, scored status, progress stats — new `Assessment/GradingSession.svelte` (GS-2)

## C — Scores

- [x] C1 `RecordScores` Action (transactional multi-domain upsert, per-domain deterministic idempotency keys, roster + finalized guards) + `RecordScoresRequest` + `PUT .../attempts/{attempt}/scores` (GS-3)
- [x] C2 `GET .../attempts/{attempt}` score input page + `Assessment/GradingScoreInput.svelte` (legacy `Grading/ScoreInput` shape; one row per active aptitude area) (GS-3)
- [x] C3 Clear-scores path (DELETE action; finalized lock) (GS-4)

## D — Finalize

- [x] D1 `FinalizeGradingSession` completeness guard (every roster attempt × every active domain) + expected-version guard + version bump + immutable-after (GS-5)
- [x] D2 `POST /grading/sessions/{gradingSession}/finalize` route + finalize button on session page gated by completeness (GS-5)

## E — Proof

- [x] E1 Pest `GradingWorkflowTest`: open idempotent; roster derivation; transactional + idempotent multi-area save; not-in-roster denied; save-after-finalize denied; finalize rejects incomplete; finalize succeeds + version bump + immutable; capability deny; degraded-mode manual path (GS-6/GS-7, REQ-VS-008/009/017/021)
- [x] E2 HANDOFF + evidence note (demo logins, click path, recreate); update archive README + master reference + project-context on PASS
