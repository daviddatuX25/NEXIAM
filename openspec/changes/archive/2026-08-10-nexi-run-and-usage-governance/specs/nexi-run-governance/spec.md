## ADDED Requirements

### Requirement: Durable conversation and run persistence
The system SHALL persist conversations, turns, runs, and sequenced run events in PostgreSQL for authenticated Nexi surfaces, partitioned by SurfaceProfile so staff and applicant history never mix.

#### Scenario: History survives navigation within staff surface
- **WHEN** an authorized staff actor submits a turn on `/staff` then navigates to `/exams/sessions` and reopens Nexi
- **THEN** prior turns in the same context epoch/partition remain available unless policy segmented the epoch
- **AND** the exams route having `page_pack: null` does not clear history or hide the dock

#### Scenario: Applicant cannot read staff conversation
- **WHEN** an applicant principal requests a staff conversation id
- **THEN** the system denies access

### Requirement: Queued Run worker with FakeAiGateway
Turn submission SHALL create one Run, reserve usage, and enqueue an idempotent worker that calls a FakeAiGateway, passes output through the application firewall/assembler, appends sequenced events, and settles or releases the reservation. No live provider SDK SHALL be installed in this change.

#### Scenario: Fake reply becomes sequenced events
- **WHEN** a staff actor submits a turn under FakeAiGateway
- **THEN** run events are persisted in order and the run reaches a terminal success or typed failure state

#### Scenario: Duplicate job delivery is idempotent
- **WHEN** the same Run job is processed twice
- **THEN** events are not double-appended and usage is not double-settled

### Requirement: Reauthorized SSE cursor transport
The system SHALL expose a generic SSE (or equivalent stream) endpoint that reauthorizes the subscriber, replays from cursor/`Last-Event-ID`, heartbeats, and falls back to an authoritative snapshot on sequence gaps — without piping provider bytes or requiring Redis/Echo/WebSockets.

#### Scenario: Reconnect resumes by cursor
- **WHEN** a client disconnects mid-run and reconnects with Last-Event-ID
- **THEN** it receives only subsequent events for that authorized run/conversation

### Requirement: TaskGrants for cross-page tool continuity
Cross-page tool work SHALL require a server-issued TaskGrant plus fresh CapabilityKernel authorization. Grants SHALL NOT inherit destination-page pack tools. Pack membership alone SHALL NOT grant capabilities (AD-3).

#### Scenario: Grant preserved within bounds across navigation
- **WHEN** a TaskGrant is active and the actor navigates within the grant’s surface/scope bounds
- **THEN** the grant remains usable with fresh Kernel checks

#### Scenario: Grant revoked on emergency stop
- **WHEN** emergency stop is activated
- **THEN** active TaskGrants are revoked and new runs are blocked

### Requirement: Usage budgets and reconciliation
The system SHALL atomically reserve multi-dimensional usage before provider/fake work, settle or release on completion, and reconcile unknown/orphan reservations via scheduled jobs.

#### Scenario: Exhausted budget denies new runs
- **WHEN** the actor/institution budget is exhausted
- **THEN** new turn submission is denied with `quota_exhausted` (or equivalent) and a manual fallback intent

### Requirement: Proposal shell without Apply mutation
The system MAY persist proposal shell records and emit proposal events for UI, but SHALL NOT execute domain command confirmation/Apply that mutates examination or setup records in this change.

#### Scenario: No domain mutation on proposal confirm endpoint absence
- **WHEN** only this OpenSpec is implemented
- **THEN** no Nexi confirm-to-command endpoint commits an ordinary domain Action

### Requirement: Non-goals enforced
This change SHALL NOT install `laravel/ai`, implement knowledge admin/RAG, or treat missing page packs as grounds to hide Nexi on mountable surfaces.
