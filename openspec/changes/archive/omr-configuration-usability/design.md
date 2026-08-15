# Design: OMR Configuration Usability

## Context

`omr-configuration` shipped the full backend (intake, builder, booklets, pairs, retirement) and first-pass screens. Browser review as a test administrator exposed: no breadcrumbs, no preview of sheets or booklets, booklet editing that retypes everything, builder that cannot load drafts, hard-coded sheet header text, no bulk key entry, bare `confirm()` retirement, and no path for extra identity fields like birthdate. Founder direction: minimal but flexible, every feature functioning, good flow.

## Philosophy (binding for this cut)

1. **Every screen answers three questions**: where am I (breadcrumbs), what exists (list + status), what's next (one primary action, plus next-step affordances). No dead ends: after saving a booklet, the page tells you the next step is pair activation and links it.
2. **Progressive disclosure**: the one-task-one-click path is always visible; power features (bulk entry, overlays, per-page notes) sit behind expandable panels on the same page — never hidden routes.
3. **Preview equals print**: one server-side render path (`BundleRenderer` + DomPDF) is the single visual truth for sheets, blank-sheet printing, and previews. The builder's instant SVG is a sketch only and is labeled as approximate.
4. **Authority separation**: print overlays are NEXIAM render-only data. The engine seam receives exactly the native bundle (unchanged hashes); overlays never touch geometry, labels, or the provider boundary.
5. **Every feature lands functioning**: each task ships with its test, its failure state, and browser evidence before the next phase starts.

## Decisions

1. **Breadcrumbs belong on `StaffLayout`.** `PageHeaderBar` gets a `description`/`actions` only; all OMR pages pass `breadcrumbs` to `StaffLayout`. (`PageHeaderBar` never renders breadcrumbs — passing them there is dead markup.)

2. **Config index = journey hub, not three tables.** One page, four anchored sections (Templates, Booklets, Pairs, Print queue) with per-section counts, the section's primary action in its header (so actions are visible without scrolling), readiness chips at top, and a recent-print-jobs list (from the existing `PrintJob` model, read-only). Cross-links: template Show lists its pairs with links to the booklet; booklet Show lists its pairs with links to the template; pair rows link both sides.

3. **Preview architecture — one service, three surfaces.**
   - Extract `RenderBundlePdf` (new service): `(OmrAnswerSheetTemplateVersion | array $templateJson, ?array $overlay): string` returning PDF bytes via `BundleRenderer` + `DomPdfPrintRenderer`. `RenderPrintJob` is refactored to call it (single visual source preserved), and two new read-only surfaces call it:
   - `GET /setup/omr/templates/{version}/preview` — inline PDF (`Content-Disposition: inline`, `Cache-Control: private, no-store`), capability-gated, no job, no storage, no audit (pure read). Shows the sheet exactly as it prints, including overlays.
   - `POST /setup/omr/templates/preview-draft` — body is the authored `template_json` + optional overlay draft; renders WITHOUT saving (staff can see the full sheet before creating any version). Validation failures return typed JSON errors; this is a preview, so nothing is persisted.
   - Failure states: render exceptions → typed message in the UI ("Sheet preview failed: …"), never a blank iframe.

4. **Print overlay model** — `print_overlay_json` (jsonb, nullable) on `omr_answer_sheet_template_versions`:
   ```json
   {
     "title": "NEXIAM OMR ANSWER SHEET",
     "header_lines": ["EX-DEMO-1 · MAIN HALL 101"],
     "footer_lines": ["Shade completely. Erasures void the sheet."],
     "name_box_label": "NAME (PRINT):",
     "per_page": [{ "page_no": 1, "note": "Answer only in this booklet." }]
   }
   ```
   - `BundleRenderer` reads it: `title` replaces the hard-coded "SYNTHETIC NEXIAM OMR ANSWER SHEET"; `header_lines` render below the title; `footer_lines` render bottom-center; `name_box_label` replaces "NAME (PRINT):"; `per_page[].note` renders under the page header. Null overlay = today's defaults (backward compatible).
   - Authored by the builder's per-page meta panel; stored on the draft version row by `SaveAuthoredTemplateDraft`; intake versions default to null (staff can set overlays later via a small "sheet meta" editor on template Show — drafts only, append-only after activation per existing trigger rules).
   - Explicitly NOT part of `template.json` and never sent to the engine — the worker envelope is unchanged.

