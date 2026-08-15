# Design: Admission Support, Program Choice, and Program Head Interface

**OpenSpec:** `2026-08-15-admission-model-program-head`
**Status:** APPROVED QUEUED PLAN — 2026-08-15 reconciliation; no implementation until sole-change promotion
**Decision set:** D-087, D-ROLE-05, D-080, D-089, D-090, D-091, D-092, D-093

## 1. Canonical truth map

```text
OWNER       AUTHORITATIVE ACTIONS
----------- --------------------------------------------------------------
Registrar   review/correct application; ACCEPT/DISMISS; eligibility lookup;
            stamp ENROLLING / ENROLLED; correct/revert stamps with reason
Guidance/TA exam/session/proctor/scoring/release; consultation/advising;
            confirm human decision; finalize program pool/advised default;
            open targeted follow-up; internal case thread
Applicant   choose/replace current program within pool; acknowledge assisted
            default; read messages/access history; show digital/printed QR
PH          assigned-program dashboard; own quota; candidate overview;
            protected record when stronger case relationship exists;
            decline acknowledged/follow-up-targeted selection pre-enrolling; resolve follow-up;
            request appearance; bounded current-case messages
Analysis    future plugins produce attributable evidence only; no plugin is
            installed here and no output writes authoritative business state
```

### Immutable ownership rule

`Registrar ACCEPT` is the only application acceptance and the gate to exam assignment. PH outcomes use **resolve follow-up** / **decline acknowledged or follow-up-targeted selection**, never `accept`, so they cannot be confused with Registrar authority.

## 2. Canonical lifecycle

```text
submitted
  → needs_correction (optional loop)
  → accepted_by_registrar                    [exam gate]
  → exam_assigned / exam_completed
  → grading_finalized
  → result_released
  → advised                                  [confirmed consultation]
  → program_selection_pending_ack OR program_selected
  → enrolling                                [Registrar: sheet received/processing]
       ↳ needs_program_head                  [orthogonal edge-case flag/queue]
  → enrolled                                 [Registrar: signed paper verified; final program]
```

These are projections from domain-owned records, not one mutable stage column.

### Normal path

```text
Guidance advises + sets pool
  → Guidance records the advised default (normal assisted route)
       OR applicant self-selects
  → provisional selection reserves quota
  → applicant presents/submits enrollment sheet to Registrar
  → Registrar records attributable confirmation as needed + stamps enrolling
  → Registrar verifies completed signed enrollment sheet
  → Registrar confirms/adjusts final paper program + stamps enrolled
```

No PH system clearance is required on this normal route. A signed institutional enrollment sheet is external evidence that the final program accepted the applicant; it is not represented as a PH `accept` command.

### Edge path

```text
Guidance marks targeted PH follow-up
  → Registrar may still stamp enrolling when the sheet is submitted
  → primary status = enrolling; follow-up remains visible in PH work queue
  → PH: clear | decline | request appearance
  → enrolled remains blocked while follow-up is open
  → terminal PH clear/decline
  → Registrar verifies signed sheet and records final paper program
  → enrolled
```

`request_appearance` is non-terminal. Guidance cannot silently settle a PH-routed edge case. Direct PH decline without a routed follow-up remains pre-`enrolling` only.

### Reconsideration and paper truth

```text
Before enrolling:
  PH decline OR applicant changes choice
    → old provisional selection terminal
    → old quota reservation released atomically
    → applicant chooses another option / Guidance versions the pool

During enrolling:
  existing PH follow-up may resolve and release/change the provisional choice
    → enrolling remains
    → Registrar later records the signed paper's final program

At enrolled:
  final paper program may differ from advice/pool/provisional selection
    → pre-enrollment selection becomes paper_finalized
    → old reservation releases
    → final program commitment is recorded
    → full/unconfigured quota does not reject paper truth; variance is reported
```

## 3. Candidate relationship and confidentiality tiers

### Tier A — Potential overview

A candidate appears in a program's Potential list when:

- program is preference 1/2/3 **or** is in the active Guidance pool;
- applicant is not committed to a single different program; and
- application has been accepted by Registrar before identified rows are exposed.

Allowed row fields:

- applicant name/reference (minimum identity for staff workflow);
- basis tags (`preference #1/#2/#3`, `guidance pool`);
- pipeline tags (`accepted`, `exam done`, `result released`, `consulted/advised`, `selected`, `follow-up`, `enrolling`, `enrolled`);
- no scores, result document, counselor notes, private contact, or admission-support engine inputs/outputs.

