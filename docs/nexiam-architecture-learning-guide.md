# NEXIAM Architecture Learning Guide

## From junior implementer to senior project oversight

> **Context update (2026-08-01):** NEXIAM is being rebuilt as a clean-slate greenfield project. The legacy codebase is a read-only reference, not a refactoring target. This learning guide remains valuable — the domain model, workflow invariants, architectural judgment, and capability boundaries it teaches apply directly to the greenfield build. Where the guide says "what code should change," read it as "what the new code must get right." See `docs/greenfield-architecture-plan.md` for the execution plan.

This guide is a study companion for the NEXIAM architecture audit. It is written for a developer who wants to understand the domain, why architectural decisions matter, how to review them, and how to protect the capstone from becoming a fragile collection of features.

The goal is not to memorize architecture vocabulary. The goal is to develop judgment:

- Which behavior is business-critical?
- Where should that behavior live?
- Which rule must be protected by the database?
- Which rule belongs in authorization policy?
- Which work must be transactional?
- Which domain patterns from the legacy code are worth preserving as design input?
- Which new feature must wait until a foundation decision is settled?

This guide is based on the verified audit documents in this repository. It is a learning guide, not a replacement for the evidence-backed audit.

## Start here

Read the documents in this order:

1. [This learning guide](./nexiam-architecture-learning-guide.md)
2. [Greenfield Architecture Plan](./greenfield-architecture-plan.md) — the execution plan
3. [Greenfield Implementation Order](./greenfield-implementation-order.md) — concrete build sequence with audit-finding traceability
4. [Master Architecture Audit and Refactor Blueprint](./master-architecture-picture.md) — domain and requirements reference (the deep technical findings)
5. [Audit-to-Greenfield Mission Plan](./audit-refactor-plan.md) — decision gates and OpenSpec tracking
6. [Controller Audit](./audit-controllers.md) — legacy workflow mapping (reference)
7. [Access-Control Inventory](./access-control-inventory.md) — role/permission requirements (reference)
8. [Architecture Audit](./architecture-audit.md) — historical reconnaissance (reference)

The greenfield plan is the execution authority. The master blueprint is the domain and requirements reference. This guide explains how to digest both.

---

## 1. First mental model: NEXIAM is a system, not a pile of screens

A junior view of NEXIAM often looks like this:

```text
Page -> Controller -> Database
```

That is too small for the real system. NEXIAM manages an admission lifecycle:

```text
Applicant submits
      |
      v
Application is reviewed
      |
      v
Applicant is accepted and activated
      |
      v
Applicant is assigned to an exam session
      |
      v
Proctor records attendance and submission
      |
      v
Scores are imported or entered
      |
      v
Guidance/test administration finalizes grading
      |
      v
Results and consultation are released
```

Every arrow has rules. For example:

- Can an application be accepted twice?
- Can two simultaneous requests book the last appointment slot?
- Can a proctor act on a session they are not assigned to?
- Can scores be changed after finalization?
- Can a notification be sent before the database transaction commits?
- Can an applicant submit another admission attempt next academic year?
- Can a staff user export applicant text that becomes an Excel formula?

Senior oversight means looking at the arrows and invariants, not only at the pages.

### The four layers to recognize

NEXIAM has four useful conceptual layers:

1. **Transport layer**
   - Routes, controllers, form requests, Inertia responses.
   - Converts HTTP input into an application request and converts a result back into a redirect or page response.

2. **Application capability layer**
   - Commands/use cases and queries.
   - Owns meaningful workflows such as accepting an application, publishing a session, recording attendance, or releasing a result.

3. **Policy and data-integrity layer**
   - Policies, database constraints, transactions, locks, state machines/projections, and audit rules.
   - Protects behavior even when callers race, fail, or are malicious.

4. **Adapter/integration layer**
   - Email, queues, spreadsheets, DOCX/PDF conversion, RAG providers, OAuth, and the future Python inference worker.
   - Keeps external-system assumptions from spreading throughout the application.

The target is not to build a complicated enterprise hierarchy. The target is to put each important rule where it can be protected and tested locally.

---

## 2. Vocabulary you should be able to use comfortably

### Module

A module is anything with an interface and an implementation. It may be a function, class, package, or larger feature slice.

