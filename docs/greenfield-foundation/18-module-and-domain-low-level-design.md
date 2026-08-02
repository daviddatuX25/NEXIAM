# Module and Domain Low-Level Design

**Artifact:** NEXIAM-GF-18  
**Status:** DRAFT — REVIEW REQUIRED  
**HLD dependency:** GF-17 provisional working baseline; not yet founder-approved.  
**Purpose:** Define implementation-level domain/application boundaries for the connected Admission-to-Release slice without prematurely fixing framework class names, migration syntax, or provider libraries.

## 1. LLD design rules

This LLD translates the approved/conditional product and workflow contracts into implementation responsibilities.

It does not yet authorize:

- migrations or domain code;
- a final credential provider;
- a final role/permission package;
- provider-specific SDKs;
- production retention periods;
- optional OMR/RAG/offline implementation.

It does require every first-slice behavior to have an explicit owner, transaction boundary, authorization point, failure result, audit result, and test evidence target.

### 1.1 Layer rules

```text
HTTP/Inertia adapter
  → request DTO/form validation
  → authorization context
  → application command/query
  → domain aggregate/value object/policy
  → persistence port or Eloquent mapping
  → after-commit event/job
  → adapter/provider
```

Rules:

1. Controllers invoke one command or query and return a response; they do not orchestrate workflows.
2. Commands own transaction boundaries and authoritative state mutation.
3. Queries return typed read models/view models; they do not mutate state.
4. Policies answer actor/resource/scope/state questions; UI visibility is not authorization.
5. Domain events describe committed facts; listeners perform non-authoritative side effects.
6. Eloquent models map persistence and relationships but do not become hidden workflow orchestrators.
7. External workers/providers never write authoritative domain tables.
8. A correction is a new version/event unless an approved contract explicitly allows an in-place edit.

## 2. Proposed module layout

```text
app/
  Modules/
    IdentityAccess/
    ReferenceData/
    Admissions/
    Examination/
    Assessment/
    ConsultationRelease/
    DocumentsReporting/
    Communications/
    AuditCompliance/
    Knowledge/
    DecisionSupport/
    Platform/
```

Each module may contain:

```text
Application/Commands
Application/Queries
Domain/Entities
Domain/ValueObjects
Domain/Events
Domain/Policies
Infrastructure/Persistence
Infrastructure/Adapters
Http/Controllers
Http/Requests
Http/Resources-or-ViewModels
```

The exact namespace and whether Laravel modules use folders, bounded packages, or a simpler directory convention is an ADR/implementation decision. The dependency rules are authoritative regardless of folder syntax.

## 3. Aggregate and ownership catalog

### 3.1 Identity and access

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| StaffAccount | Named staff identity and status | Unique normalized login; disabled staff cannot authenticate or act |
| ApplicantIdentity | Longitudinal applicant identity | Durable across cycles; identity history is not deleted by attempt reopen |
| ApplicantAccount | Optional applicant credential linkage | At most the approved number of active account links; credential is never Applicant No alone |
| CredentialChallenge | One-time setup/recovery verifier lifecycle | Store verifier hash only; short expiry; atomic consume; purpose/subject binding; rate-limited |
| RoleGrant/ScopeGrant | Time-bound capability and office/program/resource scope | No grant exceeds grantor ceiling; revoked/expired grants cannot authorize |
| AuthenticationSession | Active browser/session state | Rotation, expiry, revocation, and actor binding are explicit |

`ApplicantIdentity` and `AdmissionAttempt` are separate concepts. Applicant access may be established after submission, while Registrar acceptance remains an independent command.

### 3.2 Reference data and academic configuration

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| AcademicCycle | Cycle code, active window, lifecycle | At most approved active cycle/window; historical cycles remain readable by scope |
| Program | Program identity and ownership | Codes/names are versioned/deactivated, not destructively removed when referenced |
| Room | Physical room and capacity | Capacity positive; inactive room cannot receive new session |
| AptitudeArea | Versioned score area definition | Area/version remains stable for historical grading |
| AppointmentSlot | Optional intake appointment capacity | Capacity cannot be overbooked under concurrent reservation |
| ResultTemplate | Versioned result document template | New versions do not mutate historical generated artifacts |
| Mapping/MetricDefinition | Approved conversion/program/metric semantics | Versioned source, owner, effective period, and approval status |

