# Phase 14: Release Page Redesign - Research

**Researched:** 2026-04-20
**Domain:** Laravel 12 + Inertia/Svelte release page with mode-aware layouts, notification classes, bulk release
**Confidence:** HIGH

## Summary

Phase 14 redesigns the release management page to support three modes (online, f2f, both) controlled by the `SystemSetting::releaseMode()` value. The existing release page (`Release/Index.svelte`) uses a single table with checkboxes and a side panel for editing counselor comments. In 'online' mode, the redesign adds a "Release All" button with a confirmation modal; in 'f2f' mode, it preserves the existing table + side panel but adds a "Release" button inside the panel (D-10); in 'both' mode, it uses tabs to switch between the two layouts. A new `ResultReleasedF2F` notification class mirrors the existing `ResultReleased` pattern but with F2F-specific wording and no "View in Portal" action. The `ReleaseController` gains a `releaseAll()` endpoint for bulk release of all unreleased summaries in online mode.

**Primary recommendation:** Extend the existing ReleaseController with a `releaseAll()` method, create ResultReleasedF2F mirroring ResultReleased, and refactor Index.svelte into a mode-aware layout using the project's existing Tabs UI component and Dialog component for confirmation.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Tab-based UI in 'both' mode — "Online" tab and "F2F" tab. In 'online' or 'f2f' mode, show a single view (no tabs, just the relevant layout).
- **D-02:** Online mode: read-only consultation data table with "Release All" header button. No per-row Release button. Side panel for editing counselor comments and recommended course is still accessible via Edit button.
- **D-03:** F2F mode: keeps the existing checkbox table + side panel pattern. No changes to the table structure. Release button inside the side panel is added (D-06).
- **D-04:** Both mode: two tabs ("Online" / "F2F") that switch between the online and F2F layouts. Each tab operates independently with its own dataset from the backend.
- **D-05:** Release All uses a custom modal dialog confirming: "This will release N results to applicants via email and portal notification. This action cannot be undone." Shows count of unreleased summaries. Proceed/Cancel buttons.
- **D-06:** Already-released applicants are silently skipped. Success message shows count: "X results released." Only show error if the entire operation fails.
- **D-07:** ResultReleasedF2F notification sends both in-app (database channel) and email. Subject: "Your exam results are available for consultation". Body explains F2F, tells applicant to wait for further announcement about venue for release and consultation. No "View in Portal" action button.
- **D-08:** In-app notification message: "Your exam results are available for face-to-face consultation. Please wait for further announcement regarding the venue and schedule."
- **D-09:** Any unreleased row can be bulk-released regardless of whether counselor notes are filled. The admin decides what's complete enough.
- **D-10:** Add a "Release" button inside the side panel (after saving notes) so admin can save + release in one flow without closing the panel.

### Claude's Discretion
- Exact tab component implementation (Svelte tabs library vs custom)
- Confirmation modal styling details
- Toast message wording for Release All success/error
- Pagination approach for the online tab (reuse existing paginator)
- F2F email template HTML styling

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-REL-01 | Mode-aware release page layout adapting to release_mode setting | Tabs component (bits-ui), Dialog for confirmation, conditional rendering per mode |
| REQ-REL-02 | Online one-click Release All with confirmation dialog | New `releaseAll()` endpoint, Dialog component, skip-already-released logic |
| REQ-REL-03 | F2F release with consultation notes, side panel, bulk release | Existing side panel pattern, new Release button in panel (D-10) |
| REQ-REL-04 | F2F notification with specific wording | New `ResultReleasedF2F` notification class, mail + database channels |
| REQ-REL-05 | Online release continues sending existing ResultReleased notification | No changes to existing ResultReleased class, only to controller dispatch logic |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Release mode determination | API / Backend | — | SystemSetting::releaseMode() is server-side; frontend receives it via Inertia shared props |
| Tab switching UI | Browser / Client | — | Pure client-side state; only determines which dataset to display |
| Release All action | API / Backend | — | Single POST endpoint releases all unreleased summaries, dispatches notifications |
| F2F notification dispatch | API / Backend | — | Laravel Notification class sends mail + database channels |
| Confirmation modal | Browser / Client | — | Dialog/AlertDialog component for "are you sure?" UX |
| Side panel edit + release | Browser / Client | API / Backend | Panel UI is client-side; save and release are API calls |
| Mode-specific data splitting | API / Backend | — | Controller passes separate online/f2f datasets when mode is 'both' |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| bits-ui | ^2.15.6 | Headless Svelte UI primitives (Tabs, AlertDialog) | Project's existing UI component foundation [VERIFIED: package.json] |
| @inertiajs/svelte | ^2.3.15 | Server-side data binding, form submissions | Project's SPA framework [VERIFIED: package.json] |
| Laravel Framework | 12.56.0 | Backend, notifications, routes | Project's backend framework [VERIFIED: artisan --version] |
| Svelte | ^5.51.3 | Frontend reactivity ($state, $derived, $props) | Project's frontend framework [VERIFIED: package.json] |
| TailwindCSS | ^4.0.0 | Styling | Project's CSS framework [VERIFIED: package.json] |
| lucide-svelte | ^0.574.0 | Icons | Project's icon library [VERIFIED: package.json] |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| shadcn-svelte tabs | (local) | Pre-wrapped Tabs UI component | For both-mode tab switching |
| shadcn-svelte dialog | (local) | Pre-wrapped Dialog component | For Release All confirmation modal |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| shadcn-svelte Dialog | Custom Svelte modal | Custom would be hand-rolled; project already has Dialog component wrapping bits-ui |
| Alert-specific AlertDialog | Reuse Dialog with custom buttons | AlertDialog enforces focus trapping and escape-key semantics better for destructive actions; worth creating the component [ASSUMED] |

