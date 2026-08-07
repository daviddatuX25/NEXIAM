# UI Kit Legacy Capture Catalog

**OpenSpec:** [`e1b-svelte-ui-kit`](../../../openspec/changes/e1b-svelte-ui-kit/proposal.md)
**Legacy source:** `legacy_codebase/application/resources/js/Components/`, `Layouts/`
**Target:** `system/resources/js/Components/`, `system/resources/js/Layouts/`, `system/resources/js/Pages/UiKit/Gallery.svelte`

This catalog is evidence for the E1b stack flip only. It does not authorize any domain journey. Every row states legacy source, disposition, and the academic-tone delta applied when porting.

Legend:

- **PORT** — copied with only mechanical adaptation (icon package import path, `hsl(var())` → `var()` token fix). No behavior change.
- **IMPROVE_TABLE** — ported, and a small accessibility/token defect found during the port was fixed in place (documented per row).
- **DEFER_COMPOSITE** — legacy composite exists but is **not** ported because it is domain-bound, device-bound, or chart/business-KPI bound. Listed for completeness; excluded per the stop-scope in the proposal.

## 1. UI primitives (`Components/ui/*`)

| Primitive | Legacy path | Disposition | Notes / academic delta |
|---|---|---|---|
| Badge | `ui/badge/badge.svelte` | PORT | `rounded-full` pill kept (semantic, not affected by radius-scale reduction). |
| Button | `ui/button/button.svelte` | PORT | Uses theme radius scale (`rounded-lg`, `rounded-[min(var(--radius-md),Npx)]`); automatically less-rounded once `--radius` drops to `0.25rem`. |
| Card | `ui/card/*` | PORT | `variant="glass"` kept for gallery demo of the glass-panel pattern; radius scale token-driven. |
| Checkbox | `ui/checkbox/checkbox.svelte` | PORT | `@lucide/svelte` → `lucide-svelte` icon import path. |
| Command | `ui/command/*` (8 files) | PORT | `@lucide/svelte` → `lucide-svelte`; depends on `ui/dialog` for `CommandDialog`. |
| Dialog | `ui/dialog/*` (9 files) | PORT | `@lucide/svelte` → `lucide-svelte` for the close `X` icon. |
| File upload | `ui/file-upload/file-upload.svelte` | IMPROVE_TABLE | Legacy component wrapped OKLCH color tokens in `hsl(var(--token))`, which is invalid (tokens are full `oklch()` values, not HSL triples). Fixed to reference the CSS variables directly. Radius fallback lowered `0.5rem` → `0.25rem` to match the academic token. |
| Input | `ui/input/input.svelte` | PORT | No changes beyond copy. |
| Label | `ui/label/label.svelte` | PORT | No changes beyond copy. |
| Popover | `ui/popover/*` (8 files) | PORT | No icon deps; `bits-ui` only. |
| Select | `ui/select/*` (10 files) | PORT | `@lucide/svelte` → `lucide-svelte` for chevron/check icons. |
| Separator | `ui/separator/separator.svelte` | PORT | No changes beyond copy. |
| Switch | `ui/switch/switch.svelte` | PORT | No changes beyond copy. |
| Table | `ui/table/*` (8 files) | PORT | No changes beyond copy. |
| Tabs | `ui/tabs/*` (4 files) | PORT | No changes beyond copy. |
| Textarea | `ui/textarea/textarea.svelte` | PORT | No changes beyond copy. |
| Toggle | `ui/toggle/toggle.svelte` | PORT | No changes beyond copy. |
| Toggle group | `ui/toggle-group/*` (2 files) | PORT | No changes beyond copy. |

All primitives share the `cn()` helper from `@/lib/utils.js` (`clsx` + `tailwind-merge`), ported unmodified.

## 2. Presentational composites (`Components/kit/*`)