Reference data is read by workflow modules through approved snapshots/contracts. It cannot silently change historical interpretation.

### 3.3 Admissions

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| AdmissionAttempt | Cycle-scoped application/intake workflow | One durable identity may own many attempts; reference unique; state transitions command-owned |
| ProgramPreference | Applicant-selected program ordering | Rank unique within attempt; choices remain separate from system suggestions |
| AdmissionCorrection | Controlled correction request/history | Actor, reason, previous values, approved fields, and outcome are recorded |

AdmissionAttempt is the authoritative owner of intake decision state. A dashboard pipeline stage is a projection, not a second mutable truth.

### 3.4 Examination

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| ExamSession | Session schedule, mode, room, lifecycle | Valid cycle/room/time; publication prerequisites; effective deadline |
| ExamAssignment | Attempt membership in session | Assignment unique per approved scope; accepted/eligible attempt only; capacity safe |
| ProctorAssignment | Contextual duty overlay | Session-bound, time-bound/revocable, no permanent role semantics |
| AttendanceRecord | Presence outcome and correction evidence | One authoritative attendance outcome per assignment/version |
| SubmissionRecord | Submission receipt/outcome | Idempotent receipt; assignment/session/time-window checks |

ExamSession owns the operational close boundary. Manual close and scheduler close invoke the same application command.

### 3.5 Assessment and grading

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| GradingSession | One active grading version for an assessment unit | State `open → review → finalized`; one active version; finalization complete/valid |
| ScoreRecord | Score for attempt/area/version | Area/attempt/session consistency; source/provenance; correction versioning |
| ScoreImportBatch | Staged import lifecycle | Durable batch identity; immutable source metadata; explicit row state; resumable |
| ScoreImportRow | One parsed/imported/rejected row | Row status is explicit; idempotency prevents duplicate authoritative score |
| OmrBatch/Observation | Proposal observations and human review | Approved template/input; confidence/provenance; observation is not authoritative score |
| InferenceResult/SupportSignal | Decision-support output provenance | Engine/input/version/hash/time; proposal only; no domain mutation authority |

Manual score entry and reviewed import converge on the same authoritative score command/invariants.

### 3.6 Consultation and release

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| ConsultationCase | Consultation workflow for an attempt | Human-owned decision; version/reason; separate from model suggestions |
| ConsultationAvailability | Office-published online/F2F availability | Owner/scope/time/capacity; publication/cancellation history |
| ConsultationReservation | Applicant reservation where online branch allows it | Applicant/slot uniqueness; state and cancellation rules; no cross-applicant mutation |
| ResultRelease | Current release/reversal history | Prerequisites validated; current visibility explicit; prior releases preserved |
| GeneratedDocument | Artifact metadata and lifecycle | Owner, template/version, checksum, private storage key, generation/distribution/print evidence |

F2F and online consultation are branches of one release contract, not one ambiguous state.

### 3.7 Communications, audit, and optional modules

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| NotificationDelivery | Per-event/per-recipient delivery and read state | Idempotent delivery key; own-data scope; terminal failure visible |
| InquiryThread (conditional) | Confirmed bounded Guidance inquiry | Created only after applicant confirmation; office scope, retention, unread/SLA if retained |
| AuditEvent | Mandatory mutation/access evidence | Canonical event, sequence, subject, actor, redaction, chain fields |
| AuditCheckpoint | External verification checkpoint | Key/version/destination/time and verification result |
| KnowledgeDocument | Approved corpus source/version/sync state | Corpus audience/scope and provenance; no unapproved source injection |
| EngineManifest | Approved decision-support adapter/version | Capability, model/version, input/output schema, approval/status |

## 4. State ownership and transitions

### 4.1 AdmissionAttempt

```text
submitted
  ├─→ needs_correction
  ├─→ accepted
  ├─→ dismissed
  └─→ archived

needs_correction ─→ submitted | dismissed | archived
accepted ─→ archived | approved correction path
```

Rules:

- setup/access challenge is issued after successful submission, not after acceptance;
- acceptance/dismissal is a separate Registrar-owned command;
- historical reasons and evidence remain attached;
- reopen/correction never cascades into deletion of identity, score, release, or audit history.

