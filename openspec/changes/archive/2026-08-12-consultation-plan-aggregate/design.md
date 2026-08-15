# Design: Consultation Sessions + Release/Consultation Split + Enrollment Eligibility

**OpenSpec:** `openspec/changes/2026-08-12-consultation-plan-aggregate/`
**Authority:** founder direction 2026-08-13 (proposal.md) · GF-06 §8.1 (mode + reservation workflow) · GF-03 §4 · GF-22 (page/state contract) · exam scheduling implementation as pattern authority · legacy `consultation_summaries` (evidence only — rejected target shape).

## 1. Engineering decision: sibling module + shared reservation primitive

- New **`Consultation` module** structurally mirrors `Examination`: own tables, actions, queries, pages. Domain-owned state (modular-monolith doctrine).
- **Extract one shared concurrency primitive** (e.g. `Scheduling\ReservationGuard`) consumed by both `ReserveExamSlot` and the consultation reserve action: lock session+attempt → cycle match → one-active-assignment → capacity-from-room guard → idempotent reuse. Audit + after-commit notification stay per-domain.
- **Reject** a generic `Sessionable` framework: two consumers, diverging eligibility/conduct, retrofit risk on shipped exam code, module-owned-state doctrine.
- UI familiarity via shared kit components (booking toggle, calendar cards, badges, confirm dialog) — presentation-level reuse only.

## 2. Domain shape

```
ConsultationSession  (mirrors ExamSession)
 ├─ academic_cycle_id, code, room_id (venue: "Guidance Office", "Covered Court", video-call room…)
 ├─ scheduled_at — admin sets any date/time; no invented granularity
 ├─ status: draft → published → in_progress → completed | cancelled
 ├─ booking_mode: staff_only | open_for_reservation (admin can DISABLE booking anytime)
 ├─ reservation_opened_at / reservation_closed_at
 └─ ConsultationAssignment[]  (mirrors exam_assignments)
      ├─ source: staff_assigned | applicant_reserved
      ├─ status: assigned → consulted | cancelled (cancelled_at + reason; never deleted)
      ├─ unique (session_id, attempt_id)
      └─ terminal: ConsultationDecision (existing) — the "conduct"/advising step
```

### Tables (GF-19 style)

**`consultation_sessions`**: id, academic_cycle_id FK, code unique, room_id FK, scheduled_at, status enum, booking_mode enum, reservation_opened_at/closed_at nullable, version, published_by nullable, timestamps.

**`consultation_assignments`**: id, consultation_session_id FK, admission_attempt_id FK, source enum, status enum, assigned_by/reserved_by nullable, consulted_at nullable, cancelled_at + cancel_reason nullable, version, timestamps, **unique (session_id, attempt_id)**.

**`admission_attempts`** (or pipeline projection): consulted/advised milestone derived from confirmed `ConsultationDecision` — no new editable state.

- **`result_releases`**: `mode` enum (f2f/online) set at release time (`ReleaseResult` gains a mode input) — selects the release notification template (F2F vs online messaging; legacy `ResultReleased`/`ResultReleasedF2F` parity). Gate logic uniform; mode is never a prerequisite.

## 3. Invariants

1. Assign/reserve is **blocked unless the attempt is result-ready** (grading finalized + result document prepared). This alone yields the early-assessment advantage.
2. One active assignment per attempt (across sessions); unique per (session, attempt).
3. Capacity = room capacity; guarded inside the DB transaction (same race-safe pattern as `ReserveExamSlot`).
4. Reserve only when `booking_mode = open_for_reservation`, session `published`, same cycle, result-ready; idempotent reuse of existing assignment.
5. Cancellation/rebooking = status transition + history; never a delete.
6. Every transition emits audit (publish, open/close reservation, assign, reserve, reassign, cancel, complete).
7. `ConsultationDecision` is the terminal advising record; counselor suggestions and applicant selections are never merged.

## 4. Release / consultation split (founder 2026-08-13)

- `ReleaseResult` and `RecordConsultationDecision` are **independent actions**. Release prerequisite: grading finalized + result document prepared. Consultation is NOT a release prerequisite.
- F2F mode: printed sheets at the session; online mode: scores visible digitally, no office printing.
- Consultation completion (decision **confirmed**) → attempt projection milestone **Advised** (new pipeline stage after "Result released").
- **Enrollment eligibility** = derived query: `accepted + grading finalized + result released + advised`. Never hand-set; recomputes on any change (incl. unrelease).
- ⚠️ **Current-code conflict to fix in this change:** `ReleaseResult` (`system/app/Release/Actions/ReleaseResult.php`) requires a confirmed consultation decision before release. The 2026-08-13 refinement overrides: drop that prerequisite (task I2) — release needs grading finalized + document prepared only.


