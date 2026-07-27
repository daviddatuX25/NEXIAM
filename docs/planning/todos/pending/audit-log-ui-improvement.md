---
title: Audit Log UI/UX — Friendly Event Labels
date: 2026-04-13
priority: medium
status: completed
---

## Summary

Implement automatic friendly label translation for audit log events so non-developers see human-readable descriptions instead of technical event names like `user.login`.

## Approach

Auto-translate events in the service layer. Add mapping that generates friendly descriptions automatically — no extra effort from developers when logging new events.

## Implementation

1. ✅ Add event-to-label mapping in AuditService (PHP) — 35 event types mapped
2. ✅ Add method to generate summary from event + values — auto-generates if not provided
3. ✅ Update AuditLogController to pass translated labels to UI
4. ✅ Svelte already uses labels correctly
5. ✅ Filter dropdowns use friendly labels

## Changes Made

- `app/Services/AuditService.php`: Added `$eventLabels` and `$categoryLabels` mappings, plus `getEventLabel()`, `getCategoryLabel()`, `getEventOptions()`, `getCategoryOptions()`, and `generateSummary()` methods
- `app/Http/Controllers/Admin/AuditLogController.php`: Uses AuditService for labels