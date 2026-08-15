# Failure, Recovery, and Integration Contracts

**Artifact:** NEXIAM-GF-07  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Make failure behavior, idempotency, retry, compensation, reconciliation, and degraded operation part of the product contract rather than post-implementation patches.

## 1. Failure doctrine

1. Domain validation/conflict failures are typed user-correctable outcomes, not generic server errors.
2. Mandatory authorization, invariant, state, and audit failures prevent the command from committing.
3. External effects do not occur inside the authoritative transaction unless an approved adapter/ADR proves that boundary safe.
4. Every retryable operation has an idempotency key and visible state.
5. Retrying never creates duplicate authoritative records or side effects.
6. Optional-provider failure degrades to a manual/core path; it does not silently invent success.
7. Recovery preserves history and records actor/reason.
8. Reconciliation is an explicit operator workflow with evidence, not a direct database edit.

## 2. Canonical command result

Every application command should return one of:

- `Succeeded` with command/event IDs and resulting aggregate version;
- `ValidationFailed` with field/rule errors;
- `Unauthorized` without protected-data leakage;
- `IllegalTransition` with current state and legal next actions appropriate to caller;
- `Conflict` for stale version, capacity, uniqueness, or concurrent operation;
- `DependencyUnavailable` only when the dependency is required and no fallback exists;
- `AcceptedForProcessing` with observable operation ID for asynchronous work;
- `AlreadyApplied` for a repeated idempotency key;
- `Failed` with safe correlation/reference and operator visibility.

Raw provider/database exceptions are not user contracts.

## 3. Idempotency catalog

| Operation | Idempotency scope/key candidate | Duplicate behavior |
|---|---|---|
| Submit admission attempt | actor/client submission ID + cycle + normalized identity policy | return existing result or typed duplicate path; never create a second accidental attempt |
| Accept/dismiss/reopen/archive | command ID + attempt + expected version | return AlreadyApplied or conflict; no repeated challenge/history destruction |
| Book appointment | command ID + slot + attempt | one reservation only; capacity remains correct |
| Assign attempt to session | command ID + attempt + session/mode/version | one active legal assignment |
| Assign/revoke Proctor | command ID + session + staff + assignment version | one assignment/revocation event |
| Attendance/submission | client command ID + ExamAssignment + action type | one authoritative status/event |
| Score entry/import | command/import ID + grading version + attempt + aptitude area | one score version per approved operation |
| Score import batch | source checksum + schema/mapping version + grading target + batch ID | resume explicit row states; never silently replay imported rows |
| OMR processing | upload checksum + template/key/version + operation ID | reuse/report existing operation; no duplicate score import |
| Grading finalization | command ID + grading version | finalize once; repeated request returns final state |
| Consultation confirmation | command ID + consultation version | one decision version |
| Release/unrelease | command ID + attempt + release version | one release/reversal event and delivery trigger |
| Notification | domain event ID + recipient + channel + template version | one delivery record; retry same record |
| Document generation | owner/version + template version + content hash | reuse verified artifact or generate one version |
| Knowledge sync | source/version/content hash | no duplicate active chunks/document version |
| RAG request | request ID for tracing, not indefinite semantic deduplication | repeated transport request can return stored bounded result if policy permits |
| Assistant exchange | applicant + client request ID + conversation/version | one user message/operation and at most one accepted assistant response; provider failure remains reconcilable |
| Report/document batch | actor + selection hash + template/report version + command ID | one observable generation job; retry failed artifacts without duplicate publication evidence |
| Audit checkpoint | checkpoint range/last sequence + key version | one signed/persisted checkpoint per range/version |

Exact key storage and expiry are architecture/schema decisions; the behavioral contract is mandatory.

## 4. Transaction and side-effect boundary

### Within one authoritative transaction

- load/lock aggregate and required constraint records;
- authorize actor/resource/scope;
- validate expected version and transition preconditions;
- write state/version/relationship changes;
- append mandatory canonical audit event;
- record domain event/outbox or otherwise guarantee after-commit dispatch;
- commit or roll back everything.

### After commit

