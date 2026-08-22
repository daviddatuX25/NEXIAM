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
## Compiled decorations, QR controls, and editor history (2026-08-22)

- **Decorated compiled HTML**: `renderFullSheetHtml()` now accepts explicit sheet decorations and emits four selected-pattern fiducials plus a `data-omr-overlay="blank_instance_qr"` reservation. Static HTML contains only a QR placeholder; signed instance QR generation remains server/print authoritative.
- **Shared QR controls**: Sheet Designer and Stage 2 use `StudioQrReservationControls.svelte` with lock, X/Y, and a 20–50 mm square size slider mapped to persisted `qrW`/`qrH`.
- **Local editor history**: `studioHistory.ts` provides a bounded 100-entry snapshot ring with branch invalidation, coalesced edits, undo/redo, and snapshot isolation. Durable authoring state is history-tracked; view/transport state is excluded. Undo/redo autosaves through the existing atomic endpoint and mode/source resets clear history.
- **History UX**: Toolbar Undo/Redo buttons expose disabled/ARIA state; `Ctrl/Cmd+Z`, `Ctrl/Cmd+Shift+Z`, and `Ctrl/Cmd+Y` work outside native text controls; drag and input edits commit as coalesced transactions.
## Functional inputs, appearance, and layers (2026-08-22)

- **Field components**: Candidate Name, Birthdate, and Room / Seat now render a separate label and response region. Input kind supports text/number/date/multiline; input shape supports box/dotted-line/underline. A Custom Input Box is available in the Sheet Designer palette.
- **Appearance**: Component background is independent from typography color and supports transparent, white, black, or custom hex colors. Input fill is separately configurable.
- **Layers**: Sheet Components is now a compact Back-to-front layer list with drag reorder, raise/lower buttons, lock, remove, persisted `zIndex`, and collapsible visibility.
- **Sidebar chrome**: Draft code remains visible; draft name is available as a tooltip. The mode description is a tooltip instead of persistent copy.
## Bubble-stage geometry and customization follow-up (2026-08-22)

- **Stage separation**: Stage 3 uses dedicated visual bounds that include the rendered label, bubble diameter, padding, and row gaps; Sheet Designer component bounds remain separate.
- **Row Pitch**: the rendered row height and CSS gap now use the same 10.5 px bubble footprint, so increasing Row Pitch moves rows and expands the green wrapper together.
- **Fiducials**: target markers are absolutely positioned at all four corners; ArUco has a distinct grid pattern instead of the square fallback.
- **Customization**: instructions expose an editable label; Examinee ID Matrix exposes typography and bubble diameter; typography alignment includes Justify; clicking canvas background clears component selection.
## Autosave serialization and refresh recovery (2026-08-22)

- **Save queue**: `performSave()` now serializes all atomic studio PUT requests through one promise queue, preventing same-lock concurrent autosaves and repeated conflict failures.
- **Refresh persistence**: browser proof added Document Header + Question Grid, waited for one `200` studio save, refreshed the draft, and recovered both components (`Layers (2)`, two canvas blocks).
- **Runtime path**: restored the `FONT_FAMILY_OPTIONS` import in `StudioTypographyControls.svelte`; final browser proof returned zero page errors and zero failed requests.

## Excalidraw-style Sheet Designer controls (2026-08-22)

- **Selection set**: `selectedComponentIds` supports plain single selection, Shift-click toggle on canvas and layer rows, blank-canvas clear, and group movement while `selectedComponentId` remains the inspector primary target.
- **Deletion**: Delete and Backspace remove the complete selection through the existing history/autosave transaction; the inspector and layer-row Remove controls remain available.
- **Duplication**: Ctrl/Cmd+D duplicates unlocked selected components with collision-safe IDs and an 8 mm offset. Alt/Option-drag duplicates the clicked unlocked component or selected group, then moves the copies with blue copy-drag feedback.
- **Lock semantics**: locked components remain selectable for inspection/unlock, but do not move and are excluded from duplication.
- **Layer and guidance**: Ctrl/Cmd bracket shortcuts retain raise/lower/front/back actions; the Layers panel visibly documents Shift-click, Alt-drag, and Delete. Grouping, lasso, frames, and deep selection remain deferred.

### Controls verification

- Browser proof on Draft 2: Shift-click selected two components, group drag moved both, Delete/Backspace removed the complete selection, Ctrl/Cmd+D created a new selected copy, and Alt-drag created and moved a second selected copy.
- Shortcut guards: Ctrl/Cmd+D did not duplicate while a numeric inspector input was focused; locked-component Ctrl/Cmd+D did not create a copy.
- Refresh proof: the final two-component demo state rehydrated with `Layers (2)`; page-error and failed-request listeners returned empty arrays, and the refresh response sweep found no HTTP status ≥400.
- Frontend suite: **7 files passed, 38 tests passed**; production build exited `0`; `openspec validate omr-canonical-template-authoring --strict` exited `0`.

## Unified QR canvas-item and modifier drag controls (2026-08-22)

