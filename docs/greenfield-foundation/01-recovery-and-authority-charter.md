# Recovery and Authority Charter

**Artifact:** NEXIAM-GF-01  
**Status:** DRAFT — REVIEW REQUIRED  
**Applies to:** Greenfield planning recovery and implementation entry  
**Does not authorize:** Feature implementation, schema creation, deployment, pilot operation, or stakeholder claims

## 1. Recovery objective

Create one coherent, reviewable authority chain for rebuilding NEXIAM without carrying forward accidental legacy behavior or silently dropping current capstone commitments.

Recovery succeeds when:

- source authority is explicit;
- product and research scope is settled;
- actors, duties, workflows, states, invariants, failures, and data lifecycle are explicit;
- high-risk technical claims have measurable spike gates;
- one connected vertical slice has acceptance scenarios;
- all P0 decisions are approved or explicitly blocked;
- a fresh implementation-readiness audit passes.

## 2. Why this is a BMAD recovery bridge

NEXIAM already has substantial analysis, manuscript material, audit evidence, architecture proposals, and a greenfield roadmap. Repeating BMAD Phase 1 as blank-slate brainstorming would waste evidence and risk new contradictions. Skipping directly to Phase 4 would make unresolved assumptions executable.

Founder decision D-002 is GO: use this controlled recovery bridge because the exercised legacy codebase and capstone documents are valuable evidence inputs, while the approved BMAD/foundation chain becomes the target authority.

The controlled path is:

1. **Phase 1 recovery validation:** inventory evidence, preserve unknowns, close field-evidence gaps.
2. **Phase 2 planning reconstruction:** promote approved product requirements and scope.
3. **Phase 3 solutioning:** promote domain/schema/ADR/architecture and executable stories.
4. **Readiness audit:** independently verify traceability and contradictions.
5. **Phase 4 implementation:** scaffold, then build the approved connected slice.

## 3. Source-of-truth precedence

D-001 was approved GO by David on 2026-08-02. The following hierarchy governs the recovery process, while the individual foundation artifacts remain DRAFT until their own decisions are propagated and the readiness audit passes:

1. Explicit founder decisions recorded in the approved batch-decision matrix.
2. Binding panel/adviser constraints and verified institutional policy.
3. Approved product scope and success contract.
4. Approved role, workflow, domain, data-lifecycle, security, and research contracts.
5. Approved ADRs and architecture.
6. Approved requirements traceability and connected-slice acceptance scenarios.
7. OpenSpec change packages implementing the approved behavior.
8. Tests and implementation evidence.
9. Legacy code, old plans, mockups, and archived manuscripts as supporting evidence only.

A lower authority cannot silently override a higher authority. A contradiction reopens the affected upstream decision before downstream documents are changed.

## 4. Source dispositions

| Source class | Current disposition | Permitted use | Forbidden use |
|---|---|---|---|
| Legacy application source | EVIDENCE ONLY | Discover current workflows, data, integrations, defects, and useful invariants | Copy behavior as target truth without review |
| Architecture audit documents | SUPPORTING AUTHORITY | Identify known risks, capability seams, and decision gates | Replace stakeholder/product decisions |
| Greenfield architecture and implementation order | DRAFT STRATEGY/BACKLOG | Guide boundaries, sequencing, quality goals, and risk closure | Direct implementation while gates conflict or remain unresolved |
| Current manuscript and panel records | PRODUCT/RESEARCH INPUT | Establish promised scope, objectives, population, and evaluation claims | Treat stale or internally contradictory wording as settled |
| Requirements-planning instruments | EVIDENCE COLLECTION INPUT | Identify questions, needed artifacts, and planned validation | Claim interviews, mappings, or observations are completed when evidence is absent |
| Existing access-control inventory | WORKING PROPOSAL | Seed role/capability review and current-versus-target comparison | Treat its role catalog as approved |
| Founder-approved foundation artifacts | FUTURE CANONICAL AUTHORITY | Govern downstream PRD, ADR, OpenSpec, and implementation | Imply pilot/production clearance without separate evidence |

## 5. Frozen work during recovery

The following are blocked until the applicable approval gate passes:

