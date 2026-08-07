# Module and Domain Low-Level Design

**Artifact:** NEXIAM-GF-18  
**Status:** DRAFT — REVIEW REQUIRED  
**HLD dependency:** GF-17 HLD checkpoint 1 GO as the working baseline; remaining HLD promotion gates still apply.
**Purpose:** Define implementation-level domain/application boundaries for the connected Admission-to-Release slice without prematurely fixing framework class names, migration syntax, or provider libraries.

## 1. LLD design rules

This LLD translates the approved/conditional product and workflow contracts into implementation responsibilities.

It does not yet authorize:

- migrations or domain code;
- a final credential provider;
- a final role/permission package;
- provider-specific SDKs;
- institutional/legal approval of production retention periods;
- provider-specific OMR/RAG/offline implementation details. The domain contracts for these capabilities are committed, with evidence gates before promotion.

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
4. Policies answer actor/resource/scope/state questions; UI visibility is not authorization. GF-21 defines the enforcement pipeline and GF-22 defines the corresponding browser states.
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
| ApplicationWindow | Public intake opening/closing and reasoned late-intake override | Versioned/effective-dated; closure does not delete cycle records |
| Program | Program identity and ownership | Codes/names are versioned/deactivated, not destructively removed when referenced |
| Room | Physical room and capacity | Capacity positive; inactive room cannot receive new session |
| AptitudeArea | Versioned score area definition | Area/version remains stable for historical grading |
| AppointmentSlot | Optional intake appointment capacity | Capacity cannot be overbooked under concurrent reservation |
| ExamSlotPlan | Test Administrator-controlled reservation policy and publication boundary | Only accepted attempts may reserve; states are auditable and capacity-safe |
| ExamSlotReservation | Accepted applicant’s reservation before staff finalization | One active reservation per approved scope; cancellation/finalization history preserved |
| ResultTemplate | Versioned result document template | New versions do not mutate historical generated artifacts |
| Mapping/MetricDefinition | Approved conversion/program/metric semantics | Versioned source, owner, effective period, and approval status |

Reference data is read by workflow modules through approved snapshots/contracts. It cannot silently change historical interpretation.

### 3.3 Admissions

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| AdmissionAttempt | Cycle-scoped application/intake workflow | One durable identity may own many attempts; reference unique; state transitions command-owned |
| ProgramPreference | Applicant-selected program ordering | Rank unique within attempt; choices remain separate from system suggestions |
| AdmissionCorrection | Controlled correction request/history | Actor, reason, previous values, approved fields, and outcome are recorded |

AdmissionAttempt is the authoritative owner of intake decision state. Registrar Admin, Registrar Clerk, and explicitly delegated Registrar Support Staff may accept/dismiss within Registrar scope; Support Staff cannot mutate institutional/reference setup. A dashboard pipeline stage is a projection, not a second mutable truth.

### 3.4 Examination

| Aggregate/entity | Owns | Key invariants |
|---|---|---|
| ExamSession | Session schedule, mode, room, lifecycle | Valid cycle/room/time; publication prerequisites; effective deadline |
| ExamAssignment | Attempt membership in session | Assignment unique per approved scope; accepted/eligible attempt only; capacity safe |
| DirectAssessment | Special late/same-day assessment context | Accepted eligible attempt, responsible Test Administrator, durable evidence, same grading/release invariants |
| ProctorAssignment | Contextual duty overlay | Session-bound, time-bound/revocable, no permanent role semantics |
| AttendanceRecord | Presence outcome and correction evidence | One authoritative attendance outcome per assignment/version |
| SubmissionRecord | Submission receipt/outcome | Idempotent receipt; assignment/session/time-window checks |

