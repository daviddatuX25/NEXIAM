# NEXIAM Master Delivery Roadmap and Readiness Gates

**Artifact:** NEXIAM-GF-14
**Status:** ACTIVE MASTER ROADMAP — E0 PASS (scaffold evidence recorded); E1/E2 NO-GO until fresh R5 and active E1 OpenSpec
**Purpose:** One whole-project delivery authority: high-level roadmap, architecture spine, phase gates, delivery tracks, parallel-work rules, and readiness scorecard. Detailed contracts stay in GF-01–GF-13 and GF-15–GF-24; this document sequences and governs them.
**Companion navigator:** [`00-catch-up-and-continue-progress.md`](00-catch-up-and-continue-progress.md)
**Implementation handoff:** [`24-bmad-openspec-implementation-package.md`](24-bmad-openspec-implementation-package.md)
**Active executable change:** [`openspec/changes/e0-greenfield-foundation/`](../../openspec/changes/e0-greenfield-foundation/)

## 0. How to use this document

| Need | Use |
|---|---|
| Where are we right now? | [GF-00](00-catch-up-and-continue-progress.md) |
| What was decided? | [GF-13](13-decision-and-batch-approval-matrix.md) |
| What is the whole-project plan? | **This document (GF-14)** |
| What may be implemented next? | GF-24 + the one active OpenSpec change |
| How does a module/command/page work? | GF-17–GF-23 (HLD, LLD, schema, contracts, auth, UX, ops) |
| What did legacy do? | [`docs/legacy-reference/`](../legacy-reference/README.md) — evidence only |

**Authority order for target behavior:**

1. recorded founder decisions (GF-13) and verified institutional evidence;
2. this master roadmap’s phase/gate rules;
3. promoted greenfield contracts (GF-03–GF-12, GF-15–GF-23);
4. the active OpenSpec change;
5. executable tests and runtime evidence;
6. legacy-reference crosswalk and legacy code last.

**D-004:** Older greenfield architecture and implementation-order plans are inputs to a compound merge, not standalone execution authority. Useful detail is reconciled here and into GF-24/OpenSpec; conflicting standalone plans remain historical.

```text
GF-13 decisions + GF contracts + legacy evidence
        ↓
   GF-14 master roadmap (phases, gates, tracks)
        ↓
   One active OpenSpec change
        ↓
   system/ implementation + tests/evidence
        ↓
   Next phase gate
```

---

## 1. Strategic sequence

```text
R0 Preserve evidence and isolate greenfield workspace
  -> R1 Approve recovery authority and product cut-line
  -> R2 Close domain/stakeholder evidence needed by committed scope
  -> R3 Approve domain/role/workflow/data/security contracts
  -> R4 Run bounded high-risk technical spikes
  -> R5 Promote PRD/ADRs/architecture/stories and pass readiness
  -> E0 Create clean all-green scaffold
  -> E1 Build identity/reference-data foundation
  -> E2 Build connected admission-to-release slice
  -> E3 Add spike-passed capstone differentiators
  -> E4 Expand dashboards/reports/communication/offline conditionally
  -> E5 Technical pilot and role-specific TAM preparation/evaluation
  -> E6 Deployment/operations hardening for any approved pilot/production plane
```

Novel capstone risk is moved before full parity reconstruction. Production hardening is not required to call a bounded demonstration coherent, but baseline security, quality, and integrity are never deferred.

### Current position — 2026-08-04

| Verdict | Status |
|---|---|
| Planning coherence | DRAFT/PREPARED — founder dispositions recorded; contracts reconciled at draft level |
| Implementation preparation | **E0 PASS** for scaffold/runtime/quality/operation evidence; **E1/E2 NO-GO** until fresh R5 |
| Pilot/production clearance | **NOT GRANTED** |

Facts:

- `system/` is the isolated greenfield workspace; quality baseline (Pest/Vitest/Playwright/lint toolchain) is green; E0 contains foundation seams only (no E1/E2 domain features).
- E0 evidence report: [`evidence/e0-scaffold-evidence.md`](evidence/e0-scaffold-evidence.md).
- Remaining E0 hygiene before opening E1: deliberate failed-op evidence capture, negative-fixture fail-closed proof, and open `e1-identity-reference-foundation`.
- R4-GO is not passed for E3 differentiator promotion.
- R5-GO for E1/E2 is not passed: GF-18–GF-24 remain review-required drafts until a fresh readiness audit after E0 handoff.
- Immediate authorized work: close the three residual E0 checklist items, then open E1. Do not start E2 domain features yet.

