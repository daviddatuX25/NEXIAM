# Canonical template authoring handoff

## Implementation status

Unified Template Studio, Atomic Save Protocol, and Dual-Input Engine Integration complete and verified.
- **Single Atomic Studio Save (`PUT /setup/omr/templates/canonical/drafts/{draft}/studio`)**: FormRequest `SaveOmrStudioStateRequest` + Action `SaveOmrCanonicalStudioState` persisting mapping, custom overlays, and studio state (`authoring_json.studio`) in a single DB transaction without mutual deletion or concurrency deadlocks.
- **Expanded Overlay Spec & Content Persistence**: `OmrOverlaySpec` supports `header_title`, `instructions_box`, `signature_box`, `metadata_box`, `custom_text`, and `blank_instance_qr` with sanitized `content`, `name`, and `font_size`.
- **Parametric Blank Canvas Mode**: `OmrCanonicalComposer.php` generates clean white 300 PPI rasters with 4 corner fiducials directly from paper dimensions and marker insets without requiring ingested documents.
- **Dynamic Blank Print QR Placement**: `OmrBlankPrintOrchestrator.php` positions cryptographic QR codes dynamically from overlay box reservations with safe top-right fallback.
- **Dual-Input Test Capture (Live Camera + Upload)**: Stage 5 in `OmrTemplateStudio.svelte` provides live viewfinder snapshot capture via `getUserMedia` and drag-and-drop file upload against OMRChecker diagnostic verification.
- **Clean Cutover & Parity**: 100% vector print PDF and frame-anchored `template.json` export cleanly with zero coordinate drift. Focused backend and frontend suites are green; the full backend run has only the two unrelated setup-readiness expectation failures recorded below.
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

## Reliable Sheet Designer → Question Bubbles transition (2026-08-22)

- **Runtime fix**: `StudioCanvas` now receives the bindable `activeSectionIndex` prop required by Stage 3 section selection; this removes the `ReferenceError: activeSectionIndex is not defined` crash.
- **Persistence fix**: explicit stage transitions cancel pending autosave timers, save the converted component/section state first, and roll back the visible stage if the save fails instead of leaving an unsaved Stage-3 view.
- **Verification**: Draft 1 transitioned to Question Bubbles with zero page errors or HTTP failures; reload preserved the active Question Bubbles step and Examinee ID matrix.

## Roll mapping schema parity (2026-08-22)

- **Preview contract**: the exported studio JSON now identifies the Examinee ID Matrix as `QTYPE_INT` and emits options `0`–`9`; it no longer labels the roll section as `QTYPE_MCQ4` with A–D.
- **Verification**: Stage 3 → Verify & Export showed `examinee_id_5` as `QTYPE_INT` with ten digit options, zero page errors, and no failed responses.

## Sheet Designer → bubble-stage visual parity (2026-08-22)

- **Visual root cause**: HTML-mode Stage 1, compiled HTML, and Stage 3 previously rendered the bubble matrix independently. The non-green label reported by the founder is the compiled renderer's `.omr-subject-header` (`subjectName || name`), not the Stage 3 diagnostic badge. Switching to the compiled iframe exposed that print-only render path and its different header/spacing rules.
- **Current visual fix**: HTML authoring keeps the Stage 1 component render tree mounted through Question Bubbles and Verify & Export. Stage 3 adds only selection/mapping outlines, and section edits synchronize back to their source component. Question-grid `bubbleDiameterMm` survives component → section conversion.
- **Browser proof, Draft 1**: Stage 1 and Stage 3 each render the same 11 component IDs with byte-for-byte identical inline geometry; Stage 3 adds seven empty-text outlines, renders no HTML preview iframe, and transitions with zero page/request failures. `Part_2` remains `top: 207.984px; left: 113.896px; width: 81.708px; height: 433.3px` in both stages.
- **Accuracy limitation**: this proves editor-stage visual continuity only. It does NOT prove that the Sheet Designer, server-composed 300-PPI PDF, compiled native bundle, and OMRChecker-aligned raster share one geometry authority.

## Engine-parity implementation (2026-08-23)

