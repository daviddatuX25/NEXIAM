# Application, Event, Job, and Integration Contract Catalog

**Artifact:** NEXIAM-GF-20  
**Status:** DRAFT — REVIEW REQUIRED  
**Depends on:** GF-17 HLD checkpoint 1, GF-18 LLD, and GF-19 schema draft.  
**Purpose:** Define stable application boundaries for HTTP/Inertia actions, commands, queries, domain events, queued jobs, provider adapters, idempotency, and typed failures.

## 1. Contract posture

The application boundary is a modular-monolith contract, not an accidental collection of controller methods.

Every write path must have:

- a named command/action;
- an authenticated actor or explicit public-intake context;
- a capability/resource-scope requirement;
- a validated request DTO;
- an idempotency rule where replay is possible;
- one authoritative transaction boundary;
- an audit result;
- a committed event where asynchronous work is needed;
- a typed success/failure result.

Every read path must have:

- an explicit scope resolver;
- a field-level data contract;
- stable empty/loading/denied behavior;
- no hidden mutation;
- a query/runtime evidence target.

## 2. HTTP/Inertia boundary

### 2.1 Controller rules

Controllers may:

1. resolve the authenticated actor/context;
2. validate request shape and file metadata;
3. call policy authorization;
4. construct a command/query DTO;
5. invoke the application handler;
6. translate the typed result to redirect/page/validation/error response.

Controllers may not:

- contain multi-step domain orchestration;
- call providers directly for authoritative work;
- mutate multiple aggregates without a command transaction;
- decide whether an AI answer is authoritative;
- return protected data merely because a route is “internal.”

### 2.2 Response categories

| Result category | Inertia/browser behavior |
|---|---|
| Success | Redirect or page response with typed flash/status and refreshed authoritative props |
| Validation failure | Field-level errors; no state transition |
| Authorization denial | Generic denial; no protected existence/details unless policy allows |
| State conflict | Current legal action guidance; no partial mutation |
| Concurrency conflict | Safe retry/reselection message; stale data marked |
| Idempotent replay | Return original authoritative result or stable completion status |
| Policy hold | Explain that institutional/technical approval is required; no fake success |
| External failure | Preserve core state; queue/retry/manual fallback visible |
| Integrity failure | Stop/alert/escalate; no silent repair |

Exact serialization must follow Laravel/Inertia project convention, but the category semantics are fixed here.

## 3. Request and command DTO rules

All commands use explicit input objects rather than unbounded request arrays.

A command DTO records:

- command name/version;
- actor context or public-intake context;
- target public identifier(s);
- requested values;
- idempotency key when applicable;
- client/request correlation ID;
- source channel/browser metadata only where policy allows;
- consent/confirmation evidence where required.

DTOs must not contain raw passwords/challenge verifiers after the command boundary. Secrets are handled only by the credential adapter/service boundary and are excluded from logs/audit payloads.

## 4. Command contract catalog

