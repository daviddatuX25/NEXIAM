# Schema, ERD, and Constraint Catalog

**Artifact:** NEXIAM-GF-19  
**Status:** DRAFT — REVIEW REQUIRED  
**Depends on:** GF-17 HLD checkpoint 1 and GF-18 LLD draft.  
**Purpose:** Translate domain ownership and invariants into a migration-ready persistence contract without treating the conceptual ERD as sufficient schema authority.

## 1. Persistence posture

The relational database is the authoritative store for core workflow state. The current working baseline is MySQL 8.4 with engine-specific migration, concurrency, backup, and restore evidence required before system-ready promotion.

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
| `scope_grants` | Office/program/resource scope | Scope subject/index; effective window; grant ceiling policy |
| `authentication_sessions` | Browser/session lifecycle if separately stored | Actor/session hash uniqueness; expiry/revocation indexes |

The raw credential challenge is never stored. A public challenge reference may identify a record, but it is not sufficient without the verifier.

### 3.2 Reference data and configuration

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `academic_cycles` | Academic cycle and application window | Unique code; active/status index; window check |
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

`admission_attempts` must not use a global one-to-one applicant constraint. Repeat attempts and retakes are policy-scoped by cycle/mode/rule.

### 3.4 Examination

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `exam_sessions` | Session schedule/lifecycle | Cycle/room/time/status indexes; effective deadline; ownership reference |
| `exam_assignments` | Attempt-to-session membership | Unique approved attempt/session scope; session/attempt/status indexes |
| `proctor_assignments` | Contextual Proctor duty | Session/staff/effective indexes; active assignment uniqueness where required |
| `attendance_records` | Attendance result/history | Assignment/status/time indexes; correction/version reference |
| `submission_records` | Submission receipt/result | Assignment/idempotency indexes; received-at/status indexes |

The database strategy for non-overlapping room sessions and capacity must be proven against MySQL 8.4. Application pre-checks alone are insufficient.

### 3.5 Assessment and grading

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `grading_sessions` | Active/versioned grading workflow | Unique active grading unit/version; status/indexes |
| `score_records` | Authoritative score per area/version | Unique grading version/attempt/area; FK to aptitude area; source/index |
| `score_import_batches` | Durable staged import | Public batch identity/checksum/status indexes |
| `score_import_rows` | Explicit row-level import state | Unique batch/row identity; status/error/retry indexes |
| `omr_batches` | Approved OMR input batch | Template/input/answer-key/version/status indexes |
| `omr_observations` | Proposal observation per item | Batch/attempt/item unique scope; confidence/review indexes |
| `engine_manifests` | Approved engine/adapter metadata | Engine/version unique; capability/status indexes |
| `inference_results` | Proposal/provenance record | Attempt/engine/version/input hash indexes; no authoritative score FK mutation |

Authoritative `score_records` may be created through manual entry or reviewed import, but both paths use the same uniqueness, validity, source, and audit rules.

### 3.6 Consultation and release

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `consultation_cases` | Human consultation decision evidence | Attempt/version/status indexes; author/scope indexes |
| `consultation_availabilities` | Published F2F/online availability | Office/mode/time/status/capacity indexes |
| `consultation_reservations` | Applicant reservation | Unique applicant/slot as policy requires; status/indexes |
| `result_releases` | Release/reversal history | Attempt/version/status indexes; current visibility lookup |
| `generated_documents` | Artifact metadata and provenance | Owner/subject/template/checksum/storage indexes |
| `document_delivery_events` | Generated/downloaded/distributed/printed evidence | Document/action/actor/time indexes; idempotency key |

External result access must resolve through current approved `result_releases`, not merely the presence of a generated document.

### 3.7 Communications, knowledge, and audit