- mail/SMS/provider delivery;
- heavy document generation where the approved release contract permits asynchronous generation;
- vector sync and RAG/model calls;
- OMR processing;
- projection rebuild that is not required for immediate invariant safety;
- external audit checkpoint;
- analytics refresh.

A controller must not return a success that implies an after-commit effect completed unless delivery/generation status actually proves it.

## 5. Failure and recovery matrix

| Failure ID | Situation | Required immediate behavior | Recovery/operator path | Forbidden behavior |
|---|---|---|---|---|
| FAIL-001 | Duplicate/concurrent admission submission | DB constraint/lock selects legal result; return typed duplicate/conflict | Link/review/merge/new-attempt path per D-027 | read-then-insert race or second accidental identity |
| FAIL-002 | Reference collision | Roll back and retry bounded safe generator/sequence | Alert if exhausted | `COUNT + 1` or exposing DB error |
| FAIL-003 | Appointment capacity race | One reservation commits; other receives capacity conflict | choose another slot/waitlist if approved | negative/drifting count or overbooking |
| FAIL-004 | Submission commits but applicant setup/access delivery fails | ApplicantIdentity, AdmissionAttempt, Applicant No/Reference, and submission state remain committed; delivery marked failed/retrying | authorized resend/retry or bounded staff-assisted recovery; applicant acceptance remains independent | roll back submission through delivery failure or issue a second reusable token |
| FAIL-005 | Bulk intake row invalid | Apply approved atomic/staged semantics; return each row outcome | corrected re-import by same batch/idempotency contract | bypass validation/policy or silently skip rows |
| FAIL-006 | Session room/capacity conflict | Reject conflicting command atomically | refresh/reselect room/time/assignment | last-write-wins schedule corruption |
| FAIL-007 | Proctor unavailable/revoked | Deny further protected session actions immediately | authorized reassignment with history/notification | permanent role leakage or deleting assignment evidence |
| FAIL-008 | Start outside legal time | Deny normal Proctor command | privileged reasoned override only if D-039 GO | broad unreasoned override |
| FAIL-009 | Duplicate attendance/submission | Return AlreadyApplied/current status | explicit reasoned correction where approved | duplicate side effects or contradictory statuses |
| FAIL-010 | Auto-close scheduler repeats/crashes | Lock and apply once; leave observable operation | retry same operation; manual reconciliation for unresolved assignments | two terminal transitions or swallowed scheduler error |
| FAIL-010A | Extended exam uses inconsistent cutoffs or manual/automatic close diverges | Reject direct path; use one effective deadline and shared CloseExamSession command | reasoned override or retry same close operation; grading opens once | attendance accepted by one path but denied by another, or completed session without grading/reconciliation |
| FAIL-011 | Session cancelled after assignments/notices | Preserve session/assignments; mark disposition and notify/reassign | authorized reassignment workflow | cascade delete or stale applicant schedule with no correction |
| FAIL-012 | Score import partially invalid | Staged validation or approved atomic batch; row-level report | correct and retry same batch/version | silently import valid subset if contract says atomic |
| FAIL-013 | Concurrent score correction | Expected version conflict; no overwrite | refresh/reapply with new reasoned command | last-write-wins loss |
| FAIL-014 | Finalization incomplete | Reject whole finalization with missing/invalid requirements | enter/correct scores; resolve OMR reviews | advance workflow/projection with partial/zero scores |
| FAIL-015 | OMR malformed/wrong template/low confidence | Reject or create review item; no authoritative score | human review/manual scoring | guessing or marking processing successful |
| FAIL-016 | Inference worker timeout/outage | Mark operation unavailable/failed/retryable; preserve manual path | bounded retry/circuit recovery; manual scoring/counseling | block core journey or invent model output |
| FAIL-017 | Consultation needs revision after release | Block silent edit; show dependent release impact | explicit correction/reopen/unrelease/new-release workflow | overwrite historical rationale/result |
| FAIL-018 | Release artifact generation fails | Do not claim artifact delivered; release behavior follows approved sync/async ADR | retry generation; authorized rollback/unrelease only through command | partial invisible state or deleting prior artifacts |
| FAIL-019 | Release delivery fails | Release remains explicit; delivery terminal/retry status visible | retry/resend with same event key; support fallback | duplicate release or duplicate recipient messages |
| FAIL-020 | RAG/vector/LLM unavailable | Bounded unavailable/refusal and staff/static fallback | retry/circuit recovery; corpus health remediation | uncited fabricated answer or exposing provider error/secrets |
| FAIL-021 | Knowledge source stale/sync fails | Retain last approved valid version with stale/status warning or disable source by policy | owner review/resync/version rollback | silently mixing failed partial corpus |
| FAIL-021A | Local knowledge update/delete succeeds but remote index operation fails | Mark version divergence explicitly and exclude stale remote content where possible | durable reconciliation operation retries delete/upsert and verifies final state | swallowing remote delete failure while reporting corpus consistent |
| FAIL-022 | Mandatory audit append fails | Roll back critical command | security/operator remediation, then retry command | commit state with missing required audit evidence |
| FAIL-023 | External audit checkpoint fails | Preserve committed chain; raise security health incident | retry/checkpoint reconciliation and verification | claim full checkpoint assurance while unhealthy |
| FAIL-024 | Document/template contains malicious values | Encode/neutralize or reject typed placeholder | correct data/template; security review | raw HTML/formula/template execution |
| FAIL-025 | Backup missing/corrupt or restore fails | Pilot/production readiness NO-GO | repair backup chain and complete restore rehearsal | proceed based on backup configuration alone |
| FAIL-026 | Offline conflict if feature retained | Quarantine conflicting change; no blind overwrite | authorized manual resolution with both versions/provenance | timestamp last-write-wins or duplicate attendance |
| FAIL-027 | Projection/dashboard stale/fails | Source aggregates remain authoritative; label freshness and repair projection | replay/rebuild from source events/data | direct projection edit or workflow mutation from stale projection |
| FAIL-028 | Break-glass misuse/compromise | revoke session/grant; alert and preserve evidence | incident review, credential/key rotation, affected-data review | erase use history or normalize routine use |
| FAIL-029 | Provider call fails after applicant chat message is persisted | Keep one failed exchange/operation state tied to client request ID; do not duplicate the user message on retry | retry/reconcile or let applicant abandon; bounded staff fallback | orphan message with no status or duplicate history after retry |
| FAIL-030 | Applicant notification projection/page is absent or stale | Do not claim notification delivered/read in portal; delivery record remains authoritative | rebuild projection, expose dedicated list/mark-read path, reconcile unread count | empty UI caused by missing shared props being treated as no notifications |
| FAIL-031 | Report/export contains formula-like values or is generated without protected-access audit | Neutralize cells and fail/audit the protected operation as appropriate | correct encoder/access evidence and regenerate under same job | spreadsheet formula execution or untracked sensitive export |
| FAIL-032 | Pre-release result preview is requested for external distribution | Deny external publication; allow only approved watermarked internal preview | finalize/consult/release, then generate current version | exposing recommendation/comments from an unfinalized or unreleased record |
| FAIL-033 | RAG refuses/has no support and inquiry fallback is offered | Return refusal with staff contact and explicit confirmation option | create one bounded Guidance inquiry only after applicant confirmation and idempotent routing | silently auto-creating threads or claiming live messaging exists |