Do not judge a module only by its file size. Ask whether it has a coherent interface and whether it hides useful complexity.

### Interface

An interface is everything a caller must know to use a module correctly:

- parameters and return values;
- required ordering;
- authorization assumptions;
- transaction expectations;
- error behavior;
- idempotency behavior;
- performance characteristics;
- what data is allowed to cross the seam.

A method signature alone is not the whole interface.

### Deep module

A deep module gives callers a small, understandable interface while hiding substantial behavior behind it.

```text
Small interface
       |
       v
Large protected behavior
```

Example:

```php
$command->execute($applicationId, $actor);
```

Behind that small interface, the command may:

- authorize the actor;
- lock the application;
- validate the current lifecycle state;
- write the application update;
- create the applicant activation record;
- write an audit event;
- dispatch a notification after commit;
- return a result or a domain error.

A shallow module merely forwards every detail to the caller. That creates a large interface without providing locality or leverage.

### Seam

A seam is a place where behavior can be changed without editing every caller.

Examples in NEXIAM:

- the interface between a controller and an admission command;
- the interface between document rendering and DOCX/PDF conversion;
- the interface between the application and Mixedbread retrieval;
- the interface between Laravel and a future Python OMR worker.

Seam placement is a design decision. Do not create an abstraction only because a file is large.

### Adapter

An adapter satisfies an interface at a seam. It translates between NEXIAM's needs and an external system.

Examples:

- `GoogleOAuthProviderAdapter`;
- spreadsheet reader adapter;
- PDF conversion adapter;
- Mixedbread retrieval adapter;
- Python inference worker adapter.

An adapter is justified when an external system varies, has a different data shape, or must be isolated for testing.

### Invariant

An invariant is a rule that must remain true.

Examples:

- an application reference number is unique;
- an applicant cannot exceed appointment capacity;
- an assigned proctor can access only the assigned session;
- a finalized score cannot be silently overwritten;
- an applicant setup token is single-use and expires;
- one exam session has at most one grading session, if that is the approved domain rule.

A senior reviewer asks: “Where is this invariant enforced?”

If the answer is only “the page checks it,” the system is not protected. Pages can be bypassed, requests can race, and jobs can call the application without that page.

### Projection

A projection is stored or computed data derived from authoritative state for reading or reporting.

For example, `booked_count` may be a projection of the applications assigned to an appointment. If it is stored, the system must define how it stays correct and how it is repaired. If it is derived, the read may be more expensive but has fewer drift risks.

### Characterization test

A characterization test records behavior that currently works and must survive refactoring.

It is not automatically a test of ideal behavior. It answers:

> “What does the current system actually do at this stable seam?”

Use characterization tests for retained workflows. Do not freeze behavior that the capstone intentionally plans to redesign, such as obsolete recommendation behavior or unsafe CSV-as-OMR assumptions.

### Idempotency

An operation is idempotent when repeating it does not produce an unintended second effect.

Examples:

- retrying a notification job should not send unlimited duplicate emails;
- retrying result release should not create duplicate release records;
- retrying an import confirmation should not duplicate applicants;
- retrying a webhook should not apply the same state transition twice.

### Transaction boundary

A transaction boundary defines which database changes succeed or fail together.

A transaction should contain the core state changes that must agree. Expensive external work usually happens after commit through an idempotent job.

```text
BEGIN
  lock target state
  validate current state
  update business state
  write mandatory audit record
COMMIT

AFTER COMMIT
  queue email
  generate expensive document
  call external adapter
```

Do not queue work that assumes committed data while the queue configuration allows it to run before commit.

---

## 3. What NEXIAM's legacy codebase teaches us

The audit is not a declaration that everything was bad. The legacy system contains valuable domain knowledge and proven patterns worth carrying into the greenfield build:

- Laravel 12 + Inertia + Svelte 5 is a coherent stack (retained for the rebuild);
- staff and applicant identities are separated conceptually (separate guards/providers);
- Form Requests are already used for transport validation (adopt this pattern from day one);
- policies exist and can be deepened (build them correctly from the start);
- the admission, exam, grading, release, reporting, notification, and audit workflows exist (these are the domain specification);
- `ApplicationPipelineService` contains useful transition vocabulary (use it as a state-machine design reference);
- the analyze/preview/confirm import flow is a good operational pattern (preserve this UX);
- a separate Python worker is a reasonable seam for OMR and inference workloads;
- the existing audit event vocabulary and redaction behavior are worth preserving as a design input.