**Installation:**
No new packages needed. All UI components (Tabs, Dialog) already exist in the project's `resources/js/Components/ui/`.

## Architecture Patterns

### System Architecture Diagram

```
Admin Browser
  |
  v
ReleaseController@index()
  |-- mode = 'online'  -->  Inertia::render('Release/Index', {online_summaries, ...})
  |-- mode = 'f2f'     -->  Inertia::render('Release/Index', {f2f_summaries, ...})
  |-- mode = 'both'    -->  Inertia::render('Release/Index', {online_summaries, f2f_summaries, ...})
  |
  +--> Tabs component (both mode only)
       |-- "Online" tab: read-only table + Release All button
       |      |
       |      +--> [Release All] --> Confirmation Dialog
       |                            |
       |                            +--> POST /admin/release/summaries/release-all
       |                                 |
       |                                 +--> ReleaseController@releaseAll()
       |                                      |-- Skip already released
       |                                      |-- Send ResultReleased notification per applicant
       |                                      +--> Return count + redirect back
       |
       +-- "F2F" tab: checkbox table + side panel
              |
              +--> [Edit] row --> Side Panel opens
              |      |-- Save notes --> PUT /admin/release/summaries/by-applicant/{id}
              |      +--> [Release] --> POST /admin/release/summaries/{id}/release
              |                          |-- Send ResultReleasedF2F notification
              |                          +--> Close panel, refresh
              |
              +--> [Bulk Release] --> POST /admin/release/summaries/bulk-release
                                      |-- Send ResultReleasedF2F per applicant
                                      +--> Return count + redirect back
```

### Recommended Project Structure
```
app/
  Http/Controllers/
    ReleaseController.php           # Extend with releaseAll()
  Notifications/
    ResultReleased.php              # Existing — no changes
    ResultReleasedF2F.php           # NEW — F2F-specific notification
  Models/
    SystemSetting.php               # Existing — no changes
  Services/
    ConsultationSummaryService.php  # Existing — may extend release() for F2F

resources/js/
  Pages/Release/
    Index.svelte                    # REFACTOR — mode-aware layout with tabs
  Components/ui/
    tabs/                           # EXISTING — use for both mode
    dialog/                         # EXISTING — use for confirmation modal
    alert-dialog/                   # NEW (optional) — if AlertDialog is preferred over Dialog
```

### Pattern 1: Mode-Aware Controller Payload
**What:** Controller returns different datasets based on release_mode
**When to use:** When rendering the Index page in all three modes
**Example:**
```php
// ReleaseController@index()
public function index(): Response
{
    $mode = SystemSetting::releaseMode();
    $courses = Course::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

    $props = [
        'release_mode' => $mode,
        'courses' => $courses,
    ];

    if ($mode === 'online') {
        $props['online_summaries'] = $this->getOnlineSummaries();
    } elseif ($mode === 'f2f') {
        $props['f2f_summaries'] = $this->getF2fSummaries();
    } else {
        // 'both' mode — separate datasets for each tab
        $props['online_summaries'] = $this->getOnlineSummaries();
        $props['f2f_summaries'] = $this->getF2fSummaries();
    }

    return Inertia::render('Release/Index', $props);
}
```

