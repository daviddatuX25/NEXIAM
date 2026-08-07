# Architecture Decision Record Catalog

**Artifact:** NEXIAM-GF-15  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Identify the architecture decisions that must be promoted after founder/product decisions and before implementation stories.  
**Authority:** These are decision candidates, not accepted ADRs. Their numbered founder rows govern.

## 1. ADR lifecycle

Statuses:

- `proposed`
- `accepted`
- `accepted_with_conditions`
- `rejected`
- `superseded`
- `deprecated`

Each accepted ADR must contain:

- status/date/decision owner;
- context and problem;
- upstream decisions/requirements;
- considered options and evidence;
- decision and scope;
- positive/negative consequences;
- security/privacy/data/operations impact;
- migration/rollback or reversal strategy;
- measurable verification;
- supersession trigger.

ADR approval cannot override the product/domain/security/research contract. If an ADR exposes an upstream contradiction, planning reopens upstream first.

## 2. Dependency order

```text
ADR-001 Greenfield workspace
  -> ADR-002 Modular-monolith stack
  -> ADR-003 Database and schema integrity
  -> ADR-004 Identity and admission-attempt model
  -> ADR-005 Authorization and contextual duties
  -> ADR-006 Command/state/event ownership
     -> ADR-007 Transaction, audit, and after-commit effects
     -> ADR-008 Queue/idempotency/reconciliation
     -> ADR-009 Private files, uploads, and typed documents
     -> ADR-010 Inference worker and fixed engine adapters
        -> ADR-011 OMR human-review boundary
        -> ADR-012 RAG authorization/citation/refusal boundary
     -> ADR-013 Audit threat model and checkpoints
     -> ADR-014 Compose/deployment topology
     -> ADR-015 Offline synchronization (conditional)
```

## 3. Proposed ADR catalog

### ADR-001 — Isolate the greenfield implementation from legacy evidence

- **Status:** proposed
- **Upstream:** D-003, D-004, D-005
- **Candidate decision:** Freeze/reference the legacy repository as evidence and build the new application in an explicitly selected clean repository or isolated worktree.
- **Rationale:** The current moving dirty worktree and legacy structural defects make an all-green baseline and provenance impossible inside an ambiguous mixed workspace.
- **Consequences:** Approved documentation/reference fixtures must be imported deliberately; historical source remains accessible but non-authoritative.
- **Alternative:** Clean the current repository in place. Rejected by default because it risks accidental carryover and mixes historical changes.
- **Verification:** Clean Git status at scaffold start, explicit legacy reference, lockfile/toolchain provenance.

### ADR-002 — Use a Laravel modular monolith with Inertia/Svelte presentation

- **Status:** proposed
- **Upstream:** D-055, D-068
- **Candidate decision:** PHP 8.4/Laravel 13 application with capability/deep modules, Inertia 3, Svelte 5 + shadcn-svelte, TypeScript 5.9, and Tailwind 4; no service split for core domain workflows. (E1b implemented the Svelte presentation kit; React revision revoked.)
- **Rationale:** One transactional boundary and delivery unit fit the capstone team while allowing clear module ownership and adapter boundaries.
- **Consequences:** Module dependency rules and architecture tests are required; controllers/pages remain thin; domain/application logic cannot return framework responses.
- **Alternative:** Microservices. Rejected for unnecessary operational/transactional complexity.
- **Verification:** Dependency/architecture tests, module ownership map, connected vertical-slice trace.

### ADR-003 — Pin PostgreSQL as the greenfield integrity authority

- **Status:** proposed, conditional
- **Upstream:** D-057
- **Candidate decision:** PostgreSQL is the greenfield production, integration, migration, locking, backup, and restore authority from E0; the supported major version is pinned before scaffold promotion; SQLite is limited to parity-safe fast tests; pgvector is optional and requires its own extension, privacy, index, backup, restore, and verification evidence.
- **Rationale:** The founder-revised D-057 explicitly separates greenfield authority from the current MySQL Compose evidence and requires the database-specific behavior used by the domain contract to be tested against the actual target engine.
- **Consequences:** Compose, CI, schema, migrations, JSON/full-text/vector assumptions, race tests, backup/restore rehearsal, and operational runbooks must all use the pinned PostgreSQL major. Current MySQL configuration remains historical/current-app evidence and cannot silently govern greenfield design.
- **Alternative:** MySQL/MariaDB as greenfield authority. Rejected for the target plan after D-057 unless a new explicit decision reopens the engine choice and propagates it through every downstream artifact.
- **Verification:** Fresh/upgrade/constraint/concurrency/catalog/locking/backup/restore evidence on the pinned PostgreSQL major, plus parity evidence for any SQLite tests and separate pgvector evidence if enabled.

### ADR-004 — Separate durable applicant identity from cycle-scoped admission attempts

