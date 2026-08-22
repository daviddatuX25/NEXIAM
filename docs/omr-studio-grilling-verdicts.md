# OMR Template Studio — Self-Grilling Verdicts
> Self-interrogation session: 2026-08-21
> Scope: UX architecture, code patterns, full functionality alignment
> Method: Grilling skill (evaluative mode against gap audit)

---

## Branch 1: UX Architecture — Should Studio be a standalone wizard or embedded inside CanonicalWorkflow?

**Question**: Studio currently has its own 5-stage wizard (Base → Layout → Alignment → Bubbles → Export) but lives inside CanonicalWorkflow which has its own 9-stage pipeline (draft → convert_inspect → page_prepare → overlay → map → digital_verify → compile → physical_activate → blank_print). These are two competing navigation models. Which is the authority?

**Verdict**: **CanonicalWorkflow is the lifecycle authority. Studio is the geometry editor only.**

Rationale:
- CanonicalWorkflow manages draft stage transitions that the backend enforces (`STAGE_DRAFT` → `STAGE_PAGE_PREPARED` → `STAGE_MAPPING_COMPLETE` → etc.)
- Studio's internal "stages" are visual groupings for the geometry editing task, not lifecycle gates
- The 5-stage wizard creates a false sense of progression — clicking "Stage 5: Export" doesn't mean the template is ready to print (no compose, no digital verify, no compile)
- **Decision**: Studio should be a single-surface editor (no wizard stages). The left panel shows contextual tool panels (layout tools, bubble tools, overlay tools) toggled by toolbar buttons, not wizard steps. CanonicalWorkflow provides the real lifecycle progression.

**UX Consequence**: Replace the 5-stage stepper with a toolbar mode switcher: `[Layout] [Bubbles] [Overlays] [Preview]`. No "Export" stage — export actions live in CanonicalWorkflow's compile/print stages.

---

## Branch 2: File Upload — Should Studio handle ingest or should that be CanonicalWorkflow's job?

**Question**: Stage 1 "Choose Base" has Photo/Scan/PDF/HTML buttons but no actual upload. Should Studio handle the upload+conversion pipeline, or should users arrive at Studio only after their source is already ingested?

**Verdict**: **CanonicalWorkflow handles ingest. Studio receives the composed page.**

Rationale:
- Ingest is async (queues `RenderOmrSourcePagesJob`), has progress polling, can fail, needs retry — this is lifecycle management, not geometry editing
- The backend flow is: ingest → convert → inspect pages → confirm → compose. Only AFTER compose is the draft at `STAGE_PAGE_PREPARED`, ready for overlay + mapping
- Studio should receive `draft.raster_url` (composed page image) and the canonical zones as read-only backdrop
- For parametric/blank mode: skip ingest entirely, compose generates a blank A4 page with margins + markers

**UX Consequence**: Remove Stage 1 from Studio. CanonicalWorkflow shows ingest/convert/compose UI. Studio opens only when draft reaches `page_prepare`/`overlay`/`map` stages (which is already how CanonicalWorkflow wires it at line 320: `{:else if ['page_prepare', 'overlay', 'map'].includes(activeStage)}`).

---

## Branch 3: Overlay Architecture — Fixed tag registry or freeform custom elements?

**Question**: Backend has a closed `ALLOWED_TAGS` list (7 tags). User wants "overlays to become elements with text and font adjustable, importable libraries, intricate customization." These are opposing philosophies. Which wins?

**Verdict**: **Expand the tag registry with a `custom_text` tag, but keep it closed. No freeform HTML injection.**

Rationale:
- Open HTML injection into print templates is a security surface (XSS in PDF renderers, layout-breaking content)
- The existing `OmrOverlaySpec` is well-designed: closed tag set, fixed overflow strategy, hash-signed
- User's actual need is: (a) editable text content, (b) adjustable font size, (c) draggable position, (d) toggleable elements
- All four are achievable by adding tags `custom_text`, `signature_area`, `instructions_area` to `ALLOWED_TAGS`, plus wiring the existing `font_size` and `content` fields that the spec already partially supports

