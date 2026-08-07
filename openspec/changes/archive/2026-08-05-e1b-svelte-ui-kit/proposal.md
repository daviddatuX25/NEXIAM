# E1b — Frontend Stack Flip: Inertia v3 + Svelte 5 + shadcn-svelte UI Kit

**Status:** OPEN
**Depends on:** E0 scaffold evidence PASS, E1 identity/reference PASS.
**Blocks:** No E2 journey pages/commands until this change is closed and the reconciled roadmap/ADR text is updated.

## Intent

Flip the greenfield `system/` frontend runtime from Inertia v3 + React 19 to **Inertia v3 + Svelte 5 + shadcn-svelte (bits-ui)**, matching the legacy runtime's frontend stack (which is already Svelte 5 + Inertia + shadcn-svelte, not React). Port the complete legacy UI primitive suite and a curated set of presentational composites into `system/`, verbatim in structure and class-name vocabulary, and expose them through a rich, non-functional UI kit gallery page. This closes the stale ADR-002/GF-09 text (documented as `BLOCK-05` in the master reference: "stale Laravel/Svelte ADR text versus the implemented Laravel/React stack") by making the implemented stack match the intended/legacy-evidenced one.

This is **infrastructure and presentation only**. No domain journey, controller, command, or authorization behavior is added, removed, or reinterpreted. `CapabilityAuthorizer`, Spatie grants, actor scope, and all backend PHP logic are untouched except for Inertia page-render component names (unaffected — component identifiers stay extension-less, e.g. `Auth/Login`) and one new presentational route (`GET /ui-kit`).

## Why this is safe under the master-reference stop rules

- No new FR/BR is implemented; no legacy business behavior is reproduced. Only UI primitives (button, input, table, dialog, ...) and presentational composite *shells* (StatCard, KpiCard, pagination, view toggles, password strength meter UI, toast pattern, guide panels, action dropdown shell, layout chrome) are ported.
- Composites are captured as **shells**: any legacy composite that called a domain API, referenced a specific route, or depended on role-string gating is stripped of that binding and replaced with generic props/synthetic demo data (see capture catalog).
- Explicitly excluded: `AiCompanionChatWidget` business logic, `QrScanner` device logic, chart components tied to product KPIs, `NotificationDropdown` polling logic, and all Laravel controllers/domain code from legacy.
- Academic tone delta: `--radius` lowered from `0.5rem` to `0.25rem` (cascades through the whole Tailwind v4 radius scale used by every ported primitive), and hand-picked oversized literal radii on composites are reduced when touched.

## Non-goals

- No E2 domain screens, commands, or business copy.
- No new authorization capability/scope is introduced. The `/ui-kit` route is gated by the existing `auth` middleware only (no new capability string invented without a story).
- No AI/Nexi, OMR, scheduling-assistant, or Program Head UI.
- No charts, QR scanning, or docx/import analyzers.

## Deliverables

1. Legacy UI capture catalog — `docs/greenfield-foundation/evidence/ui-kit-legacy-capture-catalog.md`.
2. This OpenSpec change (`proposal.md`, `tasks.md`, `specs/ui-kit/spec.md`).
3. `system/` frontend flipped to Svelte 5 + `@inertiajs/svelte` + shadcn-svelte/bits-ui; React removed.
4. Full `Components/ui/*` primitive suite ported; presentational composites ported to `Components/kit/*`.
5. `Pages/Auth/Login`, `Pages/Staff/Home`, `Pages/Foundation/Status` converted to `.svelte`, preserving existing Pest `assertInertia` component names and the existing Playwright `foundation.spec.ts` visible-text contract.
6. `Pages/UiKit/Gallery.svelte` — rich, interactive, synthetic-data-only gallery covering every ported primitive and composite, states included (default/disabled/invalid/loading where applicable).
7. `GET /ui-kit` route behind `auth` middleware.
8. Doc updates: GF-09, GF-22 note, ADR-002 note (in GF-15), `GREENFIELD-MASTER-REFERENCE.md`, `project-context.md`.
9. Updated frontend tests (Vitest) and Pest evidence for the new route; `npm run build` green.

## Exit

E1b PASS means: `system/` frontend runtime is Svelte 5 + Inertia v3 + shadcn-svelte, the full UI kit is ported and demonstrable at `/ui-kit`, and stale React/ADR references are corrected. E1b PASS does not unlock E2 journey implementation — that still requires the product/story/UX catalog work tracked in the master reference (`BLOCK-01` through `BLOCK-03`).
