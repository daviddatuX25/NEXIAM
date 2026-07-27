---
phase: 02-toast-notification-system-with-smooth-sound
plan: 02-03
subsystem: Toast Notification System
tags: [toast, notifications, polling, D-05, D-06, D-07]
dependency_graph:
  requires:
    - 02-02
  provides:
    - Toast triggered on new notification arrival
  affects:
    - NotificationDropdown.svelte
tech_stack:
  added:
    - usePoll for polling notifications
  patterns:
    - Svelte 5 runes ($state, $derived)
    - Set-based ID tracking for new notification detection
key_files:
  modified:
    - resources/js/Components/NotificationDropdown.svelte
decisions:
  - Used Set-based ID tracking to detect new notifications (more reliable than count-based)
  - 45s polling interval within D-03's 30-60s range
  - 5s toast cooldown to prevent notification spam
  - Toast type based on notification type: success for application_status/result, message for exam_session
---

# Phase 02 Plan 03: NotificationDropdown Toast Integration

## Summary

Extended NotificationDropdown polling to trigger toast notifications when new unread notifications arrive via 45-second polling. The bell dropdown shows notification history, while toast shows live events (D-07).

## Completed Tasks

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Track previous notification count and trigger toast | 81341d3 | resources/js/Components/NotificationDropdown.svelte |

## Implementation Details

**NotificationDropdown.svelte changes:**

1. **State tracking for new notifications:**
   - `previousNotificationIds` - Set to track previously seen notification IDs
   - `previousCount` - Track previous count for reference
   - `lastToastTime` - Track last toast time for debouncing
   - `TOAST_COOLDOWN` - 5000ms (5 seconds) between toasts

2. **Toast triggering logic in usePoll onSuccess:**
   - Get current notification IDs as a Set
   - Filter for NEW notifications (not in previous set AND unread)
   - Check edge cases: dropdown open, debounce cooldown
   - Show toast based on notification type:
     - `application_status` → `ToastManager.success()`
     - `exam_session` → `ToastManager.message()`
     - `result` → `ToastManager.success()`
     - Other → `ToastManager.message()`

3. **Edge cases handled:**
   - Dropdown open: Toast only shows when NOT open (user is looking at dropdown)
   - Debouncing: Max 1 toast per 5 seconds
   - New detection: Uses Set-based ID comparison (more reliable than count)

## Requirements Fulfilled

| Requirement | Status | Notes |
|-------------|--------|-------|
| D-05 | Done | Toast triggers on application status, exam session, result events |
| D-06 | Done | Extends polling to trigger toast on new notifications |
| D-07 | Done | Bell dropdown shows history, toast shows live events |

## Deviation Documentation

None - plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- ToastManager imported and used in NotificationDropdown
- previousNotificationIds state tracks notification IDs
- TOAST_COOLDOWN constant set to 5000ms
- dropdownOpen check prevents toast when user is viewing dropdown
- Toast type determined by notification.type field
- Commit 81341d3 exists in git history
