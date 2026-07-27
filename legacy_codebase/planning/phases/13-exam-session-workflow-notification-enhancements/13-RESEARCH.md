# Phase 13 Research: Exam Session Workflow & Notification Enhancements

**Gathered:** 2026-04-20
**Status:** Complete — all gaps verified against codebase

---

## Phase Summary

Goal: Overhaul exam session lifecycle (one-way flow), enhance notification system (role-filtered, context-aware sound, mobile UI), fix email scope, redesign My Sessions page for proctor/test_admin.

---

## Standard Stack

- **State transitions:** Manual controller methods (publish/unpublish/cancel/reopen already exist) — extend with `start()` and `complete()` on `ExamSessionController`. No external state machine package needed; existing pattern is sufficient for this project's scale.
- **Notifications:** Laravel Notification classes with `database` channel. Use `$notifiable->notify(new Notification())` for individual sends (existing project pattern). Use `Notification::send($users, new Notification())` only for bulk sends to merged collections.
- **Authorization:** `ExamSessionPolicy` — extend with `start()` and `complete()` policy methods.
- **Sound:** Web Audio API (`OscillatorNode`) — already implemented in `notification-sound.js`. Extend for two-tier sound.
- **Frontend date grouping:** Server-side grouping preferred — controller passes pre-grouped data to Inertia, avoids client-side date logic.
- **Scheduled reminders:** Single daily scheduled command reads `EXAM_REMINDER_DAYS` env, queries sessions, sends only for matching windows.

---

## Architecture Patterns

### State Machine (Manual Pattern)
Transitions validated in controller + policy. No package needed.
```php
// ExamSessionController
public function start(ExamSession $session): RedirectResponse
{
    $this->authorize('start', $session);
    abort_unless($session->isWithinStartWindow(), 422, 'Outside start window');
    $session->update(['status' => ExamSession::STATUS_IN_PROGRESS]);
    // dispatch notification
    return back();
}

public function complete(ExamSession $session): RedirectResponse
{
    $this->authorize('complete', $session);
    $session->update(['status' => ExamSession::STATUS_COMPLETED]);
    // dispatch notification
    return back();
}
```

### Role-Filtered Notification Recipients
```php
// Individual sends — existing project pattern (8 usages across 4 controllers)
$applicant->notify(new ExamSessionPublished($exam_session));

// Bulk sends — use Notification::send for merged collections (NEW pattern for Phase 13)
$proctors = $session->proctors; // already eager-loadable via relationship
$testAdmins = User::role('test_administrator')->get();
Notification::send($proctors->merge($testAdmins), new ExamSessionStarted($session));

// For single-role sends, ->notify() is fine and matches existing pattern
$proctor->notify(new ExamSessionStarted($session));
```

### Notification via() Channel Control
```php
public function via(object $notifiable): array
{
    return ['database']; // email only for acceptance/rejection and exam publish/cancel
}
```

### ExamSessionReminder — Remove Mail Channel
```php
// Before
return ['mail', 'database'];
// After
return ['database'];
```

### Env-Configurable Reminder Days
```php
// SendExamReminders command
$days = collect(explode(',', env('EXAM_REMINDER_DAYS', '1,3,7')))
    ->map(fn($d) => (int) trim($d))
    ->filter()
    ->values();
```

### Two-Tier Sound (Web Audio API)
```js
// notification-sound.js — extend existing singleton pattern
// EXISTING: getAudioContext() creates singleton. MISSING: .resume() for suspended contexts.
// FIX: Add resume() call to handle browser auto-suspend.

function getAudioContext() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    // Browsers suspend AudioContext until user gesture — must resume
    if (audioContext.state === 'suspended') {
        audioContext.resume();
    }
    return audioContext;
}

// Two-tier chime: 'background' (softer) vs 'action' (louder)
export function playChime(tier = 'background') {
    if (typeof window === 'undefined') return;
    const ctx = getAudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);

    if (tier === 'background') {
        // Soft: quiet, short — for poll-based background notifications
        osc.frequency.setValueAtTime(600, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(400, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
    } else {
        // Action: louder, slightly longer — for direct user actions
        osc.frequency.setValueAtTime(800, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(400, ctx.currentTime + 0.3);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
    }
}
```

### Server-Side Date Grouping for My Sessions
```php
// In controller
$sessions = ExamSession::with(['room'])->assignedTo($user)->get();

return Inertia::render('Proctor/MySessions', [
    'today'    => $sessions->filter(fn($s) => $s->exam_date->isToday())->values(),
    'upcoming' => $sessions->filter(fn($s) => $s->exam_date->isFuture() && !$s->exam_date->isToday())->values(),
    'past'     => $sessions->filter(fn($s) => $s->exam_date->isPast() && !$s->exam_date->isToday())->values(),
]);
```

### ExamSessionPolicy — Transition Methods
```php
public function start(User $user, ExamSession $session): bool
{
    return in_array($user->role, ['proctor', 'test_administrator'])
        && $session->status === ExamSession::STATUS_PUBLISHED
        && $session->isAssignedProctor($user); // or broader for test_admin
}

public function complete(User $user, ExamSession $session): bool
{
    return in_array($user->role, ['test_administrator', 'admin', 'admin_registrar'])
        && $session->status === ExamSession::STATUS_IN_PROGRESS;
}
```

---

## Don't Hand-Roll

- **Role-filtered recipient queries:** Use Eloquent relationships already on ExamSession (`proctors`, `applicants`) — don't write raw SQL.
- **Date comparison logic:** Use Carbon methods (`isToday()`, `isFuture()`, `isPast()`) not manual timestamp math.
- **Policy authorization:** Use `$this->authorize()` in controller — don't inline role checks.
- **AudioContext management:** Reuse the singleton AudioContext (already in `notification-sound.js`) — don't create one per sound play. Must add `.resume()` for suspended contexts.

