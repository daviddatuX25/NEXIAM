# Change Proposal: Notifications + Delivery Outbox

**Change ID:** `notifications-delivery-outbox`
**Status:** ACTIVE
**Phase:** E2 connected admission-to-release slice (post-grading)
**Depends on:** `grading-workflow` PASS (release prerequisite), `grading-roster-eligibility` PASS (attendance/submission gate), `exam-proctor-duty-assign` PASS (proctor recipients), `applicant-choose-slot` PASS (applicant portal user principal), E2 connected-slice scaffold PASS
**Archived prior:** `openspec/changes/archive/2026-08-11-grading-roster-eligibility/`

## Intent

Deliver the **durable, actor-scoped notification system** with an authoritative after-commit delivery outbox (REQ-VS-013 notification wording, GF-22 §5.5 notifications center + §6 toast contract, GF-19 §3.7 `notification_deliveries`, GF-07 FAIL-030 stale projection rule). Replace the E2 placeholder `notification_outbox` scaffold (release/publish/submit rows that only mark `sent`) with a real outbox: versioned template content, per-recipient delivery rows, a pending→delivered state machine, in-app + Mailpit mail adapters, staff/applicant bells, truthful typed toasts, and read-state controls.

## Disposition from legacy (authority: `legacy_codebase/application/app/Notifications/*`, `app/Http/Controllers/NotificationController.php`, `app/Http/Controllers/Portal/PortalNotificationController.php`, `app/Console/Commands/SendExamReminders.php`)

| Legacy | Disposition |
|---|---|
| 11 notification classes (`ExamSessionPublished`, `ResultReleased`, `ApplicationStatusChanged`, …) with `via()` = `mail`+`database` | **REWRITE** — typed versioned event intents (`{domain}.{event}.v1`) → `notification_deliveries` rows; content rendered from versioned template snapshots |
| `NotificationController` index/markAsRead/markAllAsRead (Laravel `DatabaseNotification`) | **REWRITE** — own-data-scoped projection over `notification_deliveries`, GF-21 capability gate, idempotent mark-read, visible failure feedback |
| SendExamReminders scheduled command (3/1-day before) | **REWRITE as follow-on** — reminder cron lands with the scheduler hardening cut; this cut defines the outbox + manual/event-driven emission |
| Legacy toasts claiming success from flash-only / double-sends | **REWRITE** — GF-22 §6.2 truthful wording; "queued" vs "delivered" never conflated |
| Legacy `notification_outbox` scaffold (`status=sent` no delivery) | **REJECT/SUPERSEDE** — replaced by `notification_deliveries` with real delivery state machine |

## Founder locks (apply)

1. **After-commit, no side-channel writes** — delivery rows are written **inside** the domain transaction; the worker job is dispatched after commit (`queue.after_commit=true` already set). A toast never claims provider delivery without the authoritative delivery row (GF-22 §6.2, master §6.5).
2. **One notifications core** — controllers, Nexi skills, and scheduled reminders all emit through the same outbox writer; no second notification path.
3. **Own-data scope (GF-21)** — the bell list and mark-read only ever address `notification_deliveries` where `recipient_type=App\Models\User` + `recipient_id=auth id`; applicants see only their own user's rows (FAIL-030).
4. **Versioned content** — each delivery freezes `template_version` + payload at dispatch; templates are append-only (`v1`, `v2`, …), never mutated in place.
5. **Thin controllers** — Form Requests own validation, Actions own invariants, controllers delegate (§3.8).
6. **Developer-ready demo** — seeded unread/read deliveries for staff + applicant logins with `HANDOFF` recreate steps (§3.7).

## Out of scope (explicit)

- Scheduled exam-reminder cron (follow-on hardening cut; the `SendExamReminders` behavior is dispositioned but not shipped here).
- WebSockets/Reverb — HTTP polling via Inertia `usePoll` this cut (legacy-stable; ADR parked).
- Inbox page beyond the bell dropdown (a full notifications page is a follow-on; the bell list is the E2 surface).
- Notification preferences per user (follow-on).
- Nexi-triggered emission wiring (follow-on; the outbox writer is the shared seam it will use).
