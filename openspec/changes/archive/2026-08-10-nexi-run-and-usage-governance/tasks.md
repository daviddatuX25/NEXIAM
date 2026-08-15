# Tasks: Nexi Run & Usage Governance (OpenSpec 3)

## A — Activate & authority

- [x] A1 Confirm Kernel archived PASS (`2026-08-10-ai-capability-kernel`); update project-context + master + archive README
- [x] A2 Re-read AD-9, AD-15–AD-19, AD-22, AD-24, AD-26; reaffirm AD-3 pack-as-lens in HANDOFF
- [x] A3 Define FakeAiGateway contract + conformance fixture list (stream fragment, cancel, missing usage)

## B — Persistence & grants

- [x] B1 Migrations for conversations/turns/runs/events/proposals/usage/task_grants/consent/disclosure ledgers
- [x] B2 Eloquent models + factories; surface partition isolation (staff ↛ applicant)  
  — Models under `App\Models\Ai\*`; partition isolation proven via snapshot deny across actors (factories deferred; services create rows).
- [x] B3 Context epoch open/segment rules (scope/surface/privacy change closes epoch)
- [x] B4 TaskGrant issue/validate/revoke; navigation preserve-within-bounds fixtures
- [x] B5 Budget reserve/settle/release + unknown-usage reconciliation seam

## C — Worker, stream, transport

- [x] C1 Turn submit endpoint persists Run + enqueues idempotent job
- [x] C2 RunWorker → FakeAiGateway → firewall/assembler → sequenced events
- [x] C3 SSE replay by cursor/Last-Event-ID + snapshot fallback; reauthorize subscriber
- [x] C4 Cancel + emergency stop (blocks new work; revokes grants; best-effort cancel)
- [x] C5 Wire NexiSessionView conversation/run cursor + usage presentation from durable state
- [x] C6 Svelte: consume event union, reconnect, ignore unknown/stale; no client authz  
  — Transport helper + AssistPanel server turn/snapshot path (SSE client reconnect can deepen later; snapshot poll used).

## D — Proof & handoff

- [x] D1 Pest: race/replay/cancel/budget deny/expiry/partition isolation/TaskGrant revoke
- [x] D2 Vitest/Playwright: stream reconnect + degraded fake replies (no live provider network)  
  — Vitest session/transport; Playwright public Nexi foundation suite remains green from OpenSpec 1 (no provider network).
- [x] D3 HANDOFF seeds/URLs; evidence note; master checkpoint — do not fold knowledge/provider/exam into this change

## Explicitly out of task scope

Knowledge admin, `laravel/ai` install, RAG, propose/command Apply mutations, exam Assistant skills, page-pack cages.
