# Evidence: ai-assistant-foundation OpenSpec 1

**Date:** 2026-08-10  
**OpenSpec:** `openspec/changes/ai-assistant-foundation/`  
**Scope:** degraded shells + SurfaceProfile/PageCapabilityPack + NexiSessionView (no provider)

## Server

- `App\Ai\Surface\*`, `App\Ai\Context\*`, `App\Ai\Policy\EffectiveNexiAvailabilityResolver`, `App\Ai\Http\NexiSessionView*`
- Inertia share: transitional BC fields + `nexi.session` contract
- Pest: `tests/Feature/Ai/NexiFoundationSessionTest.php` — **9 passed**

## Frontend

- `resources/js/types/nexi.ts`
- `Components/kit/Nexi/NexiDock.svelte` mounted from AuthenticatedLayout, StaffLayout, ApplicantLayout
- `nexi-context.js` / `nexi-actions.js` marked FIXTURE / DEMO ONLY
- NexiAssistPanel accepts `session`; Inquiry forced; Apply catalog empty under session
- Vitest: `tests/frontend/nexi-session.test.ts` — **3 passed**
- Playwright: `tests/browser/nexi-foundation.spec.ts` — **2 passed** (public/applicant login have no Nexi dock)

## Explicitly not in this cut

Provider SDK, RAG, Capability Kernel, conversation persistence, propose/confirm mutations.

## Amendment 2026-08-10 — pack is lens not cage

Unregistered staff routes (e.g. `exams.sessions.index`) keep `availability: degraded` with `page_pack: null`. Pest updated accordingly (9 passed).


## ARCHIVED PASS 2026-08-10

OpenSpec moved to `openspec/changes/archive/2026-08-10-ai-assistant-foundation/`. Founder pack-as-lens clarification promoted to durable spine **AD-3** and carried into active `ai-capability-kernel` design.
