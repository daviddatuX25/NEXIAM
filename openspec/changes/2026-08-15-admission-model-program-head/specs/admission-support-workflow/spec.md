# admission-support-workflow Spec

## ADDED Requirements

### Requirement: Registrar acceptance remains the exam gate

Only Registrar Clerk/authorized Registrar Administration capability SHALL Accept or Dismiss an admission attempt. An attempt MUST be accepted before exam assignment. Guidance consultation/advice, applicant program choice, PH follow-up/decline, and enrollment stamps MUST NOT create, imply, or rename a second application-acceptance decision.

#### Scenario: Accepted attempt becomes exam-assignable
- **WHEN** authorized Registrar accepts a submitted attempt
- **THEN** exam assignment eligibility becomes true and the acceptance/audit actor is Registrar

#### Scenario: PH cannot accept an application
- **WHEN** PH attempts to invoke an application acceptance route or command
- **THEN** authorization denies the request and the attempt state is unchanged

### Requirement: Targeted follow-up is an edge-case work queue

Guidance MAY open one active targeted follow-up per attempt/program/cycle before `enrolling`, with reason, requested action, actor, timestamps, and version. While open it SHALL project `needs_program_head` and remain visible in the assigned PH queue even when the attempt's primary status is `enrolling`. It MUST NOT block `StampAttemptEnrolling`, but it MUST block `StampAttemptEnrolled` until assigned PH terminally clears or declines it. `request_appearance` remains non-terminal. Guidance MUST NOT silently settle a PH-routed follow-up. PH decline SHALL preserve history, make the pre-enrollment option unavailable, and release any eligible provisional reservation atomically; it MUST NOT erase `enrolling` or prevent later Registrar paper confirmation.

#### Scenario: Open follow-up permits enrolling but blocks enrolled
- **WHEN** Registrar receives the sheet for an otherwise ready attempt with an open targeted follow-up
- **THEN** `StampAttemptEnrolling` may commit, the eligibility card shows `enrolling + follow-up pending`, and `StampAttemptEnrolled` remains unavailable

#### Scenario: PH clears follow-up during enrolling
- **WHEN** assigned PH clears the open follow-up after Registrar has stamped `enrolling`
- **THEN** `enrolling` remains current, the follow-up becomes terminal, and Registrar may finalize `enrolled` after signed-paper verification

#### Scenario: Request appearance remains unresolved
- **WHEN** assigned PH records `appearance_requested`
- **THEN** the follow-up remains open and `StampAttemptEnrolled` remains blocked

#### Scenario: PH declines a follow-up without a selection
- **WHEN** assigned PH declines a targeted follow-up for a pool option the applicant has not selected
- **THEN** the option becomes unavailable in the current pool version, no quota changes, `enrolling` remains if already stamped, and final paper truth remains Registrar-controlled

### Requirement: Internal PH-Guidance thread is bounded

PH and Guidance MAY exchange messages only on a quota-change, consultation, current selection, or targeted-follow-up subject within the PH's assigned program. Thread participants, subject, actor, read state, timestamps, and audit correlation SHALL be server-owned. Potential preference alone MUST NOT create access. This is not a general inbox and applicant is not a participant.

#### Scenario: Current-selection internal thread
- **WHEN** Guidance posts a case note for a current BSIT selection
- **THEN** assigned BSIT PH can read/reply and unrelated PHs/applicant cannot access the thread

### Requirement: PH-applicant support messaging is current-case only

PH MAY message an applicant only when the applicant has a current selection or targeted follow-up for the PH program and remains within the admission-support window. Applicant MAY reply only in that same bounded thread. Messages SHALL be audited, notify recipients after commit, and become read-only/closed when the case relationship ends. Marketing blasts, arbitrary applicant lookup, and preference-only outreach are prohibited.

#### Scenario: Current selection permits support
- **WHEN** assigned PH messages an applicant with a current BSIT selection
- **THEN** the message persists with actor/thread scope and the applicant receives an after-commit notification

#### Scenario: Preference-only outreach denied
- **WHEN** PH attempts to message an accepted Potential candidate grounded only by preference
- **THEN** the command is denied and no thread, message, audit-success, or notification row is created

### Requirement: Registrar starts processing before edge follow-up completion

Registrar SHALL stamp `enrolling` when application acceptance, required exam, released result, and advised milestones exist and the applicant presents/submits the external enrollment sheet. Registrar SHALL record a provisional program from the current selection/default or presented sheet and MAY record attributable applicant confirmation of a pending Guidance default in the same transaction. An open targeted PH follow-up SHALL be shown but SHALL NOT reject `enrolling`.

Registrar SHALL stamp `enrolled` only from current `enrolling`, after completed signed-sheet verification and after every targeted PH follow-up is terminal. `StampAttemptEnrolled` SHALL accept the paper's final program and MAY differ from the Guidance pool/default, provisional selection, prior PH decline, or configured quota. It SHALL append immutable paper-verification/final-program evidence, terminalize the provisional selection as `paper_finalized`, release/move its commitment atomically, leave configured capacity unchanged, and report any not-configured/over-capacity variance. No tuition, subjects, class lists, payments, or long-term enrollment records are created.

#### Scenario: Normal assisted route enters enrolling
- **WHEN** Guidance recorded a pending advised default and the applicant presents the enrollment sheet at Registrar
- **THEN** Registrar may record attributable counter confirmation and one `enrolling` event atomically without PH clearance

