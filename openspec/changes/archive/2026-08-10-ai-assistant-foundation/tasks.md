# Tasks: AI Assistant Foundation (OpenSpec 1)

## A — Authority & archive

- [x] A1 Archive `exam-session-scheduling` → `openspec/changes/archive/2026-08-10-exam-session-scheduling`
- [x] A2 Activate this OpenSpec; update `docs/project-context.md`, master checkpoint, archive README
- [x] A3 Confirm Inertia 3 + Svelte 5 authority in active planning pointers for this change (no React reintroduction)
- [x] A4 Record bounded Nexi disclosure/persona placeholders used by `NexiSessionView` (full product tone contract may deepen later)

## B — Server surface + page packs

- [x] B1 Code-owned `SurfaceProfile` catalog (`staff`, `applicant`, `proctor_session`, `knowledge_admin`, `operations`, `break_glass`) + deny-on-ambiguity precedence fixtures
- [x] B2 Route-name → `PageCapabilityPack` compiler for first packs: `staff.workspace.home@1`, `staff.setup.readiness@1`, `applicant.portal.home@1`; unknown routes do not inherit prior pack (`page_pack: null`; dock stays degraded on mountable surfaces)
- [x] B3 Effective availability stub (flags ∩ profile ∩ pack ∩ no-provider → `degraded` / `inquiry_only` / `hidden`); Inquiry default; Assistant not activatable
- [x] B4 `NexiSessionView` PHP DTO (`contract_version: 1`) + builder wired into Inertia share (`HandleInertiaRequests` or dedicated sharer)
- [x] B5 Pest: staff vs applicant distinct props; public/login no authenticated Nexi; unknown/ambiguous **surface** → fail closed (`hidden`); unknown **pack** → degraded without tools

## C — Shared degraded Svelte shell


- [x] C1 Shared dock adapter over locked wrap-island chrome; mount from staff + applicant layouts via server `nexi` prop only
- [x] C2 Checked TypeScript `NexiSessionView` + availability/mode unions; unknown contract_version → degraded/manual fallback
- [x] C3 Render state matrix: `hidden`, `inquiry_only`, `degraded`, `quota_exhausted`, `suspended` (assistant states present in type but not activatable)
- [x] C4 Demote `nexi-context.js` / `nexi-actions.js` to non-authoritative fixtures/demo; remove production decision paths
- [x] C5 Vitest dock state matrix; Playwright staff + applicant keyboard / reduced-motion / no provider network

## D — Handoff

- [x] D1 Developer-ready `HANDOFF.md` seed credentials, URLs, reset steps, expected degraded states
- [x] D2 Evidence note path under `docs/greenfield-foundation/evidence/` when B/C pass
- [x] D3 Update master checkpoint when this OpenSpec exits PASS (do not open kernel/provider work inside this change)

## Explicitly out of task scope

Capability Kernel, conversation/SSE/usage ledger, knowledge admin, `laravel/ai`, RAG, propose/confirm mutations, exam Assistant skills.
