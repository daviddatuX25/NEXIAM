## ADDED Requirements

### Requirement: Versioned knowledge lifecycle
The system SHALL persist knowledge sources and versions with AD-25 legal states and SHALL NOT use a soft `is_active` boolean as publication authority.

#### Scenario: Draft cannot be retrieved as active
- **WHEN** a version is in `draft` or `review_required`
- **THEN** FakeKnowledgeIndexPort retrieve for that version returns denied/empty for active corpus queries

### Requirement: Separation of duties on approve
The system SHALL deny approval when the approving actor is the version creator.

#### Scenario: Creator cannot approve own version
- **WHEN** the creating staff actor attempts approve
- **THEN** the transition is denied

### Requirement: Revoke denies retrieval synchronously
#### Scenario: Revoked source is not returned
- **WHEN** an active version is revoked
- **THEN** subsequent retrieve calls omit/deny that version before async index cleanup completes

### Requirement: No vendor SDK in this cut
#### Scenario: No Mixedbread dependency
- **WHEN** this OpenSpec is implemented
- **THEN** application knowledge paths do not require Mixedbread or laravel/ai packages

### Requirement: Citation fixtures gate activation
#### Scenario: Missing fixtures block active
- **WHEN** approval is attempted without required citation/refusal fixtures
- **THEN** the version cannot become `active`
