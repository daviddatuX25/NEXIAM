# Phase 4: Applicant AI Companion Chat Interface - Research

**Researched:** 2026-04-14
**Domain:** Laravel + Svelte + Inertia portal chat widget
**Confidence:** HIGH

## Summary

This phase requires implementing a floating expandable chat widget that appears on all applicant portal pages when AI Companion mode is enabled. The existing `AiCompanionService` and endpoints already handle chat functionality — the primary work is creating a mountable Svelte widget component and integrating it into `PortalLayout.svelte`.

**Primary recommendation:** Create a new `AiCompanionChatWidget.svelte` component that mirrors the chat logic from the full-page `AiCompanion.svelte`, then conditionally render it in `PortalLayout.svelte` based on the `ai_companion_enabled` prop passed from the backend.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Fixed bottom-right corner position — standard chat widget placement
- **D-02:** Expandable design — floating button that expands to full chat panel on click

### Claude's Discretion
- Animation details (smooth expand/collapse transitions)
- Color scheme (use existing toast system colors or introduce new)
- Panel sizing (width, height when expanded)
- Header/footer content (title, clear history button, close button)

### Deferred Ideas (OUT OF SCOPE)
[None — discussion stayed within phase scope]
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| (No IDs mapped yet) | Floating chat widget on portal pages | AiCompanionService chat() method, existing API endpoints, PortalLayout integration |
</phase_requirements>

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel 12 | 12.x | Backend framework | [VERIFIED: project uses Laravel 12] |
| Inertia.js v2 | 2.x | Server-side rendering | [VERIFIED: project uses Inertia v2] |
| Svelte 5 | 5.x | Frontend framework | [VERIFIED: project uses Svelte 5 with $state/$derived] |
| lucide-svelte | latest | Icon library | [VERIFIED: existing use in AiCompanion.svelte] |
| shadcn-svelte | latest | UI components | [VERIFIED: project uses shadcn-svelte] |

### No Additional Packages Required
This phase reuses existing infrastructure — no new npm packages needed.

---

## Architecture Patterns

### Recommended Project Structure
```
resources/js/
├── Components/
│   └── AiCompanionChatWidget.svelte    # NEW: Floating chat widget
├── Layouts/
│   └── PortalLayout.svelte           # MODIFIED: Conditionally render widget
```

### Pattern 1: Floating Chat Widget Component

**What:** Self-contained Svelte component with two states (collapsed FAB, expanded panel)

**When to use:** When AI companion is enabled and consultation results are released

**Implementation approach:**
- Uses `$state` for messages, input, loading, expanded state
- Fetches CSRF token from `$page.props.csrf_token`
- Calls existing `/portal/ai-companion/chat` endpoint
- Call existing `/portal/ai-companion/clear-history` for clear

**Code structure (based on AiCompanion.svelte lines 15-83):**
```svelte
// Source: Adapted from resources/js/Pages/Portal/AiCompanion.svelte
<script>
  import { usePage } from '@inertiajs/svelte';
  import { MessageSquare, Send, X, Trash2 } from 'lucide-svelte';

  let { ai_companion_enabled = false, initialMessages = [] } = $props();

  let messages = $state([...(initialMessages ?? [])]);
  let input = $state('');
  let loading = $state(false);
  let error = $state('');
  let expanded = $state(false);

  const page = usePage();
  const csrf_token = $derived($page.props.csrf_token ?? '');

  async function send() {
    // ... same send() logic as AiCompanion.svelte lines 15-53
  }

  async function clearHistory() {
    // ... same clearHistory() logic as AiCompanion.svelte lines 63-83
  }
</script>
```

### Pattern 2: PortalLayout Integration

**What:** Conditionally render the chatbot widget based on backend prop

**When to use:** In PortalLayout.svelte, pass `ai_companion_enabled` from dashboard

**Integration point:**
- Backend passes `ai_companion_enabled` in dashboard response (line 292 of PortalAuthController.php)
- Layout checks prop and renders `<AiCompanionChatWidget />` when true

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Chat API | Custom endpoint | Existing `/portal/ai-companion/chat` | Already implemented in AiCompanionController.php |
| Chat state | New database table | Existing `ai_companion_messages` table | AiCompanionService handles persistence |
| CSRF handling | Custom token logic | `$page.props.csrf_token` | Standard Inertia pattern |

**Key insight:** The full-page implementation already exists — extract the chat logic into a reusable component.

---

## Runtime State Inventory

> This phase is a **new feature** (not a rename/refactor), so runtime state is minimal.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | `ai_companion_messages` table already exists | None — reuses existing |
| Live service config | `ai_exam_companion_enabled` system setting | None — exists and checked |
| OS-registered state | None | None |
| Secrets/env vars | None | None |
| Build artifacts | None | None |

**Nothing found in category:** All required infrastructure already in place.

---

## Common Pitfalls

### Pitfall 1: Widget Appears When Results Not Released
**What goes wrong:** Widget shows even when consultation summary is not released
**Why it happens:** Backend passes `ai_companion_enabled` false when feature disabled or results not released
**How to avoid:** Check both conditions: feature enabled AND `$page.props.auth?.applicant?.consultation_summary_status === 'released'`
**Warning signs:** Applicant sees widget but gets 403 errors on chat requests

**Mitigation:** The AiCompanionController already checks both conditions (lines 64-75), but frontend should also verify presence of `consultation` prop with `status === 'released'` before rendering.

