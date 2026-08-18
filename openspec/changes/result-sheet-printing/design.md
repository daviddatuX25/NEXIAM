# Design: Result-Sheet Printing

## Context

Legacy prints result sheets synchronously in the request (DomPDF/Browsershot for HTML, LibreOffice for DOCX/ODT) with a 1,200-line `ResultSheetTemplateService`, a fat `ReleasePrintController`, and `result_printed_at` on a grading pivot. Greenfield has an async `print_jobs` pipeline (blank answer sheets) and a stub `PrepareResultDocument`. This change adds the full release-printing feature set on the existing pipeline with capability authorization, sensitive storage, audits, and per-item bulk states. Paper: long bond 216×330mm default, US Legal alternative; sheet geometry never scales.

## Goals / Non-Goals

**Goals:**
- One versioned template authority (HTML or DOCX/ODT upload) with analyzer, preview, single-active.
- Result sheets ride the existing pipeline: `print_jobs` → queued `RenderPrintJob` → renderer interface → sensitive disk → status polling → authorized download.
- Full legacy parity: single + bulk (≤200, copies 1–10), PDF/DOCX/ZIP, mark/unmark printed, crosswise dual-slot, watermark, `release_mode` gate.
- TDD: every slice lands red → green at a named seam (HTTP, action, renderer bytes).
- Replaces the `PrepareResultDocument` stub without changing `ReleaseResult`.

**Non-Goals:**
- No report exports (later cut on the same pipeline). No admission-slip printing. No OMR answer-sheet changes. No normalized/formula scoring basis (rejected). No legacy runtime dependency.

## Decisions

### 1. Template entity — `result_sheet_templates`

Columns: `name`, `mode` (`html`|`docx`), `paper_size` (`a4`|`legal`|`letter`), `orientation`, `logical_unit` (`full`|`half_a4`|`half_legal`|`half_letter`), `content` (HTML), `document_path` (private), `document_checksum`, `document_extension`, `watermark_text`, `is_active`, `rating_scale_id` (reserved, unused — ratings come from `converted_score`), `created_by_user_id`, timestamps. Single-active enforced in the save action transaction. Documents stored on private disk under `result-sheet-templates/{id}.{ext}` with `sha256` checksum.

### 2. Module layout (mirrors legacy responsibilities, split for seams)

```
app/ResultSheet/
  Actions/
    SaveResultSheetTemplate.php      # create + update + single-active + doc storage (audited)
    ActivateResultSheetTemplate.php
    DeactivateResultSheetTemplate.php
    RequestResultSheetPrint.php      # single + bulk job creation, dedupe, pins, audit
    MarkResultSheetsPrinted.php      # manual toggle (audited)
  Data/
    ResultSheetDataProvider.php      # attempt → applicant data array (scores from ScoreRecord)
    TemplateFieldAliasCatalog.php    # legacy placeholder → area code mapping
  Support/
    ResultSheetPlaceholderService.php  # buildReplacements, scores_rows, dual-slot, sample data
    ResultSheetTemplateAnalyzer.php    # validate-document (found/missing/extra)
    DocxMacroRepairer.php              # fragmented {{ }} run repair (legacy getRepairedTemplate)
    LibreOfficeConverter.php           # DOCX/ODT → PDF (headless, chunked, merge)
  Rendering/
    ResultSheetRenderer.php            # template → PDF bytes (HTML: DomPDF; DOCX: fill+LO)
    ResultSheetArtifactRenderer.php    # PrintArtifactRenderer registry entry (pipeline seam)
```

### 3. FormRequests (every write path names its request)

- `StoreResultSheetTemplateRequest` — name/mode/paper/orientation/logical_unit/content|document/watermark/is_active; `document` validated `mimes:docx,odt|max:5120`; content required for html; document required for docx.
- `UpdateResultSheetTemplateRequest` — same shape, sometimes.
- `RequestResultSheetPrintRequest` — `applicant_ids` (array, ≤200), `copies` (1–10), optional `release_mode` context.
- `MarkResultSheetsPrintedRequest` — `release_ids` (array), `printed` (bool).
- `AnalyzeResultSheetTemplateRequest` — upload-only (document + logical_unit).
- `PreviewResultSheetTemplateRequest` — mode/paper/orientation/logical_unit + content|document.

### 4. Routes and controllers (thin)

```
Setup (capability: guidance.assessment.catalog)
  GET    /setup/result-templates            ResultSheetTemplateController@index
  GET    /setup/result-templates/create     @create
  POST   /setup/result-templates            @store
  GET    /setup/result-templates/{t}/edit   @edit
  PUT    /setup/result-templates/{t}        @update
  POST   /setup/result-templates/{t}/activate   @activate
  POST   /setup/result-templates/{t}/deactivate @deactivate
  POST   /setup/result-templates/validate-document @validateDocument
  POST   /setup/result-templates/preview    @preview

Release (capability: guidance.results.release)
  GET    /release/print/batches/{gradingSession}   ReleasePrintController@index   (per-session batch)
  POST   /release/print                            @request (creates single/bulk job)
  POST   /release/print/mark-printed               @markPrinted
  GET    /release/print/batches/{gradingSession}/preview?ids=… @bulkPreview
  GET    /release/print/batches/{gradingSession}/export?ids=…&copies=…  @export (PDF/DOCX/ZIP via jobs)
```

