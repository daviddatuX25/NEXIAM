# NEXIAM Greenfield Master Reference

**Purpose:** Single human entry point for rebuilding the complete legacy product as a scalable greenfield system  
**Status:** ACTIVE NAVIGATOR — E0/E1/E1b PASS; PRD promoted; E2 journey code present but OpenSpec parked INCOMPLETE; **active OpenSpec `exam-session-scheduling`**; institutional-setup-readiness archived PASS 2026-08-06; admission-attempt-detail archived PASS; staff-personnel archived PASS; permanent work process published  
**Work process (permanent):** [`project-context.md`](project-context.md)  
**Current readiness report:** [`greenfield-foundation/evidence/implementation-readiness-report-2026-08-05.md`](greenfield-foundation/evidence/implementation-readiness-report-2026-08-05.md) (planning PASS; prior: [2026-08-04](greenfield-foundation/evidence/implementation-readiness-report-2026-08-04.md))  
**Phase roadmap:** [`MASTER-DELIVERY-ROADMAP.md`](MASTER-DELIVERY-ROADMAP.md)  
**Target runtime:** [`../system/`](../system/)  
**Legacy runtime:** [`../legacy_codebase/application/`](../legacy_codebase/application/) — read-only behavior evidence
**Cursor always-on rule:** [`.cursor/rules/nexiam-greenfield.mdc`](../.cursor/rules/nexiam-greenfield.mdc)  
**Boost MCP:** [`.cursor/mcp.json`](../.cursor/mcp.json) → `system/bin/boost-mcp.sh` (reload Cursor MCP after first use)

This file is the place to start and return to. It does not replace the detailed GF contracts. It ties them together, records unresolved parity work, and prevents an implementation task from starting when its product behavior is still untraced.

## 1. Direct answer

The greenfield planning package now has a **promoted product PRD** ([`prd.md`](prd.md)). E2 remains **not implementation-ready** until slice legacy traces, page/state catalog, stories, and an `e2-*` OpenSpec pass readiness.

The 2026-08-04 readiness audit extracted 75 FR / 55 NFR / 83 BR with zero epics/stories at that time. On 2026-08-05 those catalogs were promoted into [`prd.md`](prd.md) with an explicit E2 Connected Slice cut-line (`REQ-VS-001`–`REQ-VS-032`). Planning blockers closed 2026-08-05; coding proceeds only under `openspec/changes/e2-connected-admission-release-slice/` tasks.

Therefore:

1. do not abandon the greenfield architecture;
2. do not start broad E2 implementation from memory;
3. use the complete legacy runtime to recover behavior and interaction details;
4. turn each recovered behavior into an explicit target disposition and acceptance contract;
5. build only from an approved story/OpenSpec after that trace exists.

No document can honestly promise that “not one detail is missed” merely by being long. NEXIAM achieves that goal through an exhaustive inventory and traceability rule: every important legacy route, page, command, state, scheduled action, notification, import, recovery path, and test must be classified and linked before its replacement is called ready.

## 2. Document-integrity alert

Integrity repair progress (2026-08-04):

- restored clean `GF-00` and foundation `README` from git, then re-pointed them to this master reference + `project-context.md`;
- reconstructed `GF-24` without omission markers;
- copied corrupted historical plans into `docs/greenfield-foundation/quarantine/` and bannered the originals as HISTORICAL only.

Acceptance remaining:

- verify zero literal omission markers in active/supporting documents;
- do not use quarantined/historical copies as sequence authority.

## 3. Authority model

Use this order when sources disagree:

1. recorded founder decisions in GF-13 and verified institutional/stakeholder evidence;
2. this navigator for current status, required trace shape, and stop rules;
3. the root [`MASTER-DELIVERY-ROADMAP.md`](MASTER-DELIVERY-ROADMAP.md) for phase and gate sequence;
4. promoted GF product/domain/workflow/data/security/architecture/UX/operations contracts;
5. the one active OpenSpec change;
6. executable tests and runtime evidence;
7. `docs/legacy-reference/` and the complete legacy codebase as product-behavior evidence;
8. historical plans last.

Legacy code answers **what users experienced and what rules existed**. It never wins on target authorization, transaction ownership, state authority, database constraints, provider boundaries, or scalability design.

## 4. Canonical document map

### Product, decisions, and evidence