---

## 2. Architecture spine (non-negotiable across every phase)

These invariants bind all later work. Feature stories may not weaken them.

### 2.1 System shape

- Modular monolith first: module-owned aggregates, command/query boundaries, and dependency-direction tests. No cross-module ORM mutation shortcuts.
- Laravel 13 / PHP 8.4 / Inertia 3 / React 19 / TypeScript 5.9 / Tailwind 4 / PostgreSQL 16 as the approved application plane (`system/`).
- Fresh migration history only. Legacy migrations, routes, controllers, and databases are never runtime dependencies of the target.

### 2.2 Integrity and mutation

- PostgreSQL constraints and locks are integrity authority for races (uniqueness, capacity, effective deadlines, version conflicts).
- One application command owns each business transition. Workflow “stage” is a projection, not a second mutable truth.
- Critical commits are atomic: invariant + state + mandatory audit in one transaction. External effects (mail, notifications, provider calls) run only after commit.
- Idempotency is required for dangerous paths: commands, jobs, imports, assistant confirmations, notifications, deliveries, and reversals.
- Typed failures and visible degradation: business conflicts are not generic 500s; provider outage leaves manual core operable.

### 2.3 Authorization, privacy, and audit

- Default deny. Capability + resource scope + contextual duty (for example Proctor assignment). Browser UI is never authorization authority.
- Server-authored navigation and breadcrumbs. Hidden/disabled controls improve usability only.
- Private files with checksums, versions, quarantine, and formula-safe exports. No public artifact URL as authority.
- Audit claims stay within the approved threat wording. Checkpoint failure does not rewrite history.

### 2.4 AI and inference boundary

- One provider-agnostic AI capability boundary for RAG chatbot, scheduling assistance, model/OMR proposals, and future AI-assisted UI (D-008).
- Authorize before retrieval. Separate policy corpus, own-data, and typed-task contexts. Citations and refusal are mandatory for RAG answers.
- Models and workers never write authoritative domain state. They return observations/proposals; authorized human/application commands commit.
- Assistant mutations execute only through the same typed commands and policy gates as the frontend, with confirmation and audit.
- Laravel AI SDK, concrete providers, embedding stores (including optional `pgvector`), and model routing remain E3 spike/ADR decisions. E0–E2 may add ports/seams and feature-flagged UI slots only.
- Scheduling: manual scheduling is the complete authoritative baseline (D-009). AI may propose feasibility; a human confirms; ordinary scheduling commands commit.

### 2.5 Frontend and UX spine

- Shells: staff, applicant, proctor/session, break-glass (GF-22). Preserve useful legacy affordances (sidebar, header breadcrumbs, toasts, notification center); rewrite auth/props/state/trust boundaries.
- Typed shared page props, design tokens/primitives, URL-backed list state, complete `page_state` model, typed toast payloads, durable notification projection.
- Public homepage and application entry get a controlled rebrand; broad dashboards wait for E4 unless required by the connected slice.
- Accessibility and responsive behavior are product requirements, not polish. WCAG 2.2 AA behavior for implemented scope; `prefers-reduced-motion` respected.
- Assistant personality / PNG-frame animation is presentation-only, feature-flagged, reduced-motion-safe, with static-frame fallback. It never communicates authority or replaces textual state.

### 2.6 Operations and scale

- Compose health-chained topology: app, queue worker, scheduler, PostgreSQL, private storage, Mailpit (local/test), optional authenticated inference seam.
- Correlation IDs (`request_id` / `command_id` / `operation_id`), health vs readiness distinction, backup/restore rehearsal, retention holds.
- Scale triggers are measured: queue backend, object storage, cache, vector store, worker count, or service extraction change only via ADR after evidence—not premature split.

---

## 3. Recovery phases (R0–R5)

### R0 — Evidence preservation and workspace isolation