Counts may include submitted preference candidates, but submitted/pre-accept rows stay aggregate-only.

### Tier B — Protected admission-support record

Full PH record access requires:

1. assigned program scope;
2. released result; and
3. an applicant-acknowledged current selection for this program or an explicit targeted follow-up routed to this program by Guidance.

Preference/pool membership and a pending-acknowledgement Guidance default remain Tier A only.

Allowed:

- converted per-domain scores;
- result/remark sheet;
- consultation outcome and bounded program-relevant counselor notes;
- assigned-program preference/pool basis and current selection/follow-up/reconsideration outcome;
- bounded read-only admission-support score charts/evidence for the assigned program, including separately attributed engine output only after a future plugin is approved;
- Registrar stamp state.

Never allowed:

- raw answer sheets / bubble scans;
- confidential conversion tables;
- pre-release scores;
- counseling notes unrelated to program requirements/decision;
- other program names/options, cross-program selection history, case threads, or records.

Every protected open persists canonical `ProgramHeadRecordViewed` audit evidence and applicant access-history evidence before protected fields are serialized; an evidence-write failure denies the view. A user-facing notice intent is deduplicated to the first protected open by the same PH/program within 24 hours and delivery runs after commit, so delivery failure never erases access evidence or falsely reports delivery.

## 4. Program options, selection, and quota

### Program options

`admission_attempt_program_pools` + `admission_attempt_program_options`

- Guidance owns append-only complete pool versions; no option row is edited or deleted.
- Pool header records attempt, monotonic version, predecessor, actor, reason, and source.
- Child option records program, order, and basis/source. Initial application preferences remain separate immutable intake values and Potential-list evidence; they never silently become Guidance options.
- The current pool is the highest committed version. On initial consultation finalization, `SetGuidanceProgramPool` requires an advised-default option and atomically appends the immutable pool/options plus a separate attributable `guidance_default` pending selection and FCFS reservation. Later versions retain the standing selection; changing/removing it requires the ordinary atomic transfer/cancel path. Counselor confirmation alone chooses the pool and default.

### Selection

`admission_attempt_program_selections`

Statuses:

```text
pending_acknowledgement  Guidance advised default; quota held; not yet standing,
                         but Registrar may acknowledge with sheet submission
active                   applicant chose or acknowledged; quota is held
superseded               applicant/reconsideration replaced it; slot released
program_declined         PH declined eligible selection; slot released
cancelled                reasoned pre-enrolling cancellation; slot released
paper_finalized          Registrar verified signed paper; reservation released/moved
```

Selection records exact option/pool references plus attempt/program/cycle, source (`student_self` | `guidance_default`), actor, applicant acknowledgement, commitment identity, idempotency key, optimistic version, terminal reason, paper-finalization reference, and timestamps.

Invariants:

- one non-terminal selection per attempt;
- selection create/transfer destination must belong to the current Guidance pool version and remain effective (no PH decline against that pool/program); an existing non-terminal selection may reference an older version only while its program remains effective in the current pool;
- selection only after result release + consultation/advice is available;
- quota capacity is checked under row lock in the selection transaction;
- idempotency is namespaced by actor + command/version + aggregate scope and stores a canonical request hash; same key/hash returns the original authoritative result, while same key with a different hash conflicts with no mutation/audit-success/notification;
- a pending assisted default never auto-acknowledges or auto-expires; Guidance may reasonedly cancel only its own still-unacknowledged default before `enrolling`; Registrar may record attributable applicant confirmation from the submitted enrollment sheet while atomically starting `enrolling`;
- ordinary change transfers capacity atomically (reserve new + release old or fail without change);
- a pool supersession cannot silently cancel, move, acknowledge, or release a non-terminal selection;
- once `enrolling` exists, ordinary cancel/transfer and direct PH decline are blocked; only assigned-PH resolution of an already-open follow-up and Registrar paper-confirmed finalization may terminalize/release the provisional selection;
- direct decline/new-follow-up versus `StampAttemptEnrolling`, and open-follow-up resolution versus `StampAttemptEnrolled`, use the common lock order and re-evaluate guards; `enrolled` can commit only after no open follow-up remains.

### Quota