- Formal product PRD (story-binding): [`prd.md`](prd.md)
- Product boundary and capability cut-line: [`greenfield-foundation/03-product-scope-and-success-contract.md`](greenfield-foundation/03-product-scope-and-success-contract.md)
- Founder decisions: [`greenfield-foundation/13-decision-and-batch-approval-matrix.md`](greenfield-foundation/13-decision-and-batch-approval-matrix.md)
- Evidence and contradictions: [`greenfield-foundation/02-evidence-and-contradiction-register.md`](greenfield-foundation/02-evidence-and-contradiction-register.md)
- Requirements traceability: [`greenfield-foundation/12-requirements-traceability-matrix.md`](greenfield-foundation/12-requirements-traceability-matrix.md)

### Domain and behavior

- Roles/capabilities/scopes: [`greenfield-foundation/04-role-capability-and-data-scope-matrix.md`](greenfield-foundation/04-role-capability-and-data-scope-matrix.md)
- Domain model/lifecycle: [`greenfield-foundation/05-domain-model-and-data-lifecycle.md`](greenfield-foundation/05-domain-model-and-data-lifecycle.md)
- Workflows/transitions: [`greenfield-foundation/06-workflows-and-state-transitions.md`](greenfield-foundation/06-workflows-and-state-transitions.md)
- Failure/recovery/integrations: [`greenfield-foundation/07-failure-recovery-and-integration-contracts.md`](greenfield-foundation/07-failure-recovery-and-integration-contracts.md)
- Connected-slice acceptance: [`greenfield-foundation/11-connected-vertical-slice-and-acceptance.md`](greenfield-foundation/11-connected-vertical-slice-and-acceptance.md)

### Architecture and implementation

- Platform standards: [`greenfield-foundation/09-platform-topology-and-development-standards.md`](greenfield-foundation/09-platform-topology-and-development-standards.md)
- Technical spikes: [`greenfield-foundation/10-capstone-research-and-technical-validation.md`](greenfield-foundation/10-capstone-research-and-technical-validation.md)
- ADR catalog: [`greenfield-foundation/15-architecture-decision-record-catalog.md`](greenfield-foundation/15-architecture-decision-record-catalog.md)
- HLD: [`greenfield-foundation/17-system-high-level-design.md`](greenfield-foundation/17-system-high-level-design.md)
- LLD: [`greenfield-foundation/18-module-and-domain-low-level-design.md`](greenfield-foundation/18-module-and-domain-low-level-design.md)
- Schema/constraints: [`greenfield-foundation/19-schema-constraint-and-migration-catalog.md`](greenfield-foundation/19-schema-constraint-and-migration-catalog.md)
- Application/integration contracts: [`greenfield-foundation/20-application-and-integration-contract-catalog.md`](greenfield-foundation/20-application-and-integration-contract-catalog.md)
- Authorization: [`greenfield-foundation/21-authorization-enforcement-design.md`](greenfield-foundation/21-authorization-enforcement-design.md)
- UX/interface: [`greenfield-foundation/22-user-experience-and-interface-contract.md`](greenfield-foundation/22-user-experience-and-interface-contract.md)
- Operations/recovery: [`greenfield-foundation/23-operations-and-recovery-design.md`](greenfield-foundation/23-operations-and-recovery-design.md)
- BMAD/OpenSpec handoff: [`greenfield-foundation/24-bmad-openspec-implementation-package.md`](greenfield-foundation/24-bmad-openspec-implementation-package.md)

### Legacy evidence

- Index and classification rules: [`legacy-reference/README.md`](legacy-reference/README.md)
- Feature replication catalog: [`legacy-reference/02-feature-replication-catalog.md`](legacy-reference/02-feature-replication-catalog.md)
- Legacy-to-greenfield crosswalk: [`legacy-reference/03-legacy-to-greenfield-crosswalk.md`](legacy-reference/03-legacy-to-greenfield-crosswalk.md)
- Observed UX baseline: [`legacy-reference/04-ui-ux-observed-baseline.md`](legacy-reference/04-ui-ux-observed-baseline.md)
- Data/migration safety: [`legacy-reference/05-data-and-migration-safety-notes.md`](legacy-reference/05-data-and-migration-safety-notes.md)
- Residual clarifications: [`legacy-reference/06-open-clarifications.md`](legacy-reference/06-open-clarifications.md)
- Snapshot manifest: [`legacy-reference/07-snapshot-manifest.md`](legacy-reference/07-snapshot-manifest.md)