- **Status:** proposed
- **Upstream:** D-024, D-027–D-035, D-073, D-074
- **Candidate decision:** Durable ApplicantIdentity owns repeatable AdmissionAttempts; a post-submission access challenge may establish an optional Portal Account that authenticates that identity independently of acceptance; all examination/scoring/release records attach to an attempt.
- **Rationale:** Resolves legacy applicant/application relinking ambiguity, reopen deletion, and future-cycle/retake constraints.
- **Consequences:** Duplicate-resolution, identity merge/restriction, retention, and historical account access require explicit policy.
- **Alternative:** One Applicant record per attempt. Rejected by default because it duplicates identity and obscures longitudinal ownership.
- **Verification:** ERD/constraints, cycle-repeat tests, reopen/history and own-data tests.

### ADR-005 — Use capabilities and scoped contextual duties, not role-string routing

- **Status:** proposed
- **Upstream:** D-014–D-023, D-077, D-080
- **Candidate decision:** Permanent base roles grant bounded capabilities/scopes; Proctor is an ExamSession DutyAssignment; policies enforce resource ownership.
- **Rationale:** Eliminates route/policy mismatch, permanent contextual privilege, and broad cross-office data access.
- **Consequences:** Capability and scope persistence, revocation, role ceilings, and policy matrices are required.
- **Alternative:** Fixed role enums checked in routes/controllers. Rejected as too coarse and already contradictory.
- **Verification:** Allowed/denied/cross-scope/expired-duty tests.

### ADR-006 — Commands own transitions; workflow stage is a projection

- **Status:** proposed
- **Upstream:** D-028–D-045, D-074–D-077
- **Candidate decision:** Aggregate commands own admission, exam, grading, consultation, and release transitions; applicant/dashboard stage derives from authoritative milestones/events.
- **Rationale:** Prevents controller mutations, dual workflow truth, illegal backward pipeline changes, and fragmented bulk behavior.
- **Consequences:** Projection rebuild/repair, expected versions, typed failures, and explicit reversal commands are required.
- **Alternative:** A single mutable pipeline status. Rejected because independent workflows and reversals cannot be represented safely.
- **Verification:** Transition/illegal-state/reversal/projection rebuild tests.

### ADR-007 — Commit state and mandatory audit atomically; perform external effects after commit

- **Status:** proposed
- **Upstream:** D-052, D-060, D-067
- **Candidate decision:** Invariant checks, state, and mandatory canonical audit event share the owning transaction; external effects are guaranteed after commit through approved Laravel event/outbox mechanics.
- **Rationale:** Prevents accepted/released state from diverging from audit evidence or being rolled back by provider failure.
- **Consequences:** Failed audit blocks critical command; failed external delivery becomes visible retryable operation.
- **Alternative:** Inline mail/provider calls or best-effort post-hoc logging. Rejected.
- **Verification:** Transaction rollback, after-commit, duplicate dispatch, audit-failure tests.

### ADR-008 — Use idempotent database-backed queues and explicit reconciliation

- **Status:** proposed
- **Upstream:** D-060, D-065–D-067
- **Candidate decision:** Laravel database queue for initial scale, dedicated worker, versioned/idempotent jobs, bounded retries, terminal failures, and operator reconciliation queues.
- **Rationale:** Minimize infrastructure while preserving reliable side effects and observable recovery.
- **Consequences:** Queue tables and database load are accepted initially; Redis remains a future measured ADR.
- **Alternative:** Synchronous-only or untracked retries. Rejected.
- **Verification:** crash/retry/replay/dead-letter/operator-retry evidence.

### ADR-009 — Keep files private and render typed documents through format adapters

- **Status:** proposed
- **Upstream:** D-033–D-035, D-062, D-066, D-078
- **Candidate decision:** Private Laravel filesystem for controlled demo with object-storage adapter for pilot; typed template placeholders, checksums, versions, quarantine, and format-specific encoding.
- **Rationale:** Addresses upload, path, HTML/DOCX, spreadsheet formula, provenance, retention, and backup risks.
- **Consequences:** Public predictable file paths and raw string replacement are prohibited.
- **Alternative:** Store generated files in public disk or render arbitrary templates. Rejected.
- **Verification:** malicious upload/template/export fixtures, authorized retrieval, backup/restore.

### ADR-010 — Use one fixed-contract Python inference worker with approved adapters

- **Status:** proposed, conditional
- **Upstream:** D-010, D-050, D-054, D-061
- **Candidate decision:** One private authenticated/versioned Python worker supports only compiled/approved OMR/BGE adapters; no direct DB access or arbitrary endpoint/class/UI registration.
- **Rationale:** Isolates non-PHP dependencies without turning a capstone into an unsafe generic plugin platform.
- **Consequences:** Manual/core workflow must survive worker outage; schema/version/provenance/health contracts are mandatory.
- **Alternative:** Python embedded in request workers, one sidecar per engine, or runtime plugin URLs. Rejected/default-deferred.
- **Verification:** contract compatibility, timeout, outage, invalid-version, idempotency, provenance tests.

### ADR-011 — OMR proposes; authorized review/import creates scores

