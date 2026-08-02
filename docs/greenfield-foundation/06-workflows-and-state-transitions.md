# Workflows and State Transitions

**Artifact:** NEXIAM-GF-06  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Convert fragmented legacy controller/model/service behavior into proposed actor-owned workflow and state contracts.  
**Default:** A transition not listed and approved is illegal. Backward movement requires an explicit reversal/reopen command; direct status mutation is prohibited.

## 1. State-model rules

1. Intake decision, examination, grading, consultation, and release are separate lifecycle owners.
2. The applicant-facing/current workflow stage is a projection of authoritative milestones; it is not a second independently editable state.
3. Each transition names actor/capability, data scope, preconditions, transaction effects, audit event, after-commit effects, failures, and recovery.
4. Bulk commands apply the same single-record domain command per item and return explicit per-item results; they do not bypass policy or invariants.
5. Scheduled/background transitions are locked, idempotent, and observable.
6. Reopen, cancellation, reassignment, correction, and unrelease preserve historical evidence.

## 2. End-to-end target journey

```text
Admission attempt submitted
  -> Applicant No/Reference assigned and one-time applicant access challenge issued
  -> Registrar review
     -> dismissed (terminal unless explicitly reconsidered)
     -> accepted
        -> Guidance scheduling eligibility
        -> exam assignment
        -> contextual Proctor assignment
        -> exam attendance/submission
        -> grading open
        -> scores complete and finalized
        -> Guidance result preparation/printing as required by mode
        -> F2F consultation batch or online release/consultation availability
        -> result released through the approved mode
        -> applicant retrieval and program-scoped aggregate projection
```

Optional OMR, RAG, scheduling assistance, and admission-support engines provide bounded evidence or convenience around this journey. They never replace its authoritative commands or manual fallback.

## 3. AdmissionAttempt lifecycle

### 3.1 Intake decision state

Proposed states:

- `pending`
- `needs_correction`
- `accepted`
- `dismissed`
- `archived`

A separate correction/reconsideration event history is preferred over multiplying ambiguous status values.

| From | Command → To | Proposed actor/scope | Preconditions | Atomic effects | After commit | Failure/recovery |
|---|---|---|---|---|---|---|
| none | `SubmitAdmissionAttempt` → pending | unauthenticated public applicant under bounded intake policy, or authorized Registrar OFFICE | active cycle/window; valid identity/contact/preferences; duplicate/appointment rules pass | create/link ApplicantIdentity and AdmissionAttempt; generate stable Applicant No/Reference; reserve appointment if used; append audit | acknowledgement plus after-commit one-time setup/access challenge through the approved channel | duplicate/capacity conflict returns typed correction/reselection path; challenge delivery failure remains retryable |
| pending | `RequestApplicationCorrection` → needs_correction | authorized Registrar OFFICE | missing/invalid evidence uses approved correction reason rather than dismissal | correction request/history and audit | applicant correction notice/instructions | exact documents/fields and deadline follow D-074 |
| needs_correction | `ResubmitApplicationCorrection` → pending | public/identified applicant under protected correction link or Applicant OWN, or Registrar OFFICE | only requested fields/evidence; correction link/account authorization valid | versioned correction and resubmission event; audit | Registrar queue update | expired/invalid correction access fails closed |
| pending | `CorrectAdmissionAttempt` → pending | Applicant OWN before freeze; Registrar OFFICE within capability | editable fields; no forbidden downstream dependency | versioned field correction; audit diff | optional correction notice | stale version/conflict requires refresh; no partial field mutation |
| pending | `AcceptAdmissionAttempt` → accepted | Registrar Administrator OFFICE; delegated staff only if approved | decision window/override; completeness; no conflicting active identity/attempt | acceptance decision, workflow milestone, and audit; no new applicant credential gate | acceptance notice where approved | delivery failure does not roll back acceptance; access challenge was already issued after submission; no fabricated email |
| pending | `DismissAdmissionAttempt` → dismissed | Registrar Administrator OFFICE | decision authority; reason/rationale policy | dismissal and reason; audit | dismissal notice if approved | delivery retry; record remains historical |
| accepted/dismissed | `ReconsiderAdmissionAttempt` → pending or superseded/new attempt | authorized Registrar with reason; Guidance only if explicitly approved | approved reconsideration rule; no destructive cascade | preserve prior decision/history; create reversal/version/new attempt as approved; audit | notices/reconciliation jobs | HOLD pending Registrar policy |
| pending/accepted/dismissed | `ArchiveAdmissionAttempt` → archived | authorized Registrar/System process under retention rule | no illegal active workflow or explicit cancellation plan | archive visibility; preserve dependent history; audit | index/storage cleanup only if approved | restore/unarchive requires explicit command |

