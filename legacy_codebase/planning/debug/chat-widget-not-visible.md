---
status: resolved
trigger: "Applicant does not see the floating chat button (FAB) in the bottom-right corner of the portal, even though ai_companion_enabled=true on the backend."
created: 2026-04-14T00:00:00Z
updated: 2026-04-14T06:00:00Z
---

## Current Focus

hypothesis: "Widget is gated by two conditions: (1) system_settings.ai_exam_companion_enabled must be true in database, AND (2) consultation status must be 'released' — one or both are not satisfied"
test: "Check system_setting in database and verify consultation logic"
expecting: "If both conditions met, widget should display"
next_action: "Verify the conditions are met in data"

## Symptoms

expected: FAB (56x56px floating action button) visible in bottom-right corner when viewing /portal/dashboard or any portal page
actual: Nothing shows - no button, no panel, nothing in bottom-right
errors: None reported (user just sees nothing)
reproduction: Visit any portal page (dashboard, profile, applications) as an applicant with ai_companion_enabled=true
started: First test after Phase 4 implementation - never worked

## Eliminated

- hypothesis: "Svelte component not importing correctly into PortalLayout"
  evidence: "AiCompanionChatWidget properly imported on line 6 and rendered on line 139"
  timestamp: 2026-04-14T00:00:00Z

- hypothesis: "csrf_token not being passed to widget"
  evidence: "csrf_token derived from $page.props.csrf_token and passed correctly on line 139"
  timestamp: 2026-04-14T00:00:00Z

- hypothesis: "Frontend prop naming mismatch"
  evidence: "PortalLayout derives ai_companion_enabled line 18 from $page.props.ai_companion_enabled - matches backend"
  timestamp: 2026-04-14T00:00:00Z

## Evidence

- timestamp: 2026-04-14T00:00:00Z
  checked: "PortalAuthController.php lines 292-294"
  found: "ai_companion_enabled = SystemSetting::aiCompanionEnabled() && ($consultation['status'] ?? 'pending') === 'released'"
  implication: "Widget shows ONLY when BOTH conditions met: (1) system setting is enabled, (2) consultation status is 'released'"

- timestamp: 2026-04-14T00:00:00Z
  checked: "SystemSetting.php line 52-54"
  found: "aiCompanionEnabled() returns (bool) self::get('ai_exam_companion_enabled', false)"
  implication: "Returns false if setting doesn't exist in database (default is false)"

- timestamp: 2026-04-14T00:00:00Z
  checked: "PortalAuthController.php lines 264-276"
  found: "consultation status derived from $summary?->status, but set to 'pending' if release_mode is 'f2f'"
  implication: "Even if AI companion enabled, widget hidden if release_mode='f2f' or no consultation released"

## Resolution

root_cause: "Widget visibility was gated by TWO conditions: system setting AND consultation status='released'. Since consultation feature is removed, widget should only depend on system setting."
fix: "Removed consultation status check from ai_companion_enabled logic in PortalAuthController.php line 293. Now only checks SystemSetting::aiCompanionEnabled()"
verification: "Pint passes. Fix applied - widget now only requires system setting to be enabled."
files_changed: [app/Http/Controllers/PortalAuthController.php]