- **Status:** proposed, spike-gated
- **Upstream:** D-007, D-035, D-046, D-047
- **Candidate decision:** OMR produces versioned observations and confidence; uncertain/malformed sheets enter review; only authorized reviewed import writes ScoreRecords.
- **Rationale:** Maintains grading integrity and manual fallback under bounded technical evidence.
- **Consequences:** Raw-input privacy, ground-truth corpus, confidence thresholds, review queue, and benchmark report are required.
- **Alternative:** Fully automatic score writes. Rejected until materially stronger evidence and institutional authorization exist.
- **Verification:** wrong/ambiguous input, false-auto-accept, exact-score, replay/import, outage/manual tests.

### ADR-012 — Authorize retrieval before RAG and require citations, refusal, and typed task gates

- **Status:** proposed, conditional
- **Upstream:** D-008, D-051, D-067, D-079
- **Candidate decision:** Application policy selects the approved institutional corpus and the caller's authorized own-data context before prompt construction. Policy answers cite and link to the governing source; own-data answers obey release/scope gates; authorized staff task assistance uses typed UI-equivalent backend commands with the same capability, confirmation, idempotency, and audit rules as ordinary UI actions. Unsupported or unauthorized requests refuse and offer the bounded fallback; no placement advice is generated.
- **Rationale:** Prevents cross-applicant leakage, prompt-as-authorization, stale policy, and hallucinated institutional guidance.
- **Consequences:** Corpus ownership/versioning, curated query set, injection fixtures, provider privacy, outage fallback, safe response encoding, refusal copy, confirmation UX, and task-command audit evidence are required. A model response can never grant access, mutate state, or substitute for a page-level authorization decision.
- **Alternative:** General chatbot over unscoped data. Rejected.
- **Verification:** citation/support/refusal/auth isolation/injection/outage suite.

### ADR-013 — Limit audit assurance to a declared tamper-evidence threat model

- **Status:** proposed, spike-gated
- **Upstream:** D-052
- **Candidate decision:** Canonical append-only events use one serialized sequence/hash model, key versions, verifier, and optional external checkpoints; claim only covered tamper evidence.
- **Rationale:** Current “HMAC/tamper-proof” wording exceeds the unclosed key/checkpoint/concurrency threat model.
- **Consequences:** Key custody/rotation, restricted write privileges, canonicalization, checkpoint health, and incident response are required.
- **Alternative:** Conventional append-only audit without cryptographic claim remains fallback if spike fails.
- **Verification:** concurrency, modification/deletion/reorder/insertion, key rotation, checkpoint, privilege tests.

### ADR-014 — Deploy as one health-chained PostgreSQL-backed multi-service Compose project

- **Status:** proposed
- **Upstream:** D-056–D-067
- **Candidate decision:** One Compose topology with web/app, same-image queue worker and scheduler, the pinned PostgreSQL major, optional approved Python worker, private storage, and Mailpit local/test; Dokploy/Traefik provides deployed ingress.
- **Rationale:** Reproducible companion-service deployment matches project preference and avoids hidden manual infrastructure.
- **Consequences:** Images/versions are pinned; health/readiness/dependency chain, secrets, backup, and provider environment matrix are required.
- **Alternative:** Separate application/database wizard deployment. Rejected by default.
- **Verification:** Compose config/build/boot/health, migration/seed, worker/scheduler, mail, degraded sidecar, restore.

### ADR-015 — Commit offline proctoring behind conflict-controlled synchronization evidence

- **Status:** proposed, spike-gated (committed capstone capability)
- **Upstream:** D-053
- **Candidate decision:** Offline proctoring is a committed capstone capability, but it may be promoted only with encrypted device/session scope, durable outbox, authenticated ordered replay, command/idempotency IDs, aggregate-version conflict detection, visible reconciliation status, and explicit human resolution. Cache-only or last-write-wins writes are prohibited.
- **Rationale:** Attendance and submission are integrity-critical; service-worker caching alone cannot reconcile concurrent/stale writes safely.
- **Consequences:** Online and paper/manual fallback remain available while EV-011 is open. A failed spike blocks the offline readiness claim and promotion of the capability; it does not justify silently deleting the committed behavior from the plan. Device loss, revoked duty, stale versions, partial replay, and conflict states require visible recovery paths.
- **Alternative:** Last-write-wins replay. Rejected.
- **Verification:** disconnect/reconnect, duplicate replay, revoked duty, stale version, conflicting correction, partial sync tests.

## 4. ADR promotion gate

An ADR may move to `accepted` only when:

1. upstream founder decisions are GO/GO WITH CONDITION;
2. contradictory evidence is closed or explicitly constrained;
3. alternatives and consequences are reviewed;
4. required spike passes where marked;
5. linked domain/security/research documents are updated;
6. verification is assigned to stories/tests/runtime evidence;
7. a supersession trigger and rollback/reversal approach exist.

## 5. Implementation block

No coding agent may cite a `proposed` ADR as permission to lock schema, dependencies, integrations, or security behavior. The next BMAD solutioning pass must promote the subset required for E0/E1 and the connected vertical slice, then readiness must verify them.
