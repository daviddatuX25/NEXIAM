# Stories: Consultation Sessions + Release/Consultation Split + Enrollment Eligibility

**Trace:** PRD FR-020–FR-032 · BR-007, BR-023, BR-024 · GF-06 §8.1 · GF-22.
**Legacy evidence:** `legacy_codebase/application/app/Models/ConsultationSummary.php`, `Http/Controllers/ReleaseController.php`, `Services/ConsultationSummaryService.php`, `SystemSetting::releaseMode()`, `Notifications/ResultReleased.php` + `ResultReleasedF2F.php`.
**Disposition:** REJECT legacy target shape (single `consultation_summaries` row mixes planning/scheduling/outcome); PRESERVE staff vocabulary, list-first journeys, decision-recorded UX, mode-specific release messaging. Rewrite authz/transactions/concurrency.

## Epic C1 — Session scheduling (exam mirror) — Phases 0–4, NOT gated by A1–A6

- **C1.1 Create session (FR-020).** G: Guidance staff on create page. W: submit date/time, room, explicit booking_mode pick. T: draft session saved + audit `consultation.session_created`; validation errors per-field. States: ready / denied (no PLAN capability).
- **C1.2 Session lifecycle (FR-020).** G: published session. W: publish/unpublish/in-progress/complete/cancel. T: legal transitions only, each with audit; unpublish/cancel require reason, kept in history. Denied transitions return typed conflict.
- **C1.3 Booking toggle (FR-020).** G: published session. W: office opens/closes reservation. T: closed blocks new reserves, existing assignments unaffected; `reservation_opened_at`/`closed_at` recorded; audit. Reopen re-arms.
- **C1.4 Staff assign (FR-020, BR-007).** G: result-ready attempt, staff on session detail. W: assign. T: assignment created (source `staff_assigned`); blocked when not result-ready; capacity from room enforced in-transaction; no silent overwrite of active assignment; audit `consultation.assigned`.
- **C1.5 Applicant reserve (FR-029, FR-030, BR-023).** G: applicant portal, published + open session, own attempt result-ready. W: reserve (idempotency key). T: FCFS via shared `Scheduling\ReservationGuard`; full/closed/not-ready/cycle-mismatch denied with truthful states; duplicate key returns existing assignment; audit + after-commit notification.
- **C1.6 Reassign / cancel assignment (BR-007).** G: staff. W: reassign to another session or cancel. T: old row cancelled with reason + timestamps, never deleted; new row per source; audit both; capacity re-guarded.
- **C1.7 Shared primitive extraction (BR-007).** G: existing exam suites. W: `ReserveExamSlot` refactored onto `Scheduling\ReservationGuard`. T: exam reservation behavior byte-identical; all exam Pest green; consultation reserve consumes the same primitive.

## Epic C2 — Consultation conduct + Advised — Phase 5, gated A2

- **C2.1 Decision confirmed → Advised (FR-021/FR-022).** G: decision recorded. W: counselor confirms. T: attempt projection gains Advised milestone; defer does not; decision versioned, never overwritten. [A2 pending: confirmed vs attendance]
- **C2.2 Suggestions separate (FR-023).** G: decision screen. W: counselor suggests program; applicant's chosen program stored separately. T: two fields, never merged, both attributable; Registrar never sees either.

## Epic C3 — Release split + mode — Phase 7, gated A1/A4

- **C3.1 Release without consultation prerequisite (FR-025).** G: grading finalized + document ready. W: ReleaseResult. T: release succeeds with no consultation decision; versioned current release, audit, after-commit notification; unrelease recomputes derived state.
- **C3.2 Release mode + template (FR-024, FR-031).** G: release action. W: mode f2f/online supplied. T: `result_releases.mode` recorded; notification template selected by mode (legacy parity); mode never gates prerequisites.
- **C3.3 Portal result view (FR-028, FR-029).** G: released result, applicant portal. T: applicant sees own released result + mode context; unreleased/other-applicant data denied. [online mode; A1 order note]

## Epic C4 — Registrar eligibility — Phase 5, gated A3

- **C4.1 Search + eligibility card (FR-030, BR-007).** G: registrar staff. W: search name/applicant no/reference. T: eligibility card = accepted + finalized + released + advised, computed; missing requirement named; status facts only (no notes/suggestions); every lookup audited. [A3: card vs printed list]

## Epic C5 — Seeds + proof — Phase 6

- **C5.1 Developer-ready seeds + HANDOFF.** G: clean Compose DB. W: reset + seed. T: one staff_only + one open_for_reservation consultation session, result-ready applicants, one advised example; HANDOFF.md lists logins, URLs, recreate steps; Pest + Playwright cover C1–C4 happy/denied/conflict paths.
