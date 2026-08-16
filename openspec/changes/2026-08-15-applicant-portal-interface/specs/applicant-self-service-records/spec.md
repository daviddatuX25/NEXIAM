# applicant-self-service-records Spec

## ADDED Requirements

### Requirement: My Application is read-only and own-scope

`GET /applicant/application/{attempt}` SHALL render the applicant's own read-only record: status, personal information, ordered preferences, digital intake grades (D-086), and submitted timestamp. Access SHALL use the existing own-scope check; another applicant's attempt SHALL 403 (FR-030; REQ-VS-003). Staff-only fields SHALL NOT serialize.

#### Scenario: Own record
- **WHEN** the authenticated applicant opens their own attempt
- **THEN** the record renders read-only with no edit affordance except the needs_correction-gated fix prompt

#### Scenario: Cross-applicant access
- **WHEN** the applicant requests another attempt id
- **THEN** the server returns 403 and no record fields serialize

### Requirement: Guidance default projects without acknowledgement

The Program-choice page SHALL present a Guidance default in `pending_acknowledgement` as an active projection: `✦ set by Guidance · projection active · No action needed`, with an optional acknowledgement action. Copy SHALL NOT imply the projection is absent or invalid until acknowledged. Acknowledgement SHALL upgrade the selection to the applicant's explicit current selection using the existing shipped action (D-093; program-choice-and-quota spec).

#### Scenario: Default active, ack optional
- **WHEN** the current selection is a Guidance default awaiting acknowledgement
- **THEN** the page shows the ✦ active projection, quota reservation truth, and the optional "This is my choice" action; no blocking prompt renders

#### Scenario: Applicant acknowledges
- **WHEN** the applicant confirms the default as their choice
- **THEN** the existing acknowledgement command runs, the selection becomes standing, and the page updates with truthful confirmation

### Requirement: Quota truth on the options list

The options list SHALL derive `reserved`, `available`, and `full` from authoritative server rows and SHALL show not-configured programs as unavailable. The client SHALL NOT compute counters (D-087).

#### Scenario: Not-configured program
- **WHEN** a pool option has no active current-cycle quota row
- **THEN** it renders unavailable with a truthful note and selection fails closed

#### Scenario: Full program
- **WHEN** a pool option's commitments equal or exceed capacity
- **THEN** it renders full and is not selectable

### Requirement: Public landing preserves journey context

`GET /` SHALL render the institution name, an active-season badge when a season is open, a lean hero, the process strip, and three entry CTAs (Apply, Applicant sign in, Staff sign in) using kit tokens. No marketing widgets or unapproved copy SHALL render.

#### Scenario: Open season
- **WHEN** an active admissions season exists
- **THEN** the badge names the season and links to the apply flow

#### Scenario: No open season
- **WHEN** no season is active
- **THEN** the badge is absent and the Apply CTA copy reflects that intake is closed rather than silently failing

### Requirement: Applicant login stays access-key simple

The applicant login SHALL require access key + birthdate (D-085), SHALL offer device-save and email-backup copy, and SHALL return non-enumerating errors. No password field SHALL exist.

#### Scenario: Wrong key or birthdate
- **WHEN** either credential is wrong
- **THEN** one non-enumerating error renders and no field-level existence signal leaks
