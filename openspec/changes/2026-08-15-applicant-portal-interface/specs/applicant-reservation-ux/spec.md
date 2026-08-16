# applicant-reservation-ux Spec

## ADDED Requirements

### Requirement: One shared slot-row and confirm-sheet contract

Exam session reservation and consultation booking SHALL share one component contract: slot row (date, time, venue, capacity bar, action), confirm sheet (what, when, consequence copy, Cancel/Confirm), and identical states — open, full, closed, already-booked, changed-by-staff. The two pages SHALL differ only in venue semantics and consequence copy, never in anatomy (GF-22 §9.1).

#### Scenario: Full slot
- **WHEN** a slot has no remaining seats
- **THEN** it renders disabled with "Full" and no confirm sheet can open

#### Scenario: Closed window
- **WHEN** the reservation window is closed
- **THEN** the page explains why and disables selection truthfully

#### Scenario: Already booked
- **WHEN** the applicant has an existing reservation
- **THEN** the current booking renders with a change/cancel affordance per existing commands

### Requirement: Confirmation never overclaims

Confirm sheets and completion toasts SHALL claim only what the command committed: reservation created, not session published, slip generated, or notification delivered (REQ-VS-028/030).

#### Scenario: Reservation confirmed
- **WHEN** a reserve command commits
- **THEN** the confirmation states the reservation is recorded and says nothing about publication or delivery

### Requirement: Idempotent and conflict-safe submits

Both reservation posts SHALL carry the existing idempotency keys, disable the confirm button while in flight, and SHALL surface staff-change conflicts as a refresh banner rather than duplicating or overwriting state (REQ-VS-028).

#### Scenario: Double-click
- **WHEN** the applicant double-submits the confirm sheet
- **THEN** the in-flight guard prevents a second request and replay of the same key returns the original reservation

#### Scenario: Staff changed the slot
- **WHEN** staff modified the session after the page loaded
- **THEN** the server conflict renders a refresh banner and the applicant's action is not silently applied to stale data
