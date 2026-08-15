# Spec — Grading Session (manual grading core)

**Spec ID:** `grading-session`
**Change:** `grading-workflow`

## Requirements

### GS-1 — Open a grading session for an exam session

- Actor with `GUIDANCE_SCORING_MANUAL` may open one grading session per exam session.
- Opening when one already exists returns the existing session (idempotent — unique `exam_session_id`).
- Session starts `status=open`, `opened_at=now`, `opened_by_user_id=actor`.

### GS-2 — Session roster is derived from exam assignments

- The session show page lists each active `ExamAssignment` of the exam session → its `AdmissionAttempt` (name, reference code).
- Each row shows `domains_complete` (distinct active aptitude areas with a score record) and `scored` (all active domains present).
- No applicant pivot table is introduced (one core — assignments are the roster).

### GS-3 — Multi-domain score save is transactional and idempotent

- One request saves scores for many aptitude areas for one attempt in the session.
- Save is one `DB::transaction`; each domain is an upsert on the unique `(grading_session_id, admission_attempt_id, aptitude_area_id)`.
- Request-level `idempotency_key` → deterministic per-domain key; replaying the same request is a no-op (REQ-VS-021).
- Attempt not in the session roster → denied (404/validation).
- Session finalized → scores rejected (immutable).
- Each record stores `recorded_by_user_id=actor` (audit).

### GS-4 — Scores can be cleared

- Actor may clear all domain scores for one attempt in an open session.
- Finalized sessions reject clearing.

### GS-5 — Finalize requires complete scores

- Finalize succeeds only when every roster attempt has a score record for **every active aptitude area** (REQ-VS-009).
- Missing domain or missing attempt → reject with a clear message, no partial state.
- Finalize is version-bumped (`version+1`), sets `status=finalized`, `finalized_at=now`, `finalized_by_user_id=actor`.
- Expected-version guard: stale confirm with a drifted version is rejected.

### GS-6 — Capability gate

- All grading routes require `GUIDANCE_SCORING_MANUAL`; denial renders the denied page state / 403 (never role strings).

### GS-7 — Manual core survives degraded mode

- Scoring, roster, and finalize work with `nexi.mode=degraded` / provider disabled (REQ-VS-008/017); Nexi state never blocks the manual path.

## Non-goals (this spec)

- Staged score import, result-sheet printing, normalized/percentile formula engine, release integration — separate cuts.