### Pattern 2: Laravel Notification with Conditional Channels
**What:** Notification class that sends via mail + database with F2F-specific content
**When to use:** For ResultReleasedF2F notification
**Example:**
```php
// app/Notifications/ResultReleasedF2F.php
class ResultReleasedF2F extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ConsultationSummary $summary) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your exam results are available for consultation')
            ->greeting('Hello, ' . ($notifiable->name ?? 'Applicant') . '!')
            ->line('Your exam results are now available for face-to-face consultation.')
            ->line('Please wait for further announcement regarding the venue and schedule for your consultation.')
            ->line('If you have questions, please contact the guidance office.');
        // NOTE: No ->action() call — F2F has no "View in Portal" button (D-07)
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'result_released_f2f',
            'summary_id' => $this->summary->id,
            'message'    => 'Your exam results are available for face-to-face consultation. Please wait for further announcement regarding the venue and schedule.',
        ];
    }
}
```

### Pattern 3: Release All Endpoint
**What:** Single endpoint that releases all unreleased summaries in online mode
**When to use:** When admin clicks "Release All" in online mode
**Example:**
```php
// ReleaseController — new method
public function releaseAll(): RedirectResponse
{
    $mode = SystemSetting::releaseMode();

    // Only allow in online or both mode
    if ($mode === 'f2f') {
        return back()->with('error', 'Release All is not available in F2F mode.');
    }

    $summaries = ConsultationSummary::where('status', '!=', ConsultationSummary::STATUS_RELEASED)->get();
    $releasedCount = 0;

    foreach ($summaries as $summary) {
        $summary->update([
            'status' => ConsultationSummary::STATUS_RELEASED,
            'released_at' => now(),
            'released_by' => auth()->id(),
        ]);

        // Online mode sends ResultReleased notification (D-05, REQ-REL-05)
        $summary->applicant->notify(new ResultReleased($summary));
        $releasedCount++;
    }

    return back()->with('success', "{$releasedCount} result(s) released.");
}
```

### Pattern 4: Mode-Aware Release Logic in Controller
**What:** Existing release() and releaseBulk() methods need to dispatch the correct notification based on mode
**When to use:** When releasing individual or bulk summaries
**Example:**
```php
// In ReleaseController::release() — change from:
if (SystemSetting::releaseMode() !== 'f2f') {
    $summary->applicant->notify(new ResultReleased($summary));
}
// To:
$mode = SystemSetting::releaseMode();
if ($mode === 'online') {
    $summary->applicant->notify(new ResultReleased($summary));
} elseif ($mode === 'f2f') {
    $summary->applicant->notify(new ResultReleasedF2F($summary));
} elseif ($mode === 'both') {
    // F2F tab sends F2F notification; Online tab sends ResultReleased
    // The controller needs to know which context is calling
    // Approach: pass mode hint from frontend, or use a query parameter
}
```

**IMPORTANT:** In 'both' mode, the backend needs to know whether the release came from the Online tab or F2F tab. The cleanest approach is to add a `release_context` parameter to the release routes (value: 'online' or 'f2f'). The frontend sends this along with the release request.

### Pattern 5: Svelte Tab-Based Layout
**What:** Use existing shadcn-svelte Tabs component for both-mode switching
**When to use:** When `release_mode === 'both'`
**Example:**
```svelte
<script>
  import * as Tabs from '@/Components/ui/tabs';
  // ...
  let activeTab = $state('online');
</script>

{#if release_mode === 'both'}
  <Tabs.Root bind:value={activeTab}>
    <Tabs.List>
      <Tabs.Trigger value="online">Online</Tabs.Trigger>
      <Tabs.Trigger value="f2f">F2F</Tabs.Trigger>
    </Tabs.List>
    <Tabs.Content value="online">
      <!-- Online layout: read-only table + Release All -->
    </Tabs.Content>
    <Tabs.Content value="f2f">
      <!-- F2F layout: checkbox table + side panel -->
    </Tabs.Content>
  </Tabs.Root>
{:else if release_mode === 'online'}
  <!-- Online layout only -->
{:else}
  <!-- F2F layout only -->
{/if}
```