| FAIL-034 | Program selection/transfer races for remaining quota | Lock capacity and selections in deterministic order; exactly one legal transaction commits | refresh choices or Guidance extends pool; retry same idempotency key only | overbook, drifting counter, or releasing source before destination succeeds |
| FAIL-035 | Assisted default is not applicant-acknowledged | Preserve reserved pending-ack state and report acknowledgement missing | applicant acknowledges or changes selection through authorized path | stamp enrolling or silently treat counselor choice as applicant choice |
| FAIL-036 | PH decline/clear uses stale state or occurs after enrolling | Reject by relationship/version/pre-enrolling guard with no state/audit-success/notice | refresh; Registrar correction path if an enrollment stamp itself is wrong | release quota, reopen reconsideration, or overwrite terminal history |
| FAIL-037 | PH protected-record relationship/release gate is absent or expires | Deny without serializing sensitive props or revealing hidden record existence | satisfy both conditions: release result and establish applicant-acknowledged current selection or Guidance-routed targeted follow-up; then retry ordinary route | preference/pool-only score access, raw scan/table exposure, or client-side-only hiding |
| FAIL-038 | QR signature/purpose/expiry is invalid or actor lacks scope | Return invalid/expired/denied case-open state with no record data or mutation | refresh digital QR, use in-window printed code, or perform ordinary authorized search | trust payload role/capability, auto-mark state, or leak case identity |
| FAIL-039 | Enrollment start/final prerequisite, stamp order, paper evidence, or expected version is invalid | Reject entire command; preserve append-only stamp and provisional/final-program history | satisfy the specific start-vs-final missing facts or use reasoned Registrar reversal/correction | block sheet-received enrolling on an edge follow-up, finalize before terminal PH resolution/paper proof, overwrite stamps, or expose notes |
| FAIL-040 | Admission-support thread participant relationship ends | Close/read-only the thread and deny new messages; retain history/audit | open a new authorized subject if a new current relationship exists | preference-only outreach, general inbox fallback, or deleting messages |
| FAIL-041 | Direct PH decline/new follow-up races `enrolling`, or terminal PH resolution races `enrolled` | Lock attempt/selection/follow-up/stamp/commitments in deterministic order and re-evaluate: direct-decline/start has one winner; existing PH resolution may serialize during enrolling; enrolled commits only after terminal resolution | loser refreshes authoritative state; Registrar corrects only an erroneous committed stamp | lose/double reservation, bypass terminal PH action, or overwrite paper final truth |
| FAIL-042 | Prerequisite-invalidating mutation is attempted while `enrolling`/`enrolled` is current | During enrolling reject core reversal/new follow-up/ordinary selection changes but permit existing PH resolution and Registrar paper finalization; after enrolled require ordered reversal | Registrar reasonedly reverses stamps in order, then authoritative owner retries | auto-reverse, unrelease/unadvise/dismiss beneath stamp, open new follow-up, or block allowed edge resolution/finalization |