- Existing `ProgramQuota` / `ProgramQuotaChange` remain authoritative capacity configuration (D-087).
- pre-enrollment reservation = `pending_acknowledgement + active` selections;
- commitment deduplicates each attempt across its current reservation and current `enrolling`/`enrolled` stamp;
- Potential rows, preferences, options, follow-ups without selection, and Registrar application acceptance consume zero quota;
- ordinary selection with missing/inactive current-cycle quota or zero slots is unavailable (fail closed), never unlimited; lowering configured quota below current commitments conflicts;
- PH manages assigned-program quota; Guidance Counselor/TA manages authorized quotas with notice + history;
- paper-confirmed final enrollment bypasses pool/capacity rejection because it records an already-authorized institutional fact: configured capacity is unchanged, the superseded reservation is released, and projection exposes `remaining = max(capacity - commitments, 0)` plus `over_capacity = max(commitments - capacity, 0)` or `not configured`.

## 5. PH program projections

Program appears in preference P1–P3 or active Guidance pool; candidate is not committed to a single different program and is not in a later primary list for this program. Each row exposes stage tags and one or more basis tags. Identified rows require Registrar acceptance.

Primary lifecycle precedence is `Enrolled → Enrolling → Upcoming → Potential`. Follow-up is an orthogonal work flag/queue rather than a mutually exclusive stage: an applicant may appear once in the primary program list and simultaneously in the assigned PH follow-up queue.

### Upcoming

All are true:

- current selection = program;
- selection status = `active` (not pending acknowledgement);
- only one active Guidance option remains;
- no unresolved targeted follow-up;
- not yet `enrolling`/`enrolled` (those have dedicated sections).

### Follow-up queue

Targeted open follow-up for this program. It may overlap `Enrolling`; it blocks only the final `enrolled` stamp. The assigned PH must clear or decline it in NEXIAM; `request_appearance` remains open.

### Enrolling / Enrolled

`Enrolling` uses the provisional program captured when Registrar receives the sheet. `Enrolled` uses the final program verified from the signed paper, which may differ. These are read-only PH lists; an open follow-up remains separately visible as work.

### Dashboard metric dictionary

All program-scoped, active-cycle, computed from committed state, source + `as_of` shown:

| Metric | Definition |
|---|---|
| Preference candidates | deduped attempts with program in P1–P3 |
| Accepted candidates | preference candidates accepted by Registrar |
| Exam completed candidates | accepted candidates with completed attendance + submission |
| Result released candidates | candidates with current result release |
| Advised candidates | candidates with confirmed consultation decision |
| Guidance-pool candidates | attempts with active Guidance option for program |
| Active reservations | pending-acknowledgement + active pre-enrollment selections for program |
| Upcoming | canonical Upcoming definition above |
| Open follow-ups | unresolved targeted follow-ups, including enrolling attempts |
| Enrolling / Enrolled | current Registrar stamp counts for provisional/final stamp program |
| Quota | deduplicated commitments / configured slots; remaining + over-capacity/not-configured variance |

No small-cell suppression (founder decision): authenticated assigned-program staff audience; default-deny scope; protected-view audit. Accessible table alternative required; no chart mutates state.

## 6. Follow-up and PH outcomes

`admission_attempt_program_follow_ups`

Statuses:

```text
open → cleared_by_ph
     → declined_by_ph
     ↘ appearance_requested   [non-terminal]
```

Rules:

- Guidance staff only may open a follow-up before `enrolling`.
- Requires result release + confirmed consultation; target program must be in preference/pool/current selection.
- `request_appearance` is a reasoned flag/action, not a terminal outcome.
- A PH-routed edge follow-up requires assigned-PH terminal activity; Guidance cannot settle or withdraw it after opening.
- PH decline appends one immutable assigned-program decline outcome against the targeted pool/program, terminalizes/releases any eligible provisional selection, and leaves `enrolling` intact when already present. It does not prevent Registrar later recording the signed paper's final program.
- Direct PH selection decline without a routed follow-up is allowed only before `enrolling`.
- No action is called `accept`; Registrar application acceptance and paper-confirmed final enrollment remain distinct.

## 7. Consultation-embedded admission-support visualization

### Current-cut evidence inputs

- released converted-score snapshot and per-domain values;
- ordered application preferences;
- approved structured consultation/interest values;
- application/exam/grading/release/consultation readiness facts;
- Guidance pool/default, follow-up, and human-decision history.

