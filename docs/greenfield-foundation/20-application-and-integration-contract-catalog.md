# Application, Event, Job, and Integration Contract Catalog

**Artifact:** NEXIAM-GF-20  
**Status:** DRAFT — REVIEW REQUIRED  
**Depends on:** GF-17 HLD checkpoint 1, GF-18 LLD, and GF-19 schema draft.  
**Purpose:** Define stable application boundaries for HTTP/Inertia actions, commands, queries, domain events, queued jobs, provider adapters, idempotency, and typed failures. GF-21 and GF-22 consume these contracts for authorization enforcement and browser route/page/state behavior.

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
| Duty/feature/purpose/break-glass denial | Stable safe reason category and the corresponding reauthorization, fallback, purpose, or activation state; no protected detail |
| State conflict | Current legal action guidance; no partial mutation |
| Concurrency conflict | Safe retry/reselection message; stale data marked |
| Idempotent replay | Return original authoritative result or stable completion status |
| Accepted for processing | Return operation ID and durable pending state; no completion claim |
| Policy hold | Explain that institutional/technical approval is required; no fake success |
| External failure | Preserve core state; queue/retry/manual fallback visible |
| Unknown external outcome | Reconciliation state; retry is blocked when duplicate effect is possible |
| Integrity failure | Stop/alert/escalate; no silent repair |
| Mandatory audit failure | Fail closed for protected reads/mutations; expose operator-safe incident state |

