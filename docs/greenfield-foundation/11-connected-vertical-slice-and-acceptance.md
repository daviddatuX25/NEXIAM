# Connected Vertical Slice and Acceptance Contract

**Artifact:** NEXIAM-GF-11  
**Status:** DRAFT — REVIEW REQUIRED  
**Milestone:** Connected Admission-to-Release Slice  
**Purpose:** Define the smallest end-to-end implementation that proves the greenfield architecture, institutional workflow, role isolation, manual fallback, and one bounded capstone differentiator.

## 1. Slice outcome

A seeded applicant moves from a new admission attempt to an authorized released result through Registrar, Guidance, contextual Proctor, and Applicant interfaces. Every state change is command-owned, scope-checked, transactionally safe, audited, recoverable where approved, and visible through the correct read model.

This is a connected workflow, not a collection of disconnected dashboards.

## 2. Included actors

- Applicant
- Registrar Administrator, Registrar Clerk, or authorized Registrar Support Staff within approved Registrar capability and scope
- Guidance Counselor, Guidance Clerk, or Guidance Support Staff holding the required Test Administrator capability
- Staff member with temporary Proctor assignment
- Program Head through assigned-program projections and the bounded D-080/D-087/D-089/D-092 record/quota/follow-up/message exceptions approved for the post-release expansion; never Registrar acceptance or score/release authority
- Super Admin only for reasoned, time-bounded break-glass audit/support verification, not routine admission decisions

## 3. Slice boundaries

### Included

- clean authentication and approved roles/scopes;
- one active AcademicCycle and minimal reference data;
- one durable ApplicantIdentity and one public pre-account AdmissionAttempt;
- post-submission access challenge, optional Portal Account activation, and credential setup; acceptance remains an independent Registrar decision;
- one ExamSession with one room and one assigned applicant;
- temporary Proctor assignment;
- attendance and answer submission;
- complete manual scoring baseline;
- optional OMR proposal/review path only after RES-G2;
- grading finalization;
- consultation decision;
- result release and applicant portal retrieval;
- one delivery channel through local/test mail evidence;
- critical audit events and verification for covered events;
- allowed/denied browser and policy scenarios;
- fresh migration/seed and deterministic demo reset.

### Explicitly excluded from this slice

- full generic engine registry;
- BGE/Yukee functional integration unless separately approved and spike-passed;
- arbitrary plugin/UI loading;
- offline synchronization from this first connected journey; offline proctoring remains a committed later capability gated by EV-011 and must use encrypted device/session scope, durable outbox, ordered idempotent replay, conflict detection, and human reconciliation rather than cache-only behavior;
- broad in-app messaging;
- full report/template parity;
- multi-cycle migration of legacy records;
- production/pilot clearance;
- autonomous admission or placement decisions.

## 4. Proposed route/responsibility graph

Exact URIs remain a UX/implementation decision; responsibility is fixed here.

Every node in this graph has a server-authorized page or an explicit non-UI operational representation. The page contract, top-of-page breadcrumb, scope/context bar, loading/empty/denied/error/conflict/retry state, confirmation level, toast/notification wording, audit correlation, and responsive/accessibility behavior are defined in GF-22; capability and protected-view enforcement are defined in GF-21.

```text
Applicant submission
  -> Registrar intake queue
  -> Accept / Dismiss / Correct
  -> Credential setup
  -> Applicant status portal
  -> Guidance scheduling queue
  -> ExamSession + applicant assignment
  -> Proctor duty assignment
  -> Assigned proctor roster
  -> Attendance + submission
  -> Grading workspace
  -> Manual score entry OR OMR proposal -> human review
  -> Finalize grading
  -> Counselor consultation/decision
  -> Release result
  -> Notification delivery
  -> Applicant result portal
  -> Program-scoped aggregate projection (conditional)
  -> Audit verification evidence
```

## 5. Command and event spine

