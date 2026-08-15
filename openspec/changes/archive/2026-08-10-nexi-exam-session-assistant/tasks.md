# Tasks: Nexi Exam Session Assistant (OpenSpec 8)

## A — Activate + model lock

- [x] A1 Open OpenSpec; archive pointers from OS7 PASS
- [x] A2 Design vocabulary: one auth, capability matrix, skill=allowlist+engine, projections, persistence

## B — Packs + skill shell

- [x] B1 Page packs `staff.exams.sessions.index@1` / `show@1` with inquiry+assistant
- [x] B2 Staff SurfaceProfile allows assistant; session view exposes skill start
- [x] B3 `schedule.exam_session@1` SkillDefinition + SkillEngine + TaskGrant issue on start

## C — Capability matrix (mirror core)

- [x] C1 Reads: `exams.sessions.list@1`, `exams.session.show@1`
- [x] C2 Commands: reservation.open, assign, publish → existing Actions
- [x] C3 Propose handlers emit ConstraintEvidence + persist proposals
- [x] C4 ActiveRulePublication digests for exam bindings

## D — Confirm + UI

- [x] D1 `POST /nexi/proposals/{id}/confirm` (+ cancel/invalidate)
- [x] D2 AssistPanel: real confirm/cancel against proposal API (no stub Apply)
- [x] D3 Multi-step visible run events (tool/proposal/result)

## E — Proof

- [x] E1 Pest: grant allowlist rejects off-skill cap; confirm open/assign/publish; stale version invalidates; cross-user deny
- [x] E2 HANDOFF seeds + evidence; manual page parity note
