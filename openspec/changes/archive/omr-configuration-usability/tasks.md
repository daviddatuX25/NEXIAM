# Tasks: OMR Configuration Usability

> **Execution rule:** complete phases in order. Phases 1–3 unblock the test administrator's primary dead ends first; every phase ships with its tests and browser evidence before the next starts. No task is done without its failure state working.

## 1. Navigation spine

- [x] 1.1 Move `breadcrumbs` from `PageHeaderBar` to `StaffLayout` on all five OMR pages (Index, Templates/Show, Templates/Builder, Booklets/Index, Pairs/Create); keep `PageHeaderBar` for title/description/actions
- [x] 1.2 Config index journey hub: anchored sections (Templates, Booklets, Pairs, Print queue) with per-section counts + primary action in each section header; readiness chips stay on top
- [x] 1.3 Cross-links: template Show ↔ its pairs → booklet Show; booklet Show ↔ pairs → template Show; pair rows link both sides; "next step" affordance after booklet save ("Booklet saved — now activate a pair") linking `/setup/omr/pairs`
- [x] 1.4 Tests: breadcrumb props present on every OMR page response (assertInertia `staff_navigation`/layout props where applicable); index renders four section anchors

## 2. Preview infrastructure

- [x] 2.1 Extract `RenderBundlePdf` service (BundleRenderer + DomPdfPrintRenderer → PDF bytes); refactor `RenderPrintJob` to call it — single visual source
- [x] 2.2 `GET /setup/omr/templates/{version}/preview` — capability-gated inline PDF (`private, no-store`), no job/storage/audit; typed render-failure message
- [x] 2.3 `POST /setup/omr/templates/preview-draft` — render an unsaved `template_json` (+ overlay draft) without persisting; typed validation errors
- [x] 2.4 Template Show: "Preview sheet" per version (inline frame or new-tab link) + "Open in builder" for drafts
- [x] 2.5 Booklet key preview on Booklets/Show: area-grouped position grid with A–D letters + per-area totals + digest
- [x] 2.6 Tests: preview route returns PDF bytes with the right headers and denies without capability; preview-draft renders and rejects invalid JSON; render failure surfaces typed error; `RenderPrintJob` output unchanged after refactor (existing printing tests green)

## 3. Print overlays

- [x] 3.1 Migration: `print_overlay_json` jsonb nullable on `omr_answer_sheet_template_versions`
- [x] 3.2 `BundleRenderer` reads overlay (title, header_lines, footer_lines, name_box_label, per_page notes); null overlay = current defaults
- [x] 3.3 Builder per-page meta panel (title/header/footer/name-box label/note) → overlay payload saved with draft via `SaveAuthoredTemplateDraft`
- [x] 3.4 Template Show "Sheet meta" editor for drafts (append-only for activated versions per existing triggers — editing an active overlay creates a new draft)
- [x] 3.5 Tests: overlay renders in preview + print output identically; null overlay unchanged; engine worker envelope carries no overlay (assert `OmrPinnedSnapshot` request unchanged)

## 4. Booklet authoring v2

- [x] 4.1 `Booklets/Show` page: versions, key preview (2.5), pairs, "New draft from this version" (active) / "Edit draft" (draft)
- [x] 4.2 `Booklets/Create` page (`?draft=<id>` pre-populates); index's toggle-form replaced by links to Create/Show
- [x] 4.3 `CopyBookletVersionToDraft` action (copies questions to a new draft; refuses if a draft already exists; audited)
- [x] 4.4 Bulk key entry: textarea parser (`position area_code option`, `#` comments, whitespace/comma tolerant) → merge into table; per-line errors with line numbers; save validation unchanged as authority
- [x] 4.5 Tests: copy action semantics + audit; parser valid/invalid rows (line numbers); pre-population props; key preview props

## 5. Builder v2

