# Change Proposal: AI Capability Kernel (registry + first Query parity)

**Change ID:** `ai-capability-kernel`  
**Status:** ARCHIVED PASS 2026-08-10  
**Phase:** E3 OpenSpec 2 of the Nexi sequence  
**Depends on:** `ai-assistant-foundation` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-ai-assistant-foundation/`

## Intent

Stand up the **one non-bypassable Capability Kernel + Registry** so staff HTTP and (later) Nexi share the same authorized read path — starting with low-risk `setup.readiness.read@1` over existing `ComputeSetupReadinessQuery`.

This change delivers only:

1. **Capability Registry** — code-owned immutable definitions (`key@version`, schemas, kind=read, GF-21 capability, handler, projections)
2. **CapabilityKernel dispatch** — principal/provenance → schema validate → authorize → invoke → channel project → typed result
3. **Reference capability** — `setup.readiness.read@1` wrapping `ComputeSetupReadinessQuery`
4. **HTTP/Nexi parity seam** — Setup hub (and staff home readiness use if any) invokes readiness **only** through the Kernel; Pest proves identical authoritative result shape
5. **RuleSetRef / preflight / ConstraintEvidence / RulePublication compatibility seams** — stubs sufficient for read capabilities (no mutation confirmation)

## Non-goals (explicit later OpenSpecs)

| Deferred | Next change |
|---|---|
| Conversations, SSE, TaskGrants, usage ledger, proposals | `nexi-run-and-usage-governance` |
| Knowledge CRUD / index | `nexi-knowledge-governance` |
| Laravel AI / live provider | `ai-provider-live-spike` |
| Policy RAG / applicant Inquiry | later Inquiry OpenSpecs |
| Exam Assistant + confirm Apply | `nexi-exam-session-assistant` |
| Provider-facing tool exposure / model loops | after Kernel + run governance |
| Write/propose capabilities | later (not this cut’s first tool) |

## Why now

OpenSpec 1 shipped SurfaceProfile + PageCapabilityPack + `NexiSessionView` + shared dock. Without the Kernel, Nexi cannot safely call domain work. Architecture AD-1/AD-2 and READINESS-PLAN §17 OpenSpec 2 authorize this cut only.

## Founder constraint carried forward (pack ≠ cage)

Page packs remain a **relevance lens** (AD-3, 2026-08-10). Kernel authorization is actor Capability Registry ∩ GF-21 ∩ surface/mode — **not** “only tools listed on the current page pack.” Pack suggestions may *highlight* `setup.readiness.read@1` on setup routes; absence of a pack must not hide the dock or invent a second authz path. Cross-page tool continuity beyond suggestions waits for TaskGrants in OpenSpec 3.

## Authority

- [`ARCHITECTURE-SPINE.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/ARCHITECTURE-SPINE.md) AD-1, AD-2, AD-3 (hardened), AD-19–AD-21
- [`READINESS-PLAN.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/READINESS-PLAN.md) §17 OpenSpec 2
- [`JUNIOR-IMPLEMENTATION-GUIDE.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/JUNIOR-IMPLEMENTATION-GUIDE.md) Slice 2 (Kernel portion)
- Prior PASS: [`archive/2026-08-10-ai-assistant-foundation/`](../archive/2026-08-10-ai-assistant-foundation/)
- Domain query: `App\Setup\Queries\ComputeSetupReadinessQuery`
- Stack: Inertia 3 + Svelte 5 (no React)

## Exit

- Duplicate registry keys fail boot/build
- Staff without setup capability denied; with capability gets same readiness payload via Kernel as via migrated controller path
- Applicant / wrong surface cannot invoke staff readiness capability
- No provider, conversation tables, or command confirmation endpoints added
