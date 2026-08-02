# Solutioning Review and Artifact Plan

**Artifact:** NEXIAM-GF-16  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Turn the recovered greenfield foundation into a reviewable implementation-grade HLD, LLD, schema, interface, UI, operations, and delivery package without silently promoting unresolved assumptions.

## 1. Current assessment

The foundation suite is detailed enough to begin solutioning review, but not detailed enough to begin domain implementation.

It currently provides:

- authority and evidence rules;
- product scope and success boundaries;
- role, capability, and data-scope proposals;
- conceptual domain vocabulary and invariants;
- workflow/state and failure contracts;
- security/privacy/audit obligations;
- platform/deployment standards;
- research and technical validation gates;
- a connected vertical slice;
- traceability and decision matrices;
- a conceptual ERD and deployment topology.

It does not yet provide canonical implementation contracts for:

- system containers and trust boundaries;
- module ownership and dependency direction;
- aggregate/transaction boundaries;
- schema columns, foreign keys, indexes, checks, delete behavior, and migration order;
- commands, queries, events, jobs, idempotency keys, and typed failures;
- authorization enforcement points and scope-resolution algorithms;
- Inertia page, route, form, and UI-state contracts;
- provider/worker adapter envelopes and operational health;
- observability, backup/restore, and incident runbooks;
- executable BMAD epics/stories and OpenSpec change packages.

Therefore:

- planning recovery is active;
- HLD/LLD review is authorized as a draft activity;
- schema/domain implementation remains blocked;
- pilot/production clearance remains blocked.

## 2. Promotion rule

No solutioning artifact becomes implementation authority until:

1. its upstream decision IDs are resolved or explicitly conditional;
2. the artifact states its assumptions and open gates;
3. cross-artifact terminology and lifecycle checks pass;
4. the relevant acceptance scenarios and future tests are mapped;
5. the founder review checkpoint for that layer is recorded;
6. the readiness matrix is updated.

A document can be complete as a draft while still being blocked for implementation.

## 3. Canonical artifact chain

```text
Evidence and stakeholder validation
        ↓
Product/scope and approved decision matrix
        ↓
Domain/workflow/security contracts
        ↓
GF-17 System HLD
        ↓
GF-18 Module and Domain LLD
        ↓
GF-19 Schema/ERD/Constraint Catalog
        ↓
GF-20 Application/Integration Contracts
        ↓
GF-21 Authorization Enforcement Design
        ↓
GF-22 UI/Route/State Contract
        ↓
GF-23 Operations/Observability/Recovery Design
        ↓
GF-24 BMAD/OpenSpec Implementation Package
        ↓
Connected-slice implementation and evidence
```

The chain is directional. A downstream artifact may expose an upstream ambiguity, but it cannot resolve product policy silently.

## 4. Review stages

### Stage A — Foundation consistency

**Objective:** Ensure the existing source-of-truth suite agrees with the latest founder decisions.

**Checks:**

- applicant challenge timing and acceptance independence;
- role and support-staff authority;
- Guidance ownership of exam and consultation workflow;
- F2F default and online reservation branch;
- Applicant No/Reference answer-sheet binding;
- durable identity/history and non-destructive correction;
- no stale admission-slip or birthday-only authentication assumptions;
- no stale stack/test/runtime authority.

**Exit evidence:** mechanical search report, corrected artifact diff, unresolved contradictions list.

### Stage B — System HLD

**Objective:** Decide the system shape before class, table, route, or component design.

**Review sections:**

1. context and actors;
2. trust boundaries;
3. deployment/container topology;
4. modular-monolith capability boundaries;
5. synchronous versus asynchronous paths;
6. data ownership and external adapter boundaries;
7. security, privacy, audit, and failure cross-cuts;
8. environments and operational modes;
9. HLD decision log.

**Exit gate:** module boundaries and dependency direction are accepted or explicitly held.

### Stage C — Domain/application LLD

**Objective:** Make each connected workflow implementable without guessing.

**Review sections:**

- aggregate roots and invariants;
- value objects and enums;
- commands and transaction boundaries;
- queries and read models;
- domain events and after-commit effects;
- typed failure taxonomy;
- correction/reversal/versioning rules;
- concurrency and idempotency behavior;
- module dependency rules.

**Exit gate:** every connected-slice command has actor, scope, preconditions, state change, side effects, failure, retry, and audit behavior.

### Stage D — Persistence and interface contracts

**Objective:** Translate the domain LLD into durable storage and stable application boundaries.

**Artifacts:**

- schema/constraint/migration catalog;
- command/query/event/job contract catalog;
- external integration/adapter envelopes;
- authorization enforcement design;
- UI route/page/form/state contract.

**Exit gate:** no “model has many” or “service handles it” ambiguity remains for the first slice.

### Stage E — Operations and delivery plan

**Objective:** Make the system buildable, testable, observable, recoverable, and promotable.

**Artifacts:**