| Step | Proposed command | Required event/evidence |
|---:|---|---|
| 1 | `SubmitAdmissionAttempt` | `AdmissionAttemptSubmitted` |
| 2 | `CorrectAdmissionAttempt` / `AcceptAdmissionAttempt` / `DismissAdmissionAttempt` | correction, acceptance, or dismissal audit and projection update |
| 3 | `IssueApplicantAccessChallenge` / `CompleteApplicantAccessSetup` | hashed challenge lifecycle and credential-established event |
| 4 | `CreateExamSession` | draft session event |
| 5 | `AssignAttemptToExamSession` | eligibility/capacity/conflict evidence |
| 6 | `PublishExamSession` | publish event and after-commit schedule notification |
| 7 | `AssignProctorDuty` / `RevokeProctorDuty` | contextual assignment/revocation evidence |
| 8 | `StartExamSession` | legal-time/state evidence; override reason if applicable |
| 9 | `ExtendExamSession` if approved | one effective deadline/version and reasoned audit |
| 10 | `RecordAttendance` | idempotent attendance event |
| 11 | `RecordSubmission` | idempotent submission receipt |
| 12 | `CloseExamSession` | shared manual/automatic close outcome, effective-deadline, reconciliation, and exactly-once grading-open evidence |
| 13 | `OpenGradingSession` | one-active-grading invariant |
| 14 | `RecordScore` / `ImportReviewedScores` | source/version and correction evidence |
| 15 | `SubmitGradingForReview` / `FinalizeGradingSession` | explicit review state and score-completeness evidence |
| 16 | `RecordConsultationDraft` / `ConfirmConsultationDecision` / `DeferConsultationDecision` | counselor action, rationale, signal provenance |
| 17 | `PrepareResultDocument` | private artifact/template/checksum evidence before release |
| 18 | `ReleaseResult` | release event, generated artifact, after-commit delivery |
| 19 | `ReverseResultRelease` if approved | reversal/supersession event; no history deletion |
| 20 | `MarkNotificationRead` | recipient ownership and idempotent read-state evidence |

These are the canonical connected-slice command names. Feature-gated commands remain conditional, but their owner, state transition, failure behavior, and evidence contract must still be explicit.

## 6. Slice acceptance requirements

