# Change Proposal: Nexi Run & Usage Governance

**Change ID:** `nexi-run-and-usage-governance`  
**Status:** ARCHIVED PASS 2026-08-10  
**Phase:** E3 OpenSpec 3 of the Nexi sequence  
**Depends on:** `ai-capability-kernel` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-ai-capability-kernel/`

## Intent

Persist and govern Nexi **conversations / turns / runs / events / proposals / usage** with a queued Run worker, reauthorized SSE transport, TaskGrants for cross-page continuity, budgets, cancellation, emergency stop, and reconciliation — behind a **fake AiGateway** only.

This is the cut that answers: durable chat history across pages/refresh, proposal shell (not Apply execution), and TaskGrant-based cross-page tool continuity (AD-19).

## In scope

1. Context epochs + Inquiry default; Assistant consent record seam (may remain inactive without Assistant tools)
2. TaskGrants (create/revoke/expiry; navigation may preserve within bounds; never inherit destination-page tools)
3. PostgreSQL conversation / turn / run / event / proposal / usage tables + state machines
4. Queued Run worker → fake gateway → output assembler/firewall → sequenced application events
5. Reauthorized SSE cursor / `Last-Event-ID` replay + snapshot fallback
6. Idempotency keys, cumulative disclosure budgets, budget reserve/settle/release, cancel, emergency stop
7. Retention tombstones + reconciliation jobs for orphan reservations / stale runs / failed outbox
8. Wire `NexiSessionView` conversation/run cursor + usage presentation from durable state (still degraded without live provider)

## Non-goals

| Deferred | Next change |
|---|---|
| Knowledge CRUD / index | `nexi-knowledge-governance` |
| Live `laravel/ai` provider | `ai-provider-live-spike` |
| Policy RAG / applicant Inquiry | later Inquiry OpenSpecs |
| Confirm-to-Apply mutations / exam commands | `nexi-exam-session-assistant` |
| Propose/command registry kinds beyond shell | later Assistant cuts |
| Redefining page packs as allowlist cages | forbidden (AD-3) |

## Founder constraints carried forward

- **AD-3 pack-as-lens:** dock stays on mountable surfaces without pack; fail-closed = no prior-pack inheritance; Kernel ∩ GF-21 remains authz.
- **AD-19 TaskGrants:** cross-page *tool* continuity uses grants + fresh Kernel auth — not “whatever the new page pack lists.”
- Fake gateway only; no domain command execution from proposals in this cut.

## Authority

- Spine AD-6, AD-9, AD-11, AD-15–AD-19, AD-22, AD-24, AD-26 (esp. SSE/queue rule)
- READINESS-PLAN §17 OpenSpec 3; Junior Slice 3
- Prior PASS: Kernel archive + foundation archive
- Stack: Inertia 3 + Svelte 5

## Exit

- Staff can open Nexi, submit a turn, see sequenced events via SSE/snapshot with fake gateway replies
- History survives navigation/refresh within surface partition
- TaskGrant create/revoke fixtures; budget deny when exhausted; cancel + emergency stop proven
- No live provider SDK; no Apply mutation commit
