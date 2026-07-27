---
phase: 13-exam-session-workflow-notification-enhancements
reviewed: 2026-04-20T00:00:00Z
depth: standard
files_reviewed: 16
files_reviewed_list:
  - app/Http/Controllers/Admin/ExamSessionController.php
  - app/Policies/ExamSessionPolicy.php
  - app/Notifications/ExamSessionStarted.php
  - app/Notifications/ExamSessionCompleted.php
  - app/Notifications/ExamSessionCancelled.php
  - app/Notifications/ExamSessionReminder.php
  - app/Console/Commands/SendExamReminders.php
  - routes/web.php
  - resources/js/lib/notification-sound.js
  - resources/js/lib/toast.js
  - resources/js/Components/NotificationDropdown.svelte
  - app/Http/Controllers/Proctor/ProctorSessionController.php
  - resources/js/Pages/Proctor/MySessions.svelte
  - resources/js/Pages/Admin/TestAdmin/Index.svelte
  - app/Http/Controllers/Proctor/SessionRosterController.php
  - tests/Feature/ExamSessionWorkflowTest.php
findings:
  critical: 0
  warning: 3
  info: 6
  total: 9
status: issues_found
fixed:
  - CR-01: URL paths fixed (exam-scheduling route prefix)
---

# Phase 13: Code Review Report

**Reviewed:** 2026-04-20
**Depth:** standard
**Files Reviewed:** 16
**Status:** issues_found

## Summary

Reviewed the exam session workflow and notification system across Laravel controllers, policies, notifications, Svelte components, and tests. The implementation is generally sound with good state machine discipline and policy-based authorization. One critical bug was found: the TestAdmin Index Svelte component uses incorrect URL paths that would cause 404 errors. Three warnings flag logic inconsistencies and potential edge cases.

## Critical Issues

### CR-01: Wrong URL paths in TestAdmin/Index.svelte break Start and Close buttons

**File:** `resources/js/Pages/Admin/TestAdmin/Index.svelte:68` and `resources/js/Pages/Admin/TestAdmin/Index.svelte:84`

**Issue:** The `startSession()` and `confirmClose()` functions POST to `/admin/exam-sessions/{id}/start` and `/admin/exam-sessions/{id}/complete`. However, the actual routes defined in `web.php` are:
- `admin.exam-scheduling.start` at `/admin/exam-scheduling/{exam_session}/start`
- `admin.exam-scheduling.complete` at `/admin/exam-scheduling/{exam_session}/complete`

The buttons on the Test Admin session index page would trigger 404 errors, making the Start and Close session actions non-functional.

**Fix:**

```javascript
// Line 68 - change FROM:
router.post(`/admin/exam-sessions/${sessionId}/start`, {}, {

// TO:
router.post(`/admin/exam-scheduling/${sessionId}/start`, {}, {

// Line 84 - change FROM:
router.post(`/admin/exam-sessions/${sessionId}/complete`, {}, {

// TO:
router.post(`/admin/exam-scheduling/${sessionId}/complete`, {}, {
```

Note: Compare with `resources/js/Pages/Proctor/MySessions.svelte` line 68 which correctly uses `/proctor/sessions/${sessionId}/start` matching its route at `proctor.sessions.start`.

## Warnings

### WR-01: bulkAttendance allows 'submitted' as an attendance status value

**File:** `app/Http/Controllers/Proctor/SessionRosterController.php:323`

**Issue:** The validation rule for `bulkAttendance` includes `'submitted'` in the allowed values for the `status` field:

```php
'status' => ['required', Rule::in(['present', 'absent', 'submitted'])],
```

`'submitted'` is a `submission_status` value, not an `attendance_status`. The enum for `attendance_status` (defined in migration `2026_02_18_180000`) is `['pending', 'present', 'absent']`. Allowing `'submitted'` here is semantically incorrect and could corrupt attendance data.

**Fix:** Remove `'submitted'` from the rule:

```php
'status' => ['required', Rule::in(['present', 'absent'])],
```

If bulk submission marking is needed, it should use a separate endpoint.

---

### WR-02: test_administrator can complete any session without assignment check

**File:** `app/Policies/ExamSessionPolicy.php:112-118`

**Issue:** The `complete()` policy method allows `test_administrator` to complete any in-progress session without verifying they are assigned to that session:

```php
public function complete(User $user, ExamSession $examSession): bool
{
    if ($examSession->status !== ExamSession::STATUS_IN_PROGRESS) {
        return false;
    }
    return $user->hasAnyRole(['test_administrator', 'super_admin', 'registrar_administrator']);
}
```

Compare with `manageRoster()` (line 63-73) which also allows `test_administrator` unconditionally. This is inconsistent with `start()` (line 96-108) which checks proctor assignment for proctors. A test_administrator can close any session even if they have no involvement.

This may be intentional for admin override capability, but creates an asymmetry: proctors are checked for assignment but admins bypass this check entirely.

**Fix:** If intentional, add a code comment documenting this override behavior. If not, add an assignment check for test_administrator similar to the proctor check in `start()`.

---

### WR-03: Reopen action sends ExamSessionStarted notification with misleading message

**File:** `app/Http/Controllers/Admin/ExamSessionController.php:519-526`

**Issue:** When reopening a completed session, the code sends `ExamSessionStarted` notification:

```php
Notification::send(
    $recipients->merge($testAdmins)->unique('id'),
    new ExamSessionStarted($exam_session)
);
```

The `toArray()` method in `ExamSessionStarted` returns:
```php
'message' => "The exam session for {$this->session->room?->name} has started.",
```

This message says "has started" which is confusing when reopening a session. The reopen action is semantically different from a fresh start.

**Fix:** Either create a separate `ExamSessionReopened` notification with an appropriate message, or modify the message in `ExamSessionStarted` to handle the reopen context. At minimum, the notification URL correctly points to the roster where proctors can take attendance.

---

## Info

### IN-01: storeSubmission returns JSON 409 for non-JSON requests

**File:** `app/Http/Controllers/Proctor/SessionRosterController.php:145-149`

**Issue:** When session is not in progress, `storeSubmission` returns JSON 409 for both JSON and non-JSON requests, unlike other methods in the same controller that return redirects with flash messages:

```php
if ($session->status !== ExamSession::STATUS_IN_PROGRESS) {
    $message = 'Session must be in progress.';
    return $request->wantsJson() ? response()->json(['message' => $message], 409) : response()->json(['message' => $message], 409);
}
```

This appears to be a copy-paste artifact where the non-JSON branch was not implemented. Other methods in this controller return `back()->with('error', $message)` for non-JSON requests.

**Fix:** Change the non-JSON branch to:
```php
return back()->with('error', $message);
```

---

### IN-02: ExamSessionController cancel() lacks explicit authorize() call

**File:** `app/Http/Controllers/Admin/ExamSessionController.php:420`

**Issue:** The `cancel()` method does not call `authorize()` unlike `start()`, `complete()`, and `reopen()`. It relies solely on route middleware (`role:super_admin,registrar_administrator`) for authorization. While this may be intentional since the route is already middleware-protected, it is inconsistent with the other transition methods which double-check via policy.

---

### IN-03: Duplicate exam reminders possible

**File:** `app/Console/Commands/SendExamReminders.php:38-43`

**Issue:** The command has no deduplication check. If run multiple times (e.g., cron overlap, manual re-run), the same applicant could receive multiple notifications for the same session and same day count. There is no check for "already notified for this session + day".

**Fix:** Consider adding a `notified_for_session` flag or tracking table to prevent duplicate reminders.

---

### IN-04: Test coverage gaps

**File:** `tests/Feature/ExamSessionWorkflowTest.php`

**Issue:** The test file covers:
- Proctor can start published session within window (pass)
- Proctor cannot start outside window without override (pass)
- Test admin can start outside window (pass)
- Test admin can close in-progress session (pass)
- Notification dispatch for start and close (pass)
- Reminder is database-only (pass)

Missing coverage for:
- `cancel()` workflow transition
- `reopen()` workflow transition  
- `unpublish()` workflow transition
- Authorization: unassigned user cannot start/complete a session
- `removeApplicant` functionality
- Start/complete notifications include correct recipients (test_admins when proctor starts, etc.)

---

### IN-05: Close confirmation dialog does not check actual submission state

**File:** `resources/js/Pages/Admin/TestAdmin/Index.svelte:366-368`

**Issue:** The confirmation dialog always shows "Some applicants have not submitted yet" regardless of actual submission state:

```svelte
<p class="text-sm text-muted-foreground mb-4">
  Some applicants have not submitted yet. Close anyway?
</p>
```

This message is static and does not reflect whether any applicants actually have pending submissions.

---

### IN-06: Notification URLs point to proctor route for all recipients

**File:** `app/Notifications/ExamSessionStarted.php:29` and `app/Notifications/ExamSessionCompleted.php:29`

**Issue:** Both notifications use `route('proctor.sessions.show', $this->session)` which goes to `/proctor/sessions/{id}`. Recipients include test_administrators and super_admins who may not have the proctor dashboard accessible. A super_admin clicking this link would be routed to the proctor view.

**Fix:** Either use a neutral route like `admin.exam-scheduling.show` or conditionally select the URL based on the notifiable's role.

---

## Positive Findings

- **State machine integrity:** Good discipline in checking current status before transitions. `publish()` checks `STATUS_IN_PROGRESS` and `STATUS_CANCELLED`, `start()` requires `STATUS_PUBLISHED`, `complete()` requires `STATUS_IN_PROGRESS`, `cancel()` requires `STATUS_IN_PROGRESS`.

- **Policy-based authorization:** ExamSessionPolicy properly gates all transitions with role checks and assignment checks for proctors. Server-computed `can_start`/`can_complete` flags passed to Svelte components prevent UI-level unauthorized actions.

- **Notification channel discipline:** `ExamSessionReminder` correctly uses `['database']` only (mail handled separately in `toMail()`). `ExamSessionCancelled` appropriately sends to both `mail` and `database` since cancellation requires active notification.

- **SendExamReminders command:** Properly uses `EXAM_REMINDER_DAYS` env var with sensible defaults, handles the `--days` override option correctly, and uses eager loading (`with('applicants')`) to avoid N+1.

- **Proctor/MySessions.svelte:** Correctly uses server-computed `can_start` + client-side `status` check, and uses the correct route URLs (`/proctor/sessions/{id}/start`).

- **AudioContext singleton:** Good pattern for notification sounds with proper browser autoplay policy handling (resume suspended context).

---

_Reviewed: 2026-04-20_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