## 6. Retry contract

Each asynchronous adapter/job declares:

- retryable versus terminal failure taxonomy;
- maximum attempts and total deadline;
- backoff/jitter strategy;
- idempotency key;
- locked/concurrent-processing behavior;
- payload/schema/version compatibility;
- secret/personal-data redaction;
- terminal failure owner and alert;
- safe manual retry/cancel/reconcile command;
- health/readiness impact;
- retention of attempts/results.

Numeric attempt/deadline values must be set per operation after the controlled environment and provider limits are known. Infinite retry and immediate tight loops are prohibited.

## 7. Notification delivery lifecycle

Proposed states:

`pending → processing → delivered`  
Failure path: `pending/processing → retrying → failed_terminal`  
Administrative path: `failed_terminal → retrying` through an authorized reasoned command.

Rules:

- source is a committed domain event;
- recipient/channel/template version is captured;
- repeated domain event/recipient/channel does not create a second delivery record;
- provider message/reference and redacted response status are stored where permitted;
- user-facing status distinguishes business completion from delivery completion;
- resending a corrected result uses a new release/domain event version, not mutation of the old delivery.

## 8. External adapter contracts

### Mail adapter

Inputs:

- event and delivery IDs;
- recipient address from approved source;
- template ID/version and typed payload;
- locale/channel metadata where approved.

Outputs:

- accepted/delivered/failed status as provider semantics allow;
- provider reference;
- retryability and redacted diagnostic.

Local/test uses Mailpit. Production provider selection is configuration/deployment authority, not embedded domain logic.

### Python inference adapter

Inputs/outputs follow GF-09’s versioned envelope. Additional rules:

- private authenticated internal network;
- bounded request/input reference;
- no direct application database access;
- no authoritative score/decision writes;
- explicit unsupported version, invalid input, unavailable, timeout, and processing failure;
- engine/model/runtime provenance in every successful result.

### Knowledge/vector adapter

- versioned source/chunk IDs and content hashes;
- audience/data classification;
- idempotent upsert/delete/supersession;
- sync checkpoint and partial-failure visibility;
- query filter enforced from authorized application scope;
- provider result treated as untrusted input to response generation.

### LLM adapter

- fixed system policy and bounded retrieved context;
- no secrets/full unrelated applicant data;
- timeout/token/cost limit;
- typed response envelope with citations/refusal metadata;
- untrusted output encoding before UI;
- outage and low-support fallback.

