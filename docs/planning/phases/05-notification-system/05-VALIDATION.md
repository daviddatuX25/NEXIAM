---
phase: 05
slug: notification-system
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-13
---

# Phase 05 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit v11 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --filter=NotificationTest` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=NotificationTest`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 05-01 | 1 | D-01 | T-05-01 | Notifications stored in database | Integration | `php artisan test --filter=NotificationDatabaseTest` | ❌ W0 | ⬜ pending |
| 05-01-02 | 05-01 | 1 | D-03 | T-05-01 | Poll endpoint returns notifications | Integration | `php artisan test --filter=NotificationPollTest` | ❌ W0 | ⬜ pending |
| 05-01-03 | 05-01 | 1 | D-04 | T-05-02 | ApplicationStatusChanged sent | Unit | `php artisan test --filter=ApplicationStatusNotificationTest` | ❌ W0 | ⬜ pending |
| 05-02-01 | 05-02 | 2 | D-02 | — | Bell icon shows unread count | Component | Manual browser test | N/A | ⬜ pending |
| 05-03-01 | 05-03 | 3 | D-05 | T-05-03 | ResultReleased triggered | Unit | `php artisan test --filter=GradingNotificationTest` | ❌ W0 | ⬜ pending |
| 05-03-02 | 05-03 | 3 | D-06 | T-05-03 | ExamSessionPublished triggered | Unit | `php artisan test --filter=SchedulingNotificationTest` | ❌ W0 | ⬜ pending |
| 05-03-03 | 05-03 | 3 | D-07 | T-05-04 | ExamSessionReminder sent | Unit | `php artisan test --filter=ExamReminderTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/NotificationSystemTest.php` — covers D-01, D-03, D-04, D-05, D-06
- [ ] `tests/Unit/Notifications/ApplicationStatusChangedTest.php` — covers D-04
- [ ] `tests/Unit/Notifications/ExamSessionReminderTest.php` — covers D-07
- Framework install: Already installed via composer

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Bell icon dropdown renders correctly | D-02 | Visual UI verification requires browser | Visit any authenticated page, click bell icon, verify dropdown appears with notification list |

*If none: "All phase behaviors have automated verification."*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