### Anti-Patterns to Avoid
- **Don't load all summaries in one payload for 'both' mode and split client-side:** The backend should send separate datasets so each tab's pagination works independently (D-04).
- **Don't use the existing `release()` endpoint for Release All:** It's designed for single-summary release. A dedicated `releaseAll()` endpoint is cleaner and avoids N individual POST requests.
- **Don't modify `ResultReleased` for F2F wording:** Create a separate `ResultReleasedF2F` class. Different notification classes allow independent evolution of wording and channels.
- **Don't remove the existing checkbox + side panel in F2F mode:** D-03 explicitly says "keeps the existing checkbox table + side panel pattern. No changes to the table structure."
- **Don't add per-row Release buttons in online mode:** D-02 says "No per-row Release button" in online mode.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tab switching | Custom tab state + show/hide | shadcn-svelte Tabs component | Already in project at `resources/js/Components/ui/tabs/`; handles accessibility, keyboard nav, ARIA |
| Confirmation modal | Custom overlay + buttons | shadcn-svelte Dialog or AlertDialog | Already in project at `resources/js/Components/ui/dialog/`; handles focus trap, escape key, backdrop |
| Notification dispatch | Custom mail/log logic | Laravel Notification classes | Project already uses Notifiable trait on Applicant; ResultReleased sets the pattern |
| Toast feedback | Custom toast logic | toast.js (already in project) | success/error/info/silent functions with sound already wired |
| Pagination | Custom paginator | Inertia paginator (already in page) | `summaries` already uses `->paginate(50)`; reuse in both tabs |
| Authorization | Inline role checks | Route middleware `role:super_admin,test_administrator` | Already on release routes |

**Key insight:** The project has a mature UI component library (shadcn-svelte wraps bits-ui). All the primitives needed for this phase — tabs, dialogs, badges, tables, buttons — already exist. The main work is wiring backend logic and restructuring the Svelte component.

## Runtime State Inventory

> This is a refactor/redesign phase (not a rename), but there are runtime state implications.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | `system_settings.release_mode` = 'online' (current value) | No migration needed; setting already exists |
| Stored data | `consultation_summaries` table: 5 records (3 released, 2 unreleased) | No schema changes needed; status field already supports 'pending', 'draft', 'released' |
| Live service config | None — no external services config outside git | — |
| OS-registered state | None | — |
| Secrets/env vars | None — no new secrets required | — |
| Build artifacts | None — npm run build regenerates JS bundle | — |

## Common Pitfalls

### Pitfall 1: Both-Mode Release Context
**What goes wrong:** In 'both' mode, when `release()` or `releaseBulk()` is called, the backend doesn't know whether it came from the Online tab or F2F tab, so it can't determine which notification to send.
**Why it happens:** The existing `release()` method uses `SystemSetting::releaseMode()` to decide, which returns 'both' — an ambiguous state.
**How to avoid:** Add a `release_context` parameter to the release routes. Frontend sends `release_context=online` or `release_context=f2f` with each request. Controller checks this to dispatch the correct notification class.
**Warning signs:** Releases in 'both' mode send the wrong notification type, or send no notification at all.

### Pitfall 2: Release All Skips Silently, But Admin Expects Feedback
**What goes wrong:** If all summaries are already released, `releaseAll()` returns with "0 results released" which might confuse the admin into thinking something went wrong.
**Why it happens:** D-06 says "already-released applicants are silently skipped" but doesn't address the edge case where there's nothing to release.
**How to avoid:** Return a distinct message: "All results have already been released." (count = 0 case). Show "X results released." for positive count. Show "Release failed." only on exception.
**Warning signs:** Admin clicks Release All, sees "0 result(s) released", and thinks it failed.

### Pitfall 3: Release All Race Condition
**What goes wrong:** Between the confirmation dialog showing "N unreleased results" and the admin clicking "Proceed", another admin releases some results.
**Why it happens:** The count shown in the confirmation dialog is stale by the time the request hits the server.
**How to avoid:** Server-side, `releaseAll()` re-queries unreleased summaries at execution time (not from the dialog count). The dialog count is approximate. The actual released count returned in the success message is authoritative.
**Warning signs:** Admin sees "5 results will be released" in dialog, but success message says "3 results released" because 2 were released concurrently.

