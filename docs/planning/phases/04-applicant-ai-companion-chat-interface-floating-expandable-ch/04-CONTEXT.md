# Phase 04: Applicant AI Companion Chat Interface - Context

**Gathered:** 2026-04-14
**Status:** Ready for planning

<domain>
## Phase Boundary

A floating expandable chat widget that appears on all applicant portal pages (not just a dedicated page) when AI Companion mode is enabled. Applicants can chat with the AI assistant from anywhere in the portal.

</domain>

<decisions>
## Implementation Decisions

### Widget Position
- **D-01:** Fixed bottom-right corner position — standard chat widget placement, consistent with modern UX patterns

### Widget Visibility
- **D-02:** Expandable design — floating button that expands to full chat panel on click

### Claude's Discretion
- Animation details (smooth expand/collapse transitions)
- Color scheme (use existing toast system colors or introduce new)
- Panel sizing (width, height when expanded)
- Header/footer content (title, clear history button, close button)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing AI Services
- `app/Services/AiCompanionService.php` — Core chat service with `chat()`, `clearHistory()`, `buildSystemPrompt()`
- `app/Services/KnowledgeRetrievalService.php` — Knowledge retrieval for RAG
- `app/Http/Controllers/Portal/AiCompanionController.php` — HTTP endpoints

### Existing Frontend
- `resources/js/Pages/Portal/AiCompanion.svelte` — Full-page chat reference implementation
- `resources/js/Pages/Admin/AiCompanion/Index.svelte` — Admin knowledge management
- `resources/js/Components/ScheduleAssistantPanel.svelte` — Expandable panel pattern (check for reusable patterns)

### System Settings
- `app/Models/SystemSetting.php` — Settings model
- `config/services.php` — Mixedbread/AI config

### Toast System (Phase 2 reference)
- Use for consistent animation patterns

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `AiCompanionService::chat()` — accepts applicant and message
- `AiCompanionService::clearHistory()` — clear conversation
- Existing Svelte components for chat UI

### Established Patterns
- Toast expandable panel already implemented in Phase 2
- Portal layouts use `PortalLayout.svelte`

### Integration Points
- PortalLayout.svelte — where chat widget should be mounted (applies to all portal pages)
- `PortalAuthController` middleware for AI companion mode check (if enabled)

</code_context>

<specifics>
## Specific Ideas

- Use fixed bottom-right corner positioning (standard)
- Floating action button that expands into chat panel
- Should work on ALL applicant portal pages, not just a specific route
- Must check AI Companion mode is enabled before showing widget

</specifics>

<deferred>
## Deferred Ideas

[None — discussion stayed within phase scope]

</deferred>

---

*Phase: 04-applicant-ai-companion-chat-interface-floating-expandable-ch*
*Context gathered: 2026-04-14*