Deliverables:

- freeze/reference commit or equivalent provenance for legacy evidence;
- disposition of the existing dirty worktree;
- selected greenfield repository/worktree location (`system/`);
- historical/non-authoritative labels for competing plans;
- approved recovery charter.

Gate R0-GO: no accidental legacy source mixing; founder approves authority/review process; greenfield location is explicit.

### R1 — Product and research authority

Deliverables:

- evidence/contradiction register;
- product scope and success contract;
- committed/conditional/deferred cut-line;
- evaluation/TAM versus technical-evidence separation;
- decision matrix round for authority, scope, and claims.

Gate R1-GO: final capstone promise is internally coherent; quiet feature omission is prohibited; pilot/production claims remain separate.

### R2 — Domain evidence closure

Deliverables:

- indexed stakeholder/operational evidence;
- role and data-scope decisions;
- workflow/state/failure/recovery decisions;
- OMR/RAG/model inputs required by retained scope;
- explicit blocked/unverified items.

Gate R2-GO: every committed workflow rule is evidenced or explicitly approved as a constrained assumption; no fabricated validation claim.

### R3 — Domain, data, security, and operations contracts

Deliverables:

- role-capability matrix;
- domain model and data lifecycle;
- workflows/state transitions;
- failure/recovery/integration contracts;
- security/privacy/audit contract;
- migration-ready schema/ERD candidate;
- platform/development standards.

Gate R3-GO: every P0 decision has a winner; domain names, state owners, cardinalities, delete/reversal rules, and scope agree; stack/runtime/database/testing are pinned.

### R4 — High-risk technical spikes

Spikes:

- OMR approved-sheet feasibility and uncertainty workflow;
- RAG corpus/query/refusal/authorization feasibility if committed;
- Python worker/queue/storage contract;
- reproducible Yukee baseline or honest reclassification;
- BGE input/relevance evidence if conditional scope advances;
- audit-chain canonicalization/concurrency/checkpoint design;
- offline sync only if it remains committed.

Gate R4-GO: measurable pass/fail report exists; failed spike activates a documented fallback/scope change; prototype output is not mistaken for production code.

### R5 — BMAD promotion and implementation readiness

Deliverables:

- approved/validated PRD;
- GF-21 authorization enforcement design and GF-22 UX/route/state contract;
- ADR catalog reviewed in dependency order;
- architecture and schema/ERD;
- executable epics/stories for E0, E1, and the connected slice;
- OpenSpec package for the first implementation change;
- fresh readiness audit.

Gate R5-GO: evidence → requirement → decision → workflow → state/data → ADR → story → acceptance/test chain is complete for the next slice; no open P0; implementation-preparation verdict is PASS or explicitly bounded CONDITIONAL PASS.

---

## 4. Execution phases (E0–E6)

### E0 — Clean scaffold and quality baseline

**Status:** QUALITY BASELINE VERIFIED; EXIT EVIDENCE INCOMPLETE
**Authority:** [GF-24 §5](24-bmad-openspec-implementation-package.md) + [`e0-greenfield-foundation`](../../openspec/changes/e0-greenfield-foundation/)
**Does not build:** admissions, identity domain features, RAG/OMR, production auth integrations.

Deliverables:

- isolated `system/` workspace with no legacy DB/storage/migration/route coupling;
- Laravel 13/PHP 8.4, Inertia/React/TS/Tailwind, PostgreSQL 16 pin, Compose topology;
- real Pest 5/Vitest/Playwright discovery; Pest architecture/browser plugins, Pint/PHPStan/ESLint/Prettier with no inherited suppressions;
- deterministic synthetic seed/reset; operation/idempotency/audit foundation seams required by E0 evidence;
- architecture-boundary tests; health/readiness; queue success/failure visibility; Mailpit and private-storage evidence; restore rehearsal; degraded optional inference seam;
- formal E0 evidence report and GF-14 PASS/FAIL update.

Exit: a new engineer can clone, boot, migrate, seed, reset, test, build, and inspect health/queue evidence without touching legacy. Then open `e1-identity-reference-foundation` only if PASS.

### E1 — Identity, access, reference, and cross-cutting foundation

