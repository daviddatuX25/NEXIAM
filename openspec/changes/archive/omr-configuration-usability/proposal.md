# Proposal: OMR Configuration Usability

## Why

The OMR configuration cut (`omr-configuration`) shipped the full backend state machine — intake, compliant builder, booklet authoring, pair activation, retirement — but the staff surfaces are not yet a usable product. A test administrator today cannot: see a breadcrumb trail anywhere in the section (the prop goes to `PageHeaderBar`, which does not render breadcrumbs — `StaffLayout` does), **preview any answer sheet or booklet** (template Show is hashes-only; booklets are counts + digest; the builder preview is bare bubble dots without option letters, question numbers, identity labels, or borders), edit a booklet without retyping every question (the form opens empty even when a draft exists; active versions have no "new draft from this version" copy), load an existing draft into the builder (start-blank only), enter keys in bulk, put any header/footer text on the sheet (the header is hard-coded PHP in `BundleRenderer`), or add an extra bubbled identity field such as a birthdate. The founder direction for this cut: minimal surface, maximal flow — every feature functioning, no dead ends, flexible without sprawl.

## What Changes

- **Navigation spine**: breadcrumbs rendered on every OMR configuration page; the config index becomes a journey hub (Templates → Booklets → Pairs → Print queue) with section anchors, per-section primary actions, and cross-links between artifacts (template ↔ booklet ↔ pair).
- **Preview infrastructure**: a shared `RenderBundlePdf` service (the same `BundleRenderer` + DomPDF used by the print pipeline) powers three previews: a per-version sheet preview route on template Show, a "Full preview" of the unsaved builder draft, and a booklet key preview (area × position grid with A–D letters and distribution). The builder's instant SVG preview is upgraded to high fidelity (option letters, question numbers, identity digit labels, border, header text) but remains a sketch — the server render is the single visual truth.
- **Print overlays**: new `print_overlay_json` on template versions (title, header lines, footer lines, name-box label, per-page notes). Consumed ONLY by the renderer — never compiled into engine geometry (founder lock preserved: the engine still receives exactly the native bundle).
- **Booklet authoring v2**: dedicated Create page (pre-populated when editing a draft), Show page with key preview and pairs, "new draft from active version" copy action, and bulk key entry (paste `position area_code option` rows with per-line validation errors).
- **Builder v2**: load an existing draft, per-page meta panel for overlays, upgraded live preview, full-preview button.
- **Retirement safety**: inline confirm panel that lists cascading pairs before retiring (replaces bare `window.confirm`).
- **Extra identity field (founder-gated)**: an optional bubbled birthdate grid (MMDDYYYY) as an engine-native `QTYPE_INT` field — the same grid mechanics as the applicant number (no geometry invention) — with NEXIAM-side date validation, identity-region projection, per-page reconciliation, and review-card display. This explicitly extends the founder identity lock from "applicant number only" to "applicant number + optional bubbled birthdate"; free-form OCR stays out.

## Capabilities

### New Capabilities

- `omr-configuration-usability`: staff-facing previews, overlays, booklet editing ergonomics, builder load/meta, retirement confirmation, and the optional birthdate identity field, all under the existing `guidance.assessment.catalog` gate.

### Modified Capabilities

<!-- Canonical specs under openspec/specs/ are not yet promoted; this change extends the in-flight omr-configuration change. -->

## Impact

- Reuses: `BundleRenderer`, `DomPdfPrintRenderer`, `NativeBundle`, `SaveAuthoredTemplateDraft`, `SaveAuthoredBookletVersion`, `ActivateOmrConfiguration`, `RetireOmrConfiguration`, capability authorizer, existing print pipeline.
- Extends `omr-configuration` (sections 2–5). No provider-boundary or answer-key-authority changes. No new geometry authority: overlays are render-only, birthdate reuses engine-native grid mechanics.
- New migration: `print_overlay_json` (jsonb, nullable) on `omr_answer_sheet_template_versions`.