### 4.2 ExamSession

```text
draft → published → in_progress → closed
  ├─ draft → cancelled
  ├─ published → cancelled
  └─ closed → superseded only through approved correction/reconciliation
```

`effective_deadline` is the only deadline evaluated by attendance, submission, manual close, and scheduler close. Extension creates auditable deadline evidence.

### 4.3 GradingSession

```text
open → review → finalized
  └─ finalized → new correction version after approved reopen/reason
```

Score completeness is a projection/finalization precondition. It is not an excuse to maintain contradictory independent workflow states.

### 4.4 Consultation and ResultRelease

```text
consultation_pending
  → consultation_recorded
  → prepared_or_printed_when_required
  → released
  → reversed_or_superseded through explicit command
```

Online consultation availability may remain open after digital release. F2F uses published batch availability and printed result handling. Exact institutional prerequisites remain conditional on D-044/D-077 evidence.

## 5. Command catalog for the connected slice

Each command has one transaction boundary unless explicitly marked as an external/projection command.

| Command | Owner/capability | Authoritative effect | Required evidence |
|---|---|---|---|
| SubmitAdmissionAttempt | Applicant/public intake or authorized Registrar intake | Create/link ApplicantIdentity, AdmissionAttempt, reference, preferences | Submission audit; after-commit challenge delivery intent |
| CorrectAdmissionAttempt | Authorized Registrar intake/correction | Change approved fields under correction policy | Before/after evidence, reason |
| AcceptAdmissionAttempt | Registrar acceptance authority | Transition attempt to accepted | Decision reason, actor/scope audit |
| DismissAdmissionAttempt | Registrar acceptance authority | Transition attempt to dismissed | Decision reason, actor/scope audit |
| ArchiveAdmissionAttempt | Authorized Registrar/Guidance policy | Preserve history and hide from active queue | Archive reason and retention evidence |
| IssueApplicantAccessChallenge | System after submission or approved recovery flow | Create hashed one-time challenge | Purpose/channel/rate-limit audit; raw token never stored |
| CompleteApplicantAccessSetup | Applicant or approved assisted-recovery flow | Consume challenge and establish credential/link | Atomic consume and credential event |
| CreateExamSession | Guidance exam authority | Create draft session | Session configuration audit |
| AssignAttemptToExamSession | Guidance exam authority | Create valid assignment | Capacity/conflict/eligibility evidence |
| PublishExamSession | Guidance exam authority | Publish complete session | Publish audit; after-commit schedule delivery |
| AssignProctorDuty | Authorized Test Administrator | Create/revoke session-bound duty | Grant/revoke reason and scope audit |
| StartExamSession | Guidance/Test Administrator or approved override | Transition session to in-progress | Time-window/override evidence |
| RecordAttendance | Assigned Proctor/Test Administrator | Record idempotent attendance | Assignment/time/idempotency evidence |
| RecordSubmission | Assigned Proctor/Test Administrator or approved intake | Record idempotent submission | Receipt/time/idempotency evidence |
| CloseExamSession | Proctor/Guidance/scheduler by policy | One effective close/reconciliation | Close source, effective deadline, unresolved records |
| OpenGradingSession | Guidance grading authority | Create/activate one grading version | One-active invariant/audit |
| RecordScore | Authorized Guidance grading authority | Write validated manual score | Area/source/version/audit |
| StageScoreImport | Authorized Guidance grading authority | Create batch and rows | File metadata/checksum/row counts |
| ReviewScoreImportRow | Authorized Guidance grading authority | Accept/reject/correct staged row | Reviewer/reason/evidence |
| ImportReviewedScores | Authorized Guidance grading authority | Convert reviewed rows to authoritative score records | Batch/row idempotency and score audit |
| SubmitGradingForReview | Guidance grading authority | Move grading to review | Completeness/review evidence |
| FinalizeGradingSession | Guidance grading authority | Finalize complete valid version | Finalization audit and projection |
| RecordConsultationDecision | Counselor/Test Administrator capability | Record human decision and rationale | Signal provenance and human action audit |
| PublishConsultationAvailability | Guidance consultation authority | Publish scoped availability | Mode/capacity/time audit |
| ReserveConsultationSlot | Applicant own-data capability | Reserve permitted online slot | Applicant/slot idempotency and ownership |
| PrepareResultDocument | Authorized Guidance/Documents capability | Generate internal artifact | Template/version/checksum/private storage |
| ReleaseResult | Approved Guidance release capability | Commit release and current visibility | Prerequisite audit; after-commit delivery |
| ReverseResultRelease | Approved correction authority | Create reversal/supersession | Reason, prior release preserved |
| MarkNotificationRead | Recipient own-data capability | Update read state | Ownership/idempotency evidence |
| ConfirmGuidanceInquiry | Applicant own-data capability, conditional | Create bounded inquiry after explicit confirmation | Confirmation, scope, idempotency |

