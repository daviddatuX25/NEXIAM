# Phase 14: Release Page Redesign - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Redesign the release page with mode-aware layout (online/F2F/both tabs), add a ReleaseAll endpoint for online mode, create ResultReleasedF2F notification class, and support bulk release with F2F-specific notifications. The release_mode system setting controls which view the admin sees — online mode gets a streamlined read-only table with Release All, F2F mode gets the existing side panel pattern with per-row release, and 'both' mode uses tabs to switch between them.

</domain>

<decisions>
## Implementation Decisions

### Mode-aware layout
- **D-01:** Tab-based UI in 'both' mode — "Online" tab and "F2F" tab. In 'online' or 'f2f' mode, show a single view (no tabs, just the relevant layout).
- **D-02:** Online mode: read-only consultation data table with "Release All" header button. No per-row Release button. Side panel for editing counselor comments and recommended course is still accessible via Edit button.
- **D-03:** F2F mode: keeps the existing checkbox table + side panel pattern. No changes to the table structure. Release button inside the side panel is added (D-06).
- **D-04:** Both mode: two tabs ("Online" / "F2F") that switch between the online and F2F layouts. Each tab operates independently with its own dataset from the backend.

### Release All flow
- **D-05:** Release All uses a custom modal dialog confirming: "This will release N results to applicants via email and portal notification. This action cannot be undone." Shows count of unreleased summaries. Proceed/Cancel buttons.
- **D-06:** Already-released applicants are silently skipped. Success message shows count: "X results released." Only show error if the entire operation fails.

### F2F notification content
- **D-07:** ResultReleasedF2F notification sends both in-app (database channel) and email. Subject: "Your exam results are available for consultation". Body explains F2F, tells applicant to wait for further announcement about venue for release and consultation. No "View in Portal" action button.
- **D-08:** In-app notification message: "Your exam results are available for face-to-face consultation. Please wait for further announcement regarding the venue and schedule."

### F2F side panel & bulk release
- **D-09:** Any unreleased row can be bulk-released regardless of whether counselor notes are filled. The admin decides what's complete enough.
- **D-10:** Add a "Release" button inside the side panel (after saving notes) so admin can save + release in one flow without closing the panel.

### Claude's Discretion
- Exact tab component implementation (Svelte tabs library vs custom)
- Confirmation modal styling details
- Toast message wording for Release All success/error
- Pagination approach for the online tab (reuse existing paginator)
- F2F email template HTML styling

</decisions>

<canonical_refs>
## Canonical References

### Release controller and routes
- `app/Http/Controllers/ReleaseController.php` — Current release logic: index(), release(), releaseBulk(), storeOrUpdateByApplicant()
- `routes/web.php` — Release routes under admin/release prefix

### Notification system
- `app/Notifications/ResultReleased.php` — Current result notification (mail + database channels, "View in Portal" action)
- `app/Models/SystemSetting.php` — releaseMode() method returning 'online', 'f2f', or 'both'

### Frontend
- `resources/js/Pages/Release/Index.svelte` — Current release page with checkbox table, side panel, and mode banner

### Related phase context
- `.planning/phases/13-exam-session-workflow-notification-enhancements/13-CONTEXT.md` — Phase 13 decisions: notification patterns (mail + database channels), context-aware sound system, role-filtered recipients

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- ReleaseController: Already has release() and releaseBulk() methods with notification dispatch. releaseAll() can follow the same pattern.
- ResultReleased notification: Existing notification class with via() returning ['mail', 'database'], toMail(), toArray(). ResultReleasedF2F can mirror this structure with different wording.
- Index.svelte: Current release page with checkbox selection, side panel for editing counselor comments, and bulk release action. The F2F tab can largely reuse this.
- SystemSetting::releaseMode(): Already returns 'online', 'f2f', or 'both'. No changes needed to the setting itself.
- Toast system (Phase 2): toast.js with success/error/info/silent functions. Can be used for Release All feedback.

### Established Patterns
- Notification pattern: Laravel Notification classes with via() returning channels, toMail() and toArray(). ResultReleasedF2F follows this pattern.
- Side panel pattern: Fixed overlay panel from right side with backdrop. Already implemented in Index.svelte.
- Inertia rendering: Server-side data via Inertia::render(), frontend receives as props. Mode-aware payload follows this pattern.
- Bulk action pattern: releaseBulk() already handles array of IDs with success count feedback.

### Integration Points
- ReleaseController::index(): Needs to pass mode-aware payload — separate online/f2f datasets for 'both' mode tabs.
- ReleaseController: New releaseAll() endpoint for online mode — releases all unreleased summaries in one call.
- ResultReleasedF2F: New notification class needed. Mirrors ResultReleased but with F2F-specific wording and no "View in Portal" action.
- ReleaseController::release(): Update to send ResultReleasedF2F when release_mode is 'f2f' (currently sends nothing for f2f mode).
- ReleaseController::releaseBulk(): Same F2F notification update.
- Index.svelte: Redesign with tab-based layout, Release All button, and conditional rendering per mode.

</code_context>

<specifics>
## Specific Ideas

- In F2F notification email, say "wait for further announcement regarding the venue" rather than directing them to the portal
- Side panel should have a Release button so admin can save notes then release without closing the panel
- Online mode table should still let admin edit counselor comments — it's not purely read-only, just the release mechanism is "Release All"

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---
*Phase: 14-release-page-redesign*
*Context gathered: 2026-04-20*