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
        -> Guidance/Test Administrator scheduling eligibility
        -> applicant slot reservation while the reservation window is open, or Test Administrator manual assignment
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
| pending | `AcceptAdmissionAttempt` → accepted | Registrar Administrator OFFICE, Registrar Clerk OFFICE, or explicitly authorized Registrar Support Staff OFFICE | decision window/override; completeness; no conflicting active identity/attempt | acceptance decision, workflow milestone, and audit; no new applicant credential gate | acceptance notice where approved | delivery failure does not roll back acceptance; access challenge was already issued after submission; no fabricated email |
| pending | `DismissAdmissionAttempt` → dismissed | Registrar Administrator OFFICE, Registrar Clerk OFFICE, or explicitly authorized Registrar Support Staff OFFICE | decision authority; reason/rationale policy | dismissal and reason; audit | dismissal notice if approved | delivery retry; record remains historical |
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
| none | `CreateExamSession` → draft | Authorized Test Administrator CAPSET | active cycle; valid room/date/time/capacity/mode; no conflict | session and audit | none | conflict returns typed alternative path |
| draft | `AssignAttemptToExamSession` → draft | Authorized Test Administrator CAPSET | accepted attempt; same cycle/mode; no conflicting assignment; capacity | ExamAssignment; audit | optional schedule draft event | conflict safe under concurrency |
| draft | `AssignProctorDuty` → draft | Authorized Test Administrator CAPSET | eligible active staff; no conflicting duty if policy requires | ProctorAssignment; audit | assignment notice | revoke/reassign explicitly |
| draft/published/in_progress | `RevokeProctorDuty` → same session state | Authorized Test Administrator CAPSET or approved duty owner | active duty exists; reason and effective time valid | revoke duty and audit; session access is re-evaluated immediately | reassignment/reconciliation notice | no further protected Proctor action after revocation |
| draft | `PublishExamSession` → published | Authorized Test Administrator CAPSET | non-past legal schedule; room; at least one eligible assignment; required proctor rule | publish state and audit | applicant/proctor schedule deliveries | delivery failure retryable; no unpublish rollback |
| published | `UnpublishExamSession` → draft | authorized Test Administrator CAPSET with reason | session not started; downstream notification/reconciliation permitted | unpublish event/state; audit | correction notices | preserve prior publish evidence |
| published | `StartExamSession` → in_progress | assigned Proctor or authorized Test Administrator CAPSET | active duty; approved session/application time window; non-cancelled; at least one assignment | start timestamp/state; audit | monitoring event | force-open/early/late override is a distinct reasoned command and cannot create an arbitrary date outside the approved window |
| published/in_progress | `ExtendExamSession` → same state | assigned Proctor or authorized Test Administrator CAPSET under approved limit | active session; reason/duration valid | one `effective_end_at` update/version and audit | affected roster/monitoring update | every attendance, submission, manual-close, and auto-close rule uses the same effective deadline |
| published | `CancelExamSession` → cancelled | authorized Test Administrator CAPSET | approved reason; no illegal active processing | cancellation and assignment disposition; audit | notices/reassignment work | never delete session/assignments |
| published/in_progress | `CloseExamSession` → completed/cancelled | assigned Proctor, authorized Test Administrator CAPSET, or locked scheduler source | effective deadline/grace/override rule; command not already applied | explicit outcome/reason/source; no attendance → no-show cancellation; completed outcome reconciles every assignment and opens grading exactly once; audit | affected applicant/staff notices and grading availability | incomplete submission needs reasoned override; manual/automatic callers share this command |
| completed | `ReopenExamSession` → in_progress only before grading starts, otherwise superseding session/correction path | privileged Test Administrator CAPSET with reason | D-075; no finalized/downstream release; exact impact known | preserve old close event; explicit reopen/supersession and audit | notify affected actors and cancel/reconcile stale work | never delete grading, scores, attendance, or assignments |

### 5.1 Slot reservation, finalization, and manual assignment

Exam scheduling has three deliberately different paths after an applicant is accepted:

1. **Applicant reservation path** — the authorized Test Administrator opens an `ExamSlotPlan` as `open_for_slot_reservation`; the applicant may reserve only an available date/slot; the Test Administrator closes the reservation window, resolves capacity/conflicts, finalizes assignments, and publishes the resulting schedule.
2. **Staff-assigned path** — the Test Administrator leaves reservation closed or bypassed and manually assigns accepted applicants to the Friday/Monday/Tuesday or other available sessions. This path does not enforce an applicant reservation cutoff because the staff member is the assigning authority. Publication may happen immediately or later; notification occurs after publication.
3. **Direct Assessment path** — the special D-042 assessment mode bypasses ordinary applicant reservation and ordinary scheduling. It still creates a durable assessment/grading context, binds an accepted eligible applicant, records the responsible Test Administrator, and uses the same score/finalization/release invariants.