Commands with exact capability ownership still marked `HOLD` must not be implemented as broad role checks.

## 6. Query/read-model catalog

Queries do not mutate authoritative state.

| Query | Scope | Read model |
|---|---|---|
| ApplicantStatusQuery | Applicant own identity/attempt | Current attempt stage, required actions, safe history |
| ApplicantNotificationQuery | Applicant own recipient | Unread count, list, delivery status, read state |
| ApplicantReleasedResultQuery | Applicant own released artifacts | Current release, approved document links, consultation status |
| RegistrarIntakeQueueQuery | Registrar office scope | Pending/correction/decision queue with safe fields |
| RegistrarAttemptDetailQuery | Registrar scope | Intake fields, history, decision evidence; no restricted result fields |
| GuidanceSessionQueueQuery | Guidance scope | Sessions, assignments, deadlines, unresolved close items |
| ProctorRosterQuery | Active assignment/session scope | Assigned roster and permitted attendance/submission fields |
| GradingWorkspaceQuery | Guidance assessment scope | Scores, missing/invalid areas, import/review state |
| ConsultationWorkspaceQuery | Guidance scope | Human decision, signals, availability, preparation/release prerequisites |
| ProgramHeadProjectionQuery | Program ownership scope | Approved aggregate/consultation-facing projection with metric metadata |
| AuditAccessQuery | Break-glass/approved review scope | Filtered event/access evidence with redaction |
| OperationsFailureQuery | Operations capability | Failed jobs, provider health, delivery/import reconciliation |

Every query must declare its scope resolver and fields; “load model and hide fields in the page” is not sufficient.

## 7. Transaction boundaries and concurrency

### 7.1 Required transaction patterns

- Submit: identity/attempt/reference/preferences/audit commit atomically.
- Accept/dismiss: attempt transition, reason, audit, and projection event commit atomically.
- Session assignment: capacity/conflict/eligibility/assignment commit atomically.
- Attendance/submission: assignment lock/idempotency/result/audit commit atomically.
- Close: session lock, effective-deadline evaluation, unresolved reconciliation, close event, and grading-open decision commit atomically.
- Manual score save: all related area writes in one command transaction or one explicitly staged batch.
- Reviewed import: batch/row claims and authoritative score import use durable per-row idempotency.
- Finalization: completeness, version transition, result projection, and audit commit atomically.
- Release: prerequisites, release/version, artifact metadata, and audit commit atomically; delivery after commit.
- Read notification: ownership check and read mutation are idempotent.

### 7.2 Race-sensitive invariants

Database/locking evidence is required for:

- Applicant No/Reference generation;
- normalized duplicate identity;
- appointment/session capacity;
- room/session overlap;
- one active proctor assignment where applicable;
- one active grading version;
- one effective session close;
- challenge consumption;
- score import row replay;
- notification delivery deduplication;
- consultation reservation ownership;
- release/reversal version sequencing;
- audit sequence/hash append.

## 8. Authorization enforcement model

Authorization is evaluated at multiple levels:

```text
authenticated actor
  → capability grant
  → office/program/resource scope
  → subject ownership or assignment
  → workflow state/time window
  → action-specific policy
```

Examples:

- Applicant No identifies an attempt but does not grant access.
- Proctor capability is valid only with active ProctorAssignment for the target session.
- Program Head capability is scoped to assigned program and approved projection fields.
- Registrar acceptance is not implied by Registrar read access.
- Internal preview is not external publication.
- Super Admin break-glass is an exceptional access context, not a universal business-role shortcut.

