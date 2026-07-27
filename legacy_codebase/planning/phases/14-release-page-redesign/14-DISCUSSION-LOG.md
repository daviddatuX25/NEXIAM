# Phase 14: Release Page Redesign - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-20
**Phase:** 14-release-page-redesign
**Areas discussed:** Mode-aware layout, Release All flow, F2F notification content, F2F side panel & bulk release

---

## Mode-aware layout

| Option | Description | Selected |
|--------|-------------|----------|
| Tab-based for both, single-view for single modes | Two tabs (Online / F2F) in 'both' mode, single-view in 'online' or 'f2f' mode | ✓ |
| Always two tabs, disable irrelevant tab | Same component tree, toggle tab availability | |
| Stacked sections, no tabs | Two sections stacked vertically in 'both' mode | |

**User's choice:** Tab-based for both, single-view for single modes
**Notes:** Keeps interface simple — one mode, one view at a time. In 'both' mode, tabs switch between the two views.

### Online view details

| Option | Description | Selected |
|--------|-------------|----------|
| Read-only table + Release All button | No per-row Release, no checkbox column. Release All in header. Side panel still accessible for editing notes. | ✓ |
| Read-only table + per-row Release as fallback | Selective release option alongside Release All | |

**User's choice:** Read-only table with Release All button, side panel for editing notes
**Notes:** User clarified that even in online mode, the admin needs to add counselor comments before release. Side panel stays accessible for editing, but release is only via Release All.

---

## Release All flow

| Option | Description | Selected |
|--------|-------------|----------|
| Count + warning modal | Custom modal: "This will release N results to applicants via email and portal notification. This action cannot be undone." Proceed/Cancel. | ✓ |
| Detailed list modal (applicant names) | Shows all applicant names being released. More transparent but slower for large datasets. | |
| Simple browser confirm dialog | Browser confirm() with "Are you sure?" | |

**User's choice:** Count + warning modal

### Already-released handling

| Option | Description | Selected |
|--------|-------------|----------|
| Silent skip, count-based success | Skip already-released silently, show "X results released." Only error on full failure. | ✓ |
| Detailed breakdown (released/skipped counts) | Return "Released 45 of 48. 3 already released." | |

**User's choice:** Silent skip, count-based success

---

## F2F notification content

| Option | Description | Selected |
|--------|-------------|----------|
| Visit-guidance-office wording, no portal link | Subject: "Your exam results are available for consultation". Body: explains F2F, wait for venue announcement. No "View in Portal" button. | ✓ |
| Generic wording with portal link | Brief "results available" with portal link despite F2F | |

**User's choice:** Visit-guidance-office wording, no portal link
**Notes:** User specified the body should tell applicants to "wait for further announcement for the venue for release and consultation." No portal action button.

### F2F notification channels

| Option | Description | Selected |
|--------|-------------|----------|
| In-app + email | Database channel for in-app notification, mail channel for email. F2F-specific wording in both. | ✓ |
| In-app only, no email | Database channel only. No email for F2F release. | |

**User's choice:** In-app + email

---

## F2F side panel & bulk release

| Option | Description | Selected |
|--------|-------------|----------|
| Only filled notes can be bulk-released | Require counselor notes filled before checkbox can be selected | |
| Any unreleased row can be bulk-released | Admin decides completeness. All unreleased rows are selectable. | ✓ |

**User's choice:** Any unreleased row can be bulk-released
**Notes:** No validation gate on notes for F2F bulk release. The admin decides what's complete enough.

### F2F side panel changes

| Option | Description | Selected |
|--------|-------------|----------|
| Same side panel, same behavior | No changes to existing side panel | |
| Add Release button inside side panel | After saving notes, admin can release directly from the panel without closing it | ✓ |

**User's choice:** Add Release button inside side panel
**Notes:** Allows save + release in one flow without closing the panel.

---

## Claude's Discretion

- Exact tab component implementation (Svelte tabs library vs custom)
- Confirmation modal styling details
- Toast message wording for Release All success/error
- Pagination approach for the online tab
- F2F email template HTML styling

## Deferred Ideas

None — discussion stayed within phase scope