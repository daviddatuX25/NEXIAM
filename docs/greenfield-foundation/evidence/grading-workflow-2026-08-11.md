# E2 Grading Workflow — Implementation Evidence

**Date:** 2026-08-11
**OpenSpec:** `openspec/changes/grading-workflow/`
**Runtime:** `system/` (Laravel 13 / Inertia 3 / Svelte 5)

## Status

**CUT SHIPPED.** Manual grading core of the admission-to-release journey: open a grading session per exam session, roster from exam assignments, transactional multi-domain score entry, roster-complete finalize, immutable after. Legacy `Grading/*` controllers + `GradingSessionService`/`ScoreInputService` dispositioned onto the existing greenfield `grading_sessions`/`score_records` tables.

## What was built

| Feature | Entry | Notes |
|---|---|---|
| Grading dashboard | `GET /grading` | Session list with scoring progress, "Open grading session" picker (legacy `Grading/Dashboard` shape) |
| Open session (idempotent) | `POST /grading` | `OpenGradingSession` Action; unique `exam_session_id` → re-open returns existing |
| Session show (roster) | `GET /grading/sessions/{gs}` | Roster derived from **active exam assignments** → attempts, scored status, progress stats, finalize gate |
| Score input | `GET .../attempts/{attempt}` | Per-applicant multi-domain input page (legacy `Grading/ScoreInput` shape) |
| Transactional save | `PUT .../attempts/{attempt}/scores` | `RecordScores` Action — one DB transaction, per-domain upsert on unique triple, deterministic per-domain idempotency keys (REQ-VS-021) |
| Clear scores | `DELETE .../attempts/{attempt}/scores` | Locked when finalized |
| Finalize | `POST /grading/sessions/{gs}/finalize` | Roster-complete guard (every active assignment × every active aptitude area), version bump, `finalized_by`, immutable after (REQ-VS-009) |

## Capability gates

All grading routes gate on `GUIDANCE_SCORING_MANUAL` (GF-21, no role strings). Staff sidebar "Grading" item was already capability-gated; unchanged.

## Pest evidence (2026-08-11)

```text
tests/Feature/Assessment/GradingWorkflowTest.php → 14 passed (201 assertions)
tests/Feature/Assessment/GradingAccessTest.php → 1 passed (1 assertion)
tests/Feature/Release/ReleaseFlowTest.php → 3 passed (17 assertions)
```

Run:

```bash
cd system
docker compose build app && docker compose up -d app
docker compose exec app php artisan test --compact \
  tests/Feature/Assessment tests/Feature/Release
```

Regression (2026-08-11): `Examination + Assessment + Release + Authorization` → **45 passed (496 assertions)**. `AdmissionFlowTest > attendance idempotency` fails with a 403 on `proctor.attendance` — **pre-existing, unrelated** (proctor-duty overlay path; no grading file is involved in that test). Frontend `npm run build` clean; pages `Assessment/Grading`, `Assessment/GradingSession`, `Assessment/GradingScoreInput` emit.

## REQ-VS map

| REQ-VS | Evidence |
|---|---|
| 008 (manual scoring fallback) | `GradingWorkflowTest` degraded-mode test; manual UI path independent of Nexi |
| 009 (finalize requires complete) | Roster-complete guard tests (incomplete roster + zero-score attempt both rejected) |
| 017 (outage does not block manual core) | `nexi.mode=degraded` scoring + finalize test |
| 021 (transactional multi-area writes) | Multi-area transactional save + replay idempotency test |

## Gated non-goals (follow-on cuts)

Staged score import (REQ-VS-021 import part), result-sheet printing/mark-printed (PDF/DOCX), normalized/percentile formula engine (needs aptitude formula storage), release/consultation integration beyond existing scaffold.

## Amendment (2026-08-11, post-PASS) — GF-06 open preconditions + suite reconciliation

Robustness review against GF-06 §5/§6/§7 found one missing precondition and several recorded follow-ons. Shipped:

- **`OpenGradingSession`** now enforces GF-06 §6 preconditions: the exam session must be `completed` (never `draft`/`published`/`in_progress`/`cancelled`) and must still have ≥1 active assignment (an empty roster would create a permanently unfinalizable session). Idempotent re-open returns the existing session *before* the preconditions, so re-opening an already-graded session still works.
- **`GradingController::store`** surfaces domain rejections as a validation flash (`errors.open`) instead of a 500, and differentiates "Grading session opened." vs "Grading session already exists." via `wasRecentlyCreated`.
- **`GradingController::index`** candidate picker now lists only `completed` sessions without a grading session (was: everything except `cancelled`).
- **`Assessment/Grading.svelte`** open dialog renders `errors.open` and stays open on failure (`onError` vs `onSuccess`).
- **Tests:** reconciled to the fuller fixture-based suite (`buildGradingFixture`; includes close-auto-open, clear-scores, stale-version finalize, index-progress coverage) + 5 new precondition tests (non-completed reject, draft/in-progress/cancelled reject, empty-roster reject, candidates-scope, idempotent-reopen flash) → **19 passed (261 assertions)**. Note: the `app` image bakes source (no bind-mount) — rebuild (`docker compose build app`) is required to run new code.

Recorded follow-ons (NOT this cut — stop-and-record, do not invent):
- Review-state commands: `ReturnGradingForCorrection` (review→open) and `ReopenGradingSession` (finalized→new open version, prior superseded) — GF-06 §6.
- `score_records` needs a `source`/provenance column + staged-import batches before OMR/automated scoring can land — GF-06 §6/§7, D-007, Tier B.
- Grading↔exam-session lifecycle coupling: close auto-open on an empty roster (all-no-show), cancel/reopen after grading starts (D-075), "opens grading exactly once".
- Completeness vs attendance/submission: how absent / not-submitted attempts are scored (GF-06 §5.2) — currently any active assignment must be scored.
- Direct-assessment eligibility for grading open (GF-03) once that path exists.