#### Scenario: Enrolled before enrolling denied
- **WHEN** Registrar attempts to stamp enrolled without a current enrolling event
- **THEN** the command is rejected and no stamp/audit-success/notification effect is committed

#### Scenario: Signed paper confirms a different full program
- **WHEN** the completed signed sheet names BSN instead of the provisional BSIT selection and BSN is already at configured capacity
- **THEN** Registrar records BSN as the final program, paper-finalizes/releases the BSIT reservation, leaves BSN capacity unchanged, stamps enrolled, and exposes an over-capacity variance

#### Scenario: Open follow-up blocks final enrollment
- **WHEN** Registrar attempts to stamp enrolled while an assigned-program follow-up remains open
- **THEN** the command reports the PH resolution requirement and writes no final-program, selection, quota, stamp, audit-success, or notification effect

#### Scenario: Reasoned correction
- **WHEN** Registrar reverts an erroneous enrolled stamp with current version and reason
- **THEN** a reversal event is appended, the prior event remains in history, and the projection returns to enrolling

### Requirement: Enrollment stamps freeze different boundaries

While `enrolling` is current, application acceptance reversal/dismissal, grading/result unfinalize or unrelease, removing advised status, opening a new follow-up, ordinary selection cancel/transfer, and direct PH decline SHALL be rejected. Resolution of the already-open assigned-PH follow-up and Registrar paper-confirmed finalization SHALL remain allowed. While `enrolled` is current, prerequisite-invalidating mutations SHALL require ordered reasoned Registrar reversal. No command or background process may auto-reverse or overwrite a stamp.

#### Scenario: Result unrelease after enrolling is blocked
- **WHEN** Guidance attempts to unrelease a result while the attempt has a current `enrolling` stamp
- **THEN** the command is rejected with the Registrar reversal path and no release, stamp, audit-success, or notification state changes

### Requirement: Registrar lookup separates start-processing from final-enrollment readiness

The existing Registrar search/card and the Registrar destination reached from the universal applicant action hub SHALL show application/exam/release/advice facts, provisional program + acknowledgement source, paper-received/verified state, open PH follow-up, provisional/final program, quota variance, `enrolling`, and `enrolled`. It MUST distinguish “may start enrolling” from “may finalize enrolled” and MUST NOT expose counselor notes, internal messages, engine rationale, or PH discussion. Every protected lookup remains audited.

#### Scenario: Pending default confirmed at Registrar
- **WHEN** an applicant presents a sheet for a pending Guidance default
- **THEN** the card offers attributable counter confirmation with `StampAttemptEnrolling`; it does not require a normal-route PH action

### Requirement: One universal applicant QR resolves actor-specific destinations

The applicant portal and approved printed documents MAY display representations of one universal applicant QR concept. Digital and printed tokens may use different cryptographic lifetimes, but each SHALL resolve the same opaque applicant-attempt locator and SHALL NOT encode a feature-specific destination. The payload MUST NOT contain names, scores, birthdates, notes, program decisions, business actions, capability claims, role grants, or destination pages.

#### Scenario: Decoded payload contains no sensitive or routing authority
- **WHEN** an attacker decodes the QR representation without a signing key
- **THEN** no applicant identity, result, note, program outcome, business action, destination, or authority field is present

#### Scenario: Expired QR
- **WHEN** staff scan a validly signed but expired locator
- **THEN** the system returns an expired/window-closed state and exposes no applicant data

### Requirement: QR scan re-authorizes into a role-neutral applicant action hub

Scanning SHALL verify signature, universal locator purpose, expiry/window, and nonce/version rules, then resolve the authenticated actor's ordinary capabilities, current duty, office/program/cycle relationship, case state, and data scope into a server-authored applicant action hub. Registrar, Guidance/Test Administrator, and Program Head MAY receive different destinations for the same QR. A scan MUST NOT perform any state transition, and every destination/action SHALL retain its ordinary authorization, validation, confirmation, idempotency, transaction, and audit contract.

#### Scenario: Guidance has several applicant workflows
- **WHEN** authorized Guidance scans an applicant QR and currently holds the required exam, grading, result, and consultation capabilities
- **THEN** the action hub lists only the corresponding permitted destinations for that attempt and performs no action until Guidance chooses a destination and later confirms an ordinary command

#### Scenario: Registrar scans the same QR
- **WHEN** authorized Registrar scans the same applicant QR
- **THEN** the action hub exposes only Registrar-permitted application, milestone/eligibility, and enrollment destinations and does not expose Guidance or Program Head operations

#### Scenario: Assigned Program Head scans the same QR
- **WHEN** assigned PH scans the QR for a case satisfying D-080 relationship rules
- **THEN** the action hub exposes the bounded assigned-program admission-support destination/actions and no cross-program, grading, release, or Registrar operation

#### Scenario: Copied QR used by an unauthorized actor
- **WHEN** an authenticated actor with no permitted destination scans a copied valid QR
- **THEN** access is denied without revealing applicant data

### Requirement: Client notices follow authoritative commit

Selection, assisted default, PH protected-record open, PH decline, follow-up change, message, and enrollment-stamp notifications SHALL be enqueued only after the corresponding authoritative transaction commits. Duplicate delivery SHALL be idempotent and visible status wording SHALL not claim delivery before provider evidence.

#### Scenario: Transaction rolls back
- **WHEN** a quota conflict causes selection creation to roll back
- **THEN** no selection-success notification is enqueued