**Concrete additions to ALLOWED_TAGS**:
```php
'custom_text',        // Freeform text with editable content
'signature_area',     // Candidate signature box
'instructions_area',  // Exam instructions
'header_title',       // Document header
'instance_qr',        // Maps to existing blank_instance_qr at print time
```

**UX Consequence**: Overlays panel gets a "+ Add Element" button with preset types. Each overlay element is a draggable box on the canvas with inline text editing, font-size slider, and delete button. No code/HTML injection.

---

## Branch 4: Print ↔ Template Fidelity — How do we guarantee the printed sheet matches the JSON?

**Question**: `window.print()` produces browser-rendered output at arbitrary DPI. The template.json has pixel coordinates at 300 PPI. These won't match during scanning. How do we close this gap?

**Verdict**: **The printed sheet MUST be server-rendered at exact PPI. Studio's canvas is a preview only.**

Rationale:
- OMRChecker's `CropOnMarkers` warps the scanned image to `pageDimensions` and then indexes bubbles at `origin + row*rowPitch + col*optionPitch`. If the printed sheet's physical bubble positions don't match these pixel coordinates at the declared PPI, recognition fails.
- Browser printing has uncontrolled margins, scaling, DPI. Two users printing the same template will get different physical layouts.
- The correct pipeline: Studio edits geometry → saves mapping → CanonicalWorkflow runs `ComposeOmrCanonicalPages` → server renders exact 300 PPI raster → user downloads/prints that artifact
- `window.print()` should be removed from Studio or explicitly labeled "PREVIEW ONLY — NOT FOR SCANNING"

**Code Consequence**: The "Print" button in Studio becomes "Download Composed Sheet (PDF)" which calls the compose endpoint and downloads the server-rendered artifact. The `PrintOmrBlankInstances` action (which adds QR codes per instance) is the actual production print path.

---

## Branch 5: Studio State Persistence — Where does the Studio's parametric state live?

**Question**: Studio has rich state (sections with ids/positions/pitches/types, margin settings, overlay toggles, bubble radius). Currently this is sent as `studio` key in the mapping payload but Laravel strips it. Where should it live?

**Verdict**: **Persist `studio` state in `authoring_json.studio` alongside `authoring_json.mapping` and `authoring_json.overlay`.**

Rationale:
- The Studio state is the source of truth for the geometry — `emitNativeMapping()` derives the OMRChecker-native format FROM it
- If Studio state is lost, reopening the draft shows empty sections (no way to reconstruct the parametric model from native blocks)
- `authoring_json` already stores `mapping.input`, `overlay`, `pages`, `canonical`, `verification` — adding `studio` is consistent
- No new endpoint needed: include `studio` in the mapping save request, persist it in the same transaction

**Code Changes**:
1. Add `'studio' => ['nullable', 'array']` to `SaveOmrMappingRequest::rules()`
2. In `SaveOmrCanonicalMapping::handle()`: `$authoring['studio'] = $payload['studio'] ?? null;` alongside the mapping
3. In `OmrCanonicalTemplateController::show()`: include `studio` in the draft's `authoring` prop (already passes full `authoring_json`)
4. In Studio: on mount, hydrate sections/margins from `draft.authoring?.studio` if present

---

## Branch 6: Mapping Stage Gate — How does parametric mode work without ingest?

**Question**: Backend requires `STAGE_PAGE_PREPARED` for mapping save. Parametric mode (blank canvas) has no source document. How does the draft reach that stage?

**Verdict**: **Parametric compose: a server endpoint that generates a blank A4 composed page with margins + markers, advancing to `STAGE_PAGE_PREPARED`.**

Rationale:
- The compose step is not optional — it establishes `pageDimensions`, `marker_zones`, `content_zones` that mapping validation uses
- For parametric mode: compose receives `{ paper: 'a4', margins_mm: 15, marker_profile_id: X }` and generates a blank white page with markers at the computed positions
- This is a subset of what `ComposeOmrCanonicalPages` already does — it just has no raster base to composite onto
- The draft progresses: `draft` → (skip ingest/convert/confirm) → `compose` → `page_prepared` → overlay + mapping

**Code Consequence**: `ComposeOmrCanonicalPages::handle()` already accepts a `$payload` array — add a `parametric: true` mode that generates a blank canvas instead of compositing onto ingested pages. CanonicalWorkflow should auto-compose when Studio opens for a parametric draft.

