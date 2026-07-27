---
status: testing
phase: 14-release-page-redesign
source: 14-01-SUMMARY.md, 14-02-SUMMARY.md
started: 2026-04-20T12:00:00Z
updated: 2026-04-20T12:00:00Z
---

## Current Test
<!-- OVERWRITE each test - shows where we are -->

number: 1
name: Cold Start Smoke Test
expected: |
  Kill any running server. Start the application from scratch. Server boots without errors, and the release page (/admin/release/summaries) loads without error.
awaiting: user response

## Tests

### 1. Cold Start Smoke Test
expected: Kill any running server. Start the application from scratch (php artisan serve + npm run dev). Server boots without errors, and the release page (/admin/release/summaries) loads displaying the page without error.
result: pending

### 2. Release Page - Online Mode Layout
expected: When system setting release_context is "online", the release page shows a read-only summary table with a "Release All" button (if unreleased summaries exist). No checkboxes appear. No per-row Release button appears. Each row shows applicant and consultation summary data.
result: pending

### 3. Release Page - F2F Mode Layout
expected: When system setting release_context is "f2f", the release page shows a checkbox table with per-row Edit and Release buttons. A Release button appears in the side panel. No "Release All" button appears.
result: pending

### 4. Release Page - Both Mode Tabs
expected: When system setting release_context is "both", the release page shows two tabs: "Online" and "F2F". Clicking each tab switches between the corresponding summary tables. Each tab has independent pagination.
result: pending

### 5. Release All Confirmation Dialog
expected: In Online mode, clicking "Release All" opens a confirmation dialog showing the count of unreleased summaries and a "cannot be undone" warning message.
result: pending

### 6. Release All Execution
expected: Confirming the Release All dialog releases all unreleased online summaries. Their status changes to "released" and applicants receive ResultReleased notifications (with "View in Portal" action).
result: pending

### 7. F2F Per-Row Release
expected: In F2F mode, clicking Release on a row (or selecting checkboxes and clicking Release in side panel) releases the selected consultation summary. The applicant receives a ResultReleasedF2F notification with face-to-face consultation messaging (no "View in Portal" action).
result: pending

### 8. Tab Switch Resets Selection
expected: In Both mode, selecting checkboxes on one tab, then switching to the other tab clears the checkbox selection. Switching back shows no remembered selections.
result: pending

### 9. F2F Notification Content
expected: F2F-released applicants see a notification with face-to-face consultation wording (no "View in Portal" link), while online-released applicants see a notification with a "View in Portal" action link.
result: pending

## Summary

total: 9
passed: 0
issues: 0
pending: 9
skipped: 0
blocked: 0

## Gaps

[none yet]