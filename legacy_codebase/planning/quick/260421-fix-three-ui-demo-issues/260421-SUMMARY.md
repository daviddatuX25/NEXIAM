---
status: complete
quick_id: "260421"
date: 2026-04-21
---

# Fix Three UI/Demo Issues

## Changes

### 1. Phone input 12-char lock + error message
- Added `maxlength="12"` to all phone input fields across 4 Svelte files
- Added `oninput` handler that truncates past 12 chars as a safety net
- Added amber "Maximum 12 characters reached" hint when at limit
- Updated backend validation: `max:20` → `max:12` in both Store and Update Application requests

**Files changed:**
- `resources/js/Pages/Admin/Applications/Create.svelte`
- `resources/js/Pages/Admin/Applications/Edit.svelte`
- `resources/js/Pages/Applications/Apply.svelte`
- `resources/js/Pages/Portal/ApplicationEdit.svelte`
- `app/Http/Requests/StoreApplicationRequest.php`
- `app/Http/Requests/UpdateApplicationRequest.php`

### 2. Fix Inertia response error on notification click
- Root cause: Admin `NotificationController` returns `response()->json()` but frontend uses Inertia `router.post()` which expects Inertia responses
- Fix: Replaced `router.post()` with `fetch()` in both `NotificationDropdown.svelte` and `PortalLayout.svelte` for notification mark-as-read calls
- These are AJAX operations that don't need page navigation, so `fetch()` is the correct approach

**Files changed:**
- `resources/js/Components/NotificationDropdown.svelte` — replaced `router.post` with `fetch` for markAsRead/markAllAsRead, added `usePage` for CSRF token
- `resources/js/Layouts/PortalLayout.svelte` — replaced `router.post` with `fetch` for markRead

### 3. Ngrok tunnel for mailpit endpoint
- Added second ngrok tunnel for mailpit web UI (port 8025) in both demo launch scripts
- Mailpit public URL displayed in the ready banner
- Uses ngrok's second agent port (4041) to read the mailpit tunnel URL

**Files changed:**
- `demo-launch.ps1` — added mailpit ngrok tunnel, updated banner
- `demo-launch.sh` — added mailpit ngrok tunnel, updated banner and log section