# Phase 2: Toast Notification System - Context

**Gathered:** 2026-04-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Toast notification system with smooth sound for SecureCAT-v2 exam management system. Brief, auto-dismissing notifications that appear on screen with optional sound for key events.

</domain>

<decisions>
## Implementation Decisions

### Library
- **D-01:** svelte-french-toast — Lightweight toast library with animations and auto-dismiss

### Position
- **D-02:** Top-right — Standard toast position, doesn't block main content

### Behavior
- **D-03:** Auto-dismiss — 3-5 seconds with progress bar showing time remaining

### Sound
- **D-04:** Subtle chime — Soft, professional notification sound

### Trigger Events
- **D-05:** All notification events from Phase E5:
  - Application status changes (pending→approved, etc.)
  - Grading results ready
  - Exam session scheduled/changed/cancelled
  - Proctor assignments
  - Room changes
  - Session rosters
  - Exam reminders

### Integration
- **D-06:** Extend existing notification events to also trigger toast
- **D-07:** One-way: Bell dropdown shows history, toast shows live events

</decisions>

<canonical_refs>
## Canonical References

- svelte-french-toast: https://github.com/kbrgl/svelte-french-toast
- npm: https://www.npmjs.com/package/svelte-french-toast

</canonical_refs>

<codebase_context>
## Existing Code Insights

### Reusable Assets
- NotificationDropdown.svelte already exists
- Polling mechanism already implemented (45s interval)
- Layout components in place for bell icon

### Established Patterns
- Svelte 5 with $state() runes
- Inertia.js for frontend communication
- Tailwind CSS for styling

### Integration Points
- NotificationDropdown component
- AuthenticatedLayout or HomeLayout
- Existing notification events in controllers

</codebase_context>

<specifics>
## Specific Ideas

- Toast appears on same events that create in-app notifications
- Sound plays alongside toast display
- Visual toast with progress bar indicates time remaining
- User can click to dismiss early

</specifics>

<deferred>
## Deferred Ideas

- None — this phase completes the notification feature set

</deferred>

---

*Phase: 02-toast-notification-system-with-smooth-sound*
*Context gathered: 2026-04-13*