- **Shared selection**: the unique QR reservation uses the `qr` canvas-item ID, appears as `QR Reservation` in Layers, participates in Shift selection, Delete, lock/unlock, and group movement.
- **Axis lock**: holding Shift during a drag locks movement to the dominant horizontal or vertical axis after the pointer crosses the threshold; this applies to components, QR group drags, overlays, and Stage 3 blocks.
- **Alt+Shift**: selected duplicateable components are copied and dragged with the axis lock; the unique QR reservation is intentionally not cloned because the server contract has one `blank_instance_qr` slot.
- **Browser proof**: QR + component multi-selection, Shift-locked QR/group movement, Alt+Shift copy drag, QR lock/remove controls, clean runtime reload, and no failed HTTP responses were verified on Draft 2.
- **Verification**: frontend suite **38 tests passed**; production build exited `0`; existing detector warnings remain limited to contrast suggestions and the intentional canvas grid background.

## Examinee ID Matrix footprint parity (2026-08-22)

- **Root cause**: Stage 1 `canvasComponentBlockSizeMm()` sized the selected border from a bubble-only section formula, while the renderer added a title, write-in row, padding, and a fixed `gap-0.5`; the last two digit rows therefore overflowed the border.
- **Fix**: added a shared Stage-1 matrix footprint helper covering the title/write-in/padding/ten-row structure, and replaced the fixed row gap with the configured Row Pitch-derived value.
- **Evidence**: the supplied reproduction measured the old border bottom at `366.6 px` while row 9 ended at `398.4 px`; after the fix the compact border bottom is `402.0 px` and the last row ends at `398.4 px`. At Row Pitch `12 mm`, the border grows to `553.3 px` while the last row ends at `549.7 px`.
- **Verification**: frontend suite passed; production build exited `0`; browser reload returned zero page errors and zero HTTP responses ≥400.

## Matrix pitch and printable-page constraints (2026-08-22)

- **Content-hug width**: Stage-1 canvas width now follows the actual label/bubble footprint instead of the legacy stored 58 mm width, removing right-side dead space and allowing the matrix to move closer to the page edge.
- **Pitch floor**: matrix and question-grid pitch values normalize to a rendered minimum of **5.2 mm**; the UI exposes that minimum and valid maxima. Values below the floor no longer appear editable while producing unchanged bubble positions.
- **Page constraints**: oversized question grids normalize pitch, row count, and X/Y placement against the printable A4 area; a 50-row/25 mm test normalized to 11 rows and stayed inside the sheet instead of overflowing.
- **Browser evidence**: default matrix width measured `112.2 px` at the canvas scale, Row Pitch displayed `5.2 mm`, lower input attempts stayed at `5.2 mm`, and current runtime/HTTP checks were clean.

## Canonical aptitude-area scaffold (2026-08-22)

- **Canonical labels**: General Ability, Verbal Aptitude, Numerical Aptitude, Spatial Aptitude, Perceptual Aptitude, and Manual Dexterity.
- **Projection**: the canonical workflow now filters to those six compatibility codes and orders them in that exact sequence, excluding legacy profile-only areas from this scaffold.
- **Scaffold output**: each area creates exactly 25 MCQ items with contiguous ranges Q1–25 through Q126–150.
- **Verification**: seeded projection showed `Scaffold from System Aptitude Areas (6)`; browser proof produced six grids with 25 rows each. Focused PHP tests passed (3 tests / 48 assertions); frontend suite and production build passed.

## Scaffold inspector binding and grid controls (2026-08-22)

- **Aptitude binding**: scaffolded grids persist `aptitudeAreaCode`; the inspector select is now controlled and opens on the selected area instead of `-- Choose Aptitude Area --`.
- **Grid appearance**: Question Grid now exposes Bubble Size `4.2–8 mm` and Question Label Size `5–12 pt`; canvas and compiled HTML use both values.
- **Verification**: browser proof showed `LOGIC` selected for General Ability, 25 rendered rows, and both controls present. Frontend suite passed **40 tests** and production build exited `0`.





## Verification evidence
- Functional browser proof: Candidate Name and Custom Input Box expose separate input regions, Input Type/Shape controls, Component Background control, compact `Layers (2)` list, custom layer selection, and zero page/request errors.
- Frontend suite: **7 files passed, 38 tests passed**.

- Focused Sheet Designer browser proof (Draft 2, `CANONICAL-HTML`):
  - Compiled HTML textarea contains four `omr-fiducial-square` elements and `data-omr-overlay="blank_instance_qr"` with the selected QR size.
  - QR size slider updates the compiled reservation to 34 mm.
  - Undo after adding a component returns `Sheet Components (0)`; redo restores `Sheet Components (1)`.
  - Keyboard undo works after the toolbar path and emits the live “Undid …” status.
  - Upload Document Stage 2 exposes the same QR size control.
- Stage 3 geometry proof: green wrapper height grows from **422.6 px** at Row Pitch 6.8 mm to **731.7 px** at Row Pitch 12 mm; last bubble remains inside the wrapper.
- Marker proof: target and ArUco classes render at all four corner coordinates.
- Selection proof: clicking blank canvas clears the floating inspector.
- Focused OMR PHP suites: **47 passed, 229 assertions**.
- Canonical focused Laravel suite before this hardening: **56 passed, 1 skipped, 261 assertions**.
- Parity command: `php artisan omr:canonical-parity` → **PASSED for all 7 seeded versions**.

## Qualification truth

- Both seeded marker profiles remain `candidate`.
- All supplier/canonical demo drafts remain unqualified and inactive.
- No seeder fabricates `physical_qualified`; UI qualification requires explicit printer/device identity, measured marker-center distances, and operator confirmation that the physical matrix passed.
- Real physical printer/device/paper acceptance remains open and is the production activation blocker.