---

## Branch 7: Code Patterns — Single monolithic Svelte file or component decomposition?

**Question**: `OmrTemplateStudio.svelte` is 1450+ lines. Is this maintainable? Should it be decomposed?

**Verdict**: **Decompose into 4 focused components + 1 coordinator.**

Rationale:
- Current file mixes: canvas rendering, drag/zoom engine, section management, overlay management, save/persistence, JSON export, stage navigation
- These are independent concerns with different change frequencies
- Svelte 5's `$props()` and `$state()` make component composition clean

**Target structure**:
```
OmrTemplateStudio.svelte          — coordinator: toolbar, mode switching, save orchestration
├── StudioCanvas.svelte           — pan/zoom, paper rendering, block positioning, drag handles
├── StudioBubblePanel.svelte      — section management, pitch sliders, option types, question ranges
├── StudioOverlayPanel.svelte     — overlay element list, drag/font controls, add/remove
└── StudioExportPanel.svelte      — JSON preview, copy, download, compose trigger
```

**Code Quality Rules**:
1. All geometry math stays in `omrStudioGeometry.ts` (already there) — components consume, never compute
2. State flows down via props, events flow up via callbacks (`onSectionsChanged`, `onOverlayChanged`)
3. Canvas component owns drag/zoom state internally; parent doesn't reach into it
4. Save debounce lives in coordinator; panels just call `triggerAutosave()` callback

---

## Branch 8: Marker Profile — Cosmetic buttons vs real backend-managed profiles

**Question**: Frontend shows square/target/aruco as cosmetic patterns. Backend has `OmrMarkerProfile` with real `asset_sha256`, `size_mm`, `quiet_zone_mm`. How do we reconcile?

**Verdict**: **Replace cosmetic buttons with a real marker profile picker from the backend-provided `marker_profiles` prop.**

Rationale:
- The `marker_profiles` prop is already passed to Studio (via CanonicalWorkflow)
- Each profile has `code`, `version`, `family`, `status`, `size_mm`, `quiet_zone_mm`
- The marker asset (actual fiducial image) is the geometry authority — cosmetic CSS squares don't represent real detection targets
- Studio should show a dropdown/card picker of available profiles, call `selectMarkerProfile` endpoint, and render the actual marker preview

**UX Consequence**: Stage 2's "Marker Pattern" section becomes a profile picker showing the real markers. Selected profile determines corner marker size and quiet zone on the canvas.

---

## Branch 9: QR Code — Preview placeholder vs real preview

**Question**: Studio shows a Lucide QR icon. Should we render a real QR preview?

**Verdict**: **Keep the placeholder icon. Real QR is instance-specific.**

Rationale:
- Each blank instance gets a unique HMAC-SHA256 signed QR payload (instance code, manifest hash, page number)
- A "preview QR" would be unsigned and meaningless — it can't be scanned for anything real
- The QR overlay box is correctly modeled as a spatial reservation (x, y, width, height) — the real QR is composited at print time by `OmrBlankPrintOrchestrator`
- Showing a fake QR creates false confidence ("I can scan this preview!")

**UX Consequence**: Keep the QR icon as-is. Add a tooltip: "Unique signed QR will be generated for each printed instance."

---

## Branch 10: Overall UX Flow — What's the simplest correct user journey?

**Verdict: Optimal flow**:

```
1. CanonicalWorkflow: Create draft (code, name, source_kind)
2. CanonicalWorkflow: Upload source (if photo/scan/PDF) OR skip (if parametric)
3. CanonicalWorkflow: Confirm pages (if uploaded) OR auto-compose (if parametric)
4. CanonicalWorkflow: Select marker profile
5. CanonicalWorkflow: Compose canonical page → STAGE_PAGE_PREPARED
6. [Studio opens] — user sees composed page as backdrop
7. Studio: Add/configure overlay elements (header, signature, instructions, QR reservation)
8. Studio: Add/configure bubble sections (position, pitch, question ranges)
9. Studio: Save mapping + overlay + studio state → STAGE_MAPPING_COMPLETE
10. CanonicalWorkflow: Run digital verification → STAGE_DIGITAL_VERIFIED
11. CanonicalWorkflow: Compile immutable version → STAGE_COMPILED
12. CanonicalWorkflow: Physical qualification + activate
13. CanonicalWorkflow: Print blank instances (production sheets with real QR codes)
```