> **Greenfield context:** The legacy code is a domain reference, not a codebase to port. Every module is written fresh, but the domain workflows, invariants, and integration patterns documented above inform the design. The deep technical findings in `master-architecture-picture.md` sections 3-8 are the specification source. The concrete build sequence is in `greenfield-implementation-order.md`.
---

## 4. The central architectural problem: ownership is fragmented

Several large controllers currently coordinate too many responsibilities. The most important problem is not simply that a controller has many lines. The deeper problem is that one workflow is spread across many callers.

A typical risky workflow may do this in several places:

```text
Controller A updates application status
Controller B updates pipeline status
Service C writes an audit record
Service D sends a notification
Controller E creates a grading record
Model F updates a milestone
```

This makes it difficult to answer:

- Which operation is authoritative?
- Which transaction owns the change?
- What happens if step 4 fails after steps 1–3 succeed?
- Which authorization rule applies?
- Can a retry safely repeat the operation?

### Target pattern

```text
HTTP Controller
      |
      v
AcceptApplication command
      |
      +--> authorization
      +--> lock and state validation
      +--> transaction
      +--> application update
      +--> applicant activation
      +--> audit write
      +--> after-commit notification
```

The controller should remain simple, but “simple controller” is not the goal by itself. The command must be cohesive and deep enough to protect the workflow.

### What should remain direct Eloquent?

Direct Eloquent is fine for simple local work:

```php
$course = Course::query()->where('is_active', true)->orderBy('name')->get();
```

A shallow wrapper around that query adds little value.

A command is justified when the operation has:

- multiple writes;
- a state transition;
- authorization-sensitive scope;
- a transaction;
- a reusable invariant;
- audit behavior;
- notifications or jobs;
- an external integration;
- import/export semantics.

Do not replace every query with a one-line “service.” That creates ceremony without depth.

---

## 5. Database thinking: why “it is inside a transaction” is not enough

A transaction protects a group of operations from partial commit, but it does not automatically make every calculation safe under concurrency.

### Example: `COUNT + 1`

Unsafe pattern:

```text
Request A: COUNT references = 100
Request B: COUNT references = 100
Request A: choose reference 101
Request B: choose reference 101
Request A: insert succeeds
Request B: unique constraint fails
```

A transaction around each request does not necessarily serialize the count. Use a locked counter, a database sequence where supported, or a collision-safe generator with bounded retry.

### Example: appointment capacity

Unsafe pattern:

```text
read appointment: booked_count = 9, max_slots = 10
read appointment: booked_count = 9, max_slots = 10
request A increments to 10
request B increments to 10
both create an assignment
```

A safe design uses one of these:

- row lock plus recheck inside the transaction;
- atomic conditional update where the update succeeds only below capacity;
- a durable assignment table whose unique/locking rules determine occupancy;
- a derived occupancy count with a controlled repair strategy.

### Schema is part of the architecture

If the application says “one grading session per exam session,” a normal non-unique index does not enforce that. If a foreign key cascades deletion of exam history, that is a product and retention decision, not merely a migration detail.

Review every important rule in three places:

1. request/application validation;
2. authorization and command logic;
3. database constraint or transaction strategy.

The strongest rules are enforced at more than one layer.

### Fresh versus upgraded installations

A migration may work correctly for a database that started six months ago but produce a different schema on a fresh installation. Both paths must be tested.

The audit found fresh-schema drift around:

- `aptitude_area_id` in applicant scores;
- nullable room references and delete actions;
- course preference delete behavior;
- grading-session uniqueness.

A scalable project treats fresh install, upgrade, rollback, and restore as separate verification paths.

---

## 6. Authorization: roles, policies, and temporary duty are different

These questions are not the same:

1. What permanent organization role does this person have?
2. What broad capability may this role use?
3. Which resource may this person access?
4. What temporary operational duty has this person been assigned?

A recommended model is:

```text
Permanent role/permission
        |
        v
Coarse capability
        |
        v
Policy checks resource, state, and scope
        |
        v
Temporary assignment grants operational duty
```

