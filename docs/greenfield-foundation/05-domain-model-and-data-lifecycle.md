# Domain Model and Data Lifecycle

**Artifact:** NEXIAM-GF-05  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Define the proposed greenfield domain vocabulary, aggregate ownership, critical invariants, and lifecycle rules before schema design.  
**Non-claim:** This is a conceptual contract, not a migration catalog or approved ERD.

## 1. Modeling principles

1. A person/account is not the same thing as one admission attempt.
2. One aggregate owns each state transition and invariant.
3. Historical examination, score, decision, release, and audit evidence is not destroyed by reopening operational work.
4. Derived read models never become competing workflow sources of truth.
5. Reference data is deactivated/versioned rather than destructively removed when history depends on it.
6. External side effects occur after the owning transaction commits.
7. Every hard-to-reverse data rule is decision-gated and enforced in both domain commands and database constraints where feasible.

## 2. Proposed bounded contexts and aggregates

### Identity and Access

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| `StaffAccount` | Staff authentication, status, role/capability grants, office/program assignments | Durable while employed; deactivate, do not erase audit identity |
| `ApplicantIdentity` | Durable normalized person/contact identity that owns cycle-scoped admission attempts, Applicant No/Reference values, and optional portal access, including unauthenticated public intake | Durable subject record across approved cycles; correction/merge/restriction is explicit |
| `ApplicantAccount` | Optional portal credential principal linked to one ApplicantIdentity and established after successful submission through an approved one-time challenge; Registrar acceptance is not a prerequisite | Durable account across approved cycles; deactivation does not erase ApplicantIdentity/history |
| `RoleGrant` | Permanent approved base-role assignment with granter and dates | Versioned/revocable |
| `DutyAssignment` | Contextual capability such as Proctor for one ExamSession | Time/state bounded and revocable |
| `CredentialChallenge` | Hashed one-time setup/reset/access challenge issued after successful Applicant submission or an approved recovery request | Short-lived, one-time, auditable; never a stored bearer secret |

### Admission Intake

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| AcademicCycle | Admission cycle/window and activation state | Historical, immutable after closure except reasoned correction |
| AdmissionAttempt | One ApplicantIdentity’s cycle-scoped application, stable Applicant No/Reference, preferences, intake decision, and workflow reference; Applicant No/Reference binds answer-sheet receipt/import | Durable historical record; may exist before ApplicantAccount activation |
| AppointmentSlot | Capacity-controlled intake appointment | Cycle-scoped; occupancy derived or transactionally maintained |
| ApplicationWindow | Academic-cycle public-intake opening/closing and reasoned late-intake override policy | Versioned/effective-dated; closure changes the cycle to historical scope and does not delete records |
| ApplicationDocument | Approved uploaded/generated intake artifact metadata | Retained by classification/version policy |

### Examination Administration

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| ExamSession | Schedule, room, capacity, status, assignment eligibility, and assessment mode | Historical, cancellable/archivable, not physically erased after use |
| ExamAssignment | AdmissionAttempt assigned to one session/mode | Historical child record; supports explicitly approved retake semantics |
| ExamSlotPlan | Test Administrator-controlled reservation policy for one or more exam dates/sessions | Draft → open for reservation → closed/finalized → published/bypassed; every change is attributable |
| ExamSlotReservation | Applicant’s requested/reserved slot before staff finalization | Accepted attempt only; capacity-safe, cancellable, and preserved as reservation history |
| ProctorAssignment | Staff duty for one session | Historical assignment and revocation evidence |
| `AttendanceRecord` | Present/absent/pending and actor/time/source | Immutable event history plus current projection |
| `SubmissionRecord` | Answer-sheet/submission receipt and chain of custody | Historical, idempotent receipt |

### Scoring and Grading

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| `GradingSession` | Score-entry/finalization workflow for one ExamSession or approved assessment unit | One active version or explicit version sequence |
| `ScoreRecord` | Score for one AdmissionAttempt and aptitude area, with source/version | Versioned corrections; final values never silently overwritten |
| `OmrBatch` | Input batch, answer key/version, processing status, provenance, and reconciliation | Retained technical evidence under privacy policy |
| `OmrObservation` | Detected mark, confidence, review disposition, and final accepted value | Immutable inference/review provenance |
| `AptitudeArea` | Versioned scoring dimension/rubric reference | Deactivate/version; do not rewrite historical meaning |

