# Change Proposal: Nexi Exam Session Assistant

**Change ID:** `nexi-exam-session-assistant`  
**Status:** ACTIVE  
**Phase:** E3 OpenSpec 8  
**Depends on:** `nexi-applicant-inquiry` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-nexi-applicant-inquiry/`

## Intent

First **staff Assistant** that can run a **multi-step exam scheduling skill**: server-owned allowlist + engine over Capability Kernel entries that call the **same** `Examination\Actions\*` as `/exams/sessions/*`. Human confirm → Apply. Manual page path remains complete (AD-18).

## Founder model lock (2026-08-10) — non-negotiable

1. **One auth** — Nexi and Inertia share the logged-in server principal (cookie/session). Prompt/provider text cannot change actor/session/institution.
2. **One core** — Controllers and Nexi unite on Examination Actions/Queries. No AI-only persistence layer.
3. **Capability matrix** — thin Kernel registry rows mirror core Actions/Queries (FormRequest-class wall). Scalable: new domain work = Action + registry row, not duplicate endpoints.
4. **Skill** — not “MD as authority.” Skill = **allowlist of capability versions + server engine/cursor (+ optional guidance digest for the model)**. Multi-step = engine advances after each Kernel success; model only recommends among allowed tools.
5. **Projections** — after auth, channel shaping (`http_page` / `nexi_safe` / provider). Not a second business-rule engine.
6. **Persistence** — Reads: no domain write (run/disclosure/usage still durable). Propose: persist `nexi_proposals`. Confirm→Command: domain write via ordinary Action.

## Pilot skill

`schedule.exam_session@1` on packs `staff.exams.sessions.index@1` / `staff.exams.sessions.show@1`.

**Allowlisted command trio (confirm → Action):**

| Capability | Core Action |
|---|---|
| `exams.session.reservation.open` | `OpenExamSessionReservation` |
| `exams.session.assign` | `AssignApplicantToExamSession` |
| `exams.session.publish` | `PublishExamSession` |

Plus reads: `exams.sessions.list`, `exams.session.show`. Propose variants emit exact impact + `ConstraintEvidence` before confirm.

## Explicit non-goals

- Applicant NL booking (“assign me next week”) — later `applicant.choose_slot` → `ReserveExamSlot`
- Grading / release / proctor write proposals
- Skill implemented as freeform markdown with no allowlist/engine
- Capability rows that re-implement domain rules in prompts

## Exit

Guidance on `/exams/sessions/{id}` can start the skill, multi-step read→propose in Nexi, confirm Open / Assign / Publish with the same outcomes as the page buttons; stale version invalidates; Pest proves authz + idempotent confirm + manual parity.
