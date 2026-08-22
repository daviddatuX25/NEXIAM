# NEXIAM Greenfield Master Reference

**Purpose:** Single human entry point for rebuilding the complete legacy product as a scalable greenfield system
**Status:** ACTIVE NAVIGATOR — E0/E1/E1b PASS; PRD promoted; **E2 scoring conversion shipped 2026-08-11** (confidential conversion-table engine — `scoring_method=conversion_table` + range rows, frozen `converted_score` snapshot, encrypted at rest, `Setup/AptitudeScoring` editor; normalized/formula basis REJECTED per founder; `notifications-delivery-outbox` parked DEFERRED in archive); **grading workflow archived PASS 2026-08-11** (connected admission-to-release code spine complete — grading sessions → per-applicant scoring → finalize → roster eligibility gated on attendance+submission; next: result-sheet printing pipeline + E2 exit evidence G2–G4/H4 + legacy parity ledger); **OMR capture first cut shipped 2026-08-12 + real OMRChecker provider active in dev 2026-08-13** (native SYN-OMR-ONE-PAGE bundle → derived review manifest → signed isolated worker → aligned encrypted evidence → staff review/binding; deterministic fixture generator packaged; full suite 282 passed); applicant-choose-slot archived PASS 2026-08-11 (Nexi applicant slot skill); ta-capability-delegation PASS 2026-08-10 (TA grant/revoke + rank hierarchy + password ceiling + profile page founder locks); proctor-duty PASS 2026-08-10; OS8 exam Assistant PASS 2026-08-10; exam-session-scheduling archived PASS 2026-08-10; institutional-setup-readiness PASS 2026-08-04; staff-personnel archived PASS 2026-08-05; admission-attempt-detail archived PASS 2026-08-06; E0/E1 foundation + ui-kit archived PASS 2026-08-05.
**Work process (permanent):** [`project-context.md`](project-context.md)
**Current readiness report:** [`greenfield-foundation/evidence/implementation-readiness-report-2026-08-10.md`](greenfield-foundation/evidence/implementation-readiness-report-2026-08-10.md) (Nexi architecture ready-for-OpenSpec; implementation not-ready for provider); prior E2 planning: [2026-08-05](greenfield-foundation/evidence/implementation-readiness-report-2026-08-05.md)
**Phase roadmap:** [`MASTER-DELIVERY-ROADMAP.md`](MASTER-DELIVERY-ROADMAP.md)
**Target runtime:** [`../system/`](../system/)
**Legacy runtime:** [`../legacy_codebase/application/`](../legacy_codebase/application/) — read-only behavior evidence
**Cursor always-on rule:** [`.cursor/rules/nexiam-greenfield.mdc`](../.cursor/rules/nexiam-greenfield.mdc)
**Boost MCP:** [`.cursor/mcp.json`](../.cursor/mcp.json) → `system/bin/boost-mcp.sh` (reload Cursor MCP after first use)
**Teammate handoff (Taglish):** [`HANDOFF-TEAMMATE.md`](HANDOFF-TEAMMATE.md) — para sa nagpapatuloy ng development habang wala ang founder

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

**Updated 2026-08-15 — D-ROLE-05/D-080/D-087/D-089–D-093:**