- **Canonical definition/materializer**: `CanonicalSheetDefinition` normalizes editable studio intent and saved mapping into 300-PPI marker-frame geometry. `CanonicalArtifactMaterializer` validates the native template through `NativeBundle::loadFromDirectory`, derives the review manifest, emits a non-blank reference SVG, renders the server page/PDF, asks `pdftoppm` for the exact target dimensions without a second full-page GD allocation, checks page-count parity, and computes stable artifact hashes.
- **Compile promotion**: materialized native template/config/manifest/reference/marker/raster/PDF assets are draft-owned and immutable. Compile promotes the candidate files byte-for-byte when its candidate version and verification artifact hash match; stale candidates are rejected.
- **Stage 3 accuracy boundary**: HTML-mode Stage 3 now requests `/preview`, uses the server canonical page as the underlay, and draws only native mapping guides. A persisted Stage-3 state is reopened only after preview revalidation. Missing marker geometry or worker failures return typed 422 errors instead of showing an unverified green sheet.
- **Studio/runtime hardening**: `StudioCanvas.svelte` now defines the primary-selection and aggregate-drag predicates used by component chips, resize handles, and drag styling. Candidate profiles may materialize draft previews with explicitly provisional 12 mm / 3 mm geometry; their artifact manifests record `geometry_status=provisional`, while compile and activation still require evidence-bound qualification. Native worker projection strips authoring-only field metadata, emits `customLabels` as an object, preserves semantic native labels, and uses the exact bundle/config hash contracts.
- **Invalidation and page ownership**: mapping, overlay, studio, source, and marker-profile changes clear candidate artifacts and verification; validated field blocks retain source page ownership so multi-page candidates cannot silently collapse into page 1.
- **Label contract**: section header visibility/text is explicit in the component definition and carried into native rendering; compiled-only subject labels are no longer silently introduced by a separate iframe path.
- **Worker proof**: digital verification shades the materialized PDF, runs clean answer recovery through the detection port, executes image variants through a queue-safe `RunOmrNoGoMatrixJob` boundary plus layout verification, records real typed outcomes/hashes/evidence, and keeps application-owned instance lifecycle variants explicit.
- **Stage 4/export**: physical Test Capture is disabled before immutable compile; the aligned raster returned by OMRChecker is the default proof tab; engine overlay remains separate; native template download streams the immutable version asset, while client JSON is labeled non-authoritative.
- **Pinned worker characterization**: `test_marker_pipeline.py` and `test_layout_verification.py` pass against the pinned OMRChecker image after fixing the wrapper's missing `sys` import. The worker still owns preprocessing/alignment/classification; NEXIAM owns lifecycle, review, and grading.

- **Browser proof, Draft 1**: the seeded candidate profile now advances through `/preview` without fabricated qualification. The live page displays the canonical server-rendered underlay, seven native mapping guides, and “Native OMR geometry verified against the canonical server-rendered page,” with zero browser errors. The toolbar labels the marker geometry `preview provisional`; compile remains blocked until genuine profile qualification supplies measured size/quiet-zone evidence.
- **Builder/render visual-footprint correction**: native bubble origins begin inside the component footprint—grid `+5 mm/+8 mm`, ID matrix `+6.4 mm/+14 mm`—so subject headers, option labels, row numbers, and bubbles no longer share one origin. Builder/server subject bands now share a 3.5 mm physical height; candidate fields use stored typography plus a shared padding/label/gap/input formula instead of hardcoded `7pt`/`4.5mm`; same-row grids validate/reflow their complete 28 mm visible footprints with ≥2 mm gaps (Draft 1 normalized to X `15, 45.4, 75.8, 106.2, 136.6, 167`); Stage 3 retains the QR reservation, and returning to Sheet Designer restores four profile-family bullseye markers.
- **Printable artifact continuity**: Sheet Designer now has `Print Preview`, generated by the same materializer used by compile. Navigation-only `current_stage`/palette changes do not invalidate it. Entering Question Bubbles with an unchanged definition reuses the exact candidate without another `/preview` request. Question Bubbles defaults to the full `Print Sheet`, keeps the QR reservation, and offers marker-frame `Engine Aligned` and `Engine Overlay` comparisons without replacing or stretching the paper raster. Browser proof recorded zero extra preview requests across the transition.

## Verification snapshot (2026-08-23)

- Final focused visual-parity backend regression: **4 tests passed / 27 assertions**; broader canonical regression remains recorded above.
- Frontend: **42 tests passed**; production Vite build exits `0` with only pre-existing Svelte accessibility/state warnings.
- Pinned inference characterization: `test_marker_pipeline.py`, `test_layout_verification.py`, and `test_source_rectification.py` exit successfully in the Compose inference image.
- Full backend suite: **554 passed / 2 skipped**; two unrelated setup-readiness tests still expect 8 capability categories while the current application projects 9.
- Strict OpenSpec validation exits `0`.
- Remaining OpenSpec tasks are intentionally blocked, not fabricated complete: old-builder cutover, genuine physical end-to-end proof, and the final browser proof requiring a physically qualified marker profile.
## Verification update (2026-08-24)