| Requirement | Acceptance statement |
|---|---|
| REQ-VS-001 | The system can be created from an empty approved production-engine database and seeded deterministically. |
| REQ-VS-002 | Every actor authenticates through the approved credential model; Applicant identifier alone is insufficient. |
| REQ-VS-003 | Applicant sees only own records; staff and Program Head cross-scope access is denied. |
| REQ-VS-004 | One admission attempt can be submitted, corrected under guard, accepted/dismissed, and non-destructively reopened/archived under approved rules. |
| REQ-VS-005 | Session creation, applicant assignment, room/capacity/time conflicts, and publication are transactionally validated. |
| REQ-VS-006 | Proctor capability exists only through an active session assignment and disappears after revocation/closure. |
| REQ-VS-007 | Attendance and submission are idempotent, actor-attributed, and legal only during approved state/time windows. |
| REQ-VS-008 | Manual scoring forms a complete operational fallback independent of OMR/model/provider availability. |
| REQ-VS-009 | Grading cannot finalize until every required score is valid and complete. |
| REQ-VS-010 | OMR, if included, produces proposals with confidence/provenance; uncertainty requires human review before authoritative import. |
| REQ-VS-011 | Counselor decision records independent signals and approve/modify/override/defer action without autonomous state change. |
| REQ-VS-012 | Result release requires approved prerequisites; unrelease/reversal preserves release history. |
| REQ-VS-013 | Schedule/result notification is emitted once after commit; failure is observable and retryable. |
| REQ-VS-014 | Generated documents encode untrusted values safely and retain template/version/checksum evidence. |
| REQ-VS-015 | Critical events are auditable, attributable, redacted, and verifiable under the approved threat model. |
| REQ-VS-016 | Refresh/re-login preserves authoritative state; deterministic reset recreates the seeded demo. |
| REQ-VS-017 | Provider/worker outage does not prevent the manual core journey from completing. |
| REQ-VS-018 | Browser execution records no unhandled console errors, failed required network requests, or silent failures. |
| REQ-VS-019 | Public submission creates/links a durable ApplicantIdentity and AdmissionAttempt, assigns Applicant No/Reference, and issues a one-time setup/access challenge after commit; acceptance remains independent; any optional Portal Account uses a real approved delivery/recovery channel without fabricated email. |
| REQ-VS-020 | One effective exam deadline applies after extension to attendance, submission, manual close, and scheduler close; all close paths reconcile assignments and open grading once. |
| REQ-VS-021 | Manual multi-area score writes are transactional; staged imports have durable batch identity, explicit row states, resumable idempotency, and a manual fallback. |
| REQ-VS-022 | Internal result preview is distinct, watermarked, and audited; applicant/external generation or distribution is denied until finalized consultation/release prerequisites pass. |
| REQ-VS-023 | Applicant notification projection/list/read state is connected and scoped; an empty UI cannot mask stored unread notifications. |
| REQ-VS-024 | Policy-corpus RAG and own released-data retrieval use separate authorization gates; refusal creates no inquiry until the applicant explicitly confirms fallback. |
| REQ-VS-025 | Any Program Head projection uses approved metric definitions, program ownership, freshness, and privacy threshold and exposes no unapproved individual data. |
| REQ-VS-026 | Every connected page/panel exposes an explicit initial-loading, ready, empty/not-yet-available, denied, validation, server-error, stale/conflict, and degraded/retry state where applicable; no failure is rendered as an empty success state. |
| REQ-VS-027 | Staff/applicant navigation is server-authorized and includes a top-of-page breadcrumb with safe authorized ancestors, current-page semantics, scope context, mobile collapse, deep-link, and dirty-form/back behavior. |
| REQ-VS-028 | Every authoritative mutation has field/server validation, idempotent submit behavior, risk-appropriate confirmation, authoritative success/pending wording, failure/retry, conflict/review, and audit correlation; a toast is never the only error or permission signal. |
| REQ-VS-029 | Every dashboard chart/graphic has a scoped metric/source/freshness/unit contract, distinguishes zero/no-data/suppressed/stale/error, provides an accessible text/table alternative, and cannot mutate or decide domain state. |
| REQ-VS-030 | Notification center and toasts are scoped, deduplicated, severity/duration/dismissal aware, redacted, keyboard/screen-reader accessible, and never claim delivery or release beyond authoritative evidence. |
| REQ-VS-031 | Critical connected journeys work at narrow responsive widths and by keyboard, including navigation drawer, filters, dialogs, tables, charts, OMR/document preview, and degraded/recovery states. |
| REQ-VS-032 | OMR/document graphics show provenance, confidence/uncertainty, review/correction state, watermark/privacy status, and authorized alternatives; they never imply autonomous score/admission/release authority. |

### 6.1 Requirement-to-scenario/evidence crosswalk

Every slice requirement has at least one named scenario or an explicit browser/operational evidence target. A range reference alone is not a passing result; the final package must attach the discovered test, trace, or runtime artifact to the row.