| Command | Input contract | Authorization | Transaction result | After-commit effects |
|---|---|---|---|---|
| `SubmitAdmissionAttempt` | Cycle, applicant fields, ranked preferences, intake source, idempotency key | Public intake or Registrar intake capability | Identity/attempt/reference/preferences/audit | Challenge delivery intent, confirmation notification |
| `CorrectAdmissionAttempt` | Attempt reference, allowed field patch, reason, version | Registrar correction scope | Correction, attempt update, audit | Optional status notification |
| `AcceptAdmissionAttempt` | Attempt reference, reason, version | Registrar acceptance authority | Accepted transition and audit | Status notification |
| `DismissAdmissionAttempt` | Attempt reference, reason, version | Registrar acceptance authority | Dismissed transition and audit | Status notification |
| `ArchiveAdmissionAttempt` | Attempt reference, reason | Approved archive capability | Archive projection/history state | Optional notification |
| `IssueApplicantAccessChallenge` | Applicant/attempt, purpose, channel, request key | System submission or approved recovery actor | Hash-only challenge and audit | Delivery job |
| `CompleteApplicantAccessSetup` | Challenge reference/verifier, credential choice | Challenge subject/applicant | Atomic consume and credential linkage | Credential-established notification/audit |
| `CreateExamSession` | Cycle, room, mode, schedule, policy fields | Guidance exam capability | Draft session and audit | None until publication |
| `AssignAttemptToExamSession` | Session, attempt, idempotency key | Guidance scheduling capability | Assignment under capacity/conflict lock | Optional schedule projection |
| `PublishExamSession` | Session, expected version | Guidance publication capability | Published session and audit | Schedule notification job |
| `AssignProctorDuty` | Session, staff, effective period, reason | Test Administrator capability | Assignment/revocation and audit | Duty notification |
| `StartExamSession` | Session, expected version, optional override reason | Guidance/Test Administrator/approved override | In-progress transition | None |
| `RecordAttendance` | Assignment, outcome, client key | Active Proctor duty or approved Guidance capability | Idempotent attendance/audit | None |
| `RecordSubmission` | Assignment, receipt metadata, client key | Active Proctor duty or approved Guidance capability | Idempotent submission/audit | None |
| `CloseExamSession` | Session, source, reason/override, expected version | Guidance/Proctor/scheduler policy | One close/reconciliation/grading-open outcome | Grading notification/job |
| `OpenGradingSession` | Session/assessment unit, version | Guidance grading capability | One active grading version | None |
| `RecordScore` | Attempt, area, value, source, expected version | Guidance grading capability | Valid score and audit | Projection refresh |
| `StageScoreImport` | Private file ref, template/version, checksum, batch key | Guidance import capability | Durable batch/rows | Parse/analysis job if required |
| `ReviewScoreImportRow` | Batch/row, decision, corrected value, reason | Guidance review capability | Row review state/audit | None |
| `ImportReviewedScores` | Batch, reviewed rows, idempotency | Guidance import capability | Row-atomic authoritative score writes | Projection/audit effects |
| `SubmitGradingForReview` | Grading version, expected version | Guidance grading capability | Review transition | Review notification |
| `FinalizeGradingSession` | Grading version, expected version | Guidance finalization capability | Completeness/version/audit | Result-preparation intent |
| `RecordConsultationDecision` | Attempt, decision, rationale, signal refs | Counselor/Test Administrator capability | Human decision/version/audit | Consultation notification/projection |
| `PublishConsultationAvailability` | Mode, date/time, office, capacity | Guidance consultation capability | Availability/publication/audit | Applicant notification |
| `ReserveConsultationSlot` | Availability, applicant, idempotency | Applicant own-data capability | Reservation under slot lock | Confirmation notification |
| `PrepareResultDocument` | Attempt/release context, template/version | Guidance document capability | Artifact metadata/private storage ref | Render/conversion job or completion |
| `ReleaseResult` | Attempt, prerequisites, release mode, expected version | Approved Guidance release capability | Release/artifact/audit | Applicant notification/delivery |
| `ReverseResultRelease` | Current release, reason, expected version | Approved correction authority | Reversal/supersession/audit | Correction notification |
| `MarkNotificationRead` | Notification delivery, expected version | Recipient own-data capability | Read state | None |
| `ConfirmGuidanceInquiry` | Refusal/fallback reference, confirmation, message | Applicant own-data capability, conditional | Inquiry creation/idempotency | Guidance notification |

## 5. Query contract catalog

