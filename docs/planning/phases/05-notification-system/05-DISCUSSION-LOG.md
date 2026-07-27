# Phase 5: Notification System - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-13
**Phase:** 05-notification-system
**Areas discussed:** Storage, UI Location, Delivery Mode, Trigger Events

---

## Storage

| Option | Description | Selected |
|--------|-------------|----------|
| Database table | Persisted in database, works across devices/sessions, enables history | ✓ |
| Session | Session-only, no persistence across page reloads | |

**User's choice:** Database table
**Notes:** Preferred for cross-device support and history

---

## UI Location

| Option | Description | Selected |
|--------|-------------|----------|
| Bell icon with dropdown | Shows count badge on bell icon, opens dropdown panel with notification list | ✓ |
| Floating messages | Messages appear as floating cards in corner of screen | |
| Both | Use both - bell dropdown + floating toasts for different notification types | |

**User's choice:** Bell icon with dropdown

---

## Delivery Mode

| Option | Description | Selected |
|--------|-------------|----------|
| Poll-based (every 30-60s) | Check server periodically (simpler, works everywhere) | ✓ |
| Real-time (WebSocket) | Instant push via WebSocket (more complex setup) | |

**User's choice:** Poll-based (every 30-60s)

---

## Trigger Events

| Option | Description | Selected |
|--------|-------------|----------|
| Application status changes | When application status changes (pending→approved, etc.) | ✓ |
| Grading results ready | When grading results are available | ✓ |
| Scheduling changes | When exam session is scheduled/changed/cancelled | ✓ |
| Exam reminders | When there's an upcoming exam reminder | ✓ |

**User's choice:** All four events selected

---

## Claude's Discretion

- Notification list sorting (newest first vs oldest first)
- Individual notification read/unread behavior
- Notification actions (mark all read, clear all)

## Deferred Ideas

- Toast notifications — deferred to separate future phase
