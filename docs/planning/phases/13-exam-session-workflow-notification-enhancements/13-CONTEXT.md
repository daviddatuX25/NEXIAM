# Phase 13: Exam Session Workflow & Notification Enhancements - Context

**Gathered:** 2026-04-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Overhaul exam session lifecycle (publish -> in-progress -> complete one-way flow with unpublish escape hatch), enhance notification system (status change alerts for all roles, lighter context-aware sound, larger mobile notification UI), fix email handling (acceptance/rejection + exam publish/cancel only; reminders in-app only; reminders configurable via env), and redesign My Sessions page with distinct proctor/test_admin views, policy-based auth, and proper role separation where admin/registrar control stops at publish.

</domain>

<decisions>
## Implementation Decisions

### Workflow transitions
- **D-01:** One-way lifecycle with unpublish escape hatch: draft -> published -> in_progress -> completed. Allow unpublish (published -> draft) for pre-exam-day corrections. Cancel stays (in_progress -> cancelled). Reopen stays (completed -> in_progress) for admin only.
- **D-02:** Role-based transition control: proctor/test_admin can start sessions (published -> in_progress). test_admin/admin can close sessions (in_progress -> completed). Reopen stays admin/registrar only.
- **D-03:** Manual "Start session" button on roster page. Must pass isWithinStartWindow check before allowing start. No auto-start.
- **D-04:** Manual "Close session" button with validation: warns if present applicants lack submissions, override option available. No auto-complete.
- **D-05:** Cancel transition (in_progress -> cancelled) stays. Draft sessions can also be deleted (existing destroy action).

### Control transfer after publish
- **D-06:** Admin/registrar control ends at publish. Once published, attendance, submissions, start/close, and monitoring actions belong to proctor/test_admin via My Sessions. Admin/registrar can only unpublish (pre-exam) or reopen completed sessions — they should not have attendance/submission controls.

### Notification delivery
- **D-07:** All status transitions trigger in-app notifications: publish, start (in_progress), complete, cancel, reopen.
- **D-08:** Role-filtered recipients: Applicants get notified on publish and cancel only. Assigned proctors and test_admins get notified on ALL status changes. Admins/super_admins do NOT receive notifications (they trigger the changes).
- **D-09:** Context-aware sound system: two-tier — softer/shorter chime for background poll notifications (session status change), louder chime for direct user actions (publish, accept, reject). The current single chime (800Hz->400Hz sweep at 0.3s) should be lightened overall.
- **D-10:** Mobile notification UI: make the notification dropdown area larger on mobile screens. Keep the existing dropdown pattern but increase sizing on small viewports.

### Email scope
- **D-11:** Email only for: acceptance/rejection (ApplicationStatusChanged) and exam publish/cancel. All other notifications are in-app only (database channel only).
- **D-12:** Exam reminders (1/3/7 day) are in-app only, no email. Currently ExamSessionReminder sends via ['mail', 'database'] — change to ['database'] only.
- **D-13:** Reminder windows are configurable via env variables (EXAM_REMINDER_DAYS=1,3,7). If no env is set, default to 1,3,7 days.

### My Sessions redesign
- **D-14:** Distinct pages: separate route for proctor (proctor/my-sessions) and test_admin (admin/test-admin/sessions). Proctor sees only assigned sessions. Test_admin sees sessions they're proctor on, plus all sessions if they have admin roles.
- **D-15:** Session list includes: status badges (color-coded), date grouping (Today, Upcoming, Past sections), quick action buttons (Start, Close, View Roster), active session highlight (in-progress sessions stand out visually).
- **D-16:** Policy-based authorization: proctor My Sessions uses ExamSessionPolicy.viewRoster (checks assignment). Test_admin My Sessions uses ExamSessionPolicy.manageRoster. No more hardcoded role checks in controllers.

### Claude's Discretion
- Exact sound frequency/duration values for context-aware chimes
- Date grouping thresholds (Today = today, Upcoming = next 7 days, Past = older)
- Active session highlight styling details
- Pagination vs infinite scroll for My Sessions
- Exact env variable names for reminder configuration