Exact serialization must follow Laravel/Inertia project convention, but the category semantics are fixed here. GF-22 maps these categories to page/panel state, field errors, confirmations, conflict views, retry behavior, toast/notification wording, and accessible announcements; a generic success flash must not replace the authoritative state or recovery path.

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
| `AcceptAdmissionAttempt` | Attempt reference, reason, version | Registrar Admin, Registrar Clerk, or explicitly delegated Registrar Support Staff | Accepted transition and audit | Status notification |
| `DismissAdmissionAttempt` | Attempt reference, reason, version | Registrar Admin, Registrar Clerk, or explicitly delegated Registrar Support Staff | Dismissed transition and audit | Status notification |
| `ArchiveAdmissionAttempt` | Attempt reference, reason, expected version, idempotency key | Approved archive capability | Archive projection/history state | Optional notification |
| `IssueApplicantAccessChallenge` | Applicant/attempt, purpose, channel, request key, idempotency key | System submission or approved recovery actor | Hash-only challenge and audit | Delivery job |
| `CompleteApplicantAccessSetup` | Challenge reference/verifier, credential choice, idempotency key | Challenge subject/applicant | Atomic consume and credential linkage | Credential-established notification/audit |
| `OpenExamSlotReservation` | Cycle, dates, slot/capacity policy, expected version | Authorized Test Administrator capability | Open slot plan and audit | Applicant reservation affordance |
| `CloseExamSlotReservation` | Slot plan, reason, expected version | Authorized Test Administrator capability | Closed reservation boundary and audit | Optional status notification |
| `FinalizeExamSlotAssignments` | Slot plan, reservation set, expected version | Authorized Test Administrator capability | Finalized assignments under capacity/conflict lock | Schedule projection |
| `CreateExamSession` | Cycle, room, mode, schedule, policy fields, source mode, idempotency key | Authorized Test Administrator capability | Draft session and audit | None until publication |
| `AssignAttemptToExamSession` | Session, attempt, source mode, idempotency key | Authorized Test Administrator capability | Applicant-reserved or staff-assigned assignment under capacity/conflict lock | Optional schedule projection |
| `StartDirectAssessment` | Accepted attempt, reason, assessment mode, optional room/context, idempotency key | Authorized Test Administrator capability | Direct assessment context and audit; no applicant reservation required | Optional immediate notification |
| `PublishExamSession` | Session, expected version | Authorized Test Administrator capability | Published session and audit | Schedule notification job |
| `AssignProctorDuty` | Session, staff, effective period, reason, expected version, idempotency key | Test Administrator capability | Assignment and audit | Duty notification |
| `RevokeProctorDuty` | Session, staff duty, reason, expected version | Test Administrator capability or approved duty owner | Revocation and audit | Duty/reconciliation notification |
| `StartExamSession` | Session, expected version, optional force-open reason | Assigned Proctor or Test Administrator; force-open still requires approved session window | In-progress transition | None |
| `ExtendExamSession` | Session, duration/new effective end, reason, expected version | Assigned Proctor or Test Administrator under approved limit | One effective deadline/version update and audit | Roster/monitoring update |
| `UnpublishExamSession` | Session, reason, expected version | Test Administrator capability before start | Return to draft and audit | Reconciliation/correction notification |
| `RecordAttendance` | Assignment, outcome, expected version, idempotency key/client key | Active Proctor duty or Test Administrator capability | Idempotent attendance/audit | None |
| `RecordSubmission` | Assignment, receipt metadata, expected version, idempotency key/client key | Active Proctor duty or Test Administrator capability | Idempotent submission/audit | None |
| `CloseExamSession` | Session, source, reason/override, expected version | Proctor/Test Administrator/scheduler policy | One close/reconciliation/grading-open outcome | Grading notification/job |
| `CancelExamSession` | Session, reason, expected version | Test Administrator capability | Cancellation and assignment disposition/audit | Notices/reassignment work |
| `ReopenExamSession` | Session, reason, expected version | Privileged Test Administrator capability | Reopen or superseding correction path/audit | Reconciliation notifications |
| `OpenGradingSession` | Session/assessment unit, version | Guidance grading capability | One active grading version | None |
| `RecordScore` | Attempt, area, value, source, expected version | Guidance grading capability | Valid score and audit | Projection refresh |
| `StageScoreImport` | Private file ref, template/version, checksum, batch key | Guidance import capability | Durable batch/rows | Parse/analysis job if required |
| `ReviewScoreImportRow` | Batch/row, decision, corrected value, applicant binding, reason, expected version, idempotency key | Authorized scorer/Test Administrator | Human review/binding/correction state and audit; no autonomous acceptance/final-score decision | None |
| `ImportReviewedScores` | Batch, reviewed rows, idempotency | Guidance import capability | Row-atomic authoritative score writes | Projection/audit effects |
| `SubmitGradingForReview` | Grading version, expected version | Guidance grading capability | Review transition | Review notification |
| `ReturnGradingForCorrection` | Grading version, reason, expected version | Authorized Guidance reviewer | Return to open with correction audit | Scorer/import-owner notification |
| `FinalizeGradingSession` | Grading version, expected version | Guidance finalization capability | Completeness/version/audit | Result-preparation intent |
| `ReopenGradingSession` | Grading version, reason, expected version | Privileged Guidance authority | New correction version; prior superseded | Reconciliation notification |
| `RecordConsultationDraft` | Attempt, draft, signal refs, expected version, idempotency key | Guidance Counselor or authorized Test Administrator capability holder | Human decision draft/version/audit | None or draft notification |
| `ConfirmConsultationDecision` | Attempt, decision, rationale, signal refs, expected version | Guidance Counselor | Confirmed human decision/version/audit | Consultation notification/projection |
| `DeferConsultationDecision` | Attempt, reason, expected version | Guidance Counselor | Deferred decision/version/audit | Follow-up notification |
| `ReviseConsultationDecision` | Attempt, revised decision, reason, expected version | Authorized Guidance Counselor | New decision version; prior superseded | Release reconciliation/notification |
| `PublishConsultationAvailability` | Mode, date/time, office, capacity, expected version, idempotency key | Guidance consultation capability | Availability/publication/audit | Applicant notification |
| `ReserveConsultationSlot` | Availability, applicant, expected version, idempotency key | Applicant own-data capability | Reservation under slot lock | Confirmation notification |
| `PrepareResultDocument` | Attempt/release context, template/version, content hash, idempotency key | Guidance document capability | Create/reuse a generation operation; return `AcceptedForProcessing` while pending or a ready private artifact when synchronous generation completes | Render/conversion job or completion |
| `ReleaseResult` | Attempt, ready artifact, prerequisites, release mode, expected version | Approved Guidance release capability | Release/current-visibility/audit only after required artifact status is `ready` | Applicant notification/delivery |
| `ReverseResultRelease` | Current release, reason, expected version | Approved correction authority | Reversal/supersession/audit | Correction notification |
| `MarkNotificationRead` | Notification delivery, expected version | Recipient own-data capability | Read state | None |
| `ConfirmGuidanceInquiry` | Refusal/fallback reference, confirmation, message, idempotency key | Applicant own-data capability, conditional | Inquiry creation/idempotency | Guidance notification |
| `ProposeAssistantAction` | Actor context, typed action name, validated fields, target reference, idempotency key | Same capability/scope required by the equivalent UI command | Stored proposal with citations/context and confirmation requirement | None |
| `ConfirmAssistantAction` | Action request, confirmation, expected version, idempotency key | Same capability/scope required by the equivalent UI command | Execute the existing application command, never a model-defined mutation | Existing command effects and audit |
| `ActivateBreakGlassSession` | Reason/category, non-wildcard capability allowlist, target/purpose, duration, reviewer/alert target, confirmation, idempotency key | Super Admin/approved break-glass activator with strong authentication | Time-bound `break_glass_sessions` row and audit before exceptional access is usable | Alert/reviewer notification |
| `EndBreakGlassSession` | Session, reason, expected version, idempotency key | Active break-glass actor or approved reviewer | Revoked/ended session and audit | Review task/alert update |
| `VerifyAuditChain` | Sequence/checkpoint range, key version, purpose, idempotency key | Break-glass technical audit capability | Verification result and audit-of-audit evidence; no history mutation | Incident/checkpoint notification |
| `PlaceRetentionHold` | Subject/class/scope, reason, owner, start/end, expected version, idempotency key | Approved privacy/legal/records authority | Active `retention_holds` row and audit; eligible purge is blocked | Retention/review notification |
| `ReleaseRetentionHold` | Hold, reason, expected version, idempotency key | Approved privacy/legal/records authority with required review | Ended hold and audit; no automatic deletion in the same request | Retention/review notification |

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
| `AuditAccessQuery` | Super Admin full audit scope or approved review scope | Redacted protected-view/mutation evidence, with audit-of-audit evidence |
| `ExamSlotPlanQuery` | Test Administrator scope or Applicant own reservation scope | Reservation state, capacity, finalization/publication state |
| `AssistantContextQuery` | Actor-scoped policy/own-data/task context | Citations plus typed action affordances; no hidden fields |
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
- `AdmissionAttemptArchived`;
- `ApplicantAccessChallengeIssued`;
- `ApplicantCredentialEstablished`;
- `ExamSlotReservationOpened`;
- `ExamSlotReservationClosed`;
- `ExamSlotAssignmentsFinalized`;
- `ExamSessionPublished`;
- `ExamSessionUnpublished`;
- `ExamSessionExtended`;
- `ExamSessionCancelled`;
- `ExamSessionReopened`;
- `ExamAssignmentCreated`;
- `DirectAssessmentStarted`;
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
- `GradingReturnedForCorrection`;
- `GradingFinalized`;
- `GradingReopened`;
- `ConsultationDecisionDrafted`;
- `ConsultationDecisionConfirmed`;
- `ConsultationDecisionDeferred`;
- `ConsultationDecisionRevised`;

