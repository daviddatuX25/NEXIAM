# Spec: Staff Personnel

## Requirements

### Requirement: HR and office-limited personnel lifecycle
The system SHALL allow authorized actors to create, update, deactivate, and activate staff accounts with approved permanent and support role helpers under D-014/D-020 ceilings.

#### Scenario: HR creates permanent staff
- **WHEN** an actor with `hr.staff.manage` submits valid name, email, password (confirmed), office, and allowed role helpers
- **THEN** a User and active StaffProfile are created, roles synced, and `hr.staff.created` is audited

#### Scenario: Office creates support staff only
- **WHEN** an actor with `office.support_staff.manage` attempts to assign a role outside their office support ceiling
- **THEN** the command is denied and no account is created

#### Scenario: Deactivated staff cannot login
- **WHEN** a staff profile status is `inactive`
- **THEN** staff password login fails without authenticating the session

#### Scenario: Capability deny
- **WHEN** an actor without personnel capabilities opens `/staff/personnel`
- **THEN** the request is denied (403) and authorization denial is audited
