# applicant-notifications-center Spec

## ADDED Requirements

### Requirement: Truthful notification status wording

Each notification entry SHALL show a status derived from authoritative evidence: in-app delivered, queued, or failed. The UI SHALL NOT claim email/SMS/mail or provider delivery without after-commit provider evidence (REQ-VS-023/030; GF-22 toast rules). Unread count SHALL come from the same projection as the list.

#### Scenario: Queued notification
- **WHEN** a notification has no provider delivery evidence
- **THEN** the entry shows "queued" and never "sent" or "delivered"

#### Scenario: Failed notification
- **WHEN** delivery failed terminally
- **THEN** the entry shows a truthful failed state and a retry/note path, never a silent success

### Requirement: Empty state cannot mask stored unread

The notification center SHALL verify unread-count consistency with the list; an empty render while unread rows exist SHALL surface the discrepancy rather than showing a false empty state (REQ-VS-023).

#### Scenario: Count/list mismatch
- **WHEN** the unread count is nonzero but the list fails to load
- **THEN** an error state with retry renders, not an empty "no notifications" screen

### Requirement: Mark-read and read-all semantics

Mark-read and read-all SHALL use the existing commands and SHALL update badge and list from the server response; the client SHALL NOT optimistically clear rows without server confirmation (REQ-VS-028).

#### Scenario: Mark one read
- **WHEN** the applicant marks an entry read
- **THEN** the server confirms, the row and badge update, and a failed request leaves state unchanged with a retry affordance