### Pitfall 4: Notification Queue Delays
**What goes wrong:** Both `ResultReleased` and `ResultReleasedF2F` implement `ShouldQueue`. If the queue worker is not running, notifications won't be sent, and the admin won't know.
**Why it happens:** Queueable notifications are dispatched asynchronously.
**How to avoid:** This is the existing project pattern — all notifications use `ShouldQueue`. Ensure the queue worker is running. Consider adding a note in the admin UI or logging. No code change needed for this phase.
**Warning signs:** Admin releases results but applicants don't receive emails.

### Pitfall 5: Side Panel State Loss on Release
**What goes wrong:** When admin clicks "Release" inside the side panel (D-10), the panel closes and the table refreshes. If the release request fails, the admin loses their place.
**Why it happens:** The current `saveSummary()` function closes the panel on success. Adding a release action inside the panel needs careful error handling.
**How to avoid:** On release failure, keep the panel open and show the error. Only close the panel on successful release. Use Inertia's `onError` callback to handle this, same pattern as `saveSummary()`.
**Warning signs:** Panel closes on error, admin has to re-find the row.

### Pitfall 6: F2F Bulk Release Should Send F2F Notification
**What goes wrong:** The existing `releaseBulk()` checks `SystemSetting::releaseMode() !== 'f2f'` and only sends `ResultReleased` in non-f2f mode. In 'f2f' mode it sends nothing. After this phase, it should send `ResultReleasedF2F`.
**Why it happens:** Original code intentionally skipped notifications for F2F mode because the notification class didn't exist yet.
**How to avoid:** Update `releaseBulk()` to dispatch `ResultReleasedF2F` when mode is 'f2f', and use context-aware dispatch in 'both' mode.
**Warning signs:** F2F applicants receive no notification after bulk release.

### Pitfall 7: Inertia Shared Props vs Page Props for release_mode
**What goes wrong:** `release_mode` is shared globally via `HandleInertiaRequests` middleware (line 59), AND also passed explicitly in `ReleaseController@index()` (line 33). If they get out of sync, the page behaves incorrectly.
**Why it happens:** Two sources of truth for the same value.
**How to avoid:** The explicit page prop takes precedence in Inertia (page props override shared props). This is fine — both read from `SystemSetting::releaseMode()`. Just be aware that both exist and they should always agree.
**Warning signs:** Mode shows differently in nav vs content area.

## Code Examples

### New Route for Release All
```php
// routes/web.php — add to existing release route group
Route::post('/release/summaries/release-all', [ReleaseController::class, 'releaseAll'])
    ->name('summaries.release-all');
```
Source: Project routes pattern [VERIFIED: web.php line 236-247]

### ReleaseController — releaseAll() Method
```php
public function releaseAll(Request $request): RedirectResponse
{
    $mode = SystemSetting::releaseMode();

    if ($mode === 'f2f') {
        return back()->with('error', 'Release All is not available in F2F mode.');
    }

    $summaries = ConsultationSummary::where('status', '!=', ConsultationSummary::STATUS_RELEASED)
        ->with('applicant')
        ->get();

    $releasedCount = 0;

    foreach ($summaries as $summary) {
        $summary->update([
            'status' => ConsultationSummary::STATUS_RELEASED,
            'released_at' => now(),
            'released_by' => auth()->id(),
        ]);

        $summary->applicant->notify(new ResultReleased($summary));
        $releasedCount++;
    }

    if ($releasedCount === 0) {
        return back()->with('info', 'All results have already been released.');
    }

    return back()->with('success', "{$releasedCount} result(s) released.");
}
```
Source: Existing `releaseBulk()` pattern [VERIFIED: ReleaseController.php line 83-107]

### ResultReleasedF2F Notification Class
```php
<?php

namespace App\Notifications;

use App\Models\ConsultationSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultReleasedF2F extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ConsultationSummary $summary) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your exam results are available for consultation')
            ->greeting('Hello, ' . ($notifiable->name ?? 'Applicant') . '!')
            ->line('Your exam results are now available for face-to-face consultation.')
            ->line('Please wait for further announcement regarding the venue and schedule for your consultation.')
            ->line('If you have questions, please contact the guidance office.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'result_released_f2f',
            'summary_id' => $this->summary->id,
            'message'    => 'Your exam results are available for face-to-face consultation. Please wait for further announcement regarding the venue and schedule.',
        ];
    }
}
```
Source: Existing ResultReleased pattern [VERIFIED: ResultReleased.php]

