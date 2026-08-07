# UI Kit Locked Start Set (institutional)

**Status:** LOCKED — apply for all staff chrome / kit work until a founder decision changes it  
**OpenSpec:** [`e1b-svelte-ui-kit`](../../../openspec/changes/e1b-svelte-ui-kit/proposal.md)  
**Live gallery:** `GET /ui-kit` → `system/resources/js/Pages/UiKit/Gallery.svelte`  
**Capture catalog:** [`ui-kit-legacy-capture-catalog.md`](ui-kit-legacy-capture-catalog.md)  
**Static referral (offline HTML):** [`ui-kit-reference/`](ui-kit-reference/)

This is the durable visual contract for greenfield staff UI. Do not re-litigate fonts/radius/background per feature. Domain journeys still require Legacy → disposition → FR → story → OpenSpec before wiring real commands.

## 1. Design read (locked)

| Dial | Value | Meaning |
|---|---|---|
| DESIGN_VARIANCE | 3 | Institutional symmetry; no marketing chaos |
| MOTION_INTENSITY | 2 | Sidebar slide + hover only |
| VISUAL_DENSITY | 5 | Staff cockpit density, not art gallery |

**Language:** trust-first campus operations. Brand green retained. No Montserrat. No decorative mono captions on chrome. No razor-flat zero-radius experiment.

## 2. Token lock

| Token | Locked value | Source of truth |
|---|---|---|
| Sans | `IBM Plex Sans` | `system/resources/css/app.css` `--font-sans` + `app.blade.php` Google Fonts link |
| Mono | `IBM Plex Mono` | `--font-mono` (code/tabular only — never chrome captions) |
| Radius | `0.5rem` | `--radius` with sm/md/lg/xl derived like legacy |
| Primary | OKLCH brand green (`--primary`) | unchanged from legacy palette |
| Background | Layered paper (forest wash + warm bone edge) | `body` background-image in `app.css` |
| Surfaces | `glass-panel` + solid `card` | `.glass-panel` blur/saturate; solid fallback under `prefers-reduced-transparency` |
| Dark | `.dark` token block in `app.css` | Theme toggle on Authenticated/Guest layouts; gallery dark panel |

**Explicit rejections**

- Montserrat / Merriweather as default UI fonts
- `--radius: 0` / forced `rounded-none` chrome
- Floating design meta banners above the app header (breaks sticky top nav)
- AI-purple / cream-terracotta marketing palettes as overrides of brand green

## 3. Layout / responsive lock (legacy parity)

Matches legacy `AuthenticatedLayout` behavior:

- `h-dvh` shell, `overflow-hidden`
- Sidebar `w-64`, `-translate-x-full` by default, `md:translate-x-0`
- Mobile: hamburger opens drawer + `bg-black/50` backdrop (`lg:hidden` / `md:hidden` patterns as in layout)
- Main column `md:pl-64`
- Header `h-20` glass bar, breadcrumbs `hidden md:flex`, compact title on small screens
- Main `p-4 lg:p-8` scroll

Guest login: centered card, soft atmospheric orbs, theme toggle top-right — no meta caption strip.

### Management Index chrome (required for list pages)

Reuse kit pieces rather than inventing per page:

- `PageHeaderBar` — description + action cluster (`hidden sm:inline` labels)
- `FilterToolbar` — search always on; desktop filters inline; mobile Filters `details` drawer
- `ActiveFilterChips` — removable applied filters
- `BulkActionBar` — selection count + bulk actions + Clear
- `ModeBanner` — online/F2F/context callouts
- `SwitchableListView` + `ViewModeToggle` + `SimplePagination` + row `ActionDropdown`

Gallery section `#management-list` is the living reference. Touch targets: **44px** mobile, **~40px** desktop filter controls.


## 4. Component coverage authority

