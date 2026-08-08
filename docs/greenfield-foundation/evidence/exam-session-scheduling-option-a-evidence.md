# Evidence — Exam session scheduling Option A

**Date:** 2026-08-08  
**OpenSpec:** `openspec/changes/exam-session-scheduling/`  
**Authority:** D-088 (amended Option A)

## What shipped

- Schema: `exam_sessions.booking_mode` (`staff_only` | `open_for_reservation`), reservation timestamps; assignment `source` includes `applicant_reserved`; dropped `exam_slot_plans` / `exam_slot_reservations` / `exam_slot_plan_id`.
- Actions: `OpenExamSessionReservation`, `CloseExamSessionReservation`, `ReserveExamSlot` → assignment; SlotPlan module removed.
- Staff UI: session Index booking badge; Show Open/Close reservation; Slot Plans nav/pages removed.
- Applicant UI: calendar from open sessions; no soft-hold `reserved` state.
- Pest: schema + reservation flow rewritten for Option A.

## Runtime

- Code baked into Compose image → rebuild `app` (+ `queue`) after PHP/JS changes.
- DB: `php artisan foundation:reset --force` (or migrate:fresh --seed) then JourneyFixtureSeeder demos.

## Demo login

| Role | Key | Birthdate |
|---|---|---|
| Accepted | `DEMO-ACPT` | `2003-09-25` |
| Pending | `DEMO-PEND` | `2003-09-25` |
| Guidance | `guidance.counselor@nexiam.test` / `password` |

## Verification (2026-08-08)

- `php artisan test --compact tests/Feature/Examination` → **14 passed** (180 assertions)
- Live DB after `foundation:reset --force`: `booking_mode` present; `exam_slot_plans` absent
- Compose: `docker compose build app && docker compose up -d --force-recreate app queue`

## Developer-ready seeds

See active OpenSpec [`HANDOFF.md`](../../../openspec/changes/exam-session-scheduling/HANDOFF.md) § Developer-ready seeded data (project-context §3.7).