### Mode-Aware Notification Dispatch in release()
```php
// Updated ReleaseController::release()
public function release(Request $request, ConsultationSummary $summary, string $context = null): RedirectResponse
{
    if ($summary->status === ConsultationSummary::STATUS_RELEASED) {
        return back()->with('error', 'Already released.');
    }

    $summary->update([
        'status' => ConsultationSummary::STATUS_RELEASED,
        'released_at' => now(),
        'released_by' => auth()->id(),
    ]);

    $mode = SystemSetting::releaseMode();
    $releaseContext = $request->input('release_context', $mode);

    if ($releaseContext === 'online' || ($mode === 'online')) {
        $summary->applicant->notify(new ResultReleased($summary));
    } elseif ($releaseContext === 'f2f' || $mode === 'f2f') {
        $summary->applicant->notify(new ResultReleasedF2F($summary));
    }

    return back()->with('success', 'Result released.');
}
```

### Confirmation Dialog Using Existing Dialog Component
```svelte
<script>
  import * as Dialog from '@/Components/ui/dialog';
  import { Button } from '@/Components/ui/button';
  // ...
  let showConfirmDialog = $state(false);
  let unreleasedCount = $derived(
    onlineSummaries?.data?.filter(s => s.status !== 'released').length ?? 0
  );
</script>

<!-- Release All Confirmation Dialog -->
<Dialog.Root bind:open={showConfirmDialog}>
  <Dialog.Portal>
    <Dialog.Overlay class="fixed inset-0 bg-black/40 z-50" />
    <Dialog.Content class="fixed top-[50%] left-[50%] -translate-x-1/2 -translate-y-1/2 z-50 bg-background rounded-lg border p-6 shadow-lg max-w-md w-[calc(100%-2rem)]">
      <Dialog.Header>
        <Dialog.Title>Confirm Release All</Dialog.Title>
        <Dialog.Description>
          This will release {unreleasedCount} results to applicants via email and portal notification.
          This action cannot be undone.
        </Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer class="flex justify-end gap-2 mt-4">
        <Button variant="outline" onclick={() => showConfirmDialog = false}>Cancel</Button>
        <Button onclick={handleReleaseAll}>Proceed</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Portal>
</Dialog.Root>
```
Source: Existing Dialog component pattern [VERIFIED: resources/js/Components/ui/dialog/]

### F2F Side Panel with Release Button (D-10)
```svelte
<!-- Inside the existing side panel, add Release button after Save -->
<div class="p-6 border-t border-border space-y-2">
  <Button onclick={saveSummary} disabled={saving} class="w-full min-h-[44px]">
    {saving ? 'Saving...' : 'Save'}
  </Button>
  {#if selectedSummary?.status !== 'released'}
    <Button
      variant="default"
      onclick={() => releaseOneFromPanel(selectedSummary.id)}
      disabled={saving}
      class="w-full min-h-[44px]"
    >
      Release
    </Button>
  {/if}
  <Button variant="outline" onclick={closePanel} class="w-full min-h-[44px]">Cancel</Button>
</div>
```

### Toast Feedback for Release All
```svelte
import { success, error, info } from '@/lib/toast.js';

// In handleReleaseAll callback:
router.post('/admin/release/summaries/release-all', {}, {
  preserveScroll: true,
  onSuccess: (page) => {
    showConfirmDialog = false;
    // Flash message will show via Inertia; optionally add toast
    // success(page.props.flash.success);
  },
  onError: () => {
    error('Failed to release results. Please try again.');
  },
});
```
Source: Existing toast pattern [VERIFIED: resources/js/lib/toast.js]

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Single release page, no mode distinction | Mode-aware release page (online/f2f/both) | Phase 14 | Page must now conditionally render based on `release_mode` |
| No notification for F2F releases | ResultReleasedF2F notification | Phase 14 | F2F applicants now receive email + in-app notification |
| Per-row release only | Release All for online mode | Phase 14 | New endpoint, new confirmation UX |
| Side panel has Save only | Side panel has Save + Release | Phase 14 | Admin can save notes and release without closing panel |

