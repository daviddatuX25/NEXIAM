---
name: result-sheet-templates-under-release-summary
date: 2026-04-15
quick_id: 260415-c7o
phase: quick
plan: 260415-c7o
---

# Phase Quick Plan 260415-c7o: Move Result Sheet Templates Under Release Summary

## Objective

Move Result Sheet Templates page from being a standalone item under Guidance Office to nested child under Release section in the sidebar navigation.

## Tasks Completed

| Task | Name | Status |
|------|------|--------|
| 1 | Update routes for nested result-sheet-templates | complete |
| 2 | Update sidebar navigation | complete |
| 3 | Update breadcrumbs in Result Sheet Templates pages | complete |
| 4 | Verify all links updated | complete |

## Files Modified

### Routes
- `routes/web.php` - Moved result-sheet-templates routes from `admin` prefix to `release` prefix group

### Controller
- `app/Http/Controllers/Admin/ResultSheetTemplateController.php` - Updated redirect routes from `admin.result-sheet-templates.index` to `release.result-sheet-templates.index`

### Frontend - Layout
- `resources/js/Layouts/AuthenticatedLayout.svelte` - Converted Release to nested items with Result Sheet Templates as child

### Frontend - Pages
- `resources/js/Pages/Admin/ResultSheetTemplates/Index.svelte` - Updated all links from `/admin/result-sheet-templates` to `/release/result-sheet-templates`, updated breadcrumbs
- `resources/js/Pages/Admin/ResultSheetTemplates/Create.svelte` - Updated all links, updated breadcrumbs
- `resources/js/Pages/Admin/ResultSheetTemplates/Edit.svelte` - Updated all links, updated breadcrumbs

### Frontend - Grading Links
- `resources/js/Pages/Grading/ResultSheetBulk.svelte` - Updated error link to result templates
- `resources/js/Pages/Grading/ResultSheet.svelte` - Updated error link to result templates

## Key Changes

1. **Route structure**: Result Sheet Templates now nested under `/release` prefix with route name `release.result-sheet-templates.index`
2. **Sidebar**: Release section now shows nested items: "Release Management" and "Result Sheet Templates"
3. **Breadcrumbs**: All pages now show `Release Management > Result Sheet Templates` hierarchy

## Verification

- Routes verified: result-sheet-templates routes now under `/release` prefix
- No remaining `/admin/result-sheet-templates` links in production code
- All controller redirect routes updated to new naming convention