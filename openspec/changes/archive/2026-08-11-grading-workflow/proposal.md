# Change Proposal: E2 Grading Workflow

**Change ID:** `grading-workflow`
**Status:** ACTIVE
**Phase:** E2 connected admission-to-release slice (continuation)
**Depends on:** `exam-session-scheduling` PASS (`ExamSession` + `ExamAssignment` + booking modes), `exam-proctor-duty-assign` PASS (roster wiring), E2 connected-slice scaffold PASS (command spine + `GradingSession`/`ScoreRecord` tables)
**Archived prior:** `openspec/changes/archive/2026-08-11-applicant-choose-slot/`

## Intent

Deliver the **manual grading core** of the admission-to-release journey: open a grading session for an exam session, enter per-applicant multi-domain scores transactionally, and finalize only when every applicant is completely scored (REQ-VS-008/009/017/021 manual part). This is the biggest remaining legacy-parity gap in the E2 slice.

Current greenfield state is a scaffold: `/grading` bare list + single-score `recordScore` + `finalize` with no roster, no session page, no per-applicant input page, no completeness gate.

## Disposition from legacy (authority: `legacy_codebase/application/app/Http/Controllers/Grading/*` + `app/Services/GradingSessionService|ScoreInputService`)

| Legacy | Disposition |
|---|---|
| `GradingController::index` (dashboard list w/ progress) + `store` (open for exam session) | Keep UX (list → create on list); rewrite as thin controller + `OpenGradingSession` Action; one grading session per exam session (unique `exam_session_id`) — re-open is idempotent redirect |
| `GradingSessionController::show` (applicant roster, scored status, printed flag, workflow) | Keep shape; roster derived from `ExamSession.assignments → AdmissionAttempt` (greenfield has no applicant pivot — one core) |
| `updateWorkflowStatus` (in_progress ↔ finalized; `ensureAllApplicantsScored`) | Rewrite as `FinalizeGradingSession` Action with completeness validation inside a transaction (REQ-VS-009); no un-finalize in this cut |
| `GradingScoreController::show/update/destroy` (per-applicant score input page, multi-domain) | Keep page/UX; rewrite as thin controller + `RecordScores` Action — **multi-domain transactional save** with request-level idempotency key (REQ-VS-021), finalize lock |
| `ScoreInputService` formula / conversion_table / percentile machinery | **Follow-on cut** — greenfield `AptitudeArea` stores code/name/version/is_active only; this cut stores raw `score` per domain (manual fallback). Normalized/percentile engine + formula storage = separate OpenSpec |
| `ensureDraftSummary` → `ConsultationSummary` + pipeline `scored`/`graded` | **Follow-on cut** — release/consultation integration beyond the existing scaffold; this cut ends at finalized grading session |
| `ScoreImportController` (CSV/XLSX analyze/preview/confirm/template) | **Follow-on cut** — REQ-VS-021 staged imports get their own OpenSpec (imports/bulk queue) |
| `ReleasePrintController` (result sheets PDF/DOCX, bulk print, mark-printed) | **Follow-on cut** — printing/mark-printed in the print/report queue |

## Founder locks (apply)

1. **One core** — session roster and score input share the ordinary exam/assessment queries; no AI-only or import-only copy.
2. **Capability authz (GF-21)** — all grading routes gate on existing `GUIDANCE_SCORING_MANUAL` via capability middleware (no role strings).
3. **Transactions + idempotency** — multi-area score save is one DB transaction; request-level `idempotency_key` (unique on `score_records`); finalize is version-bumped and immutable.
4. **Thin controllers** — Form Requests (validated payloads), Actions own domain invariants, controllers delegate (project convention §3.8).

## Out of scope (explicit)

- Score import (staged CSV/XLSX) — REQ-VS-021 import part → follow-on.
- Result-sheet printing / mark-printed / PDF/DOCX — follow-on.
- Normalized/percentile formula engine — needs aptitude formula storage → follow-on.
- Release/consultation integration beyond existing scaffold — follow-on.
- Nexi assistant for grading — AI returns later; this is manual core.