Slot-plan states are `draft → open_for_slot_reservation → reservation_closed → finalized → published`, with `bypassed` recorded when the staff-assigned path is used. Closing reservation is not the same as publishing the schedule; publication is the notification boundary.

Applicant reservation is disabled until the admission attempt is accepted. An applicant may see that scheduling is pending, but cannot select a slot before acceptance or while the Test Administrator has not opened reservation.

### 5.2 ExamAssignment substates

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
| none | `OpenGradingSession` → open | Guidance Counselor or authorized Guidance Clerk/Support Staff with approved Test Administrator/scoring capability | completed eligible exam/direct-assessment unit; no conflicting active grading version | create version and audit | workspace available | unique conflict returns existing active session |
| open | `RecordScore` → open | Guidance Counselor or authorized Guidance Clerk/Support Staff with approved scoring capability | assigned eligible attempt; active/versioned aptitude area; valid range/source; optimistic version | create score/correction version; audit | projection update | stale conflict refreshes; no silent overwrite |
| open | `ImportReviewedScores` → open | Guidance Counselor or authorized Guidance Clerk/Support Staff with approved scoring capability | validated idempotent import; all rows mapped; partial behavior approved | atomic or explicit staged import; row outcomes/audit | import report | retry by idempotency key; invalid rows never disappear |
| open | `SubmitGradingForReview` → review | Guidance Counselor or authorized Guidance Clerk/Support Staff with approved scoring capability | staged batch/manual writes committed; row states explicit | lock routine edits as approved; review snapshot and audit | reviewer queue | return to open only through reasoned correction command |
| review | `FinalizeGradingSession` → finalized | Guidance Counselor or explicitly delegated Guidance scoring authority | exactly one valid current score for every required area/attempt; no unresolved OMR/import items | finalization and immutable score set/version; audit | counseling eligibility | missing/invalid score blocks whole finalization |
| review | `ReturnGradingForCorrection` → open | authorized Guidance Counselor or delegated Guidance reviewer with reason | approved correction need | reason/history and new expected version | assigned scorer/import owner notice | no score/history deletion |
| finalized | `ReopenGradingSession` → new open version; prior superseded | privileged Guidance Counselor or explicitly delegated Guidance scoring authority with reason | approved correction need; release/consultation impact known | preserve prior version; create correction version; audit | notify/reconcile dependent consultation/release | no direct final score overwrite |

Direct assessment uses the same completeness/finalization contract and cannot become `finalized` or “scored” with absent required evidence. It is included as a special late/same-day operational path, not treated as an untracked score shortcut.

Score imports use a durable staged batch with file hash/schema version, explicit `valid`, `invalid`, `empty`, `duplicate`, `imported`, and `failed` row states, row-atomic resumability, batch idempotency, and a documented per-row manual fallback. A preview/confirm path and any direct import endpoint must converge on the same command contract.

## 7. OMR workflow

Proposed states:

`uploaded/quarantined → validated → processing → review_required or ready_for_import → imported`  
Terminal failure states: `rejected`, `failed`, `cancelled`.

Rules:

- input is linked to template/key/version and checksum before processing;
- worker results are observations/proposals, not authoritative ScoreRecords;
- technically unsupported/malformed files may fail ingestion, but the scanner does not autonomously reject an applicant or approve a score;
- low confidence, multiple/erased/ambiguous marks, uncertain Applicant No, or subject mismatch enters human review;
- the review interface shows per-item green/accepted, red/uncertain, and unresolved identity indicators;
- an authorized scorer/Test Administrator may search for the applicant, bind the correct Applicant No, correct fields, and record the reason;
- authorized human review resolves every review item;
- only `ImportReviewedScores` enters the grading aggregate; an OMR-origin batch carries its source/provenance and still requires reviewed rows;
- retry reuses operation idempotency and does not duplicate scores;
- manual scoring bypasses unavailable/failed OMR without bypassing grading invariants.

### 7.1 Offline proctoring reconciliation

Offline proctoring is a committed capstone capability behind EV-011, not a cache-only claim.

- Before a session goes offline, the device receives only the assigned-session roster, effective window, approved form/template, and the minimum data needed for attendance/submission.
- Each local action receives a device/session/operation identifier, actor/duty context, sequence, timestamp, integrity marker, and idempotency key; local storage is encrypted and expires according to policy.
- Actions enter a durable outbox and are replayed to the same authoritative commands when connectivity returns.
- Replay is idempotent; stale assignment, duplicate submission, conflicting attendance, expired session, and changed effective deadline become explicit conflicts rather than silent overwrites.
- A Test Administrator or authorized reviewer resolves conflicts with a reason; the original offline action and resolution remain audit evidence.
- The UI exposes `offline_pending`, `syncing`, `synced`, `conflict`, and `manual_reconciliation_required` states. Online/procedural paper fallback remains available when the spike or device path is unavailable.