| Query | Input/scope | Output contract |
|---|---|---|
| `ApplicantStatusQuery` | Authenticated ApplicantIdentity | Safe current attempt status, next actions, selected history |
| `ApplicantNotificationQuery` | Recipient identity and filters | Authoritative unread count/list/read state |
| `ApplicantReleasedResultQuery` | Applicant identity and released attempts | Current approved release/artifact links only |
| `RegistrarIntakeQueueQuery` | Registrar office scope, filters, cursor | Paged queue with allowed fields and stale/version markers |
| `RegistrarAttemptDetailQuery` | Registrar scope + attempt | Intake data, corrections, decision history, no restricted result fields |
| `GuidanceSessionQueueQuery` | Guidance scope, cycle/time filters | Session, assignment, close/reconciliation data |
| `ProctorRosterQuery` | Active ProctorAssignment + session | Permitted roster/attendance/submission fields |
| `GradingWorkspaceQuery` | Guidance assessment scope | Scores, missing/invalid areas, import rows, version state |
| `ConsultationWorkspaceQuery` | Guidance scope | Decision evidence, signals, availability, release prerequisites |
| `ProgramHeadProjectionQuery` | Program scope + approved metric version | Aggregates/approved consultation-facing evidence with freshness metadata |
| `AuditAccessQuery` | Approved audit scope | Redacted protected-view/mutation evidence |
| `OperationsFailureQuery` | Operations capability | Jobs/provider/import/delivery failures and recovery actions |

## 6. Domain event contract

Committed events contain:

- event ID and event type/version;
- occurred-at timestamp;
- actor context or system source;
- subject public identifier and aggregate version;
- correlation/causation IDs;
- redacted event payload;
- schema version;
- audit reference.

Candidate event types:

- `AdmissionAttemptSubmitted`;
- `AdmissionAttemptCorrected`;
- `AdmissionAttemptAccepted`;
- `AdmissionAttemptDismissed`;
- `ApplicantAccessChallengeIssued`;
- `ApplicantCredentialEstablished`;
- `ExamSessionPublished`;
- `ExamAssignmentCreated`;
- `ProctorDutyAssigned`;
- `ProctorDutyRevoked`;
- `ExamSessionStarted`;
- `AttendanceRecorded`;
- `SubmissionRecorded`;
- `ExamSessionClosed`;
- `GradingOpened`;
- `ScoreImportStaged`;
- `ScoreImportReviewed`;
- `ScoreImported`;
- `GradingSubmittedForReview`;
- `GradingFinalized`;
- `ConsultationDecisionRecorded`;
- `ConsultationAvailabilityPublished`;
- `ResultDocumentPrepared`;
- `ResultReleased`;
- `ResultReleaseReversed`;
- `NotificationIntentCreated`;
- `ProtectedViewAccessed`;
- `GuidanceInquiryConfirmed` (conditional).

Events are not permissions and are not commands. Consumers must validate their own scope and idempotency.

## 7. Queued job contract

Every job records operation identity, attempts, timeout, provider, correlation ID, and terminal outcome.

| Job | Trigger | Retry/fallback |
|---|---|---|
| `DeliverApplicantAccessChallenge` | Challenge issued | Bounded retry; approved alternative/manual recovery if terminal |
| `DeliverStatusNotification` | Admission decision event | Idempotent per event/recipient/channel; dead-letter visibility |
| `DeliverScheduleNotification` | Session published | Retry/reconcile without changing schedule |
| `ProcessDocumentGeneration` | Preparation/release intent | Private artifact retry; preserve release state; operator reconciliation |
| `ProcessScoreImportBatch` | Staged batch | Durable row claims; resume failed rows; manual fallback |
| `ProcessApprovedOmrBatch` | Approved OMR gate | Abstain/review on uncertainty; no direct score write |
| `RunDecisionSupportEngine` | Approved support request | Proposal-only result; provider outage is visible |
| `SyncKnowledgeDocument` | Approved corpus change | Versioned retry; no unapproved source publication |
| `GenerateAuditCheckpoint` | Schedule/threshold | Key/version/destination evidence; alert on failure |
| `ReconcileIntegrationOperations` | Scheduler/operator | Detect stuck/duplicate/terminal operations |
| `PurgeExpiredChallenges` | Scheduler | Delete or anonymize only per approved credential policy |

## 8. Integration port catalog

### `MailDeliveryPort`

Input: recipient reference, approved channel, template/version, safe variables, correlation ID.  
Output: provider message reference, accepted/rejected/temporary-failure/terminal-failure.  
Rules: no raw challenge in logs; no fabricated address; no domain rollback on delivery failure.

### `PrivateStoragePort`

