# Readiness Gates and Reconciled Roadmap

**Artifact:** NEXIAM-GF-14  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Reconcile BMAD recovery, stakeholder evidence, high-risk spikes, the connected vertical slice, and the previous 14-phase greenfield backlog into one dependency-controlled path.

## 1. Corrected strategic sequence

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

Novel capstone risk is moved before full parity reconstruction. Production hardening is not falsely required to call a bounded capstone demonstration coherent, but baseline security/quality/integrity is never deferred.

**D-004 founder direction:** The previous greenfield architecture and implementation-order plans are inputs to a compound merge, not standalone execution authority. Their useful frontend, backend, architecture, and phase details must be reconciled into the stronger canonical requirements/HLD/LLD/domain/ADR/implementation chain before implementation. Conflicting standalone plans are then marked historical or superseded.

## 2. Recovery phases

### R0 — Evidence preservation and workspace isolation

Deliverables:

- freeze/reference commit or equivalent provenance for legacy evidence;
- disposition of the existing dirty worktree;
- selected greenfield repository/worktree location;
- historical/non-authoritative labels for competing plans;
- approved recovery charter.

Gate R0-GO:

- no accidental legacy source mixing;
- founder approves authority/review process;
- greenfield location is explicit.

### R1 — Product and research authority

Deliverables:

- evidence/contradiction register;
- product scope and success contract;
- committed/conditional/deferred cut-line;
- evaluation/TAM versus technical-evidence separation;
- decision matrix round for authority, scope, and claims.

Gate R1-GO:

- final capstone promise is internally coherent;
- quiet feature omission is prohibited;
- pilot/production claims remain separate.

### R2 — Domain evidence closure

Deliverables:

- indexed stakeholder/operational evidence;
- role and data-scope decisions;
- workflow/state/failure/recovery decisions;
- OMR/RAG/model inputs required by retained scope;
- explicit blocked/unverified items.

Gate R2-GO:

- every committed workflow rule is evidenced or explicitly approved as a constrained assumption;
- no fabricated validation claim.

### R3 — Domain, data, security, and operations contracts

Deliverables:

- role-capability matrix;
- domain model and data lifecycle;
- workflows/state transitions;
- failure/recovery/integration contracts;
- security/privacy/audit contract;
- migration-ready schema/ERD candidate;
- platform/development standards.

Gate R3-GO:

- every P0 decision has a winner;
- domain names, state owners, cardinalities, delete/reversal rules, and scope agree;
- stack/runtime/database/testing are pinned.

### R4 — High-risk technical spikes

Spikes:

- OMR approved-sheet feasibility and uncertainty workflow;
- RAG corpus/query/refusal/authorization feasibility if committed;
- Python worker/queue/storage contract;
- reproducible Yukee baseline or honest reclassification;
- BGE input/relevance evidence if conditional scope advances;
- audit-chain canonicalization/concurrency/checkpoint design;
- offline sync only if it remains committed.

Gate R4-GO:

- measurable pass/fail report exists;
- failed spike activates a documented fallback/scope change;
- prototype output is not mistaken for production code.

### R5 — BMAD promotion and implementation readiness

Deliverables:

- approved/validated PRD;
- UX responsibility and journey contract;
- ADR catalog reviewed in dependency order;
- architecture and schema/ERD;
- executable epics/stories for E0, E1, and the connected slice;
- OpenSpec package for the first implementation change;
- fresh readiness audit.

Gate R5-GO:

- evidence → requirement → decision → workflow → state/data → ADR → story → acceptance/test chain is complete for the next slice;
- no open P0;
- implementation-preparation verdict is PASS or explicitly bounded CONDITIONAL PASS.

## 3. Execution phases

### E0 — Clean scaffold and quality baseline

- Laravel 12/PHP 8.4 and approved frontend/runtime versions;
- approved Compose topology;
- CI and zero-on-greenfield quality gates;
- PHPUnit/Vitest/Playwright scaffolds with real discovered tests;
- health/readiness structure;
- fresh production-engine migration skeleton and deterministic seed path;
- no domain choices beyond approved contracts.

Exit: clean install, boot, build, tests, checks, migration/seed, and Compose health are green.

### E1 — Identity, access, and reference foundation

- Staff authentication plus ApplicantIdentity/optional ApplicantAccount establishment after submission;
- hashed setup/reset challenges;
- roles/capabilities/scopes and duty assignments;
- AcademicCycle and reference data;
- base audit event service;
- schema constraints and policy matrix tests.

Exit: identity and authorization acceptance scenarios pass, including cross-scope denial.

### E2 — Connected admission-to-release slice

Implement `11-connected-vertical-slice-and-acceptance.md` using manual scoring as complete fallback.

Exit: one repeatable browser journey plus denial, concurrency, reversal, notification, document, audit, and degraded-provider evidence passes.

### E3 — Spike-passed differentiators

- OMR processing/review/import;
- scoped RAG and staff fallback;
- human-gated scheduling assistance;
- approved fixed inference adapters and counselor signal presentation.