GF-21 must define the exact policy classes, scope query rules, and denial/audit behavior.

## 9. Domain events and after-commit effects

Candidate committed facts:

- AdmissionAttemptSubmitted;
- AdmissionAttemptCorrected;
- AdmissionAttemptAccepted;
- AdmissionAttemptDismissed;
- ApplicantAccessChallengeIssued;
- ApplicantCredentialEstablished;
- ExamSessionPublished;
- ExamAssignmentCreated;
- ProctorDutyAssigned/ProctorDutyRevoked;
- ExamSessionStarted/Closed;
- AttendanceRecorded;
- SubmissionRecorded;
- GradingOpened/SubmittedForReview/Finalized;
- ScoreImportStaged/Reviewed/Imported;
- ConsultationDecisionRecorded;
- ConsultationAvailabilityPublished;
- ResultDocumentPrepared;
- ResultReleased/Reversed;
- NotificationIntentCreated;
- ProtectedViewAccessed;
- InquiryConfirmed (conditional).

Events are facts, not commands. Listeners may create notifications/doc jobs/provider work, but they may not infer acceptance, final scores, or release from a loosely related event.

## 10. Typed failure taxonomy

The application contract should expose stable categories rather than framework exceptions as business behavior:

| Failure class | Example | User/operator behavior |
|---|---|---|
| ValidationFailure | Missing required intake field | Show field errors; no mutation |
| AuthorizationDenied | Applicant requests another applicant | Generic denial; audit where required; no protected detail |
| StateConflict | Accept already dismissed attempt | Explain current legal action; no partial mutation |
| ScopeConflict | Program Head requests another program | Deny without data leakage |
| ConcurrencyConflict | Capacity/reference/reservation race | Reload/reselect/retry with safe message |
| IdempotentReplay | Same submission/read/import key | Return original authoritative result |
| ExpiredChallenge | Setup/recovery verifier expired | Generic recovery path; no enumeration |
| ExternalTimeout | Mail/worker/provider timeout | Core state remains; retry/terminal failure visible |
| ExternalRejected | Provider rejects malformed input | Preserve proposal/import failure; manual fallback |
| IntegrityFailure | Audit chain/checksum mismatch | Stop/alert/escalate; do not silently repair |
| ArtifactUnavailable | Generated document missing/private storage failure | Keep release state; retry/regenerate under policy |
| PolicyHold | Missing stakeholder/technical gate | Block feature and show operator action, not fake success |

The exact HTTP/Inertia serialization belongs in GF-20, but failure identity must remain stable across browser and job boundaries.

## 11. First-slice implementation order implied by this LLD

1. Identity/access context and challenge contract.
2. Reference data and deterministic seed.
3. Admission submission/review/decision.
4. Exam session/assignment/proctor/attendance/submission.
5. Grading/manual score/finalization.
6. Consultation/release/document metadata.
7. Notifications and applicant read models.
8. Audit verification and failure/reconciliation evidence.
9. Optional provider spike and proposal/import path only after its gate.

This is an implementation dependency order, not permission to begin before the readiness gate.

## 12. LLD exit criteria

Before GF-19 schema design begins:

- every first-slice aggregate has an owner and lifecycle;
- every state transition has one command owner;
- every command has actor/capability/scope/precondition/effect/failure/audit behavior;
- every race-sensitive invariant has a DB or locking strategy;
- manual core has no worker/provider dependency;
- optional proposals cannot become authoritative without human import;
- query scope and fields are explicit;
- module dependency violations are listed and testable;
- unresolved decisions are linked to D-IDs and not hidden in implementation prose.

## 13. Open LLD decisions

- final applicant credential type and delivery channel;
- exact grant storage and scope-resolution mechanism;
- exact admission decision vocabulary and historical mapping;
- whether AppointmentSlot belongs in the first slice;
- exact session capacity/overlap enforcement for the selected database;
- exact score-area/version cardinality;
- whether consultation reservation is core or conditional;
- result-template storage/rendering adapter;
- audit serialization/key/checkpoint model;
- inquiry thread retention/SLA if retained;
- provider-specific envelope versions.

Until these are resolved, GF-18 is a detailed design proposal, not a code-generation specification.