5. **Booklet authoring v2.**
   - `Booklets/Show` page: versions with status/digest/questions, **key preview** (positions 1..N in area groups, each cell shows its A–D letter; per-area distribution counts; digest), pairs using this booklet, "New draft from this version" for active versions, "Edit draft" for drafts.
   - `Booklets/Create` page (new route): `?draft=<version_id>` pre-populates code/name/questions from an existing draft; otherwise blank with one row. Replaces the toggle-form bolted onto the index.
   - `CopyBookletVersionToDraft` action: copies an active version's questions into a new draft version of the same booklet (audited; refusal when a draft already exists — edit that instead). No key mutation on the active row.
   - **Bulk key entry**: textarea parsing `position area_code option` (whitespace/comma separated; `#` comments). Parser validates position contiguity and option A–D per line and reports line numbers. Parsed rows merge into the questions table before save; the existing `SaveAuthoredBookletVersion` validation remains the authority.
   - Failure states: parse errors listed per line; save errors typed inline + toast.

6. **Builder v2.**
   - `GET /setup/omr/templates?version=<draft_id>` hydrates the form from an existing draft (native bundle JSON + overlay); template Show's "Open in builder" links it. Activated versions are read-only and the builder refuses to load them for editing (drafts only).
   - Per-page meta panel: title, header lines, footer lines, name-box label, page note → assembled into the overlay payload on save.
   - Instant SVG preview upgrade: option letters A–D, question numbers, identity digit labels, page border, and header/title text — still a sketch; the **Full preview** button posts the current draft to `preview-draft` and opens the authoritative PDF in a new tab.

7. **Retirement confirmation panel.** Replace `window.confirm` with an inline expandable panel on the retire buttons: lists the artifact and every cascading pair (from the retire action's own query, surfaced as props), requires clicking "Retire" inside the panel. The backend action is unchanged (it already cascades + audits); the panel only makes the consequence visible.

8. **Birthdate identity field (founder-gated, approved direction 2026-08-14).** Extends the identity lock: identity = applicant number + optional bubbled birthdate; free-form OCR remains out.
   - Engine-native mechanics only: a second `QTYPE_INT` grid with `fieldLabels: ["BIRTHDATE_1..6"]` (digit positions across x, values 0–9 down y) — identical grid machinery to the applicant number, so the engine, renderer, and derivier need no new primitives. NEXIAM interprets the 6 digits as MMDDYYYY.
   - Builder gains an optional "Birthdate grid" toggle per page (off by default); `SaveAuthoredTemplateDraft` and `RegisterNativeBundle` already project identity regions generically — extend `identity_regions_json` to carry both `applicant_number` and `birthdate` region labels.
   - Review: `ReviewOmrCaptureSheet`/merge treat birthdate like the applicant number (per-page reconciliation, conflicts recorded in identity-conflict evidence_json naming the differing values). Review card displays "Applicant No." and "Birthdate" lines; NEXIAM validates MMDDYYYY sanity (month 1–12, day 1–31, year 1900–current) and flags invalid dates as review issues.
   - Answer key: birthdate is identity, not a scored item — no booklet questions, no key changes.
   - If the founder later revokes the lock extension, removing the feature is a builder toggle + UI only (no data-model forks).

## Risks / Trade-offs

- [Preview route leaks sheet design] → capability-gated (`guidance.assessment.catalog`), `no-store`, no public route; same protection as the existing print downloads.
- [Overlay drift between preview and print] → both call `RenderBundlePdf`; no second path (single-source invariant).
- [Bulk parser accepts garbage] → parser is advisory (builds the same rows the table would); `SaveAuthoredBookletVersion` remains the validator; per-line errors.
- [Birthdate grid ambiguity] → MMDDYYYY is fixed and documented on the sheet (label "BIRTHDATE (MMDDYYYY)"); NEXIAM-side validation flags impossible dates.

## Migration Plan

- One migration: `print_overlay_json` jsonb nullable on `omr_answer_sheet_template_versions` (no backfill — null means defaults).
- No changes to existing tables' semantics; no seeder changes required (overlay null = current behavior).
- Rollback: drop the column; remove routes/screens; print pipeline and config state machine untouched.

## Merge order

Navigation spine (1) → preview infrastructure (2) → overlays (3) → booklet authoring v2 (4) → builder v2 (5) → retirement panel (6) → birthdate field (7) → verification (8). Each phase is independently shippable; phases 1–3 unblock the TA's primary dead ends first.
