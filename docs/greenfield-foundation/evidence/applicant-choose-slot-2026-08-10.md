# Evidence — Applicant Slot Skill (B1–E1)

**Date:** 2026-08-11  
**OpenSpec:** `openspec/changes/applicant-choose-slot/`  
**Authority:** Slice 9 plan; founder locks (one core, capability matrix, OWN scope, skill allowlist)

## What shipped

### B1 — `exams.slots.list@1` (Read row)

- **`applicant.exam.slots.view` / `applicant.exam.slot.reserve`** — new applicant-owned capability keys in `CapabilityCatalog::all()` (NOT role-granted).
- **`Admission\Support\ApplicantCapabilityGrants::sync(User)`** — grants applicant capabilities directly to the portal user; wired into `AuthenticateApplicantWithAccessKey` (login), so seeded + new applicants get them idempotently.
- **`Examination\Queries\ApplicantSlotCalendar`** — the ONE slot-calendar projection (`openSlots` / `calendarDays` / `flatSlots`). `ApplicantScheduleController::show` refactored to use it (identical payload; reservation flow regression green).
- **`ExamSlotsListHandler`** — OWN scope: identity → own accepted attempt → eligible → open slots (or `assigned` seat, or shaped `denied` reasons). Never another user's data.
- Kernel row in `CapabilityDefinitions` (`Read`, gate `applicant.exam.slots.view`, channels `['nexi_safe']`) + `exams.slots.catalog@1` digest in `ActiveRulePublication`.

### B2 — `exams.slot.reserve@1` (Propose row)

- **`ExamSlotReserveHandler`** — kind **Propose**: propose returns impact/evidence, never writes; confirm (`execute: true`) → `ReserveExamSlot` with OWN-scope check + idempotency passthrough.

### C1 — Skill recipe + turn planner

- **`Ai\Skills\ApplicantChooseSlotSkill`** — KEY `applicant.choose_slot`, VERSION 1, allowlist (`exams.slots.list@1`, `exams.slot.reserve@1`), cursors `started → slots_loaded → awaiting_confirm → done`, code-owned guidance digest.
- **`Ai\Skills\ApplicantChooseSlotTurnService`** — mirrors `ExamSessionAssistantTurnService`: find active grant → plan (list / reserve / seat-status intents; reserve requires slot id + attempt id) → `SkillEngine::assertToolAllowed` → `SkillToolInvoker` → run events → proposal → confirm.
- **`SkillEngine`** — added `startApplicantSlotSkill` (applicant actor, `surface_key='applicant'`, attempt id in scope bounds); `assertToolAllowed` / `advanceCursor` now dispatch on the grant's skill key (exam vs applicant recipes).
- **`SkillToolInvoker`** — kernel surface now taken from the grant (`$grant->surface_key`); applicant proposals capture `_command_input` = `exam_session_id` / `admission_attempt_id` / `idempotency_key`; `exams.slots.list@1` advances cursor to `slots_loaded`.
- **`ProposalService`** — proposal summary persists `surface_key`; confirm re-invokes with the originating surface and the applicant command keys, so `exams.slot.reserve@1` executes through the same OWN-scope path as the page button.
- **`ProcessNexiRun`** — assistant turns route by conversation `actor_type`: applicant → slot planner; staff → exam planner.

### C2 — Pack `applicant.schedule@1`

- Replaces `applicant.portal.home@1` on `applicant.schedule`: `allowedModes ['inquiry','assistant']`, `defaultMode 'assistant'`, capabilities = the two skill rows, `skills: ['applicant.choose_slot@1']`, `skill_start` UI intent.
- `NexiSessionViewBuilder` surfaces `assistant_available` for any pack with assistant mode + listed skills (was hardcoded to `staff.exams.sessions.*`).
- `NexiTurnController` now honors the page pack's `allowedModes` (mirrors the view builder) so assistant turns are accepted on the applicant surface.

### D1/D2 — Nexi island + degradation

- `ApplicantLayout` already mounts `NexiDock` → `NexiAssistPanel` on any page with a `nexi.session`; the new pack makes the schedule page mount the island in assistant mode with the skill-start intent and confirm gate — no new client chrome required (server-gated).
- Manual calendar reserve stays independent of Nexi (`nexi.enabled=false` → page + reserve still work).

## Verification

```text
tests/Feature/Ai/ApplicantChooseSlotSkillTest.php            → 8 passed (93 assertions)
tests/Feature/Capabilities/ApplicantSlotsCapabilityTest.php  → 11 passed (91 assertions)
tests/Feature/Examination/ExamSlotReservationFlowTest.php    → 10 passed (130 assertions)
tests/Feature/Ai/NexiExamSessionAssistantTest.php            → 7 passed (exam skill regression)
tests/Feature/Ai/NexiFoundationSessionTest.php               → 8 passed (pack registry/session regression; one stale fixture route updated)
tests/Feature/Ai/NexiApplicantInquiryTest.php                → 9 passed (inquiry regression)
tests/Feature/Capabilities/CapabilityKernelTest.php          → 2 passed
```

Coverage (E1): skill start issues grant + allowlist/guidance; staff start denied; OWN-scope slots list + cursor advance; propose writes nothing; confirm reserves once + repeat confirm rejected (no duplicate); cursor gates block invokes while awaiting confirm; not-own-attempt denied via skill invoke; assistant turn end-to-end (plan → tool → result → proposal → confirm → assignment); degraded manual path with Nexi disabled.

## Model

- Applicant skill rows authorize via real GF-21 applicant-owned capabilities (kernel ctor requires non-empty `applicationCapabilities`), not a bypass.
- One projection (page + skill) — no AI-only query. Propose never writes; confirm is the same `ReserveExamSlot` Action as the page button.
- SkillEngine allowlist + cursor are the authority; the deterministic planner is a fake-provider stand-in that must still pass both.