**Status:** BLOCKED until E0 PASS + fresh R5
**Authority:** GF-24 §6; GF-04/GF-08/GF-19/GF-21/GF-22

| Track | Build | Fallback / bound |
|---|---|---|
| Backend/domain | Staff auth; ApplicantIdentity; optional ApplicantAccount after submission; hashed challenges; reference entities (cycle, window, program, room, aptitude, templates, config) | No connected journey yet |
| AuthZ/security | Capabilities, scopes, TA/Proctor duty overlays, break-glass allowlist, policy matrix tests | Default deny always |
| Data | Fresh schema for identity/reference/audit/idempotency/retention holds | No legacy migration copy |
| Frontend/UX | Typed `PageProps`, tokens/primitives, auth surfaces, base shells, server breadcrumbs, toast/notification contracts, page-state primitives | Rebrand homepage only as needed for auth entry |
| AI | Ports/envelopes, feature flag, disabled/degraded assistant slot, audit/idempotency hooks | No SDK/RAG implementation |
| Ops/evidence | Correlation IDs, operation visibility, denial/replay/stale-version/audit tests | — |

Exit: cross-scope denial, credential lifecycle, replay, stale-version, audit, shell, responsive, and accessibility evidence pass.

### E2 — Connected admission-to-release slice (first working product)

**Status:** BLOCKED until E1 exit
**Authority:** [GF-11](11-connected-vertical-slice-and-acceptance.md) (32 `REQ-VS-*`); GF-24 §7; GF-05/GF-06/GF-20–GF-22
**Fallback:** Manual scoring is complete. OMR/RAG/BGE/Yukee/offline are out of slice unless separately gated.

Fixed build order:

1. `SubmitAdmissionAttempt` and durable identity/reference
2. Correct / Accept / Dismiss / bounded reopen-archive
3. Applicant access challenge and setup
4. Exam session create / assign / publish (manual scheduling)
5. Proctor duty, start/extend, attendance, submission
6. Close exam → open grading (exactly once)
7. Manual score / review / finalize
8. Consultation draft/confirm/defer
9. Prepare result document
10. Release, after-commit notification, applicant retrieval
11. Reverse release where policy allows
12. Full denial, concurrency, degraded, accessibility, breadcrumb, toast, chart/document, audit evidence

Legacy reuse: preserve journeys, fields, vocabulary, sidebar/breadcrumb/toast affordances via [`docs/legacy-reference/`](../legacy-reference/README.md). Rewrite state ownership, authorization, transactions, concurrency, and trust boundaries.

Frontend in slice: public apply + controlled homepage rebrand; staff/applicant/proctor shells; only projections required by the journey. Broad role dashboards wait for E4.

Exit: all 32 `REQ-VS-*` map to executable tests or runtime evidence—not merely a happy-path render.

### E3 — Spike-passed differentiators

**Status:** BLOCKED until E2 exit and per-capability R4/evidence gates
**Authority:** GF-24 §8; GF-10; D-007–D-011; G-OMR / G-RAG / G-ENGINE / G-OFFLINE

| Capability | Entry gate | Fallback |
|---|---|---|
| RAG / chatbot | Corpus ownership/versioning, auth-before-retrieve, citation/refusal, privacy, prompt-injection, outage tests | Static policy + staff handoff |
| Scheduling assistance | Constraint/feasibility spike; human confirm only | Manual scheduling |
| OMR | Approved sheet/input/ground truth/confidence/review | Manual scoring |
| Yukee / BGE / models | Reproducible artifact or honest baseline; proposal-only worker | Counselor/manual; feature disabled |
| Offline proctor | Durable outbox/replay/conflict (EV-011) | Online/paper/manual reconciliation |
| Personality / PNG frames | Feature flag + reduced-motion + static fallback | Text-first assistant UI |

One OpenSpec change per capability. No research feature hidden inside E2.

### E4 — Conditional horizontal expansion

- Role-scoped dashboards/reports with metric dictionary, scope/freshness, drill-down re-auth, accessible chart alternatives, auditable exports.
- Broader notifications/preferences; bounded inquiry/messaging only after routing/retention/SLA ownership.
- Additional documents/imports; offline sync only with approved conflict protocol.
- Infrastructure capacity changes only when measured triggers justify ADR (queue backend, object storage, cache, vector store, workers).