- Registrar alone Accepts/Dismisses applications and gates exam assignment.
- Program Head receives assigned-program metrics and canonical Potential/Upcoming/Follow-up/Enrolling/Enrolled lists; Potential is grounded in initial preferences and Guidance pool but remains minimal.
- Protected records require released results plus an applicant-acknowledged current selection or explicit targeted follow-up for the PH program; preference/pool membership alone remains minimal. Every open is audited and applicant-visible; other program names/options and cross-program choice history never serialize.
- **May mutate own-program quotas**; authorized Guidance/TA may also adjust with audit + PH notice.
- Applicant choice or Guidance's normal advised default creates the provisional quota reservation; Registrar may record sheet-based acknowledgement while starting `enrolling`.
- Normal processing needs no PH system approval. Direct PH selection decline is pre-`enrolling`; assigned PH resolves an already-open edge follow-up during `enrolling`, and that follow-up blocks only `enrolled`.
- PH↔Guidance internal and current-case PH↔applicant support threads are bounded; no general inbox/marketing.
- No raw answer sheets, bubble scans, confidential conversion tables, unrelated notes, or cross-program protected records.
- Registrar-only `enrolling` records sheet submission/processing even with open follow-up; `enrolled` requires terminal PH edge follow-up plus signed-paper verification. Registrar records paper final-program truth even outside pool/quota, leaves capacity unchanged, and reports variance.
- One universal digital/printed applicant QR resolves an opaque applicant-attempt locator into a role-neutral action hub. Registrar, Guidance/TA, and PH receive only destinations authorized by ordinary capability/duty/scope; QR encodes no feature destination, grants no authority, and never mutates automatically.
- The existing consultation workspace owns the applicant-specific admission-support backbone: real released-score charts/table, preferences, structured consultation evidence, independent future-analysis regions, and human decision/pool/follow-up/history. No engine ships in this cut; YuKee artifact/K-Means and transparent deterministic/rule-based plugins remain separate later integrations under D-093, with BGE separately deferred.