Steps 6-9 are Studio's domain. Everything else is CanonicalWorkflow. Clean separation.

---

## Summary Verdicts

| Decision | Verdict |
|---|---|
| Studio navigation | **Single-surface editor with toolbar modes, not wizard stages** |
| File upload | **CanonicalWorkflow handles it, not Studio** |
| Overlay model | **Expand ALLOWED_TAGS with 5 new tags, keep closed registry** |
| Print fidelity | **Server-rendered 300 PPI artifact, remove window.print()** |
| State persistence | **Save `studio` key in `authoring_json`, hydrate on mount** |
| Parametric compose | **Server endpoint for blank A4 compose** |
| Code structure | **Decompose into 4 components + coordinator** |
| Marker profile | **Real profile picker from backend data, not cosmetic buttons** |
| QR preview | **Keep placeholder icon, real QR is instance-specific** |
| Overall flow | **Studio = geometry editor (steps 6-9), Workflow = lifecycle (all other steps)** |
| Route cleanup | **3 legacy pages + 3 routes deletable after canonical verified; keep `builder()` as redirect** |
| Upload UX | **CanonicalWorkflow shows dropzone/progress/conversion; Studio never shows upload controls** |

---

## Branch 11: Route & Page Cleanup — What's legacy, what stays, what merges?

**Question**: There are two controllers and ~20 Svelte pages for OMR configuration. Which are legacy, which are canonical, and what should be cleaned up?

### Full Route/Page Map

#### Controller 1: `OmrConfigurationController` (LEGACY — pre-canonical)
| Route | Method | Svelte Page | Status |
|---|---|---|---|
| `GET /setup/omr` | `index()` | `OmrConfiguration/Index.svelte` | **KEEP** — top-level OMR dashboard (templates, booklets, pairs, print jobs) |
| `GET /setup/omr/templates` | `templatesIndex()` | `OmrConfiguration/Templates/Index.svelte` | **KEEP** — template list view |
| `GET /setup/omr/templates/builder` | `builder()` | `OmrConfiguration/Templates/Builder.svelte` | **REDIRECT ONLY** — when `omr.canonical.enabled=true`, redirects to canonical draft. Legacy path renders `Builder.svelte` → `BlueprintBuilder` + `TemplateEditor` |
| `POST /setup/omr/templates` | `storeAuthoredTemplate()` | (form submit from Builder) | **LEGACY** — saves via `SaveAuthoredTemplateDraft` action. Canonical uses `SaveOmrCanonicalMapping` |
| `GET /setup/omr/templates/{template}` | `showTemplate()` | `OmrConfiguration/Templates/Show.svelte` | **KEEP** — template version detail, pairs, capture policy |
| `GET /setup/omr/templates/{version}/preview` | `previewVersion()` | (JSON response) | **KEEP** — read-only version preview |
| `POST /setup/omr/templates/preview-draft` | `previewDraft()` | (JSON response) | **LEGACY** — used by old Builder |
| `POST /setup/omr/layout/preview` | `layoutPreview()` | (JSON response) | **LEGACY** — old layout solver preview |
| `POST /setup/omr/templates/{version}/overlay` | `storeOverlay()` | (called from Show.svelte) | **KEEP** — overlay save for existing versions |
| `POST /setup/omr/templates/{version}/test-capture` | `testCapture()` | `TestCapturePanel.svelte` | **KEEP** — builder test capture |
| `GET /setup/omr/templates/test-capture/{token}/{file}` | `testCaptureAsset()` | (asset stream) | **KEEP** — test capture result files |
| `GET /setup/omr/booklets` | `booklets()` | `OmrConfiguration/Booklets/Index.svelte` | **KEEP** — booklet management |
| `GET /setup/omr/booklets/create` | `bookletCreate()` | `OmrConfiguration/Booklets/Create.svelte` | **KEEP** |
| `GET /setup/omr/booklets/{booklet}` | `bookletShow()` | `OmrConfiguration/Booklets/Show.svelte` | **KEEP** |
| `POST /setup/omr/booklets` | `storeBooklet()` | (form submit) | **KEEP** |
| `POST /setup/omr/booklets/{version}/copy` | `copyBooklet()` | (action from Show) | **KEEP** |
| `GET /setup/omr/pairs` | `pairs()` | `OmrConfiguration/Pairs/Create.svelte` | **KEEP** — pair management |
| `GET /setup/omr/intake` | `intake()` | `OmrConfiguration/Intake.svelte` | **KEEP** — intake/bundle upload |