Exit: each capability has its own requirement, acceptance, security/privacy, and operations evidence.

### E5 — Technical pilot and evaluation

- Controlled role-based pilot; defect closure; frozen TAM scenarios; approved respondent execution.
- Separate technical evidence from TAM/usability reports; limitations and non-claims explicit.

Exit: capstone evaluation evidence is complete and claims match actual results.

### E6 — Pilot/production operations

Only if authorized:

- real-data privacy/institutional approval;
- deployment health/observability;
- backup/restore and RPO/RTO targets;
- provider/sidecar outages and incident runbooks;
- security review and key/secret operations;
- staff training/support ownership;
- release/rollback procedure.

Exit: separate pilot/production readiness verdict passes.

---

## 5. Delivery tracks (core-first with bounded parallel preparation)

Critical path: **E0 → E1 → E2**. Parallel tracks may prepare evidence and prototypes; they may not mutate authoritative domain state or inject E3 dependencies into E1/E2.

| Track | Critical path | Parallel (bounded) |
|---|---|---|
| Backend/domain | E0 seams → E1 identity/reference → E2 command spine | Fixture/scenario extraction from legacy |
| Data/schema | Fresh PG history → E1 tables → E2 aggregates/constraints | Constraint spike notes (`btree_gist`, uniqueness themes) |
| AuthZ/security | E1 policies → E2 denial evidence | Break-glass/runbook drafts |
| Frontend/UX | E0 toolchain → E1 shells/tokens → E2 connected pages | Token/rebrand research; legacy layout inventory |
| AI/inference | E0 optional seam → E1 ports/flags → E3 OpenSpecs | R4 RAG/OMR/worker spikes and corpus collection |
| Operations | E0 health/queue/restore → E2 degraded evidence → E5/E6 | Runbook drafts |
| Evidence/testing | Real discovery every phase; GF-11 matrix in E2 | Characterization scenarios from legacy tests (rewritten) |

---

## 6. Mapping from previous 14-phase plan

| Previous phase | Reconciled destination | Treatment |
|---|---|---|
| 0 Toolchain | R3 decisions → E0 | Keep after runtime/test choices and clean-workspace boundary |
| 1 Reference data | R3 schema → E1 | Keep; depend on domain/ownership decisions |
| 2 Identity/auth | R3 role/auth contract → E1 | Keep; resolve role catalog, applicant lifetime, identifier semantics first |
| 3 Application intake | R2/R3 workflow → E2 | Keep within the connected slice, not isolated CRUD |
| 4 Exam scheduling | R2/R3 workflow → E2; optimization spike R4/E3 | Manual/core scheduling in slice; assistance later |
| 5 Grading/release | R2/R3 workflow → E2 | Keep with non-destructive correction/reversal and manual fallback |
| 6 Documents | R3 security contract → E2/E4 | Minimum safe documents in slice; parity inventory later |
| 7 Audit | R3 design → E1/E2 continuously | Audit is foundation from first command |
| 8 Communications | R3 integration contract → E2/E4 | Required notifications in slice; preferences/broader channels later |
| 9 RAG | R2 evidence + R4 spike → E3 | Only after scope/corpus/refusal/privacy contract passes |
| 10 OMR/engines/triage/Program Head | R4 spikes → E3/E4 | Fixed adapters before generic registry; minimal Program Head projection may begin in E2 |
| 11 Dashboards/reporting | E2 projections → E4 expansion | Build from connected aggregates, not disconnected screens |
| 12 Messaging/offline | R4 if retained → E4 | Conditional; no cache-only offline claim |
| 13 Hardening/deployment | E0 baseline + E2 integrity + E5/E6 operations | Split baseline quality from later pilot/production hardening |

Historical detail lives in [`docs/greenfield-implementation-order.md`](../greenfield-implementation-order.md). Do not treat it as sequence authority.

---

## 7. Stable gate IDs

Replace ambiguous numeric gate references with:

| Gate ID | Gate | Blocks |
|---|---|---|
| G-AUTHORITY | Source precedence and promotion | All downstream authority |
| G-SCOPE | Capstone cut-line and claims | PRD, evaluation, roadmap |
| G-ROLE | Role/capability/data scope | Identity schema, policies, workflows |
| G-IDENTITY | Applicant lifetime and authentication | Identity/application schema |
| G-WORKFLOW | Intake/exam/grading/release transitions and reversals | Domain implementation |
| G-DATA | Cardinality, uniqueness, retention, ERD | Migrations |
| G-OMR | Sheet/input/confidence/review/benchmark | OMR implementation/claim |
| G-RAG | Corpus/data/refusal/citation/privacy | RAG implementation/claim |
| G-ENGINE | Yukee/BGE method/data/provenance | Engine implementation/claim |
| G-AUDIT | Threat model/key/checkpoint/failure | Cryptographic assurance claim |
| G-PLATFORM | Runtime/database/testing/topology | Scaffold and CI |
| G-OFFLINE | Sync/conflict/idempotency/manual resolution | Offline implementation/claim |
| G-UX | Route/page/breadcrumb/chart/toast/state/a11y/responsive contract | Browser implementation/evidence |
| G-EVALUATION | Pilot/TAM scenarios/protocol | Broad evaluation |
| G-DEPLOY | Privacy/operations/restore/security ownership | Real pilot/production |

Documents must reference these IDs rather than renumbered lists.

---

## 8. Readiness scorecard

| Criterion | Current verdict | Required proof |
|---|---|---|
| Authority chain | CONDITIONAL/DRAFT | Founder outcomes recorded; propagation and promotion evidence |
| Product scope | PROMOTED (planning) | Formal PRD `docs/prd.md` + GF-03 PROMOTED 2026-08-05; manuscript propagation still open |
| Stakeholder evidence | BLOCKED/PARTIAL | Required workflow/mapping/corpus evidence indexed |
| Role and data scope | CONDITIONAL/DRAFT | Matrix decisions propagated into GF-04/GF-21 and scenarios |
| Domain/state/data lifecycle | DRAFT | Contracts approved; ERD/schema follows |
| Platform/runtime/testing | E0-CONDITIONAL | Versions/topology approved; E0 exit evidence report |
| Research differentiators | BLOCKED | Inputs and technical spike gates pass |
| First connected slice | SPECIFIED DRAFT | Upstream approvals plus executable stories/OpenSpec and GF-22 coverage |
| Browser/UI contract | DRAFT/BLOCKED | GF-21/GF-22 review and route/page/state evidence |
| Implementation preparation | E0-CONDITIONAL / E1-E2 NO-GO | E0 package evidence; then R5 PASS for feature work |
| Controlled demonstration | NO-GO NOW | E2/E3 evidence and technical pilot |
| Pilot/production | BLOCKED | G-DEPLOY and institutional approval |

Three independent verdicts must always be reported separately: planning coherence, implementation preparation, and pilot/deployment clearance.

---

## 9. Definition of Ready and Done

### Definition of Ready (story / OpenSpec change)

A change is ready only if it names:

- upstream decision IDs and contract sections;
- legacy disposition (preserve / rewrite / spike / defer / reject) when behavior is reused;
- actor, capability, resource scope, and duty context;
- command/query or page ownership;
- transaction, lock, and idempotency protocol;
- asynchronous side effects and degraded fallback;
- route/page/state/feedback requirements where UI is involved;
- Pest/Vitest/Playwright or operational evidence target;
- rollback or correction behavior.

### Definition of Done (E0 / E1 / E2 and later)

A change is done only when:

1. OpenSpec tasks for the change are complete without hiding earlier failures;
2. fresh PostgreSQL migrations and deterministic seed/reset pass where applicable;
3. relevant backend/frontend/browser tests are discovered and green;
4. authorization denial and happy path are both proven for protected behavior;
5. audit/idempotency/after-commit side effects match contracts;
6. UI states (loading/empty/denied/conflict/degraded) match GF-22 where UI is involved;
7. evidence paths are recorded; GF-00/GF-14 status is updated if a phase gate moves;
8. implementation matches GF-18–GF-23 or divergence is recorded as a decision/ADR;
9. no legacy route, migration, database, or provider was silently made authoritative.

---

## 10. Risk register (delivery)