- Source adapter matrix: `OmrSourceAdapterTest` now covers HTML, DOCX, PDF, image, and preset adapters converging on the same canonical page boundary contract (7 tests passed / 7 tests).
- Canonical continuity: `OmrCanonicalAuthoringTest` confirms uploaded page ownership survives studio save and marker-profile selection; `OmrCanonicalCompileTest` confirms candidate provisional geometry and immutable promotion.
- Memory boundary: digital no-go evidence is persisted through a per-variant callback, and large raster mutations run in an isolated PHP child with an explicit memory ceiling; the app process no longer retains all aligned/overlay payloads in the matrix.
- Browser proof: uploaded Draft 7 reaches the single keyed Verify & Export panel with no page errors; the digital request reaches the real worker and returns a visible typed `no_go_variant_failed` notice for perspective/rotation/light/blur/glare/low-resolution variants. Compile remains disabled because digital verification did not pass.
- Qualification truth is unchanged: candidate marker profiles remain provisional; no physical evidence, compile, activation, or native immutable download is claimed.
- Adversarial review fixed five load-bearing defects: stale long-running verification commits, provisional→qualified marker geometry promotion, failed-idempotency false success, cross-owner draft/asset reads, and unbounded/leaking raster mutation failures.
- Review regression: **25 backend tests / 155 assertions**, **42 frontend tests**, Vite production build, strict OpenSpec validation, and `git diff --check` pass. Added direct tests for owner-scoped reads, failed-key retry, and rejection of marker geometry changed after materialization.
- Two decisions remain open: evidence-backed no-go expectations/severity for six accepted perturbations, and which of the two in-progress OMR OpenSpecs is authoritative. No code change may manufacture either answer.
- Sidebar regression repaired: a missing close tag had nested the absolute 350 px tool card inside the bottom canvas-controls bar, collapsing it to 12.5 px high at the viewport bottom. Browser proof now measures `[x=210, y=84, width=350, height=902]`, confirms the controls bar no longer contains the sidebar, and reports zero page errors.
- Question Bubbles separation repaired: the three switches are views of one candidate (`Print Sheet` full raster, `Engine Aligned`, `Engine Overlay`). The printed raster never moves. An unlocked guide moves only the recognition origin emitted to the same native `template.json`; locking is editor metadata, persists without invalidating artifacts, and prevents guide movement. A normal guide move keeps the raster mounted and shows one amber “changes saved; refresh diagnostics” status, with no red error. Test & Verify reruns preview (`200`) and clears it. Genuine mapping refusals are typed 422. Browser proof: rose messages `0`, amber statuses `1` while dirty and `0` after verification, canonical raster continuously mounted.
- Recognition Mapping redesign: Sheet Designer and uploaded Draft 7 now render the same translucent MCQ/ID overlay (`7 guides / 670 rings` designed; `1 guide / 80 rings` uploaded), the fixed canonical raster remains underneath, and collision-bound drags stop before an invalid autosave (`0` failed responses). Stage-1-style Single question/Question range cards replace the compressed buttons. `Run recognition test` is separate from `Continue to Verification`; surfaces are `Print Sheet`, `Aligned Check`, and `Recognition Overlay`.
- Verification & Test Capture now exposes `Open printable PDF` with Actual Size instructions, retains device-camera and uploaded-photo paths, and removes normal-staff native-download/client-JSON controls. Scanner/runtime pairing remains signed-instance QR → immutable template version plus capture-batch template/booklet pair; internal `template.json` is automatic, not a staff file-selection burden.
- Redesign regression: **13 backend tests / 69 assertions**, **42 frontend tests**, production build, strict OpenSpec, and diff checks pass. Browser checks report canonical raster present, no rose errors, both source paths equivalent, print/camera/upload present, and native download absent.
- Exact overlay correction: the client no longer reconstructs rows with padding/number labels. Preview now returns one paper-space rectangle per expanded native bubble, derived from the same field blocks used by OMRChecker. Draft 1 proves `100` rings per MCQ25 block and `70` rings for the 7-column × 10-row Examinee ID grid; canvas labels are absent, rose errors are absent, and screenshot `/tmp/omp-sshots-15657b37e647ab8e.webp` shows the rings aligned without covering row/option labels.
- Examinee ID upward-drag blocker removed: Recognition Mapping had a legacy `marginMm + 15` minimum Y clamp that forced sections to ≥30 mm on A4. The uniform minimum is now the normal 15 mm printable margin. Browser proof moved the 7×10 ID guide upward by 10 screen pixels, produced no rose error, then returned it to persisted Y `20`.
- Zoom controls repaired: the toolbar previously mutated a parent `scale` that was never bound into `StudioCanvas`. Browser proof measures the sheet at `405.6×573.3`, Zoom In at `483.6×683.6`, and Zoom Out restored to `405.6×573.3`; `Fit` now describes the action without a false fixed percentage.
- Studio chrome refinement: top utility controls are now Undo/Redo, save state, and Save only. Page registration owns the marker profile explanation and provisional/qualified state; `nexiam.custom` comparison profiles are absent for ordinary sample-profile drafts. The Studio uses semantic theme tokens across workspace/sidebar/inspector/mapping/verification, while the paper remains `rgb(255,255,255)`.
- Responsive/theme browser proof: at 1024px there is no horizontal overflow, Save ends at x `1003.75`, and stage steps collapse to three 28px numbered controls with descriptive tooltips. At desktop the full stage labels return. Dark sidebar/registration compute to the elevated dark card token; printable paper remains white. Screenshot: `/tmp/omp-sshots-15658a536a1f3b73.webp`.
- Final UX regression: **10 authoring tests / 49 assertions**, **43 frontend tests**, production build, strict OpenSpec and diff checks pass. Impeccable reports only the advisory canvas grid, which is intentional on this measurement/editing surface.
- Current Studio PUT path rechecked: a valid Part 1 move returns `200`. An intentionally overlapping Part 2 numeric input is stopped by `validateStudioLayout`, shows `Block "Part_1" overlaps with block "Part_2".`, and emits **no PUT request**, eliminating the repeated browser-console 422 for ordinary invalid edits. Frontend suite is now **43 tests**.
- The former blue “OMR Optical Recognition Grid” row is now a semantic muted Recognition guides explanation with an emerald icon in both themes.
- Error presentation normalized: backend `mapping_invalid` now returns structured `errors[]`; Laravel field-validation maps are flattened; legacy JSON detail is decoded defensively. Recognition Mapping renders one titled bullet list with recovery copy, while the toolbar says only `Save blocked`. The manual Save button now wraps `performSave()` so its MouseEvent cannot become `source_kind`; browser proof shows manual Save `200` with no source-kind error and overlapping input produces no PUT.