Input: owner, lifecycle class, content stream/reference, content type, checksum.  
Output: opaque storage key, metadata, availability result.  
Rules: private by default, no path traversal, authorization on retrieval, checksum verification.

### `DocumentRendererPort`

Input: typed template version and context-specific encoded values.  
Output: generated artifact stream/metadata or typed failure.  
Rules: no raw placeholder replacement; output is private until release policy allows distribution.

### `InferencePort`

Input: approved engine/version, input artifact reference, schema version, correlation ID.  
Output: observations/proposals/confidence/provenance or abstention/failure.  
Rules: authenticated worker; no direct DB write; no autonomous authoritative transition.

### `KnowledgeSearchPort`

Input: authorized corpus/data scope, query, filters, citation policy.  
Output: source references, excerpts, confidence/coverage metadata.  
Rules: scope is resolved before retrieval; own-data and policy corpus gates remain separate.

### `BoundedGenerationPort`

Input: approved context/citations, refusal policy, output schema.  
Output: answer proposal/citations/refusal/provider status.  
Rules: no permission decision, no placement/admission decision, no state mutation.

### `ClockPort` and `IdempotencyPort`

These ports make expiry, effective deadlines, deterministic tests, and replay semantics testable without relying on ambient time or browser state.

## 9. Idempotency and concurrency contract

An idempotency key is required for:

- public/staff submission;
- challenge issue/consume requests;
- attendance/submission;
- session assignment/reservation;
- score import batch/row;
- notification delivery;
- document generation/delivery;
- provider operations;
- reversal/retry commands where duplicate execution is dangerous.

The idempotency record stores command name/version, actor/scope, request hash, status, authoritative result reference, and expiry. A reused key with a different request hash is a conflict, not a new request.

## 10. Adapter failure contract

External outcomes are normalized to:

- accepted;
- completed;
- temporary failure;
- terminal rejection;
- timeout/unknown outcome;
- invalid response;
- unavailable/health failure.

Unknown outcome requires reconciliation before retry where duplicate external effects are possible.

Provider failures never:

- roll back a committed admission decision;
- create a fake success page;
- convert a proposal to an authoritative score;
- expose a private artifact;
- erase an audit record.

## 11. Authorization and audit contract at the boundary

Before a command handler runs:

1. authenticate or establish approved public-intake context;
2. resolve capability;
3. resolve office/program/resource scope;
4. load target by opaque/public reference;
5. evaluate state/time-window/assignment policy;
6. reject without leaking protected details;
7. write required audit evidence inside the mutation transaction.

Protected read queries use the same scope rules and emit access evidence when required by GF-08/D-023.

## 12. Contract-testing requirements

Every command requires:

- valid success test;
- validation failure test;
- authorization denial test;
- scope denial test;
- stale/version conflict test where applicable;
- idempotent replay test;
- concurrent/race test where applicable;
- audit atomicity test;
- after-commit job test;
- degraded provider/manual fallback test when integrated.

Every adapter requires:

- schema contract fixture;
- timeout/retry test;
- malformed/terminal failure test;
- secret/redaction test;
- provenance test;
- health/readiness test;
- no-direct-authoritative-write test for inference/provider adapters.

## 13. Open contract holds

- exact public versus internal route names;
- final Laravel/Inertia error serialization;
- applicant credential/delivery adapter;
- queue transport promotion from database to another backend;
- document renderer and PDF/DOCX policy;
- external provider identity/credentials and rate limits;
- audit event schema/key/checkpoint implementation;
- conditional inquiry thread command/retention;
- OMR/inference envelope version;
- RAG citation/refusal thresholds;
- operational SLOs and retry counts.

## 14. Exit criteria

GF-20 may advance only when:

- first-slice commands and queries map to GF-18 owners;
- command transaction boundaries map to GF-19 constraints;
- events/jobs have after-commit and idempotency behavior;
- adapter ports have typed input/output/failure envelopes;
- authorization occurs before protected data loading/mutation;
- test/evidence obligations are assigned;
- unresolved provider and policy choices remain explicit holds.