ExamSession owns the operational close boundary. Manual close and scheduler close invoke the same application command. The authorized Test Administrator controls reservation open/bypass, manual assignment, publication, force-open within the approved window, and close; Proctor performs assigned-session operations only.

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
| ConsultationDecision | Consultation workflow for an attempt | Human-owned decision; version/reason; separate from model suggestions |
| ConsultationPlan | Office-published online/F2F availability | Owner/scope/time/capacity; publication/cancellation history |
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
| AssistantExchange/ActionRequest | Policy/own-data answer or typed UI-equivalent task request | Scope resolved before prompt; action allowlist, confirmation, idempotency, and audit; no direct model/database authority |
| EngineManifest | Approved decision-support adapter/version | Capability, model/version, input/output schema, approval/status |

## 4. State ownership and transitions

### 4.1 AdmissionAttempt

The canonical intake decision state is `pending`; `submitted` is a milestone/event and must not be persisted as a competing decision value.

```text
pending
  ├─→ needs_correction
  ├─→ accepted
  ├─→ dismissed
  └─→ archived

needs_correction ─→ pending | dismissed | archived
accepted ─→ archived | approved correction path
```

Rules:

- setup/access challenge is issued after successful submission, not after acceptance;
- acceptance/dismissal is a separate Registrar-owned command;
- historical reasons and evidence remain attached;
- reopen/correction never cascades into deletion of identity, score, release, or audit history.

### 4.2 ExamSession

```text
draft → published → in_progress → completed
  ├─ draft → cancelled
  ├─ published → cancelled
  └─ completed → superseded only through approved correction/reconciliation
```

`effective_deadline` is the only deadline evaluated by attendance, submission, manual close, and scheduler close. Extension creates auditable deadline evidence.

`ExamSlotPlan` is separate from `ExamSession`:

```text
draft → open_for_slot_reservation → reservation_closed → finalized → published
  └─ bypassed (staff-assigned path)
```

Applicant reservation is disabled before acceptance and remains disabled until the Test Administrator opens the plan. Staff-assigned late cohorts and Direct Assessment do not fabricate a reservation; they record the bypass/source mode and responsible capability holder.

### 4.3 GradingSession

```text
open → review → finalized
  └─ finalized → new correction version after approved reopen/reason
```

Score completeness is a projection/finalization precondition. It is not an excuse to maintain contradictory independent workflow states.

### 4.4 Cross-aggregate consultation/release milestone projection