### 3.2 Workflow milestone projection

Candidate milestone order:

1. `submitted`
2. `applicant_access_issued`
3. `accepted`
4. `draft_scheduled`
5. `scheduled`
6. `attended`
7. `answer_submitted`
8. `scoring_complete`
9. `grading_finalized`
10. `result_prepared_or_printed` where required by the selected mode
11. `consultation_available_or_scheduled`
12. `result_released`

Projection rules:

- milestone advances only from authoritative domain events;
- dismissal/archive/cancellation/reversal has an explicit projection rule;
- projection can be rebuilt from event/current aggregate evidence;
- a projection failure is repairable and cannot silently mutate the source aggregate;
- unrelease changes current visibility and projection without inventing an illegal backward domain transition.

## 4. Applicant credential lifecycle

| State | Event/command | Rule |
|---|---|---|
| no account/link | submission creates or links ApplicantIdentity/AdmissionAttempt and issues access challenge | Applicant No/Reference is assigned at submission; durable identity/cardinality follows D-024 |
| access_pending | setup/access challenge issued after submission | Store hash only; expiry; atomic one-time use; delivery after commit |
| access_pending | challenge completed → optional ApplicantAccount active | Set credential; rotate session; audit; account remains optional but history remains durable |
| access_pending | challenge expired/replaced | Old challenge unusable; new request throttled and audited |
| active | reset requested | Non-enumerating response; short-lived hashed challenge |
| active | credential reset | One-time atomic consumption; revoke/rotate sessions as approved |
| active | deactivated/restricted | New access denied; historical ownership preserved |

## 5. ExamSession lifecycle

Proposed states:

- `draft`
- `published`
- `in_progress`
- `completed`
- `cancelled`

| From | Command → To | Actor/scope | Preconditions | Atomic effects | After commit | Failure/recovery |
|---|---|---|---|---|---|---|
| none | `CreateExamSession` → draft | Guidance OFFICE; Registrar only if approved | active cycle; valid room/date/time/capacity/mode; no conflict | session and audit | none | conflict returns typed alternative path |
| draft | `AssignAdmissionAttempt` → draft | Guidance OFFICE | accepted attempt; same cycle/mode; no conflicting assignment; capacity | ExamAssignment; audit | optional schedule draft event | conflict safe under concurrency |
| draft | `AssignProctorDuty` → draft | Guidance Administrator/delegate | eligible active staff; no conflicting duty if policy requires | ProctorAssignment; audit | assignment notice | revoke/reassign explicitly |
| draft | `PublishExamSession` → published | Guidance OFFICE | non-past legal schedule; room; at least one eligible assignment; required proctor rule | publish state and audit | applicant/proctor schedule deliveries | delivery failure retryable; no unpublish rollback |
| published | `UnpublishExamSession` → draft | authorized Guidance with reason | session not started; downstream notification/reconciliation permitted | unpublish event/state; audit | correction notices | preserve prior publish evidence |
| published | `StartExamSession` → in_progress | assigned Proctor or authorized Guidance | active duty; approved time window; non-cancelled; at least one assignment | start timestamp/state; audit | monitoring event | privileged early/late override is distinct reasoned command |
| published/in_progress | `ExtendExamSession` → same state | assigned Proctor or authorized Guidance under approved limit | active session; reason/duration valid | one `effective_end_at` update/version and audit | affected roster/monitoring update | every attendance, submission, manual-close, and auto-close rule uses the same effective deadline |
| published | `CancelExamSession` → cancelled | authorized Guidance | approved reason; no illegal active processing | cancellation and assignment disposition; audit | notices/reassignment work | never delete session/assignments |
| published/in_progress | `CloseExamSession` → completed/cancelled | assigned Proctor, authorized Guidance, or locked scheduler source | effective deadline/override rule; command not already applied | explicit outcome/reason/source; no attendance → no-show cancellation; completed outcome reconciles every assignment and opens grading exactly once; audit | affected applicant/staff notices and grading availability | incomplete submission needs reasoned override; manual/automatic callers share this command |
| completed | `ReopenExamSession` → in_progress only before grading starts, otherwise superseding session/correction path | privileged Guidance with reason | D-075; no finalized/downstream release; exact impact known | preserve old close event; explicit reopen/supersession and audit | notify affected actors and cancel/reconcile stale work | never delete grading, scores, attendance, or assignments |

