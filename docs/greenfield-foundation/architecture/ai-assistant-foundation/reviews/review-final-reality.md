# Final Technology-Reality and Junior-Implementability Review

**Reviewed:** 2026-08-10  
**Primary artifact:** `ARCHITECTURE-SPINE.md`  
**Support:** `JUNIOR-IMPLEMENTATION-GUIDE.md`, `READINESS-PLAN.md`, `implementation-readiness-report-2026-08-10.md`, and the current `system/` runtime  
**Verdict:** **CONDITIONAL PASS — the architecture is technically coherent and suitable as the authority for the next ADR, story, and OpenSpec work. It is not yet a direct junior coding handoff. The blockers are correctly outside the domain architecture, but must be made concrete before the affected slices begin.**

## Executive assessment

The final spine has the right seams. Nexi is an inbound adapter to the same domain Actions and Queries used by ordinary pages; the provider is isolated behind `AiGateway`; page packs filter relevance without granting authority; surface profiles partition staff, applicant, proctor, knowledge-administration, operations, and break-glass contexts; mutations use immutable proposals plus a fresh Capability Kernel dispatch; RAG is separated from deterministic operational Queries; and failure leaves the manual application usable.

This is compatible with the committed application:

- the backend is PHP 8.4, Laravel 13, Inertia Laravel 3, PostgreSQL, Spatie Permission, Pest 5, and a database queue/cache baseline;
- the frontend is Svelte 5, Inertia Svelte 3, TypeScript 5.9, Vitest, and Playwright;
- `laravel/ai` is not installed, matching AD-13's adapter-candidate status;
- existing domain work already lives in Actions and Queries, so the Capability Kernel can wrap and gradually migrate callers instead of forcing duplicated AI routes;
- the current Nexi component is a degraded presentation prototype whose URL-derived context, browser-derived action list, locally persisted mode, and demo usage values are explicitly marked for replacement;
- the applicant layout does not yet mount Nexi, so AD-23 can be introduced before applicant history or staff capabilities are accidentally shared.

Provider isolation, schema validation, proposal integrity, domain-rule evidence, usage reservation, source governance, revocation, manual fallback, and test expectations are stronger than a typical first AI integration. No direct model-to-route, model-to-ORM, model-to-DOM, or SDK-approval-to-commit path remains in the design.

## Findings

### F-1 — P0: the repository-wide React/Svelte contradiction still blocks junior stories

The executable runtime is unambiguously Inertia 3 plus Svelte 5, and the proposed frontend ownership paths are Svelte-specific. The final readiness plan and implementation guide both identify promoted documents that still say React 19. A junior cannot be asked to infer whether those documents are stale or whether a framework migration is intended.

**Required disposition:** update the PRD/roadmap/GF authorities named by the readiness plan before approving Slice 1 or the foundation OpenSpec. The accepted decision must say that Nexi extends the current Svelte target. This is a documentation correction, not a reason to change AD-24.

### F-2 — P1: OpenSpec 1 is much larger than the junior slice sequence and must be decomposed

`READINESS-PLAN.md` currently places the Contract Kernel, Capability Registry, page-pack resolver, generic transport, context epochs, TaskGrants, conversations, runs, streams, proposals, usage persistence, domain-rule evidence, atomic RulePublication, reverse invalidation, KnowledgeRouter, pilot page migration, and concurrency tests in one foundation OpenSpec. The junior guide correctly divides that work into a degraded shell, server contract kernel, persistence/recovery foundation, knowledge governance, provider spike, Inquiry, and Assistant cuts.

The guide's ordering is implementable; the single OpenSpec scope is not a junior-sized unit. It has too many independent state machines and recovery outcomes for one reviewable change, and it would encourage placeholder implementations merely to make the whole graph compile.

**Required disposition:** make the OpenSpec an umbrella with independently reviewable task groups/stories, or split it into at least:

1. disabled/degraded Svelte shell plus server-owned `SurfaceProfile`/`NexiSessionView` fixtures;
2. Capability Kernel, registry, schema profile, page-pack compiler, and one read-only pilot capability;
3. conversation/run/event persistence, proposal/confirmation, usage reservation, and reconciliation using a fake gateway;
4. rule-publication and knowledge-router/governance seams.

Each unit needs its own migrations, rollback, seed, Pest/Vitest/Playwright proof, and stop condition. No unit should need a live provider.

### F-3 — P1: stream delivery and worker topology remain a decision, not an implementation instruction

AD-17 and AD-24 specify the correct semantic protocol: monotonic per-run sequence, durable state/citation/proposal/usage/terminal events, transient token deltas, duplicate suppression, gap detection, and snapshot recovery. `READINESS-PLAN.md` recommends SSE/fetch streaming but intentionally leaves exact URIs and execution topology open.

The current runtime has `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, and `BROADCAST_CONNECTION=log`; it does not have a production broadcast transport. Direct long-lived PHP SSE, queued generation plus polling/SSE fan-out, and Laravel AI queue/broadcast are materially different operational designs. They change database connection pressure, reverse-proxy timeouts, disconnect behavior, cancellation, ordering, retry, and horizontal deployment.

**Required disposition before Slice 3:** record a transport/runtime LLD or ADR that pins:

- whether the first implementation streams in-request or runs generation in a queue worker;
- how durable events are committed and observed without publishing an event before its transaction commits;
- the cursor/snapshot endpoints and reconnect timeout/heartbeat contract;
- proxy/PHP/worker timeouts, maximum stream duration, client-disconnect semantics, and backpressure;
- event ordering across worker retries and the owner of the one-terminal-event constraint;
- local/test setup and the production dependency, if any, beyond the database queue.

The provider spike may measure SDK cancellation and streaming behavior, but a junior must not choose the application transport from those measurements ad hoc.

### F-4 — P1: the Capability Kernel migration needs an accepted adapter recipe for current controllers

The current routes commonly combine route middleware or custom middleware, Form Request validation, controller orchestration, and direct Action invocation. AD-1 says browser controllers and Nexi must use one non-bypassable Capability Kernel, while the foundation plan migrates only two or three examination operations. That gradual migration is correct, but the exact bridge is not yet defined.

Without AI-001/AI-001A, a junior would have to invent:

- whether existing Form Requests map to registry DTOs or are replaced by schema-generated validation;
- where authenticated principal, provenance, scope, expected version, and idempotency identity are created;
- whether the kernel invokes existing Actions directly or via thin registered handlers;
- how page-specific redirects/flash messages remain channel projections rather than handler behavior;
- how route middleware and kernel authorization coexist during migration without either being mistaken for the sole authority;
- how one Action result is proven equivalent through page and Nexi channels.

**Required disposition:** AI-001/AI-001A must include one executable reference migration using an existing read and one existing command, plus contract tests for direct HTTP, Nexi read, confirmation dispatch, denied scope, schema mismatch, stale version, and replay. Other capability stories should copy this reference instead of inventing a new seam.

### F-5 — P1: persistence, reconciliation, and knowledge deletion need concrete operational contracts before CRUD work

The architecture correctly names all conceptual records and reconciliation classes, and AD-25 prevents ordinary file CRUD from becoming corpus authority. The remaining details are necessarily LLD/OpenSpec work, but they are not optional implementation choices.

Before the conversation/usage/knowledge slices, the accepted design must pin:

- table keys, uniqueness and optimistic-version constraints, legal state transitions, ownership indexes, and transaction boundaries;
- job uniqueness, lock scope, retry/backoff limits, stale thresholds, reconciliation cadence, operator visibility, and terminal/manual-repair states;
- supported upload formats, maximum size, private-object storage, checksum duplicate semantics, malware scanning, extraction adapters, and immutable artifact storage;
- the distinction between synchronous retrieval denial, live-store deletion, retained audit evidence, legal hold, and backup expiry. Selective deletion from immutable backups must not be promised as an immediate job outcome;
- index rebuild/swap behavior, model/dimension compatibility, active-version atomicity, and recovery when indexing succeeds but publication/invalidation work fails;
- retention/clear/export rules for conversations, provider metadata, pseudonym maps, citations, proposals, audit evidence, and usage records.

AI-004, AI-005, AI-007, AI-009, and the OpenSpec migrations should own these decisions. The Svelte knowledge-administration pages may be built first against fakes only after their lifecycle and failure fixtures are fixed.

## Area verdicts

| Area | Verdict | Reason |
|---|---|---|
| Laravel/Svelte fit | Pass with gate | The committed stack supports the architecture; `laravel/ai` remains correctly uninstalled and must be re-verified/pinned in AI-002. |
| Route/capability seam | Pass after AI-001/001A | The shared kernel is the right design; the existing controller-to-kernel migration recipe is still a blocker. |
| Provider isolation | Pass | No SDK or provider type crosses NEXIAM ports, and conformance fixtures cover malformed streams, tools, usage, refusal, timeout, and cancellation. |
| Streaming/reconnect | Conditional | Protocol semantics are strong; runtime transport, queue/broadcast topology, and deployment timeouts are undecided. |
| Conversation/proposal/usage persistence | Conditional | Required records and invariants are identified; migrations, constraints, and reconciliation ownership belong in the next accepted LLD/OpenSpec. |
| Knowledge/index lifecycle | Conditional pass | Governance and synchronous revocation are sound; extraction/storage/index/deletion/backup operations still need pinned contracts. |
| Applicant isolation | Pass with implementation proof | AD-23 is strong and timely; the current common web guard and shared User model require explicit conflicting-profile and cross-surface tests. |
| Testability | Pass | Pest, Vitest, and Playwright are present, fakes are the required first path, and the proposed adversarial/race/reconnect fixtures are executable. |
| Junior sequencing | Conditional | The guide is good; the foundation OpenSpec must be decomposed and the stale planning authorities corrected. |

## Required proof before each promotion

### Before frontend stitching

- React/Svelte authority corrected;
- applicant, staff, proctor, knowledge-admin, operations, and break-glass profile fixtures accepted;
- Inquiry is the server-projected default;
- the applicant shell receives no staff history, modes, packs, suggestions, usage policy, or TaskGrant;
- the current URL/action stubs are explicitly test/demo-only.

### Before persistent fake-gateway foundation

- AI-001/AI-001A reference migration accepted;
- exact migrations and database constraints reviewed;
- stream/runtime topology pinned;
- concurrency, replay, cursor-gap, duplicate-confirmation, kill-switch, and orphan-reservation tests pass.

### Before knowledge CRUD

- source lifecycle, separation of duties, storage/extraction policy, retention/hold semantics, and reconcile operations fixed;
- publish cannot make a version active before complete index/citation evidence;
- revoke denies retrieval synchronously even when cache/index deletion fails;
- operational records cannot enter the vector corpus through the upload path.

### Before a live provider, RAG, own-data answer, or command

- the corresponding AI-002 through AI-009 decisions and evidence gates are accepted;
- a dedicated user-value story/OpenSpec names exact actors, fixtures, errors, manual fallback, and proof;
- provider data handling, model routing, cost, cancellation, outage, retention, and incident controls are approved;
- the selected behavior passes the common adapter, privacy, injection, authorization, citation/refusal, and regression suites.

## Final verdict

The architecture spine should remain **final**. Its invariants do not require another conceptual redesign. It is ready to authorize the next planning artifacts and the fake/degraded vertical slices.

It is not honest to label the repository ready for unrestricted junior implementation yet. The implementation-readiness report's **NEEDS WORK** verdict still applies to live/provider-backed Nexi because the dedicated epics/stories and OpenSpec are absent, the frontend framework contradiction remains, and the transport, migration, persistence, and operational contracts above are deliberately gated. Once those are made explicit, a junior can implement the slices without inventing security, product, framework, or recovery behavior.