## 8. ConsultationDecision lifecycle

Proposed states:

- `draft`
- `confirmed`
- `deferred`
- `superseded`

| From | Command → To | Actor | Preconditions | Effects |
|---|---|---|---|---|
| none/draft | `RecordConsultationDraft` → draft | Guidance Counselor OFFICE or authorized holder of the Guidance-scoped Test Administrator CAPSET | finalized scoring; authorized attempt; available signal provenance recorded; suggested program/course and Applicant selection are separate fields | draft rationale/action and separate choice evidence; audit as policy requires |
| draft | `ConfirmConsultationDecision` → confirmed | Guidance Counselor | approve/modify/override action; required rationale complete; Applicant selection and counselor decision remain attributable | human-owned confirmed version; audit |
| draft | `DeferConsultationDecision` → deferred | Guidance Counselor | approved reason/follow-up | defer reason and next action; audit |
| confirmed/deferred | `ReviseConsultationDecision` → new draft/confirmed version; prior superseded | authorized Counselor with reason | approved correction; release impact reconciled | version history; audit; release reconciliation |

Inference/model output cannot execute any row in this table.

### 8.1 Consultation mode and reservation workflow

`ConsultationPlan` is a Guidance-owned publication aggregate separate from `ConsultationDecision` and `ResultRelease`.

Default mode is face-to-face (F2F):

- the authorized Guidance Counselor/Guidance Clerk/Test Administrator capability holder publishes consultation batches or office dates;
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
| released | `ReverseResultRelease` → unreleased/review | privileged Guidance with reason | approved reversal condition; dependent portal/document behavior defined | reversal event, current visibility update, audit; preserve prior artifact | correction notice/reconciliation | no destructive deletion |
| unreleased after correction | `ReleaseResult` → released new version | approved Guidance actor | corrected grading/consultation finalized; new release version supplied | new release/document version and audit | delivery with new idempotency key | prior release remains historical |

F2F is the default mode: printed result preparation precedes published batch consultation handling. Online release is a separate branch: the approved digital result may be released while the Guidance office keeps consultation availability open. Both branches preserve the later ConsultationReservation and ConsultationDecision history.

## 10. Applicant portal journey

Applicant can:

- authenticate through approved credential flow;
- view own current admission status and actionable next step;
- view own published schedule/assignment details; before acceptance or before reservation opens, scheduling remains disabled/pending;
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

Institutional policy RAG, own-data retrieval, and staff task assistance are separate contexts:

- **Policy context:** retrieves only approved/versioned institutional policy sources, returns citations, and links the user to the governing policy page for the authoritative document.
- **Own-data context:** retrieves only the authenticated applicant’s released status/result/consultation data, or an authorized staff member’s scoped operational data; unreleased scores and cross-applicant data remain unavailable.
- **Task context:** the assistant may propose a typed UI-equivalent request such as “open reservations for these dates,” “schedule these accepted applicants,” or “show which assigned scores are still missing.” The server resolves the actor’s capability/scope/state/window rules, presents a confirmation for mutations, executes the normal command, and audits the request/result. The model cannot call the database, skip a frontend/request gate, or turn text into an unlisted command.

The assistant may combine policy explanation with a link to the relevant page, but policy text never becomes executable instruction. A refusal may offer a bounded Guidance inquiry, but creates no thread without applicant confirmation.

## 11. Document preview and publication workflow

- Admission slips are not required for answer-sheet identity in the target flow; Applicant No/Reference binds answer-sheet receipt/import. Legacy admission-slip generation/routes remain historical cleanup items.
- Result-sheet `generated`, `downloaded/distributed`, and `physically_printed` evidence are separate; a download timestamp is not proof of physical printing.
- Result-sheet internal preview may exist only as an explicitly authorized, watermarked, audited operation.
- Applicant/external result download, print, or export requires finalized grading, approved consultation prerequisites, and the current released result version.
- Large generation/export batches are observable jobs with actor, selection, template version, status, failures, retry, and audit evidence.

## 12. Program choice, Program Head support, and admission completion

### 12.1 Ownership truth