## 5. Product reconstruction rule

Every feature or interaction must have this complete trace before implementation:

1. legacy source paths;
2. observed actor and data scope;
3. entry conditions and visible states;
4. observed mutation/query behavior;
5. observed success, error, empty, conflict, loading, denied, offline, and retry states;
6. target disposition: `PRESERVE`, `REWRITE`, `SPIKE`, `DEFER`, or `REJECT`;
7. founder/stakeholder decision or explicit constrained assumption;
8. target FR/NFR/BR and GF contract links;
9. target module, command/query, route, page, and component;
10. authorization capability, resource scope, and contextual duty;
11. transaction, lock, idempotency, audit, and after-commit behavior;
12. correction, cancellation, reversal, and recovery behavior;
13. accessibility/responsive behavior;
14. Pest/Vitest/Playwright/operational evidence;
15. OpenSpec task and final evidence path.

If any field is unknown, label it `UNVERIFIED` or `DECISION REQUIRED`. Do not guess and do not quietly omit the behavior.

## 6. Legacy parity items already proven to need deeper capture

These are confirmed examples of why the current catalogs are not enough.

### 6.1 Administrative setup readiness

Observed legacy behavior:

- `legacy_codebase/application/app/Http/Controllers/Admin/SetupController.php` computes overall and category readiness;
- critical, important, and optional checks have different semantics;
- `resources/js/Pages/Admin/Setup/Index.svelte` displays overall, category, and per-card progress bars;
- the first failing category expands automatically.

Target status: `UNVERIFIED — TARGET DISPOSITION REQUIRED`.

Required target work:

- distinguish the administrative readiness hub from applicant password setup;
- define checks from target reference/configuration prerequisites;
- define severity and completion semantics;
- define page props, progress states, accessibility text, and browser acceptance.

### 6.2 Breadcrumbs

Observed legacy behavior:

- pages supply labeled ancestor/current entries;
- linked ancestors and `aria-current` are rendered;
- mobile collapses multi-level trails into a menu;
- the final crumb drives the document title.

Target status: `PARTIALLY SPECIFIED`.

GF-22 defines principles, but E2 still needs a route-by-route breadcrumb catalog with safe parents, mobile behavior, authorization filtering, and Playwright cases.

### 6.3 Applicant and score imports

Observed legacy behavior:

- client-side/server-assisted file analysis;
- upload readiness and processing state;
- staged preview;
- row validation and invalid-row expansion;
- valid-row selection;
- explicit confirm/import step;
- applicant and score imports share an analyze → preview → confirm pattern;
- import confirmation is generally a full preview page, not an import verification modal.

Target status: `UNRESOLVED`.

Required decision:

- separate applicant/score imports or one bounded import framework;
- page versus dialog confirmation;
- file/row limits, mapping, duplicate handling, partial commit policy;
- staged payload storage and expiry;
- correction/cancellation/resume behavior;
- operation idempotency, audit, quarantine, and error export.

### 6.4 Dialogs and destructive confirmation

Legacy mixes accessible dialogs, hand-built overlays, and native `confirm()`.

Target status: `REWRITE`.

GF-22 must be projected into a command-level modal inventory: command, risk copy, required typed phrase or reason, mobile layout, focus behavior, conflict handling, and completion feedback.

### 6.5 Notifications and toasts

Observed legacy behavior includes redirect flash toasts, inline `message` handling on imports, polled database notifications, sounds, cooldowns, and mark-read actions.

Target status: `PARTIALLY SPECIFIED`.

Target wording must distinguish:

- command committed;
- operation queued/pending;
- provider delivery succeeded;
- delivery failed/degraded;
- durable in-app notification state.

A toast must never claim mail, release, import, or provider success without authoritative evidence.

### 6.6 Missed examination, no-show, retry, and reassignment

The legacy runtime has no single “re-exam” feature. It composes:

- mark absent;
- keep the application from advancing;
- remove from the old session;
- assign to another session;
- postpone/unpublish;
- cancel and notify;
- auto-cancel all-pending sessions;
- direct-assessment exceptions.

Target status: `DECISION REQUIRED — E2 BLOCKER`.

The target must explicitly decide:

- whether absence permits reassignment;
- who may initiate it;
- whether attempts are limited;
- deadline/window effects;
- whether a new exam attempt is created or the assignment changes;
- score/submission safeguards;
- applicant messaging;
- audit/reason requirements;
- cancellation/postponement interaction;
- concurrency and “one effective session” enforcement.