For example, a person may have permission to perform proctoring in general, but only an `exam_session_user` assignment should grant access to a particular exam session.

Do not force temporary duty into permanent role membership. A proctor assigned to Session A should not automatically gain access to every session.

### Authorization review questions

For every operation, ask:

- Who is the actor?
- What permanent capability is required?
- What resource is being acted on?
- What state must that resource be in?
- What temporary assignment or organizational scope applies?
- Can the same action be called through a job, import, or direct request?
- Does the policy check the same rule as the route middleware?

The audit found three divergent role catalogs in live code, the manuscript, and the proposed access inventory. Approve one authoritative catalog before migrating roles.

---

## 7. Authentication and security boundaries

### An identifier is not a credential

An applicant identifier is a lookup identity. It is not automatically proof that the person is the applicant.

A safe authentication contract must specify a separate proof mechanism:

- password;
- passkey;
- one-time code;
- or another approved credential.

It must also specify recovery, rotation, throttling, failed-attempt handling, and audit behavior.

### Setup and reset tokens

A secure token lifecycle generally requires:

1. generate random token;
2. show the raw token only through the intended delivery channel;
3. hash it before storage;
4. compare presented token against the hash;
5. enforce short expiry;
6. consume atomically exactly once;
7. clear or rotate it after success;
8. rate-limit requests and attempts;
9. avoid logging or exposing the raw token.

Plaintext storage makes database exposure more damaging and complicates safe reuse prevention.

### Google OAuth account linking

“Google email equals local email” is not a complete account-linking policy. The system needs explicit rules for:

- verified provider email claims;
- trusted domains, if domain restriction is intended;
- first login versus linking an existing account;
- user consent before linking;
- state validation and callback protection;
- route throttling;
- role assignment;
- audit events;
- account-switch and takeover tests.

OAuth is not only a login button. It is an identity-linking capability.

---

## 8. Frontend architecture: fix contracts before callers

The frontend quality ledger is large, but many diagnostics are cascades from shared primitives.

That means this is usually the wrong first move:

```text
Add disabled={false} to 200 callers
```

If the shared `Button` contract incorrectly makes `disabled` required, fix the shared contract once. That improves every caller and reduces the ledger at its source.

### Frontend priorities

1. repair runtime/API mismatches;
2. fix shared primitive prop contracts;
3. define typed page-prop and form-data boundaries;
4. repair accessibility and nested interactive controls;
5. extract genuinely shared Create/Edit form modules;
6. migrate new or substantially rebuilt modules to TypeScript;
7. reduce legacy JS incrementally instead of translating everything mechanically.

### Important frontend distinction

A large Svelte file may be a symptom, but line count is not an architecture law. Ask:

- Does the page own several unrelated capabilities?
- Is state duplicated across pages?
- Is the component difficult to test through a stable interface?
- Are the same validation rules copied in multiple forms?
- Are raw HTML and browser globals crossing trust or SSR seams?

### Current runtime defects are more urgent than formatting

Examples found by the focused frontend audit include:

- undefined Start-session handler;
- obsolete `form.setData` calls;
- dead assigned `onFinish` properties;
- incorrect Inertia store access;
- non-reactive URL checks;
- nested interactive controls;
- raw HTML trust-boundary concerns.

Formatting is useful, but a formatted broken workflow is still broken.

---

## 9. Document, import, and export safety

NEXIAM handles files and generated documents. That means there are multiple trust boundaries:

```text
Uploaded file -> parser -> imported records -> database -> export -> staff office software
Template -> placeholder compiler -> HTML/DOCX/PDF renderer -> browser/download
```

### Uploaded spreadsheet risks

Imports need limits and defenses against:

- oversized workbooks;
- decompression/resource exhaustion;
- formula/external-link behavior;
- malformed files;
- unsupported reader types;
- SSRF/RCE-adjacent dependency behavior;
- abandoned temporary preview files;
- duplicate confirmation requests.

### Generated document risks

Do not treat every template placeholder as trusted because an administrator can edit the template. Applicant values can still be untrusted.

Use typed placeholder categories:

```text
escaped_text     applicant name, address, email
trusted_html     approved structural fragment only
qr_svg           generated and validated QR output
document_text    DOCX/ODT-specific escaped text
table_rows       structured rows, not concatenated HTML
```