---

## Common Pitfalls

1. **AudioContext suspended state:** Browsers suspend AudioContext until user gesture. The existing `notification-sound.js` does NOT handle this — it creates a singleton but never calls `.resume()`. MUST add `if (ctx.state === 'suspended') { ctx.resume(); }` in `getAudioContext()`.
2. **N+1 on notification recipient queries:** Always eager-load `$session->proctors` and `$session->applicants` before sending bulk notifications.
3. **Queued notifications + database channel:** If notifications are queued, they may arrive slightly delayed — this is acceptable for polling-based frontend. Don't switch to sync for performance.
4. **Mobile dropdown overflow:** Fixed-position dropdown on mobile can exceed viewport height. Use `max-h-[80vh] overflow-y-auto` on the dropdown container. TailwindCSS v4 keeps the `sm:`, `md:`, `lg:` prefix pattern (confirmed: project already uses `sm:w-96`).
5. **Inertia redirect after transition:** After `start()`/`complete()`, use `return back()` not `redirect()->route()` to preserve the Inertia context.
6. **Policy `complete()` conflict with PHP:** Don't name a policy method `complete` if it conflicts — check PHP keyword list. It's safe (`complete` is not a PHP keyword).
7. **Env array parsing:** `env('EXAM_REMINDER_DAYS')` returns a string. Always `explode(',', ...)` and cast to int. Handle whitespace.
8. **Test_admin My Sessions scope:** test_admin users can be proctors — their "My Sessions" should include sessions where they are listed as a proctor, not just all sessions. Clarify scope with D-14.

---

## Code Examples

### New Notification Class Pattern (ExamSessionStarted)
```php
class ExamSessionStarted extends Notification implements ShouldQueue
{
    public function __construct(public readonly ExamSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Session Started',
            'body'    => "{$this->session->title} has started.",
            'url'     => route('proctor.my-sessions'),
            'type'    => 'session_started',
            'session' => $this->session->id,
        ];
    }
}
```

### Route Additions (web.php)
```php
// Proctor
Route::get('/proctor/my-sessions', [ProctorSessionController::class, 'index'])->name('proctor.my-sessions');

// Test Admin
Route::get('/admin/test-admin/sessions', [TestAdminSessionController::class, 'index'])->name('admin.test-admin.sessions');

// Transition actions (on ExamSessionController or new dedicated controller)
Route::post('/admin/exam-sessions/{session}/start', [ExamSessionController::class, 'start'])->name('exam-sessions.start');
Route::post('/admin/exam-sessions/{session}/complete', [ExamSessionController::class, 'complete'])->name('exam-sessions.complete');
```

### Mobile Dropdown Sizing (TailwindCSS v4)
```svelte
<!-- NotificationDropdown.svelte -->
<div class="
  absolute right-0 mt-2
  w-80 sm:w-96
  max-h-[80vh] overflow-y-auto
  ...
">
```

---

## Canonical Files (from CONTEXT.md)

| File | Purpose |
|------|---------|
| `app/Models/ExamSession.php` | Status constants, isWithinStartWindow(), isWithinExamWindow() |
| `app/Http/Controllers/Admin/ExamSessionController.php` | publish/unpublish/cancel/reopen — add start/complete |
| `app/Policies/ExamSessionPolicy.php` | Add start(), complete() methods |
| `app/Notifications/ExamSessionPublished.php` | Extend or copy pattern for new notifications |
| `app/Notifications/ExamSessionReminder.php` | Change via() to ['database'] only |
| `app/Console/Commands/SendExamReminders.php` | Add env-configurable EXAM_REMINDER_DAYS |
| `resources/js/Components/NotificationDropdown.svelte` | Mobile sizing improvements |
| `resources/js/lib/notification-sound.js` | Add two-tier playChime(tier) |
| `resources/js/lib/toast.js` | Wire context-aware sound to toast type |
| `resources/js/Pages/Admin/TestAdmin/Index.svelte` | Redesign into full My Sessions page |
| `resources/js/Pages/Proctor/SessionRoster.svelte` | Add Start/Close buttons |

---

## Verified Findings (codebase-confirmed)

- [x] **State machine:** Manual controller+policy pattern is sufficient. ExamSession has ~195 lines, 4-5 states, existing transition methods. No package needed. Confidence: HIGH.
- [x] **AudioContext resume():** NOT handled in existing `notification-sound.js`. The singleton is created but `.resume()` is never called. Must add `if (ctx.state === 'suspended') { ctx.resume(); }` in `getAudioContext()`. Confidence: HIGH.
- [x] **TailwindCSS v4 breakpoints:** Project uses `sm:w-96` pattern (confirmed in NotificationDropdown.svelte). TailwindCSS v4 keeps `sm:`, `md:`, `lg:` prefix syntax. No `@breakpoint` syntax needed. Confidence: HIGH.
- [x] **Notification pattern:** All 8 existing notification calls use `$notifiable->notify(new ...)` pattern (confirmed in ExamSessionController, ApplicationController, ReleaseController, SendExamReminders). `Notification::send()` is NOT used anywhere. For bulk sends to merged collections (proctors + test_admins), `Notification::send()` is the correct Laravel approach, but for individual sends, stick with `->notify()`. Confidence: HIGH.

---

*Research status: Complete — all gaps verified against codebase.*
*Next: Run `/gsd-plan-phase 13` to create implementation plan.*
