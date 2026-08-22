# OMR Template Studio — Frontend ↔ Backend Alignment Audit
> Generated: 2026-08-21 | Status: Gap analysis complete, pre-implementation

## Critical Gaps (blocks functionality)

### 1. Overlay Tag Mismatch (HTTP 500 on every save)
- **Frontend** `emitOverlayBoxes()` emits tags: `instance_qr`, `instruction_box`, `signature_box`, `metadata_box`
- **Backend** `OmrOverlaySpec::ALLOWED_TAGS` only allows: `institution`, `assessment`, `template`, `blank_instance_code`, `page_number`, `page_count`, `blank_instance_qr`
- **Result**: Every overlay save fails with `tag_forbidden`
- **Fix options**: (A) Expand ALLOWED_TAGS, (B) Remap frontend tags to allowed backend tags

### 2. Mapping Save Requires STAGE_PAGE_PREPARED
- `SaveOmrCanonicalMapping::handle()` checks `$draft->stage !== STAGE_PAGE_PREPARED` → throws
- Studio tries to save mapping on `draft`-stage drafts → 500
- **Fix options**: (A) Auto-compose for parametric mode, (B) Stage bypass for Studio, (C) Require compose step in Studio flow

### 3. File Upload Not Wired
- Stage 1 shows Photo/Scan/PDF/HTML buttons but has no `<input type="file">` or `fetch(urls.source_ingest)` call
- Backend route `POST .../source/ingest` exists and works
- Need: file input + FormData POST + poll `urls.source_run` for conversion status

## Important Gaps (print/template fidelity)

### 4. Print ≠ template.json Parity
- `window.print()` produces browser-resolution viewport, not 300 PPI vector sheet
- The JSON template uses pixel coordinates at 300 PPI (11.811 px/mm)
- Printed sheet won't match scan coordinates → OMRChecker misreads
- **Fix**: Server-side composed page download via `ComposeOmrCanonicalPages`

### 5. Studio State Not Persisted
- `performSave()` sends `{ mapping, studio: {...}, lock_version, idempotency_key }`
- `SaveOmrMappingRequest` only validates `mapping` + `lock_version` + `idempotency_key`
- Laravel strips the `studio` key → sections, margins, pitches lost on reload
- **Fix**: Add `studio` to request rules as `nullable|array`

## Enhancement Gaps (user-requested)

### 6. Overlay Customizability
- Overlays have hardcoded strings and fixed CSS positions
- Backend already accepts `font_size` per box but UI doesn't expose it
- Overlays should be draggable with position/size/font controls

### 7. Marker Profile Selection Not Wired
- Frontend shows cosmetic marker pattern buttons (square/target/aruco)
- Backend has `PUT .../marker-profile/{profile}` → `selectMarkerProfile()`
- Frontend doesn't call this endpoint — marker choice is visual-only

### 8. Compose Step Not Wired
- Stage 3 shows static alignment crosshairs but no compose call
- Backend `POST .../compose` generates deterministic 300 PPI raster + canonical zones
- Required before mapping save (stage gate)

## Working Features ✅

| Feature | Status |
|---|---|
| Booklet options fetch | ✅ Wired (onMount → fetch booklet_options) |
| Download template.json | ✅ Working (client-side blob) |
| Copy JSON to clipboard | ✅ Working |
| Mapping geometry emission | ✅ emitNativeMapping produces valid OMRChecker fieldBlocks |
| Multi-section question blocks | ✅ Working (add/remove/configure) |
| Pitch sliders (bubblesGap/labelsGap) | ✅ Working (frontend renders) |
| Canvas drag & zoom | ✅ Working |
| Stage navigation | ✅ Working |
| Raster preview (passive) | ✅ Works if raster_url populated by server |
| Digital verify / Compile / Activate | ✅ Handled by parent CanonicalWorkflow.svelte |
| Real QR at blank print time | ✅ Backend OmrBlankPrintOrchestrator renders signed QR per instance |

## Architecture Notes

- **OmrTemplateStudio** is embedded inside **CanonicalWorkflow.svelte** at stages `page_prepare`, `overlay`, `map`
- Studio handles visual geometry editing (stages 1-5 internally)
- CanonicalWorkflow handles lifecycle (draft → ingest → compose → overlay → map → verify → compile → activate → print)
- Two controllers: `OmrCanonicalTemplateController` (canonical workflow) and `OmrConfigurationController` (legacy builder)
- `/setup/omr/templates/builder` route redirects to canonical workflow when `omr.canonical.enabled` is true

## Key Backend Contracts

### Overlay Payload Shape (what backend expects)
```json
{
  "overlay": {
    "boxes": [
      { "tag": "institution|assessment|template|...", "scope": "template|print_job|blank_instance", "x": 100, "y": 50, "width": 300, "height": 80, "overflow": "truncate|shrink|wrap", "font_size": 12.0 }
    ]
  },
  "lock_version": 0,
  "idempotency_key": "uuid"
}
```

### Mapping Payload Shape (what backend expects)
```json
{
  "mapping": {
    "page_width": 2480,
    "page_height": 3508,
    "page_count": 1,
    "blocks": [{ "name": "...", "page_no": 1, "field_type": "QTYPE_MCQ4", "labels": [...], "rows": 25, "options": 4, "origin_x": 295, "origin_y": 1004, "width": 886, "height": 2067, "option_pitch_px": 66, "row_pitch_px": 80, "bubble_width_px": 53, "bubble_height_px": 53 }]
  },
  "lock_version": 0,
  "idempotency_key": "uuid"
}
```