### Interface backbone

The existing `/consultation` selected-attempt workspace owns one applicant-specific tabbed admission-support view. It includes:

- overview/readiness;
- assessment profile charts backed by server-authored values and the same-value accessible table;
- preferences and structured consultation evidence;
- independent analysis-output regions with engine/source/version labels when a future plugin exists;
- human consultation decision, program pool/advised default, follow-up, and history.

Charts obey GF-22's typed chart contract: stable identifier/title/purpose, source projection, evidence/version/freshness, unit and ordered labeled series, null distinct from zero, approved semantic tokens, data/error state, and accessible table. The browser does not derive a business metric, cluster, recommendation, or decision merely to draw the chart.

The workspace has truthful `result_not_ready`, `no_analysis`, `stale_input`, `unavailable`, `invalid_output`, and `denied` states. Manual consultation and `SetGuidanceProgramPool` remain complete with no provider installed. Authorized PH Tier B may reuse only the assigned-program-safe read-only projection; Guidance retains consultation/advising authority.

### Deferred plugin boundary

The current change installs no engine/provider SDK, fake fixture engine, `ProgramSuggestion`, or proposal-review workflow. Two later integrations remain separate:

1. the verified YuKee artifact/K-Means compatibility plugin under GF-10 Path A; and
2. a separately named transparent deterministic/rule-based plugin under GF-10 Path B.

Their outputs remain independent evidence objects and MUST NOT be combined into one synthetic score or recommendation. BGE/interest embedding is a separate future research option. Any later engine output remains non-authoritative and cannot write consultation decisions, pool/default, selection, quota, follow-up, release, PH outcome, or enrollment state.

## 8. Messaging and access notices

### Internal case thread

PH ↔ Guidance only, scoped to an assigned-program quota-change or admission-support subject (consultation/current selection/targeted follow-up) plus cycle. Internal messages/counselor notes never appear to applicant.

### Applicant support thread

PH ↔ applicant only when program is current selection or targeted follow-up; pre-`enrolled`; redacted, audited, bounded to requirements/follow-up/next steps. Potential preference alone does not permit outreach. This is not a general inbox or marketing channel.

Thread subject and participants are server-owned. When the qualifying selection/follow-up relationship ends, new messages are denied and the thread is read-only only for actors who still pass current scope; revoked, expired-duty, or cross-program actors receive no history. Message/read history and audit evidence are retained.

### Notices

After commit:

- protected record opened (deduplicated notification + complete access history);
- selection declined;
- follow-up opened/resolved;
- PH support message;
- selection changed / quota reservation moved;
- enrollment stamp changed/reverted.

## 9. Enrollment stamps

`admission_attempt_enrollment_stamps`

Transitions:

```text
none → enrolling → enrolled
          ^           |
          +-- reasoned Registrar correction/revert --+
```

`enrolling` preconditions:

- application accepted by Registrar;
- exam/assessment completed where required;
- result released;
- consultation advised;
- applicant presents/submits the external enrollment sheet;
- Registrar records the provisional program from current selection/default or the presented sheet and may record attributable counter acknowledgement.

An unresolved follow-up is shown but does not block `enrolling`.

`enrolled` requires current `enrolling`, no unresolved targeted PH follow-up, and Registrar verification of the completed signed enrollment sheet. `StampAttemptEnrolled` records verifier, paper reference/hash, final program, and reason/source; it may atomically paper-finalize the provisional selection and move the commitment to a different, out-of-pool, declined, full, or unconfigured program without altering configured capacity. Any variance is reported.

While `enrolling`, core acceptance/exam/result/advice facts and opening new follow-up are frozen; existing PH follow-up resolution and Registrar paper finalization remain allowed. While `enrolled`, prerequisite-invalidating changes require ordered reasoned Registrar reversal. Both stamps/corrections are Registrar-only, append-only, audited, and version-checked. Scope ends here (BR-026).

## 10. Universal applicant QR contract