</decisions>

<specifics>
## Specific Ideas

- "Admin/registrar should have no control for attendance — once published, all control goes to exam monitoring (My Sessions)"
- Current chime (800Hz->400Hz at 0.3s, gain 0.2-0.3) noted as too loud — needs to be lighter
- Notification dropdown is "very small on mobile" — user wants it larger
- Reminder days should be env-configurable for demo environments

</specifics>

<canonical_refs>
## Canonical References

### Exam session model and workflow
- `app/Models/ExamSession.php` — Status constants, relationships, isWithinStartWindow(), isWithinExamWindow()
- `app/Http/Controllers/Admin/ExamSessionController.php` — Current publish/unpublish/cancel/reopen actions, status transitions
- `app/Policies/ExamSessionPolicy.php` — Current authorization rules for exam session actions

### Notification system
- `app/Notifications/ExamSessionPublished.php` — Current publish notification (sends mail + database)
- `app/Notifications/ExamSessionReminder.php` — Current reminder notification (sends mail + database)
- `app/Notifications/ApplicationStatusChanged.php` — Acceptance/rejection notification (sends mail + database)
- `app/Notifications/ResultReleased.php` — Result notification (sends mail + database)
- `app/Console/Commands/SendExamReminders.php` — Scheduled reminder artisan command
- `app/Http/Controllers/NotificationController.php` — Admin/staff notification API
- `app/Http/Controllers/Portal/NotificationController.php` — Applicant notification API
- `resources/js/Components/NotificationDropdown.svelte` — Frontend notification dropdown with 45s polling
- `resources/js/lib/notification-sound.js` — Web Audio API sound utility
- `resources/js/lib/toast.js` — Toast notification store with sound

### My Sessions pages
- `resources/js/Pages/Admin/TestAdmin/Index.svelte` — Current test_admin sessions page
- `resources/js/Pages/Proctor/SessionRoster.svelte` — Proctor roster page
- `resources/js/Components/SessionRoster.svelte` — Shared roster component

### Routes and scheduling
- `routes/web.php` — All exam session routes, notification routes
- `routes/console.php` — Scheduled reminder commands (1-day and 3-day)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- ExamSession model: Already has status constants, isWithinStartWindow(), isWithinExamWindow(), isPastEndTime() — these are useful for the new start/close button logic
- ExamSessionPolicy: Already has viewRoster and manageRoster methods — can be extended for new transition actions
- NotificationDropdown component: Already polls every 45s, shows toasts for new notifications, has markAsRead/markAllAsRead — can be enhanced with context-aware sound
- toast.js: Already has success/error/info/silent functions with sound — can be extended with two-tier sound
- notification-sound.js: Standalone sound utility with playSound(type) — can be enhanced for context-aware sound
- ExamSessionPublished and ExamSessionReminder notifications: Already queued and structured — can be extended with new notification types

### Established Patterns
- Notification pattern: Laravel Notification classes with via() returning channels, toMail() and toArray()
- Toast pattern: Svelte store-based toast with sound on success/error/info
- Policy pattern: ExamSessionPolicy with role-based checks, can be extended for new actions (start, complete)
- Inertia rendering: Server-side data passed via Inertia::render(), frontend components receive as props
- Status badge pattern: Already exists in TestAdmin/Index.svelte with statusVariant() mapping

### Integration Points
- ExamSessionController: publish(), unpublish(), cancel(), reopen() — need new start() and complete() actions
- ExamSessionPolicy: Need new start(), complete() policy methods
- SendExamReminders command: Needs to read env config for reminder days
- NotificationDropdown.svelte: Needs mobile sizing improvements
- toast.js / notification-sound.js: Need context-aware sound tiers
- My Sessions routes: Need new proctor-specific route, enhanced test_admin route with proper auth

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 13-exam-session-workflow-notification-enhancements*
*Context gathered: 2026-04-19*