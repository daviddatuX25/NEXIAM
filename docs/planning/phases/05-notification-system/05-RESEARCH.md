# Phase 5: Notification System - Research

**Researched:** 2026-04-13
**Domain:** Laravel Notification System + Inertia.js Svelte Frontend
**Confidence:** HIGH

## Summary

This phase implements an in-app notification system for SecureCAT-v2. The codebase already has Laravel notifications set up (User and Applicant models use `Notifiable` trait), with existing Notification classes (`ExamSessionPublished`, `ResultReleased`) that store to database via the `database` channel. The migration for Laravel's notifications table already exists. The primary work involves creating the UI (bell icon with dropdown) and implementing poll-based delivery (30-60s interval).

**Primary recommendation:** Extend existing Laravel notification infrastructure with a Svelte notification dropdown component in AuthenticatedLayout, using Inertia's `usePoll` hook for 45-second polling intervals.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Database table — Notifications persisted in database
- **D-02:** Bell icon with dropdown — Header shows bell icon with unread count badge
- **D-03:** Poll-based (30-60s interval) — Simple polling approach
- **D-04:** Application status changes notification
- **D-05:** Grading results ready notification
- **D-06:** Scheduling changes notification
- **D-07:** Exam reminders notification

### Claude's Discretion
- Notification list sorting (newest first vs oldest first)
- Individual notification read/unread behavior
- Notification actions (mark all read, clear all)

### Deferred Ideas (OUT OF SCOPE)
- Toast notifications — Moved to separate future phase
</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Notifications | Built-in (v12) | Database notification channel | Native Laravel feature, stores in `notifications` table |
| Inertia.js Svelte | v2 | Frontend framework | Project uses Inertia v2 with Svelte |
| lucide-svelte | Latest | Icons | Already in project, provides Bell icon |
| Svelte 5 | v5 | Component framework | Project uses Svelte 5 with runes |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@inertiajs/svelte` usePoll | Built-in | Poll-based notification fetching | For D-03 polling implementation |
| DatabaseNotification facade | Laravel | Access user's notifications | Retrieving unread notifications |

**Installation:**
No new packages needed — Laravel notifications and Inertia Svelte already in use.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/
│   └── Controllers/
│       └── NotificationController.php  # API for fetching/marking notifications
├── Notifications/
│   ├── ExamSessionPublished.php       # Existing
│   ├── ResultReleased.php              # Existing
│   └── ApplicationStatusChanged.php    # NEW - D-04
├── Models/
│   └── User.php                        # Already Notifiable
resources/js/
├── Layouts/
│   └── AuthenticatedLayout.svelte     # Add bell icon here
└── Components/
    └── NotificationDropdown.svelte     # NEW - notification list component
routes/
└── web.php                             # Add notification routes
```

### Pattern 1: Laravel Notification Database Channel
**What:** Use Laravel's built-in database notification channel with `DatabaseNotification` model
**When to use:** For D-01 (database storage) — already implemented
**Example:**
```php
// Existing: app/Notifications/ExamSessionPublished.php
public function via(object $notifiable): array
{
    return ['mail', 'database']; // database channel stores in notifications table
}

public function toArray(object $notifiable): array
{
    return [
        'type'       => 'exam_session_published',
        'session_id' => $this->session->id,
        'message'    => 'Your exam session has been scheduled.',
    ];
}
```

### Pattern 2: Bell Icon with Dropdown
**What:** Header bell icon with badge showing unread count, opens dropdown panel
**When to use:** For D-02 (UI Location) — implement in AuthenticatedLayout
**Source:** [VERIFIED: Existing AuthenticatedLayout.svelte — bell icon exists at line 326-329 with placeholder red dot]
**Example:**
```svelte
<script>
import { Bell } from 'lucide-svelte';
import { usePoll } from '@inertiajs/svelte';

let notifications = $state([]);
let unreadCount = $derived(notifications.filter(n => !n.read_at).length);
let dropdownOpen = $state(false);

// Poll every 45 seconds (within D-03's 30-60s range)
usePoll(45000, { only: ['notifications'] });

function toggleDropdown() {
    dropdownOpen = !dropdownOpen;
}
</script>

<button type="button" class="relative p-2 rounded-full hover:bg-muted" onclick={toggleDropdown}>
    <Bell class="w-5 h-5" />
    {#if unreadCount > 0}
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
    {/if}
</button>
```

