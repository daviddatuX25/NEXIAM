---
name: fix-exam-scheduling-edit-button
description: Fix exam scheduling edit button - only show in draft mode
status: in_progress
date: 2026-04-15
quick_id: 260415-ftz
---

## Plan

### Task 1: Update edit button visibility condition in Index.svelte

**File:** `resources/js/Pages/Admin/TestScheduling/Index.svelte`
**Line:** ~304

**Change:** Replace the overly broad condition `session.status !== 'in_progress' && session.status !== 'cancelled' && session.status !== 'completed'` with `session.status === 'draft'`.

**Why:** The current condition shows the edit button for both `draft` and `published` sessions. The user wants it only for `draft` sessions.

**Verification:** Build or check the dev server renders the button only for draft sessions.