- `ConsultationAvailabilityPublished`;
- `ResultDocumentPrepared`;
- `ResultReleased`;
- `ResultReleaseReversed`;
- `NotificationIntentCreated`;
- `ProtectedViewAccessed`;
- `AssistantActionProposed`;
- `AssistantActionConfirmed`;
- `AssistantActionCompleted`;
- `AssistantActionRefused`;
- `OfflineOperationQueued`;
- `OfflineOperationReplayed`;
- `OfflineConflictResolved`;
- `GuidanceInquiryConfirmed` (conditional).
- `BreakGlassSessionActivated`;
- `BreakGlassSessionEnded`;
- `AuditChainVerified`;
- `RetentionHoldPlaced`;
- `RetentionHoldReleased`.

Events are not permissions and are not commands. Consumers must validate their own scope and idempotency.

## 7. Queued job contract

Every job records operation identity, attempts, timeout, provider, correlation ID, and terminal outcome.

| Job | Trigger | Retry/fallback |
|---|---|---|
| `DeliverApplicantAccessChallenge` | Challenge issued | Bounded retry; approved alternative/manual recovery if terminal |
| `DeliverStatusNotification` | Admission decision event | Idempotent per event/recipient/channel; dead-letter visibility |
| `DeliverScheduleNotification` | Session published | Retry/reconcile without changing schedule |
| `ProcessDocumentGeneration` | Preparation/release intent | Private artifact retry; emit `ResultDocumentPrepared` only after verified ready storage; preserve release state and expose terminal failure for operator reconciliation |
| `ProcessScoreImportBatch` | Staged batch | Durable row claims; resume failed rows; manual fallback |
| `ProcessApprovedOmrBatch` | Approved OMR gate | Abstain/review on uncertainty; no direct score write |
| `RunDecisionSupportEngine` | Approved support request | Proposal-only result; provider outage is visible; BGE is beta/feature-flagged and Yukee reproduction requires approved parameters |
| `SyncKnowledgeDocument` | Approved corpus change | Versioned retry; no unapproved source publication |
| `ProcessOfflineProctorOutbox` | Authenticated device outbox | Ordered replay, duplicate suppression, conflict queue; never silent last-write-wins |
| `ReconcileAssistantAction` | Assistant action timeout/unknown outcome | Query authoritative command result before retry; no duplicate mutation |
| `PurgeRetainedOmrArtifacts` | Retention status/hold scheduler | Delete/anonymize only eligible raw images; preserve derived provenance and audit |
| `ApplyRetentionPolicy` | Approved retention profile/scheduler | Evaluate lifecycle and active holds; stage or execute only eligible archive/purge actions with audit and operator reconciliation |
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

