# Operations and Recovery Design

**Artifact:** NEXIAM-GF-23
**Status:** DRAFT — E0 HANDOFF CANDIDATE; R4/R5 evidence and pilot approval remain separate gates
**Purpose:** Define how the greenfield system runs, fails, degrades, retries, restores, and proves recovery before the first connected slice is promoted.
**Upstream authority:** GF-07, GF-08, GF-09, GF-13, GF-17, GF-18, GF-19, GF-20, GF-21, GF-22
**Downstream consumer:** GF-24 and the E0/E1/E2 OpenSpec changes

## 1. Why this artifact exists

The old application contains working operational paths, but its runtime behavior is distributed across controllers, services, model hooks, notifications, queues, file rendering, and provider calls. A greenfield build must not make reliability an oral convention.

GF-23 defines the operational contract around the domain contract:

- authoritative state changes remain synchronous and transaction-owned;
- notifications, rendering, indexing, and provider work happen only after commit;
- every asynchronous operation has an operation identity, retry state, terminal failure state, and operator view;
- optional providers never become the only path for admission, examination, scoring, consultation, or release;
- database and protected files are restored together and verified;
- degraded mode is visible and does not masquerade as success or empty data.

This is not pilot clearance. It is the minimum run/recovery design required so E0 can establish a clean baseline and E2 can produce evidence instead of screenshots only.

## 2. Operating planes

| Plane | Purpose | Authority | Allowed at E0 | Promotion condition |
|---|---|---|---|---|
| Development | Build and test the clean target with synthetic data | Greenfield source plus deterministic fixtures | Yes | Reproducible setup and zero-known-failure quality baseline |
| Controlled demonstration | Run the connected slice with synthetic or explicitly approved demo data | Greenfield database, private storage, local/test delivery | Yes, after E0/E1 | Repeatable reset, browser evidence, failure/recovery evidence |
| Technical pilot | Operate with approved institutional data and named operators | Greenfield runtime plus approved policy/retention/backup controls | No | R4/R5, privacy, stakeholder, restore, and operational gates |
| Production | Institutional operational system | Approved deployment and operating authority | No | Separate pilot/production clearance in GF-14 |

The legacy application is not a runtime dependency of any plane. It is a read-only reference/evidence source until a specific migration or behavior is intentionally approved.

## 3. Runtime topology

The starting topology is one health-chained Compose project. It is a modular monolith, not a collection of independently authoritative microservices.

| Component | Responsibility | Must not do | Health/readiness obligation | Failure behavior |
|---|---|---|---|---|
| `app/web` | HTTP routes, authentication, authorization, commands, queries, page responses | Own provider-specific business rules or bypass command authorization | Proves application boot, configuration, database connectivity, migration compatibility, and storage access | Writes fail explicitly when the authoritative database is unavailable; no fake success |
| `queue-worker` | After-commit jobs, notifications, document operations, adapter calls | Re-decide admission, scores, release, or permissions | Exposes queue liveness, failed-job count, and terminal-operation visibility | Retries bounded work; terminal failures remain visible and recoverable |
| `scheduler` | Time-based close, retry/reconciliation, retention eligibility, health checks | Mutate state without the same commands/locks used by HTTP | One scheduler identity and observable last-run/last-success state | Missed runs are detected; catch-up commands are idempotent |
| `postgres` | Authoritative relational state, constraints, audit records, idempotency records | Serve as an implicit provider cache or permit application-only invariants | Connection, migration, backup, restore, extension, and constraint checks | Core writes stop clearly; no alternate SQLite/MariaDB authority |
| `private-storage` | Protected scans, generated documents, import artifacts, checksums, versioned templates | Be public web storage or the only copy of domain state | Read/write/checksum/retention and backup verification | Artifact operation remains pending/failed; state is not silently marked ready |
| `python-inference` | Optional OMR/BGE/Yukee proposal work through approved adapter envelopes | Write application tables or create authoritative scores/decisions | Authenticated health, model/version manifest, bounded request/result identity | Core manual workflow remains available; proposal operation becomes degraded/retryable |
| `mailpit` | Local/test email sink | Represent production delivery success | Captures message evidence for deterministic tests | Tests can assert queued/delivered/failed states without external mail |
| external mail/provider adapters | Production delivery or approved external capability | Mutate core domain state directly | Provider health, timeout, retry, and terminal failure evidence | Domain state remains; delivery/integration operation is retried or manually recovered |

