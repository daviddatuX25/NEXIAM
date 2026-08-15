# program-choice-and-quota Spec

## ADDED Requirements

### Requirement: Guidance owns a versioned program pool

After result release and confirmed advice, authorized Guidance SHALL finalize an immutable complete pool and choose one advised-default option through `SetGuidanceProgramPool`. The initial transaction SHALL atomically append pool/options plus a separate attributable `guidance_default` pending selection and FCFS reservation. Later add/remove/reintroduce creates a reasoned complete version; an existing selection is retained unless explicitly transferred/cancelled. Preferences, admission-support analysis evidence, and the human-owned pool remain separate; only counselor confirmation through this command SHALL choose or write options/default.

#### Scenario: Counselor finalizes pool and advised default
- **WHEN** authorized Guidance confirms BSIT and BSN and chooses BSIT as the advised default
- **THEN** one transaction writes the immutable pool/options, separate pending BSIT default selection, one reservation, actor/reason/source, audit, and after-commit notice

#### Scenario: Analysis evidence cannot write the pool
- **WHEN** a current or future admission-support visualization indicates BSIT
- **THEN** the evidence remains read-only and BSIT is not added until authorized Guidance explicitly confirms it through `SetGuidanceProgramPool`

#### Scenario: Pool extension supersedes instead of mutating
- **WHEN** Guidance adds a third option or reintroduces a program declined by PH under the current version
- **THEN** a new complete reasoned pool version is created and the prior version remains immutable history

#### Scenario: Pool update cannot silently remove a standing choice
- **WHEN** Guidance submits a newer pool version that omits the program of a pending-acknowledgement or active selection
- **THEN** the command reports conflict and writes no pool, selection, quota, audit-success, or notification effect until the selection is explicitly cancelled or transferred

### Requirement: Applicant choice or Guidance default owns the provisional selection

Before `enrolling`, the applicant MAY acknowledge the normal Guidance default or choose another effective program from the current pool. The server MUST reject ordinary choices outside the pool, PH-declined options, stale versions, cross-attempt commands, and premature choices. This provisional selection is not final enrolled-program authority; Registrar-verified signed paper owns that later fact.

#### Scenario: Applicant selects an available option
- **WHEN** the authenticated applicant selects BSIT from the current pool with the current version
- **THEN** one active selection is created for that applicant and program

#### Scenario: Applicant selects outside the pool
- **WHEN** the applicant submits a program not present in the current pool
- **THEN** the command is denied without changing selection or quota

#### Scenario: Applicant retries a PH-declined option
- **WHEN** the applicant selects a program made ineffective by PH decline under the current pool version
- **THEN** the command is denied without changing selection or quota and presents the remaining-choice or Guidance-reconsideration path

### Requirement: Assisted default requires acknowledgement

The normal initial pool command SHALL create one attributable `guidance_default`, reserve capacity, and leave it `pending_acknowledgement` until applicant portal/Guidance confirmation or Registrar sheet-based confirmation while atomically starting `enrolling`. Staff presence alone is not acknowledgement. The default neither auto-acknowledges nor expires; Guidance MAY reasonedly cancel its still-pending default only before `enrolling`.

#### Scenario: Consultation finalization records default
- **WHEN** Guidance finalizes the advised pool/default
- **THEN** capacity is reserved, source is attributable, status is pending acknowledgement, and Registrar shows confirmation needed but may confirm it with sheet submission

#### Scenario: Applicant acknowledges the default
- **WHEN** the applicant acknowledges the current assisted selection
- **THEN** the selection becomes standing and may satisfy the program-choice prerequisite

#### Scenario: Guidance records in-person acknowledgement
- **WHEN** authorized Guidance records the no-device applicant's explicit in-person acknowledgement with channel/evidence
- **THEN** the selection becomes standing with applicant acknowledgement time and staff recorder attribution; an unattributed staff-only confirmation is rejected

#### Scenario: Pending default is not silently promoted or expired
- **WHEN** an assisted default remains unacknowledged across later requests
- **THEN** it continues to reserve one slot until attributable acknowledgement, authorized pre-enrolling cancellation, or Registrar records sheet-based confirmation while starting `enrolling`

### Requirement: Pre-enrollment selection reserves quota; final paper enrollment records truth

Application preferences, admission-support analysis evidence, Guidance pool membership, Potential-list membership, and follow-up marks SHALL consume zero quota. Pending-acknowledgement and active selections SHALL reserve one pre-enrollment slot. Quota commitment SHALL deduplicate an attempt across reservation and current enrollment stamp. Ordinary selection SHALL derive `reserved`, `available`, and `full` from authoritative rows rather than counters.

#### Scenario: Non-default pool membership does not consume quota
- **WHEN** BSIT appears as a non-default option in ten applicant pools
- **THEN** those option rows add zero to BSIT reserved count

#### Scenario: Assisted default reserves quota
- **WHEN** Guidance records an assisted default while capacity remains
- **THEN** the pending-acknowledgement selection consumes one slot so another applicant cannot overbook it

#### Scenario: Quota is not configured
- **WHEN** a current pool option has no active current-cycle quota row
- **THEN** the option remains visible as unavailable/not configured, selection fails closed, and no capacity is treated as unlimited

