# Evidence: Exam session scheduling reservation cut

**Date:** 2026-08-08  
**OpenSpec:** `openspec/changes/exam-session-scheduling/`  
**Result:** PASS (B1–D2)

## What was verified

- Compose rebuild: `docker compose build app && docker compose up -d --force-recreate app queue`
- Pest: `php artisan test --compact tests/Feature/Examination/` → **15 passed** (213 assertions)
- Includes `ExamSlotReservationFlowTest`: unpublish/cancel, reserve→finalize, capacity deny, plan-closed deny, bypass staff path, capability deny, pending_acceptance, nav hrefs
- Vite manifest includes `Applicant/Schedule` and `Examination/SlotPlans/{Index,Create,Show}`

## Surfaces

| Surface | URL |
|---|---|
| Staff sessions | `/exams/sessions` |
| Staff slot plans | `/exams/slot-plans` |
| Applicant calendar | `/applicant/schedule` |

Staff seed: `guidance.counselor@nexiam.test` / `password`  
App: `http://127.0.0.1:18000`

## Authority

D-088 + OpenSpec design; GF-21 `guidance.exam.schedule` for staff mutate; applicant reserve via ownership + open plan.
