# Schema, ERD, and Constraint Catalog

**Artifact:** NEXIAM-GF-19  
**Status:** DRAFT — REVIEW REQUIRED  
**Depends on:** GF-17 HLD checkpoint 1 and GF-18 LLD draft.  
**Purpose:** Translate domain ownership and invariants into a migration-ready persistence contract without treating the conceptual ERD as sufficient schema authority.

## 1. Persistence posture

The relational database is the authoritative store for core workflow state. PostgreSQL is the greenfield production authority; the supported major version is pinned during E0 and recorded in the environment/ADR. `pgvector` is optional and may be enabled for approved BGE/RAG embedding use only after extension/version, index, privacy, backup, and restore evidence. `btree_gist` is required if the room-overlap exclusion-constraint strategy is selected and must be pinned and tested with the approved PostgreSQL image.

SQLite may be used only for tests explicitly proven to have equivalent behavior for the tested contract. Race-sensitive, foreign-key, date/time, check, JSON, locking, and indexing behavior must be verified against the production engine.

The schema must enforce invariants that cannot safely remain application-only checks.

## 2. Naming and identifier rules

These are proposed physical names. GF-19 remains draft until the final naming convention and Laravel authentication mapping are promoted.

- internal primary keys use database-generated numeric identifiers unless a bounded external/public identifier is required;
- public identifiers are opaque, non-sequential, and separate from internal keys where exposed;
- timestamps use one UTC storage convention with explicit application timezone conversion;
- state/status values use versioned application enums plus database-safe values;
- secrets/verifiers are stored as hashes or encrypted values according to purpose;
- polymorphic relationships are allowed only when ownership, index, and authorization behavior are explicit;
- no sentinel `0` foreign keys;
- nullable foreign keys have an explicit null/restrict/set-null policy;
- historical records are not removed because an active reference is deactivated.

## 3. Table catalog

### 3.1 Identity and access

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `staff_accounts` | Named staff identities | Unique normalized login; status index; disabled accounts cannot authenticate |
| `applicant_identities` | Longitudinal applicant identity | Unique approved normalized identity key within its defined scope; status/indexes |
| `applicant_accounts` | Optional credential linkage | Unique applicant identity link under approved cardinality; login identifier uniqueness |
| `credential_challenges` | Hashed one-time setup/recovery verifier | Subject/purpose/index; expiry/consumed indexes; replay-safe unique request key |
| `role_grants` | Time-bound capability grant | Actor/role/effective window indexes; no self-escalation enforced by command/policy |
| `break_glass_sessions` | Time-bound exceptional access activation | Actor/reason/capability allowlist/start/end/reviewer/alert/revocation indexes; no wildcard capability; one active session per actor unless explicitly approved |
| `scope_grants` | Office/program/resource scope | Scope subject/index; effective window; grant ceiling policy |
| `authentication_sessions` | Browser/session lifecycle if separately stored | Actor/session hash uniqueness; expiry/revocation indexes |

The raw credential challenge is never stored. A public challenge reference may identify a record, but it is not sufficient without the verifier.

### 3.2 Reference data and configuration

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `academic_cycles` | Academic cycle identity and lifecycle | Unique code; active/status index; closed-cycle history |
| `application_windows` | Versioned public-intake open/close and late-intake override policy | Cycle/status/effective-time indexes; one effective window; reason/audit metadata |
| `programs` | Program catalog and ownership | Unique code/version; active/index; ownership reference |
| `rooms` | Room capacity and status | Unique code; positive capacity; active/index |
| `aptitude_areas` | Versioned score areas | Unique code/version; effective/status index |
| `appointment_slots` | Optional intake appointment availability | Cycle/time unique scope; positive capacity; reservation index |
| `result_templates` | Versioned document template authority | Name/version unique; active/effective index |
| `mapping_definitions` | Conversion/program mapping versions | Type/version/source/approval indexes |
| `metric_definitions` | Program/report metric dictionary | Name/version/owner/effective indexes |

Reference rows are deactivated or superseded when historical data refers to them. Destructive deletion requires an approved migration/retention decision.

### 3.3 Admissions

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `admission_attempts` | Cycle-scoped intake/decision aggregate | Unique public reference; applicant/cycle indexes; status/stage indexes; lifecycle timestamps |
| `program_preferences` | Applicant-ranked choices | Unique attempt/rank; unique attempt/program where required |
| `admission_corrections` | Controlled correction history | Attempt/actor/time indexes; reason and approved-field metadata |
| `application_documents` | Approved uploaded/generated intake artifact metadata when intake-document scope is promoted | Attempt/owner/type/version/status/lifecycle indexes; checksum/private storage reference; no untrusted path or raw content authority |

