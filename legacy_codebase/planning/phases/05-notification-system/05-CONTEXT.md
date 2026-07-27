# Phase 5: Notification System - Context

**Gathered:** 2026-04-13
**Status:** Ready for planning

<domain>
## Phase Boundary

In-app notification system for key events in SecureCAT-v2 exam management system. Users receive notifications for application, grading, and scheduling events.

</domain>

<decisions>
## Implementation Decisions

### Storage
- **D-01:** Database table — Notifications persisted in database, enabling cross-device history and full notification lifecycle

### UI Location
- **D-02:** Bell icon with dropdown — Header shows bell icon with unread count badge, opens dropdown panel with notification list

### Delivery Mode
- **D-03:** Poll-based (30-60s interval) — Simple polling approach for checking new notifications, avoids WebSocket complexity

### Trigger Events
- **D-04:** Application status changes — When application status changes (pending→approved, etc.)
- **D-05:** Grading results ready — When grading results are available
- **D-06:** Scheduling changes — When exam session is scheduled/changed/cancelled
- **D-07:** Exam reminders — When there's an upcoming exam

### Claude's Discretion
- Notification list sorting (newest first vs oldest first)
- Individual notification read/unread behavior
- Notification actions (mark all read, clear all)

</decisions>

<canonical_refs>
## Canonical References

No external specs — requirements captured in decisions above.

</canonical_refs>

<codebase_context>
## Existing Code Insights

### Reusable Assets
- Laravel Notifiable trait already in use (User model uses `Notifiable`)
- Existing Notification classes can extend `Illuminate\Notifications\Notification`
- Inertia.js available for frontend communication

### Established Patterns
- Role-based authorization using policies
- Database migrations in `database/migrations/`
- Svelte components in `resources/js/Components/`

### Integration Points
- User model for notification recipients
- Existing controller methods for triggering events
- Layout component for bell icon placement

</codebase_context>

<specifics>
## Specific Ideas

No specific references — open to standard implementation approaches.

</specifics>

<deferred>
## Deferred Ideas

- Toast notifications — Moved to separate future phase

</deferred>

---

*Phase: 05-notification-system*
*Context gathered: 2026-04-13*
