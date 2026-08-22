# Canonical template authoring handoff

## Implementation status

Unified Template Studio, Atomic Save Protocol, and Dual-Input Engine Integration complete and verified.
- **Single Atomic Studio Save (`PUT /setup/omr/templates/canonical/drafts/{draft}/studio`)**: FormRequest `SaveOmrStudioStateRequest` + Action `SaveOmrCanonicalStudioState` persisting mapping, custom overlays, and studio state (`authoring_json.studio`) in a single DB transaction without mutual deletion or concurrency deadlocks.
- **Expanded Overlay Spec & Content Persistence**: `OmrOverlaySpec` supports `header_title`, `instructions_box`, `signature_box`, `metadata_box`, `custom_text`, and `blank_instance_qr` with sanitized `content`, `name`, and `font_size`.
- **Parametric Blank Canvas Mode**: `OmrCanonicalComposer.php` generates clean white 300 PPI rasters with 4 corner fiducials directly from paper dimensions and marker insets without requiring ingested documents.
- **Dynamic Blank Print QR Placement**: `OmrBlankPrintOrchestrator.php` positions cryptographic QR codes dynamically from overlay box reservations with safe top-right fallback.
- **Dual-Input Test Capture (Live Camera + Upload)**: Stage 5 in `OmrTemplateStudio.svelte` provides live viewfinder snapshot capture via `getUserMedia` and drag-and-drop file upload against OMRChecker diagnostic verification.
- **Clean Cutover & Parity**: 100% vector print PDF and frame-anchored `template.json` export cleanly with zero coordinate drift. All 22 backend test suites pass green.
## Pinned dependencies and security decisions

### Server-side QR renderer
- Package: `endroid/qr-code`
- Locked version: `6.1.3` (Composer lock)
- License: MIT
- Runtime: PHP 8.4; SVG and PNG writers are used in queue-safe PHP code.
- Fixed output contract: UTF-8, ECC `Medium`, quiet-zone margin `4`, `RoundBlockSizeMode::Margin`, no logo, no external/network calls.
- Determinism: identical payload and options produce identical renderer output; repeated canonical runs hash the bytes.
- Package source: https://github.com/endroid/qr-code

### HTML sanitizer
- Package: `ezyang/htmlpurifier`
- Locked version: `4.19.0` (Composer lock)
- License: LGPL-2.1-or-later
- Adapter policy: server-side allowlist, CSS filtering, remote resources disabled, scripts/events/iframes rejected, and deterministic configuration; unknown dynamic tokens rejected unless fixed `data-omr-overlay` slots.
- Package source: https://github.com/ezyang/htmlpurifier

### ICC conversion
- Images with no ICC profile or an sRGB profile are accepted; non-sRGB ICC input is rejected with typed `image_icc_unsupported`.
- Alpha is flattened onto white during image normalization.

## Runtime and reset

Use the Compose runtime, not Sail:

```bash
docker compose -f system/compose.yaml up -d
```

To reset and seed demo data:

```bash
docker compose -f system/compose.yaml exec app php artisan migrate:fresh --seed --force
```

## Demo credentials and URLs

- Staff Login: `guidance.counselor@nexiam.test` / `password`
- Canonical Templates Index: `http://localhost:18000/setup/omr/templates/canonical`
- Seeded Drafts (idempotent `OmrCanonicalDemoSeeder` via `updateOrCreate`; `source_kind` values are the NOT NULL `varchar(16)` column):
  - Draft 2 (`CANONICAL-HTML`, `source_kind=html`): Sheet Designer entry — seeded with NO studio payload so the entry gate shows on a fresh seed.
  - Draft 5 (`CANONICAL-PRESET`, `source_kind=preset`): parametric preset pathway.
- Sheet Designer studio URL: `http://localhost:18000/setup/omr/templates/canonical/drafts/2` (also reachable from the draft list).

Reseed after frontend changes:

```bash
cd system && docker compose exec -T app php artisan db:seed --class=OmrCanonicalDemoSeeder
```

Expected initial state: Every canonical draft is uncompiled; physical qualification and activation remain blocked until genuine marker/template evidence is recorded.

## Sheet Designer (Stage-1 rework, 2026-08-22)

Stage 1 of the html-mode studio is now the **Sheet Designer** ("Native Builder" term removed). Single source of truth is the persisted `components` array (`authoring_json.studio.components`); `sections` (OMR geometry) and compiled `htmlCode` are derived via `syncComponentsToSections()`.

- **Entry gate**: before any canvas renders, staff choose Sheet Designer or Upload Document once (`stage1Mode`); switching later is a deliberate "Change mode" action with a confirm modal (`Change creation mode?`) when content exists.
- **Palette**: 7 draggable + click-to-add cards (`COMPONENT_PALETTE`: Document Header, Candidate Info, Instructions, Examinee ID Matrix, Question Grid, Custom Text, Signature Box). Drag carries `application/x-omr-component` plus a `window.__omrDragKind` fallback; the canvas wrapper handles `ondragover`/`ondrop`.
- **Canvas blocks**: roll + question-grid components render as interactive blocks in stage 1 (`StudioCanvas` html-mode condition); text components are HTML-only and selected via sidebar cards.
- **Inspector**: per-selected-component config — full typography controls (`StudioTypographyControls`: Font Size (pt), family, bold, alignment, color) for text components; geometry/binding fields for roll/grid components; per-grid subject binding select fed by system `aptitude_areas`.
- **Quick Scaffolds**: 150-Q / 100-Q / 50-Q presets and an aptitude-area scaffold derived from active `AptitudeArea` rows.
- **Compiled HTML tab**: readonly compiled markup + the copyable suite reference (`COMPONENT_SUITE_CSS` + `COMPONENT_SUITE_PROMPT`) for reproducing the sheet in other interfaces / AI tools; Copy buttons on both.
- **Persistence**: `components` rides the studio PUT (`X-CSRF-TOKEN` via the `csrfToken()` helper); route is CSRF-excepted (`setup/omr/studio/*`, `setup/omr/templates/canonical/drafts/*`). Hydration prefers `studio.components`, falls back to legacy `studio.sections`.
- **Blank start**: no default template; compiled HTML is empty until a component exists.

## Verification evidence

- OpenSpec: `openspec validate omr-canonical-template-authoring --strict` → **Valid**.
- Sheet Designer browser proof (Draft 2, `CANONICAL-HTML`, fresh reseed): entry gate renders; Sheet Designer chosen; simulated drag-and-drop of Question Grid adds a component (window fallback path); click-add of Document Header opens `Inspector — Document Header` with Font Size (pt) controls; Remove drops the count and clears the inspector; Compiled HTML tab shows markup (`omr-sheet`/`omr-exam-grid` classes), suite reference, and 2 Copy buttons; Change mode opens the confirm modal; zero console/page errors, zero failed requests.
- Focused OMR suites re-run green: unit 26 passed (103 assertions) + feature 18 passed (115 assertions) — 44 passed, 218 assertions total.
- Canonical focused Laravel suite: **56 passed, 1 skipped, 261 assertions (100% green)** (last full run).
- Parity command: `php artisan omr:canonical-parity` → **PASSED for all 7 seeded versions**.
- Worker scenarios: Rectification and layout verification passing in the pinned inference worker image.
- Headless Browser: Confirmed live `OmrTemplateStudio` workspace with interactive toolbox, SVG canvas, property inspector, and live geometry validation.

## Qualification truth

- Both seeded marker profiles remain `candidate`.
- All supplier/canonical demo drafts remain unqualified and inactive.
- No seeder fabricates `physical_qualified`; UI qualification requires explicit printer/device identity, measured marker-center distances, and operator confirmation that the physical matrix passed.
- Real physical printer/device/paper acceptance remains open and is the production activation blocker.