`admission_attempts` must not use a global one-to-one applicant constraint. Repeat attempts and retakes are policy-scoped by cycle/mode/rule.

### 3.4 Examination

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `exam_slot_plans` | Test Administrator-controlled reservation policy | Cycle/date/status/effective-time indexes; state transition/audit metadata |
| `exam_slot_reservations` | Accepted applicant reservation before final assignment | Unique active attempt/plan scope; slot/capacity/status/idempotency indexes |
| `exam_sessions` | Session schedule/lifecycle | Cycle/room/time/status indexes; `effective_end_at`; extension/version evidence; ownership/source-mode reference |
| `exam_assignments` | Attempt-to-session membership | Unique approved attempt/session scope; session/attempt/status/source-mode indexes |
| `direct_assessments` | Special late/same-day assessment context | Accepted attempt unique active context; Test Administrator actor/reason/status indexes |
| `proctor_assignments` | Contextual Proctor duty | Session/staff/effective indexes; active assignment uniqueness where required |
| `attendance_records` | Attendance result/history | Assignment/status/time indexes; correction/version reference |
| `submission_records` | Submission receipt/result | Assignment/idempotency indexes; received-at/status indexes |

The database strategy for non-overlapping room sessions, slot capacity, reservation finalization, and direct-assessment uniqueness must be proven against PostgreSQL. Application pre-checks alone are insufficient.

### 3.5 Assessment and grading

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `grading_sessions` | Active/versioned grading workflow | Unique active grading unit/version; status/indexes |
| `score_records` | Authoritative score per area/version | Unique grading version/attempt/area; FK to aptitude area; source/index |
| `score_import_batches` | Durable staged import | Public batch identity/checksum/status indexes |
| `score_import_rows` | Explicit row-level import state | Unique batch/row identity; status/error/retry indexes |
| `omr_batches` | Approved OMR input batch | Template/input/answer-key/version/status indexes |
| `omr_observations` | Proposal observation per item, possibly unbound until human identity resolution | Batch/item unique scope; nullable attempt binding; confidence/review/ambiguity indexes |
| `omr_review_actions` | Human binding/correction/adjudication evidence | Observation/actor/action/time indexes; reason and before/after payload/checksum |
| `engine_manifests` | Approved engine/adapter metadata | Engine/version unique; capability/status indexes |
| `inference_results` | Proposal/provenance record | Attempt/engine/version/input hash indexes; no authoritative score FK mutation |

Authoritative `score_records` may be created through manual entry or reviewed import, but both paths use the same uniqueness, validity, source, and audit rules.

### 3.6 Consultation and release

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `consultation_decisions` | Human consultation decision evidence | Attempt/version/status indexes; author/scope indexes; separate suggested-versus-selected values |
| `consultation_plans` | Published F2F/online availability | Office/mode/time/status/capacity indexes; publication/cancellation/version evidence |
| `consultation_reservations` | Applicant reservation | Unique applicant/slot as policy requires; status/indexes |
| `result_releases` | Release/reversal history | Attempt/version/status indexes; current visibility lookup |
| `generated_documents` | Artifact metadata and provenance | Owner/subject/template/status/checksum/storage/operation indexes; checksum and storage reference required only for `ready` artifacts |
| `document_delivery_events` | Generated/downloaded/distributed/printed evidence | Document/action/actor/time indexes; idempotency key |

External result access must resolve through current approved `result_releases`, not merely the presence of a generated document.