Do not escape the entire finished document blindly; that may destroy intended structure. Escape according to the output context.

---

## 10. AI, RAG, OMR, and future scaling

The capstone features should not be treated as one undifferentiated “AI module.” They have different safety and architectural contracts.

### Institutional RAG

Answers about policies, requirements, schedules, and office procedures should use a versioned institutional corpus with:

- citations;
- confidence/refusal behavior;
- source ownership;
- ingestion status;
- prompt-injection defense in depth;
- a fallback to a responsible office.

### Applicant-context retrieval

The applicant may be allowed to retrieve their own:

- results;
- schedule;
- approved assignments;
- other explicitly authorized records.

This requires authenticated, row-level authorization. “The user is logged in” is not enough; the retrieval must be scoped to that applicant.

### Recommendation engines

Placement or course recommendations are decision-support capabilities, not merely policy retrieval. They need:

- versioned engine configuration;
- feature definitions;
- provenance of input data;
- model/algorithm version;
- reproducible output;
- confidence and explanation;
- counselor oversight;
- a correction path.

### OMR and computer vision

OMR is an asynchronous inference workflow:

```text
Upload image/batch
      |
      v
Validate and store original
      |
      v
Python CV worker
      |
      v
Confidence-scored extraction
      |
      v
Human review for uncertain rows
      |
      v
Approved score import
      |
      v
Normal grading/release workflow
```

The worker should not directly become the authority for released grades. Laravel remains the transactional system of record, while the worker returns evidence and a proposed result.

---

## 11. How to review a proposed change like a senior

For every feature or refactor, use this sequence.

### Step 1: Name the capability

Avoid “update controller.” Use a business capability:

- Accept application;
- Assign applicant to exam session;
- Record attendance;
- Finalize grading;
- Release result;
- Import scores;
- Generate admission slip.

### Step 2: Write the invariant

Example:

> An applicant cannot be assigned beyond the session's available capacity, even when two requests arrive concurrently.

### Step 3: Name the actor and scope

Example:

> A test administrator may assign applicants only within the academic-year/session scope they are authorized to manage.

### Step 4: Choose the owner

Ask which module owns the rule:

- controller for HTTP adaptation only;
- command for mutation and transaction;
- query for composed reads;
- policy for authorization;
- database for uniqueness and referential integrity;
- adapter for external systems.

### Step 5: Define failure behavior

What happens if:

- the record is already in the next state;
- another request wins the race;
- email fails after commit;
- the parser rejects one row in a batch;
- the external AI provider times out;
- the browser retries?

### Step 6: Define the test seam

The test should cross the same interface the production caller uses. If the test needs to know ten internal helper methods, the module may be too shallow or the seam may be misplaced.

### Step 7: Check migration and rollback

For schema work, verify:

- fresh database;
- upgraded database;
- existing data;
- rollback or recovery plan;
- indexes and constraints;
- nullable values;
- delete behavior;
- production database-engine behavior.

### Step 8: Check the ledger

The change must:

- not grow inherited debt;
- make new code green;
- improve or retire an existing problem;
- preserve checker exit codes;
- avoid hiding findings by adding suppressions.

### Step 9: Check traceability

The change should connect:

```text
Capstone objective
      -> architecture decision
      -> OpenSpec requirement
      -> implementation module
      -> migration/invariant
      -> test scenario
      -> operational evidence
```

If the chain stops at a vague feature description, the change is not ready.

---

## 12. The NEXIAM learning path

Use this as a paced study plan. Do not rush through it.

### Lesson 1 — Read the current lifecycle

Trace one applicant from submission to release.

Answer:

- Which controller receives each action?
- Which model changes?
- Which status fields change?
- Which notification or job runs?
- Which policy protects the action?
- What happens when the action is repeated?

### Lesson 2 — Read one large workflow controller

Start with `ApplicationController`.

Do not immediately refactor it. Mark each method as:

- transport-only;
- query/read;
- mutation;
- state transition;
- authorization decision;
- audit/notification side effect;
- document/import behavior.

Then identify which methods should belong to one deep capability module.

### Lesson 3 — Study one race condition

Use reference-number generation or appointment capacity.

Draw the interleaving of two requests. Then write the invariant and compare three possible solutions:

- row lock;
- atomic conditional update;
- database sequence/counter;
- derived projection.

### Lesson 4 — Study authorization scope

Compare:

- permanent role;
- permission;
- policy;
- exam-session assignment.

Write an authorization matrix for one proctor action, including allowed and denied cases.

### Lesson 5 — Study the frontend seam

Inspect one shared primitive and one Create/Edit pair.

Ask:

- What is the public interface?
- Which props are incorrectly required?
- Which callers duplicate validation?
- Which state is local and which comes from the page?
- Which browser/API assumptions are obsolete?

### Lesson 6 — Study trust boundaries

Trace one applicant name through:

```text
HTTP input -> validation -> database -> template -> HTML/PDF/export
```

Mark every point where the value changes context. Decide where escaping belongs.

### Lesson 7 — Study RAG and inference as separate capabilities

Write two separate contracts:

1. institutional policy question answering;
2. applicant-specific authorized retrieval.

Then write a third contract for OMR proposed scores and human approval.

### Lesson 8 — Practice senior review

Take one proposed change and produce:

- capability name;
- actor/scope matrix;
- invariant list;
- transaction boundary;
- failure behavior;
- migration plan;
- test scenarios;
- ledger impact;
- rollback plan.

Only after this exercise should the first OpenSpec change be created.

---

## 13. Questions to ask in team review

Use these questions during Jaypee, Christine, Prince John, and future team discussions:

### Architecture

- What capability does this change own?
- Is the new module deep or merely a pass-through?
- Does the interface hide complexity or expose it to every caller?
- Is the seam placed where behavior can change safely?

### Data

- What must be unique?
- What happens under concurrent requests?
- What is authoritative state versus a projection?
- What happens when a related record is deleted?
- Does fresh install match upgraded install?

### Authorization

- Who may do this?
- On which resource?
- In which lifecycle state?
- Under which organizational or session scope?
- Can a queued job or import bypass the same rule?

### Security

- Is the input trusted, escaped, or merely validated?
- Does a stored value later become HTML, SQL, a spreadsheet formula, a shell argument, or a prompt?
- Can a token be reused or exposed?
- Is an external identity being linked automatically?

### Operations

- Can it be retried safely?
- What happens after a timeout?
- Are failed jobs visible?
- Is the operation observable and auditable?
- Can the data be repaired if a projection drifts?

### Capstone traceability

- Which research objective does this implement?
- Which ADR/OpenSpec requirement defines it?
- Which test demonstrates it?
- Which evidence supports the claim in the manuscript?

---

## 14. The implementation sequence in plain language

> **Updated 2026-08-01.** The greenfield build replaces the legacy gate sequence. The full detailed implementation order with audit-finding traceability is in [`greenfield-implementation-order.md`](./greenfield-implementation-order.md). Below is a plain-language summary of the major phases.

Do not begin with OMR, dashboards, or capstone features. Build the foundation first.

### Phase 0 — scaffold and tooling

- clean Laravel + Inertia + Svelte 5 (TypeScript) project;
- all quality tools at zero tolerance from commit 1;
- CI pipeline that is green and stays green.

### Phase 1 — domain model foundation

- core tables with database-enforced constraints (not application-only checks);
- locked academic-year activation (not racy updates);
- transactional room booking (not drifting counters);
- audit event table with nullable subjects (not sentinel IDs);
- architecture tests from day one.

### Phase 2 — identity and access

- settle the authoritative role catalog, proctor model, identifier auth, and OAuth policy;
- staff and applicant authentication with separate guards;
- hashed tokens with atomic one-time use;
- proctor as session-assignment duty, not permanent role;
- policy base classes for each capability boundary.

### Phase 3 — admissions core

- application submission with safe reference generation (DB sequence, not COUNT + 1);
- pipeline state machine with single source of truth;
- acceptance, rejection, and reopen with archive semantics (no hard-delete of history);
- atomic appointment booking.

### Phase 4 — examination and proctoring

- exam sessions with scoped uniqueness (not global applicant constraint);
- assignment-based proctor access evaluated in policy;
- transactional attendance and submission recording.

### Phase 5 — assessment and grading

- hardened spreadsheet import (latest dependencies, formula neutralization, size limits);
- unique grading session per exam session (enforced by database);
- atomic pipeline progression on finalization.