| Table | Purpose | Required constraints/indexes |
|---|---|---|
| `notification_deliveries` | Event/recipient/channel delivery and read state | Unique event/recipient/channel; status/read/retry indexes |
| `inquiry_threads` | Conditional confirmed Guidance inquiry | Applicant/office/status/unread indexes; retention metadata |
| `knowledge_documents` | Approved corpus source/version | Source/version/audience/sync indexes |
| `audit_events` | Mandatory mutation/access evidence | Monotonic sequence; actor/subject/action/time indexes; chain fields |
| `audit_checkpoints` | External verification checkpoints | Sequence/hash/key version/destination/time indexes |
| `idempotency_records` | Cross-command request replay result where needed | Actor/scope/command/key unique; expiry/index |
| `integration_operations` | External operation state/provenance | Operation type/subject/status/provider/retry indexes |

`inquiry_threads` remains conditional. It must not be introduced merely because an RAG refusal message has no destination.

## 4. Foreign-key and deletion policy

Default relationship rules:

- historical workflow records use `RESTRICT` or controlled archival, not broad cascade;
- nullable operational references use `SET NULL` only when the domain remains valid without the reference;
- child records that have no independent historical meaning may use controlled cascade only after review;
- credential/session records may be expired/revoked rather than physically deleted;
- generated artifacts and audit events are retained according to approved lifecycle policy;
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
5. Positive capacities and valid time windows.
6. Assignment uniqueness and capacity/eligibility strategy.
7. Active Proctor assignment uniqueness where required.
8. One active grading version per assessment unit.
9. Score uniqueness per grading version/attempt/area.
10. Import batch/row idempotency.
11. OMR batch/attempt/item identity.
12. Consultation reservation ownership/idempotency.
13. Result release version/current-state lookup integrity.
14. Document checksum/storage metadata completeness.
15. Notification event/recipient/channel idempotency.
16. Credential challenge single consumption.
17. Audit sequence and checkpoint integrity.
18. Idempotency record uniqueness by command/actor/scope/key.

Where MySQL cannot express a business rule as a check or exclusion constraint, GF-20 must define the transaction/lock/reconciliation strategy and its concurrency test.

## 6. Migration ordering

Proposed fresh migration order:

1. framework/auth support and base identifiers;
2. staff/applicant identity and authentication tables;
3. grants/scopes/challenge/idempotency tables;
4. academic cycles and reference data;
5. admission attempts/preferences/corrections;
6. rooms, sessions, assignments, proctor duty, attendance, submissions;
7. grading/scores/import/OMR/provenance;
8. consultation/availability/reservation/release;
9. templates/documents/delivery evidence;
10. notifications/inquiry conditional tables;
11. knowledge/engine/integration operation tables;
12. audit events/checkpoints and verification indexes;
13. foreign-key tightening, unique/check constraints, and seed references;
14. deterministic synthetic seed and reset verification.

The migration chain must support a clean database and a rehearsed upgrade path. “Fresh migration works” alone is not sufficient for system-ready promotion.

## 7. ERD authority rule

The current Mermaid ERD is a conceptual illustration. GF-19 becomes persistence authority only after:

- every table is mapped to an aggregate/entity owner;
- every relationship has cardinality and delete policy;
- every unique/index/check rule is listed;
- conditional entities are marked as conditional;
- migration order and seed dependencies are defined;
- MySQL engine behavior is tested;
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
- no destructive cascade regression tests.

## 9. Schema holds

- final production database engine approval;
- exact applicant duplicate key and institutional identifier policy;
- retake/reassignment uniqueness rule;
- appointment-slot inclusion in the first slice;
- exact consultation reservation scope;
- retention periods and deletion/restriction implementation;
- audit key/checkpoint storage;
- conditional inquiry/engine/OMR table promotion;
- final Laravel authentication table/guard mapping.

## 10. Exit criteria

GF-19 may advance to `CONDITIONAL` only when the HLD/LLD boundaries remain stable, all first-slice tables and constraints are mapped, and every unresolved schema hold has a named decision ID or evidence gate.
