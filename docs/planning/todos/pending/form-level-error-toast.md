---
title: Replace form-level error banner with toast notifications
date: 2026-04-14
priority: medium
status: completed
---

## Context

Error handling audit during /gsd-explore session. Found inconsistent patterns:

- Field-specific errors → shown inline under each field (correct, keep)
- Form-level errors → shown with "Please fix the errors below" banner (should use toast instead)

## What

Replace `{#if Object.keys($form.errors || {}).length > 0 && !$form.processing}` error banner with toast notification for form-level errors that aren't field-specific.

Files affected:
- `resources/js/Pages/Admin/Applications/Create.svelte`
- `resources/js/Pages/Admin/Applications/Edit.svelte`
- `resources/js/Pages/Admin/AcademicYears/Create.svelte`
- (check for others)

## Implementation

1. Remove the error banner block from each form
2. For form-level validation errors (e.g., server returns a general message), call `error()` toast instead
3. Keep field-level errors inline as they are

## Why

- "Please fix the errors below" doesn't tell users WHICH fields are wrong
- Field-specific errors already show inline — no need for duplicate summary
- Toast is consistent with other notifications in the app