### Counseling and Release

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| `ConsultationPlan` | Published consultation mode/availability: default F2F batch schedule or online open reservation window | Versioned, office-owned, published/unpublished with audit; does not alter result evidence |
| `ConsultationReservation` | Applicant’s F2F batch assignment or online first-come reservation, with actor/source and scope | Durable reservation history; cancellation/rebooking preserves prior evidence |
| `ConsultationDecision` | Counselor/Test Administrator’s approved/modified/overridden/deferred outcome and rationale, including suggested programs/courses and the Applicant’s separate selected choice | Versioned, attributable, never autonomously generated; suggestions and selections are never silently collapsed |
| `InferenceResult` | Engine input hash, output, confidence, version, provenance | Immutable supporting evidence; not the final decision |
| `ResultRelease` | Release/unrelease event, mode, document version, actor, recipient effects | Immutable event/version history with current released projection |
| `GeneratedDocument` | Template/version, typed inputs, output checksum, storage reference | Retained according to document/data class |

### Communication and Knowledge

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| `NotificationDelivery` | Event/recipient/channel status, idempotency, retry, final failure | Operational history with retention policy |
| `InquiryThread` | Bounded applicant-to-office handoff where approved | Retained by communication policy |
| `KnowledgeDocument` | Institutional corpus source, owner, version, validity dates, sync status | Versioned; superseded sources preserved or archived |
| AssistantExchange | Authorized query, citations/refusal, data scope, provider metadata | Privacy-limited and retention-controlled |
| AssistantActionRequest | Typed UI-equivalent task proposal/confirmation/result linked to an AssistantExchange | Never a direct model command; server authorization, confirmation, idempotency, and audit required |

### Governance and Operations

| Aggregate/entity | Responsibility | Proposed lifetime |
|---|---|---|
| `AuditEvent` | Canonical actor/action/subject/context/diff/chain metadata | Append-only under approved threat model |
| `ProgramQuotaSnapshot` | Program-scoped capacity figure, source, effective dates, owner | Versioned; current value is a projection |
| `SystemSetting` | Non-secret versioned operational configuration | Audited changes; secrets referenced through secure storage |
| `IntegrationOperation` | External request/response status, idempotency, retry, health/provenance | Operational history by retention policy |

## 3. Proposed aggregate ownership

| Command family | Owning aggregate | Forbidden competing owner |
|---|---|---|
| Submit/accept/dismiss/reopen/archive admission | AdmissionAttempt | Controller, ApplicantAccount, report projection |
| Activate/close cycle | AcademicCycle | Arbitrary settings mutation |
| Open/close application window | ApplicationWindow/AcademicCycle | ExamSession or page-local state |
| Open/close/finalize slot reservation | ExamSlotPlan | Applicant page or arbitrary settings mutation |
| Create/publish/start/complete/cancel/reopen exam | ExamSession | Route middleware or page-local state |
| Assign applicant/reserve slot | ExamSession/ExamAssignment or ExamSlotReservation transaction boundary | Independent pivot write without eligibility checks |
| Assign/revoke Proctor | ExamSession/ProctorAssignment | Permanent role mutation |
| Record attendance/submission | ExamAssignment | Direct pivot mutation |
| Enter/import/correct/finalize scores | GradingSession | CSV importer or OMR worker directly |
| Process OMR | OmrBatch; accepted score import remains GradingSession command | Worker writing authoritative scores directly |
| Record counseling decision | ConsultationDecision | Inference engine |
| Release/unrelease | ResultRelease coordinated with AdmissionAttempt projection | Notification or document generator |
| Send notification | NotificationDelivery after domain event | Controller transaction before commit |
| Answer RAG query or request assistant task | Knowledge/RAG application service under policy, then typed application command | LLM prompt as authorization layer or direct tool/database call |
| Append audit event | Audit service within command transaction | Best-effort logging after state mutation |

## 4. Core state ownership

The proposed authoritative state families are:

- Admission intake status: pending, accepted, dismissed, archived/cancelled as approved.
- Admission workflow stage: projection of completed domain milestones, not an independently mutable second truth.
- ExamSession status: draft, published, in_progress, completed, cancelled.
- ExamSlotPlan status: draft, open_for_slot_reservation, reservation_closed, finalized, published, bypassed.
- ExamAssignment attendance/submission statuses.
- GradingSession status: open, review, finalized, with a superseded prior version when an approved correction/reopen creates a new version. `in_progress` is a derived activity indicator, not a competing authoritative state.
- ConsultationDecision status: draft, confirmed, superseded/deferred.
- ResultRelease status: unreleased/released/superseded through explicit release events.
- Integration operation status: pending, processing, succeeded, failed, retrying, exhausted.

Exact transition contracts are defined in `06-workflows-and-state-transitions.md`.

## 5. Critical data invariants

| Invariant ID | Proposed invariant | Evidence/risk addressed |
|---|---|---|
| INV-001 | One durable ApplicantIdentity may own multiple cycle-scoped AdmissionAttempts; one optional ApplicantAccount authenticates that identity; one attempt belongs to exactly one identity and cycle | Public pre-account intake, legacy applicant/application cardinality, and relinking ambiguity |
| INV-002 | Normalized identity uniqueness is explicitly scoped and database-enforced; duplicate handling is an approved workflow | Read-then-insert duplicate race |
| INV-003 | Applicant No/Reference uses a collision-safe generator or locked counter plus unique retry; it is unique per AdmissionAttempt, stable for its history, non-secret, and used to bind answer-sheet receipt/import | `COUNT + 1` race and wrong-sheet attribution |
| INV-004 | At most one active AcademicCycle, enforced through serialized activation and database support | Application-level active-cycle race |
| INV-005 | Appointment capacity cannot be exceeded under concurrent booking | Non-atomic `booked_count` drift |
| INV-006 | Room/session overlap cannot be committed concurrently | Application-only room-conflict check |
| INV-007 | One AdmissionAttempt cannot hold conflicting active exam assignments; retake uniqueness is explicit by cycle/mode | Global applicant uniqueness currently blocks future cycles/retakes |
| INV-008 | A published session has date/time/room, at least one eligible assignment, valid timing, and a source mode (`applicant_reserved`, `staff_assigned`, or `direct_assessment`) | Legacy publish intent and new slot/direct paths |
| INV-009 | Attendance/submission commands are idempotent and legal only for assigned attempts in an active session | Duplicate/concurrent pivot mutation risk |
| INV-010 | Grading finalization requires exactly one valid score per required active/versioned aptitude area per assigned attempt | Legacy completeness rule and nullable-area defect |
| INV-011 | One active grading workflow per assessment unit unless a version model is approved | Model `hasOne` without DB uniqueness |
| INV-012 | Score correction after finalization creates attributable version/correction evidence | Silent overwrite risk |
| INV-013 | No inference result autonomously changes admission, grading, counseling, or release state | Human-in-the-loop manuscript boundary |
| INV-014 | Release requires finalized scores and approved consultation/release prerequisites | Fragmented release checks |
| INV-015 | Unrelease/reversal preserves prior release event, generated artifact, actor, and reason | Forward-only pipeline contradiction |
| INV-016 | Reopen/archive never cascades deletion of identity, assignments, scores, counseling, release, or audit history | Legacy destructive reopen |
| INV-017 | Mandatory audit event and state mutation commit atomically where required; external effects are after-commit | Partial publish/release/notify failures |
| INV-018 | Every external operation has an idempotency key and observable terminal/retry status | Swallowed/repeated integration failures |
| INV-019 | Program Head queries are assigned-program projections: identified Potential rows require Registrar acceptance; protected record requires released result + applicant-acknowledged current selection or explicit targeted follow-up; other-program choices and raw artifacts never serialize | D-080/D-090 relationship model |
| INV-020 | Secrets and one-time tokens are hashed/redacted; identifiers are not bearer secrets | Legacy plaintext token/auth ambiguity |

## 6. Proposed data lifecycle matrix

Final periods require institutional/privacy approval; this table establishes behavior categories, not durations.