#### Controller 2: `OmrCanonicalTemplateController` (CANONICAL — current)
| Route | Method | Svelte Page | Status |
|---|---|---|---|
| `GET .../canonical/drafts/{draft}` | `show()` | `Canonical/CanonicalWorkflow.svelte` → embeds `OmrTemplateStudio.svelte` | **PRIMARY** |
| `GET .../canonical/drafts/{draft}/assets/{asset}` | `asset()` | (stream) | **PRIMARY** |
| `GET .../canonical/drafts/{draft}/booklet-options` | `bookletOptions()` | (JSON) | **PRIMARY** |
| `DELETE .../canonical/drafts/{draft}` | `destroy()` | (action) | **PRIMARY** |
| `POST .../source/ingest` | `ingest()` | CanonicalWorkflow UI | **PRIMARY** |
| `GET .../source/ingest/run` | `sourceRun()` | CanonicalWorkflow polling | **PRIMARY** |
| `POST .../source/ingest/cancel` | `cancelSourceRun()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../source/rectify` | `rectify()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../compose` | `compose()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../source/pages/confirm` | `confirmPages()` | CanonicalWorkflow UI | **PRIMARY** |
| `PUT .../overlay` | `overlay()` | Studio autosave | **PRIMARY** |
| `PUT .../mapping` | `mapping()` | Studio autosave | **PRIMARY** |
| `PUT .../marker-profile/{profile}` | `selectMarkerProfile()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../compile` | `compile()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../verify/digital` | `digitalVerification()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../{version}/verify/physical` | `physicalQualification()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../{version}/activate` | `activate()` | CanonicalWorkflow UI | **PRIMARY** |
| `POST .../{version}/blank/print` | `printBlankInstances()` | `BlankInstancePrintPanel.svelte` | **PRIMARY** |
| `GET .../{version}/blank/instances` | `blankInstances()` | `BlankInstanceHistoryPanel.svelte` | **PRIMARY** |
| Various blank instance actions | supersede/void | CanonicalWorkflow UI | **PRIMARY** |
| Marker profile calibration/qualify | `calibrationArtifact()` / `qualifyMarkerProfile()` | CanonicalWorkflow UI | **PRIMARY** |

#### Svelte Pages Disposition
| Page | Disposition |
|---|---|
| `OmrConfiguration/Index.svelte` | **KEEP** — OMR dashboard |
| `OmrConfiguration/Intake.svelte` | **KEEP** — bundle upload |
| `Canonical/CanonicalWorkflow.svelte` | **KEEP** — lifecycle orchestrator |
| `Canonical/OmrTemplateStudio.svelte` | **KEEP** — geometry editor (refactor per Branch 7) |
| `Canonical/Index.svelte` | **KEEP** — canonical draft list |
| `Canonical/BlankInstancePrintPanel.svelte` | **KEEP** |
| `Canonical/BlankInstanceHistoryPanel.svelte` | **KEEP** |
| `Templates/Show.svelte` | **KEEP** — version detail |
| `Templates/Index.svelte` | **KEEP** — template list |
| `Templates/TestCapturePanel.svelte` | **KEEP** — test capture UI |
| `Templates/Builder.svelte` | **⚠️ LEGACY** — wrapper for BlueprintBuilder + TemplateEditor |
| `Templates/BlueprintBuilder.svelte` | **⚠️ LEGACY** — old raw JSON editor |
| `Templates/TemplateEditor.svelte` | **⚠️ LEGACY** — old manual bubble coordinate editor |
| `Booklets/Index.svelte` | **KEEP** |
| `Booklets/Create.svelte` | **KEEP** |
| `Booklets/Show.svelte` | **KEEP** |
| `Pairs/Create.svelte` | **KEEP** |