| Layer | Path | Authority |
|---|---|---|
| Primitives | `system/resources/js/Components/ui/*` | Full suite ported; gallery demos every variant/state |
| Composites | `system/resources/js/Components/kit/*` | Presentational only; no domain APIs. Includes management-list chrome: `PageHeaderBar`, `FilterToolbar`, `BulkActionBar`, `ModeBanner`, `ActiveFilterChips`, plus list helpers. |
| Management list chrome | `PageHeaderBar`, `FilterToolbar`, `BulkActionBar`, `ModeBanner`, `ActiveFilterChips`, `SwitchableListView` | Legacy Index responsive patterns; gallery `#management-list` |
| Staff chrome | `Layouts/AuthenticatedLayout.svelte`, `GuestLayout.svelte` | Responsive + theme toggle |
| Live kit | `/ui-kit` | Interactive light + dark (header toggle + scoped dark panel). Includes management list chrome, Motion scroll reveals, marketing homepage sample, and Nexi assist. |
| Static twin | `evidence/ui-kit-reference/*.html` | Offline light/dark referral if Compose is down |
| Nexi chrome | `Components/kit/Nexi/NexiAssistPanel.svelte` + `lib/nexi-*.js` + `config/nexi.php` | Universal staff assist; see §4.1 |

Deferred composites remain as listed in the capture catalog (charts, QR, domain timelines). Legacy `AiCompanionChatWidget` stays **DEFER_COMPOSITE** for domain/provider logic — greenfield Nexi chrome is a **rewrite**, not a port of that widget’s business logic.

**Management list responsive rules (locked with chrome):**

- Search always visible; Filters button + panel only below `md`
- Header CTAs: icon always, label from `sm` up
- Bulk bar wraps; hint text may hide on xs
- Table `min-w-[640px]` inside horizontal scroll; cards on small / SwitchableListView responsive mode
- Touch targets ≥ 44px on mobile filter controls

### 4.1 Nexi universal assist (LOCKED chrome seam)

**Status:** LOCKED presentation seam for E0–E2 — provider/RAG remain E3 OpenSpec  
**Authority:** master §8.4–8.5, GF-06 §10, GF-22 (UI ≠ authz; typed actions only)

| Piece | Locked behavior |
|---|---|
| Mount | Every authenticated staff page via `AuthenticatedLayout` + Inertia `nexi` share |
| Flags | `config/nexi.php` / `NEXI_*` — `enabled`, `mode` (`degraded` default), interaction modes, usage meter |
| Header | Minimal: sparkles · **Assistant / Inquiry** mode pill (icon + label + chevron) · circular **% used** · minimize — no long disclosure dump (sr-only) |
| FAB | Minimizable / expandable; session-persisted |
| Layout | **History** above; **wrap island** at bottom: floated character frame + **latest agent reply** wraps beside it (`float` + `shape-outside`) |
| Status fillers | `Thinking…` → `Analyzing…` in the wrap island before the reply (demo only) |
| Actions | Soft suggestion chips (not a control strip); Apply proposals open a **gate overlay** (Confirm) — same command contract as page UI later |
| Modes | `assistant` = page/task help + Apply previews; `inquiry` = policy/own-data Q&A (no Apply). Page matrix may disable keys via `interaction_modes` later |
| Out of scope | Provider SDK, RAG retrieval, real metering counters, sprite art (placeholder frames OK) |

**Paths**

- Panel: `system/resources/js/Components/kit/Nexi/NexiAssistPanel.svelte`
- Context/actions: `system/resources/js/lib/nexi-context.js`, `nexi-actions.js`
- Share: `HandleInertiaRequests` → `nexi`; config `system/config/nexi.php`
- Gallery note: `/ui-kit` § Nexi assist
- Sample twin: `Components/kit/samples/NexiWalkthroughPreview.svelte` (composition reference only)

Do not reintroduce a dense Guide/Apply toolbar, absolute frame that paints over chat, or hidden authz/DB paths.

## 5. How later UI work must use this

1. Open `/ui-kit` (or static reference) and pick existing primitive/composite first.
2. Match locked tokens — do not invent new fonts/radius per page.
3. Preserve responsive shell; do not put content above the sticky header.
4. Prove light + dark with the theme toggle before calling a page done.
5. Keep Nexi wrap-island + minimal header if touching assist chrome; extend typed-action catalog only with allowlisted stubs + OpenSpec.
6. Domain behavior still needs the Legacy → Target Trace in `project-context.md`.

## 6. Change control

To change this lock: record a founder decision (GF-13), update this file + `app.css` + gallery token section + static reference (and Nexi paths if chrome changes) in one change, under an OpenSpec task. Do not drift via one-off page CSS.
