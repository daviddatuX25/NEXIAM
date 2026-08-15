# Tasks: Applicant Slot Skill

## A — Open

- [x] A1 Archive `ta-capability-delegation` PASS; open this OpenSpec; update pointers (archive README, master reference, project-context)
- [x] A2 Verify manual reservation core (`ApplicantScheduleController` + `ReserveExamSlot` + `Schedule.svelte`) still green before overlay work — `ExamSlotReservationFlowTest` 10 passed (130 assertions)

## B — Kernel rows (one core)

- [x] B1 `exams.slots.list@1` Read row + `ExamSlotsListHandler` (reuse `ApplicantScheduleController::show` calendar projection, OWN scope) — extracted `Examination\Queries\ApplicantSlotCalendar` shared by page + kernel; new `applicant.exam.slots.view` capability granted at applicant login (`ApplicantCapabilityGrants`); `ApplicantSlotsCapabilityTest` 8 passed + reservation regression green
- [x] B2 `exams.slot.reserve@1` Propose row + `ExamSlotReserveHandler` (delegates to `ReserveExamSlot`, OWN-scope check, propose-never-writes, idempotency passthrough) — `ApplicantSlotsCapabilityTest` + `ExamSlotReservationFlowTest` 21 passed (219 assertions)

## C — Skill + pack

- [x] C1 `ApplicantChooseSlotSkill` (KEY/VERSION/allowlist/cursor/guidance) + `ApplicantChooseSlotTurnService` (mirror `ExamSessionAssistantTurnService`)
- [x] C2 Page pack `applicant.schedule@1` (assistant mode, skill listed) replaces `applicant.portal.home@1` on the schedule page
- [x] C3 Confirm path maps proposal → `exams.slot.reserve@1` → `ReserveExamSlot` (no write on propose)

## D — UI

- [ ] D1 Nexi island on `Applicant/Schedule.svelte`: chat → structured slot proposal → human confirm → toast (legacy `ScheduleAssistantPanel` UX basis; server-gated)
- [ ] D2 Graceful degradation: manual calendar buttons still reserve when Nexi disabled

## E — Proof

- [x] E1 Pest `ApplicantChooseSlotSkillTest`: skill start/invoke, OWN-scope slots list, propose-not-write, confirm idempotency, cursor denials, not-owned attempt denied, degraded manual path
- [x] E2 HANDOFF + evidence note (demo logins, click path, recreate)
