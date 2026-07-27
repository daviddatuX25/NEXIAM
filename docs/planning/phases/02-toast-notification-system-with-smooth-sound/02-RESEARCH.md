# Phase 2: Toast Notification System - Research

**Researched:** 2026-04-13
**Phase:** 02-toast-notification-system-with-smooth-sound

## Domain Summary

Toast notifications are brief, auto-dismissing feedback messages that appear temporarily on screen to inform users of system events. This phase extends the earlier notification system (Phase E5) which implemented the bell dropdown but deferred toast UI to this phase.

## Technology Options

### Option 1: svelte-french-toast
- **Pros:** Lightweight, customizable, good animations, active maintenance
- **Cons:** Requires npm install, adds dependency
- **Score:** High reputation, 4 code snippets

### Option 2: Custom Svelte toast component
- **Pros:** No dependency, full control, matches existing UI
- **Cons:** More implementation effort
- **Score:** N/A (custom)

### Option 3: Notus.js (newer alternative)
- **Pros:** Modern, Svelte 5 native
- **Cons:** Less mature ecosystem
- **Score:** Medium reputation

## Decision

**D-01: svelte-french-toast** — Lightweight library with proven UX patterns, supports sound, auto-dismiss with progress bar, position options.

## Implementation Notes

From research:
- Install: `npm install svelte-french-toast`
- Mount `<Toaster />` component once in root layout
- API: `toast.success()`, `toast.error()`, `toast.message()` with options
- Duration: `toast(message, { duration: 3000 })` for auto-dismiss
- Sound: Custom audio can be triggered alongside toast

## Trigger Events (User-selected)

All notification events from Phase E5:
- Application status changes
- Grading results ready
- Exam session scheduled/changed/cancelled
- Proctor assignments
- Room changes
- Session rosters

## Integration Points

Existing code:
- NotificationDropdown already polls every 45s
- Can extend same notification events to also trigger toast
- Layout already has bell icon positioned

## External References

- svelte-french-toast: https://github.com/kbrgl/svelte-french-toast
- npm: https://www.npmjs.com/package/svelte-french-toast

---

*Research complete: 2026-04-13*