### Pattern 3: Notification Controller API
**What:** REST endpoints for fetching notifications and marking as read
**When to use:** For D-03 (poll-based delivery) — provides JSON endpoint
**Example:**
```php
// app/Http/Controllers/NotificationController.php
public function index(Request $request)
{
    $notifications = $request->user()
        ->notifications()
        ->latest()
        ->limit(20)
        ->get();

    return response()->json([
        'notifications' => $notifications,
        'unread_count' => $notifications->where('read_at', null)->count(),
    ]);
}

public function markAsRead(Request $request, string $id)
{
    $notification = $request->user()->notifications()->findOrFail($id);
    $notification->markAsRead();

    return response()->json(['success' => true]);
}
```

### Pattern 4: Trigger Notifications from Application Status Changes
**What:** Send notification when application status changes
**When to use:** For D-04 (application status changes)
**Example:**
```php
// In ApplicationController or Application model
$application->applicant->notify(new ApplicationStatusChanged($application, $oldStatus, $newStatus));
```

## Common Pitfalls

### Pitfall 1: Missing Notification Routes
**What goes wrong:** Poll requests fail with 404 because no API routes exist
**Why it happens:** Laravel notifications database channel stores data but doesn't auto-create routes
**How to avoid:** Explicitly create notification API routes in `routes/web.php`
**Warning signs:** Network tab shows 404 on poll requests

### Pitfall 2: Missing unread count badge
**What goes wrong:** User doesn't know new notifications exist
**Why it happens:** Forgetting to calculate and display unread count
**How to avoid:** Use derived state to compute `unreadCount = notifications.filter(n => !n.read_at).length`
**Warning signs:** Users report missing notifications

### Pitfall 3: Polling too frequently
**What goes wrong:** Unnecessary server load, network congestion
**Why it happens:** Setting poll interval too low (e.g., 5 seconds)
**How to avoid:** Use 30-60 second interval per D-03, consider `keepAlive: false` for background throttling
**Warning signs:** High server CPU, slow response times

### Pitfall 4: Not handling empty notification state
**What goes wrong:** Dropdown shows blank or error when no notifications exist
**Why it happens:** Missing conditional rendering for empty state
**How to avoid:** Add `{#if notifications.length === 0}` empty state UI
**Warning signs:** Console errors, confusing UI

## Code Examples

### Trigger Event: Application Status Change (D-04)
```php
// app/Notifications/ApplicationStatusChanged.php
<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification
{
    public function __construct(
        public readonly Application $application,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_status_changed',
            'application_id' => $this->application->id,
            'message' => "Your application status changed from {$this->oldStatus} to {$this->newStatus}",
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }
}
```