### 5.1 ExamAssignment substates

Attendance: `pending`, `present`, `absent`, optionally `excused` only if approved.  
Submission: `pending`, `received`, `not_submitted`, `reconciled` only if approved.

Rules:

- only assigned attempts may be mutated;
- normal Proctor access is limited to active assigned session;
- attendance and submission are separate idempotent commands;
- correction after session completion requires elevated capability, reason, audit, and downstream reconciliation;
- one `effective_end_at` (including extensions) controls all attendance, submission, close, and scheduler paths;
- absence does not silently create a zero score unless the approved scoring policy says so;
- paper/offline reconciliation, if retained, is explicit provenance—not a blind overwrite.

## 6. GradingSession lifecycle

Proposed states:

- `open`
- `review`
- `finalized`
- `superseded` (historical version)

`in_progress` is derived from score/import activity rather than a competing authoritative state. `review` is explicit because unresolved OMR/import/manual completeness must be visible before finalization.

| From | Command → To | Actor/scope | Preconditions | Atomic effects | After commit | Failure/recovery |
|---|---|---|---|---|---|---|
| none | `OpenGradingSession` → open | Guidance CAPSET/OFFICE | completed eligible exam/direct-assessment unit; no conflicting active grading version | create version and audit | workspace available | unique conflict returns existing active session |
| open | `RecordScore` → open | Guidance CAPSET/OFFICE | assigned eligible attempt; active/versioned aptitude area; valid range/source; optimistic version | create score/correction version; audit | projection update | stale conflict refreshes; no silent overwrite |
| open | `ImportReviewedScores` → open | Guidance CAPSET/OFFICE | validated idempotent import; all rows mapped; partial behavior approved | atomic or explicit staged import; row outcomes/audit | import report | retry by idempotency key; invalid rows never disappear |
| open | `SubmitGradingForReview` → review | Guidance CAPSET/OFFICE | staged batch/manual writes committed; row states explicit | lock routine edits as approved; review snapshot and audit | reviewer queue | return to open only through reasoned correction command |
| review | `FinalizeGradingSession` → finalized | Guidance CAPSET/OFFICE | exactly one valid current score for every required area/attempt; no unresolved OMR/import items | finalization and immutable score set/version; audit | counseling eligibility | missing/invalid score blocks whole finalization |
| review | `ReturnGradingForCorrection` → open | authorized Guidance reviewer with reason | approved correction need | reason/history and new expected version | assigned scorer/import owner notice | no score/history deletion |
| finalized | `ReopenGradingSession` → new open version; prior superseded | privileged Guidance with reason | approved correction need; release/consultation impact known | preserve prior version; create correction version; audit | notify/reconcile dependent consultation/release | no direct final score overwrite |

Direct assessment, if retained, uses the same completeness/finalization contract and cannot become `finalized` or “scored” with absent required evidence.