### 3.7 Communications, knowledge, and audit

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `notification_deliveries` | Event/recipient/channel delivery and read state | Unique event/recipient/channel; status/read/retry indexes |
| `inquiry_threads` | Conditional confirmed Guidance inquiry | Applicant/office/status/unread indexes; retention metadata |
| `knowledge_documents` | Approved corpus source/version | Source/version/audience/sync indexes |
| `knowledge_chunks` | Chunked approved corpus text and optional embedding | Document/version/order indexes; vector index only when pgvector is approved; source checksum |
| `assistant_exchanges` | Policy/own-data/task conversation envelope | Actor/scope/context/time/status indexes; redacted retention metadata |
| `assistant_action_requests` | Typed UI-equivalent task proposal/confirmation/result | Exchange/action/idempotency/status/actor indexes; no arbitrary route or SQL field |
| `retention_holds` | Approved legal/institutional restriction preventing eligible purge/archive | Subject type/key, data class, scope, reason, owner, start/end/status indexes; active-hold uniqueness and append-only history |
| `audit_events` | Mandatory mutation/access evidence | Monotonic sequence; actor/subject/action/time indexes; chain fields |
| `audit_chain_heads` | Serialized current sequence/hash head for each audit stream | Unique stream key; current sequence/hash/key-version; one-row lock target |
| `audit_checkpoints` | External verification checkpoints | Sequence/hash/key version/destination/time indexes |
| `idempotency_records` | Cross-command request replay result where needed | Actor/scope/command/key unique; expiry/index |
| `integration_operations` | External operation state/provenance | Operation type/subject/status/provider/retry indexes |

`inquiry_threads` remains conditional. It must not be introduced merely because an RAG refusal message has no destination; the definite chatbot can provide policy links and staff fallback without creating a thread unless the applicant confirms the bounded handoff.

## 4. Foreign-key and deletion policy

Default relationship rules:

- historical workflow records use `RESTRICT` or controlled archival, not broad cascade;
- nullable operational references use `SET NULL` only when the domain remains valid without the reference;
- child records that have no independent historical meaning may use controlled cascade only after review;
- credential/session records may be expired/revoked rather than physically deleted;
- generated artifacts and audit events are retained according to approved lifecycle policy;
- academic-cycle closure changes records to historical/read-only scope; it does not delete them or merge them into the next cycle;
- raw OMR images carry `retention_status` and are normally eligible for purge 90 days after accepted import/finalization and correction/appeal hold closure; derived observations/provenance follow assessment/audit retention;
- working default retention is five years after cycle closure for applicant/admission/exam/score/consultation/result records and seven years for audit/security evidence, subject to institutional/privacy approval and legal holds;
- every retention-managed artifact/aggregate exposes a lifecycle/data-class reference that the retention controller can evaluate; purge/archive is forbidden while an active `retention_holds` row covers the subject/class/scope;
- reference data referenced by historical records is deactivated, not deleted;
- applicant identity is never deleted as a side effect of reopening an attempt;
- result release reversal creates a new event/version; it does not delete prior release rows.

Every foreign key in the final schema catalog must specify: nullable, on-delete behavior, on-update behavior, historical meaning, and test fixture.

## 5. Required unique/check constraints

The final migration design must enforce at least:

1. Applicant No/Reference uniqueness.
2. Approved normalized identity duplicate rule.
3. Applicant attempt-to-cycle cardinality according to the approved retake rule.
4. Program preference rank uniqueness per attempt.
5. At most one active AcademicCycle and one effective application window per cycle.
6. Positive capacities and valid time windows.
7. Assignment uniqueness and capacity/eligibility strategy.
8. Active Proctor assignment uniqueness where required.
9. One authoritative attendance outcome and one authoritative submission outcome per assignment/version.
10. One active grading version per assessment unit.
11. Score uniqueness per grading version/attempt/area.
12. Import batch/row idempotency.
13. OMR batch/attempt/item identity.
14. One current ConsultationDecision version per approved attempt/context.
15. Consultation reservation ownership/idempotency.
16. Result release version/current-state lookup integrity, including one current visibility projection per approved attempt/mode.
17. Document checksum/storage metadata completeness.
18. Notification event/recipient/channel idempotency.
19. Credential challenge single consumption.
20. Audit sequence and checkpoint integrity.
21. Idempotency record uniqueness by command/actor/scope/key.
22. A version/expected-version strategy for every mutable aggregate participating in a race-sensitive command; the strategy must be explicit for optimistic conflict, pessimistic lock, or both.
23. Break-glass activation has a non-wildcard capability allowlist, mandatory reason/reviewer/alert metadata, explicit expiry/revocation, and at most one active session per actor unless separately approved.
24. Retention holds are unique for an active subject/class/scope and cannot be silently removed or shortened; purge eligibility requires a verified absence of an active hold.

Where PostgreSQL cannot express a business rule as a check, partial unique, exclusion, or equivalent constraint, GF-20 must define the transaction/lock/reconciliation strategy and its concurrency test.

## 6. Migration ordering

Proposed fresh migration order:

1. framework/auth support and base identifiers;
2. staff/applicant identity and authentication tables;
3. grants/scopes/break-glass/challenge/idempotency tables;
4. academic cycles and reference data;
5. admission attempts/preferences/corrections/application documents when that scope is promoted;
6. rooms, application windows, slot plans/reservations, sessions, assignments, direct assessments, proctor duty, attendance, submissions;
7. grading/scores/import/OMR/provenance;
8. consultation plans/decisions/reservations/release;
9. templates/documents/delivery evidence;
10. notifications/inquiry conditional tables;
11. knowledge/chunks/assistant exchanges/action requests/engine/integration operation tables;
12. retention holds, audit events/chain heads/checkpoints, and verification indexes;
13. foreign-key tightening, unique/check constraints, and seed references;
14. deterministic synthetic seed and reset verification.

The migration chain must support a clean database and a rehearsed upgrade path. “Fresh migration works” alone is not sufficient for system-ready promotion.

### 6.1 Canonical vocabulary and conceptual-to-persistence mapping

The following mappings prevent conceptual entities from becoming hidden implementation tables or silently divergent state machines:

- `ConsultationPlan` is persisted as `consultation_plans`.
- `ConsultationDecision` is persisted as `consultation_decisions`.
- `ApplicationDocument` is represented by `application_documents` only when intake-document scope is promoted. If it is excluded from the connected slice, its requirement and acceptance must be explicitly marked conditional/deferred; it must not remain an unowned conceptual dependency.
- `ProgramQuotaSnapshot` is not an independent authority in the current read-only Program Head direction. Its source/version/meaning are represented through `metric_definitions` and approved source projections while EV-017 remains open. A persisted snapshot requires a separate decision, table contract, and acceptance evidence.
- `SystemSetting` is operational configuration, not core workflow state. Secrets remain outside ordinary domain persistence. A mutable database-backed settings table requires a separate ADR, versioning, authorization, audit, and retention contract.

Status vocabulary is also canonicalized here: `AdmissionAttempt` uses `pending`, `needs_correction`, `accepted`, `dismissed`, and `archived`; `ExamSession` uses `draft`, `published`, `in_progress`, `completed`, and `cancelled`; `GradingSession` uses `open`, `review`, `finalized`, and superseded correction versions. Milestones such as `submitted` and terminal command outcomes such as `closed` must not be introduced as competing aggregate status values.

### 6.2 Aggregate/module ownership crosswalk

Every physical table has one authoritative aggregate/entity or explicit cross-cutting owner. Support tables do not become unowned domain state merely because they are operational.

| Owner | Physical tables |
|---|---|
| `StaffAccount` / Identity & Access | `staff_accounts` |
| `ApplicantIdentity` | `applicant_identities` |
| `ApplicantAccount` | `applicant_accounts` |
| Credential challenge service | `credential_challenges` |
| Capability/scope administration | `role_grants`, `scope_grants`, `break_glass_sessions` |
| Authentication session service | `authentication_sessions` |
| `AcademicCycle` | `academic_cycles` |
| Reference Data & Academic Configuration | `application_windows`, `programs`, `rooms`, `aptitude_areas`, `appointment_slots`, `result_templates`, `mapping_definitions`, `metric_definitions` |
| `AdmissionAttempt` | `admission_attempts`, `program_preferences`, `admission_corrections`, `application_documents` |
| `ExamSlotPlan` / `ExamSlotReservation` | `exam_slot_plans`, `exam_slot_reservations` |
| `ExamSession` / `ExamAssignment` | `exam_sessions`, `exam_assignments`, `direct_assessments` |
| `ProctorAssignment` / Examination operations | `proctor_assignments`, `attendance_records`, `submission_records` |
| `GradingSession` / score import | `grading_sessions`, `score_records`, `score_import_batches`, `score_import_rows` |
| OMR review/provenance | `omr_batches`, `omr_observations`, `omr_review_actions` |
| Decision-support provenance | `engine_manifests`, `inference_results` |
| `ConsultationPlan` / `ConsultationReservation` | `consultation_plans`, `consultation_reservations` |
| `ConsultationDecision` / `ResultRelease` | `consultation_decisions`, `result_releases` |
| `GeneratedDocument` / delivery evidence | `generated_documents`, `document_delivery_events` |
| `NotificationDelivery` | `notification_deliveries` |
| Conditional Guidance inquiry | `inquiry_threads` |
| Knowledge/RAG corpus | `knowledge_documents`, `knowledge_chunks` |
| Assistant interaction/action boundary | `assistant_exchanges`, `assistant_action_requests` |
| Retention controller | `retention_holds` |
| Audit & Compliance | `audit_events`, `audit_chain_heads`, `audit_checkpoints` |
| Cross-command replay boundary | `idempotency_records` |
| External adapter operation boundary | `integration_operations` |