- Registrar alone reviews/corrects and **Accepts/Dismisses** applications; Registrar acceptance is the gate to exam assignment.
- Guidance/Test Administrator owns exam, scoring, release, consultation/advice, the Guidance program pool, normal advised default, and pre-enrolling edge follow-up routing.
- Applicant action establishes the ordinary provisional choice; Guidance's normal advised default may do so first, reserves quota, and remains pending acknowledgement until portal/Guidance acknowledgement or Registrar records sheet-based confirmation while starting `enrolling`.
- PH normal-path approval is not required. PH may manage own quota, see scoped projections, open D-080 records, directly decline an acknowledged selection before `enrolling`, resolve an already-open edge follow-up during `enrolling`, request appearance, and use bounded messaging.
- Registrar alone stamps/reverts `enrolling`/`enrolled` and records signed-paper final-program truth; NEXIAM stops there (BR-026).

### 12.2 Canonical journey

```text
submit → Registrar accept (exam gate) → exam → score/finalize → release
  → consultation/advised → Guidance pool/default → applicant choice/ack
  → sheet submitted → Registrar enrolling
  → [edge only: assigned-PH terminal follow-up]
  → Registrar verifies signed paper + final program → enrolled
```

- Initial preferences remain Potential evidence; pool/follow-up/preferences consume no quota.
- Provisional choice/default reserves quota FCFS/idempotently. The standing record may still be pending applicant acknowledgement.
- Direct PH decline of an acknowledged current selection is pre-`enrolling`; it releases quota and opens reconsideration. Guidance opens edge follow-up only before `enrolling`.
- An already-open edge follow-up does not block `enrolling`; it requires assigned-PH terminal clear/decline and blocks only `enrolled`. Request appearance remains open.
- `enrolling` freezes core acceptance/exam/result/advice facts, opening new follow-up, and ordinary applicant/Guidance selection changes, while permitting existing PH resolution and Registrar paper finalization. `enrolled` freezes final truth until ordered Registrar reversal.

### 12.3 PH lists and access

- **Potential:** program in P1–P3 or Guidance pool; candidate not committed to a single different program and not in a later primary list; identified rows only after Registrar acceptance; basis + lifecycle tags, no sensitive record from preference/pool alone.
- **Upcoming:** active acknowledged selection + one remaining option + no open follow-up or enrollment stamp.
- **Follow-up / Enrolling / Enrolled:** canonical targeted follow-up and Registrar-stamp projections. Primary-list precedence is `Enrolled → Enrolling → Follow-up → Upcoming → Potential`; follow-up remains an orthogonal work queue and may overlap `Enrolling`.
- Full protected record requires result release plus an applicant-acknowledged current selection or explicit targeted follow-up for the PH program. Preference/pool membership alone remains minimal. D-080 permits converted scores/result sheet and bounded program notes; raw sheets/scans/conversion tables and unrelated notes remain denied.
- Every protected PH open is audited; applicant access history records it and a user-facing notice is deduplicated per PH/program/24h.

### 12.4 Messaging, universal QR, and consultation visualization

- PH↔Guidance internal thread is case-scoped; Guidance's ordinary work requires no PH permission. PH↔applicant support requires current selection/targeted follow-up and is not a general inbox.
- One universal digital/printed applicant QR resolves an opaque applicant-attempt locator, not a feature route. Authenticated scan opens a role-neutral action hub whose destinations come from the actor's ordinary capability, duty, office/program/cycle relationship, and case state; it never mutates automatically.
- The existing consultation selected-attempt workspace owns the applicant-specific admission-support tabs: real released-score charts/table, preferences, structured consultation evidence, independent future-analysis regions, human decision/pool/default, follow-up, and history.
- This cut installs no engine. YuKee artifact/K-Means and transparent deterministic/rule-based plugins remain separate later changes under D-093; BGE remains separately deferred. Future outputs remain independent evidence and never write authoritative state.

This journey is approved by D-ROLE-05, D-080, D-087, and D-089–D-093. Executable implementation evidence remains open under `2026-08-15-admission-model-program-head`.

## 13. System administration journey

There is no permanent System IT business role. Super Admin may, only through break-glass:

- create/deactivate named staff accounts and govern permanent role grants;
- configure non-domain technical/integration settings;
- operate knowledge synchronization and audit verification under approved content-owner separation;
- monitor health, queue, storage, delivery, and security evidence;
- invoke technical/support break-glass only under D-017, with full audit-scope visibility but no implied routine business-data authority.

Super Admin break-glass may not routinely accept applications, change scores, author counseling decisions, or release results.

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
- EV-001/EV-002 Guidance Counselor/Guidance Clerk and Registrar workflow evidence.
- EV-003 applicant recovery experience.
- EV-004/EV-005 Program Head scope/mapping.
- G-OMR/G-RAG/G-ENGINE/G-OFFLINE for committed/gated capability branches; RAG is definite, BGE remains optional beta, and offline proctoring is committed but promotion-gated.
- D-073–D-080 for public intake, canonical status, exam close/reopen, grading/import, publication, document, inquiry, and reporting semantics.
