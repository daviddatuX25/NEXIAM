# Handoff: E2 Grading Workflow

**Date:** 2026-08-11
**Active OpenSpec:** `openspec/changes/grading-workflow/` → archived `archive/2026-08-11-grading-workflow/` on PASS
**Checkpoint:** A1–E2 SHIPPED — grading sessions → per-applicant multi-domain scoring → finalize, all green (14 passed / 201 assertions + regression gate). Remaining: none for this cut; known pre-existing suite gaps recorded in evidence.
**Prior:** `archive/2026-08-11-applicant-choose-slot/` PASS

## Scope

Manual grading core of the admission-to-release journey: open a grading session per exam session → per-applicant multi-domain transactional score entry → finalize only when every applicant is completely scored. Rewrite of legacy `Grading/*` controllers + `GradingSessionService`/`ScoreInputService` on the existing greenfield `grading_sessions`/`score_records` tables with thin controllers, Actions, Form Requests, capability authz, and idempotency.

## What shipped

- B1 `opened_by_user_id` + `finalized_by_user_id` migration; B2 idempotent `OpenGradingSession` + `OpenGradingSessionRequest` + `POST /grading`.
- B3 `Grading.svelte` dashboard (legacy `Grading/Dashboard` shape) with progress projection; B4 `GradingSession.svelte` roster show.
- C1 transactional `RecordScores` + `RecordScoresRequest` + `PUT .../scores`; C2 `GradingScoreInput.svelte` (legacy `ScoreInput` shape); C3 `ClearScores` + `DELETE .../scores`.
- D1 `FinalizeGradingSession` (roster-based completeness × expected-version guard × version bump × immutable-after + `finalized_by_user_id`); D2 `POST .../finalize` + gated button.
- E1 `GradingWorkflowTest` (14 tests); demo seeder (below).

## Demo seed (`GradingDemoSeeder`)

Provides a ready-to-click grading scenario without hand-entering fixtures:

```bash
cd system
docker compose exec app php artisan db:seed --class=GradingDemoSeeder
```

| Actor | Login | Notes |
|---|---|---|
| Guidance Counselor | `guidance.counselor@nexiam.test` / `password` | has `GUIDANCE_SCORING_MANUAL`; opens scoring, enters scores, finalizes |
| Registrar Clerk | `registrar.clerk@nexiam.test` / `password` | accepts applicants (roster builders) |
| Applicant (assigned) | per `JourneyFixtureSeeder` access key + birthdate | appears on grading roster; no grading UI this cut |

Demo state after seed: one exam session closed for grading with **two assigned applicants** — one already fully scored (shows progress) and one unscored (complete the journey by scoring + finalizing).

## Click path

1. Guidance → login → `/grading` → dashboard (sessions list with progress).
2. "Open grading session" → pick the demo exam session → session show page (roster from exam assignments).
3. Row action on the unscored applicant → score input → enter a score for each aptitude area → save (transactional).
4. Back to session → finalize (only enabled when all applicants × all domains scored) → finalized, immutable.

## Recreate

```bash
cd system
docker compose build app && docker compose up -d --force-recreate app queue
docker compose exec app php artisan foundation:reset --force
docker compose exec app php artisan db:seed --class=GradingDemoSeeder
```

## Proof

```bash
docker compose exec app php artisan test --compact tests/Feature/Assessment/GradingWorkflowTest.php
docker compose exec app php artisan test --compact tests/Feature/Assessment/GradingAccessTest.php
```

Evidence: `docs/greenfield-foundation/evidence/grading-workflow-2026-08-11.md`

## Known gaps (pre-existing, not this cut)

Full-suite at PASS: 212 passed / 4 failed — `UiKit\GalleryTest` ×2 (Nexi availability `hidden` at HTTP time; uncommitted ai-assistant-foundation surface/pack work), `Unit\Authorization\ExamCapabilitySyncTest` (D-016 catalog drift; test file itself new/untracked), `Feature\Admission\AdmissionFlowTest` attendance idempotency (403; uncommitted capability-authz work). None touch `Assessment/` grading code. Follow-up: dedicated OpenSpec change to restore Nexi availability + capability catalog parity, then full suite green.

## Amendment (2026-08-11, post-PASS) — GF-06 open preconditions

Robustness review vs GF-06 §6 found the manual open path missing its preconditions. Shipped:

- `OpenGradingSession` requires the exam session to be `completed` and to have ≥1 active assignment (no unfinalizable empty sessions); idempotent re-open still returns the existing session.
- `GradingController::store` surfaces rejections as `errors.open` (no 500) and reports "already exists" on re-open; candidate picker now lists only completed ungraded sessions.
- `Grading.svelte` dialog shows `errors.open` and stays open on failure.
- Suite reconciled to the fixture-based version + 5 precondition tests → **19 passed / 261 assertions**.

Recorded follow-ons (evidence file): review-state commands (`ReturnGradingForCorrection` / `ReopenGradingSession`), `score_records` source/provenance for OMR/import, grading↔exam-session lifecycle coupling (empty-roster close auto-open, cancel/reopen after grading starts, D-075), attendance/submission vs completeness, direct-assessment eligibility.

Proof (updated):

```bash
docker compose build app   # app image bakes source — rebuild after any change
docker compose exec app php -d memory_limit=1G vendor/bin/pest --compact tests/Feature/Assessment/GradingWorkflowTest.php
```
