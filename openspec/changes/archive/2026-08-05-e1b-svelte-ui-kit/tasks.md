# E1b Svelte UI Kit Tasks

Tasks are ordered. A later task must not hide a failed earlier task.

## A. Evidence and capture

- [x] A1 Enumerate every legacy `Components/ui/*` primitive and dispose it (PORT / IMPROVE_TABLE / DEFER_COMPOSITE).
- [x] A2 Enumerate the named presentational composites, layouts, and the `Guide/*` family; strip any domain/API binding; record academic-tone deltas.
- [x] A3 Write `docs/greenfield-foundation/evidence/ui-kit-legacy-capture-catalog.md`.

## B. Stack flip

- [x] B1 Remove React runtime deps (`react`, `react-dom`, `@inertiajs/react`, `@vitejs/plugin-react`, `@types/react*`, `@testing-library/react`, react eslint plugins).
- [x] B2 Add Svelte 5, `@inertiajs/svelte`, `@sveltejs/vite-plugin-svelte`, `bits-ui`, `lucide-svelte`, `clsx`, `tailwind-merge`, `tailwind-variants`, `svelte-check`, `eslint-plugin-svelte`, `prettier-plugin-svelte`.
- [x] B3 Update `vite.config.ts` to use the Svelte plugin and `resources/js/app.ts` entry; keep the `@tailwindcss/vite` plugin and `laravel-vite-plugin`.
- [x] B4 Add `components.json` for shadcn-svelte (`style: default`, alias `@/Components`, `@/lib/utils`, `@/Components/ui`, `@/lib`).
- [x] B5 Update `tsconfig.json` for Svelte (bundler resolution, `@/*` path alias) and drop the `react-jsx` setting.
- [x] B6 Rewrite `resources/css/app.css` tokens from legacy `app.css`, with `--radius: 0.25rem` and unchanged color/typography tokens.
- [x] B7 Replace `resources/js/app.tsx` with `resources/js/app.ts` using `createInertiaApp` from `@inertiajs/svelte` + `mount()`.
- [x] B8 Update `resources/views/app.blade.php` `@vite` entry to `resources/js/app.ts`.
- [x] B9 Update `eslint.config.js`, `.prettierrc`, `vitest.config.ts`, `package.json` scripts for Svelte file globs.

## C. UI primitive + composite port

- [x] C1 Port all `Components/ui/*` primitives verbatim (adapt `@lucide/svelte` → `lucide-svelte` imports only).
- [x] C2 Fix the pre-existing `hsl(var(--token))` bug in `file-upload.svelte` (tokens are OKLCH, not HSL triples) while touching that file.
- [x] C3 Port presentational composites to `Components/kit/*`: `StatCard`, `KpiCard`, `SimplePagination`, `ViewModeToggle`, `SwitchableListView`, `InfoPopover`, `PasswordStrengthInput`, `ToastManager` (+ `lib/toast.js`, `lib/password-strength.js`), `Guide/GuidePanel|GuideSection|GuideNote`, `ActionDropdown` shell.
- [x] C4 Port `Layouts/AuthenticatedLayout.svelte` and `Layouts/GuestLayout.svelte` as **nav chrome only** — generic placeholder nav items, no role-string gating, no legacy image asset dependency, no `NotificationDropdown` polling logic.

## D. Pages and routing

- [x] D1 Convert `Pages/Auth/Login.tsx`, `Pages/Staff/Home.tsx`, `Pages/Foundation/Status.tsx` to `.svelte`, preserving props contracts and visible text asserted by existing Pest/Playwright tests.
- [x] D2 Build `Pages/UiKit/Gallery.svelte` covering every ported primitive/composite with interactive demos and default/disabled/invalid/loading states, synthetic data only.
- [x] D3 Add `GET /ui-kit` behind `auth` middleware in `routes/web.php`.

## E. Tests and evidence