**Deprecated/outdated:**
- `ReleaseController::release()` currently sends no notification in F2F mode (`if (SystemSetting::releaseMode() !== 'f2f')`) — this is being replaced with context-aware dispatch that sends `ResultReleasedF2F` for F2F.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | AlertDialog component doesn't exist in project but Dialog does — using Dialog for confirmation is acceptable, though AlertDialog provides better focus-trapping semantics for destructive actions | Architecture Patterns | Confirmation dialog may not meet WCAG focus management requirements |
| A2 | In 'both' mode, the frontend needs to pass `release_context` to distinguish which notification to send — this is the cleanest approach | Common Pitfalls | If not passed, 'both' mode releases may send wrong notification type |
| A3 | ConsultationSummary model does not have a 'mode' column — all summaries share the same table regardless of online vs f2f release mode | Runtime State Inventory | If summaries were mode-tagged, the data model would need adjustment |
| A4 | The existing `releaseBulk()` and `release()` methods should be updated to support context-aware notification dispatch in 'both' mode | Architecture Patterns | If not updated, 'both' mode releases will either always send ResultReleased or no notification |

**If this table is empty:** All claims in this research were verified or cited — no user confirmation needed.

## Open Questions (RESOLVED)

1. **Should AlertDialog be created as a new shadcn-svelte component?** — RESOLVED: Use existing Dialog component per Claude's Discretion (confirmation modal styling details). Plan 02 uses Dialog.Root with custom footer buttons, which is sufficient for the confirmation flow.
   - What we know: Project has Dialog component but no AlertDialog. bits-ui supports AlertDialog natively.
   - What's unclear: Whether the team prefers to create a proper AlertDialog component for better a11y or reuse Dialog with custom buttons.
   - Recommendation: Create a minimal AlertDialog wrapper for the destructive Release All action. It's a one-time setup that improves accessibility for confirmation flows.

2. **How should 'both' mode pagination work?** — RESOLVED: Independent pagination per tab. Backend provides separate `online_summaries` and `f2f_summaries` paginated datasets. Plan 01 Task 2 returns separate datasets; Plan 02 Task 1 renders them independently per tab.
   - What we know: D-04 says "Each tab operates independently with its own dataset from the backend."
   - What's unclear: Whether both tabs should share pagination state or have independent page tracking.
   - Recommendation: Independent pagination per tab. Each tab's data comes from a separate paginated query, and clicking between tabs preserves each tab's current page.

3. **Should Release All endpoint be restricted to 'online' mode only?** — RESOLVED: Return back with flash error for 'f2f' mode. Plan 01 Task 2 releaseAll() returns `back()->with('error', ...)` for f2f mode. Matches existing releaseBulk() pattern.
   - What we know: D-02 says Release All is for online mode. D-05 describes the confirmation dialog.
   - What's unclear: Whether the endpoint should return 403 for 'f2f' mode or just redirect back with a flash message.
   - Recommendation: Return back with flash error for 'f2f' mode. Matches existing pattern in `releaseBulk()`.

4. **What happens to summaries with status 'pending' in Release All?** — RESOLVED: Release all non-released summaries (both 'pending' and 'draft'). Plan 01 Task 2 releaseAll() queries `where('status', '!=', ConsultationSummary::STATUS_RELEASED)` which includes both pending and draft. D-09 confirms "The admin decides what's complete enough."
   - What we know: ConsultationSummary has statuses 'pending', 'draft', 'released'. The current index query uses `whereIn('status', ['draft', 'released'])`.
   - What's unclear: Should Release All also release 'pending' summaries, or only 'draft'?
   - Recommendation: Release All should release all non-released summaries (both 'pending' and 'draft'). The admin explicitly confirms via the dialog, and D-09 says "The admin decides what's complete enough."

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel backend | Yes | 8.2 | — |
| Node.js | Frontend build | Yes | — | — |
| MySQL | Data storage | Yes | — | — |
| Laravel 12 | Framework | Yes | 12.56.0 | — |
| Svelte 5 | Frontend | Yes | ^5.51.3 | — |
| bits-ui | UI primitives | Yes | ^2.15.6 | — |
| @inertiajs/svelte | SPA framework | Yes | ^2.3.15 | — |
| TailwindCSS 4 | Styling | Yes | ^4.0.0 | — |