### Verdict: Cleanup Plan

**Phase 1 (Now):** Leave legacy files in place. The `builder()` route already redirects to canonical when `omr.canonical.enabled=true` (default). No breakage.

**Phase 2 (After canonical is fully functional):** Delete 3 legacy pages + 3 legacy routes:
- Delete `Templates/Builder.svelte`, `Templates/BlueprintBuilder.svelte`, `Templates/TemplateEditor.svelte`
- Remove routes: `storeAuthoredTemplate`, `previewDraft`, `layoutPreview`
- Keep `builder()` method as a permanent redirect to canonical

**Phase 3 (Optional):** Merge `OmrConfigurationController` into `OmrCanonicalTemplateController` for the shared concerns (template show, booklets, pairs). Low priority — two controllers is fine for readability.

**Key invariant**: The legacy builder path (disabled by default) must never be accidentally re-enabled. The `omr.canonical.enabled` config flag is the switch. When all canonical features are verified working, hardcode it to `true` and remove the flag.

---

## Branch 12: File Upload UX — What does Studio show during ingest/conversion?

**Question**: The verdict says "file upload is not Studio's job" but the user points out Studio should show loading states. What exactly does Studio display when the draft is in an early stage?

**Verdict**: **Studio shows the draft's current stage status as a read-only banner. CanonicalWorkflow owns the upload controls.**

### How it actually works (and should work):

```
CanonicalWorkflow.svelte (line 320):
  {:else if ['page_prepare', 'overlay', 'map'].includes(activeStage)}
    <OmrTemplateStudio {draft} {marker_profiles} {urls} ... />
```

Studio **only renders** when the draft is at `page_prepare`, `overlay`, or `map` stage. Before that, CanonicalWorkflow shows its own stage-appropriate UI:

- `draft` stage → "Create draft" form
- `convert_inspect` stage → File upload dropzone + conversion progress bar + cancel button (this is where the ingest UI lives)
- `page_prepare` stage → Studio opens (draft has composed raster_url)

So Studio **never needs** upload controls. By the time Studio renders, the file is already uploaded, converted, confirmed, and composed.

### What Studio SHOULD show at mount:

1. **Composed page backdrop**: `draft.raster_url` → `<img>` as the canvas background (already partially wired)
2. **Draft metadata banner**: Template code, name, stage, compose status
3. **"Not yet composed" guard**: If somehow Studio opens before `STAGE_PAGE_PREPARED` (parametric mode), show a "Composing..." spinner and auto-trigger compose

### What CanonicalWorkflow needs (the actual upload UX):

The `convert_inspect` stage in CanonicalWorkflow needs:
1. **File dropzone** — `<input type="file" accept="image/*,.pdf,.html">` + drag-and-drop
2. **Upload progress** — XHR progress bar during `POST .../source/ingest`
3. **Conversion status** — Poll `GET .../source/ingest/run` every 2s showing: `queued → processing → completed / failed`
4. **Page confirmation** — Show extracted pages for staff review (`confirmPages()`)
5. **Cancel button** — `POST .../source/ingest/cancel`
6. **Error states** — Conversion failure, unsupported format, corrupt file, over size limit

This is all CanonicalWorkflow UI, not Studio. Studio opens fresh once compose completes.

### For parametric (blank canvas) mode:

1. User creates draft with `source_kind: 'preset'` or `'blank'`
2. CanonicalWorkflow skips `convert_inspect` stage entirely
3. Goes straight to marker profile selection → compose (blank A4 + markers)
4. Studio opens with a white page + margin guides + marker positions
5. No upload UI shown at all

---

# QA HARDENING PASS (2026-08-21, second iteration)

Method: traced every user flow line-by-line against backend action source.
Result: 5 critical + 6 important corrections. The plan is NOT hardened until these are in.

## H1 — CRITICAL: The Studio save protocol is broken three ways at once

Traced `performSave()` (OmrTemplateStudio.svelte:385-463) against both actions:

1. **Overlay save destroys the mapping.** `SaveOmrCanonicalOverlay::handle()` sets
   `authoring['mapping'] = null`, `verification = null`, reverts stage to
   `page_prepared`. Studio saves mapping FIRST then overlay — so every autosave
   deletes the mapping it just wrote.
2. **lock_version never advances client-side.** Controller responses put
   `lock_version` at TOP LEVEL, but Studio reads `data.draft?.lock_version`
   (nonexistent) → falls back to stale value. Second autosave: mapping PUT sends
   stale lock → `concurrency_conflict`. **All saves deadlock after the first.**
3. **Overlay tags forbidden.** `instance_qr`/`instruction_box`/`signature_box`/
   `metadata_box` are not in `ALLOWED_TAGS` → overlay PUT always throws.

**Verdict**: Two sequential PUTs can never be correct here. Replace with ONE
atomic endpoint:

```
PUT /setup/omr/templates/canonical/drafts/{draft}/studio
body: { mapping?, overlay, studio?, lock_version, idempotency_key }
```

New action `SaveOmrCanonicalStudioState`: assertOwner → stage gate
[page_prepared, mapping_complete, digital_verified] → validate overlay →
validate mapping IF blocks present → ONE transaction: write overlay + mapping
(input/field_blocks/hash) + studio; null verification only when mapping changed;
stage = mapping present ? mapping_complete : page_prepared; lock+1; audit.
NO cross-invalidation. Frontend: single PUT, read top-level `lock_version`
from response into `currentLockVersion`.

Old `overlay`/`mapping` endpoints remain for CanonicalWorkflow lifecycle stages
(their invalidation semantics are correct there — staff re-entering the overlay
stage IS a layout change).

## H2 — CRITICAL: Overlay `content` is dropped by the spec

`OmrOverlaySpec::validate()` normalizes to tag/scope/x/y/w/h/overflow/font_size
only — `content` and `name` are DISCARDED. Static-text overlays (header,
instructions, signature label) would render with nothing to draw.

**Verdict**: extend spec + `SaveOmrOverlayRequest` to persist `content`
(nullable string, max 500, plain text — strip all markup) and `name`
(max 64). Renderer (H3) consumes them.

## H3 — CRITICAL: No renderer draws template-scope overlay boxes

`OmrBlankPrintOrchestrator::composePage()` composites ONLY the per-instance QR
(hardcoded top-right: `width - qrSize - 12, 10`) + display code onto the static
PDF. It never reads overlay boxes. Therefore header/instructions/signature
boxes are invisible on printed sheets unless baked at compose time.

**Verdict**:
- Verify `OmrCanonicalComposer::compose()` draws template-scope boxes into the
  static PDF + raster + digital proof (first implementation step; if absent, add
  a renderer: text boxes with font_size + overflow shrink/wrap/truncate,
  signature frame with label line).
- Extend orchestrator: if compiled overlay has a `blank_instance_qr` box,
  place QR at that box; else current hardcoded fallback. (QR detection at
  capture is whole-image, so position is safe to customize.)

## H4 — IMPORTANT: Parametric compose is unverified

`ComposeOmrCanonicalPages` delegates to `OmrCanonicalComposer::compose($draft,
$settings)`. Whether compose works with zero ingested pages (blank canvas) is
unconfirmed.

**Verdict**: first implementation step = read `OmrCanonicalComposer`; add
`mode: 'parametric'` path if missing (white raster at 300 PPI from paper mm,
marker_zones from selected profile insets, content_zones = inner frame).
Do not assume it exists.

## H5 — IMPORTANT: New drafts land on the wrong workflow tab

`CanonicalWorkflow` `domainView` maps backend stage `draft` → tab `'map'`,
so a brand-new draft opens straight into an empty Studio instead of the
Start/Inspect upload UI. Upload controls exist (submitIngest etc.) but are
buried behind manual tab clicks.

**Verdict**: fix mapping: `draft → 'draft'`, `source_confirmed →
'convert_inspect'`, `page_prepared → 'map'`.

## H6 — IMPORTANT: Studio error surfacing