The exact image tags, PostgreSQL major version, secrets mechanism, and deployment host are E0 configuration decisions under D-055–D-066. They must be pinned in the greenfield repository; “latest” is not an acceptable runtime authority.

## 4. Request and command lifecycle

Every authoritative mutation follows this shape:

```text
HTTP/page or scheduled trigger
        ↓
authentication + capability/scope policy
        ↓
request shape validation
        ↓
command/action with idempotency/version input
        ↓
transaction: lock → preconditions → state change → audit → operation record
        ↓
commit
        ↓
after-commit event/listener/job
        ↓
provider/file/notification side effect
        ↓
operation status, retry or terminal-failure evidence
```

Rules:

1. A controller is an adapter. It does not contain the workflow transaction.
2. A command owns state transitions, authorization-relevant preconditions, lock targets, audit write, and typed failures.
3. A domain event is not proof that a provider delivered something. Delivery requires its own operation record.
4. External work is dispatched after commit. A failed provider call cannot roll back a valid admission, attendance, score, consultation decision, or release state.
5. A retry must reuse the same operation identity or an explicitly versioned retry identity. It must not create duplicate documents, notifications, scores, or releases.
6. A stale request receives a typed conflict/review response. It must not overwrite newer state.
7. All scheduler and worker commands call the same domain command path as interactive operations.

## 5. Queue and asynchronous-operation contract

The queue starts with Laravel's database queue and a dedicated worker. Redis is a later measured ADR, not an E0 prerequisite.

| Concern | E0/E2 rule |
|---|---|
| Dispatch timing | Domain side effects dispatch after commit; no job may observe uncommitted state as if it were final |
| Identity | `operation_id` plus command/request correlation; notification operations also carry event/recipient/channel deduplication |
| Retry | Bounded retry count and backoff are explicit per operation class; retry is safe only when the operation contract is idempotent |
| Terminal failure | Failed jobs and failed integration/document/delivery operations remain visible with sanitized error class, attempt count, last attempt, next action, and operator ownership |
| Manual retry | Operator retry reuses the stored operation recipe and checks current state/version before applying side effects |
| Cancellation | Cancellation is a domain operation where allowed; it cannot pretend a provider call that already completed was undone |
| Dead letter | The terminal queue state is a visible operational record, not an invisible log line |
| Transaction boundary | `after_commit` is enabled for queue dispatch; domain state does not depend on a worker completing immediately |
| Backpressure | Large print, import, notification, and indexing work is staged and bounded; HTTP requests return accepted/pending where appropriate |

## 6. Provider and degraded-mode matrix

D-067 is the operating rule: optional providers may improve the workflow but cannot be the only completion path.

| Failure | Authoritative state allowed to continue | Visible degraded behavior | Manual or alternate path | Never do |
|---|---|---|---|---|
| Mail provider unavailable | Admission submission, acceptance, scheduling, scoring, consultation, and release may commit | Delivery operation is `pending`/`failed`; operator sees recipient/channel/error and retry action; applicant sees only evidence actually available through an authorized channel | In-app notification, approved staff follow-up, controlled printed/office notice, or later retry | Mark email delivered or block core state forever because email failed |
| RAG/LLM provider unavailable or refuses | Core admission, exam, grading, consultation, and release continue | Assistant shows unavailable/refusal reason and safe fallback | Governing policy page, authorized staff workflow, confirmed Guidance inquiry when applicable | Give unsupported advice, silently invent an answer, or write through the model |
| Embedding/vector provider unavailable | Core system continues; RAG retrieval is unavailable/degraded | Retrieval operation is visible as degraded; citations are not fabricated | Search the approved policy source manually or route to staff | Treat a missing retrieval result as “no policy” |
| OMR/inference worker unavailable | Manual scoring and reviewed import remain available | Proposal operation is pending/failed; item/candidate confidence is not represented as authoritative score | Manual answer-sheet review and manual score entry | Finalize a score from a missing or uncertain proposal |
| Yukee/BGE or other decision-support adapter unavailable | Applicant/counselor workflow continues without the signal | Signal is absent/degraded with provenance state | Explicit deterministic baseline or manual counselor evidence, only if approved | Present an unavailable signal as a recommendation or validated model result |
| Document renderer/storage unavailable | Domain prerequisites and decision evidence may commit; external document delivery remains pending/failed according to release policy | Generation operation shows pending/failed, checksum/artifact state, and retry | Authorized internal preview or controlled manual print path when policy permits | Mark an artifact ready without durable storage/checksum evidence |
| Queue worker stopped | Synchronous domain transactions continue where the database is available | Pending operations and queue lag are visible; after-commit effects wait | Restart worker, inspect/retry terminal operations | Re-run HTTP mutation just because the notification is pending |
| PostgreSQL unavailable | No authoritative write is reported successful | Readiness is failed; writes return a clear service-unavailable result | Restore/reconnect according to runbook | Fall back silently to SQLite, files, or legacy database |
| Legacy application unavailable | Greenfield continues | No user-facing degradation; legacy is not called | Use the approved greenfield contract | Make old code a hidden runtime dependency |
| Offline proctor sync conflict | Accepted local operation remains a proposal until replay/reconciliation | Device/session shows pending, conflict, and human-resolution state | Ordered idempotent replay plus authorized human resolution | Last-write-wins silently or treat a local cache as authoritative |