Planning authority: [`2026-08-15-admission-model-program-head`](../openspec/changes/2026-08-15-admission-model-program-head/). Implementation must wait until it becomes the sole active code change.

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
- Exam session scheduling — **Option A session booking_mode PASS**; OpenSpec archived `openspec/changes/archive/2026-08-10-exam-session-scheduling/`. Evidence: [`greenfield-foundation/evidence/exam-session-scheduling-option-a-evidence.md`](greenfield-foundation/evidence/exam-session-scheduling-option-a-evidence.md).
- Nexi AI assistant architecture package FINAL 2026-08-10 — spine/visual/readiness/junior guide + readiness report [`greenfield-foundation/evidence/implementation-readiness-report-2026-08-10.md`](greenfield-foundation/evidence/implementation-readiness-report-2026-08-10.md); AD-3 pack-as-lens hardened 2026-08-10.
- AI assistant foundation OpenSpec 1 — **PASS** archived `openspec/changes/archive/2026-08-10-ai-assistant-foundation/`; evidence [`greenfield-foundation/evidence/ai-assistant-foundation-openspec1-evidence.md`](greenfield-foundation/evidence/ai-assistant-foundation-openspec1-evidence.md).
- AI capability kernel OpenSpec 2 — **PASS** archived `openspec/changes/archive/2026-08-10-ai-capability-kernel/`; evidence [`greenfield-foundation/evidence/ai-capability-kernel-openspec2-evidence.md`](greenfield-foundation/evidence/ai-capability-kernel-openspec2-evidence.md).
- Nexi run/usage OpenSpec 3 — **PASS** archived `openspec/changes/archive/2026-08-10-nexi-run-and-usage-governance/`.
- Nexi knowledge + policy RAG + applicant Inquiry OpenSpec 4–7 — **PASS** archived 2026-08-10.
- OpenSpec 8 exam Assistant — **PASS** archived `openspec/changes/archive/2026-08-10-nexi-exam-session-assistant/` (founder skill/capability lock in `docs/project-context.md` §0.1).
- Exam proctor duty assign — **PASS** archived `openspec/changes/archive/2026-08-10-exam-proctor-duty-assign/`; evidence [`greenfield-foundation/evidence/exam-proctor-duty-assign-2026-08-10.md`](greenfield-foundation/evidence/exam-proctor-duty-assign-2026-08-10.md).
- TA capability delegation — **PASS** archived `openspec/changes/archive/2026-08-10-ta-capability-delegation/`; evidence [`greenfield-foundation/evidence/ta-capability-delegation-2026-08-10.md`](greenfield-foundation/evidence/ta-capability-delegation-2026-08-10.md). Includes founder locks: rank hierarchy (support < clerk < office lead < HR), password-reset ceiling (HR + office leads), profile page `/staff/profile`.
- Applicant slot skill — **PASS** archived `openspec/changes/archive/2026-08-11-applicant-choose-slot/`; Nexi applicant slot skill `applicant.choose_slot@1` over the existing manual reservation core (kernel rows, pack, confirm path).
- E2 grading workflow — **PASS** archived `openspec/changes/archive/2026-08-11-grading-workflow/`; manual grading core (idempotent open, roster, transactional multi-domain scores, finalize completeness + version lock + immutable). Evidence: [`greenfield-foundation/evidence/grading-workflow-2026-08-11.md`](greenfield-foundation/evidence/grading-workflow-2026-08-11.md) (14 tests / 201 assertions + `GradingDemoSeeder`).
- Grading roster eligibility — **PASS** archived `openspec/changes/archive/2026-08-11-grading-roster-eligibility/`; attendance + submission gate the roster (`GradingRoster`), close guarded on attendance completeness, absent/no-submission excluded from scoring. Evidence: [`greenfield-foundation/evidence/grading-roster-eligibility-2026-08-11.md`](greenfield-foundation/evidence/grading-roster-eligibility-2026-08-11.md) (22 tests / 328 assertions + seeder).
- Active OpenSpec — **`omr-scoring-capture`** (`openspec/changes/omr-scoring-capture/`): Phase A online Manual + Auto fake-provider vertical slice **IMPLEMENTED and browser-proven 2026-08-12**. Phase B is decision-locked and junior-ready: immutable OMRChecker-native bundle as the sole detection-geometry authority → activation-derived NEXIAM review manifest/projections → answer-key-free envelope v2 with null unqualified confidence and aligned-sheet evidence → durable per-sheet claims → isolated pinned worker/PHP adapter → benchmark and fail-closed development qualification.
- Review UX checkpoint — protected identity crop, per-area detected/final score projection, per-item evidence, camera selection, and manual-editor handoff are browser-proven. The existing layout is preserved; Phase B adds only truthful provider/queue/degraded states.
- **2026-08-14 checkpoint (observed):** real OMRChecker engine ACTIVE in dev (native bundle → derived manifest → signed worker → aligned evidence → staff review, `OmrScoringCaptureTest` + hardening + multipage suites green). **Multipage capture UI shipped** — "Set page X of N" stepper, per-card page chips, Resume set / audited Retake page (`ReplaceOmrSheetPage`), review page tabs with per-page detected identity, structured confirm errors, reject-batch/retry-scan actions. **OMR configuration surfaces shipped** (`omr-configuration` change): intake, template show, compliant template builder (writes native `template.json`, flows through unchanged pin→verify→derive→activate, `SaveAuthoredTemplateDraft`), booklet authoring (`SaveAuthoredBookletVersion`, key digest only, contiguous global 1–200 positions), pair activation page (typed compatibility errors), retirement with audit (`RetireOmrConfiguration`), Setup hub OMR readiness category. **Async print pipeline shipped** (`omr-printing-pipeline`): `print_jobs`, `PrintRenderer`/DomPDF, `RenderBlankOmrSheets`, checksum-verified private downloads, `BundleRenderer` as single visual source; v4 long-bond 2-page bundle is the standard (kit `omr-test-kit/answer-sheet-longbond-v4.pdf`). Browser-proven full loop: builder → draft template → booklet authoring → pair activation → capture pair selection; builder→save→show→activate exercised end to end 2026-08-14.
- **2026-08-15 layout-solver + blueprint builder shipped (browser + real-engine verified):** server-owned `OmrTemplateLayoutSolver` (semantic blueprint → canonical fieldBlocks; 2/3/4 columns, auto-flow, breaks, validated pins; canonical hashing) wired into save + preview with `hash(preview)==hash(saved)` proven; durable save boundary (private-disk staging, row-lock, retention sweep, `NativeBundle::load` durable-first fallback); blueprint metadata columns; builder rewritten (Layout/Advanced modes, server projections, typed diagnostics, section reorder/range/breaks); recognition integration test + **real-engine smoke**: OMRChecker succeeded on solver-generated fixtures (applicant `2345678901` detected, confidence null per qualification gate). Full suite **361 passed / 1 skipped / 22,519 assertions** on the baked image. OpenSpec `omr-layout-solver` valid; see [`docs/omr-layout-solver-verdict.md`](omr-layout-solver-verdict.md).