`saveStatus = 'error'` is silent. Surface typed banners: `mapping_invalid`
(list validator errors), `tag_forbidden`, `concurrency_conflict` → "Draft
changed elsewhere — reload" with refetch button.

## H7 — IMPORTANT: Zero-section guard

Atomic endpoint: overlay-only save allowed when `mapping.blocks` empty (stage
stays page_prepared). Studio shows "Add at least one question section before
this template can be verified" and never claims "saved" for a mapping-less
draft.

## H8 — IMPORTANT: Upload UX completeness (convert_inspect tab)

Verify/complete: drag-drop + file input (image/pdf/html/docx), 25M limit
message, XHR progress bar, poll `source_run` @2s with status chips
(queued/processing/completed/failed), cancel button, typed failure banner,
page-confirm grid, then compose CTA. Parametric drafts skip this tab entirely.

## H9 — Tests to add (QA completeness contract)

1. Atomic studio save persists overlay+mapping+studio, bumps lock ONCE, no
   cross-invalidation; second save with returned lock succeeds.
2. Expanded tags accepted; unknown tag still rejected.
3. `content` persisted for static tags; markup stripped.
4. Studio state round-trips: save → `show()` → hydrate.
5. Parametric compose: raster asset + marker_zones, stage page_prepared, no
   ingest required.
6. Overlay-only save (no blocks) keeps stage page_prepared.
7. Stale lock → typed concurrency error.
8. Composer/proof renders new template-scope tags without exception; QR box
   position honored by orchestrator with fallback.

## H10 — Record in OpenSpec

Append H1-H9 as tasks to `omr-canonical-template-authoring` tasks.md; keep
`openspec validate --strict` green.

## H11 — Implementation order (dependency-safe)

1. Read `OmrCanonicalComposer` (H4/H3 ground truth).
2. Backend: spec content/name (H2) → atomic action+route+request (H1) →
   parametric compose (H4) → renderer extensions (H3).
3. Frontend: Studio single-call save + lock readback + error banners (H1/H6/H7)
   → workflow tab fix (H5) → upload UX (H8) → hydrate studio state.
4. Tests (H9) → OpenSpec (H10) → full 22-suite regression.

---

# FINAL HARDENED VERDICT

The original 12-branch plan was architecturally right but functionally
UNHARDENED: its save-protocol verdict ("add studio to request rules", "expand
tags") would still leave every autosave deadlocked or self-destructing.
With H1-H11 incorporated, the plan covers: save atomicity, lock sequencing,
overlay content persistence, print-time rendering, parametric entry, upload
UX, error surfacing, edge guards, tests, and docs.

**Status: HARDENED — conditional on H4/H3 verification of `OmrCanonicalComposer`
as the first implementation step (flagged, not assumed).**

---

# SESSION STATUS (paused 2026-08-21)

- Plan saved; implementation NOT started. TDD/test pass deferred by founder.
- Resume entry points:
  1. `docs/omr-studio-alignment-audit.md` — gap inventory + backend payload contracts.
  2. This file — 12 UX/architecture verdicts + H1-H11 hardening + implementation order.
- Verified ground truth (do not re-derive):
  - `OmrCanonicalComposer::compose()` requires STAGE_SOURCE_CONFIRMED + confirmed
    pages → parametric compose missing (H4); draws markers only, NO overlay
    rendering (H3).
  - `OmrBlankPrintOrchestrator::composePage()` hardcodes QR top-right, ignores
    overlay boxes (H3).
  - Studio `performSave()` dual-PUT is deadlocked/self-destructive (H1).
  - `OmrOverlaySpec` drops `content`/`name` (H2); ALLOWED_TAGS lacks studio tags.
  - Legacy cleanup candidates: `Templates/{Builder,BlueprintBuilder,TemplateEditor}.svelte`
    + routes `storeAuthoredTemplate`/`previewDraft`/`layoutPreview`
    (delete only after canonical verified; `builder()` stays as redirect).
  - Test conventions: Pest, `Permission::findOrCreate(GUIDANCE_SCORING_OMR,'web')`
    + `givePermissionTo`; `OmrAuthoringDraftService::create($actor,$code,$name,$kind)`;
    `omrValidMappingBlock()` fixture helper exists in OmrMappingValidatorTest.
