# UI Kit Specification

## ADDED Requirements

### Requirement: Svelte 5 frontend runtime

The `system/` frontend MUST run on Inertia v3 + Svelte 5 + shadcn-svelte (bits-ui) instead of React, using Tailwind v4 CSS-token theming.

#### Scenario: Build produces a Svelte bundle

- GIVEN the flipped `system/` frontend dependencies
- WHEN `npm run build` executes
- THEN the build succeeds without any React runtime dependency
- AND the compiled entry resolves `resources/js/app.ts` through `@sveltejs/vite-plugin-svelte`.

### Requirement: Complete primitive port

Every legacy `Components/ui/*` primitive (badge, button, card, checkbox, command, dialog, file-upload, input, label, popover, select, separator, switch, table, tabs, textarea, toggle, toggle-group) MUST exist under `system/resources/js/Components/ui/` with equivalent variants and no broken imports.

#### Scenario: Primitive imports resolve

- GIVEN any ported `Components/ui/*` primitive
- WHEN it is imported into a page or gallery section
- THEN it resolves only to `bits-ui`, `lucide-svelte`, `tailwind-variants`, `@/lib/utils`, or other ported `Components/ui/*` files
- AND it does not import `@lucide/svelte`, `chart.js`, `svelte-french-toast`, or any legacy domain module.

### Requirement: Presentational composite shells only

Ported composites (`StatCard`, `KpiCard`, `SimplePagination`, `ViewModeToggle`, `SwitchableListView`, `InfoPopover`, `PasswordStrengthInput`, `ToastManager`, `Guide/*`, `ActionDropdown`, layout chrome) MUST NOT call a domain API, reference a specific legacy route, or gate on a legacy role string.

#### Scenario: Composite has no domain binding

- GIVEN any file under `system/resources/js/Components/kit/`
- WHEN its imports and props are inspected
- THEN it accepts generic/synthetic props only
- AND it contains no reference to legacy controllers, legacy role arrays, or a specific admissions/exam/release route.

### Requirement: Rich, non-functional UI kit gallery

`Pages/UiKit/Gallery.svelte` MUST render a working demo section for every ported primitive and composite, using only synthetic demo data, with default/disabled/invalid/loading states shown where the primitive supports them.

#### Scenario: Gallery renders all sections

- GIVEN an authenticated staff user visits `/ui-kit`
- WHEN the page renders
- THEN every ported `ui/*` primitive and `kit/*` composite has a visible demo section
- AND no gallery section issues a network request beyond the initial page load.

### Requirement: Gallery route authorization

`GET /ui-kit` MUST require authentication and MUST NOT introduce a new capability string without a story.

#### Scenario: Guest is redirected

- GIVEN an unauthenticated visitor
- WHEN they request `/ui-kit`
- THEN they are redirected to `/login`
- AND no gallery content is returned.

#### Scenario: Authenticated staff can view the gallery

- GIVEN an authenticated staff user
- WHEN they request `/ui-kit`
- THEN the response is successful and renders the `UiKit/Gallery` Inertia component.