### Pitfall 2: CSRF Token Not Available
**What goes wrong:** Chat requests fail with 419 error
**Why it happens:** CSRF token not passed to widget component
**How to avoid:** Extract from `$page.props.csrf_token` — verify it's passed in props
**Warning signs:** POST requests fail with "CSRF token mismatch"

### Pitfall 3: Duplicate Chat History
**What goes wrong:** Messages appear twice or history doesn't persist across navigation
**Why it happens:** Widget doesn't load initial messages from backend
**How to avoid:** Fetch initial messages on component mount or pass from parent page
**Warning signs:** Chat history resets on each page navigation

---

## Code Examples

### Existing Full-Page Chat (Reference)
```svelte
// Source: resources/js/Pages/Portal/AiCompanion.svelte lines 15-53
async function send() {
  const text = input.trim();
  if (!text || loading) return;

  input = '';
  error = '';
  messages = [...messages, { role: 'user', content: text }];
  loading = true;

  try {
    const res = await fetch('/portal/ai-companion/chat', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf_token,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ message: text }),
      credentials: 'same-origin',
    });

    const data = await res.json().catch(() => {});

    if (!res.ok) {
      error = data.message ?? 'Something went wrong. Please try again.';
      messages = messages.filter((m) => m.role !== 'user' || m.content !== text);
      return;
    }

    if (data.reply) {
      messages = [...messages, { role: 'assistant', content: data.reply }];
    }
  } catch (e) {
    error = 'Network error. Please try again.';
    messages = messages.filter((m) => m.role !== 'user' || m.content !== text);
  } finally {
    loading = false;
  }
}
```

### Visibility Check Pattern (from PortalAuthController.php lines 272-276)
```php
// Source: app/Http/Controllers/PortalAuthController.php lines 272-276
// R7 — If f2f mode, hide result data from the portal
$releaseMode = SystemSetting::releaseMode();
if ($releaseMode === 'f2f') {
    $consultation = ['status' => 'pending', 'summary' => null];
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Full-page only | Floating widget on all pages | This phase | Enables contextual chat everywhere |

**No deprecation required** — The full-page route remains available.

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `PortalLayout.svelte` already receives `ai_companion_enabled` from dashboard | PortalAuthController line 292 | LOW — verified in code |
| A2 | Consultation summary status check happens on backend | AiCompanionController lines 72-75 | LOW — verified in code |
| A3 | CSRF token is available via `$page.props.csrf_token` | Inertia standard | LOW — verified in existing components |

**All claims verified** — No user confirmation needed.

---

## Open Questions

1. **Should the widget load existing chat history?**
   - What we know: The backend provides message history via `AiCompanionMessage::lastForApplicant()`
   - What's unclear: Whether to pass initial messages to widget or fetch lazily
   - Recommendation: Pass initial messages from dashboard for instant state restoration

2. **Should widget persist expanded state across page navigation?**
   - What we know: Inertia SPA maintains client-side state
   - What's unclear: User expectation
   - Recommendation: Keep widget mounted in layout — state persists automatically

---

## Environment Availability

> This phase involves code/config changes only — no external tool dependencies beyond existing stack.

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.2 | Laravel | ✓ | 8.2 | — |
| Node.js | Frontend build | ✓ | 20.x | — |
| Composer packages | Laravel | ✓ | Latest | — |

**All dependencies available.**

---

## Validation Architecture

> This section included because workflow.nyquist_validation is enabled (default).

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| N/A | Widget renders when AI enabled | Feature | `php artisan test --filter=ai_companion` | ❌ Wave 0 |
| N/A | Widget hidden when AI disabled | Feature | `php artisan test --filter=ai_companion` | ❌ Wave 0 |
| N/A | Chat sends message successfully | Feature | `php artisan test --filter=ai_companion` | ❌ Wave 0 |
| N/A | Chat handles errors gracefully | Feature | `php artisan test --filter=ai_companion` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** Quick test filter
- **Per wave merge:** Full suite
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/PortalAiCompanionChatTest.php` — covers chat widget functionality (NEW)
- [ ] Tests for visibility toggle based on AI enabled status

---

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | Yes | Laravel auth guard (existing) |
| V3 Session Management | Yes | Laravel session (existing) |
| V4 Access Control | Yes | Auth guard + consultation status check |
| V5 Input Validation | Yes | AiCompanionChatRequest validation |
| V6 Cryptography | No | No crypto needed |

### Known Threat Patterns for This Stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| XSS in chat messages | Repudiation | Output escaping via Svelte (default) |
| CSRF on chat endpoint | Tampering | Laravel CSRF token (existing) |
| Unauthorized access | Spoofing | Auth guard + consultation status (existing) |

---

## Sources

### Primary (HIGH confidence)
- `app/Services/AiCompanionService.php` — Service implementation with chat(), clearHistory()
- `app/Http/Controllers/Portal/AiCompanionController.php` — HTTP endpoints
- `resources/js/Pages/Portal/AiCompanion.svelte` — Full-page chat reference
- `resources/js/Layouts/PortalLayout.svelte` — Layout integration point

### Secondary (MEDIUM confidence)
- `app/Http/Controllers/PortalAuthController.php` — ai_companion_enabled prop passing
- `app/Models/SystemSetting.php` — aiCompanionEnabled() method

### Tertiary (LOW confidence)
- [None required — verified from codebase]

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — verified existing infrastructure
- Architecture: HIGH — clear patterns from existing code
- Pitfalls: MEDIUM — identified from backend implementation

**Research date:** 2026-04-14
**Valid until:** 2026-05-14 (30 days for stable feature)