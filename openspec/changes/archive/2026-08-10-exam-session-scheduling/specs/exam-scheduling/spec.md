## ADDED Requirements

### Requirement: Assign requires accepted eligible attempt
The system SHALL assign only exam-eligible (accepted) attempts via `AssignApplicantToExamSession`.

#### Scenario: Assign requires accepted eligible attempt
- **WHEN** staff assigns an attempt that is not exam-eligible
- **THEN** the command is rejected

### Requirement: Session booking mode dual path
The system SHALL default new exam sessions to `booking_mode=staff_only` and SHALL allow staff to open/close reservation per session without a SlotPlan aggregate.

#### Scenario: Open session for reservation
- **WHEN** staff opens reservation on a non-terminal session with room and times set
- **THEN** `booking_mode` becomes `open_for_reservation` and applicants may reserve

#### Scenario: Close reservation
- **WHEN** staff closes reservation
- **THEN** `booking_mode` returns to `staff_only`; existing assignments remain; new applicant reserves are denied

### Requirement: Applicant reserve creates assignment
The system SHALL create an `ExamAssignment` with `source=applicant_reserved` when an eligible applicant reserves an open session, subject to capacity and one-active-assignment rules.

#### Scenario: Reserve while open
- **WHEN** an accepted applicant confirms a seat on an open session with capacity
- **THEN** an active assignment is created and the calendar reflects remaining capacity

#### Scenario: Reserve when closed
- **WHEN** the session is `staff_only`
- **THEN** `ReserveExamSlot` is denied

### Requirement: Applicant calendar schedule UX
The applicant schedule surface SHALL present open sessions primarily as a calendar with capacity and confirmation.

#### Scenario: Calendar open
- **WHEN** at least one session in the applicant cycle is open for reservation and they have no active assignment
- **THEN** the applicant sees calendar cells and can confirm a reservation
