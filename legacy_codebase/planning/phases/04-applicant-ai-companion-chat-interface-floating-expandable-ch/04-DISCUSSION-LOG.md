# Phase 04: Applicant AI Companion Chat Interface - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-14
**Phase:** 04-applicant-ai-companion-chat-interface-floating-expandable-ch
**Areas discussed:** Widget Position, Widget Visibility

---

## Widget Position

| Option | Description | Selected |
|--------|-------------|----------|
| Bottom-right fixed | Fixed bottom-right corner like chat widgets (standard) | ✓ |
| Floating draggable | Floating draggable anywhere on screen | |
| Bottom-left fixed | Fixed bottom-left corner | |
| Right side fixed | Fixed right side (vertical) | |

**User's choice:** Bottom-right fixed (Recommended)
**Notes:** Standard chat widget placement, consistent with modern UX patterns

---

## Widget Visibility

| Option | Description | Selected |
|--------|-------------|----------|
| Expandable | Floating button that expands to full chat panel on click | ✓ |
| Collapsed to icon | Minimized to chat icon, expands on click | |
| Always visible | Always full panel visible | |

**User's choice:** Expandable (Recommended)
**Notes:** Floating button that expands to full chat panel on click — space-efficient approach

---

## Claude's Discretion

- Animation details (smooth expand/collapse transitions)
- Color scheme (use existing toast system colors or introduce new)
- Panel sizing (width, height when expanded)
- Header/footer content (title, clear history button, close button)