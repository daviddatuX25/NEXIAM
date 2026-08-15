# Design — E2 Grading Workflow

**OpenSpec:** `grading-workflow`
**Authority:** REQ-VS-008 (manual scoring complete fallback), REQ-VS-009 (finalize requires complete valid scores), REQ-VS-017 (outage does not block manual core), REQ-VS-021 (transactional multi-area writes, manual part). Legacy UX basis: `Grading/Dashboard.svelte`, `Grading/Session.svelte`, `Grading/ScoreInput.svelte`.

## Domain model (existing greenfield tables)

- `grading_sessions`: `id`, `exam_session_id` (UNIQUE), `status` (`open|review|finalized`), `version`, `opened_at`, `finalized_at`.
- `score_records`: `grading_session_id`, `admission_attempt_id`, `aptitude_area_id`, `score` (decimal 8,2), `recorded_by_user_id`, `idempotency_key` (UNIQUE), unique `(grading_session_id, admission_attempt_id, aptitude_area_id)`.
- Roster source: `ExamSession.assignments → ExamAssignment.admissionAttempt → AdmissionAttempt` (active assignments only).

**Migration (small):** add `opened_by_user_id` (nullable FK users) + `finalized_by_user_id` (nullable FK users) to `grading_sessions` for audit parity with legacy.

## State machine

```text
open ──(finalize: all attempts × all active domains scored)──▶ finalized
 │                                                              │
 └── scores editable ──────────────────────────────────────────┘ (finalized = immutable)
```

- `review` is reserved (not used this cut; stays in the enum for import follow-on).
- Finalize is version-bumped (`version + 1`); no un-finalize, no score edits after finalize (legacy lock preserved).

## Capabilities

| Route | Capability (existing) |
|---|---|
| `GET /grading` (index) | `GUIDANCE_SCORING_MANUAL` |
| `POST /grading` (open session) | `GUIDANCE_SCORING_MANUAL` |
| `GET /grading/sessions/{gradingSession}` (show) | `GUIDANCE_SCORING_MANUAL` |
| `GET /grading/sessions/{gradingSession}/attempts/{attempt}` (score input) | `GUIDANCE_SCORING_MANUAL` |
| `PUT /grading/sessions/{gradingSession}/attempts/{attempt}/scores` (save) | `GUIDANCE_SCORING_MANUAL` |
| `POST /grading/sessions/{gradingSession}/finalize` | `GUIDANCE_SCORING_MANUAL` |

## Actions

### `OpenGradingSession`
- Input: `exam_session_id`.
- Idempotent: existing open/finalized session for that exam session → return it (no duplicate; unique key).
- Creates `GradingSession` (`status=open`, `opened_at=now`, `opened_by_user_id=actor`).

### `RecordScores`
- Input: `grading_session`, `admission_attempt_id`, `scores: [{aptitude_area_id, score}]`, `idempotency_key`.
- Guards: attempt must be in the session roster (via exam session assignments); session must not be finalized.
- One `DB::transaction`: upsert each domain score (`updateOrCreate` on the unique triple), `recorded_by_user_id=actor`.
- Request-level `idempotency_key` → a per-domain deterministic key (`"{key}:{attempt}:{area}"`) so replays are no-ops and the unique constraint holds.

### `FinalizeGradingSession`
- Input: `grading_session`, actor, expected `version`.
- Guards: session not already finalized; `expected_version` matches (stale reject).
- Completeness (REQ-VS-009): every roster attempt has a score record for every active aptitude area; else `RuntimeException` with a clear message (no partial finalize).
- Transaction: `status=finalized`, `finalized_at=now`, `finalized_by_user_id=actor`, `version+1`.

## Pages (mimic legacy UX)

1. **`Assessment/Grading.svelte`** (rewrite of the 17-line scaffold) — legacy `Grading/Dashboard` shape: session list (exam date/time, room, status, applicants total/scored, finalized_at), progress summary, "Open grading session" button → exam-session picker (or link to session show for existing). Empty state when no aptitude areas are configured.
2. **`Assessment/GradingSession.svelte`** (new) — legacy `Grading/Session` shape: session header (exam code/date/room), workflow status badge, progress stats (total/scored/pending), applicant table (name, reference, domains complete, scored ✓, actions → score input). Finalize button gated on completeness; server rejects with validation message.
3. **`Assessment/GradingScoreInput.svelte`** (new) — legacy `Grading/ScoreInput` shape: applicant header (name/ref/exam), one row per active aptitude area (code, name, current score), save button (multi-domain, transactional), clear-scores action, finalized lock notice.

## Verification

Pest `tests/Feature/Assessment/GradingWorkflowTest.php`:
- open session for exam session (idempotent re-open)
- roster derived from exam assignments (attempts listed, scored statuses)
- multi-domain save is transactional + idempotent (replay same key = no dup)
- attempt not in roster denied
- save after finalize denied
- finalize rejects incomplete (missing domain / missing attempt)
- finalize succeeds when complete; version bumped; scores immutable after
- capability deny (non-guidance user → 403)
- degraded/outage: manual scoring works with `nexi.mode=degraded` (REQ-VS-017)

## Files

```text
system/app/Assessment/Actions/OpenGradingSession.php
system/app/Assessment/Actions/RecordScores.php          (replaces/augments RecordManualScore)
system/app/Assessment/Actions/FinalizeGradingSession.php (guard + completeness added)
system/app/Http/Controllers/Assessment/GradingController.php  (thin; index/store/show/finalize)
system/app/Http/Controllers/Assessment/GradingScoreController.php (new; show/update/destroy)
system/app/Http/Requests/Assessment/{OpenGradingSessionRequest,RecordScoresRequest}.php
system/app/Models/Assessment/GradingSession.php  (add openedBy/finalizedBy rels)
system/database/migrations/..._add_auditors_to_grading_sessions.php
system/resources/js/Pages/Assessment/{Grading,GradingSession,GradingScoreInput}.svelte
system/routes/web.php  (grading routes)
system/tests/Feature/Assessment/GradingWorkflowTest.php
```
