---
title: Release Page Redesign
date: 2026-04-20
priority: high
context: Phase 13 — exam session workflow and notification enhancements
---

# Release Page Redesign

## Tasks

### Backend
- [ ] Update `ReleaseController::index()` to pass mode-aware payload (separate online/f2f datasets for 'both' mode tabs)
- [ ] Add `releaseAll()` endpoint for online mode — releases all unreleased summaries in one call
- [ ] Create `ResultReleasedF2F` notification class — in-app + email with F2F wording ("Your results are available for face-to-face consultation. Please visit the guidance office.")
- [ ] Update `ReleaseController::release()` to send F2F notification when release_mode is 'f2f' (currently sends nothing)
- [ ] Update `ReleaseController::releaseBulk()` same — F2F notification support

### Frontend
- [ ] Redesign `Release/Index.svelte` with mode-aware layout:
  - **Online mode:** Read-only consultation data table + "Release All" header button + confirmation dialog
  - **F2F mode:** Checkbox table + side panel per row for consultation notes + bulk release action
  - **Both mode:** Tab-based views — "Online" tab and "F2F" tab
- [ ] Online tab: hide edit/release per-row actions, show read-only course + comments
- [ ] F2F tab: keep existing side panel pattern for individual notes, add bulk select + release
- [ ] Wire "Release All" button to new `releaseAll()` endpoint
- [ ] Add success/error feedback for bulk and all-release actions

### Notifications
- [ ] Create `ResultReleasedF2F` notification (mail + database channels, F2F-specific subject/body)
- [ ] Ensure online release continues using existing `ResultReleased` notification