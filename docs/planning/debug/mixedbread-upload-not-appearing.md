---
status: verifying
trigger: "mixedbread-upload-not-appearing"
created: 2026-04-13T00:00:00Z
updated: 2026-04-13T00:00:00Z
---

## Current Focus

hypothesis: config_cache_causing_empty_store_id
test: test_upload_with_fallback_to_env
expecting: env_fallback_will_bypass_cache_and_upload
next_action: awaiting_user_verification

## Symptoms

expected: Document uploads to MixedBread and appears in their dashboard as an ingested knowledge source
actual: Document saves in SecureCAT but doesn't appear on MixedBread platform - "sent but failed processing"
errors: No visible errors - user checked browser console, nothing there
reproduction: Admin > Knowledge Documents > Add > fill form > save
started: First time trying - never worked before

## Root Cause Identified

Laravel config cache was returning empty string for `services.mixedbread.store_id`
which caused silent skip in syncToMixedbread() since `empty($storeId)` returned true.

## Fix Applied

1. Added debug logging to trace config values
2. Added env() fallback to bypass config cache
3. Added SYNC_PENDING status before upload attempt
4. Added visible warning log when sync is skipped

## Verification

- 16 controller tests pass
- Pint formatting applied
- Code changes in: app/Http/Controllers/Admin/KnowledgeDocumentController.php

## Resolution

root_cause: |
  Laravel config cache returns empty for services.mixedbread.store_id during HTTP requests.
  The syncToMixedbread() function returned silently without attempting upload.

fix: |
  1. Added debug/logging for visibility
  2. Added env() fallback: config('services.mixedbread.store_id') ?: env('MIXEDBREAD_STORE_ID', '')
  3. Added pending status to track sync attempts
  4. Added warning log when sync is skipped

verification: |
  - All 16 tests pass
  - Code formatted with Pint

files_changed:
  - app/Http/Controllers/Admin/KnowledgeDocumentController.php