| Requirement | Scenario/evidence target |
|---|---|
| REQ-VS-001 | SCN-VS-016; fresh PostgreSQL migration/seed/catalog output |
| REQ-VS-002 | SCN-VS-003/006; authentication, expiry, re-login, and revoked-duty tests |
| REQ-VS-003 | SCN-VS-001/006/014/020; cross-applicant/cross-session/browser/API denial tests |
| REQ-VS-004 | SCN-VS-003/004/013; correction, decision, archive/reopen history tests |
| REQ-VS-005 | SCN-VS-005/017; database constraint/lock/race and publication tests |
| REQ-VS-006 | SCN-VS-006; assignment/revocation/closure authorization tests |
| REQ-VS-007 | SCN-VS-007/017; replay, time-window, duplicate, and close-race tests |
| REQ-VS-008 | SCN-VS-008; no-worker/manual end-to-end journey |
| REQ-VS-009 | SCN-VS-010; completeness/invalid-score atomicity tests |
| REQ-VS-010 | SCN-VS-009/018; OMR uncertainty, review, import, and provenance evidence when promoted |
| REQ-VS-011 | SCN-VS-011; decision/provenance/confirmation tests |
| REQ-VS-012 | SCN-VS-012/013/019; prerequisite, reversal, artifact, and visibility tests |
| REQ-VS-013 | SCN-VS-012/020; after-commit delivery, retry, projection, and terminal-failure evidence |
| REQ-VS-014 | SCN-VS-019; malicious-template/value fixtures, checksum, private-storage, and print evidence |
| REQ-VS-015 | SCN-VS-015 and audit assertions in SCN-VS-003/012/013/020; tamper/checkpoint/access evidence |
| REQ-VS-016 | SCN-VS-003/016; refresh/re-login persistence and deterministic reset output |
| REQ-VS-017 | SCN-VS-008/020; provider outage/manual fallback and degraded-state browser evidence |
| REQ-VS-018 | All connected browser journeys; console/network error report with no unhandled failures |
| REQ-VS-019 | SCN-VS-003; public/staff parity, challenge one-time use, delivery failure, and acceptance-independence tests |
| REQ-VS-020 | SCN-VS-017; extension/manual-close/scheduler-close effective-deadline race evidence |
| REQ-VS-021 | SCN-VS-010/018; transactional multi-area save, staged row states, replay, and manual recovery |
| REQ-VS-022 | SCN-VS-019; internal watermark, protected preview, pending generation, and external-publication denial |
| REQ-VS-023 | SCN-VS-020; notification prop/API/ownership/read/rebuild/retry evidence |
| REQ-VS-024 | SCN-VS-021; policy/own-data gate, refusal, confirmation, idempotency, and outage evidence |
| REQ-VS-025 | SCN-VS-022; metric dictionary, freshness, threshold, scope, and export audit evidence |
| REQ-VS-026 | All connected journeys plus GF-22 §7 component/E2E state matrix |
| REQ-VS-027 | All authenticated/public journeys plus GF-22 §§3–4 breadcrumb/deep-link/dirty-form evidence |
| REQ-VS-028 | SCN-VS-003–013/017–022 as applicable plus GF-22 §§5–7 validation/confirmation/feedback tests |
| REQ-VS-029 | SCN-VS-022 plus GF-22 §8 chart data/no-data/suppression/accessibility/export evidence |
| REQ-VS-030 | SCN-VS-020 plus GF-22 §6 notification/toast dedupe, severity, redaction, and live-region tests |
| REQ-VS-031 | All critical journeys plus GF-22 §§10–13 keyboard/viewport/accessibility evidence |
| REQ-VS-032 | SCN-VS-009/019 plus GF-22 §§8.5/9.5/9.6 OMR/document provenance, watermark, privacy, and manual-alternative evidence |

## 7. Given/When/Then scenarios

### SCN-VS-001 — Submit and isolate applicant data

**Given** an active application window and Applicant A  
**When** Applicant A submits a valid admission attempt  
**Then** one cycle-scoped attempt is created with a collision-safe reference and audit evidence  
**And** Applicant B cannot view or infer Applicant A’s record.

### SCN-VS-002 — Reject duplicate identity race

**Given** two concurrent submissions with the same approved normalized identity key  
**When** both attempt creation  
**Then** the database and command contract allow only the approved result  
**And** the rejected request receives a typed duplicate-resolution response.

### SCN-VS-003 — Submit and establish applicant access

**Given** a valid public or authorized staff submission
**When** the submission commits
**Then** the ApplicantIdentity, AdmissionAttempt, Applicant No/Reference, and mandatory audit commit atomically
**And** setup/access delivery occurs only after commit
**And** the one-time setup challenge cannot be reused or recovered from storage.

### SCN-VS-004 — Deny unauthorized acceptance

**Given** a Guidance, Program Head, Applicant, or unrelated office actor without the approved capability  
**When** they attempt acceptance  
**Then** the command is denied without changing state or leaking protected details  
**And** the denial is logged where policy requires.