#### Scenario: Paper-confirmed program exceeds configured quota
- **WHEN** Registrar verifies a signed sheet for a different program whose commitments equal or exceed capacity
- **THEN** final enrollment still records, configured capacity is unchanged, the old reservation releases, and the target projection reports over-capacity rather than rejecting or silently increasing quota

### Requirement: Selection concurrency is FCFS, capacity-guarded, and idempotent

Selection create/transfer SHALL use the shared reservation-concurrency primitive with an authoritative program-capacity row lock, one-active-selection constraint, cycle/attempt guard, and idempotency key. Exactly one writer may claim the last slot. Replaying the same successful command SHALL return the same result without consuming a second slot.

#### Scenario: Two applicants race for the last slot
- **WHEN** two valid selection transactions race for a program with one available slot
- **THEN** exactly one commits and the other receives a truthful full/conflict result

#### Scenario: Successful replay
- **WHEN** the same actor replays a successful selection command with the same idempotency key
- **THEN** the original selection is returned and reserved capacity does not change

#### Scenario: Idempotency key reused for a different request
- **WHEN** the same actor reuses a selection/transfer idempotency key with a different canonical request payload
- **THEN** the command reports conflict and selection, quota, audit-success, and notifications remain unchanged

### Requirement: Transfer is atomic

A program change SHALL lock source and destination capacity in deterministic order, verify the current selection/pool/version, reserve the destination, supersede the source, and emit one correlated audit sequence in one transaction. Failure at any point SHALL retain the source selection and its quota reservation.

#### Scenario: Successful transfer
- **WHEN** an applicant changes from BSIT to BSN and BSN has capacity
- **THEN** BSN becomes the sole active selection and the BSIT slot is released atomically

#### Scenario: Destination is full
- **WHEN** an applicant attempts a transfer to a full program
- **THEN** the transfer fails and the original selection remains active and reserved

### Requirement: Cancellation remains applicant-controlled

Before `enrolling`, the applicant MAY cancel the current selection with reason, releasing quota while preserving pool/history. Guidance MAY cancel only its unacknowledged `guidance_default` or upon explicit attributable applicant instruction. During `enrolling`, ordinary cancellation/transfer SHALL be blocked; only assigned-PH resolution of an already-open follow-up and Registrar paper finalization may terminalize/release the provisional selection. `enrolled` requires Registrar reversal before correction.

#### Scenario: Guidance cannot silently cancel applicant choice
- **WHEN** Guidance attempts to cancel an acknowledged selection without attributable applicant instruction
- **THEN** the command is denied and selection, quota, audit-success, and notifications remain unchanged

### Requirement: Decline and reconsideration release capacity

Before `enrolling`, an assigned-program PH MAY decline a current selection only when it is applicant-acknowledged active or explicitly targeted by an open Guidance follow-up for the same program. A pending Guidance default without that follow-up MUST NOT be PH-declinable. The decline transaction SHALL make the program unavailable in the current pool version without rewriting Guidance history, release the slot, mark the selection declined, notify the applicant and Guidance after commit, and open reconsideration. The applicant MAY re-pick from remaining effective options; otherwise Guidance MAY create a reasoned superseding pool version. No PH action SHALL be named or treated as application Accept/Dismiss.

#### Scenario: PH declines current selection
- **WHEN** assigned PH declines a standing selection before enrolling
- **THEN** the slot is released, the applicant enters reconsideration, and notifications/audit identify the reason and actor

#### Scenario: Direct PH decline after enrolling
- **WHEN** PH submits a direct selection decline after Registrar stamped enrolling and no routed follow-up authorizes the action
- **THEN** the command is denied and selection/quota remain unchanged

#### Scenario: Existing follow-up decline during enrolling
- **WHEN** assigned PH declines an already-open targeted follow-up after `enrolling`
- **THEN** the follow-up becomes terminal, any provisional reservation releases once, `enrolling` remains, and Registrar still owns final paper-program confirmation

#### Scenario: Direct PH decline races Registrar enrolling stamp
- **WHEN** assigned PH direct-decline and Registrar `enrolling` commands race against the same current selection/version
- **THEN** deterministic locking and guard re-check allow exactly one to commit

### Requirement: Program demand projections have canonical definitions

Program lists SHALL use these definitions: Potential = accepted attempt with the program in application P1-P3 or current Guidance pool, excluding single-program commitments elsewhere and any later primary status for this program; Upcoming = standing acknowledged selection for this program with one active pool option, no open follow-up, and no enrollment stamp; Enrolling/Enrolled = current Registrar stamp's provisional/final program. Primary lifecycle precedence SHALL be `Enrolled → Enrolling → Upcoming → Potential`. Follow-up is a separate work queue that may overlap `Enrolling`, so PH work remains visible while basis/lifecycle tags retain all evidence.

#### Scenario: Preference-grounded Potential row
- **WHEN** an accepted applicant listed BSIT as preference 2 but has no pool or selection
- **THEN** the BSIT Potential list includes a minimal row tagged `preference #2` and consumes no quota

#### Scenario: Committed elsewhere excluded
- **WHEN** that applicant receives a one-option standing selection for BSN
- **THEN** the applicant no longer appears as BSIT Potential
