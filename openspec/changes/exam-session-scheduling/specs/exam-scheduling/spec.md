## ADDED Requirements

### Requirement: Staff exam session management (legacy shape)
The system SHALL provide Test Administrator exam session Index, Create, Edit, and Show surfaces that preserve the legacy TestScheduling interaction shape (list first; create/edit as separate pages; assign and publish from show/list actions), authorized by CapabilityAuthorizer capability for exam scheduling (not role middleware).

#### Scenario: Index has no create form
- **WHEN** an authorized scheduler opens the exam sessions index
- **THEN** the page lists sessions with status and actions and links to a separate create page

#### Scenario: Assign requires accepted eligible attempt
- **WHEN** staff assigns an attempt that is not exam-eligible
- **THEN** the command is denied and no assignment row is created

#### Scenario: Capacity conflict
- **WHEN** an assignment would exceed room capacity
- **THEN** the command fails transactionally with a typed conflict and no partial assignment

### Requirement: Publish is the notification boundary
The system SHALL allow publishing only when session schedule fields and roster rules are satisfied, and SHALL treat publication as the boundary for applicant schedule notification.

#### Scenario: Publish without applicants blocked
- **WHEN** staff publishes a draft session with zero assignments
- **THEN** publish is rejected with a clear reason

### Requirement: Applicant reservation window
The system SHALL allow an accepted exam-eligible applicant to reserve an available slot only while an ExamSlotPlan is `open_for_slot_reservation`, and SHALL deny reservation otherwise (pending acceptance, waiting for open, closed, finalized, bypassed).

#### Scenario: Accepted applicant reserves on open plan
- **WHEN** the plan is open and the applicant has no active assignment/reservation
- **THEN** `ReserveExamSlot` creates a capacity-safe reservation and the calendar reflects remaining capacity

#### Scenario: Reservation denied when plan closed
- **WHEN** the plan is not open for reservation
- **THEN** reserve is denied and the portal shows the appropriate waiting/closed state

### Requirement: Dual path coexistence
Staff manual assignment SHALL remain available (including late assignment) when reservation is closed or bypassed. Finalized reservations SHALL become ExamAssignments before or as part of publish preparation.

#### Scenario: Staff bypasses reservation
- **WHEN** the Test Administrator marks the slot plan bypassed and assigns manually
- **THEN** applicants cannot open the reservation calendar and staff assignment proceeds

### Requirement: Applicant calendar schedule UX
The applicant schedule surface SHALL present open slots primarily as a calendar (month/week) with capacity and confirmation, not only as a flat select list.

#### Scenario: Calendar shows open sessions
- **WHEN** reservation is open
- **THEN** the applicant sees calendar cells for available sessions and can confirm a reservation
