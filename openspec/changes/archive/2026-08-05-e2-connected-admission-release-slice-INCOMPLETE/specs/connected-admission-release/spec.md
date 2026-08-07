# Spec: Connected Admission-to-Release

## Purpose

Prove one seeded applicant can move from public/staff submission through Registrar acceptance, applicant credential setup, exam session, contextual proctoring, manual grading, consultation, release, notification, and applicant retrieval — with denied paths, audit, and explicit page states.

## Requirements

### Requirement: Deterministic foundation for the slice
The system SHALL be recreatable from an empty approved database via deterministic reset/seed supporting the connected journey fixtures.

#### Scenario: Demo reset
- **Given** the demo environment
- **When** the approved reset/migrate/seed procedure runs
- **Then** the same actors, references, and workflow starting state are recreated

### Requirement: Authenticated actors only
Every actor SHALL authenticate through the approved credential model; Applicant identifier alone is insufficient.

### Requirement: Scope isolation
Applicants SHALL see only own records; unauthorized staff and Program Head cross-scope access SHALL be denied without leakage.

### Requirement: Admission attempt lifecycle
The system SHALL support submit, guarded correct, accept, dismiss, and non-destructive reopen/archive under approved rules, creating durable ApplicantIdentity + cycle-scoped AdmissionAttempt with post-commit access challenge independent of acceptance.

### Requirement: Exam session integrity
Session create, applicant assignment, room/capacity/time conflicts, and publication SHALL be transactionally validated. One effective deadline SHALL apply after extension; all close paths SHALL reconcile and open grading exactly once.

### Requirement: Contextual proctor duty
Proctor capability SHALL exist only through active session assignment and SHALL disappear after revocation or closure.

### Requirement: Idempotent attendance and submission
Attendance and submission SHALL be idempotent, actor-attributed, and legal only during approved state/time windows.

### Requirement: Manual grading fallback
Manual scoring SHALL complete the operational journey independent of OMR/model/provider availability. Finalization SHALL require complete valid scores.

### Requirement: Human consultation and release
Counselor decisions SHALL record approve/modify/override/defer without autonomous state change. Release SHALL require prerequisites; unrelease SHALL preserve history. Notifications SHALL emit once after commit with observable retryable failure.

### Requirement: Safe documents and previews
Generated documents SHALL encode untrusted values safely with template/version/checksum evidence. Internal preview SHALL be watermarked and audited; external distribution denied until release prerequisites pass.

### Requirement: Audit and UX contracts
Critical events SHALL be auditable and verifiable. Connected pages SHALL expose explicit page states. Mutations SHALL use validation, idempotent submit, risk-appropriate confirmation, and truthful pending/success wording. Navigation SHALL be server-authorized with breadcrumbs. Journeys SHALL work at narrow widths and by keyboard. Browser runs SHALL record no unhandled console errors on required paths.

### Requirement: Gated differentiators stay out of E2
OMR proposals, policy RAG, and unapproved Program Head metric dictionaries remain non-goals for this change; manual and presentation fallbacks apply.
