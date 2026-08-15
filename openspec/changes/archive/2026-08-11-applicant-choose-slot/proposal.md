# Change Proposal: Applicant Slot Skill

**Change ID:** `applicant-choose-slot`  
**Status:** ACTIVE  
**Phase:** E3 Nexi applicant skill (Slice 9)  
**Depends on:** `nexi-exam-session-assistant` PASS (skill engine pattern), `exam-session-scheduling` PASS (`booking_mode` + `ReserveExamSlot`), `ta-capability-delegation` PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-ta-capability-delegation/`

## Intent

First **applicant-facing Nexi skill**: `applicant.choose_slot@1` helps an accepted applicant pick an exam slot and confirm a reservation. It runs the **same** calendar projection and `ReserveExamSlot` action as the manual `/applicant/schedule` page — no AI-only persistence, no new office capability. Human confirm → Apply, exactly like the staff exam Assistant (AD-18).

Manual reservation core is already shipped in `system/` (`ApplicantScheduleController::show/reserve`, `ReserveExamSlot`, `Applicant/Schedule.svelte` calendar). This change adds the Nexi overlay only.

## Founder model lock (2026-08-10) — non-negotiable

1. **One auth** — Nexi and Inertia share the logged-in server principal (cookie/session). Prompt/provider text cannot change actor/session/institution.
2. **One core** — Controllers and Nexi unite on Examination Actions/Queries. No AI-only persistence layer.
3. **Capability matrix** — thin Kernel registry rows mirror core Actions/Queries. Applicant skill rows are **OWN-scope**: the applicant is the resource owner (their own accepted attempt); no office/HR capability is granted or implied.
4. **Skill** — not “MD as authority.” Skill = allowlist of capability versions + server engine/cursor. Multi-step = engine advances after each Kernel success; model only recommends among allowed tools.
5. **Projections** — after auth, channel shaping (`http_page` / `nexi_safe`). Not a second business-rule engine.
6. **Persistence** — Reads: no domain write (run/disclosure/usage still durable). Propose: persist `nexi_proposals`. Confirm→Command: domain write via ordinary Action (`ReserveExamSlot`).

## Pilot skill

`applicant.choose_slot@1` on pack `applicant.schedule@1` (page `applicant.schedule`, surface Applicant).

**Allowlisted capability pair (propose → confirm):**

| Capability | Kind | Core seam |
|---|---|---|
| `exams.slots.list@1` | Read | same calendar projection as `ApplicantScheduleController::show` (open-for-reservation sessions, remaining capacity, full state) |
| `exams.slot.reserve@1` | Command | `ReserveExamSlot` action (idempotent; ownership + booking_mode + status guards already inside) |

## UX basis (legacy)

Legacy `ScheduleAssistantPanel.svelte` (537 lines) is the full-featured ancestor: chat → structured schedule proposal → human confirm → toast + localStorage history. Greenfield keeps the calendar page and adds the Nexi island on `Applicant/Schedule` with the same chat → proposal → confirm → toast rhythm, but server-gated by the SkillEngine allowlist (not client-side).

## Out of scope

- SlotPlan aggregates, waitlists, auto-allocation (removed by exam-session-scheduling PASS).
- Applicant editing own profile/password (profile-page follow-up cut).
- Cross-office or staff skills — one pack at a time.