Score imports use a durable staged batch with file hash/schema version, explicit `valid`, `invalid`, `empty`, `duplicate`, `imported`, and `failed` row states, row-atomic resumability, batch idempotency, and a documented per-row manual fallback. A preview/confirm path and any direct import endpoint must converge on the same command contract.

## 7. OMR workflow

Proposed states:

`uploaded/quarantined → validated → processing → review_required or ready_for_import → imported`  
Terminal failure states: `rejected`, `failed`, `cancelled`.

Rules:

- input is linked to template/key/version and checksum before processing;
- worker results are observations/proposals, not authoritative ScoreRecords;
- wrong template, malformed input, low confidence, multiple/erased/ambiguous marks, or subject mismatch enters review/rejection;
- authorized human resolves review items;
- only `ImportReviewedOmrScores` enters the grading aggregate;
- retry reuses operation idempotency and does not duplicate scores;
- manual scoring bypasses unavailable/failed OMR without bypassing grading invariants.

## 8. ConsultationDecision lifecycle

Proposed states:

- `draft`
- `confirmed`
- `deferred`
- `superseded`

| From | Command → To | Actor | Preconditions | Effects |
|---|---|---|---|---|
| none/draft | `RecordConsultationDraft` → draft | Guidance Counselor OFFICE or authorized Guidance Test Administrator CAPSET | finalized scoring; authorized attempt; available signal provenance recorded; suggested program/course and Applicant selection are separate fields | draft rationale/action and separate choice evidence; audit as policy requires |
| draft | `ConfirmConsultationDecision` → confirmed | Guidance Counselor | approve/modify/override action; required rationale complete; Applicant selection and counselor decision remain attributable | human-owned confirmed version; audit |
| draft | `DeferConsultationDecision` → deferred | Guidance Counselor | approved reason/follow-up | defer reason and next action; audit |
| confirmed/deferred | `ReviseConsultationDecision` → new draft/confirmed version; prior superseded | authorized Counselor with reason | approved correction; release impact reconciled | version history; audit; release reconciliation |

Inference/model output cannot execute any row in this table.

### 8.1 Consultation mode and reservation workflow

`ConsultationPlan` is a Guidance-owned publication aggregate separate from `ConsultationDecision` and `ResultRelease`.

Default mode is face-to-face (F2F):

- Guidance/Test Administrator publishes consultation batches or office dates;
- applicants are assigned to a batch by an authorized Test Administrator;
- printed result sheets prepared by Guidance are used during the consultation;
- the schedule is published/announced and each assignment is auditable;
- consultation notes, suggested programs/courses, applicant-selected choices, and final counselor decision are stored separately.

Online mode:

- Guidance publishes an office-owned availability window or schedule;
- the applicant may reserve an available slot on a first-come, first-served basis when enabled;
- the office is not required to punch every applicant into a manual batch schedule;
- approved result artifacts may be released online while consultation remains open;
- later consultation reservations and decisions remain linked to the same AdmissionAttempt and are not overwritten by the release.

Both modes require:

- a versioned plan and availability state;
- capacity/concurrency protection;
- cancellation/rebooking history;
- actor/source (`staff_assigned` or `applicant_reserved`);
- audit events for publish, announce, assign, reserve, cancel, and complete;
- immutable separation between Test Administrator suggestions and the Applicant’s selected program/course.

## 9. ResultRelease lifecycle

Model release as versioned events with current visibility rather than forcing a backward pipeline transition.

| Current projection | Command | Actor/scope | Preconditions | Atomic effects | After commit | Recovery |
|---|---|---|---|---|---|---|
| unreleased | `ReleaseResult` → released | approved Guidance actor | finalized scores; result artifact prepared/printed where required; F2F consultation prerequisites or online availability/release prerequisites; authorized recipient/data | ResultRelease version, current visibility, artifact metadata, mode, and audit | generate/deliver if generation is safely after commit or prevalidated per ADR | delivery failure retries; release remains explicit; online consultation may remain open |
| released | `UnreleaseResult` → unreleased/review | privileged Guidance with reason | approved reversal condition; dependent portal/document behavior defined | reversal event, current visibility update, audit; preserve prior artifact | correction notice/reconciliation | no destructive deletion |
| unreleased after correction | `ReleaseCorrectedResult` → released new version | approved Guidance actor | corrected grading/consultation finalized | new release/document version and audit | delivery with new idempotency key | prior release remains historical |