### SCN-VS-005 — Prevent conflicting scheduling

**Given** a room/capacity/assignment conflict  
**When** two scheduling commands race  
**Then** only a legal schedule commits  
**And** the other receives a typed conflict suitable for retry/reselection.

### SCN-VS-006 — Proctor duty is contextual

**Given** Staff A has no permanent Proctor role but is assigned to Session S  
**When** Staff A opens S’s roster  
**Then** access is allowed only for S  
**And** access to Session T is denied  
**And** revocation immediately denies further protected actions.

### SCN-VS-007 — Attendance/submission idempotency

**Given** an assigned applicant and active session  
**When** the same attendance or submission command is repeated with the same idempotency key  
**Then** one authoritative record exists  
**And** no duplicate side effect or contradictory status is created.

### SCN-VS-008 — Manual scoring completes without inference

**Given** the Python worker/model provider is unavailable  
**When** authorized Guidance staff manually enter all required scores  
**Then** grading can be finalized under the same completeness and audit rules  
**And** the applicant journey can continue to release.

### SCN-VS-009 — OMR uncertainty abstains

**Given** OMR is enabled and one mark is ambiguous or below threshold  
**When** the sheet is processed  
**Then** the system does not silently create an authoritative score for that mark  
**And** it creates a human-review item with image/source/confidence provenance.

### SCN-VS-010 — Block incomplete finalization

**Given** one required aptitude-area score is missing or invalid  
**When** finalization is requested  
**Then** the command fails atomically with the missing requirement identified  
**And** no released/scored projection is advanced.

### SCN-VS-011 — Human owns counseling decision

**Given** zero, one, or multiple engine signals  
**When** the counselor records approve/modify/override/defer  
**Then** the human action and rationale are authoritative  
**And** every signal remains supporting versioned provenance only.

### SCN-VS-012 — Release and delivery after commit

**Given** finalized scores and approved release prerequisites  
**When** an authorized counselor releases the result  
**Then** release state, artifact metadata, and mandatory audit commit atomically  
**And** delivery is queued once after commit  
**And** a delivery failure is visible/retryable without duplicate release.
**And** if document generation is asynchronous, the command returns `AcceptedForProcessing` with a durable operation ID until a verified private artifact is ready
**And** no release, delivery, or toast claims the document is ready merely because generation was requested.

### SCN-VS-013 — Non-destructive unrelease/reopen

**Given** a previously released result  
**When** an authorized actor unreleases or corrects it with an approved reason  
**Then** the prior release event/document remains historical evidence  
**And** current visibility changes through an explicit reversal/supersession event  
**And** no applicant, score, assignment, or audit history is deleted.

### SCN-VS-014 — Program scope denial

**Given** a Program Head assigned to Program P  
**When** they request Program Q or unapproved individual-level data  
**Then** access is denied  
**And** approved Program P aggregate values remain available only if D-ROLE-05 is approved.

### SCN-VS-015 — Audit tamper evidence

**Given** a trusted audit sequence/checkpoint  
**When** a covered event is modified, deleted, inserted, or reordered in the technical test fixture  
**Then** verification fails with a diagnosable mismatch  
**And** no “tamper-proof” claim is made beyond the approved threat model.

### SCN-VS-016 — Deterministic demonstration reset

**Given** the demo environment  
**When** the approved reset/migrate/seed procedure runs  
**Then** the same actors, references, workflow starting state, and fixture versions are recreated  
**And** no real applicant data or default production secret is introduced.

### SCN-VS-017 — Extension and close use one operational command

**Given** an in-progress session whose approved end time is extended  
**When** attendance/submission occurs after the original end but before the effective end and manual/scheduler close race  
**Then** every path evaluates the same effective deadline  
**And** one terminal close outcome commits with actor/system source and reason  
**And** grading opens exactly once with unresolved submissions explicitly reconciled or reason-overridden.

### SCN-VS-018 — Resume a partially invalid score import