The crosswalk is an ownership map, not permission. Authorization and scope remain enforced by GF-21 and the command/query contracts.

### 6.3 PostgreSQL constraint and lock protocol catalog

The following mechanisms are required implementation targets. A policy-dependent key or predicate remains a hold until its decision is approved, but the race-control mechanism must not be left as an application pre-check.

| Invariant | PostgreSQL mechanism | Transaction/lock protocol | Conflict outcome |
|---|---|---|---|
| One active academic cycle/window | Partial unique indexes on active cycle and effective window per cycle | Lock cycle/window parent during publish/close/extension | Typed state conflict |
| Applicant duplicate identity | Unique normalized identity key under the approved scope | Insert and handle unique violation; never read-then-insert | Typed duplicate-resolution response |
| Program preference rank | Unique `(attempt_id, rank)` and approved program uniqueness | Attempt row lock for reorder/replacement | Validation/conflict response |
| Room session overlap | PostgreSQL `EXCLUDE USING gist` on room plus active `tstzrange(start_at, effective_end_at, '[)')` where policy permits | Constraint is authoritative; re-evaluate effective end under session lock | Scheduling conflict |
| Slot capacity/reservation | Slot/plan row lock plus active reservation predicate/unique scope | `SELECT ... FOR UPDATE` on slot plan before count/finalize; idempotency record in same transaction | Capacity/ownership conflict |
| Attempt assignment/retake | Partial unique active assignment plus policy-approved retake key | Lock attempt and session before assignment; unresolved retake predicate remains a hold | Eligibility/duplicate conflict |
| Direct-assessment uniqueness | Partial unique active context per accepted attempt | Lock attempt before creating or replacing active context | Already-applied/conflict response |
| Proctor duty | Partial unique active duty per session/staff policy | Lock session before assign/revoke; authorization rechecked at use time | Duty conflict/denial |
| Attendance/submission once | Unique assignment/version outcome and idempotency key | Insert/update with `RETURNING`; replay returns stored result | Already applied or conflict |
| One active grading version | Partial unique active grading version per assessment unit | Lock grading aggregate before open/reopen/finalize; increment version atomically | Stale-version conflict |
| Score uniqueness/completeness | Unique grading-version/attempt/area plus score checks | Lock grading version for multi-area finalization | Missing/invalid/conflict response |
| Challenge single consumption | Consumed/expiry predicate and unique request key | Conditional `UPDATE ... WHERE consumed_at IS NULL RETURNING` | Expired/already-used response |
| Consultation reservation ownership | Active applicant/slot uniqueness under approved scope | Lock availability row before capacity count/confirm | Reservation conflict |
| Current result release | Partial unique current-release predicate per attempt/mode plus monotonic release version | Lock attempt/release stream; reversal and new current projection commit atomically | Stale/reversal conflict |
| Ready document metadata | Status/checksum/storage check and unique generation operation | Lock operation/document row; publish `ready` only after renderer success | Accepted-for-processing or artifact failure |
| Notification delivery | Unique event/recipient/channel and idempotency key | Delivery job claims operation row with lease/attempt update | Replay/terminal failure |
| Audit sequence/hash | `audit_chain_heads` row per stream with unique stream key | Lock head row, allocate next sequence/hash, append event and update head atomically | Audit append failure blocks mutation |
| Cross-command idempotency | Unique actor/scope/command/key | Lock existing record; request-hash mismatch is a conflict | Stored replay or mismatch |
| Retention hold protection | Partial unique active hold by subject/class/scope | Purge job locks hold/subject decision and rechecks immediately before action | Purge blocked/review required |

The exact retake key, identity scope, consultation reservation scope, and account cardinality remain policy decisions; their eventual predicates must be added to this table and to the migration tests before GF-19 promotion.

### 6.4 Foreign-key relationship and delete-action catalog

The final migration must expand these grouped rules into column-level assertions and fixtures. No default cascade is permitted outside an explicitly approved child with no independent historical meaning.

