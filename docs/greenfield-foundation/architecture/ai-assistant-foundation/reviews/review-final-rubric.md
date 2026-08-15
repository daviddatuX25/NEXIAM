---
review: final-rubric-walker
target: ../ARCHITECTURE-SPINE.md
supporting_evidence:
  - ../JUNIOR-IMPLEMENTATION-GUIDE.md
  - ../READINESS-PLAN.md
  - ../ARCHITECTURE-VISUAL.md
  - ../../../evidence/implementation-readiness-report-2026-08-10.md
date: 2026-08-10
verdict: pass-with-required-reconciliation
---

# Final Rubric Review — Nexi AI Assistant Foundation

## Verdict

**PASS WITH REQUIRED RECONCILIATION:** the spine is a strong feature-level build substrate and has no critical authority, privacy, or brownfield-fit failure. It covers the full Nexi trust surface and fixes the consequential divergence points. Before knowledge-governance implementation is handed to a junior, the two normative knowledge lifecycle descriptions must be reconciled. Before any junior coding beyond the disabled/degraded frontend shell, the named OpenSpec/story, exact route-pack catalog, planning-stack reconciliation, and deferred product/privacy decisions must be approved.

The deterministic architecture linter reports zero findings.

## Good-spine checklist

| Criterion | Result | Evidence and judgment |
| --- | --- | --- |
| Fixes the real divergence points for the level below | **Pass** | AD-1 through AD-9 prevent duplicate AI routes, raw route/DOM execution, prompt-owned business logic, autonomous commands, and route-hopping bulk work. AD-20 through AD-25 close rule-change propagation, knowledge routing, cross-surface isolation, frontend authority, and publication governance. |
| Every rule is enforceable and prevents its stated divergence | **Pass with one high reconciliation** | Most rules name a server-owned registry, resolver, state machine, digest, compare-and-swap transition, fail-closed behavior, test fixture, or typed command. AD-21 and AD-25 currently describe different legal knowledge-version state sets; see H-1. |
| Deferred items cannot silently split implementations | **Pass** | Provider/model, index, retention, quotas, publication storage, applicant promotion, and failover are all paired with explicit ADR/OpenSpec gates. None is required for the first disabled/degraded frontend slice. Exact values are deferred; the governing invariants are not. |
| Named technology is verified-current or explicitly gated | **Pass** | PHP 8.4, Laravel 13.23+, Inertia Laravel 3.x, Svelte 5.51+, Inertia Svelte 3.6+, and TypeScript 5.9 match the executable repository constraints. `laravel/ai` remains an uninstalled, version-noted candidate that must be reverified and locked at AI-002. |
| Ratifies the brownfield rather than replacing it | **Pass** | The spine extends the existing Svelte `NexiAssistPanel`, staff/applicant layouts, domain Actions/Queries, audit/outbox seams, and Inertia page flow. It explicitly retires client URL/action stubs as production authority instead of treating them as a new backend. No provider package or AI-specific domain route is assumed. |
| Covers the driving capabilities and all Nexi surfaces | **Pass at architecture altitude** | AD-23 isolates staff, applicant, proctor/session, knowledge administration, operations, and break-glass. The guide covers public/static entry, applicant status/scheduling/correction/results, staff setup/personnel/admissions/exams/grading/consultation/release/reports/audit, knowledge administration, and recovery, with capability and skill seeds. Exact canonical route-to-pack entries remain an implementation artifact, correctly blocked by AD-3 and the stop rules. |
| Preserves inherited constraints | **Pass** | Human authority, capability/scope authorization, Action/Query ownership, idempotency, audit/outbox, auth-before-retrieve, citation/refusal, manual fallback, and Svelte/Inertia executable reality are preserved or strengthened. |
| Owns or explicitly gates every architectural dimension | **Pass** | Application seams, state/data, security/privacy, UI/session, provider/index strategy, usage/cost, availability, reconciliation, retention, evaluation, incident stop, and recovery are decided or named as blocking ADRs. There is no silent provider, vector store, retention, or deployment assumption. |
| Converges for junior implementation | **Conditional pass** | The guide supplies vocabulary, session shape, UI states, surface/route-family matrix, capability/skill seeds, knowledge pages/lifecycle, slices, ownership, story template, DoR/DoD, and stop rules. It intentionally does not replace the still-missing accepted E3 OpenSpec/stories, exact route catalog, schema fixtures, seed identities, or product/privacy decisions; see H-2. |

## Findings

### High

#### H-1 — Two normative knowledge lifecycles can produce incompatible enums and transitions