| Data class | Examples | Proposed lifecycle behavior | Delete/restrict rule | Decision/evidence |
|---|---|---|---|---|
| Identity/contact | ApplicantIdentity, ApplicantAccount, staff profile | Provisional/active → deactivated/restricted → retained or deleted per approved obligation | Never cascade-delete operational history; pseudonymize where lawful | D-DATA-01, D-073, EV-013 |
| Admission attempt | Application/intake/preferences/status | Draft/pending → decided → historical/archive | Correct through versioned command; no reopen destruction | D-DATA-02 |
| Examination evidence | assignments, attendance, submissions | Operational → finalized → historical | Preserve chain of custody and corrections | D-DATA-03 |
| Scores/grading | ScoreRecord, GradingSession | Draft → finalized → corrected/superseded | No silent overwrite or cascade delete | D-DATA-04 |
| Counseling/release | ConsultationDecision, ResultRelease | Draft → confirmed/released → superseded | Preserve rationale and release history with scoped access | D-DATA-05 |
| Generated documents | slips/results/reports | Generated/versioned → superseded/archive | Store checksum/template/version; destroy under approved schedule | D-DATA-06 |
| OMR/model artifacts | images, observations, inputs/results | Pending → processed/reviewed → retained/eligible-for-purge/purged | Raw sheets/images are status-managed and normally eligible for purge 90 days after accepted import/finalization and correction/appeal hold closure; derived provenance follows assessment/audit retention | D-035, D-RES-02, EV-013 |
| Messages/RAG exchanges | inquiry threads, prompts, citations | Active → closed → retained/deleted per policy | Minimize personal data; provider disclosure/redaction required | D-RAG-02, EV-013 |
| Audit/security | AuditEvent, access and role changes | Append-only active verification → archive/checkpoint | No routine user deletion; define lawful retention and pseudonymization | D-AUD-01, EV-013 |
| Operations | delivery attempts, integration logs | Active/retry → terminal → operational retention | Redact secrets/personal payloads; separate metrics from content | D-OPS-02 |
| Reference data | programs, rooms, aptitude areas, templates | Active → inactive/superseded | Do not remove definitions referenced by history | D-DATA-07 |

## 7. Deletion, correction, and reversal defaults

- Prefer correction events, superseding versions, deactivation, and archive over destructive deletion.
- A user-facing erasure request must be reconciled with assessment, audit, institutional, and backup obligations; “delete row” is not the policy.
- Reopen means reopen workflow or create a new attempt/version. It does not recreate identity by deleting dependent history.
- Cancelling a session preserves its assignments and reason; reassignment is an explicit command.
- Unrelease preserves the previous release event and artifact while changing the current visibility projection.
- Reference-data deactivation does not change historical labels/meaning; snapshot or version where interpretation matters.

## 8. Schema-design obligations after approval

The future migration-ready schema must specify:

- primary-key and public identifier strategy;
- foreign keys and delete actions;
- unique constraints and normalized identity keys;
- indexes for every scope and workflow query;
- status representation and allowed values;
- optimistic/pessimistic locking choices;
- JSON use only for genuinely variable, versioned payloads;
- object/file storage references and checksums;
- migration order, fresh install, upgrade, rollback, and restore rehearsal;
- production-engine parity rather than SQLite-only confidence.

## 9. Blocking decisions

- D-DOM-01: durable ApplicantIdentity, optional portal ApplicantAccount, and repeatable AdmissionAttempt.
- D-DOM-02: canonical naming—Application versus AdmissionAttempt in code and manuscript.
- D-DATA-01: identity uniqueness scope and duplicate resolution.
- D-DATA-02: archive/reopen/new-attempt semantics.
- D-DATA-03: retake/reassignment rules and ExamAssignment uniqueness.
- D-DATA-04: grading/score correction and versioning.
- D-DATA-05: consultation/release version and reversal semantics.
- D-DATA-06: document/raw OMR storage and retention.
- D-DATA-07: reference-data ownership/versioning.
- D-CYCLE-01: academic-cycle activation and historical-cycle behavior.
- D-RET-01: retention periods and erasure/restriction policy.
- D-INTAKE-01: public pre-account intake, real contact/recovery channel, and account activation point.
