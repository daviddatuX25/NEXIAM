## ADDED Requirements

### Requirement: Auth-before-retrieve for policy Inquiry
The system SHALL retrieve policy chunks only through KnowledgeRouter with trusted actor filters, never from client-supplied audience/scope.

#### Scenario: Staff retrieves active matching policy
- **WHEN** an active source matches the query and audience allows staff
- **THEN** retrieval returns hits with version_id and excerpt

### Requirement: Revoked sources cannot be cited
#### Scenario: Revoke then inquire
- **WHEN** an active version is revoked
- **THEN** subsequent policy Inquiry retrieval omits that version

### Requirement: No-support refusal
#### Scenario: Unrelated query
- **WHEN** no active filtered chunk matches
- **THEN** the run emits an assistant refusal / no citation snapshot (no fabricated policy cite)

### Requirement: No own-data embedding in this cut
#### Scenario: Index remains policy corpus
- **WHEN** this OpenSpec is implemented
- **THEN** KnowledgeIndexPort still indexes only knowledge_source_versions — not applicant operational rows