- Compose/environment topology;
- health/readiness and degraded-mode behavior;
- queue/provider retry and dead-letter runbooks;
- backup/restore and data-retention runbooks;
- test/evidence matrix;
- BMAD epics/stories;
- OpenSpec package for the first change;
- implementation dependency graph.

**Exit gate:** the first vertical slice can be implemented from the package by another engineer without oral tribal knowledge.

## 5. Planned solutioning artifacts

| Artifact | Purpose | Depends on | Current status |
|---|---|---|---|
| GF-17 System HLD | Context, containers, boundaries, deployment, cross-cutting concerns | GF-01–GF-15 | Drafting/review |
| GF-18 Module and Domain LLD | Aggregates, commands, queries, events, dependencies, failure semantics | GF-17 + GF-05–GF-08 | Not started |
| GF-19 Schema, ERD, and Constraint Catalog | Tables, columns, keys, indexes, checks, deletes, migration order | GF-18 | Not started |
| GF-20 Application and Integration Contract Catalog | HTTP actions, queries, jobs, events, adapter envelopes, typed failures | GF-18/GF-19 | Not started |
| GF-21 Authorization Enforcement Design | Policies, scope resolution, capability checks, break-glass, audit views | GF-18/GF-04/GF-08 | Not started |
| GF-22 UI, Route, and State Contract | Page inventory, route responsibilities, form states, loading/empty/error/recovery states | GF-20/GF-21 | Not started |
| GF-23 Operations and Recovery Design | Compose, environments, health, metrics, logs, queues, backups, runbooks | GF-17/GF-20/GF-08 | Not started |
| GF-24 BMAD/OpenSpec Implementation Package | Epics, stories, acceptance, dependencies, first change package | GF-18–GF-23 | Not started |

The conceptual ERD and deployment Mermaid files remain illustrations until GF-19 and GF-23 replace them as authority.

## 6. Required detail standard

Every implementation-facing contract must answer:

- Who is the actor?
- What capability and resource scope are required?
- What is the authoritative aggregate or read model?
- What preconditions are checked?
- What state changes atomically?
- What database constraint protects the invariant?
- What event/audit record is written?
- What asynchronous side effects occur after commit?
- What happens on timeout, duplicate request, concurrent request, partial failure, or retry?
- What does the user see in success, empty, loading, denied, stale, and recovery states?
- What test and runtime evidence proves the behavior?

If an answer is unavailable, the contract must say `HOLD`, `CONDITIONAL`, or `DEFERRED`; it must not use an invented default.

## 7. Review protocol with David

Review one layer at a time:

1. Read the layer summary.
2. Review the proposed boundary or decision.
3. Identify contradictions or missing institutional rules.
4. Choose `GO`, `GO WITH CONDITION`, `REVISE`, `HOLD`, or `NO-GO`.
5. Record exact wording and affected artifacts.
6. Propagate the decision.
7. Run consistency checks.
8. Only then open the next layer.

The review order for HLD is:

1. system context;
2. container topology;
3. capability/module boundaries;
4. data ownership;
5. runtime and queue topology;
6. security/trust boundaries;
7. degraded modes;
8. HLD exit gate.

## 8. Initial known holds

These are intentionally not guessed during HLD/LLD drafting:

- applicant delivery channel and final credential model;
- exact institutional admission-status vocabulary and historical mapping;
- Registrar versus Guidance validation details for accepted operating procedures;
- retention periods and data-subject rights procedures;
- audit threat model, key custody, and checkpoint destination;
- OMR template/device/ground-truth/threshold evidence;
- program mapping and metric dictionary;
- production provider and object-storage credentials/configuration;
- whether bounded inquiry threads remain in the capstone;
- whether offline proctoring remains in scope.

## 9. Recorded review outcomes

### HLD checkpoint 1 — system shape and module boundaries

**Outcome:** GO as a working baseline; not final HLD promotion.  
**Founder response:** `go`  
**Baseline:** Continue with the proposed modular monolith, explicit core containers, adapter-backed optional services, and the following twelve capability modules:

1. Identity & Access
2. Reference Data & Academic Configuration
3. Admissions
4. Examination
5. Assessment & Grading
6. Consultation & Release
7. Documents & Reporting
8. Communications
9. Audit & Compliance
10. Knowledge & RAG
11. Decision Support
12. Platform/Operations adapters

**Scope of approval:** GF-18, GF-19, and GF-20 may use these boundaries as their working design input.  
**Not approved:** final schema, implementation code, production provider selection, pilot, or production deployment.  
**Next review:** HLD data ownership/runtime boundaries and LLD aggregate/transaction contracts.

## 10. Review deliverable definition

The solutioning review is complete only when the package contains:

- an approved or conditionally approved HLD;
- an approved or conditionally approved module/domain LLD;
- a migration-ready schema catalog and ERD;
- typed command/query/event/job/integration contracts;
- enforceable authorization and scope rules;
- page/route/UI-state contracts;
- operations and recovery runbooks;
- a traceable BMAD/OpenSpec implementation package;
- a fresh readiness audit and explicit planning/system/pilot verdict.

Until then, the correct state remains: planning active, implementation gated, pilot blocked.
