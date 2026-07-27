# Debug: file-upload-no-events-on-result-sheet-templates-create

**Status:** RESOLVED  
**Date:** 2026-04-15

## Symptoms

- FileUpload component on `/admin/result-sheet-templates/create` showed no events
- No console logs at all (not even MODULE LOADED at top of script)
- Component rendered visually but with light (non-dark) theme
- Both click and drag did nothing
- No JS errors shown in console

## Root Cause

`createEventDispatcher` is **explicitly forbidden in Svelte 5 runes mode**.

The `file-upload.svelte` component used Svelte 5 runes (`$props()`, `$state()`, `$derived()`, `$bindable()`) alongside Svelte 4's `createEventDispatcher`. This caused a **Svelte 5 compile-time error** on every hot-reload attempt.

Because the Svelte compiler failed on every HMR update, Vite kept serving the **last successfully compiled (old) version** of the component — which:
- Had no `MODULE LOADED` console log (log was added after the last good compile)
- Used the old Svelte 4 event dispatching approach (no `onfiles` callback)
- Used hardcoded light `oklch(1 0 0)` CSS instead of CSS variables

## Fix Applied

**File:** `resources/js/Components/ui/file-upload/file-upload.svelte`

1. Removed `import { createEventDispatcher } from 'svelte'`
2. Removed `const dispatch = createEventDispatcher()`
3. Removed all `dispatch(...)` calls
4. Added `onerror` as a callback prop (replaces dispatched error event)
5. Used optional chaining `onfiles?.()` / `onerror?.()` for safe invocation
6. Replaced all hardcoded `oklch(...)` CSS values with `hsl(var(--...))` CSS variables for proper dark mode support

## Callers — Compatibility Verified

| File | Usage | Compatible? |
|------|-------|-------------|
| `Grading/Import.svelte` | `bind:files={selectedFile}` | ✅ `$bindable` unchanged |
| `Applications/Import.svelte` | `bind:files={selectedFile}` | ✅ `$bindable` unchanged |
| `KnowledgeDocuments/Import.svelte` | `bind:files={selectedFile}` | ✅ `$bindable` unchanged |
| `ResultSheetTemplates/Create.svelte` | `onfiles={handleDocxFile}` with `e?.files?.[0]` | ✅ |
| `ResultSheetTemplates/Edit.svelte` | `onfiles` with `e.detail?.files?.[0] ?? e.files?.[0]` | ✅ fallback already present |
