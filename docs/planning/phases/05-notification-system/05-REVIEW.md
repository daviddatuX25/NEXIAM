---
phase: 05-notification-system
reviewed: 2026-04-13T00:00:00Z
depth: standard
files_reviewed: 15
files_reviewed_list:
  - app/Notifications/ApplicationStatusChanged.php
  - app/Policies/NotificationPolicy.php
  - app/Http/Controllers/NotificationController.php
  - routes/web.php
  - resources/js/Components/NotificationDropdown.svelte
  - resources/js/Layouts/AuthenticatedLayout.svelte
  - app/Http/Middleware/HandleInertiaRequests.php
  - app/Notifications/ExamSessionReminder.php
  - app/Console/Commands/SendExamReminders.php
  - app/Http/Controllers/ApplicationController.php
  - routes/console.php
  - tests/Feature/NotificationSystemTest.php
  - tests/Unit/Notifications/ApplicationStatusChangedTest.php
  - tests/Unit/Notifications/ExamSessionReminderTest.php
  - app/Providers/AppServiceProvider.php
findings:
  critical: 0
  warning: 2
  info: 3
  total: 5
status: issues_found
---

# Phase 05: Notification System Review Report

**Reviewed:** 2026-04-13
**Depth:** standard
**Files Reviewed:** 15
**Status:** issues_found

## Summary

The notification system implementation is well-structured with properly queued notifications, authorization policies, and test coverage. Two warnings were identified related to performance/relationship loading, and three informational findings were noted for code quality improvements.

## Warnings

### WR-01: Missing Eager Loading in HandleInertiaRequests Middleware

**File:** `app/Http/Middleware/HandleInertiaRequests.php:56-61`
**Issue:** Notifications are loaded on every page request using `$user->notifications()` without eager loading or caching. This executes a database query on every single page load for authenticated users, which could impact performance at scale.
**Fix:** Consider caching notifications for a short duration (e.g., 30-60 seconds) or loading them only when the notification dropdown is opened via a separate endpoint. Alternatively, implement request-level caching:

```php
// Option: Cache notifications for 30 seconds
$cacheKey = 'notifications:user:'.$user->id;
$notifications = cache()->remember($cacheKey, 30, function () use ($user) {
    return $user->notifications()
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get()
        ->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'message' => $n->data['message'] ?? $n->data['title'] ?? class_basename($n->type),
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);
});
```

### WR-02: Potential Undefined Relationship in SendExamReminders Command

**File:** `app/Console/Commands/SendExamReminders.php:25-30`
**Issue:** The command uses `$session->applicants` relationship without verifying the relationship exists on ExamSession model. If the relationship name differs (e.g., `examinees`, `users`, `assignedApplicants`), this will cause a method not found error.
**Fix:** Verify the exact relationship name in the ExamSession model and ensure it returns the assigned applicants:

```php
// In ExamSession model, verify this relationship exists:
public function applicants()
{
    return $this->belongsToMany(Applicant::class, 'exam_session_applicant')
        ->withTimestamps();
}

// In command, use the correct relationship name:
foreach ($session->applicants as $applicant) {
    // This should work if relationship is correctly named
}
```

## Info

### IN-01: NotificationDropdown Always Included

**File:** `resources/js/Layouts/AuthenticatedLayout.svelte:327`
**Issue:** The NotificationDropdown component is rendered on every page even for users who may not need it. The dropdown is always in the header but notifications are only loaded for `User` instances (not `Applicant`).
**Fix:** Consider conditionally rendering based on user type:

```svelte
{#if user}
  <NotificationDropdown />
{/if}
```

### IN-02: Hardcoded Notification Limit

**File:** `app/Http/Middleware/HandleInertiaRequests.php:59` and `app/Http/Controllers/NotificationController.php:26`
**Issue:** The notification limit of 20 is hardcoded in two places (middleware and controller). This duplication could lead to inconsistency if the limit needs to change.
**Fix:** Extract to a constant or configuration value:

```php
// In config/notifications.php
return [
    'default_limit' => env('NOTIFICATION_DEFAULT_LIMIT', 20),
    'max_limit' => env('NOTIFICATION_MAX_LIMIT', 100),
];

// Then use: config('notifications.default_limit')
```

### IN-03: Missing Null Check in ApplicationStatusChanged Notification

**File:** `app/Notifications/ApplicationStatusChanged.php:28-29`
**Issue:** The `toMail` method accesses `$notifiable->name` without null checking. While the fallback `?? 'Applicant'` handles nulls, the method assumes `$notifiable` is always a User/Applicant object.
**Fix:** Already has fallback handling, but could add explicit type checking for clarity:

```php
public function toMail(object $notifiable): MailMessage
{
    $name = $notifiable instanceof \Illuminate\Database\Eloquent\Model 
        ? ($notifiable->name ?? 'Applicant')
        : 'Applicant';
    
    // ... rest of method
}
```

---

_Reviewed: 2026-04-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_