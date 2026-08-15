# Change Proposal: AI Assistant Foundation (degraded shell + server session view)

**Change ID:** `ai-assistant-foundation`  
**Status:** ARCHIVED PASS 2026-08-10  
**Phase:** E3 foundation seam (OpenSpec 1 of the Nexi sequence)  
**Depends on:** exam-session-scheduling archived PASS 2026-08-10; Nexi architecture package FINAL 2026-08-10  
**Archived prior:** `2026-08-10-exam-session-scheduling`

## Intent

Move Nexi from **client-demo chrome** to a **server-owned degraded foundation** without installing a provider, RAG, conversation persistence, tools, or mutations.

This change delivers only:

1. **Shared disabled/degraded Svelte dock adapter** — one presentation component reused by staff and applicant shells under distinct surface profiles  
2. **Code-owned `SurfaceProfile` catalog + precedence** — staff, applicant, proctor_session, knowledge_admin, operations, break_glass  
3. **Route → `PageCapabilityPack` compiler** for the first non-sensitive pages (staff home / setup readiness, applicant portal) with Inquiry default  
4. **Typed `NexiSessionView`** shared via Inertia — availability, modes, disclosure, pack/surface versions, usage presentation, manual fallbacks  
5. **Authority move** — `nexi-context.js` / `nexi-actions.js` become non-authoritative fixtures; production decisions come from server props  

## Non-goals (explicit later OpenSpecs)

| Deferred | Next change |
|---|---|
| Capability Registry / Kernel / `setup.readiness.read@1` parity | `ai-capability-kernel` |
| Conversation, SSE, proposals, usage ledger, reconciliation | `nexi-run-and-usage-governance` |
| Knowledge CRUD / index / citations | `nexi-knowledge-governance` |
| Laravel AI SDK / live provider | `ai-provider-live-spike` |
| Policy RAG | `nexi-policy-inquiry-rag` |
| Applicant live Inquiry | `nexi-applicant-inquiry` |
| Exam-session Assistant + confirm mutations | `nexi-exam-session-assistant` |

## Why now

Architecture spine, visual companion, readiness plan, junior guide, and 2026-08-10 readiness report are FINAL for **OpenSpec drafting**. Live provider work remains unauthorized. Manual exam scheduling is archived PASS; the master checkpoint already named AI foundation as next.

## Authority

- [`docs/greenfield-foundation/architecture/ai-assistant-foundation/ARCHITECTURE-SPINE.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/ARCHITECTURE-SPINE.md) (AD-3, AD-4, AD-9, AD-15, AD-18, AD-20, AD-24)  
- [`READINESS-PLAN.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/READINESS-PLAN.md) §17 OpenSpec 1  
- [`JUNIOR-IMPLEMENTATION-GUIDE.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/JUNIOR-IMPLEMENTATION-GUIDE.md) Slice 0–1 (+ surface/page-pack portion of Slice 2 without Capability Kernel)  
- [`implementation-readiness-report-2026-08-10.md`](../../../docs/greenfield-foundation/evidence/implementation-readiness-report-2026-08-10.md)  
- Locked chrome: [`ui-kit-locked-start-set.md`](../../../docs/greenfield-foundation/evidence/ui-kit-locked-start-set.md) §4.1  
- Stack authority: **Inertia 3 + Svelte 5** (not React)

## Embedded decisions (foundation-only)

| ID | Decision for this change |
|---|---|
| Presentation stack | Inertia 3 + Svelte 5 remains target; do not reintroduce React for Nexi |
| AI-001 (partial) | Shared capability path is **planned**; this change does **not** implement the Kernel — only surface/pack/session view seams |
| AI-006 | Confirmation/proposal contracts are **documented out of scope**; no mutate tools in this cut |
| Mode default | Every new context epoch / page load defaults to Inquiry; Assistant consent UI may render only as **unavailable/disabled** affordance when `assistant_available` is not yet live |
| Client stubs | URL/capability arrays and local mode persistence are never authority |

## Exit

- Staff `/staff` (or setup readiness) and applicant `/applicant/portal` receive distinct server `nexi` props (`SurfaceProfile` + pack + availability)  
- Dock renders degraded/inquiry_only/hidden correctly; no provider network calls  
- Pest: surface precedence + unknown route fail-closed; Inertia prop contract tests  
- Vitest/Playwright: dock state matrix + keyboard/reduced-motion on staff and applicant shells  
- HANDOFF lists seed logins, URLs, reset steps  

## Status note

This OpenSpec **authorizes implementation of the degraded foundation only**. It does **not** authorize SDK install, embeddings, persistence tables for runs, or Assistant mutations.