F2F is the default mode: printed result preparation precedes published batch consultation handling. Online release is a separate branch: the approved digital result may be released while the Guidance office keeps consultation availability open. Both branches preserve the later ConsultationReservation and ConsultationDecision history.

## 10. Applicant portal journey

Applicant can:

- authenticate through approved credential flow;
- view own current admission status and actionable next step;
- view own published schedule/assignment details;
- view or reserve approved consultation availability when enabled;
- view own released result/current release version;
- submit approved institutional/personal-data RAG inquiry if D-008/D-051 pass;
- use approved recovery and staff fallback.
- view and mark own normalized notifications through an approved portal notification projection/page.

Applicant cannot:

- enumerate other identities/attempts;
- view unreleased scores/consultation drafts;
- alter exam/scoring/release state;
- use the assistant to bypass record authorization or obtain placement advice.

Institutional policy RAG access and own-result context are separate gates: policy retrieval may be available before release if D-079 permits it, while own scores, consultation, or result content remains hidden until its authoritative release rule allows access. A refusal may offer a bounded Guidance inquiry, but creates no thread without applicant confirmation.

## 11. Document preview and publication workflow

- Admission slips are not required for answer-sheet identity in the target flow; Applicant No/Reference binds answer-sheet receipt/import. Legacy admission-slip generation/routes remain historical cleanup items.
- Result-sheet `generated`, `downloaded/distributed`, and `physically_printed` evidence are separate; a download timestamp is not proof of physical printing.
- Result-sheet internal preview may exist only as an explicitly authorized, watermarked, audited operation.
- Applicant/external result download, print, or export requires finalized grading, approved consultation prerequisites, and the current released result version.
- Large generation/export batches are observable jobs with actor, selection, template version, status, failures, retry, and audit evidence.

## 12. Program Head journey

Proposed first-slice behavior:

- authenticate as assigned Program Head;
- select/receive only assigned program projection;
- view approved aggregate applicant/release/recommendation counts;
- view authorized consultation history, program notes, model/inference provenance, Test Administrator suggestions, and Applicant-selected program/course values within program scope;
- see freshness/source/definition for each metric;
- receive no raw scores or counseling notes by default;
- cannot modify quotas, admission decisions, model outputs, or releases unless a later approved capability row says otherwise.

This journey remains evidence-gated by EV-004/EV-005/EV-017 and D-018/D-080.

## 13. System administration journey

System IT may:

- create/deactivate named staff accounts and govern permanent role grants;
- configure non-domain technical/integration settings;
- operate knowledge synchronization and audit verification under approved content-owner separation;
- monitor health, queue, storage, delivery, and security evidence;
- invoke break-glass only under D-017.

System IT may not routinely accept applications, change scores, author counseling decisions, or release results.

## 14. Transition test obligations

For every approved transition:

- allowed actor/scope;
- denied actor and cross-scope actor;
- correct source state and wrong source state;
- valid and invalid precondition;
- stale/concurrent command;
- repeated idempotency key;
- mandatory audit success/failure;
- after-commit side-effect success/failure/retry;
- explicit reversal/reopen behavior;
- projection rebuild/repair where applicable.

## 15. Blocking decisions/evidence

- D-024–D-033 identity/cardinality/reopen/version rules.
- D-036–D-045 operational owners, overrides, auto-close, direct assessment, corrections, release modes.
- EV-001/EV-002 Guidance/Registrar workflow evidence.
- EV-003 applicant recovery experience.
- EV-004/EV-005 Program Head scope/mapping.
- G-OMR/G-RAG/G-ENGINE for optional workflow branches.
- D-073–D-080 for public intake, canonical status, exam close/reopen, grading/import, publication, document, inquiry, and reporting semantics.