| Composite | Legacy path | Disposition | Domain binding stripped | Academic delta |
|---|---|---|---|---|
| StatCard | `Components/StatCard.svelte` | PORT (shell) | None found — already generic (`label`, `value`, `icon`, `trend`, optional `href`). Kept as-is. | None; glass-panel + `rounded-2xl` is on the theme radius scale, shrinks with `--radius`. |
| KpiCard | `Components/KpiCard.svelte` | PORT (shell) | None found — generic `status`/`delta`/`secondaryItems` props. Kept as-is. | Same as above. |
| SimplePagination | `Components/SimplePagination.svelte` | PORT (shell) | Consumes a generic Laravel-paginator-shaped `data` prop (`current_page`, `last_page`, `prev_page_url`, ...). No hardcoded route. Gallery demo uses a synthetic object with this shape. | None. |
| ViewModeToggle | `Components/ViewModeToggle.svelte` | PORT (shell) | Generic `value` bindable prop; no domain binding. | None. |
| SwitchableListView | `Components/SwitchableListView.svelte` | PORT (shell) | Generic `table`/`cards` snippet slots; no domain binding. | None. |
| InfoPopover | `Components/InfoPopover.svelte` | PORT (shell) | Generic `content`/`label` props; no domain binding. | None. |
| PasswordStrengthInput | `Components/PasswordStrengthInput.svelte` + `lib/password-strength.js` | PORT (UI only) | Scoring logic (`min 8 / upper / lower / number`) is pure client-side string validation mirroring a generic backend rule description in a legacy code comment; no API call. Ported as UI-only per the task brief — any future *authoritative* password policy stays server-validated. | None. |
| ToastManager pattern | `Components/ToastManager.svelte` + `lib/toast.js` | PORT (pattern) | Legacy version imports `notification-sound.js` (audio chime) and subscribes to `page.props.flash.success/error`. Sound playback dropped (asset dependency, not required for the pattern); flash-subscription kept because `flash.status` is already shared by `HandleInertiaRequests` and is genuinely presentational. | None. |
| Guide/GuidePanel, GuideSection, GuideNote | `Components/Guide/*` | PORT (shell) | None found — generic collapsible/help-note primitives. | None. |
| ActionDropdown | `Components/ActionDropdown.svelte` | PORT (shell) | Legacy version calls `router.delete(item.href)` and native `confirm()` for `method: 'delete'` items. This is a **generic mechanism**, not a domain binding (no hardcoded route/label). Kept as the legacy affordance for gallery demonstration; a real destructive-action story must still follow GF-22's accessible confirmation contract (`docs/GREENFIELD-MASTER-REFERENCE.md` §6.4) rather than reusing `confirm()` when this shell is later wired to a real command. |
| PageHeaderBar | Index page headers (Users/Rooms/Applications/…) | PORT (pattern) | Description left + action cluster right; CTA labels `hidden sm:inline` with icon always visible. |
| FilterToolbar | Index filters (Applications/Users/Exam Scheduling/Release/Logs) | PORT (pattern) | Search always visible; desktop inline filters; mobile `details` “Filters” panel; 44px mobile / ~40px desktop touch targets. Parent owns state + auto-filter watches. |
| BulkActionBar | Applications / Release bulk selection chrome | PORT (pattern) | Count pill + hint (`hidden sm:inline`) + action snippet + Clear. Hidden when count is 0. |
| ModeBanner | Release online/F2F callouts | PORT (pattern) | `info` / `primary` / `warning` / `danger` surface variants. |
| ActiveFilterChips | Implicit “active filters” UX across Index pages | NEW (kit) | Removable chips + Clear all; presentational only. |
| `lib/auto-filter.js` | `lib/auto-filter` | PORT (helper) | Debounced + immediate watch factories for list filters. |
| PageHeaderBar | Index page headers (Applications/Users/Rooms/…) | PORT (pattern) | Description left + action cluster right; CTA labels `hidden sm:inline` with icon always visible (`min-h-[44px]`). |
| FilterToolbar | Index filter rows (Applications/Users/Exam Scheduling/Logs/Release) | PORT (pattern) | Search always visible; desktop extra filters `hidden md:flex`; mobile Filters `<details>` panel (`min-h-[44px]`). Snippets for desktop/mobile filter bodies — no domain queries. |
| BulkActionBar | Applications / Release bulk bars | PORT (pattern) | Count pill + hint (`hidden sm:inline`) + action snippet + Clear. Hidden when count is 0. |
| ModeBanner | Release online/F2F callouts | PORT (pattern) | info / primary / warning / danger variants. |
| ActiveFilterChips | Implicit filter affordance (clearable chips) | NEW (kit) | Presentational chips for active filter values; parent owns state. |
| `lib/auto-filter.js` | `lib/auto-filter` | PORT (helper) | Debounced (~300ms) + immediate watch helpers for list filters. Client UX only. |
| AuthenticatedLayout | `Layouts/AuthenticatedLayout.svelte` | PORT (chrome only) | Legacy version hardcodes role-string arrays (`['super_admin', 'registrar_administrator', ...]`) per nav item and a `NotificationDropdown` with polling/sound logic. Nav items replaced with a small generic placeholder list (`Home`, `UI Kit`); `NotificationDropdown` dropped; `registerSounds()` preload dropped; legacy `/img/nexiam-logo.png` asset dropped in favor of a text/monogram mark (no bundled asset dependency). Breadcrumb prop contract, theme toggle, user menu, and mobile sidebar behavior preserved. | Radius scale shrinks automatically with `--radius`. |
| GuestLayout | `Layouts/GuestLayout.svelte` | PORT (chrome only) | Legacy `/img/nexiam-logo.png` asset dropped for the same reason as above. `ToastManager` kept. | None beyond the asset swap. |