- NEXIAM exposes one applicant QR concept throughout the admission journey, not feature-specific eligibility, consultation, grading, PH, or enrollment QR codes.
- Portal and printed representations may have different cryptographic lifetimes, but both resolve the same opaque applicant-attempt locator.
- Payload contains no name, scores, notes, business action, destination, capability, role grant, or permission.
- Copying a QR grants nothing: scanner must authenticate; CapabilityAuthorizer evaluates the actor's ordinary capabilities, current duty, office/program/cycle relationship, case state, and requested destination/action.
- Scan verifies the locator and opens a role-neutral applicant action hub. Server-authored destinations may include Registrar application/eligibility/enrollment work, Guidance/TA exam/grading/result/consultation work, or bounded PH assigned-program admission support.
- Scan never performs a business mutation. Every selected destination opens its ordinary interface; every later action uses ordinary confirmation, validation, idempotency, version, transaction, and audit.
- No permitted destination returns a denied/not-found-equivalent response without applicant data. A PH scan never expands assigned-program scope.

## 11. Capabilities

| Key | Actor/scope | Grants |
|---|---|---|
| `program.head.view` | PH / PROGRAM | dashboard, candidate lists, tiered record views |
| `program.head.follow_up.resolve` / `program.head.selection.decline` | PH / PROGRAM | clear/decline/request appearance on targeted follow-up; decline acknowledged or explicitly follow-up-targeted current selection pre-enrolling |
| `program.head.message` | PH / PROGRAM | bounded message on current selection/follow-up |
| `guidance.selection.manage` | Guidance | pool, assisted default, reconsideration extension |
| `guidance.follow_up.manage` | Guidance | open a targeted edge follow-up before enrolling; no terminal settlement |
| `record.thread.participate` | Applicant OWN + PH/Guidance server-owned case relationship | internal PH↔Guidance thread or bounded current-selection/follow-up applicant-support thread; subject determines participants |
| `registrar.enrollment.stamp` | Registrar / OFFICE | enrolling/enrolled + reasoned correction |
| `applicant.program.select` | Applicant / OWN | select, acknowledge assisted default, transfer/cancel within active pool |
| `applicant.qr.resolve` | Authenticated staff + ordinary underlying scope | verify universal applicant locator and resolve only currently authorized destinations/actions; never grants business authority |
| existing `program.quota.manage` | PH PROGRAM / TA authorized | quota + history/notice |

GF-21 CapabilityAuthorizer remains authority; role names only seed helper grants.

## 12. Commands and queries

### Commands

- `SetGuidanceProgramPool`
- `SelectApplicantProgram` / `AcknowledgeAssistedProgramSelection`
- `TransferApplicantProgramSelection` / `CancelApplicantProgramSelection`
- `DeclineApplicantProgramSelection`
- `OpenProgramFollowUp` / `ResolveProgramFollowUp`
- `SendAdmissionSupportMessage` / `MarkAdmissionSupportMessageRead`
- `StampAttemptEnrolling` / `StampAttemptEnrolled` / `ReverseEnrollmentStamp`
- existing `UpsertProgramQuota`, `RecordConsultationDecision`

FormRequests own write validation; Actions own transactions; controllers delegate (<120 lines); every mutation uses version/idempotency/audit as applicable; notifications run only after commit.

Attempt-scoped mutations use one lock protocol: lock the attempt root first; then current stamp, pool/option, selection, and follow-up rows needed by the command in that order; then lock quota rows by ascending program ID. Re-evaluate capability relationship, expected versions, pool effectiveness, stamp freeze, and capacity under those locks before any write. `UpsertProgramQuota` locks its authoritative quota row before comparing active reservations. Failure commits no domain state, success audit, or notification intent.

### Queries

- `ProgramHeadDashboardQuery`
- `PotentialEnrolleesQuery`
- `UpcomingEnrolleesQuery`
- `ProgramFollowUpQueueQuery`
- `ProgramHeadApplicantSummaryQuery` (Tier A)
- `ProgramHeadApplicantDetailQuery` (Tier B)
- `ConsultationAdmissionSupportQuery`
- `ResolveApplicantQrQuery` / `ApplicantActionMenuQuery`
- existing `EnrollmentEligibilityQuery` extended with selection/follow-up/stamp facts

## 13. Pages and states

