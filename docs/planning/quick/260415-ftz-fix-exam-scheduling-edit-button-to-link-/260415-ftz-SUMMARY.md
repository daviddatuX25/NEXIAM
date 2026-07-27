---
name: fix-exam-scheduling-edit-button
description: Fix exam scheduling edit button - only show in draft mode
status: complete
date: 2026-04-15
quick_id: 260415-ftz
---

## Summary

### Root Cause

Two issues were found:

1. **Route ordering bug** (`routes/web.php`): The wildcard `exam-scheduling/{exam_session}` show route was defined **before** the specific `exam-scheduling/{exam_session}/edit` route in the same middleware group. Laravel matches routes in definition order, so navigating to `/admin/exam-scheduling/4/edit` would match the wildcard first and route to `show()` instead of `edit()`.

2. **Overly broad visibility** (`Index.svelte`): The edit button used `!== 'in_progress' && !== 'cancelled' && !== 'completed'`, which showed it for both `draft` AND `published` sessions. Should only show for `draft`.

### Changes Made

**`routes/web.php`** — Moved the wildcard show route from the `super_admin,registrar_administrator,test_administrator,proctor` group (line 122) to the end of the `super_admin,registrar_administrator` group (after `destroy`), preserving all original role permissions:
- Removed: `Route::get('exam-scheduling/{exam_session}', ..., 'show')` from first group
- Added: Same route with expanded middleware at end of second group, with comment explaining placement

**`resources/js/Pages/Admin/TestScheduling/Index.svelte`** (line ~304):
```svelte
// Before
{#if !isProctorView && session.status !== 'in_progress' && session.status !== 'cancelled' && session.status !== 'completed'}

// After
{#if !isProctorView && session.status === 'draft'}
```

### Commits

- `410e0f2` fix: only show edit button for draft exam sessions
- `f5c93ef` fix: move exam-scheduling show route after specific routes
- `2f84574` docs(state): record quick task 260415-ftz

### Verification

- `/admin/exam-scheduling/{id}/edit` now correctly renders `Admin/TestScheduling/Edit.svelte` ✓
- Edit button only appears for sessions with `status === 'draft'` ✓
- `test_administrator` and `proctor` roles can still view session show pages ✓