**Given** a staged score file containing valid, invalid, empty, and duplicate rows  
**When** authorized Guidance confirms the batch and later retries corrected failures with the same batch identity  
**Then** every row retains an explicit durable status  
**And** already imported rows are not duplicated  
**And** rejected rows can be completed through the documented manual path.

### SCN-VS-019 — Separate preview from external publication

**Given** complete scores whose grading or consultation/result release is not current  
**When** an authorized internal actor previews and an applicant/external actor requests the result document  
**Then** the internal preview is watermarked and audited  
**And** external generation/distribution is denied  
**And** generated, downloaded/distributed, and physically printed evidence remain distinct.
**And** a pending generation renders as `accepted_for_processing`/`not_yet_available`, never as a public artifact or completed release.

### SCN-VS-020 — Applicant notification projection is connected

**Given** a committed schedule/release event with a database notification for Applicant A  
**When** Applicant A opens the portal notification view and marks the item read  
**Then** the unread count/list reflect the authoritative delivery record  
**And** Applicant B cannot read or mutate Applicant A’s notification  
**And** missing/stale projection data is detectable rather than displayed as an empty success state.

### SCN-VS-021 — RAG refusal requires confirmed inquiry fallback

**Given** an authorized policy question with insufficient support or provider failure  
**When** the assistant refuses and offers Guidance fallback  
**Then** no inquiry thread is created automatically  
**And** one bounded, scoped inquiry is created only after applicant confirmation if D-079 is retained  
**And** retrying the confirmed command does not duplicate the inquiry.

### SCN-VS-022 — Program Head metrics remain scoped and defined

**Given** an approved Program Head assignment and metric dictionary  
**When** the actor views their program projection or attempts another program/individual export  
**Then** quota source/version, matched definition, freshness, and aggregation threshold are visible  
**And** cross-program or unapproved individual data is denied  
**And** the protected view/export is audited.

## 8. Required evidence package

- migration/seed command output and catalog comparison;
- focused domain/policy/feature tests;
- concurrency/idempotency test output;
- browser traces/screenshots/video as approved;
- console/network error report;
- mail delivery evidence through Mailpit/test transport;
- OMR benchmark report if included;
- audit verification/tamper fixture report;
- role/capability allowed-denied matrix result;
- import batch/row replay and manual-fallback evidence;
- notification projection/ownership/read-state evidence;
- document preview/publication/physical-print evidence;
- Program Head metric dictionary/privacy evidence if included;
- limitations and unavailable-integration/degraded-mode report;
- traceability from REQ-VS and SCN-VS IDs to code/tests.
- route/page/state matrix, breadcrumb/navigation evidence, responsive screenshots, and keyboard/accessibility evidence;
- chart/graphic data/no-data/suppressed/stale/error variants with table/text alternatives;
- toast/notification severity/dedupe/pending/failure evidence and redaction review;
- confirmation, dirty-form, validation, conflict, retry, degraded-provider, and offline reconciliation evidence where applicable.

## 9. Slice stop gates

Implementation must stop before expanding horizontally when:

- actor/data scope remains unresolved;
- identity or AdmissionAttempt cardinality is unapproved;
- destructive reopen/release semantics remain;
- public intake/account activation or canonical admission states remain ambiguous;
- exam extensions and close callers do not share one effective deadline/command;
- grading import/review/publication semantics D-076–D-078 remain unresolved;
- manual scoring cannot complete end to end;
- production-engine migrations/constraints are unproven;
- browser actor state is disconnected or page-local;
- any committed page leaves breadcrumbs, charts/graphics, toasts/notifications, confirmations, validation, or recovery states to implementation inference;
- mandatory audit/authorization tests fail;
- OMR/RAG/model integration is being used to hide an incomplete core journey.

## 10. Exit condition

The slice exits only when one repeatable browser journey, its denied/recovery variants, its database/queue/audit evidence, and its traceability package pass on a clean greenfield baseline. Passing the slice authorizes horizontal expansion; it does not authorize a real-data pilot or production deployment.