### File/object storage adapter

- private names/paths controlled by application;
- checksums, content type, size, owner/version/lifecycle class;
- authorized retrieval/signed access where approved;
- atomic publish/move from quarantine;
- backup/restore and destruction hooks;
- no arbitrary URL fetch or path traversal.

### Audit checkpoint adapter

- range/last sequence, root/hash, key/version, timestamp, environment;
- authenticated append/immutability destination;
- idempotent checkpoint write;
- independent verification/read-back;
- observable mismatch/unavailability.

## 9. Import contract

Every import has explicit phases:

1. receive/quarantine and checksum;
2. validate file/media/schema/limits;
3. parse into staged typed rows;
4. authorize each target scope;
5. validate all domain references/invariants;
6. display/record preview and errors where approved;
7. commit through authoritative commands under atomic or documented partial policy;
8. produce immutable batch summary;
9. dispatch after-commit effects;
10. support idempotent retry and audited correction.

Importer code never writes around policies/domain services merely for speed.

### Future unified/dynamic import direction

The detail-stage architecture must evaluate whether one configurable import workflow can create and link related records—such as identity, admission attempt, examination assignment, and reviewed score data—without preserving today’s application-import and score-import split as a permanent boundary. Any such capability must remain staged, typed, scope-authorized, idempotent, auditable, and explicit about transaction/partial-failure semantics; it must converge on the same owning aggregate commands rather than performing unrestricted direct table writes.

## 10. Reconciliation work queues

The platform needs operator-visible queues for:

- failed/terminal notifications;
- assistant exchanges left failed after provider calls;
- unresolved OMR observations;
- failed/timeout inference operations;
- stale/failed knowledge sync;
- cancelled/reassigned exam participants;
- attendance/submission corrections;
- score import errors/corrections;
- consultation/release reversal impact;
- audit verification/checkpoint failures;
- projection rebuild failures;
- optional offline conflicts.
- report/document generation failures and protected-export audit gaps;
- local/remote knowledge-index divergence;
- optional bounded inquiry routing/SLA failures.

Each item shows owner, scope, correlation/command ID, safe redacted diagnosis, age, legal actions, and history. Direct database editing is not a normal recovery path.

## 11. Cancellation and timeout semantics

- HTTP/request timeout does not prove the command failed; clients use command/idempotency ID to retrieve outcome.
- Cancelling queued heavy work is best effort and cannot undo an already committed authoritative transition without a separate reversal command.
- Session auto-close grace period, credential challenge expiry, provider deadlines, and operation cancellation are explicitly configured and audited.
- Clock/time-zone authority is pinned; persisted timestamps use one canonical standard and display uses approved campus/user zone.
- Jobs must detect stale aggregate/input versions before applying results.

## 12. Operations evidence

Before connected-slice PASS:

- duplicate/replay tests for every slice side effect;
- concurrent submission/booking/scheduling/score/audit fixtures;
- after-commit notification failure/retry proof;
- worker/provider unavailable manual-path E2E;
- scheduler repeat/crash/lock proof;
- document/import malicious fixtures;
- projection rebuild proof for critical current-stage/dashboard data;
- operator can identify and safely retry/reconcile terminal items.

Before pilot/production:

- alert ownership and response paths;
- queue/worker/scheduler/provider health dashboards;
- backup and restore rehearsal;
- RPO/RTO and incident runbooks;
- storage/provider limits and cost/throughput evidence;
- real data/privacy/provider approvals;
- audit checkpoint verification operations.

## 13. Decisions/evidence

- D-027/D-029/D-030/D-032 identity, reopen, reassignment, reversal behavior.
- D-037–D-045 operational override/auto-close/correction/release behavior.
- D-046/D-047 OMR input and human authority.
- D-051–D-054 RAG/audit/offline/engine boundaries.
- D-060–D-067 queue, worker, storage, deployment, quality, backup, degradation.
- D-073–D-080 public intake, status, close/reopen, grading/import, publication, document, inquiry, and report semantics.
- EV-001/EV-002/EV-008/EV-011/EV-013 for institutional and operational closure.
