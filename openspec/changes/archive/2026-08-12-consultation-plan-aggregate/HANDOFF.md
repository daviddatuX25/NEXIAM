# HANDOFF — Consultation Sessions + Release/Consultation Split + Enrollment Eligibility

**Change:** `openspec/changes/2026-08-12-consultation-plan-aggregate/`
**Status:** ALL PHASES COMPLETE 2026-08-14 (0–7): guard extraction, schema, actions, staff UI, portal, Advised milestone, registrar eligibility, release mode + prerequisite removal. Client decisions A1–A6 all resolved by founder.
**Active OpenSpec:** this change. `omr-scoring-capture` paused (files untouched).

## Phases 5/7 additions (2026-08-14)

- **Advised** = confirmed decision (approve/modify/override) — `App\Consultation\Support\AttemptConsultationStatus::isAdvised`. Attendance alone does NOT mark advised.
- **Registrar status search** (A3): `/registrar/enrollment-eligibility` — search by name/reference/email → eligibility card (accepted / exam graded / result released / advised; eligible or what's missing). Status facts only — no scores, no counselor notes. Every card lookup audited (`registrar.eligibility_lookup`). Registrar capability: `registrar.enrollment.eligibility`.
- **Release mode** (I1): `result_releases.mode` f2f|online, set at release; selects notification template `result.released.f2f` vs `result.released.online`. Default f2f.
- **Release no longer needs a consultation decision** (I2): prerequisite removed — release = grading finalized + document prepared. Existing decision history preserved.

## Logins

| Actor | Credentials | Portal |
|---|---|---|
| Guidance Counselor (full TA set incl. consultation PLAN) | `guidance.counselor@nexiam.test` / `password` | http://localhost:18000 |
| Registrar Clerk (denied on consultation sessions) | `registrar.clerk@nexiam.test` / `password` | http://localhost:18000 |
| Demo applicant A (result-ready, assigned CONS-DEMO-1) | access key `DEMO-ACPT`, birthdate `2003-09-25` | /applicant/login |
| Demo applicant B (result-ready, reserves CONS-DEMO-2) | seeded by ConsultationDemoSeeder; access key shown once at submission | /applicant/login |

## Reset + seed

```bash
cd system
docker compose exec -T app php artisan foundation:reset --force
docker compose exec -T app php artisan db:seed --class=Database\\Seeders\\ConsultationDemoSeeder
```

## Expected starting state

- `CONS-DEMO-1` — staff_only, **published**, applicant A assigned (source `staff_assigned`).
- `CONS-DEMO-2` — open_for_reservation, **published**, booking open; applicant B assigned; other result-ready applicants can reserve.
- Both demo applicants are result-ready (grading finalized + result document prepared), so the ready pool and portal reserve work without extra steps.
- Registrar search page for enrollment eligibility does **not** exist yet (Phase 5, A3-gated).

## URLs

| Surface | URL |
|---|---|
| Consultation main page (decision recording) + Scheduling CTA | `/consultation` |
| Session list | `/consultation/sessions` |
| Create session | `/consultation/sessions/create` |
| Session detail (roster, ready pool, assign, booking toggle, complete/cancel) | `/consultation/sessions/{session}` |
| Applicant portal consultation page | `/applicant/consultation` |

## Verification

```bash
docker compose exec -T app php artisan test tests/Feature/Consultation/ConsultationSessionFlowTest.php
# 17 tests / 185 assertions
docker compose exec -T app php artisan test tests/Feature/Examination/ExamSlotReservationFlowTest.php tests/Feature/Ai/ApplicantChooseSlotSkillTest.php tests/Feature/Capabilities/ApplicantSlotsCapabilityTest.php
# 29 tests / 314 assertions — exam reservation behavior unchanged after guard extraction
```

## Key design points (founder-locked)

- Booking rollout: **both from day one, no default** — create form requires an explicit `booking_mode` pick; DB default `staff_only` is non-form safety only.
- `result_releases.mode` (f2f/online) = per-row enum + selects the release notification template (I1, Phase 7).
- `ReleaseResult` still enforces a consultation-decision prerequisite — **remove in I2** (Phase 7) per founder refinement.
- One-active-assignment per attempt is a partial unique index (exam parity); no hard (session, attempt) pair unique so cancel+rebook keeps history.