Existing `printing.jobs.show` / `printing.jobs.download` reused for status + download. `PrepareResultDocument` route unchanged.

### 5. Result-sheet artifact in the pipeline

- `RenderPrintJob::renderPayload` gains a `result_sheet` branch dispatching to `ResultSheetArtifactRenderer` (registry pattern: artifact type → renderer).
- Pinned inputs: `result_release_id` + `release_version`, `document_checksum`, `template_version_id` + `document_checksum` (hash of active template identity).
- Rendering: `ResultSheetRenderer` builds replacements from `ResultSheetDataProvider` (frozen `converted_score`), fills the template (HTML string replace; DOCX via PhpWord `TemplateProcessor` after `DocxMacroRepairer`; ODT via zip content.xml replace), then:
  - HTML → `PrintRenderer` (DomPDF) with paper size/watermark/dual-slot CSS.
  - DOCX/ODT → `LibreOfficeConverter` (headless, chunked 40, pdfunite/FPDI merge) — only inside the worker.
- Storage: sensitive disk (`SensitiveAssetStore`) with checksum; job `ready` sets `result_printed_at` idempotently on pinned release records.
- Bulk: `progress_total` = items × copies basis (items), `per_item_manifest` (applicant id → path + checksum + status + safe_error), merged bulk PDF appended when >1.
- Download audit added to `PrintingController::download` (spec gap). Retention: `result-sheet:prune` scheduled command honoring `config('result-sheet.retention_days')`.

### 6. Release seam

`PrepareResultDocument` is rewritten to render the active template for the attempt via the same placeholder engine (HTML preview artifact), store privately with checksum, and only then mark `ResultDocument` ready; generation failures produce a typed failed state. `ReleaseResult` contract (STATUS_READY gate) unchanged.

### 7. UI (legacy shape, UI kit)

- `Setup/ResultSheetTemplates/Index.svelte` — management list: name/mode/paper/unit/watermark/status/actions (edit, activate/deactivate, delete confirm dialog).
- `Setup/ResultSheetTemplates/Create.svelte` + `Edit.svelte` — placeholder guide panel (copyable), mode toggle, form, analyzer panel (found/missing/extra), live preview (debounced server call), missing-required confirm gate.
- `Release/PrintBatch.svelte` — session card, select-all, bulk print, copies, PDF/Word merged/ZIP, mark/unmark printed, per-row print + printed badge, job status strip (queued → rendering → ready/partial/failed + retry) polling `printing.jobs.show`.
- `Release/ResultSheetBulk.svelte` — paper-width stacked preview + actions (print, PDF, DOCX merged/ZIP, mark all printed).
- `Release/ResultSheet.svelte` — single sheet preview + print/PDF/DOCX + mark printed.
- `release_mode` gate: online disables print actions (explanatory state).

## TDD seams (pre-agreed)

1. **HTTP seam** — Pest feature tests on routes: template CRUD/single-active/analyzer/preview; print job request; mark-printed; download auth/checksum/audit; release_mode gate.
2. **Action seam** — direct action tests: dedupe, pinning, stale-retry refusal, idempotent `result_printed_at`, document prepare.
3. **Renderer seam** — byte-level tests: HTML render returns non-empty PDF (`%PDF`); placeholders resolve from a fixture (legacy aliases + scores_rows + ratings from `converted_score`); dual-slot; macro repair round-trip.
4. **Bulk seam** — per-item manifest, partial-ready, progress counters.
5. **Browser seam** — Playwright smoke: template create → validate → preview; print batch → job ready → download.

## Risks / Trade-offs

- LibreOffice in images adds ~300MB; conversion runs only in workers, chunked, with per-job timeout; `isAvailable()` guard reports typed failure when missing.
- DOCX fill depends on PhpWord (`phpoffice/phpword`); repaired-template path avoids most Word quirks; ODT uses zip replace (legacy-proven).
- Encrypted `converted_score` requires decrypting within the worker — same trust boundary as release/portal serialization; never logged.
- Merged PDF for DOCX path relies on LibreOffice output order + pdfunite/FPDI merge; per-item PDFs remain the authoritative per-applicant artifact.

## Migration Plan

- Add `result_sheet_templates` migration; forward-fix `result_printed_at` on `result_releases`.
- Seed: `ResultSheetTemplateSeeder` creates an active HTML template (legacy-shaped sample) + imports the SecureCAT `template_main_icat_test1.docx` as a DOCX template (seed asset copied into `database/seed-assets/result-sheets/`).
- Rollback: drop table/column; routes 404; no data loss.

## Open Questions

- None blocking. (Report exports, admission slips, result-sheet template editor beyond HTML/DOCX are later cuts on the same pipeline.)