| Risk | Symptom | Mitigation |
|---|---|---|
| Authority drift / doc scatter | Multiple “current” roadmaps; README overclaims | Only GF-00 updates live checkpoint; only GF-14 updates phase gates; mark others historical |
| Premature AI coupling | SDK/chat mutates state or bypasses policy | Ports first; E3 OpenSpec per capability; confirm-to-command only |
| Skipping E0/E1 seams | Screens without audit/idempotency/denial tests | Gate E2 on E1 exit evidence |
| Concurrency regressions | Duplicate refs, overbooking, unlocked merges | DB constraints + command tests from GF-07/GF-19 |
| UI auth leakage | Client role checks, unauthorized breadcrumb hrefs | Server nav/props; GF-21 scenarios |
| Provider/outage false success | Toast claims sent/released without evidence | After-commit + truthful pending/sent wording |
| Restore unproven | Demo data loss | E0 restore rehearsal before feature depth |
| Scope creep / dashboards early | Disconnected admin chrome before slice | E2 projections only; E4 expansion |
| Accessibility/motion debt | Essential info via animation; no keyboard path | GF-22 §10–11; reduced-motion for PNG personality |

---

## 11. Governance and anti-scatter rules

1. **Single navigator:** [GF-00](00-catch-up-and-continue-progress.md) is the only live “where we are” document.
2. **Single phase spine:** This document (GF-14) is the only R0–R5 / E0–E6 sequence authority.
3. **Single implementation handoff:** [GF-24](24-bmad-openspec-implementation-package.md) + one active OpenSpec change authorize code.
4. **Decisions only in GF-13:** No second approval queues; PM must not silently resolve founder/stakeholder choices in tickets.
5. **Upstream-first propagation:** Conflicts reopen decisions before downstream edits (GF-01; stop rule below).
6. **Mark, don’t delete:** Superseded docs get HISTORICAL / SUPERSEDED headers and a link here or to GF-24.
7. **Stable vocabulary:** Use `G-*`, `R*`, and `E*` IDs—not old “Gate 0–5” or unaudited phase renumbering—in new material.
8. **OpenSpec hygiene:** Later tasks must not mask failed earlier ones; checkbox completion must match committed evidence paths.

### Demoted / supporting roadmaps (not sequence authority)

| Artifact | Treatment |
|---|---|
| [`docs/greenfield-implementation-order.md`](../greenfield-implementation-order.md) | HISTORICAL — phase detail/evidence only |
| [`docs/greenfield-architecture-plan.md`](../greenfield-architecture-plan.md) | HISTORICAL — reconnaissance/rationale only |
| [`docs/master-architecture-picture.md`](../master-architecture-picture.md) | HISTORICAL — audit evidence; Gates 0–5 superseded by §7 above |
| [`13b-batch-approval-recommendations.md`](13b-batch-approval-recommendations.md) | Historical rationale; GF-13 is canonical |
| Legacy planning under `legacy_codebase/planning/` etc. | Historical evidence only |

---

## 12. Current next gate

**Immediate authorized work:** close residual E0 checklist items D6/E3/F3 in [`tasks.md`](../../openspec/changes/e0-greenfield-foundation/tasks.md), then open `e1-identity-reference-foundation`. Evidence already recorded at [`evidence/e0-scaffold-evidence.md`](evidence/e0-scaffold-evidence.md).

After residual E0 hygiene + E1 package open: propagate/promote G-AUTHORITY + G-SCOPE + G-ROLE/G-IDENTITY + G-UX as needed, close or bound remaining evidence holds (D-073–D-084 and R4 items), review GF-21–GF-24, run fresh R5 before E1 implementation depth.

Recording founder outcomes or writing an OpenSpec package does **not** by itself approve E2 feature implementation, pilot, or production clearance.

---

## 13. Stop rule

Stop and reopen planning when a new founder, panel, stakeholder, privacy, or technical-spike result changes:

- product commitment;
- actor authority or data visibility;
- domain identity/cardinality;
- workflow state/reversal;
- research claim;
- storage/provider/runtime choice with schema/security consequences.

Propagate upstream first, then rerun readiness. Do not patch downstream code around an unresolved authority change.