The following is a cross-aggregate milestone projection, not a database status enum for `ConsultationDecision`, `ConsultationPlan`, or `ResultRelease`. Each aggregate retains its own state and command owner.

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
| AcceptAdmissionAttempt | Registrar Admin, Registrar Clerk, or explicitly delegated Registrar Support Staff | Transition attempt to accepted | Decision reason, actor/scope/version audit |
| DismissAdmissionAttempt | Registrar Admin, Registrar Clerk, or explicitly delegated Registrar Support Staff | Transition attempt to dismissed | Decision reason, actor/scope/version audit |
| ArchiveAdmissionAttempt | Authorized Registrar/Guidance policy | Preserve history and hide from active queue | Archive reason and retention evidence |
| IssueApplicantAccessChallenge | System after submission or approved recovery flow | Create hashed one-time challenge | Purpose/channel/rate-limit audit; raw token never stored |
| CompleteApplicantAccessSetup | Applicant or approved assisted-recovery flow | Consume challenge and establish credential/link | Atomic consume and credential event |
| OpenExamSlotReservation | Authorized Test Administrator | Open a plan for applicant reservation | Date/slot/capacity/window/audit evidence |
| CloseExamSlotReservation | Authorized Test Administrator | Close applicant reservation and prevent new reservations | Closure reason/time/capacity snapshot/audit |
| FinalizeExamSlotAssignments | Authorized Test Administrator | Convert valid reservations into assignments | Conflict/capacity/eligibility evidence |
| CreateExamSession | Authorized Test Administrator | Create draft session | Session configuration audit |
| AssignAttemptToExamSession | Authorized Test Administrator | Create valid applicant-reserved or staff-assigned assignment | Source mode/capacity/conflict/eligibility evidence |
| StartDirectAssessment | Authorized Test Administrator | Create/start special direct assessment context | Accepted attempt, reason, source, and audit evidence |
| PublishExamSession | Authorized Test Administrator | Publish complete session | Publish audit; after-commit schedule delivery |
| AssignProctorDuty | Authorized Test Administrator | Create session-bound duty | Grant reason and scope audit |
| RevokeProctorDuty | Authorized Test Administrator or approved duty owner | Revoke session-bound duty | Revoke reason, effective time, and scope audit |
| StartExamSession | Assigned Proctor or Test Administrator | Transition session to in-progress | Time-window/force-open evidence |
| ExtendExamSession | Assigned Proctor or Test Administrator under approved limit | Update the one effective deadline/version | Reason, duration, expected version, and audit |
| UnpublishExamSession | Authorized Test Administrator before start | Return published session to draft | Reason, downstream reconciliation, and audit |
| CancelExamSession | Authorized Test Administrator | Cancel session without deleting assignments | Reason, assignment disposition, and audit |
| RecordAttendance | Assigned Proctor/Test Administrator | Record idempotent attendance | Assignment/time/idempotency evidence |
| RecordSubmission | Assigned Proctor/Test Administrator or approved intake | Record idempotent submission | Receipt/time/idempotency evidence |
| CloseExamSession | Proctor/Test Administrator/scheduler by policy | One effective close/reconciliation | Close source, effective deadline/grace, unresolved records |
| ReopenExamSession | Privileged Test Administrator before grading or approved supersession path | Reopen/supersede without deletion | Prior close, reason, impact, and audit |
| OpenGradingSession | Guidance grading authority | Create/activate one grading version | One-active invariant/audit |
| RecordScore | Authorized Guidance grading authority | Write validated manual score | Area/source/version/audit |
| StageScoreImport | Authorized Guidance grading authority | Create batch and rows | File metadata/checksum/row counts |
| ReviewScoreImportRow | Authorized scorer/Test Administrator | Review/resolve/correct staged row; scanner does not decide candidate acceptance or final score | Reviewer/reason/identity-binding/evidence |
| ImportReviewedScores | Authorized Guidance grading authority | Convert reviewed rows to authoritative score records | Batch/row idempotency and score audit |
| SubmitGradingForReview | Guidance grading authority | Move grading to review | Completeness/review evidence |
| ReturnGradingForCorrection | Authorized Guidance reviewer | Return review version to open | Reason, version, and correction audit |
| FinalizeGradingSession | Guidance grading authority | Finalize complete valid version | Finalization audit and projection |
| ReopenGradingSession | Privileged Guidance authority | Create a new correction version | Reason, prior-version supersession, and audit |
| RecordConsultationDraft | Guidance Counselor or authorized holder of Test Administrator capability | Create a human decision draft | Signal provenance and draft audit |
| ConfirmConsultationDecision | Guidance Counselor | Confirm approved/modified/overridden decision | Rationale, separate applicant selection, and audit |
| DeferConsultationDecision | Guidance Counselor | Defer decision | Reason, follow-up, and audit |
| ReviseConsultationDecision | Authorized Guidance Counselor with reason | Create a new decision version and supersede prior version | Prior-version, impact, and audit evidence |
| PublishConsultationAvailability | Guidance consultation authority | Publish scoped availability | Mode/capacity/time audit |
| ReserveConsultationSlot | Applicant own-data capability | Reserve permitted online slot | Applicant/slot idempotency and ownership |
| PrepareResultDocument | Authorized Guidance/Documents capability | Create/reconcile a generation operation; expose a ready artifact only after renderer success | Template/version/checksum/private storage and pending/failed/ready evidence |
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
| AuditAccessQuery | Super Admin full audit scope or approved review scope | Filtered event/access evidence with redaction |
| ExamSlotPlanQuery | Test Administrator scope or Applicant own reservation scope | Reservation state, capacity, finalization/publication state |
| AssistantContextQuery | Actor-scoped policy/own-data/task context | Approved sources and typed action affordances only |
| OperationsFailureQuery | Operations capability | Failed jobs, provider health, delivery/import reconciliation |