- **Evidence:** AD-21 defines `draft → approved → indexing → active` plus `superseded`, `revoked`, and `deletion_pending`. AD-25 defines `draft → extracting → review_required → approved → indexing → active` and additionally names `rejected`, `index_failed`, and `deleted`.
- **Impact:** one team can implement the short AD-21 lifecycle while another implements AD-25, causing the source registry, commands, workers, UI states, and citation/reconciliation tests to disagree. The shorter path can also appear to permit approval without the mandatory extraction/review stages.
- **Disposition:** **autofix before knowledge CRUD handoff.** Make AD-25 the single canonical lifecycle, have AD-21 reference it rather than restate a subset, and align the shorter lifecycle wording in the readiness plan. Generate one shared enum/transition table for PHP, TypeScript, fixtures, and workers.

#### H-2 — The architecture is converged, but the repository is not yet authorized for junior implementation beyond the degraded shell

- **Evidence:** the guide correctly declares a dedicated E3 OpenSpec gate and says `DECISION REQUIRED` blocks a story. The readiness assessment records no Nexi delivery epic, no accepted Nexi dependency/story package, missing exact seed/demo paths, and a promoted React-versus-Svelte contradiction. The route inventory is intentionally a route-family seed, not AD-3's final canonical route-to-pack registry.
- **Impact:** handing the whole foundation to a junior now would require them to invent route ownership, exact schemas, fixtures, retention behavior, and story boundaries even though the architecture expressly forbids that authority.
- **Disposition:** **treat as a handoff gate, not a reason to redesign the spine.** The shared disabled/degraded Svelte shell can be scoped immediately; every later slice must wait for the planning-stack correction, accepted OpenSpec/story, exact route-pack catalog, schemas/error fixtures, and seeded evidence path required by the guide.

### Medium

#### M-1 — Availability-state names are not yet one canonical contract

- **Evidence:** AD-18 uses descriptive `assistant-enabled`; the readiness plan lists `assistant_enabled`; the guide's proposed `NexiSessionView` distinguishes `assistant_available` and `assistant_active`.
- **Impact:** backend DTOs, Svelte branches, event fixtures, and tests can choose different state names or conflate availability with active consented mode.
- **Disposition:** **resolve in AI-001A before Slice 1 fixtures are accepted.** Publish one availability enum and keep `active_mode` separate; generate/check PHP and TypeScript from the same contract.

#### M-2 — Surface resolution needs an exact precedence fixture for staff actors entering specialized shells

- **Evidence:** AD-23 requires exactly one profile before page-pack resolution, while the guide places knowledge administration under `/staff/knowledge/**` and treats `knowledge_admin` as a separate profile. Operations and break-glass can have the same actor identity with a different active context.
- **Impact:** implementations could select profiles from role alone, route alone, or session context, producing history/retention/prompt partition drift.
- **Disposition:** **add to the foundation OpenSpec fixtures.** Define the trusted inputs and precedence for staff → knowledge-admin, staff → operations, active duty, impersonation, and break-glass transitions; require a new context epoch and fail closed on ambiguity.

### Low

#### L-1 — Structural seed duplicates two module paths

- **Evidence:** the seed lists `Ai/Knowledge/` and `Ai/Publication/` once with their intended responsibilities, then lists both paths again below the domain folders.
- **Impact:** minor ownership and folder-scaffolding confusion for a junior.
- **Disposition:** remove the duplicate pair when the spine receives its next editorial update; no design decision changes.

## Surface and use-case coverage judgment

The final pass is no longer exam-scheduling-only. It establishes:

- a shared presentation component with isolated staff, applicant, proctor/session, knowledge-admin, operations, and break-glass trust profiles;
- policy Inquiry, released own-data Inquiry, staff operational Query context, and proposal/confirmation Assistant paths without mixing retrieval classes;
- reusable skills across setup, admissions, scheduling, proctoring, grading, consultation/release, reports, personnel, knowledge governance, and applicant journeys;
- knowledge source/version administration through extraction, review, approval, publication, citation testing, revocation, deletion, and reconciliation;
- durable usage reservation/settlement, per-actor and institutional policy, emergency disablement, degraded/manual operation, and reconnect/cancellation semantics;
- a frontend-first path that can mount the applicant dock safely before a provider exists.

The remaining exact page rows are deliberately work for the route-pack catalog, not a gap to fill with global prompts or raw Laravel routes.

## Brownfield-fit judgment

The design fits the executable repository: Laravel 13, Inertia 3, Svelte 5, the existing staff dock, an applicant layout without Nexi, client-side Nexi context/action demos, and domain-oriented Actions/Queries. Its migration path is additive and reversible: mount typed degraded shells, move context authority server-side, add the contract/capability kernel, preserve ordinary UI workflows, and introduce provider/RAG adapters only after their gates.

The stale React references are a planning-authority defect outside this spine, not a reason to migrate the working Svelte application. They must nevertheless be corrected before a junior story is opened.

## Gate conclusion

The spine is suitable for final architecture approval and for drafting the dedicated Nexi OpenSpec/epics. Apply H-1 before knowledge implementation; close H-2's handoff prerequisites before junior coding beyond the safe shell slice. M-1 and M-2 belong in the contract appendix/OpenSpec and should be accepted before their corresponding fixtures are treated as authoritative.
