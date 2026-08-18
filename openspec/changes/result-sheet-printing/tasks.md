# Result-Sheet Printing Tasks

## 1. Template storage and CRUD (HTTP seam)

- [x] 1.1 Migration `result_sheet_templates` + `result_printed_at` on `result_releases`; model with constants.
- [x] 1.2 RED: feature test — Test Administrator creates an HTML template, activates it, and the previous active template deactivates atomically.
- [x] 1.3 `SaveResultSheetTemplate` action (single-active transaction, private doc storage + checksum, audit) + `Store/UpdateResultSheetTemplateRequest` + thin controller + routes.
- [x] 1.4 RED: feature test — DOCX upload stores privately with checksum; invalid file refused.
- [x] 1.5 `ActivateResultSheetTemplate` / `DeactivateResultSheetTemplate` actions + routes.
- [x] 1.6 GREEN: full CRUD suite green; denied without `guidance.assessment.catalog`.

## 2. Analyzer, alias catalog, preview (HTTP seam)

- [x] 2.1 RED: feature test — validate-document returns found/missing/extra placeholder groups.
- [x] 2.2 `ResultSheetTemplateAnalyzer` + `TemplateFieldAliasCatalog` (legacy names → area codes) + `AnalyzeResultSheetTemplateRequest` + route.
- [x] 2.3 RED: feature test — preview renders sample-filled markup at configured paper width for HTML and uploaded DOCX.
- [x] 2.4 `ResultSheetPlaceholderService::sampleData()` + preview path (server-side render, no queue).

## 3. Data provider and placeholder engine (renderer seam)

- [x] 3.1 RED: unit test — `ResultSheetDataProvider` maps a released attempt to applicant data with per-area raw + frozen `converted_score` ratings.
- [x] 3.2 Data provider implementation (finalized GradingSession scores → ScoreRecord; aliases resolved via catalog).
- [x] 3.3 RED: unit test — legacy placeholders resolve: `{{verbal_aptitude}}`, `{{scores_rows}}`, `_2` dual-slot, course checkmarks, institution/personnel.
- [x] 3.4 `ResultSheetPlaceholderService::buildReplacements()` + `buildDocxReplacements()` + `buildScoresRows()`.
- [x] 3.5 RED: unit test — fragmented DOCX macro repaired and filled.
- [x] 3.6 `DocxMacroRepairer` port (merge adjacent runs, strip XML from macros).

## 4. Result-sheet renderer (renderer seam)

- [x] 4.1 RED: unit test — HTML template renders non-empty PDF bytes (`%PDF`) with watermark and paper size through `PrintRenderer`.
- [x] 4.2 `ResultSheetRenderer` (HTML path: placeholder fill + wrap CSS + DomPDF).
- [x] 4.3 RED: unit test — DOCX template fills and converts through `LibreOfficeConverter` (fake-backed in unit tests; real binary guarded by `isAvailable()`).
- [x] 4.4 `LibreOfficeConverter` port (chunked headless convert + pdfunite/FPDI merge) + `config/result-sheet.php` + `phpoffice/phpword` + `setasign/fpdi` deps + LibreOffice in Docker images.
- [x] 4.5 RED: unit test — dual-slot (crosswise) render pairs two applicants on one sheet.

## 5. Queued print jobs — single + bulk (action/HTTP seam)

- [x] 5.1 RED: feature test — `RequestResultSheetPrint` for one released applicant creates a `queued` `result_sheet` job with pinned inputs, dedupes duplicates, and returns the job id.
- [x] 5.2 Action + `RequestResultSheetPrintRequest` + `POST /release/print` route + audit.
- [x] 5.3 RED: feature test — `RenderPrintJob` renders the `result_sheet` artifact to sensitive disk, marks `ready`, sets `result_printed_at` idempotently, audits ready/failed.
- [x] 5.4 `ResultSheetArtifactRenderer` registry branch in `renderPayload`.
- [x] 5.5 RED: feature test — bulk (≤200, copies) job: progress counters, per-item manifest, ready-with-partial, merged PDF.
- [x] 5.6 Bulk manifest + merged-PDF support in the renderer/job.
- [x] 5.7 RED: feature test — retry refuses stale pinned inputs; download re-authorizes, verifies checksum, and is audited.
- [x] 5.8 Retry guard + `PrintingController::download` audit + `result-sheet:prune` retention command (scheduled).

## 6. Printed tracking and release seam (action seam)

- [x] 6.1 RED: feature test — `MarkResultSheetsPrinted` toggles `result_printed_at` on release records, capability-gated and audited.
- [x] 6.2 Action + request + route + Release batch page props (printed state per release).
- [x] 6.3 RED: feature test — `PrepareResultDocument` produces a template-backed ready artifact with checksum; failure never fake-ready.
- [x] 6.4 Rewrite `PrepareResultDocument` (template-backed, private storage, checksum, typed failure); `ReleaseResult` unchanged.

## 7. UI (browser seam)

- [ ] 7.1 Setup › Result Sheet Templates Index/Create/Edit (UI kit, placeholder guide, analyzer panel, live preview, activate/deactivate, delete confirm).
- [ ] 7.2 Release › Print Batch page (selection, copies, PDF/DOCX/ZIP, mark-printed, job status strip polling, `release_mode` gate).
- [ ] 7.3 Release › ResultSheetBulk preview (paper width, scale control) + single ResultSheet page.
- [ ] 7.4 Playwright smoke: template create → validate → preview; print batch → job ready → download; online mode disables printing.

## 8. Seeds, HANDOFF, evidence

- [ ] 8.1 `ResultSheetTemplateSeeder` — active HTML template + SecureCAT DOCX seed asset; `config/seed.php` wiring.
- [ ] 8.2 HANDOFF.md — staff credentials, seed/reset commands, expected state, feature URLs.
- [ ] 8.3 Full suite + OpenSpec strict validation; master checkpoint + crosswalk update in this worktree.
