# Tasks: Consultation Sessions + Release/Consultation Split + Enrollment Eligibility

## A — Client decisions (all resolved 2026-08-14 by founder)

- [x] A1 Release-before-consultation order — **release first**; consultation is a separate required step after
- [x] A2 "Advised" definition — **decision confirmed** (approve/modify/override), not attendance
- [x] A3 Registrar lookup shape — **status search** (not just eligibility): students may walk into the Registrar office without phones; visibility of pipeline status facts only; eligibility card is part of the surface. Confidentiality: assessment/scores stay Guidance-owned — registrar sees status facts only (no scores, no counselor notes). May centralize later; search first.
- [x] A4 Applicant printing — **flexible**: portal offers student's own copy to download/print but NOT required (avoid extra expense); Guidance may print impromptu at advising (not batch); future paperless knob/switch disables printing; print interface stays reachable in both modes for now
- [x] A5 Public roster — **publishable option**: roster visible on the page when the admin/TA publishes (adopt exam's publish pattern). Persisted for later: shared/agnostic **public announcement page/module** (also covers exam roster publishing)
- [x] A6 Leftovers — **office assigns via ready pool** (no auto-slot)
- [x] ~~Self-booking day one vs admin-led first~~ — RESOLVED 2026-08-14: both from day one, no default; explicit booking_mode pick at create, toggle anytime (design §8)

**Gating note (founder 2026-08-14):** A1–A6 resolved — Phases 5 and 7 (advised milestone, registrar status search + eligibility, release mode + prerequisite fix) are now authorized to build.


## B — Shared reservation primitive (Phase 0)
- [x] B1 Extract `Scheduling\ReservationGuard` (lock session+attempt → cycle match → one-active-assignment → capacity-from-room → idempotent reuse) from `ReserveExamSlot`
- [x] B2 Refactor `ReserveExamSlot` onto B1; exam suites stay green (no behavior change) — proven: ExamSlotReservationFlowTest 10✓, ApplicantChooseSlotSkillTest 8✓, ApplicantSlotsCapabilityTest 11✓


## C — Schema (Phase 1)


- [x] C1 Migrations: `consultation_sessions` + `consultation_assignments` (design §2; GF-19 constraints) — `2026_08_14_100000_create_consultation_scheduling_tables.php`
- [x] C2 One-active-per-attempt partial unique (exam parity — no hard pair unique; cancel+rebook same session keeps history); version columns; enums
- [x] C3 Models + relations (session→assignments, attempt→assignments, room) — `App\Models\Consultation\*` with property docblocks
- [x] D1 `GUIDANCE_CONSULTATION_PLAN` + `REGISTRAR_ENROLLMENT_ELIGIBILITY` in `CapabilityCatalog` (TA set + all() + registrar roleHelperGrants)
- [x] D2 Staff actions: create/update/publish/unpublish, assign (result-ready precheck + capacity), reassign, cancel-assignment, complete/cancel session — `App\Consultation\Actions\*` (11 actions)
- [x] D3 Booking actions: open/close reservation (admin disable toggle), `ReserveConsultationSlot` via `ReservationGuard` + `AttemptResultReadiness`
- [x] D4 Audit events per transition via `RecordAuditEvent` (session_created/updated/published/unpublished/reservation_opened/reservation_closed/assignment_created/assignment_reassigned/assignment_cancelled/slot_reserved/session_completed/session_cancelled)
- [x] C3 Models + relations (session→assignments, attempt→assignments, room) — `App\Models\Consultation\*` with property docblocks

## D — Commands + capabilities (Phase 2)

- [x] D1 `GUIDANCE_CONSULTATION_PLAN` + `REGISTRAR_ENROLLMENT_ELIGIBILITY` in `CapabilityCatalog` + roleHelperGrants
- [x] D2 Staff actions: create/update/publish/unpublish, assign (result-ready precheck + capacity), reassign, cancel-assignment, complete/cancel session — 11 actions in `App\Consultation\Actions\*`
- [x] D3 Booking actions: open/close reservation (admin disable toggle), `ReserveConsultationSlot` via `ReservationGuard` + `AttemptResultReadiness`
- [x] D4 Audit events per transition via `RecordAuditEvent` (12 event types)

## E — Read side (Phase 2)

- [x] E1 `ConsultationSessionListQuery` / `ConsultationSessionDetailQuery` + `ConsultationSessionPresenter`
- [x] E2 `ConsultationReadyPoolQuery` (result-ready, no active assignment)
- [x] E3 `ApplicantConsultationCalendar` (open published sessions + remaining capacity)
- [ ] E4 `EnrollmentEligibilityQuery` (accepted + finalized + released + advised; missing requirements)

## F — Staff UI (Phase 3)

- [x] F1 `ConsultationSessions.svelte` (list; status + booking-mode badges; actions)
- [x] F2 `ConsultationSessionCreate.svelte` — explicit booking-mode pick (founder: both from day one, no default)
- [x] F3 `ConsultationSessionShow.svelte` (roster, ready pool + assign, booking toggle, complete/cancel) + Edit page
- [x] F4 SUPERSEDED by founder frontend refinement 2026-08-14: sidebar keeps Assessment → Consultation (main page); Scheduling CTA added inside `Release/Consultation.svelte` (`can_plan` prop)

## G — Portal (Phase 4)

- [x] G1 `Applicant/Consultation.svelte` (states: denied / not_result_ready / no_open_sessions / booking_open / assigned_or_reserved; full shown per card)
- [x] G2 Reserve POST with idempotency key; full/closed/disabled/unpublished denials proven by tests

## H — Advised milestone + registrar (Phase 5)

- [x] H1 Decision confirmed → **Advised** derived milestone (`AttemptConsultationStatus::isAdvised`: approve/modify/override; attendance alone is not enough — A2)
- [x] H2 `Registrar/EnrollmentEligibility.svelte` (status search → eligibility card + missing requirement) + `EnrollmentEligibilityQuery` + controller + routes + nav item
- [x] H3 Registrar scope: status facts only (no scores, no counselor notes); every card lookup audited (`registrar.eligibility_lookup`); capability-gated

## I — Release metadata (Phase 7)

- [x] I1 `result_releases.mode` (f2f/online) — set at release time (form-validated, `ReleaseResult::MODE_*`); selects release notification template (`result.released.f2f` / `result.released.online`); gate uniform, never a prerequisite
- [x] I2 `ReleaseResult`: consultation-decision prerequisite REMOVED (release = grading finalized + document prepared only) — proven by `ReleaseWithoutDecisionTest`

## J — Seeds + proof (Phase 6)


- [x] J1 `ConsultationDemoSeeder`: CONS-DEMO-1 (staff_only, published) + CONS-DEMO-2 (open_for_reservation, published, booking open); both demo applicants result-ready; HANDOFF.md with creds/URLs/reset
- [x] J2 Pest suites: `ConsultationSessionFlowTest` — 17 tests / 185 assertions green; exam regression 29 tests / 314 assertions green post-guard-extraction
- [x] J3 Browser-verified (headless Chrome): staff login → Consultation main page CTA → sessions list → show page (roster/ready pool/toggle) → applicant portal assigned_or_reserved. Playwright scripts deferred (no existing Playwright harness in system/)
- [x] J4 Targeted suites green; Pint + PHPStan clean on all consultation code (pre-existing Examination query errors untouched)
