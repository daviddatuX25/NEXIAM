# Handoff: Applicant Slot Skill

**Date:** 2026-08-11  
**Active OpenSpec:** `openspec/changes/applicant-choose-slot/`  
**Checkpoint:** B1–E1 SHIPPED — kernel rows, skill, pack, confirm path, proof green. Remaining: none (D1/D2 delivered via shared dock; E1 complete).

## Scope

Nexi applicant slot skill `applicant.choose_slot@1` over the existing manual reservation core (`/applicant/schedule` + `ReserveExamSlot`). OWN data scope; no office capability; propose → human confirm → `ReserveExamSlot`.

## Manual core (reused, do not rebuild)

- `ApplicantScheduleController::show` (calendar days, remaining, full) + `reserve` (idempotent)
- `ReserveExamSlot` action
- `Applicant/Schedule.svelte` + `SchedulePipeline.svelte`
- Shared projection: `Examination\Queries\ApplicantSlotCalendar` (page + kernel — one core)

## What shipped (B1–E1)

- Kernel rows `exams.slots.list@1` (Read) + `exams.slot.reserve@1` (Propose) with handlers; applicant-owned capabilities granted at login (`ApplicantCapabilityGrants`).
- Skill `applicant.choose_slot@1`: `ApplicantChooseSlotSkill` (allowlist/cursor/guidance) + `ApplicantChooseSlotTurnService`; `SkillEngine`/`SkillToolInvoker`/`ProposalService`/`ProcessNexiRun` generalized for applicant surface.
- Pack `applicant.schedule@1` replaces `applicant.portal.home@1` on the schedule page: assistant mode + skill listed + `skill_start` intent; the shared `NexiDock`/`NexiAssistPanel` now mount the island there (no new chrome).
- Confirm path: proposal → `exams.slot.reserve@1` execute → `ReserveExamSlot` (same Action as page button; propose never writes; idempotency key passthrough).

## Demo logins (existing seeds)

| Actor | Login | Notes |
|---|---|---|
| Applicant (accepted, no assignment) | per `JourneyFixtureSeeder` applicant access key + birthdate | sees calendar; can reserve |
| Guidance Counselor | `guidance.counselor@nexiam.test` / `password` | opens a session for reservation so applicants can reserve |

## Click path

1. Guidance logs in → Exam sessions → create session → **Open reservation** (applicants can now book it).
2. Applicant → `/applicant/schedule` → calendar shows the open session → Nexi island (assistant mode) → **Start slot choice skill**.
3. Ask Nexi: “list my slots” → slots shown; then “reserve slot `<id>` for attempt `<id>`” → structured proposal → **Confirm** on the gate → reserved (toast), seat card appears.
4. Nexi disabled (`nexi.enabled=false`) → calendar buttons still reserve (D2).
5. Same idempotency key twice → same assignment (no duplicate).

## Recreate

```bash
cd system
docker compose build app && docker compose up -d --force-recreate app queue
docker compose exec app php artisan foundation:reset --force
```

## Proof

```bash
docker compose exec app php artisan test --compact tests/Feature/Ai/ApplicantChooseSlotSkillTest.php
docker compose exec app php artisan test --compact tests/Feature/Capabilities/ApplicantSlotsCapabilityTest.php \
  tests/Feature/Examination/ExamSlotReservationFlowTest.php tests/Feature/Ai/NexiExamSessionAssistantTest.php \
  tests/Feature/Ai/NexiFoundationSessionTest.php tests/Feature/Ai/NexiApplicantInquiryTest.php \
  tests/Feature/Capabilities/CapabilityKernelTest.php
```

Evidence: `docs/greenfield-foundation/evidence/applicant-choose-slot-2026-08-10.md`