### Phase 6 — consultation, release, and documents

- typed placeholder system for document templates (not raw string replacement);
- context-specific escapers per placeholder type;
- formula neutralization on every export boundary.

### Phases 7-8 — audit chain and notifications (parallel)

- tamper-evident append-only audit chain with external checkpoints;
- notification preferences, idempotency, and after-commit delivery.

### Phases 9-10 — RAG and capstone research features

- scoped RAG with confidence/citation/refusal contract;
- OMR worker, engine registry, Yukee/BGE adapters;
- counselor triage and Program Head dashboards.

### Phases 11-13 — dashboards, messaging, hardening

- role-scoped dashboard query facade;
- in-app messaging with RAG handoff;
- offline proctor sync (only after conflict semantics approved);
- E2E defense journeys, security audit, deployment.

The order is not bureaucracy. It prevents a capstone feature from depending on an unsafe or undefined foundation. Each phase in the [detailed implementation order](./greenfield-implementation-order.md) traces back to the specific audit finding it addresses.

---

## 15. What “good senior oversight” looks like

You are not expected to know every Laravel or Svelte detail immediately. Senior oversight is the ability to keep asking the right system questions:

- What does this operation promise?
- What can go wrong between two database writes?
- What is the smallest useful interface?
- Which module should own the invariant?
- What must remain true after a retry?
- Who is authorized, and at what scope?
- What is trusted at this boundary?
- How do we know the refactor preserved behavior?
- How does this capstone feature connect to the research claim?
- Can the team explain and test the decision six months later?

A senior architect is not the person who creates the most abstractions. It is the person who creates enough structure that behavior becomes understandable, protected, testable, and changeable.

For NEXIAM, the guiding sentence is:

> Build clean modules with deep interfaces, enforce important invariants at the correct seams, and let every capstone feature arrive with evidence, authorization, tests, and a reversible migration path.
---

## 16. Quick glossary

| Term | Plain meaning |
|---|---|
| Adapter | A concrete implementation at a seam for an external or variable system |
| After commit | Work dispatched only after the database transaction succeeds |
| Characterization test | A test that records stable existing behavior before refactoring |
| Command | A module that performs a meaningful mutation/workflow |
| Deep module | Small interface hiding substantial, cohesive behavior |
| Invariant | A rule that must remain true |
| Idempotency | Safe behavior when the same request is repeated |
| Interface | Everything callers must know to use a module correctly |
| Locality | Keeping change and knowledge concentrated in one place |
| Policy | Resource/state/scope authorization decision |
| Projection | Stored or computed data derived from authoritative state |
| Query module | A module that owns a composed read model or read operation |
| Seam | A location where behavior can change without editing all callers |
| Strangler modernization | Replacing parts of an existing system gradually instead of rewriting all at once (the legacy strategy; NEXIAM now uses a greenfield rebuild instead) |
| Trust boundary | A point where data changes from untrusted to trusted or enters a new execution context |

---

## Final study checkpoint

Before moving into concrete implementation, you should be able to explain in your own words:

1. Why the audit findings justify a greenfield rebuild rather than in-place refactoring, and what the legacy codebase contributes as a reference.
2. Why not every Eloquent query needs a service wrapper.
3. Why a command is appropriate for accepting an application or releasing a result.
4. Why a transaction alone does not fix `COUNT + 1` or appointment overbooking.
5. Why permanent roles and proctor session assignments are different.
6. Why applicant text must be escaped differently in HTML, DOCX, PDF, and spreadsheet contexts.
7. Why RAG policy answers and applicant-specific retrieval need separate authorization contracts.
8. Why the 12 decision gates must be resolved before (not during) building the affected module.
9. Why the build sequence starts with identity and admissions core, not with OMR or dashboards.
10. How an audit finding (P0.x, P1.x) maps through the greenfield implementation order to a specific build step, and how that step connects to a capstone evidence claim.

If you can explain those ten points and defend the answers using the master blueprint and greenfield plan, you are ready to participate in the concrete build phase with senior-level oversight.

**Next step:** open [`greenfield-implementation-order.md`](./greenfield-implementation-order.md) and identify which decision gates must be resolved first, then begin Phase 0 (scaffold and tooling).
