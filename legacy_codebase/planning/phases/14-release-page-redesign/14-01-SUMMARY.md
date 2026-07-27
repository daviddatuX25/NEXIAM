---
phase: 14-release-page-redesign
plan: 01
status: complete
completed: 2026-04-20
---

## Plan 14-01: Backend Notification + Endpoints

### What was built
- `ResultReleasedF2F` notification class — sends mail + database notifications with F2F-specific wording (no "View in Portal" action, face-to-face consultation messaging)
- `ReleaseController::releaseAll()` — one-click bulk release of all unreleased summaries; blocked in F2F mode; sends ResultReleased per applicant
- Context-aware dispatch in `release()` and `releaseBulk()` — reads `release_context` from request; sends ResultReleasedF2F for 'f2f', ResultReleased otherwise; defaults to system mode in 'both'
- Mode-aware `index()` — returns `online_summaries` + `f2f_summaries` in 'both' mode, `summaries` in single modes
- Route `POST /admin/release/summaries/release-all` inside admin/release group

### Files created
- `app/Notifications/ResultReleasedF2F.php`
- `database/factories/ConsultationSummaryFactory.php`
- `tests/Unit/Notifications/ResultReleasedF2FTest.php` (4 tests)
- `tests/Feature/ReleaseAllTest.php` (8 tests)

### Files modified
- `app/Http/Controllers/ReleaseController.php` — added releaseAll(), context-aware dispatch, mode-aware index()
- `app/Models/ConsultationSummary.php` — added HasFactory trait
- `routes/web.php` — added release-all route

### Test results
- ResultReleasedF2FTest: 4 passed
- ReleaseAllTest: 8 passed
- Routes verified: admin.release.summaries.release-all registered

### Key design decisions
- `release_context` defaults to 'online' in 'both' mode when not provided — safe default
- `releaseAll()` uses WHERE status != 'released' to silently skip already-released rows
- Both mode returns two independent paginated datasets for independent tab pagination