| Relationship group | FK/delete action | Historical rule |
|---|---|---|
| `applicant_accounts.applicant_identity_id` | `RESTRICT` | Deactivate/replace credentials; never delete identity history |
| `admission_attempts.applicant_identity_id`, `.academic_cycle_id` | `RESTRICT` | Attempt history survives account/cycle closure |
| `program_preferences`, `admission_corrections`, `application_documents` → attempt | `RESTRICT` | Correction/document evidence remains attributable |
| Slot plans/reservations → cycle/attempt | `RESTRICT` | Close/cancel status preserves reservation history |
| Sessions/assignments/direct assessments → cycle/attempt/room | `RESTRICT` | Deactivate reference data; preserve schedule and assignment evidence |
| Proctor/attendance/submission → session/assignment/staff | `RESTRICT`; nullable actor references may use `SET NULL` only with audit preservation | Duty revocation is not deletion |
| Grading/scores/import rows → session/attempt/area/batch | `RESTRICT` | Reopen creates a version; no score cascade deletion |
| OMR observations/review actions → batch/observation/attempt | `RESTRICT`; unbound attempt may remain nullable until human binding | Raw image purge cannot erase accepted provenance |
| Consultation/reservation/release → attempt/plan/slot | `RESTRICT` | Decision and release history remain after cancellation/reversal |
| Generated documents/delivery events → release/document/template | `RESTRICT` | Retention action is controlled, never ordinary FK cascade |
| Notifications → source event/recipient | `RESTRICT` or explicit retention archive | Delivery/read history remains auditable |
| Knowledge chunks → approved document/version | `RESTRICT` for retained versions | Supersede/deactivate corpus versions; do not erase cited history |
| Assistant exchanges/actions → actor/subject | `RESTRICT` or approved pseudonymization path | Redacted retention preserves action/audit linkage |
| Audit events/checkpoints/chain heads → actor/subject/key | `RESTRICT`; nullable subject only where policy permits | Audit integrity takes precedence over routine deletion |
| Idempotency/integration operations → command subject | `RESTRICT` until retention eligibility; controlled purge only | Replay/reconciliation history must remain available for its retention class |
| Retention holds → subject/class/scope | No cascading removal; hold release is a command/audit event | Active hold always overrides purge/archive eligibility |

Column-level migration output must verify nullability, `ON DELETE`, `ON UPDATE`, historical fixture, and authorization implications for every listed relationship.

## 7. ERD authority rule

The current Mermaid ERD is a conceptual illustration. GF-19 becomes persistence authority only after:

- every table is mapped to an aggregate/entity owner;
- every relationship has cardinality and delete policy;
- every unique/index/check rule is listed;
- conditional entities are marked as conditional;
- conceptual-to-physical names are mapped without unclassified aliases;
- migration order and seed dependencies are defined;
- PostgreSQL engine behavior is tested; pgvector behavior is separately evidenced if enabled;
- the schema matches GF-18 and GF-20 without hidden fields.

A revised ERD asset should be generated only after this catalog reaches conditional approval, not before.

## 8. Schema verification package

Required evidence:

- fresh migration output on the approved production engine;
- upgrade migration output from a prior synthetic baseline;
- schema/catalog comparison;
- FK/unique/check/index inspection;
- race tests for reference, capacity, reservation, challenge, import, close, release, and audit;
- rollback/forward migration policy evidence;
- deterministic seed/reset output;
- backup/restore test using database plus required private files;
- retention/archive fixture evidence;
- active/legal retention-hold prevents purge/archive and release/removal is audited;
- no destructive cascade regression tests.

## 9. Schema holds

- PostgreSQL major version pin, `btree_gist` exclusion-constraint contract if selected, and pgvector extension/index contract if enabled;
- exact applicant duplicate key and institutional identifier policy;
- retake/reassignment uniqueness rule;
- application-window/ExamSlotPlan/DirectAssessment persistence promotion;
- intake-document scope and `ApplicationDocument` persistence/retention;
- exact consultation reservation scope;
- Program Head snapshot persistence if a materialized quota/metric snapshot is selected;
- retention periods and deletion/restriction implementation;
- break-glass activation/end persistence, capability allowlist, expiry/revocation, reviewer alert, and post-use review;
- audit key/checkpoint storage;
- conditional inquiry/engine/OMR table promotion;
- final Laravel authentication table/guard mapping.

## 10. Exit criteria

GF-19 may advance to `CONDITIONAL` only when the HLD/LLD boundaries remain stable, all first-slice tables and constraints are mapped, and every unresolved schema hold has a named decision ID or evidence gate.