Every query must declare its scope resolver and fields; “load model and hide fields in the page” is not sufficient.

## 7. Transaction boundaries and concurrency

### 7.1 Required transaction patterns

- Submit: identity/attempt/reference/preferences/audit commit atomically.
- Accept/dismiss: attempt transition, reason, audit, and projection event commit atomically.
- Slot reservation/finalization/assignment: capacity/conflict/eligibility/source-mode commit atomically.
- Direct assessment start: accepted-attempt eligibility, context, responsible Test Administrator, and audit commit atomically.
- Attendance/submission: assignment lock/idempotency/result/audit commit atomically.
- Close: session lock, effective-deadline evaluation, unresolved reconciliation, close event, and grading-open decision commit atomically.
- Manual score save: all related area writes in one command transaction or one explicitly staged batch.
- Reviewed import: batch/row claims and authoritative score import use durable per-row idempotency.
- Finalization: completeness, version transition, result projection, and audit commit atomically.
- Document preparation: create one idempotent generation operation; pending/failed/ready status is durable, and `ResultDocumentPrepared` is emitted only after a verified private artifact/checksum exists.
- Release: prerequisites, release/version, ready artifact metadata, and audit commit atomically; delivery after commit.
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
- exam slot reservation/finalization ownership;
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
- Registrar Support Staff acceptance/dismissal does not imply setup/reference configuration authority.
- Internal preview is not external publication.
- Super Admin has full audit-scope user visibility, but that audit context is not a universal business-role shortcut and every audit query/export is itself audited.
- Test Administrator is a capability overlay, not a generic “Guidance” role; Proctor remains an assigned session duty.

GF-21 now defines the exact policy-context fields, enforcement pipeline, scope-query boundary, denial behavior, protected-view evidence, export/job/assistant rules, and verification matrix. GF-22 consumes those decisions for route metadata, page props, navigation, denied states, confirmation, and feedback without moving authorization into the browser.

## 9. Domain events and after-commit effects

Candidate committed facts:

- AdmissionAttemptSubmitted;
- AdmissionAttemptCorrected;
- AdmissionAttemptAccepted;
- AdmissionAttemptDismissed;
- AdmissionAttemptArchived;
- ApplicantAccessChallengeIssued;
- ApplicantCredentialEstablished;
- ExamSlotReservationOpened;
- ExamSlotReservationClosed;
- ExamSlotAssignmentsFinalized;
- ExamSessionPublished;
- ExamSessionUnpublished;
- ExamSessionExtended;
- ExamSessionCancelled;
- ExamSessionReopened;
- ExamAssignmentCreated;
- DirectAssessmentStarted;
- ProctorDutyAssigned;
- ProctorDutyRevoked;
- ExamSessionStarted;
- ExamSessionClosed;
- AttendanceRecorded;
- SubmissionRecorded;
- GradingOpened;
- GradingSubmittedForReview;
- GradingReturnedForCorrection;
- GradingFinalized;
- GradingReopened;
- ScoreImportStaged;
- ScoreImportReviewed;
- ScoreImported;
- ConsultationDecisionDrafted;
- ConsultationDecisionConfirmed;
- ConsultationDecisionDeferred;
- ConsultationDecisionRevised;
- ConsultationAvailabilityPublished;
- ResultDocumentPrepared;
- ResultReleased;
- ResultReleaseReversed;
- NotificationIntentCreated;
- ProtectedViewAccessed;
- GuidanceInquiryConfirmed (conditional).
- AssistantActionProposed;
- AssistantActionConfirmed;
- AssistantActionCompleted;
- AssistantActionRefused;
- OfflineOperationQueued;
- OfflineOperationReplayed;
- OfflineConflictResolved.

Events are facts, not commands. Listeners may create notifications/doc jobs/provider work, but they may not infer acceptance, final scores, or release from a loosely related event.

