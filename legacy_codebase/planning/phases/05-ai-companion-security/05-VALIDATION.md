---
phase: 05
slug: ai-companion-security
status: validated
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-14
validated: 2026-04-14
---

# Phase 05 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.x |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --filter=AiCompanion` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=AiCompanion`
- **After every plan wave:** Run `php artisan test --filter=AiCompanion`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | Rate limiting enforced per applicant (10 req/min) | threat_flag: dos_prevention | Per-user throttling via Laravel RateLimiter | integration | `php artisan test --filter=test_rate_limit_blocks_11th_request` | ✅ | ✅ green |
| 05-01-02 | 01 | 1 | Users receive friendly error when rate limited | — | JSON error response with message | integration | `php artisan test --filter=test_rate_limit_returns_friendly_error_message` | ✅ | ✅ green |
| 05-01-03 | 01 | 1 | Message length warnings appear at 1800+ chars | — | Warning in response JSON | integration | `php artisan test --filter=test_message_over_1800_chars_returns_warning` | ✅ | ✅ green |
| 05-01-04 | 01 | 1 | History limit warnings appear at 17+ messages | — | Warning in response JSON | integration | `php artisan test --filter=test_17_messages_returns_history_warning` | ✅ | ✅ green |
| 05-02-01 | 02 | 1 | Code generation requests are detected and blocked | — | containsCodeGenerationRequest() guardrail | integration | `php artisan test --filter=test_code_generation_request_blocked` | ✅ | ✅ green |
| 05-02-02 | 02 | 1 | Prompt injection attempts are detected and blocked | — | containsPromptInjection() guardrail | integration | `php artisan test --filter=test_prompt_injection_blocked` | ✅ | ✅ green |
| 05-02-03 | 02 | 1 | Unsafe content is detected and blocked | — | containsUnsafeContent() guardrail | integration | `php artisan test --filter=test_unsafe_content_blocked` | ✅ | ✅ green |
| 05-02-04 | 02 | 1 | Input is sanitized (strip_tags) before processing | — | sanitizeInput() method | integration | `php artisan test --filter=test_html_tags_are_stripped` | ✅ | ✅ green |
| 05-03-01 | 03 | 2 | Frontend displays warnings when approaching limits | — | Svelte warning state rendering | integration | `php artisan test --filter=test_warning_data_passed_to_frontend` | ✅ | ✅ green |
| 05-03-02 | 03 | 2 | Privacy notice displayed in chat interfaces | — | Privacy notice in component | integration | `php artisan test --filter=test_frontend_has_privacy_notice` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/Portal/AiCompanionRateLimitTest.php` — rate limiting tests
- [x] `tests/Feature/Portal/AiCompanionWarningTest.php` — warning threshold tests
- [x] `tests/Feature/Portal/AiCompanionGuardrailTest.php` — security guardrail tests
- [x] `tests/Feature/Portal/AiCompanionFrontendTest.php` — frontend integration tests

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

*All phase behaviors have automated verification.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-04-14