## 5. Commands (`app/Consultation/Actions/`, mirroring `Examination`)

- `CreateConsultationSession`, `UpdateDraftConsultationSession`
- `PublishConsultationSession`, `UnpublishConsultationSession`
- `OpenConsultationSessionReservation`, `CloseConsultationSessionReservation` (the admin disable-booking toggle)
- `AssignApplicantToConsultationSession` (result-ready precheck + capacity guard)
- `ReserveConsultationSlot` (shared reservation primitive; FCFS + idempotency)
- `ReassignApplicantConsultationSession`, `CancelConsultationAssignment` (history-preserving)
- `CompleteConsultationSession`, `CancelConsultationSession`
- Existing, changed: `ReleaseResult` — drop the consultation-decision prerequisite (see §4); idempotency, versioning, audit, notification stay unchanged. `RecordConsultationDecision` (confirm → advised milestone), `UnreleaseResult`, `PrepareResultDocument` unchanged.

## 6. Queries / presenters

- `ConsultationSessionListQuery`, `ConsultationSessionDetailQuery` (roster + counts)
- `ConsultationReadyPoolQuery` — result-ready attempts with no active assignment (staff convenience view; leftovers)
- `ApplicantConsultationCalendar` — portal: open sessions + remaining capacity + own assignment
- `EnrollmentEligibilityQuery` — registrar search: status card + missing-requirement list

## 7. Capabilities + routes

- New `GUIDANCE_CONSULTATION_PLAN` (session lifecycle: create/publish/assign/open-close) — `CapabilityCatalog` + seeder.
- Reuse `GUIDANCE_CONSULTATION_DECIDE` (decision recording).
- New `REGISTRAR_ENROLLMENT_ELIGIBILITY` (registrar lookup; status-level facts only; every lookup audited).

| route | verb | controller | capability |
|---|---|---|---|
| `consultation.sessions` | GET | index | PLAN |
| `consultation.sessions.create` / `.store` | GET/POST | create/store | PLAN |
| `consultation.sessions.show` | GET | show | PLAN or DECIDE |
| `consultation.sessions.publish` / `.unpublish` | POST | publish/unpublish | PLAN |
| `consultation.sessions.reservation.open` / `.close` | POST | open/closeReservation | PLAN |
| `consultation.sessions.assign` | POST | assignApplicant | PLAN |
| `consultation.sessions.assignments.cancel` | POST | cancelAssignment | PLAN |
| `consultation.sessions.complete` / `.cancel` | POST | complete/cancelSession | PLAN |
| `consultation.reserve` | POST | reserveSlot (portal) | applicant own-attempt |
| `registrar.enrollment-eligibility` | GET | search (registrar) | ENROLLMENT_ELIGIBILITY |

## 8. Pages / state (GF-22)

- **`Consultation/ConsultationSessions.svelte`** — list: code, date/time, room, filled/capacity, status badge, booking-mode badge, actions (publish, open/close booking, complete). States: ready / empty / denied.
- **`Consultation/ConsultationSessionCreate.svelte`** — date+time, room pick with capacity hint, explicit booking-mode pick (`staff_only` | `open_for_reservation`) — no silent default (founder 2026-08-14); DB column default stays `staff_only` as non-form safety only.
- **`Consultation/ConsultationSessionShow.svelte`** — roster, ready-pool panel + assign, booking toggle, complete/cancel.
- **`Applicant/Consultation.svelte`** — mirrors `Applicant/Schedule.svelte` states: denied / not_result_ready ("Consultation unlocks after your result is ready") / no_open_sessions / booking_open (cards: date, time, room, remaining slots, Reserve + confirm dialog, idempotency key) / assigned_or_reserved ("Your consultation schedule" + source badge) / full / closed.
- **`Registrar/EnrollmentEligibility.svelte`** — search by name/applicant no/reference → eligibility card: accepted, exam done, result released, consultation advised (+ by/date), Eligible / missing requirement.
- Counselor decision screen (existing) gains the **Advised** status context.
- **Frontend shape (founder 2026-08-14):** the staff **main consultation page is the existing `Release/Consultation.svelte`** (the actual consultation: decision recording). It gains a **Scheduling CTA** linking to `Consultation/ConsultationSessions.svelte` — the scheduling surfaces (list/create/show) mirror the exam pages and are reached from inside the consultation page, not only from the sidebar.
- Breadcrumbs: Assessment → Consultation (main page) → Session scheduling (list) → Create / Session detail; Registrar → Enrollment eligibility. Final crumb drives document title; mobile collapses per GF-22.