## 10. Typed failure taxonomy

The application contract should expose stable categories rather than framework exceptions as business behavior:

| Failure class | Example | User/operator behavior |
|---|---|---|
| ValidationFailure | Missing required intake field | Show field errors; no mutation |
| AuthorizationDenied | Applicant requests another applicant | Generic denial; audit where required; no protected detail |
| DutyExpired | Proctor duty or delegated capability expired/revoked | Stop protected controls; preserve safe draft; require reauthorization/reassignment |
| FeatureGated | Capability is behind an approved technical/evidence gate | Show not-available state and approved fallback; do not expose provider internals |
| PurposeRequired | Protected view/export/correction lacks an approved purpose | Request the safe purpose/confirmation without revealing protected data |
| BreakGlassRequired | Sensitive operation requires time-bound break-glass activation | Show activation/reviewer requirements before payload/action release |
| StateConflict | Accept already dismissed attempt | Explain current legal action; no partial mutation |
| ScopeConflict | Program Head requests another program | Deny without data leakage |
| ConcurrencyConflict | Capacity/reference/reservation race | Reload/reselect/retry with safe message |
| IdempotentReplay | Same submission/read/import key | Return original authoritative result |
| AcceptedForProcessing | Durable command/job operation accepted but not complete | Show operation ID and pending/reconciliation state; never claim completion |
| ExpiredChallenge | Setup/recovery verifier expired | Generic recovery path; no enumeration |
| ExternalTimeout | Mail/worker/provider timeout | Core state remains; retry/terminal failure visible |
| UnknownOutcome | External effect may have happened but outcome is not confirmed | Reconcile before retry; no duplicate side effect |
| ExternalRejected | Provider rejects malformed input | Preserve proposal/import failure; manual fallback |
| IntegrityFailure | Audit chain/checksum mismatch | Stop/alert/escalate; do not silently repair |
| AuditUnavailable | Mandatory audit evidence cannot be appended or verified | Fail closed for protected reads/mutations; alert operator |
| ArtifactUnavailable | Generated document missing/private storage failure | Keep release state; retry/regenerate under policy |
| ManualReconciliationRequired | Automated retry/replay cannot safely choose an outcome | Quarantine operation and route to authorized human resolution |
| PolicyHold | Missing stakeholder/technical gate | Block feature and show operator action, not fake success |

The exact HTTP/Inertia serialization belongs in GF-20 and the user-facing presentation/state mapping belongs in GF-22, but failure identity must remain stable across browser, job, assistant, and offline-replay boundaries.

## 11. First-slice implementation order implied by this LLD

1. Identity/access context and challenge contract.
2. Reference data and deterministic seed.
3. Admission submission/review/decision.
4. Exam session/assignment/proctor/attendance/submission.
5. Grading/manual score/finalization.
6. Consultation/release/document metadata.
7. Notifications and applicant read models.
8. Audit verification and failure/reconciliation evidence.
9. Slot reservation/manual assignment/direct-assessment paths and explicit offline-proctoring reconciliation after their gates.
10. Optional provider spike and proposal/import path only after its gate.

This is an implementation dependency order, not permission to begin before the readiness gate.

## 12. LLD exit criteria

Before GF-19 is promoted as the implementation-authoritative schema:

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
- ApplicationWindow and ExamSlotPlan persistence/transition details;
- exact session capacity/overlap enforcement for the selected database;
- PostgreSQL major version and pgvector extension/index/backup contract;
- exact score-area/version cardinality;
- whether consultation reservation is core or conditional;
- result-template storage/rendering adapter;
- audit serialization/key/checkpoint model;
- assistant action allowlist, confirmation UX, task/audit retention, and the GF-21/GF-22 proposal/confirmation/refusal presentation;
- offline device enrollment/key/replay/conflict-resolution details;
- inquiry thread retention/SLA if retained;
- provider-specific envelope versions.

Until these are resolved, GF-18 is a detailed design proposal, not a code-generation specification.