For OMR specifically, the worker returns per-item observations, visual status/flags, candidate identity signals, and confidence/provenance. It does not accept/reject an applicant, finalize a score, or silently bind an ambiguous Applicant No.

### `KnowledgeSearchPort`

Input: authorized corpus/data scope, query, filters, citation policy.  
Output: source references, excerpts, confidence/coverage metadata.  
Rules: scope is resolved before retrieval; own-data and policy corpus gates remain separate.

### `BoundedGenerationPort`

Input: approved context/citations, refusal policy, output schema.  
Output: answer proposal/citations/refusal/provider status.  
Rules: no permission decision, no placement/admission decision, no state mutation.

### `AssistantActionPort`

Input: authenticated actor context, typed action name from the allowlist, validated fields, target reference, expected version, confirmation state, and idempotency key.

Output: proposal, confirmation-required, completed authoritative result, refused result, or typed failure.

Rules: the assistant may retrieve policy documents, released own-data, and role-scoped operational context; it may propose UI-equivalent actions such as checking incomplete scores or preparing a schedule. It must execute through the same application command/policy gates as the frontend, require confirmation for mutations, and emit proposal/confirmation/result/refusal audit events. It cannot issue arbitrary routes, SQL, direct database writes, permission grants, or hidden field reads.

### `OfflineSyncPort`

Input: device enrollment, signed operation envelope, local sequence, entity/version, operation type, created-at, and idempotency key.

Output: accepted/replayed/rejected/conflict/manual-review status with authoritative server version.

Rules: offline proctoring is an explicit committed feature; replay is ordered and idempotent, conflicting attendance/submission changes are surfaced for human resolution, and an offline cache is never represented as synchronized authority.

### `ClockPort` and `IdempotencyPort`

These ports make expiry, effective deadlines, deterministic tests, and replay semantics testable without relying on ambient time or browser state.

## 9. Idempotency and concurrency contract

An idempotency key is required for:

- public/staff submission;
- challenge issue/consume requests;
- attendance/submission;
- slot reservation/finalization/assignment;
- direct assessment start;
- session assignment/reservation;
- score import batch/row;
- notification delivery;
- document generation/delivery;
- provider operations;
- reversal/retry commands where duplicate execution is dangerous.
- irreversible/public state transitions such as accept, dismiss, archive, publish, unpublish, extend, close, reopen, and finalize;
- assistant proposal/confirmation/action execution;
- offline operation replay/conflict resolution.
- break-glass activation/end and retention-hold placement/release.

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

Protected read queries use the same scope rules and emit access evidence when required by GF-08/D-023. Super Admin's full audit scope does not grant routine access to every business object; it grants the auditable audit view.

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
- after-commit effect test where a side effect exists, or an explicit no-side-effect assertion;
- degraded provider/manual fallback test when integrated.

Every adapter requires:

- schema contract fixture;
- timeout/retry test;
- malformed/terminal failure test;
- secret/redaction test;
- provenance test;
- health/readiness test;
- no-direct-authoritative-write test for inference/provider adapters.
- equivalent-UI-command test for every assistant action;
- prompt-injection/unauthorized-tool/refusal test;
- offline replay, duplicate, out-of-order, conflict, and manual-resolution test.

## 13. Open contract holds

- exact public versus internal route names and the final page-state serialization/prop envelope coordinated with GF-21/GF-22;
- final Laravel/Inertia error serialization;
- applicant credential/delivery adapter;
- queue transport promotion from database to another backend;
- document renderer and PDF/DOCX policy;
- external provider identity/credentials and rate limits;
- PostgreSQL major version and pgvector extension/index/restore contract;
- audit event schema/key/checkpoint implementation;
- conditional inquiry thread command/retention;
- OMR/inference envelope version;
- RAG citation/refusal thresholds and approved policy corpus;
- YuKee artifact/specification manifest, checksum, loader/runtime contract, and expected-output fixtures;
- operational SLOs and retry counts.

## 14. Exit criteria

GF-20 may advance only when:

- first-slice commands and queries map to GF-18 owners;
- command transaction boundaries map to GF-19 constraints;
- events/jobs have after-commit and idempotency behavior;
- adapter ports have typed input/output/failure envelopes;
- authorization occurs before protected data loading/mutation;
- GF-21 and GF-22 consume every first-slice command/query result with an explicit actor/scope, breadcrumb/route, loading/empty/error/conflict, feedback, and audit-correlation behavior;
- test/evidence obligations are assigned;
- unresolved provider and policy choices remain explicit holds.