## 3. Explicitly deferred / excluded (not ported)

| Item | Legacy path | Disposition | Reason |
|---|---|---|---|
| AiCompanionChatWidget | `Components/AiCompanionChatWidget.svelte` | DEFER_COMPOSITE | Business logic + provider boundary remain deferred (master §8.4–8.5). **Greenfield chrome rewrite (not a port):** `Components/kit/Nexi/NexiAssistPanel.svelte` — locked in `ui-kit-locked-start-set.md` §4.1 (wrap island, mode pill, usage, Thinking/Analyzing, Apply gate). Provider/RAG still E3. |
| QrScanner | `Components/QrScanner.svelte` | DEFER_COMPOSITE | Device/camera integration tied to attendance submission mode; domain journey, not a UI primitive. |
| AreaChart / BarChart / DoughnutChart / LineChart / PieChart / Sparkline | `Components/*Chart*.svelte`, `Sparkline.svelte` | DEFER_COMPOSITE | Chart primitives are bound to product KPI series in legacy usage; task brief explicitly excludes "charts with fake product KPIs beyond demo data." Not required by the named composite list. |
| DocxTemplateAnalyzer / ImportFileAnalyzer | `Components/*Analyzer.svelte` | DEFER_COMPOSITE | Import/analyze/preview/confirm flow is an unresolved E2 decision (master reference §6.3). Not a UI primitive. |
| ApplicationStatusTimeline / SessionRoster / PriorityQueue / ScheduleAssistantPanel / ApplicantTips / ElapsedTime / NotificationDropdown | `Components/*.svelte` | DEFER_COMPOSITE | Domain-journey-bound (admissions/exam pipeline) or provider-bound (scheduling assistant). Not in the named composite list. |
| HomeLayout / PortalLayout / PortalGuestLayout | `Layouts/*.svelte` | DEFER_COMPOSITE | Applicant-portal-specific chrome; only `Authenticated`/`Guest` staff shells were requested. |

## 4. Dependency delta vs. legacy `package.json`

| Legacy dependency | Kept for E1b? | Reason |
|---|---|---|
| `svelte`, `@inertiajs/svelte`, `@sveltejs/vite-plugin-svelte`, `bits-ui`, `clsx`, `tailwind-merge`, `tailwind-variants`, `shadcn-svelte`, `svelte-check`, `eslint-plugin-svelte`, `prettier-plugin-svelte` | Yes | Required stack. |
| `@lucide/svelte` | No — replaced by `lucide-svelte` | Task brief names `lucide-svelte` explicitly; import paths adapted. |
| `chart.js`, `html5-qrcode`, `puppeteer`, `svelte-french-toast`, `@internationalized/date` | No | Tied to deferred composites (charts, QR scanner) or superseded by the ported native-store toast pattern; not required by any ported primitive/composite. |

## 5. Token delta (LOCKED — see also `ui-kit-locked-start-set.md`)

| Token | Legacy | Greenfield lock |
|---|---|---|
| `--radius` | `0.5rem` | **`0.5rem`** (restored; sm/md/lg/xl derive as in legacy) |
| Sans | Montserrat | **IBM Plex Sans** (institutional; Montserrat rejected) |
| Serif | Merriweather | IBM Plex Sans (no serif UI default) |
| Mono | Source Code Pro | **IBM Plex Mono** (code/tabular only) |
| Colors / shadows | OKLCH brand green palette | Unchanged brand green + layered paper background |
| Surfaces | `.glass-panel` | Kept; blur/saturate + reduced-transparency fallback |
| Dark | `.dark` token block | Kept; gallery scoped `.dark` panel + header theme toggle |

Static light/dark referral HTML: [`ui-kit-reference/`](ui-kit-reference/). Live suite: `GET /ui-kit`.