## 7. Audit, correlation, and observability

Every request, command, job, provider operation, and protected view must be traceable without exposing secrets or unnecessary personal data.

Minimum correlation fields:

- `request_id` for the incoming HTTP or scheduler execution;
- `command_id` for an authoritative mutation attempt;
- `operation_id` for asynchronous work;
- `actor_id` and capability/scope context, redacted in lower-trust logs as needed;
- aggregate type and identifier, subject to protected-data rules;
- expected version and resulting version where applicable;
- outcome: committed, rejected, conflict, pending, succeeded, failed, cancelled, or superseded.

Operational evidence must distinguish:

- no data from data not yet available;
- provider refusal from provider outage;
- command denial from technical failure;
- pending work from successful delivery;
- a deleted/purged artifact from an artifact that was never generated;
- a stale read from an empty result.

Required E0/E2 dashboards or commands:

| Evidence | Minimum output |
|---|---|
| Readiness | component status, dependency status, migration compatibility, storage check, queue/scheduler state |
| Queue | pending, running, retrying, terminal failures, oldest age, operation IDs |
| Integration | provider, operation type, current state, attempts, last error class, retry action |
| Audit | append result, chain/checkpoint status, verification result, key version without exposing key material |
| Storage | object/document checksum, version, retention status, backup inclusion status |
| Security | denied requests, break-glass sessions, protected-view access, export/access-history evidence |
| Recovery | last backup, last restore rehearsal, data/file verification result, owner, next due date |

Logs are diagnostic evidence, not the authoritative audit record. Audit events are redacted, append-only by policy, serialized under the GF-19 protocol, and verifiable under the bounded threat-model wording in D-052.

## 8. Backup, restore, and retention operations

### 8.1 Backup set

The backup set must include:

1. PostgreSQL data and schema metadata;
2. protected generated documents, scans, import artifacts, and template versions that are within retention or legal hold;
3. audit chain heads/checkpoints and verification metadata;
4. operation/idempotency records required to prevent unsafe replay after restore;
5. deployment/version metadata needed to identify the code and migration state used for the backup.

Secrets are not copied into application data backups. The secret-management procedure must identify how encryption keys and application credentials are recovered separately.

### 8.2 Restore sequence

```text
select backup and record authorization/reason
  → restore PostgreSQL to isolated target
  → restore protected files to isolated target
  → verify migration/schema/extension compatibility
  → verify FK/index/constraint health
  → verify artifact checksums and retention/legal holds
  → verify audit chain/checkpoints
  → run deterministic read-only smoke checks
  → approve cutover or discard isolated restore
```

Restore must be tested as a database-plus-files operation. A database-only restore is not a valid proof for document, OMR, or audit claims.

RPO/RTO values are an explicit pilot/production decision and must not be invented from this document. E0 may run a synthetic restore rehearsal with measured elapsed time, but it must label the measurement as demonstration evidence rather than an institutional SLA.

### 8.3 Retention and legal hold

Retention is status-managed:

- `retained`: still inside policy or legal hold;
- `eligible_for_purge`: policy elapsed and no hold;
- `purge_pending`: approved purge operation queued;
- `purged`: artifact/data removed with evidence;
- `blocked_by_hold`: purge prevented by legal/correction/appeal/security hold.

A purge job rechecks status and hold immediately before deletion. No broad cascade is allowed to become an accidental retention controller. Historical domain evidence remains read-only or archived according to GF-05 and D-029/D-034/D-035.

