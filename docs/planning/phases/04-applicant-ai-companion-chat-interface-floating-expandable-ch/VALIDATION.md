# Phase 04: Validation Plan

**Phase:** 04-applicant-ai-companion-chat-interface-floating-expandable-ch
**Created:** 2026-04-14

---

## Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact` |
| Full suite command | `php artisan test` |

---

## Phase Requirements to Test Map

| Req ID | Behavior | Test Type | Automated Command | Status |
|--------|----------|-----------|-------------------|--------|
| 04-FW-01 | Widget renders when AI enabled | Feature | `php artisan test --filter=ai_companion` | Pending |
| 04-FW-01 | Widget hidden when AI disabled | Feature | `php artisan test --filter=ai_companion` | Pending |
| 04-FW-01 | Chat sends message successfully | Feature | `php artisan test --filter=ai_companion` | Pending |
| 04-FW-01 | Chat handles errors gracefully | Feature | `php artisan test --filter=ai_companion` | Pending |
| 04-FW-02 | Widget persists across page navigation | Feature | `php artisan test --filter=portal_layout` | Pending |
| 04-FW-02 | ai_companion_enabled dual-check (system + release) | Feature | `php artisan test --filter=ai_companion_enabled` | Pending |

---

## Wave 0 Test Scaffold

### tests/Feature/PortalAiCompanionChatTest.php

Tests to create during Wave 0 (before phase execution):
1. `test_widget_renders_when_ai_companion_enabled` — verifies widget appears with ai_companion_enabled=true
2. `test_widget_hidden_when_ai_companion_disabled` — verifies widget does not appear with ai_companion_enabled=false
3. `test_chat_sends_message_successfully` — verifies POST to /portal/ai-companion/chat returns reply
4. `test_chat_handles_api_errors_gracefully` — verifies error state displayed on API failure
5. `test_clear_history_clears_messages` — verifies POST to /portal/ai-companion/clear-history works
6. `test_widget_appears_on_all_portal_pages` — verifies widget present in PortalLayout
7. `test_ai_companion_enabled_reflects_consultation_status` — verifies dual-check logic in controller

---

## Verification Schedule

| Wave | Plans | Verification Point |
|------|-------|---------------------|
| Wave 1 | 04-01 | Human verify: Widget FAB visible, expands on click, chat works |
| Wave 2 | 04-02 | Human verify: Widget appears on all portal pages |
| Wave 3 | 04-03 | Automated: Controller logic, Pint formatting |
| Phase Gate | All | Full suite: `php artisan test --compact` |

---

## Sampling Rates

- **Per task commit:** Quick test filter (`--filter=<test_name>`)
- **Per wave merge:** Full suite subset (`--filter=ai_companion`)
- **Phase gate:** Full suite green before `/gsd-verify-work`