Exit: technical evidence gates pass independently of TAM and manual/core fallbacks remain complete.

### E4 — Conditional horizontal expansion

- broader Program Head/reporting projections;
- additional documents/reports/imports;
- bounded messaging;
- offline proctor sync only with approved conflict protocol;
- generic engine governance only if genuinely required;
- multi-cycle/history enhancements.

Exit: each capability has its own approved requirement, acceptance, security/privacy, and operations evidence.

### E5 — Technical pilot and evaluation

- controlled role-based pilot;
- defect closure and Go/No-Go;
- frozen TAM scenarios/instrument/handling;
- approved respondent execution;
- separate technical and TAM reports;
- limitations and non-claims.

Exit: capstone evaluation evidence is complete and claims match actual results.

### E6 — Pilot/production operations

Only if authorized:

- real-data privacy/institutional approval;
- deployment health/observability;
- backup/restore and RPO/RTO;
- provider/sidecar outages and incident runbooks;
- security review and key/secret operations;
- staff training/support ownership;
- release/rollback procedure.

Exit: separate pilot/production readiness verdict passes.

## 4. Mapping from previous 14-phase plan

| Previous phase | Reconciled destination | Treatment |
|---|---|---|
| 0 Toolchain | R3 decisions → E0 | Keep, but only after runtime/test choices and clean-workspace boundary are approved |
| 1 Reference data | R3 schema → E1 | Keep; depend on domain/ownership decisions |
| 2 Identity/auth | R3 role/auth contract → E1 | Keep; resolve role catalog, applicant lifetime, and identifier semantics first |
| 3 Application intake | R2/R3 workflow → E2 | Keep within the connected slice, not isolated CRUD |
| 4 Exam scheduling | R2/R3 workflow → E2; optimization spike R4/E3 | Manual/core scheduling in slice; assistance later |
| 5 Grading/release | R2/R3 workflow → E2 | Keep with non-destructive correction/reversal and manual fallback |
| 6 Documents | R3 security contract → E2/E4 | Minimum safe documents in slice; parity inventory later |
| 7 Audit | R3 design → E1/E2 continuously | Audit is foundation from first command, not a later isolated phase |
| 8 Communications | R3 integration contract → E2/E4 | Required notifications in slice; preferences/broader channels later |
| 9 RAG | R2 evidence + R4 spike → E3 | Only after scope/corpus/refusal/privacy contract passes |
| 10 OMR/engines/triage/Program Head | R4 spikes → E3/E4 | Move OMR risk early; fixed adapters before generic registry; Program Head projection may begin minimally in E2 |
| 11 Dashboards/reporting | E2 projections → E4 expansion | Build from connected aggregates, not disconnected screens |
| 12 Messaging/offline | R4 if retained → E4 | Conditional; no cache-only offline claim |
| 13 Hardening/deployment | E0 baseline + E2 integrity + E5/E6 operations | Split baseline quality from later pilot/production hardening |

## 5. Stable gate IDs

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
| G-EVALUATION | Pilot/TAM scenarios/protocol | Broad evaluation |
| G-DEPLOY | Privacy/operations/restore/security ownership | Real pilot/production |

Documents must reference these IDs rather than renumbered lists.

## 6. Readiness scorecard

| Criterion | Current verdict | Required proof |
|---|---|---|
| Authority chain | BLOCKED | Founder approves charter and decision matrix |
| Product scope | BLOCKED | Cut-line and manuscript propagation approved |
| Stakeholder evidence | BLOCKED/PARTIAL | Required workflow/mapping/corpus evidence indexed |
| Role and data scope | BLOCKED | Matrix decisions approved and scenarios defined |
| Domain/state/data lifecycle | DRAFT | Contracts approved; ERD/schema follows |
| Platform/runtime/testing | DRAFT/BLOCKED | Versions/topology approved; clean scaffold evidence later |
| Research differentiators | BLOCKED | Inputs and technical spike gates pass |
| First connected slice | SPECIFIED DRAFT | Upstream approvals plus executable stories/OpenSpec |
| Implementation preparation | NO-GO NOW | R5 readiness PASS |
| Controlled demonstration | NO-GO NOW | E2/E3 evidence and technical pilot |
| Pilot/production | BLOCKED | G-DEPLOY and institutional approval |

## 7. Current next gate

The immediate gate is **G-AUTHORITY + G-SCOPE + G-ROLE/G-IDENTITY**, followed by operational reconciliation D-073–D-080, through the batch decision matrix. No implementation phase should be interpreted as approved before those dependency-root decisions are recorded.

## 8. Stop rule

Stop and reopen planning when a new founder, panel, stakeholder, privacy, or technical-spike result changes:

- product commitment;
- actor authority or data visibility;
- domain identity/cardinality;
- workflow state/reversal;
- research claim;
- storage/provider/runtime choice with schema/security consequences.

Propagate upstream first, then rerun readiness. Do not patch downstream code around an unresolved authority change.
