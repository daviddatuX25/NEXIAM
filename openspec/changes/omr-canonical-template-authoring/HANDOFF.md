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

## Sheet Designer (Atomic Components & Floating Inspector, 2026-08-22)

Stage 1 of the html-mode studio is now the **Sheet Designer** ("Native Builder" term removed). Every component is atomic and absolutely positioned (`posX`/`posY`/`w_mm`/`h_mm` in mm). `renderFullSheetHtml` renders ONLY the components present on the sheet with zero implicit scaffolding.

- **Atomic Separable Palette**: 9 distinct draggable + click-to-add cards (`COMPONENT_PALETTE`: Document Header [title/subtitle/form code badge only], Candidate Name, Birthdate, Room / Seat, Instructions [notice box], Examinee ID Matrix [roll bubbles], Question Grid [subject questions & bubbles], Custom Text, Signature Box). Adding a Document Header adds ONLY the title block; adding Instructions adds ONLY the instruction box.
- **Absolute Canvas Positioning & Scaling**: Components own their exact mm positions from sheet edge. The live HTML preview iframe is scaled (`scale(0.65517)`) to align sub-pixel with canvas blocks at `2.476 px/mm`. Dragging on canvas updates mm positions; resizing via the bottom-right handle resizes text component bounds.
- **Lock / Unlock Authority**: Every component has a lock toggle in the sidebar list and in the inspector header. Locked components cannot be moved or resized on the canvas.
- **Floating Inspector (Top-Right of Canvas)**: Inspector sits directly below the top action bar on the right side of the canvas (`top-16 right-4`). Gated strictly to Stage 1 Sheet Designer so it NEVER bleeds onto Stage 2 or Stage 3. Features live mm Geometry (X/Y/W/H inputs), component-specific fields, and `StudioTypographyControls` (pt font size, font family, bold toggle, left/center/right align, color picker with hex readout).
- **Quick Scaffolds**: 150-Q (6 Subj), 100-Q (4 Subj), 50-Q (2 Subj) presets and an aptitude-area scaffold derived from active `AptitudeArea` rows with valid A4 margins (15mm) satisfying `OmrMappingValidator`.
- **Compiled HTML & AI Suite Reference**: Readonly compiled markup (`omr-comp`, `omr-header`, `omr-field-box`, `omr-instructions`, `omr-id-container`, `omr-grid-block`) + copyable `COMPONENT_SUITE_PROMPT` prompt contract with dedicated Copy buttons.
- **Persistence & Hydration**: Components persist in `authoring_json.studio.components` via single atomic `PUT /setup/omr/templates/canonical/drafts/{draft}/studio` with CSRF protection. Reloading hydrates all placed components and bypasses the entry gate.

## Shared palette, upload source, and spacing hardening (2026-08-22)

- **Source kind persistence**: Sheet Designer saves the selected `source_kind` through `SaveOmrStudioStateRequest` → `SaveOmrCanonicalStudioState`; choosing/uploading PDF, DOCX, or image updates the draft before ingestion, eliminating `source_kind_mismatch`.
- **Mode reset**: deliberate mode changes clear raster URL, Sheet Designer components, compiled HTML, Stage 3 sections, selection, and pending upload state before the next path renders.
- **Shared typed palette**: `ComponentPath`, `ComponentPaletteFilter`, `COMPONENT_PATH_FILTERS`, `paletteForPath()`, and `overlayPaletteForPath()` define the path contract; Upload Document filters roll/question components while retaining text and shared QR/margin/marker controls. The filter config is persisted under `authoring_json.studio.palette_filter`.
- **Sheet layout parity**: Sheet Designer now exposes margin inset, square/target/aruco marker pattern, QR reservation coordinates, printable overlay palette, and visible Stage-1 margin/marker/QR canvas layers.
- **Bubble geometry parity**: Sheet Designer grid and roll inspectors expose editable bubble/row pitch; entering Stage 3 derives sections from the component geometry so configured spacing carries forward.
- **Upload completion hardening**: successful source-run status accepts both `completed` and queue-native `succeeded`; Inertia reload refreshes the draft lock version before the automatic Stage-2 save. Source render jobs ignore stale source assets and concurrent normalized-page asset writes deduplicate safely.

## Verification evidence

- OpenSpec: `openspec validate omr-canonical-template-authoring --strict` → **Valid**.
- Vitest Component Suite: `tests/frontend/omr-components.test.ts` → **13 passed (13)** covering creation, atomic rendering, path-filtered shared palette, section data conversion, and sizing geometry.
- Full Vitest Suite: `tests/frontend/` → **7 test files passed, 32 passed (32)**.
- Sheet Designer Browser Proof (Draft 2, `CANONICAL-HTML`):
  - Fresh entry gate renders; Sheet Designer path exposes the shared overlay palette, Sheet Layout controls, QR reservation coordinates, marker pattern picker, and margin inset.
  - Question Grid inspector exposes editable Bubble Pitch and Row Pitch values; Stage 1 → Question Bubbles carries those values into Stage 3.
  - Deliberate mode switch clears Sheet Components/compiled HTML and exposes the Upload Document path with no Question Grid or Examinee ID cards.
  - PNG upload persists `source_kind=image`, avoids `source_kind_mismatch`, completes the source run, and advances to Layout & Overlays without a lock conflict.
- Focused OMR PHP suites: **47 passed, 229 assertions**.
- Canonical focused Laravel suite before this hardening: **56 passed, 1 skipped, 261 assertions**.
- Parity command: `php artisan omr:canonical-parity` → **PASSED for all 7 seeded versions**.

## Qualification truth

- Both seeded marker profiles remain `candidate`.
- All supplier/canonical demo drafts remain unqualified and inactive.
- No seeder fabricates `physical_qualified`; UI qualification requires explicit printer/device identity, measured marker-center distances, and operator confirmation that the physical matrix passed.
- Real physical printer/device/paper acceptance remains open and is the production activation blocker.