### 6.7 Additional legacy surfaces requiring trace rows

- applicant status timeline and current-step animation;
- staff application pipeline strip;
- applicant password-strength meter;
- QR/camera attendance versus submission modes;
- bulk accept/dismiss/revert actions;
- revert accepted/dismissed applications to pending;
- seasonal expiration of pending applications;
- result-release mode and face-to-face consultation blocks;
- schedule assistant dialog;
- report/result sheets and Program Head projections.

None may be silently discarded.

## 7. Greenfield architecture that must not be lost

- Modular monolith first; module-owned state and dependency tests.
- PostgreSQL constraints/locks are race-integrity authority.
- One command owns each transition; stage is a projection.
- State, invariant, and mandatory audit commit atomically.
- Provider work runs after commit.
- Dangerous commands, jobs, imports, notifications, reversals, and assistant confirmations are idempotent.
- Default-deny capability + resource scope + duty authorization.
- Server-authored navigation/breadcrumbs; UI is not authorization.
- Private, checksummed, versioned, quarantined files.
- Typed business conflicts and truthful degraded states.
- Manual scheduling and manual scoring remain complete fallbacks.
- Models/workers propose; they never write authoritative state.
- Accessibility and responsive behavior are acceptance requirements.
- Legacy migrations, routes, controllers, and databases never become target runtime dependencies.

## 8. Future-feature preparation without premature coupling

### 8.1 Shared AI boundary

The target reserves one provider-agnostic boundary for:

- RAG policy/own-data answers;
- typed system assistance;
- scheduling proposals;
- OMR/model proposals;
- future user/staff assistance.

E0–E2 may provide ports, envelopes, feature flags, degraded slots, authorization, audit, idempotency, and frontend placeholders. They must not select a provider or quietly install the full AI stack.

The current E1 OpenSpec does not fully trace the roadmap’s AI-port/degraded-slot requirement. This must be reconciled before E1 is declared fully closed.

### 8.2 Laravel AI SDK

Status: `E3 ADR/SPIKE REQUIRED`.

No current document authorizes a specific Laravel AI SDK integration. The future ADR must define:

- SDK/package and version;
- providers and routing;
- secrets/configuration;
- streaming and cancellation;
- tool/action calling;
- retries/timeouts/rate and cost limits;
- logging/redaction;
- test doubles;
- outage behavior;
- data residency/privacy;
- migration/removal path.

### 8.3 Embeddings and vector storage

Optional `pgvector` is reserved, not approved.

The future embedding ADR must define:

- policy corpus and ownership;
- chunking/versioning;
- embedding model and dimension;
- local versus remote computation;
- PostgreSQL extension/version;
- vector column/index strategy;
- RAG versus BGE vector ownership;
- reindex and stale-version rules;
- authorization-before-retrieval;
- backup/restore/privacy evidence;
- scale trigger for an external vector store.

### 8.4 Nexi

“Nexi” is not yet present as an approved product contract.

Before implementation, add a bounded persona specification:

- approved name and disclosure that Nexi is an assistant;
- tone, vocabulary, refusal, uncertainty, and escalation language;
- allowed actors and contexts;
- text-first behavior;
- character/PNG assets and states;
- reduced-motion and static-frame fallback;
- no authority conveyed by animation;
- localization and accessibility;
- feature flag and disabled/degraded presentation.

Nexi must never become a hidden authorization or database path.

### 8.5 Typed assistant actions

Before tool-calling or system assistance, define a finite catalog:

- `action_key`;
- underlying application command;
- actor/capability/scope/duty;
- input schema;
- preview;
- confirmation/re-authentication;
- idempotency key;
- audit event;
- allowed refusal/error states;
- equivalent frontend action;
- automated tests.

No generic “execute arbitrary action” facility is allowed.

### 8.6 OMR, YuKee, BGE, and inference worker

All remain human-in-the-loop and gate-controlled.

Required before E3:

- authenticated/versioned worker envelope;
- timeout/retry/idempotency contract;
- no direct database mutation;
- approved OMR input and ground truth;
- confidence/abstention/review workflow;
- YuKee artifact manifest, checksum, loader, and deterministic fixtures;
- BGE input corpus, provenance, and feature flag;
- manual/counselor fallbacks;
- reproducible pass/fail evidence.