## 9. Recovery runbooks

### RUN-OPS-01 — Failed delivery or provider operation

1. Locate `operation_id`, aggregate, actor, and current domain version.
2. Confirm the domain state committed and that the side effect is not already evidenced as successful.
3. Inspect sanitized failure class, attempt history, and provider response metadata.
4. Retry using the stored recipe only if the operation is still allowed by current state/version.
5. If retry is not allowed, record terminal failure and route to the named manual fallback.
6. Verify no duplicate notification/document/score/release was created.
7. Close the incident with audit correlation and operator reason.

### RUN-OPS-02 — Queue stopped or flooded

1. Check worker health, queue age, database connectivity, and failed-job count.
2. Stop repeated manual submissions; pending does not mean the command failed.
3. Restart the worker under the deployment procedure.
4. Reprocess one representative operation and verify idempotency.
5. Drain in bounded batches; do not bulk-retry unknown operations without state checks.
6. Review terminal failures and oldest pending age.

### RUN-OPS-03 — Partial staged import

1. Identify import batch and row states.
2. Preserve accepted/rejected/needs-review rows; do not restart the whole file by default.
3. Fix or review only rows with actionable states.
4. Replay with the same batch/row idempotency keys.
5. Verify score/grading cardinality and audit evidence.
6. Use manual entry for rows that cannot be safely recovered.

### RUN-OPS-04 — PostgreSQL restore

1. Declare recovery reason and isolate the restore target.
2. Select the backup and verify it contains the required files/metadata.
3. Restore database and files separately, then run the verification sequence.
4. Compare audit head/checkpoint and operation identities.
5. Run read-only slice smoke tests and verify no pending operation would duplicate a completed effect.
6. Obtain named cutover approval; retain the isolated restore evidence.

### RUN-OPS-05 — Audit verification failure

1. Stop any claim that the audit chain is verified.
2. Preserve the affected database, logs, checkpoints, and key-version metadata.
3. Determine whether the failure is serialization, missing checkpoint, key/version, storage, or suspected tampering.
4. Keep domain operation available only under the approved incident policy; do not silently rewrite audit history.
5. Record a security incident and founder/owner decision before restoring trust in the verification result.

### RUN-OPS-06 — Offline conflict

1. Identify device/session scope, operation identity, local sequence, and server version.
2. Verify signature/actor/session eligibility and replay idempotency.
3. Present conflict state to the authorized human resolver.
4. Apply the selected resolution through a typed command and audit it.
5. Rebuild the device/session projection and verify no duplicate attendance/submission/score was created.

## 10. E0 operational acceptance

E0 may be considered operationally ready only when all of the following are demonstrated with synthetic data:

- Compose starts the approved services with health-ordered dependencies;
- a fresh PostgreSQL database migrates and seeds deterministically;
- a queue job dispatched after commit is observable and idempotent;
- a failed job is visible and retryable;
- Mailpit captures a test delivery without changing domain authority;
- protected storage records checksum/version/retention metadata;
- a database-plus-files restore rehearsal is measured and verified;
- audit verification runs against the seeded chain;
- stopping the optional worker still permits the manual core path;
- no legacy route, database, file, or provider is required for boot;
- quality commands discover real tests and fail on a deliberately introduced test failure;
- no secret, token, or raw protected artifact is printed into normal logs.

## 11. E2 connected-slice operational acceptance

E2 additionally requires:

- admission, exam, grading, consultation, release, and portal state remain correct when mail/AI/inference workers are unavailable;
- pending document/notification operations are visible without falsely claiming delivery;
- command replay and stale-version conflicts are proven;
- exam close and grading-open are exactly-once under a race test;
- release reversal preserves prior evidence;
- protected view, export, break-glass, and audit access are observable;
- browser pages distinguish empty, pending, denied, failed, conflict, and degraded states;
- operator runbooks can recover a representative failed operation without code edits.

## 12. What this unblocks and what it does not

GF-23 unblocks the E0 operational scaffold and gives GF-24 a concrete runtime contract. It does not:

- prove OMR, RAG, Yukee, BGE, or offline feasibility;
- choose institutional RPO/RTO or final retention periods;
- authorize real-data pilot or production deployment;
- convert legacy routes/controllers/migrations into target authority;
- replace the need for executable tests and runtime evidence.