**Missing dependencies with no fallback:**
- None — all required dependencies are available.

**Missing dependencies with fallback:**
- AlertDialog shadcn-svelte component doesn't exist yet — can use Dialog component as fallback, or create AlertDialog wrapper from bits-ui primitives.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact --filter=ReleaseTest` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-REL-01 | Mode-aware page renders correct layout per mode | Feature | `php artisan test --compact --filter=ReleasePageTest` | No — Wave 0 |
| REQ-REL-02 | Release All releases unreleased summaries, skips released | Feature | `php artisan test --compact --filter=ReleaseAllTest` | No — Wave 0 |
| REQ-REL-03 | F2F release with side panel, per-row release in panel | Feature | `php artisan test --compact --filter=ReleaseF2fTest` | No — Wave 0 |
| REQ-REL-04 | F2F notification sends mail + database with correct wording | Unit | `php artisan test --compact --filter=ResultReleasedF2FTest` | No — Wave 0 |
| REQ-REL-05 | Online release sends ResultReleased notification | Unit | `php artisan test --compact --filter=ResultReleasedTest` | No — Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=ReleaseTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ReleasePageTest.php` — covers REQ-REL-01 (mode-aware rendering)
- [ ] `tests/Feature/ReleaseAllTest.php` — covers REQ-REL-02 (Release All endpoint)
- [ ] `tests/Feature/ReleaseF2fTest.php` — covers REQ-REL-03 (F2F release flow)
- [ ] `tests/Unit/ResultReleasedF2FTest.php` — covers REQ-REL-04 (F2F notification)
- [ ] `tests/Unit/ResultReleasedTest.php` — covers REQ-REL-05 (online notification unchanged)
- [ ] Framework install: Already present (PHPUnit 11)

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | Yes (route middleware) | Laravel auth middleware + role middleware |
| V3 Session Management | Yes (CSRF on POST routes) | Laravel CSRF token via @csrf / Inertia |
| V4 Access Control | Yes | Route middleware `role:super_admin,test_administrator` |
| V5 Input Validation | Yes | Laravel FormRequest or inline `$request->validate()` |
| V6 Cryptography | No | No crypto operations in this phase |

### Known Threat Patterns for Laravel + Inertia

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| CSRF on Release All | Tampering | Laravel CSRF token (automatic with Inertia `router.post()`) |
| Mass assignment on ConsultationSummary | Tampering | `$fillable` whitelist on model + explicit field updates |
| Unauthorized release (non-admin) | Elevation | Route middleware `role:super_admin,test_administrator` |
| Notification spam (repeated Release All) | Denial of Service | Rate limiting + idempotency check (skip already-released) |

## Sources

### Primary (HIGH confidence)
- ReleaseController.php — existing release logic, route patterns [VERIFIED: codebase]
- ResultReleased.php — existing notification class pattern [VERIFIED: codebase]
- SystemSetting.php — releaseMode() method [VERIFIED: codebase]
- ConsultationSummary.php — model with STATUS_RELEASED constant [VERIFIED: codebase]
- resources/js/Components/ui/tabs/ — shadcn-svelte Tabs component [VERIFIED: codebase]
- resources/js/Components/ui/dialog/ — shadcn-svelte Dialog component [VERIFIED: codebase]
- HandleInertiaRequests.php — shared props including release_mode [VERIFIED: codebase]
- routes/web.php — release route group [VERIFIED: codebase]
- bits-ui docs — Tabs and AlertDialog component APIs [CITED: context7 /huntabyte/bits-ui]
- Laravel notification docs — MailMessage, via(), ShouldQueue [CITED: context7 /laravel/docs]

### Secondary (MEDIUM confidence)
- ConsultationSummaryService.php — existing release logic with audit [VERIFIED: codebase]
- ExamSessionReminder.php — notification class pattern with ShouldQueue [VERIFIED: codebase]
- NotificationSystemTest.php — test pattern for notification assertions [VERIFIED: codebase]

### Tertiary (LOW confidence)
- None — all findings verified against codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components verified in package.json and codebase
- Architecture: HIGH — patterns are direct extensions of existing code
- Pitfalls: HIGH — derived from careful analysis of mode-aware dispatch logic and existing code patterns

**Research date:** 2026-04-20
**Valid until:** 2026-05-20 (stable Laravel/Svelte stack)