The current YuKee “resolved” versus missing-artifact evidence is contradictory and must be reconciled.

### 8.7 Scheduling assistance

E2 implements complete manual scheduling. E3 may add proposals after:

- hard/soft constraint schema;
- objective and feasibility definition;
- fixtures for feasible/infeasible cases;
- proposal DTO;
- human confirmation;
- mapping to ordinary scheduling commands;
- audit and refusal/degraded behavior.

### 8.8 Program Head

**Updated 2026-08-06 — D-087:**

- program-scoped aggregate and authorized applicant-record views;
- **may mutate program quotas** for assigned program(s);
- Guidance Counselor (Test Administrator) may also adjust quotas with audit + Program Head communication seam;
- no raw answer sheets;
- no confidential conversion tables;
- no admission Accept/Dismiss or score/release writes.

Before quota UI ships: metric dictionary, “matched” definition, aggregation/suppression, cross-program denial Pest.

## 9. Readiness blockers

### BLOCK-01 — Product contract is not promoted ~~CLOSED 2026-08-05~~

GF-03 remains draft/review-required. Create and approve one formal PRD or explicitly promote GF-03 plus the extracted requirement catalog.

### BLOCK-02 — No user-value epics/stories

Formal coverage is 0 of 75 FRs. Roadmap phases and OpenSpec checklists are not substitutes for user-value stories.

### BLOCK-03 — UX is principled but not page-implementable

Missing artifacts:

- route/page catalog;
- phase-scoped page inventory;
- per-page state matrix;
- breadcrumb catalog;
- modal/confirmation catalog;
- import experience contract;
- responsive/browser/device acceptance;
- measurable frontend/performance budgets.

### BLOCK-04 — Legacy parity is sampled, not exhaustively traced

The existing feature/UX references are summaries. Complete the required row shape against the snapshot manifest and legacy runtime.

### BLOCK-05 — Authority/status contradictions

Known defects include:

- literal omission-marker corruption in three active/supporting and three historical documents;
- stale E0/E1 status snapshots;
- (resolved E1b) presentation stack is Inertia v3 + Svelte 5 + shadcn-svelte; ADR-002 text must stay aligned;
- GF-24 omitted/placeholder content in its differentiator section;
- E1 OpenSpec omission of the roadmap AI-seam item;
- unresolved offline phase wording;
- YuKee evidence contradiction;
- draft/proposed artifacts cited as if promoted.

### BLOCK-06 — Future-feature implementation contracts are absent

Nexi, SDK/provider selection, embeddings, typed actions, worker protocol, scheduling proposals, and Program Head metrics are intentionally not ready. Their seams may be reserved; implementation requires separate decisions, spikes, ADRs, and OpenSpecs.

## 10. Ordered continuation plan

1. ~~Reconstruct corrupted active/supporting documents~~ — done for GF-00/README/GF-24; historical plans quarantined.
2. ~~Close E1 live Compose reset/seed + `/login`→`/staff` spot-check~~ — done 2026-08-04; optional: reconcile E1 OpenSpec AI-seam claim if still listed on roadmap.
3. Promote one formal product contract (targeted `bmad-prd`, not full BMAD restart) containing the extracted FR/NFR/BR set.
4. Exhaustive legacy archaeology into the parity ledger (setup bars, breadcrumbs, imports, missed-exam policy, notifications, Program Head).
5. Produce E2 route/page/breadcrumb/modal/state/performance catalog (stories connect UI to FRs here).
6. Create user-value E2 epics/stories with complete FR/BR + legacy disposition coverage.
7. Create `e2-connected-admission-release-slice` OpenSpec from those stories.
8. Fresh implementation-readiness audit; require complete coverage and no open P0.
9. Only after PASS, implement E2 in `system/`.
10. Prepare R4 evidence and one separate E3 OpenSpec per promoted differentiator (Nexi/AI/embeddings included).

Permanent process reminder: [`project-context.md`](project-context.md) (includes §3.7 developer-ready seeded data on every feature `HANDOFF.md`). OpenSpec remains the code handoff; BMAD is used only to close planning gaps.

## 11. Story readiness contract

Every story must name:

- user outcome;
- FR/NFR/BR IDs;
- legacy evidence and disposition;
- actor/capability/scope/duty;
- domain owner and command/query;
- migration/constraint when first needed;
- route/page/component and breadcrumbs;
- all applicable page states;
- dialogs, toasts, notifications, and truthful wording;
- transaction/locking/idempotency/audit;
- after-commit effects and degraded fallback;
- correction/reversal/retry behavior;
- accessibility/responsive requirements;
- Given/When/Then happy, denied, invalid, conflict, and recovery criteria;
- Pest/Vitest/Playwright/operations evidence;
- no dependency on a future story.

## 12. Stop rules

Stop implementation and return here when:

- a legacy behavior has no disposition;
- a business rule is inferred only from code and needs policy approval;
- a page has no route/state/breadcrumb/authorization contract;
- a command has no owner, invariant, idempotency, or audit contract;
- a provider is added before its gate/ADR;
- Program Head scope or a metric is undefined;
- Nexi or another assistant action has no allowlisted typed command;
- a future feature is inserted into E2 to “prepare” it beyond ports/seams;
- tests cannot point back to requirements and acceptance.

## 13. Current checkpoint

### Verified
- Staff personnel lifecycle scaffolded (`/staff/personnel`, `hr_administrator`, deactivate/activate) — evidence: [`greenfield-foundation/evidence/staff-personnel-lifecycle-evidence.md`](greenfield-foundation/evidence/staff-personnel-lifecycle-evidence.md); OpenSpec archived PASS.
- Admission attempt detail P-REG-02 scaffolded (`/admissions/{id}`, request-correction vs in-place correct) — evidence: [`greenfield-foundation/evidence/admission-attempt-detail-evidence.md`](greenfield-foundation/evidence/admission-attempt-detail-evidence.md).
- E2 connected-slice command spine scaffolded in `system/` with Pest feature evidence (admission→release); see [`greenfield-foundation/evidence/e2-connected-slice-evidence.md`](greenfield-foundation/evidence/e2-connected-slice-evidence.md).
- E2 planning gate PASS (2026-08-05): PRD, slice traces, page/state catalog, stories, active OpenSpec `e2-connected-admission-release-slice`.
- Formal product PRD promoted: [`prd.md`](prd.md) (GF-03 PROMOTED; Tier A + REQ-VS cut-line).

- Isolated `system/` Laravel/Svelte/PostgreSQL/Compose target exists (E1b UI kit + gallery).
- Staff UI kit **locked** (tokens, management-list chrome, Nexi wrap-island assist): [`greenfield-foundation/evidence/ui-kit-locked-start-set.md`](greenfield-foundation/evidence/ui-kit-locked-start-set.md) §4.1.
- E0 scaffold evidence exists.
- Permanent agent work process published: [`project-context.md`](project-context.md) + [`.cursor/rules/nexiam-greenfield.mdc`](../.cursor/rules/nexiam-greenfield.mdc) + Boost MCP [`.cursor/mcp.json`](../.cursor/mcp.json).
- E1 staff authentication, capability helper/seam, actor scope, and reference catalogs have automated + live Compose evidence (`foundation:reset`, rebuild app image, `/login`→`/staff` on `:18000`).
- Spatie Permission is persistence/helper only; GF-21 remains decision authority.
- Exam session scheduling OpenSpec (`exam-session-scheduling`) — **Option A session booking_mode PASS** (staff sessions + Unpublish/Cancel; per-session Open/Close reservation; applicant Reserve→Assignment `applicant_reserved`; no SlotPlan/soft holds; publish notify seam; Pest matrix; Compose reset). Evidence: [`greenfield-foundation/evidence/exam-session-scheduling-option-a-evidence.md`](greenfield-foundation/evidence/exam-session-scheduling-option-a-evidence.md).

### Still open

- Next pipeline after scheduling: **AI assistant foundation** (Schedule Assistant apply path remains out of the scheduling cut)
- E2 exit evidence G2–G4/H4 (parked; journey code remains in `system/`)
- Exhaustive legacy parity ledger (imports, missed-exam policy, …)
- optional E1 OpenSpec AI-seam alignment vs roadmap wording

### Current verdicts

- Planning coherence: **PASS** for E2 slice planning (2026-08-05); personnel + attempt detail + institutional setup archived PASS; scheduling reservation cut PASS (OpenSpec still active until archive decision).
- E0 implementation evidence: **PASS**.
- E1 identity/reference: **PASS** (personnel UI complete).
- E2 delivery PASS: **NOT YET** (OpenSpec parked incomplete; scheduling cut complete pending archive).
- Pilot/production clearance: **NOT GRANTED**.