- **2026-08-15 ADOPTED refactor contract:** [`docs/omr-layout-solver-verdict.md`](omr-layout-solver-verdict.md) — server-owned `OmrTemplateLayoutSolver` (semantic blueprint → canonical fieldBlocks; 2/3/4-column auto-flow, break-first with validated pins; canonical hashing; typed diagnostics; blueprint in adjacent metadata columns, never `template.json`/`provider_config_json`; durable content-addressed bundle staging replacing the in-`app/` dir + `exec('rm -rf')` save boundary; preview/save share one solver; one active OpenSpec change — currently four OMR changes in progress, to be consolidated). Spot-checked claims verified (defaultConfig hardcodes `[2060,3144]`; `max(version)+1` allocation; per-page dims fallback).

- **2026-08-15 checkpoint (observed):** margin-safe answer-sheet generation closed. Builder enforces the 80px printable margin (`MARGIN=80`, `BUBBLE=56`, column bands) with typed refusal and live issue markers; SVG preview matches the renderer identity-grid convention (labelsGap across x, bubblesGap down y). Printed labels clear the empty shade bubbles with explicit visual bounds; `BundleRenderer` computes a collision-free name box above the topmost field labels when space exists, uses a compact-sheet side fallback when the identity band is tight, and hides it only when no safe placement exists; calibration strip sits below the border in the bottom margin; footer lines stack inside the border. Vision-verified on v4 + v5 preview PDFs (every page) — no label/bubble overlaps, no box/border/calibration crossings. Seed data extended (user-approved reset): v5 2-page birthdate template + booklet C active, booklet B draft, retired v1 pair, demo capture batch #1 with needs-review 2-page sheet (`1234567890` / `01012010`), pending_pages 1-of-2, failed_terminal — all visible at the current seeded route `/grading/sessions/7/omr`. Seeders are idempotent (re-run = no-op; batch self-heals). Bugs found + fixed: per-page image URL missing `sheet` param; `OmrCaptureSheet::$fillable` missing `detected_birthdate` (silent loss); `OmrPinnedSnapshot::manifestExpectations` missing the `BIRTHDATE_` scorable exclusion. Regression tests added (`OmrMultipageTest` image-URL + birthdate conflict/invalid merge tests); fake detector gains `omr.fake.birthdate_by_page`. Focused OMR suites: **63 passed, 1 skipped, 18,332 assertions**; frontend geometry tests: **3 passed**; preview PDFs: **4 pages visually clean**.
- **2026-08-15 downloaded-artifact proof:** authenticated downloads for print jobs `#1` (v4) and `#2` (v5) returned `200 application/pdf`; checksums matched stored artifacts (`76d34b618207b8960ccd8ac42c5d88f0b74ee2655d151805692e202dda2ae95d`, `5f2f81920f195e242663969c7523738e82564c47ebd2cd024173ae2573e5cd6f`). `pdftoppm` rasterized two pages per PDF; all four downloaded pages were visually clean, and per-page raster hashes exactly matched the corresponding preview PDFs.
- **2026-08-15 queue-worker proof:** a queued print job initially hit `bundle_invalid` because the running queue image was stale; rebuilding `app` and `queue` from current source and restarting Compose made the job `ready` (`progress_done=1`). Job `#3` authenticated download returned `200 application/pdf` (1,044,777 bytes; checksum `e39d59c5b33a50b94b15aea8e6fa7af28803637694415d69d6bdd318baf2119b`), with raster page hashes equal to the direct v5 artifact.

