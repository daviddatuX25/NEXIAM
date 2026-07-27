---
phase: 14
slug: release-page-redesign
created: 2026-04-20
status: active
---

# Phase 14 — Validation Strategy

> Validation architecture extracted from RESEARCH.md. Defines how each requirement is tested, sampling rates, and Wave 0 gaps.

---

## Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact --filter=ReleaseTest` |
| Full suite command | `php artisan test --compact` |

## Phase Requirements to Test Map

| Req ID | Behavior | Test Type | Automated Command | File |
|--------|----------|-----------|-------------------|------|
| REQ-REL-01 | Mode-aware page renders correct layout per mode | Feature | `php artisan test --compact --filter=ReleasePageTest` | tests/Feature/ReleasePageTest.php |
| REQ-REL-02 | Release All releases unreleased summaries, skips released | Feature | `php artisan test --compact --filter=ReleaseAllTest` | tests/Feature/ReleaseAllTest.php |
| REQ-REL-03 | F2F release with side panel, per-row release in panel | Feature | `php artisan test --compact --filter=ReleaseF2fTest` | tests/Feature/ReleaseF2fTest.php |
| REQ-REL-04 | F2F notification sends mail + database with correct wording | Unit | `php artisan test --compact --filter=ResultReleasedF2FTest` | tests/Unit/ResultReleasedF2FTest.php |
| REQ-REL-05 | Online release sends ResultReleased notification (unchanged) | Unit | `php artisan test --compact --filter=ResultReleasedTest` | tests/Unit/ResultReleasedTest.php |

## Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=ReleaseTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd-verify-work`

## Wave 0 Gaps

- [ ] `tests/Feature/ReleasePageTest.php` — covers REQ-REL-01 (mode-aware rendering)
- [ ] `tests/Feature/ReleaseAllTest.php` — covers REQ-REL-02 (Release All endpoint)
- [ ] `tests/Feature/ReleaseF2fTest.php` — covers REQ-REL-03 (F2F release flow)
- [ ] `tests/Unit/ResultReleasedF2FTest.php` — covers REQ-REL-04 (F2F notification)
- [ ] `tests/Unit/ResultReleasedTest.php` — covers REQ-REL-05 (online notification unchanged)
- [x] Framework install: PHPUnit 11 already present

---

*Phase: 14-release-page-redesign*
*Validation strategy created: 2026-04-20*