```text
/staff/programs
  ProgramHead/Index                 own programs only
/staff/programs/{program}
  ProgramHead/ProgramShow           metrics, Potential, Upcoming,
                                    Follow-up, Enrolling, Enrolled, quota
/staff/programs/{program}/applicants/{attempt}
  ProgramHead/ApplicantShow         tiered summary/protected record
/staff/scan
  Staff/ApplicantScan                universal applicant QR scanner/manual code
/staff/applicants/{attempt}/actions
  Staff/ApplicantActionHub           server-authored destinations for current actor
/applicant/program-choice
  Applicant/ProgramChoice            pool, select/ack/change, status/notices
/applicant/qr
  Applicant/MyQr                     one portal QR for institutional staff interactions
/applicant/admission-support
  Applicant/AdmissionSupport         messages and access history
/consultation
  existing Release/Consultation      selected-attempt overview, score charts/table,
                                     preferences/consultation evidence, independent
                                     future-analysis regions, human decision, atomic
                                     pool/advised default, thread, and edge follow-up
/registrar/enrollment-eligibility
  existing page                      selection/follow-up/stamp; reachable from action hub
```

Every page distinguishes loading, ready, empty, filtered-empty, denied/not-found, validation, stale/conflict, server error, and applicable no-analysis/degraded-provider/invalid-or-expired-QR states. Server authors nav, breadcrumbs, QR action destinations, and action affordances; UI never authorizes.

## 14. Data structures

- `admission_attempt_program_pools`: immutable pool-version headers `(attempt, version)` with actor/source/reason and predecessor reference. The highest committed version is current; `SetGuidanceProgramPool` locks the attempt and rejects a stale expected version before appending the next complete version.
- `admission_attempt_program_options`: immutable children of one pool header; each row records program, display order, and option basis/source. Unique `(pool_id, program_id)` and `(pool_id, display_order)`.
- `admission_attempt_program_selections`: immutable pre-enrollment choices referencing their exact option/pool version; statuses include `pending_acknowledgement`, `active`, `superseded`, `program_declined`, `cancelled`, and terminal `paper_finalized` linked to the enrollment stamp.
- `admission_attempt_program_follow_ups`: snapshots the targeted `pool_id` when one exists; state/history never doubles as the authoritative option-exclusion record.
- `admission_attempt_program_declines`: immutable PH outcomes keyed by attempt/program and targeted `pool_id` when one exists, with selection/follow-up source reference, actor, reason, idempotency/audit correlation, and timestamps. Both direct selection decline and follow-up decline write this same authority; effective-option queries never infer exclusion from UI state.
- `admission_attempt_enrollment_stamps`: append-only `enrolling`/`enrolled`/reversal events with provisional/final program, external paper reference/hash, verifier, reason/source, quota-variance snapshot, version, and timestamps. `enrolled` may paper-finalize the provisional selection; the stamp, not the Guidance pool, owns final-program truth.
- `record_threads`, `record_thread_messages`, `record_thread_reads`
- existing preferences on `admission_attempts`, `ProgramQuota`, `ConsultationDecision`, `ResultRelease`, generated result document

History rows are never deleted. DB constraints/partial indexes protect unique `(attempt, pool_version)` headers and option membership/order, one non-terminal selection, one open follow-up per attempt/program/cycle, one effective PH decline outcome per attempt/program/targeted pool version (source command is provenance, not uniqueness), stamp order, and quota capacity transaction boundaries. The attempt row serializes pool-version allocation; current pool is the highest committed version rather than a mutable flag.

## 15. Implementation order

0. Promote D-ROLE-05/D-080/D-089–D-093 + propagate GF/PRD/page/story truth.
1. Capabilities, schema, constraints, models.
2. Guidance options + assisted default; applicant selection/ack/change + quota guard.
3. PH dashboard, tiered visibility, lists, protected audit/access notice, quota panel.
4. Internal/support threads and after-commit notices.
5. Consultation-embedded admission-support tabs/charts/table backed by real released-score/application/consultation evidence; no plugin.
6. Follow-up/decline/reconsideration paths.
7. Registrar stamps + eligibility projection/correction.
8. Universal applicant QR + generic scanner/action hub + portal/print representations.
9. Seeds, HANDOFF, targeted Pest/PHPStan/Pint/browser proof; full suite after shared OMR seed stabilizes.

## 16. Non-goals

YuKee/K-Means integration; deterministic/rule-based plugin; BGE/interest engine; provider SDK; fake fixture engine; `ProgramSuggestion` workflow; predictive claims; autonomous placement; PH Registrar-Accept; PH score/release writes; raw sheets/scans/conversion tables; full inbox/marketing; enrollment beyond two stamps; quota consumption by preferences/pool; QR-as-authority; feature-specific QR codes; cross-program record access.