- production or canonical greenfield migrations;
- final identity and role schema;
- implementation of disputed pipeline transitions;
- arbitrary external model/plugin execution;
- sensitive institutional or applicant-data collection for unapproved testing;
- claims that OMR, RAG, scheduling, or admission-support models are validated;
- production deployment or real-user pilot;
- broad parity reconstruction from the legacy application;
- promotion of the current 14-phase document as direct execution authority.

## 6. Permitted work during recovery

- read-only source and document inspection;
- evidence extraction and contradiction mapping;
- stakeholder interview preparation and artifact collection;
- decision-matrix preparation;
- non-authoritative domain, workflow, schema, and acceptance drafts;
- disposable, non-production technical spikes after their inputs and pass/fail criteria are approved;
- clean-workspace and toolchain planning that does not lock domain behavior.

## 7. Decision ownership

| Decision class | Final approver | Required evidence |
|---|---|---|
| Product scope and capstone cut-line | David, reconciled with adviser/panel obligations | Current manuscript, panel record, feasibility and deadline constraints |
| Institutional workflow and office authority | David after stakeholder validation; relevant office owner where available | Interview/observation/policy evidence plus current-system evidence |
| Research claims and evaluation | David and research adviser/panel | Approved methodology, instruments, technical validation plan |
| Architecture and engineering defaults | David on lead-engineer recommendation | Trade-offs, constraints, reversible/default choice, review trigger |
| Privacy, retention, and sensitive-data handling | David with institutional/privacy authority as required | Data classification, lawful purpose, retention and access requirements |
| Implementation readiness | Lead engineer issues evidence-based verdict; David authorizes entry | Approved artifacts, mechanical traceability, no open P0 blockers |

No subagent, legacy implementation, document header, or generated matrix is itself an approver.

## 8. Decision-record requirements

Every consequential decision must include:

- stable ID;
- status;
- question being resolved;
- recommended default;
- alternatives considered;
- rationale and evidence;
- consequences and affected artifacts;
- approval owner;
- review trigger;
- date and supersession target when approved.

## 9. Review and promotion protocol

1. Produce the complete draft suite so the overall system can be seen.
2. Present the batch decision matrix in dependency order.
3. David records `GO`, `GO WITH CONDITION`, `REVISE`, or `NO-GO` per row.
4. Propagate approved decisions through the suite in dependency order.
5. Mark superseded/historical authorities visibly; do not delete evidence.
6. Validate IDs, cross-references, table structure, and contradiction closure mechanically.
7. Run a fresh BMAD-style implementation-readiness audit.
8. Promote only the passing artifacts to canonical status.

Creation, completion, and approval are separate states.

## 10. Gate taxonomy

| Gate | Type | Failure behavior | Resume condition |
|---|---|---|---|
| Evidence closure | Pre-flight | Block affected product/domain decision | Required evidence gathered or explicit constrained assumption approved |
| Founder decision | Revision | Return disputed artifacts to draft | Decision recorded and propagated |
| Technical spike | Pre-flight | Use fallback or narrow claim/scope | Measurable spike criterion passes |
| Traceability | Abort for implementation | Stop story generation/implementation | Every committed requirement reaches acceptance and future test evidence |
| Readiness audit | Abort for Phase 4 | Keep implementation blocked | No open P0 and connected slice is executable |
| Pilot/deployment | Escalation | Restrict to controlled demonstration | Privacy, operations, technical, stakeholder, and deployment gates pass |

## 11. Initial recovery exit conditions

Planning recovery may be declared complete only when:

- all foundation artifacts exist and agree;
- every source has a disposition;
- every contradiction has a winning decision or explicit blocker;
- role and data scope are approved;
- core state machines and recovery paths are approved;
- canonical domain/data contract is approved;
- stack/runtime/test choices are pinned;
- research claims align with available evidence;
- first connected slice has Given/When/Then acceptance scenarios;
- requirement traceability is mechanically complete for committed scope;
- the final readiness verdict distinguishes planning, implementation, and pilot/deployment.

## 12. Explicit non-claims

Approval of this charter means only: “Use this controlled recovery process.” It does not approve any role catalog, state machine, data model, model engine, RAG boundary, feature cut-line, technical stack choice, implementation phase, pilot, or deployment.