### Svelte Poll Implementation (D-03)
```svelte
<script>
import { usePoll } from '@inertiajs/svelte';

export let initialNotifications = [];

let notifications = $state(initialNotifications);

// 45-second polling interval (within D-03's 30-60s range)
const { start, stop } = usePoll(45000, {
    only: ['notifications'],
    onSuccess: (page) => {
        notifications = page.props.notifications ?? [];
    }
});
</script>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Email-only notifications | Database + email channels | 2026-02-18 migration | Users see in-app notifications alongside email |
| Manual notification tracking | Laravel Notification facade | Existing notifications in codebase | Standardized notification pattern |

**Deprecated/outdated:**
- None for this phase — Laravel notifications are current standard

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | User model already has Notifiable trait | Codebase Context | LOW — verified by grep showing `use Notifiable` in User.php |
| A2 | Notifications table migration exists | Codebase Context | LOW — verified by reading migration file |
| A3 | Bell icon exists in AuthenticatedLayout | Codebase Context | LOW — verified by grep finding Bell icon at line 326-329 |

**If this table is empty:** All claims in this research were verified or cited — no user confirmation needed.

## Open Questions

1. **Notification sorting preference**
   - What we know: CONTEXT.md lists "newest first vs oldest first" as Claude's discretion
   - What's unclear: No explicit preference stated
   - Recommendation: Default to newest first (DESC by created_at), most common UX pattern

2. **Mark as read behavior**
   - What we know: Clicking a notification should mark it read
   - What's unclear: Should clicking navigate to related page (e.g., application detail)?
   - Recommendation: Clicking notification marks as read AND navigates to relevant detail page

3. **Application status change triggers**
   - What we know: Need to identify where application status changes happen
   - What's unclear: Which controllers/models handle status changes?
   - Recommendation: Search ApplicationController and Application model for status updates

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Laravel 12 | Backend | ✓ | ^12.0 | — |
| Inertia.js Svelte v2 | Frontend | ✓ | v2 | — |
| Svelte 5 | Frontend | ✓ | v5 | — |
| lucide-svelte | Icons | ✓ | Latest | — |
| MySQL | Database | ✓ | (project default) | — |

**Step 2.6: SKIPPED (no external dependencies identified)** — All required tools are already part of the project.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit v11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --filter=NotificationTest` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| D-01 | Database storage | Integration | `php artisan test --filter=NotificationDatabaseTest` | ❌ Wave 0 |
| D-02 | Bell icon + dropdown | Component | Manual browser test | ❌ Wave 0 |
| D-03 | Poll-based delivery | Integration | `php artisan test --filter=NotificationPollTest` | ❌ Wave 0 |
| D-04 | Application status triggers | Unit | `php artisan test --filter=ApplicationStatusNotificationTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=NotificationTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/NotificationSystemTest.php` — covers D-01, D-03, D-04
- [ ] `tests/Unit/Notifications/ApplicationStatusChangedTest.php` — covers D-04
- [ ] Framework install: Already installed via composer

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|------------------|
| V2 Authentication | Yes | Laravel middleware (auth) on notification routes |
| V4 Access Control | Yes | Policy-based authorization — user can only access own notifications |
| V5 Input Validation | Yes | Route model binding + explicit validation |

### Known Threat Patterns for Laravel Notifications

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Access other users' notifications | Tampering | Policy check: `NotificationPolicy@view` validates ownership |
| SQL injection in notification query | Tampering | Eloquent ORM prevents injection by default |
| XSS in notification message | Elevation | Data from `toArray()` should be escaped in Svelte templates |

## Sources

### Primary (HIGH confidence)
- [VERIFIED: Existing Notification classes] - `app/Notifications/ExamSessionPublished.php`, `app/Notifications/ResultReleased.php`
- [VERIFIED: Notifiable trait in User model] - grep result showing `use Notifiable` in `app/Models/User.php`
- [VERIFIED: Notification migration] - `database/migrations/2026_02_18_135250_create_notifications_table.php`
- [VERIFIED: Bell icon in AuthenticatedLayout] - Line 326-329 of `resources/js/Layouts/AuthenticatedLayout.svelte`
- [CITED: Inertia.js Svelte polling documentation] - `search-docs` tool for `usePoll` hook

### Secondary (MEDIUM confidence)
- [WebSearch verified with official source] - Laravel notification best practices

### Tertiary (LOW confidence)
- None required

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — All packages already in use in codebase
- Architecture: HIGH — Follows existing Laravel/Inertia patterns in project
- Pitfalls: MEDIUM — Identified common patterns, minor risk of edge cases

**Research date:** 2026-04-13
**Valid until:** 2026-05-13 (30 days — Laravel/Inertia stable)