## Shared verification/scanner repair cut — status (tasks 35.1–35.47)

- OpenSpec tasks 35.1–35.47 define the junior-executable shared verification/scanner repair cut. It introduces one deterministic proof plan, candidate/version diagnostic target adapters, durable verification runs, physical challenge packs, one normalized evidence projection, shared capture/evidence Svelte modules, and scanner signed-QR/pair wiring.
- Corrective scanner finding: canonical compile currently persists `review_manifest_json` but does not create the version-owned page/area/question/option rows that grading `Capture.svelte` expects through booklet-question `templateQuestion` links. This can produce missing/default per-item regions/options. `OmrTemplateGeometryProjector` + `OmrReviewGeometryQuery` are stop-gate tasks before claiming scanner per-item evidence works.

### Ownership — sole active OMR implementation owner (35.1, done 2026-08-26)

- This change (`omr-canonical-template-authoring`) is now the **sole active OMR implementation owner**. `omr-profile-test-readiness` implementation work is **paused/read-only**: its content remains untouched and nothing is copied from it; only the pinned worker/bundle/Test-capture evidence is cross-referenced.

### Scanner per-item geometry characterization (35.2, done 2026-08-26)

- Evidence: `system/tests/Feature/Setup/OmrCanonicalScannerGeometryCharacterizationTest.php` reproduces the gap through the real pipeline — qualified profile → draft → materialize → digital-verified → compile → active template/booklet pair → capture batch → grading `Capture.svelte` props. **2 tests / 34 assertions, green**, pinning current behavior honestly:
  1. Canonical compile stores `review_manifest_json` with real per-bubble geometry (2 MCQ4 labels × 4 options = 8 fields, every region `w`/`h` > 0) but creates **zero** `omr_template_areas` and `omr_answer_sheet_pages` rows for the compiled version.
  2. Booklet questions carry no `template_question_id`; `GradingController::omr()` therefore emits the documented fallback per item on the wire: `region [0,0,1,1]`, `options []`, `page_no 1` — the missing per-item regions/options `Capture.svelte` receives.
- These assertions are deliberate current-behavior pins; they must flip when tasks 35.4–35.10 land `OmrTemplateGeometryProjector` + `OmrReviewGeometryQuery` and replace the booklet-question→template-question projection.
- No implementation or qualification claim is made beyond tasks 35.1/35.2.






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