- [x] 5.1 `GET /setup/omr/templates?version=<draft_id>` hydrates builder; activated versions refused with typed message
- [x] 5.2 Instant SVG preview upgrade: option letters, question numbers, identity digit labels, page border, header text (sketch labeled approximate)
- [x] 5.3 Full preview button → `preview-draft` opens authoritative PDF in new tab
- [x] 5.4 Tests: hydration props for a draft; activated-version refusal; full-preview posts current JSON

## 6. Retirement confirmation panel

- [x] 6.1 Inline confirm panel on every Retire button: lists the artifact + cascading pairs (props from a small `retirePreview` query); final Retire button inside the panel; backend action unchanged
- [x] 6.2 Tests: panel props list cascading pairs for template/booklet retirement; pair retirement lists only itself

## 7. Optional bubbled birthdate (founder-gated lock extension)

- [x] 7.1 Builder "Birthdate grid" toggle per page (off by default) → `BIRTHDATE_1..6` QTYPE_INT block, same grid mechanics; sheet label "BIRTHDATE (MMDDYYYY)" via overlay default when enabled
- [x] 7.2 `identity_regions_json` carries both `applicant_number` and `birthdate` region labels (intake + authored projections)
- [x] 7.3 Detection/merge/review: per-page birthdate reconciliation; MMDDYYYY sanity validation (month/day/year ranges) surfaced as review issues; conflicts recorded in identity-conflict evidence naming per-page values
- [x] 7.4 Review examinee card shows "Applicant No." and "Birthdate" lines
- [x] 7.5 Tests: activation with birthdate grid projects both regions; invalid dates flagged; conflict evidence names values; booklet/key untouched
- [x] 7.6 Record the founder lock extension in `.ai/rules/` (identity = applicant number + optional bubbled birthdate; free-form OCR stays out)

## 8. Verification & handoff

- [x] 8.1 Focused suites per phase; full suite green (306+ baseline at start of this change)
- [x] 8.2 Chromium walkthrough as TA: index → template Show → preview sheet → open builder → load draft → set header/footer → full preview → save → booklet Show → key preview → new draft from active → bulk paste key → pair activation → capture pair selection
- [x] 8.3 Update `HANDOFF.md` (credentials, URLs, overlay + preview commands, observed evidence), master checkpoint, and `omr-configuration`/`omr-printing-pipeline` task notes where this change supersedes their open items
- [x] 8.4 Run `openspec validate omr-configuration-usability --type change --strict --no-interactive`

## Observed evidence (2026-08-15)

- Preview/print single source: `RenderBundlePdf` (BundleRenderer + DomPdfPrintRenderer) serves `GET /setup/omr/templates/{version}/preview` and `POST /setup/omr/templates/preview-draft`; `no-store, private`; draft JSON invalid → 422.
- Margin-safety renderer: field labels render above their empty shade bubbles with an explicit clearance; the name box is computed above the topmost field labels and hidden when the header stack leaves < 1mm; calibration strip lives below the border in the bottom margin; footer lines stack up from the calibration anchor. Vision-verified on v4 + v5 preview PDFs (all pages): no label/bubble overlap, box/border/calibration collisions.
- Builder auto-layout enforces the 80px margin (MARGIN=80, BUBBLE=56, COLUMN_X bands) with typed refusal; SVG preview matches the renderer's identity-grid convention (labelsGap across x, bubblesGap down y).
- Seeder idempotence: `OmrDemoSeeder` re-run is a no-op (guards on any existing pair/version row); `retireFirstPair` targets the v1 pair only; `OmrDemoBatchSeeder` skips existing batches.
- Bugs found by the seeded demo batch + new tests: (1) per-page `image_url` omitted the `sheet` route param; (2) `OmrCaptureSheet::$fillable` lacked `detected_birthdate` (silent data loss); (3) `OmrPinnedSnapshot::manifestExpectations` needed the same `BIRTHDATE_` scorable exclusion as the deriver. All fixed with regression tests.
- Fake detector supports `omr.fake.birthdate_by_page` (page => MMDDYYYY) for deterministic cross-page conflict fixtures.
