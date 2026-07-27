---
id: 260415-rhz
status: complete
date: 2026-04-15
commit: e6ed533
---

# Quick Task 260415-rhz: Convert Inline Flash Banners to Toasts

## What was done

Removed all inline flash message banner divs from 18 Svelte pages and cleaned up their associated derived variables and unused `usePage` imports.

`ToastManager` is globally mounted in all layouts and already watches `page.props.flash` via `page.subscribe()` in onMount — converting `flash.success` and `flash.error` to toasts automatically. The inline banners were therefore redundant.

## Files changed (19 files, -170 lines)

| File | Change |
|------|--------|
| Applications/Show.svelte | Remove success + error banners |
| Admin/AiCompanion/Index.svelte | Remove flash.success banner |
| Admin/Settings/Index.svelte | Remove flash.success banner |
| Admin/AcademicYears/Index.svelte | Remove dead `success` prop + successMsg derived + banner |
| Admin/Rooms/Index.svelte | Remove success + error banners |
| Admin/Logs/Index.svelte | Remove flash error; preserve `exportError` inline (local state) |
| Admin/TestAdmin/Index.svelte | Remove success + error banners |
| Admin/TestScheduling/Show.svelte | Remove success + error banners |
| Admin/TestScheduling/Index.svelte | Remove success banner |
| Admin/Users/Index.svelte | Remove success banner |
| Admin/AdmissionSlipTemplates/Index.svelte | Remove success banner |
| Admin/Courses/Index.svelte | Remove success banner |
| Admin/AptitudeAreas/Index.svelte | Remove success banner |
| Admin/ResultSheetTemplates/Index.svelte | Remove success banner |
| Admin/KnowledgeDocuments/Index.svelte | Remove success banner |
| Grading/Dashboard.svelte | Remove success banner |
| Grading/Session.svelte | Remove success banner |
| Auth/Login.svelte | Remove glass-panel flash banners |
| Portal/Login.svelte | Remove unused flash derived variable |

## Intentionally excluded

- Component-internal errors (Import.svelte, ImportPreview.svelte, AiCompanion.svelte) — local state, not flash
- Form validation errors — stay beside input fields
- Portal/ForgotPassword.svelte and Release/Index.svelte — outside original scope