- [x] E1 Update `tests/frontend/foundation.test.ts` runtime string expectation (Svelte 5, not React 19).
- [x] E2 Add a Pest feature test asserting the `/ui-kit` route renders `UiKit/Gallery` for an authenticated staff user and redirects a guest.
- [x] E3 Run `npm install` + `npm run build` in `system/` — must be green.
- [x] E4 Run affected Pest suites (`StaffAuthenticationTest`, new UI kit test) via Compose if the stack is up; otherwise record the gap.

## F. Docs

- [x] F1 Update GF-09 platform topology (Svelte, not React).
- [x] F2 Update GF-22 UX contract with a note on the UI kit gallery and shadcn-svelte source.
- [x] F3 Update GF-15 ADR catalog to correct the stale ADR-002 Laravel/React text.
- [x] F4 Update `GREENFIELD-MASTER-REFERENCE.md` and `docs/project-context.md` stack references.

## G. Locked start set + dark referral (post-gallery)

- [x] G1 Lock institutional tokens: IBM Plex Sans/Mono, `--radius: 0.5rem`, layered paper + glass (`app.css` + `app.blade.php`).
- [x] G2 Expand `/ui-kit` gallery with locked-set, tokens, typography, surfaces, layout chrome, scoped dark panel, page-state patterns.
- [x] G3 Persist evidence: `ui-kit-locked-start-set.md` + `ui-kit-reference/` light/dark static HTML (no meta banner above header).
- [x] G4 Update capture catalog §5 to match the lock (not the interim 0.25rem/Source Sans experiment).
- [x] G5 Port management Index chrome patterns into kit: `PageHeaderBar`, `FilterToolbar`, `BulkActionBar`, `ModeBanner`, `ActiveFilterChips`, `lib/auto-filter.js`; expand `/ui-kit` with composed `#management-list` demo (responsive Filters drawer, bulk bar, table/cards selection).

## H. Management list chrome (filters / bulk / header)

- [x] H1 Port presentational patterns: `PageHeaderBar`, `FilterToolbar`, `BulkActionBar`, `ModeBanner`, `ActiveFilterChips`, `lib/auto-filter.js`.
- [x] H2 Expand `/ui-kit` with composed **Management list page** demo (responsive Filters panel, selection, chips, banners) + isolated sections.
- [x] H3 Update capture catalog rows for the new kit patterns (legacy Index evidence).

## I. Gallery polish + Motion homepage sample

- [x] I1 Restyle GuideNote/GuidePanel (no left-border stripe); InfoPopover = CircleHelp only (hover/focus).
- [x] I2 Fix Dialog open via `bind:open` (no nested button); Popover Trigger `child` merge.
- [x] I3 Glass card demo over paper wash; modernize Card surfaces.
- [x] I4 Add `motion` + `lib/motion.js` reveal actions; `MarketingHomePreview` section sample with scroll-in.

## J. Tabs / glass polish + Nexi sample

- [x] J1 Tabs default to institutional **line** underline (segmented kept as `variant="default"`).
- [x] J2 Glass cards: single edge only (no double Tailwind+`.glass-panel` border); quieter glass border token.
- [x] J3 Add `NexiWalkthroughPreview` (dialogue + placeholder sprite frames + chat affordance) to `/ui-kit`.
- [x] J5 Universal `NexiAssistPanel` mounted via `AuthenticatedLayout` + Inertia `nexi` share (`config/nexi.php`). Presentation-only; no provider.
- [x] J6 Lock Nexi chrome in evidence: minimal header (mode pill + % used), FAB minimize, **history + wrap island** (latest agent reply floats beside frame), Thinking/Analyzing status, Apply gate overlay. Documented in `ui-kit-locked-start-set.md` §4.1.
- [x] J4 Stitch MCP generate homepage/Nexi comps — **PARKED / DEFERRED**: `user-stitch` MCP discovery/auth still failing; needs Stitch-enabled Google credential. Not an E2 readiness blocker; locked UI kit + Nexi chrome stand without Stitch comps.

## Guardrails

- No domain journey, command, or new capability string is introduced.
- No legacy business logic (AI companion, QR scanner, charts, import analyzers) is ported.
- No Sail; Compose only for backend verification.
- Do not place design meta captions above the sticky staff header.
