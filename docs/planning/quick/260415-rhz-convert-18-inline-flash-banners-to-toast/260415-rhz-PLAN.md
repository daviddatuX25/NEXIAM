---
id: 260415-rhz
slug: convert-18-inline-flash-banners-to-toast
description: Convert 18 inline flash banners to toast notifications
date: 2026-04-15
must_haves:
  truths:
    - All inline flash banner divs removed from all 18 affected pages
    - No flash-only derived variables remain (success/error/flash vars used only for banners)
    - ToastManager handles flash.success / flash.error automatically via page subscription
    - Logs/Index.svelte exportError inline banner preserved (it's local state, not flash)
    - AcademicYears/Index.svelte dead `success` prop removed from $props()
    - Portal/Login.svelte unused `flash` derived var removed
  artifacts:
    - All 18 modified .svelte files
---

# Quick Task 260415-rhz: Convert 18 Inline Flash Banners to Toast

## Context

`ToastManager.svelte` is mounted in ALL layouts (AuthenticatedLayout, HomeLayout, PortalGuestLayout, PortalLayout). It already watches `page.props.flash` and converts `flash.success` and `flash.error` to toasts automatically via `page.subscribe()` in onMount.

**What to do:** For each file, remove the inline flash banner HTML and the derived variable declarations that are only used for those banners. Do NOT touch validation errors (beside inputs), component-internal state, or other non-flash logic.

---

## Task 1 — Remove banners from AuthenticatedLayout pages (standard pattern)

**Files:** All pages using `AuthenticatedLayout`. Each follows the same pattern: derives `success`/`error` from `$page.props.flash`, renders a banner div.

### 1a. `resources/js/Pages/Applications/Show.svelte`
**Remove** from `<script>`:
```js
const success = $derived($page.props.flash?.success ?? null);
const error = $derived($page.props.flash?.error ?? null);
```
**Remove** from template:
```svelte
{#if success}
  <div class="rounded-lg bg-primary/10 px-4 py-3 text-sm text-primary">
    {success}
  </div>
{/if}
{#if error}
  <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
    {error}
  </div>
{/if}
```
Also remove unused `CheckCircle, XCircle` from the lucide-svelte import if they're only used in those banners. Read the file to verify — `CheckCircle` and `XCircle` are also used in the action buttons, so keep those imports.

### 1b. `resources/js/Pages/Admin/AiCompanion/Index.svelte`
**Remove** from `<script>`:
```js
const flash = $derived($page.props.flash ?? {});
```
**Remove** from template:
```svelte
{#if flash.success}
  <div class="rounded-lg bg-primary/10 px-4 py-3 text-sm text-primary">{flash.success}</div>
{/if}
```

### 1c. `resources/js/Pages/Admin/Settings/Index.svelte`
**Remove** from `<script>`:
```js
const flash = $derived($page.props.flash ?? {});
```
**Remove** from template:
```svelte
{#if flash.success}
  <p class="text-sm text-green-600 dark:text-green-400">{flash.success}</p>
{/if}
```

### 1d. `resources/js/Pages/Admin/AcademicYears/Index.svelte`
**Remove** from `<script>` — the `success = null` prop is dead code (unused in template) AND the `successMsg` derived var:
```js
// In $props() destructuring, remove ", success = null"
let { academic_years, success = null } = $props();
// becomes:
let { academic_years } = $props();

// Remove this line entirely:
const successMsg = $derived($page.props.flash?.success ?? null);
```
**Remove** from template:
```svelte
{#if successMsg}
  <div class="rounded-lg bg-primary/10 px-4 py-3 text-sm text-primary">
    {successMsg}
  </div>
{/if}
```

### 1e. `resources/js/Pages/Admin/Rooms/Index.svelte`
**Remove** from `<script>`:
```js
const success = $derived($page.props.flash?.success ?? null);
const error = $derived($page.props.flash?.error ?? null);
```
**Remove** from template:
```svelte
{#if success}
  <div class="rounded-lg bg-primary/10 px-4 py-3 text-sm text-primary">
    {success}
  </div>
{/if}
{#if error}
  <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
    {error}
  </div>
{/if}
```

### 1f. `resources/js/Pages/Admin/Logs/Index.svelte`
**Special case** — `exportError` is local component state (from fetch), NOT flash. Keep the exportError banner but convert it to only show exportError.

**Remove** from `<script>`:
```js
const error = $derived($page.props.flash?.error ?? null);
```

**Change** in template from:
```svelte
{#if error || exportError}
  <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
    {exportError ?? error}
  </div>
{/if}
```
**To:**
```svelte
{#if exportError}
  <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
    {exportError}
  </div>
{/if}
```

### 1g. `resources/js/Pages/Admin/Users/Index.svelte`
Read this file first. The analysis says `success` is declared but NOT rendered in template. Remove the unused derived var from `<script>`. Do not add a banner.

### 1h. `resources/js/Pages/Admin/TestScheduling/Index.svelte`
Read this file first. Same as 1g — `success` declared but not rendered. Remove unused derived var.

### 1i-1p. Remaining standard-pattern pages (read each, remove flash-derived vars + banners):
- `resources/js/Pages/Admin/TestAdmin/Index.svelte` (success + error)
- `resources/js/Pages/Admin/TestScheduling/Show.svelte` (success + error)
- `resources/js/Pages/Admin/AdmissionSlipTemplates/Index.svelte` (success)
- `resources/js/Pages/Admin/Courses/Index.svelte` (success)
- `resources/js/Pages/Admin/AptitudeAreas/Index.svelte` (success)
- `resources/js/Pages/Admin/ResultSheetTemplates/Index.svelte` (success)
- `resources/js/Pages/Admin/KnowledgeDocuments/Index.svelte` (success)
- `resources/js/Pages/Grading/Dashboard.svelte` (success)
- `resources/js/Pages/Grading/Session.svelte` (success)

**Pattern for each:** Remove `const success = $derived($page.props.flash?.success ?? null)` and/or `const error = $derived($page.props.flash?.error ?? null)` from script. Remove the corresponding `{#if success}...{/if}` and/or `{#if error}...{/if}` banner divs from template.

---

## Task 2 — Remove banners from login pages

### 2a. `resources/js/Pages/Auth/Login.svelte`
`flash` is derived from `$page.props.flash` and used ONLY for the glass-panel banners. ToastManager is in HomeLayout so it handles flash automatically.

**Remove** from `<script>`:
```js
const flash = $derived($page.props.flash ?? {});
```

**Remove** from template (two banners inside `<Card.Content>`):
```svelte
{#if flash.success}
  <div class="glass-panel p-4 rounded-xl border border-success/30 bg-success/10 text-success text-sm flex items-center gap-3 font-semibold">
    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    {flash.success}
  </div>
{/if}
{#if flash.error}
  <div class="glass-panel p-4 rounded-xl border border-destructive/30 bg-destructive/10 text-destructive text-sm flex items-center gap-3 font-semibold">
    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    {flash.error}
  </div>
{/if}
```

### 2b. `resources/js/Pages/Portal/Login.svelte`
`flash` is derived but **never rendered** in the template. Just remove the unused line.

**Remove** from `<script>`:
```js
const flash = $derived($page.props.flash ?? {});
```

---

## Task 3 — Clean up unused `usePage` imports

After removing flash-derived vars, some files may have `usePage` imported but no longer used. For each modified file:
- If `usePage` was only used for the flash-derived vars (i.e. no other `$page.props.*` usage remains), remove `usePage` from the import and remove `const page = usePage();`.
- If `usePage` or `$page` is used elsewhere in the file, keep it.

**Verify per file** by checking if `page` or `$page` appears elsewhere in the template or script after the banner-related lines are removed.

---

## Commit

Single atomic commit for all changes:
```
feat: replace inline flash banners with toast notifications across 18 pages

ToastManager is globally mounted in all layouts and already watches
page.props.flash to convert flash.success and flash.error to toasts.
Remove redundant inline banner divs and their associated derived variables.
```
