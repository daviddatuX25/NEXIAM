# Spec: Nexi Applicant Slot Skill

## Purpose

An accepted applicant can use Nexi on `/applicant/schedule` to pick an exam slot and confirm a reservation. The skill reads the same open-for-reservation calendar and writes through the same `ReserveExamSlot` action as the manual page. Own-data scope only — no office capability is granted.

## Requirements

### R1 — Skill start

- **WHEN** an authenticated applicant opens `/applicant/schedule` with page state `calendar_open` (or `assigned_published` for read-only seat answers) and starts the Assistant
- **THEN** Nexi issues a `applicant.choose_slot@1` TaskGrant bound to that run
- **AND** `started` cursor allows only `exams.slots.list@1`

### R2 — Slots read (OWN scope)

- **WHEN** the skill invokes `exams.slots.list@1`
- **THEN** it returns open-for-reservation slots for the actor's own accepted attempt in the actor's academic cycle
- **AND** each slot carries `id`, `code`, `date`, `start_time`, `end_time`, `room`, `capacity`, `remaining`, `full`
- **AND** an attempt not owned by the actor, a closed reservation, or an ineligible attempt yields a shaped "no slots / not eligible" result (never another user's data)

### R3 — Propose, not write

- **WHEN** the skill proposes a reservation (`exams.slot.reserve@1` in `slots_loaded` cursor)
- **THEN** no domain write occurs; the proposal is persisted as a `nexi_proposals` record awaiting human confirm
- **AND** `awaiting_confirm` cursor allows no further tool invocations

### R4 — Confirm → one core

- **WHEN** the applicant confirms a proposal
- **THEN** the server maps it to `exams.slot.reserve@1` → `ReserveExamSlot` with the same ownership, `open_for_reservation`, active-assignment, and status guards as the manual route
- **AND** `idempotency_key` is honored: the same key twice yields the same assignment without duplication

### R5 — Degradation

- **WHEN** Nexi is disabled or degraded
- **THEN** the manual calendar buttons on `/applicant/schedule` still reserve a slot end-to-end

### R6 — Audit

- Skill start, plan, invocation, rejection, and confirm are recorded as Nexi run events (same as staff skills); reservation confirm also records the existing exam audit event.

## Acceptance

- [ ] Pest: skill start grants TaskGrant; wrong surface denies
- [ ] Pest: `exams.slots.list@1` returns only own open slots; not-owned attempt denied
- [ ] Pest: propose writes no assignment; confirm reserves idempotently
- [ ] Pest: cursor denies reserve before `slots_loaded`; denies tool calls in `awaiting_confirm`
- [ ] Pest: degraded Nexi → manual reservation still works
