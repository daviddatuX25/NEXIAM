# Spec: nexi-exam-session-assistant

## REQ-EXAM-AI-01 — Shared principal
Nexi exam tools SHALL authorize the authenticated session user only. Provider or chat-supplied actor/session/institution identifiers SHALL NOT alter the trusted principal.

## REQ-EXAM-AI-02 — Capability matrix mirrors core
Exam mutate capabilities SHALL invoke the same `Examination\Actions\*` used by `/exams/sessions` controllers. AI SHALL NOT persist exam domain state outside those Actions.

## REQ-EXAM-AI-03 — Skill allowlist + engine
`schedule.exam_session@1` SHALL expose only its declared capability versions. The skill engine SHALL reject off-allowlist or cursor-illegal tool recommendations before Kernel dispatch.

## REQ-EXAM-AI-04 — Guidance is non-authoritative
Optional skill guidance text MAY be included in prompts for tool choice. Guidance SHALL NOT grant capabilities, bypass Kernel, or replace domain preflight.

## REQ-EXAM-AI-05 — Proposal then confirm
Mutation SHALL require a persisted proposal and human confirm with proposal id + idempotency key. Confirm SHALL re-run preflight and call the Command→Action path.

## REQ-EXAM-AI-06 — Manual parity
Every Assistant Apply in this cut SHALL remain available as an ordinary page control when Nexi is degraded or stopped.

## REQ-EXAM-AI-07 — Out of scope actors
Applicant self-booking skills SHALL NOT ship in this change.
