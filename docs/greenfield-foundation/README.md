# NEXIAM Greenfield Foundation Suite

**Status:** DRAFT — prepared for founder batch review  
**Project:** NEXIAM  
**Purpose:** Convert legacy evidence, capstone commitments, and the greenfield strategy into an approval-ready foundation for a clean rebuild.  
**Implementation authority:** None until the decision matrix is approved and the affected documents are promoted.

## Thesis

NEXIAM should not restart BMAD from an empty page, and it should not implement directly from the legacy code or the current 14-phase roadmap. The project needs a controlled BMAD recovery bridge: preserve prior work as evidence, extract intended institutional behavior, resolve contradictions through founder decisions, promote a coherent requirements/domain/architecture chain, then run implementation readiness before code.

## Governing rule

Legacy behavior is evidence, not authority. It may reveal a workflow, invariant, actor, failure mode, or integration contract, but it cannot silently become a greenfield requirement. Every consequential target rule must be supported by one or more of:

1. founder-approved decision;
2. stakeholder or panel evidence;
3. current manuscript commitment;
4. institutional policy or operational artifact;
5. defensible engineering constraint.

Unknowns remain visible. They are never filled with fabricated stakeholder facts.

## BMAD entry strategy

| BMAD phase | NEXIAM treatment | Required outcome |
|---|---|---|
| Phase 1 — Analysis | Recovery validation, not blank-slate ideation | Evidence ledger, confirmed problem/actors, unresolved field-validation tasks, capstone cut-line |
| Phase 2 — Planning | Rebuild or validate the PRD against approved decisions | Requirement catalog, role/user outcomes, scope, NFRs, acceptance and evaluation contracts |
| Phase 3 — Solutioning | Promote domain/schema/ADR/architecture contracts | Canonical domain model, state machines, schema/ERD, ADRs, architecture, executable epics/stories |
| Phase 4 — Implementation | Blocked until readiness passes | Clean scaffold followed by the approved connected vertical slice |

The existing audit and greenfield documents are inputs to these phases. They do not bypass them.

## Document suite and reading order

| Order | Artifact | Purpose | Approval effect |
|---:|---|---|---|
| 1 | `01-recovery-and-authority-charter.md` | Controls evidence, authority, freezes, review, promotion, and stop gates | Approves the recovery process, not product behavior |
| 2 | `02-evidence-and-contradiction-register.md` | Records sources, confidence, conflicts, winning candidates, and missing validation | Identifies what must be decided or gathered |
| 3 | `03-product-scope-and-success-contract.md` | Defines capstone promise, actors, must/conditional/deferred scope, and success planes | Locks product and evaluation boundaries |
| 4 | `04-role-capability-and-data-scope-matrix.md` | Current-versus-target RBAC and contextual-duty contract | Locks actor authority and data visibility |
| 5 | `05-domain-model-and-data-lifecycle.md` | Defines aggregates, ownership, identity lifetime, uniqueness, deletion/archive, and retention | Locks conceptual schema inputs |
| 6 | `06-workflows-and-state-transitions.md` | Defines end-to-end journeys, states, transitions, actor/precondition/effects | Locks intended institutional behavior |
| 7 | `07-failure-recovery-and-integration-contracts.md` | Defines retries, idempotency, cancellation, timeouts, fallback, external adapters | Locks operational semantics |
| 8 | `08-security-privacy-and-audit-contract.md` | Defines threat boundaries, authentication, authorization, privacy, audit evidence | Locks security obligations and unresolved risk gates |
| 9 | `09-platform-topology-and-development-standards.md` | Pins stack/runtime/topology and the delivery quality contract | Locks reproducible technical foundation |
| 10 | `10-capstone-research-and-technical-validation.md` | Separates TAM from OMR/RAG/model/audit technical evidence | Locks research claims and measurable feasibility gates |
| 11 | `11-connected-vertical-slice-and-acceptance.md` | Defines the first complete browser journey and executable scenarios | Locks the first build target |
| 12 | `12-requirements-traceability-matrix.md` | Connects evidence → requirement → workflow → decision → acceptance → future test | Makes coverage auditable |
| 13 | `13-decision-and-batch-approval-matrix.md` | Presents all founder decisions with recommendations, consequences, and Go/No-Go choices | Single-session batch review authority |
| 14 | `14-readiness-gates-and-reconciled-roadmap.md` | Reconciles the old 14 phases with BMAD and vertical-slice sequencing | Controls entry into implementation |
| 15 | `15-architecture-decision-record-catalog.md` | Defines proposed ADRs, dependencies, alternatives, consequences, and promotion tests | Creates the Phase 3 bridge from approved decisions to implementation stories |

## Draft engineering diagrams

| Diagram | Editable source | Rendered artifact | Authority |
|---|---|---|---|
| Proposed conceptual domain ERD | `assets/proposed-domain-erd.mmd` | `assets/proposed-domain-erd.svg` | Illustrates GF-05; not migration authority |
| Proposed Compose/deployment topology | `assets/proposed-deployment-topology.mmd` | `assets/proposed-deployment-topology.svg` | Illustrates GF-09/ADR-014; not deployment proof |

## Status vocabulary

- **DRAFT:** evidence-backed proposal; not authoritative.
- **REVIEW REQUIRED:** explicit founder or stakeholder decision needed.
- **APPROVED:** reviewed and accepted for downstream use.
- **CONDITIONAL:** usable only under stated constraints.
- **BLOCKED:** downstream work must not proceed.
- **HISTORICAL:** retained as evidence but cannot govern new work.
- **SUPERSEDED:** replaced by a named approved artifact.

## Three independent verdicts

Every readiness report must publish separate verdicts:

1. **Planning coherence:** Do the approved documents agree?
2. **Implementation preparation:** Is the next connected slice executable and testable?
3. **Pilot/deployment clearance:** Is there real operational, privacy, technical, and stakeholder evidence to run beyond a controlled demonstration?

Passing one verdict does not imply the others.

## Review protocol

David may review the complete suite in one sitting and issue batch decisions. To prevent accidental authority:

1. Every unresolved item receives a stable decision ID.
2. The batch matrix includes a recommended default, alternatives, consequences, evidence, and review trigger.
3. Unanswered items retain `REVIEW REQUIRED` or `BLOCKED` status.
4. Approved decisions propagate upstream-to-downstream: scope → roles/workflows → data → ADRs/architecture → acceptance → roadmap.
5. After propagation, a fresh consistency and readiness audit is required.
6. No artifact is called canonical merely because it exists on disk.

## Stop boundary

This suite does not prove that:

- the greenfield application exists;
- migrations or tests pass;
- OMR, RAG, scheduling, or model engines are technically valid;
- field interviews or Program Head mapping validation occurred;
- privacy, pilot, or production deployment is cleared.

The next implementation work may begin only after the decision matrix is approved, P0 contradictions are closed, the first vertical slice is fully specified, and the readiness artifact records a PASS or explicitly bounded CONDITIONAL PASS.