## 9. Tests

- Shared primitive: exam reservation behavior unchanged (existing suites stay green after refactor).
- Session lifecycle: create → publish → open/close reservation → complete/cancel; audit per transition.
- Assign: blocked when not result-ready; capacity guard; no double-assign; cycle mismatch denied.
- Reserve: FCFS race (concurrent), idempotency reuse, full-session denial, disabled-booking denial, cancel reopens capacity + history.
- Decision: confirm → advised milestone; defer → not advised; suggestions vs selections distinct.
- Enrollment eligibility: all four conditions; missing-requirement outputs; unrelease recomputes; registrar scope (no notes/suggestions); audited lookups.
- Playwright: staff create/assign/toggle flow; applicant reserve + states; registrar search.

## 10. Sequencing

1. **Phase 0** — extract shared `Scheduling\ReservationGuard`; refactor `ReserveExamSlot` onto it (exam suites prove no change).
2. **Phase 1** — migrations + models.
3. **Phase 2** — actions + audit (staff-led path first: create/publish/assign/complete).
4. **Phase 3** — staff UI (list/create/detail + ready pool + booking toggle).
5. **Phase 4** — reservation toggle + portal page.
6. **Phase 5** — advised milestone + registrar eligibility lookup.
7. **Phase 6** — seeds (demo sessions both modes, result-ready applicants) + Pest/Playwright + `HANDOFF.md` (logins, URLs, reset steps).
8. **Phase 7** — `result_releases.mode` metadata + release-prerequisite tests.

## 11. Open client decisions (blockers before build)

1. ~~Release-before-consultation order~~ — RESOLVED 2026-08-14: **release first**; consultation required after.
2. ~~"Advised" definition~~ — RESOLVED 2026-08-14: **decision confirmed** (not attendance).
3. ~~Registrar lookup shape~~ — RESOLVED 2026-08-14: **status search** (eligibility card included); Registrar counter = physical status check for students without phones; status facts only, no scores/notes; may centralize later.
4. ~~Applicant printing~~ — RESOLVED 2026-08-14: **flexible** — portal copy optional (never required), Guidance may print impromptu, future paperless switch; print UI reachable in both modes now.
5. ~~Public roster~~ — RESOLVED 2026-08-14: **publishable option** (adopt exam publish pattern); deferred: shared/agnostic public announcement module for later (covers exam rosters too).
6. ~~Leftovers~~ — RESOLVED 2026-08-14: **office assigns via ready pool**; no auto-slot.
7. ~~Self-booking day one vs admin-led first~~ — RESOLVED 2026-08-14: both from day one, no default; office picks booking_mode at create, toggle anytime.

## 12. Requirements trace (validation 2026-08-14)

- PRD FR-020 (publish consultation availability) · FR-021/FR-022 (draft/confirm/defer + evidence) · FR-023 (suggestions separate from selections) · FR-024/FR-025 (prepare/print where required; release under approved rules) · FR-028 (portal retrieval of released results) · FR-029 (portal own schedule/availability) · FR-030 (own-data isolation) · FR-031 (typed document contracts) · FR-032 (idempotent after-commit delivery).
- BR-007 (preserve vocabulary, rewrite state/authz/concurrency) · BR-023 (manual scheduling is authoritative baseline) · BR-024 (no autonomous placement decisions).
- Legacy disposition: `ConsultationSummary` + `ReleaseController` + `ConsultationSummaryService` — REJECT as target shape (one row mixes planning/scheduling/outcome); PRESERVE staff vocabulary and list-first, decision-recorded UX. Mode evidence: `SystemSetting::releaseMode()` + `ResultReleased`/`ResultReleasedF2F` → per-row `mode` + template selection (I1).
- **Deferred to later beats (founder 2026-08-14):** admission models/interfaces, program quotas, Program Head messaging interface. Not in this change.
- Stories: `stories.md` in this change folder.
