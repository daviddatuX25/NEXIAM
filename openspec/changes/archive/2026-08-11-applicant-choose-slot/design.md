# Design: Applicant Slot Skill

## Problem

Accepted applicants can already reserve exam slots manually (`/applicant/schedule` calendar). But Nexi — the product's assistant — cannot yet help them choose a slot. The exam Assistant proved the skill pattern for staff; this change applies the same pattern to the applicant side without inventing new authorization or a parallel data path.

## Shape

```
applicant /applicant/schedule
  └─ Nexi island (surface Applicant, pack applicant.schedule@1)
       └─ skill applicant.choose_slot@1
            └─ kernel capabilities (OWN scope)
                 ├─ exams.slots.list@1   (Read    — calendar projection)
                 └─ exams.slot.reserve@1 (Command — ReserveExamSlot)
```

## Kernel rows (thin registry, one core)

**`exams.slots.list@1`** — Read. Inputs: none (or `day` filter). Output mirrors the `calendarDays` projection already built in `ApplicantScheduleController::show`:
- open-for-reservation sessions for the actor's accepted attempt (own academic cycle)
- per slot: id, code, date, start/end time, room, capacity, remaining, full
- authorization: `ApplicantScope::identityIdForUser` + attempt ownership; page state `calendar_open` only; otherwise empty/denied result (not a 403 — a shaped "no slots" result the skill can surface)

**`exams.slot.reserve@1`** — Command. Inputs: `exam_session_id`, `admission_attempt_id`, optional `idempotency_key`. Delegates to `ReserveExamSlot` (existing action; ownership, active assignment, `open_for_reservation`, status guards already enforced). Confirmed only — a proposal is never a write.

Handlers live beside existing exam handlers (`app/Application/Capabilities/Handlers/Exams/`), reuse the existing queries/action, channels `['nexi_safe']`.

## Skill engine + cursor

`app/Ai/Skills/ApplicantChooseSlotSkill.php`:

- `KEY = 'applicant.choose_slot'`, `VERSION = 1`
- `allowedCapabilityIds()`: `['exams.slots.list@1', 'exams.slot.reserve@1']`
- Cursor transitions:
  - `started` → `['exams.slots.list@1']`
  - `slots_loaded` → `['exams.slots.list@1', 'exams.slot.reserve@1']` (propose only)
  - `awaiting_confirm` → `[]` (HTTP confirm endpoint only)
  - `done` → `['exams.slots.list@1']`
- `guidanceDigest()`: read slots; propose one slot; wait for confirm; never invent session/attempt ids; prefer page buttons if degraded.

`ApplicantChooseSlotTurnService` mirrors `ExamSessionAssistantTurnService`: find active `applicant.choose_slot@1` TaskGrant → plan → `SkillEngine::assertToolAllowed` → `SkillToolInvoker` → append run events; proposal persists to `nexi_proposals`; confirm maps to `exams.slot.reserve@1` → `ReserveExamSlot`.

## Page pack

Replace/augment `applicant.portal.home@1` with **`applicant.schedule@1`**:

- `pageIdentity: 'applicant.schedule'`, surface Applicant
- `allowedModes: ['inquiry', 'assistant']`, `defaultMode: 'assistant'`
- `skills: ['applicant.choose_slot@1']`
- `uiIntents`: navigate `applicant.schedule`; `skill_start` `applicant.choose_slot@1`

Assistant mode is only meaningful when the page state is `calendar_open` (or `assigned_published` for read-only "your seat" answers); the surface degrades gracefully otherwise (pack-as-lens AD-3).

## Authz / scope

- **No new office capability.** Applicant skill rows are OWN: identity derived from the authenticated user; attempt must belong to that user; slot must be open for reservation in the user's cycle.
- `CapabilityAuthorizer`/Spatie untouched for this cut (applicant own-data scope is resolved by `ApplicantScope` + `ReserveExamSlot` guards).
- Confirm endpoint reuses the existing `applicant.schedule.reserve` route guards (or a Nexi confirm route that calls the same action with the same checks).

## Files

```text
app/Ai/Skills/ApplicantChooseSlotSkill.php        skill recipe
app/Ai/Skills/ApplicantChooseSlotTurnService.php  turn planner (mirror ExamSessionAssistantTurnService)
app/Application/Capabilities/CapabilityDefinitions.php  + exams.slots.list@1, exams.slot.reserve@1
app/Application/Capabilities/Handlers/Exams/ExamSlotsListHandler.php
app/Application/Capabilities/Handlers/Exams/ExamSlotReserveHandler.php
app/Ai/Context/PageCapabilityPackRegistry.php     + applicant.schedule@1 pack
resources/js/Pages/Applicant/Schedule.svelte      Nexi island (chat → proposal → confirm → toast)
routes/web.php                                    + Nexi skill start/invoke for applicant pack (if not generic)
tests/Feature/Ai/ApplicantChooseSlotSkillTest.php Pest proof
```

## Proof plan

- Skill start on `applicant.schedule` issues `applicant.choose_slot@1` TaskGrant; wrong surface denies.
- `exams.slots.list@1` returns only open-for-reservation slots for the actor's own accepted attempt; not-owned attempt → denied; closed reservation → no slots.
- `exams.slot.reserve@1` (propose) never writes; confirm → `ReserveExamSlot` idempotent (same key twice = same assignment).
- Cursor denies reserve before slots_loaded; deny after awaiting_confirm except confirm.
- Nexi disabled/degraded → page buttons still reserve (manual path complete).