- **OMR configuration usability cut shipped 2026-08-14** (`omr-configuration-usability`, browser-verified): breadcrumbs everywhere, journey-hub index, **preview == print** (`RenderBundlePdf` shared service + version preview + unsaved-draft preview), **print overlays** (`print_overlay_json` render-only — title/header/footer/name-box notes; engine seam unchanged), booklet key preview + draft pre-population + audited copy-to-draft + bulk key paste, builder draft hydration + full-preview PDF, inline retire cascade panels, and the **optional bubbled birthdate identity grid** (founder lock extension recorded in `.ai/rules/`). `OmrConfigurationUsabilityTest` (7 tests) green; full suite green at ship.
- **2026-08-15 OMR closeout evidence:** the deployed blueprint builder now renders the server-owned projection as a real answer-sheet SVG with the dashed 80px printable-margin guide; the previous empty preview was traced to the stale `projection.pages` path and corrected to `projection.layout.pages`. Fresh authenticated v4/v5 preview PDFs returned `200 application/pdf` and all four pages were raster/vision-checked: header/title/page-note text clears the top frame, v4 identity/question columns remain inside margins, v5 footer remains inside the frame, and calibration text/bar remain below it without crossings. Focused OMR suites passed **55 tests / 19,593 assertions / 1 intentional skip** after the final renderer and builder fixes; frontend `npm run build` passed with existing Svelte warnings. Archived OMR layout-solver task 8.3 is complete; provider qualification remains intentionally open.
- **2026-08-15 final frame regression:** the shared renderer frame clears the full header stack and first field labels; per-page notes use an explicit line box, and the builder mirrors the same clearance. Fresh v4/v5 preview PDFs remained `200 application/pdf`; all four pages were rasterized and vision-checked after the fix with no note/frame, label/bubble, footer, name-box, or calibration crossings. `OmrConfigurationUsabilityTest` + `PrintPipelineTest`: **17 passed, 1 skipped, 169 assertions**.
- **2026-08-16 compact one-page OMR profile closeout (updated):** the server solver's six-column profile uses 40×40 bubbles, 52px option pitch, answer-flow origins `[120,452,784,1116,1448,1780]` at y=`940`, six 25-question domains, and one long-bond page. Native identity is applicant-number-only at compact origin `[200,300]`; birthdate and printed name are render-only manual header fields. Configurable EDQ columns now support `reserve_native_identity: true`: `BundleRenderer` computes the native identity footprint before placing exam/instruction/name/birthdate content, and the builder SVG mirrors the same origin. Fresh profile PDF (`200 application/pdf`, 648,260 bytes) was rasterized and visually checked: EDQ/header text, native identity grid, aligned manual name/birthdate boxes, six answer columns, 150 rows, bottom margin, and no footer/calibration copy. Database evidence after the founder's focused-data decision: one active profile template/booklet pair, one open batch, and one complete `needs_review` sheet bound to one examinee; pending/failed states are opt-in via `OmrDemoMultiStateBatchSeeder`, and provider confidence remains null until qualification. Final proof: full suite **401 passed / 1 skipped / 22,970 assertions**; focused print regression **10 passed / 65 assertions**; setup/profile suites **12 passed / 1 skipped / 243 assertions**.
- **2026-08-16 capture-readiness evidence:** `GradingDemoSeeder` now self-heals the default open profile batch to one complete `needs_review` sheet bound to one examinee; `OmrDemoMultiStateBatchSeeder` retains the pending/failed states for opt-in capture-state testing. Browser verification at `/grading/sessions/7/omr` confirmed camera-unavailable preflight with upload fallback, the focused gallery state, bound roster identity, private evidence, `Unqualified` confidence at review levels, and truthful OMRChecker provenance. Physical acceptance remains open: baseline, blank, double-mark, erase, rotation, shadow, fold, and wrong-paper scans require real sheets and a camera/scanner; synthetic/fake-provider evidence is not production qualification.
- **2026-08-18 OMR fix refresh:** focused reset/reseed now verifies exactly one profile template, one active booklet, one active pair, 150 authored questions, one capture batch, one capture sheet/page, and one roster-bound `needs_review` examinee; browser review at `/grading/sessions/7/omr` opens private evidence with truthful `Unqualified` confidence and OMRChecker provenance. The LAN/non-secure-context UUID regression is covered by the 3-test fallback suite and browser OMR reload with zero page/console errors. EXIF, manual-camera, and native aligned worker smoke fixtures all complete successfully. Physical acceptance remains intentionally open.
- **2026-08-18 real-photo OMR blocker (corrective evidence, refreshed):** the labeled 3000×4000 phone photo still disproves automated readiness: the exact replay fails preprocessing, and the temporary `0.30` alignment hypothesis reaches OMRChecker but returns false marks/ambiguous items instead of the declared `1=C, 2=B, 3=C, 4=D, 5=D`, leaves applicant `2000600002` unresolved, and is not qualified. The corrective cut now keeps selected review props synchronized through `PROCESSING → NEEDS REVIEW`, stores the clean unannotated aligned raster, renders typed NEXIAM option overlays and row/name crops, fails closed on option-crossing shifts, and emits raw-value/local-mean/global-threshold diagnostics. Latest reset/reseed remains exactly one profile pair, one batch, one sheet/page, and one roster-bound examinee; queue is healthy, but no-manual-intervention recovery across reset remains unproven. **Open blockers: physical matrix 7.7; queue proof 9.1; recognition/protected regression 9.7–9.9; full qualified operator loop 9.11.**
- **2026-08-20 canonical OMR authoring checkpoint (implementation in progress):** active OpenSpec `omr-canonical-template-authoring` now has the additive draft/asset/marker-profile/blank-instance schema; five source-adapter boundary with hostile preflight; server-authoritative photo quadrilateral rectification; exact-size 300-PPI composition; fixed overlays; constrained native-SVG mapping; item-level Test capture evidence; atomic compile; version-scoped physical/activation gates; signed blank-instance QR, chunked ZIP printing, manual recovery, authorization/audit/idempotency seams; and five clickable seeded drafts at `/setup/omr/templates/canonical`. Focused proof: **48 passed / 209 assertions / 1 intentional PostgreSQL-only constraint skip**, worker rectification + declared-option evidence scenarios passed, frontend production bundle passed, and the nine-stage browser surface was exercised with forward gates. Marker profiles and supplier drafts remain intentionally unqualified; no physical qualification or supplier activation was seeded or inferred.
- **2026-08-22 canonical Sheet Designer parity hardening:** the Stage-1 studio now shares typed path-filtered component/overlay palettes, exposes margins/marker patterns/QR reservation in Sheet Designer, filters roll/question cards from Upload Document, resets all canvas state on mode changes, persists selected upload `source_kind`, exposes editable bubble/row pitch, and carries spacing into Stage 3. Browser proof confirms PNG ingestion reaches Layout & Overlays without `source_kind_mismatch` or stale lock conflicts; focused proof is 32 frontend tests plus 47 OMR PHP tests / 229 assertions. Production physical qualification remains open.
- **2026-08-22 compiled-decoration/history hardening:** Sheet Designer compiled HTML now includes selected-pattern fiducial preview markup and a server-authoritative `blank_instance_qr` reservation; both authoring paths expose a shared QR size control. The builder now has bounded snapshot undo/redo with coalesced edits, keyboard shortcuts, and autosave-preserving restore. Browser proof confirms exported fiducials/QR reservation, 34 mm QR sizing, toolbar undo/redo, keyboard undo, and zero page/request errors; frontend suite is **35 passed**. Production physical qualification remains open.
- **2026-08-22 functional Sheet Designer components:** candidate fields now separate label and writing area with selectable text/number/date/multiline input kinds and box/dotted/underline shapes; Custom Input Box is palette-owned; backgrounds are independent from typography; component layers are draggable and raise/lowerable with persisted z-order; sidebar copy is reduced to draft code plus tooltips. Browser proof: two functional input blocks, compact Layers list, custom inspector, and zero page/request errors; frontend suite **38 passed**.
- **2026-08-22 Stage 3 bubble correction:** Stage 3 now derives green wrapper bounds from the actual rendered bubble/label footprint and uses the same 10.5px bubble row footprint for row gaps; Row Pitch 6.8→12mm expands the wrapper 422.6→731.7px while keeping the final bubble inside. Target fiducials are absolute at all corners; ArUco has a distinct grid pattern; blank canvas clicks clear selection.
- **2026-08-22 autosave refresh recovery:** studio saves now run through a serialized promise queue, preventing same-lock concurrent PUTs. Browser proof captured one `200` studio save for Document Header + Question Grid, refreshed Draft 2, and recovered both components with zero page errors. The typography control runtime import was also repaired.
- **2026-08-22 Excalidraw-style Sheet Designer controls:** `selectedComponentIds` now supports Shift-click multi-selection on canvas/layers, group drag, Delete/Backspace removal, Ctrl/Cmd+D duplication, and Alt/Option-drag copy placement; locked components stay selectable but immobile/non-duplicable. Browser proof covered refresh persistence, native-input shortcut guards, locked duplication guards, and zero current page/request failures.
- **Admission support, program choice, and Program Head — IMPLEMENTED 2026-08-15** (`2026-08-15-admission-model-program-head`, sole active change after founder handed off the OMR layout solver `e586e86`): 10 new capabilities with parity tests; schema/migrations + 9 models (immutable pools/options, selections, follow-ups, declines, enrollment stamps, record threads); `CommandIdempotency` ledger (actor+command+aggregate namespace, canonical hash, replay/conflict); lock protocol (attempt root → rows → quota ascending) with FCFS quota guard and partial unique indexes (one non-terminal selection, one open follow-up); 14 actions (pool set, select/ack/transfer/cancel, follow-up open/resolve/decline, thread message/read, enrolling/enrolled/reverse stamps with paper-final program + quota variance); **UX revision (founder 2026-08-15): `/consultation` is a DASHBOARD** (stat tiles, scan/search quick actions, today's schedule grouped by consultation session) with the per-applicant workspace on a **shared inner page** `/consultation/applicants/{attempt}` (Guidance tabs + PH Record/Threads tabs + Models tab with 2 reserved engine slots in truthful `no_provider` states; Registrar stays on its dedicated eligibility page); **permanent deterministic QR** (HMAC over attempt id — same code forever, no refresh/expiry, print recommended, usable across scan/registrar/consultation front desk) and **scan → action modal**; protected-view audit + 24h notice dedupe (fail-closed); `AdmissionSupportDemoSeeder` (3 journeys + today consultation session for the dashboard, standalone after the test-suite slowdown was caught and reverted); fixed: Laravel's SQLite FK-add rebuild silently dropped raw `DB::statement` partial indexes, and `crypto.randomUUID` crashing pages outside secure contexts (`newIdempotencyKey()` fallback). Tests: AdmissionSupportCapabilityTest (6), ProgramSelectionTest (6), AdmissionSupportWorkflowTest (10 incl. dashboard), EnrollmentEligibilityTest (6), release tests — **clean full suite 385 passed / 1 skipped / 22,643 assertions**. One earlier Ai-turn failure was reproduced as a load flake (passes alone and in-order; unrelated to this cut).

- E2 exit evidence G2–G4/H4 (journey code present in `system/`; grading workflow archived PASS 2026-08-11 — write the exit evidence + close out the slice)
- **2026-08-19 result-sheet printing — IMPLEMENTED on isolated branch `feature/result-sheet-printing`** (`openspec/changes/result-sheet-printing/` in that worktree, all 24 tasks complete, suite green): full legacy-parity release printing on the async pipeline — versioned `result_sheet_templates` (HTML + Word/ODT upload, single-active, private checksummed docs, placeholder analyzer with found/missing/extra, live sample preview, `guidance.assessment.catalog` gate); legacy ICAT alias catalog + per-domain raw/rating/pct + `scores_rows` + dual-slot + DOM-safe DOCX macro repair; `result_sheet` print jobs (single + bulk ≤200/copies 1–10, pinned inputs, dedupe, per-item manifest with ready-with-partial, merged stack PDF, stale-retry refusal, typed errors); `result_printed_at` on `result_releases` set idempotently on ready + manual mark/unmark (audited); download audit + `result-sheet:prune` retention; LibreOffice headless inside workers (real SecureCAT ICAT docx conversion proven) with DomPDF for HTML; `PrepareResultDocument` now template-backed with checksummed private artifact; UI-kit pages (Setup › Result Sheet Templates index/create/edit with guide+analyzer+preview; Release › Print Batch with selection/copies/bulk/preview/mark-printed/job-status strip; bulk stack preview); online releases refuse printing. Evidence: 444 passed / 2 env-skips / 23,346 assertions; browser-proven create→preview→analyzer→print→download; full-sheet PDF text contains header, applicant, scores, converted ratings. Open follow-ups: report exports, admission slips, template editor beyond HTML/DOCX, global release_mode toggle.
- **2026-08-15 applicant portal interface — IMPLEMENTED** (`2026-08-15-applicant-portal-interface`, sole implementation change; `2026-08-15-admission-model-program-head` archived PASS): status-spine hub at `/applicant/portal` (server-projected stage cards with `✓ done · ◐ current · ○ upcoming · ⚠ action · ✦ auto`; actions hang off their stage; mode-aware Results — `MODE_ONLINE` artifact with descriptor-safe bars vs `MODE_F2F` claim-at-consultation with zero serialized scores); ✦ Guidance default shown as active projection with optional ack ("This is my choice"); My Application read-only page (own-scope 403); server-authorized applicant nav (8 items) + breadcrumbs; shared `ReservationSlotList`/`ReservationConfirmSheet` across exam schedule + consultation; landing season badge + process strip; login + correction flows land on the hub. Seeds: `ApplicantPortalDemoSeeder` wired into `foundation:reset` (NOT `DatabaseSeeder` — quota reservations would perturb quota-edge tests): `PORT-ONLN`/`PORT-F2F2`/`PORT-DEFL` · birthdate `2003-09-25`. Evidence: Pest `ApplicantPortalInterfaceTest` (9) + updated redirect suites green; Vitest `applicant-portal.test.ts` (8); browser-verified mobile 390 + desktop 1280 (hub spine ×3 keys, f2f blocks scores, ✦ card, My Application, nav, no overflow). One pre-existing failure remains (`ReleaseFlowTest` "release notifies after commit once" — `firstOrFail()` picks seeded EX-AS-1; unrelated to this cut, present in the pre-change tree).
- OMR development work starts by rejecting the duplicate seeded geometry, establishing the immutable native bundle, pinning the minimal headless/overlay patch set, and deriving the NEXIAM review manifest before envelope v2, fixtures, or adapter work. Production activation remains blocked on the approved client sheet/key, representative labeled corpus, production qualification/approvers, retention/legal-hold policy, and operational drills.
- Exhaustive legacy parity ledger (imports, missed-exam policy, …)
- optional E1 OpenSpec AI-seam alignment vs roadmap wording

### Current verdicts

- Planning coherence: **PASS** for OMR Phase B; **implementation evidence** for `2026-08-15-admission-model-program-head` shipped 2026-08-15 (backend + UI + targeted tests; full-suite status in checkpoint). Real-provider/production use remains not authorized.
- E0 implementation evidence: **PASS**.
- E1 identity/reference: **PASS** (personnel UI complete).
- E2 delivery PASS: **IN PROGRESS** (connected admission-to-release code spine COMPLETE — grading workflow archived PASS 2026-08-11; remaining: E2 exit evidence G2–G4/H4 + legacy parity ledger; scheduling + proctor + TA cuts archived PASS).
- Pilot/production clearance: **NOT GRANTED**.
