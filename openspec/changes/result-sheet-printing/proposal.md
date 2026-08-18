## Why

The connected admission-to-release journey needs real result sheets: legacy renders them synchronously in the request (DomPDF/Browsershot for HTML, LibreOffice for DOCX), which blocks the UI, and greenfield only has a stub `PrepareResultDocument` writing trivial HTML. This change delivers the full legacy release-printing feature set — template management with Word/ODT import, placeholder engine, single + bulk printing with copies, printed tracking, PDF/DOCX/ZIP downloads — rebuilt on the existing async `print_jobs` pipeline, capability authorization, sensitive storage, and audits.

## What Changes

- **Versioned result-sheet templates** (`result_sheet_templates`): HTML or DOCX/ODT modes, paper size, orientation, logical unit (full/crosswise dual-slot), watermark, single-active, private checksummed document storage, placeholder validation, live preview. Templates are Test-Administrator-managed under `guidance.assessment.catalog` (the catalog capability already reserved for result templates).
- **Placeholder engine** ported from legacy: applicant identity/reference, exam date/time/room, per-domain raw + converted rating, `scores_rows` table injection, overall percentile, course checkmarks, institution settings, personnel/signatories, `_2` dual-slot variants; plus an alias catalog mapping legacy names (e.g. `{{verbal_aptitude}}`) to current aptitude-area codes. Fragmented DOCX macro repair (`getRepairedTemplate`) is ported.
- **Result-sheet artifact in the print pipeline**: `ARTIFACT_RESULT_SHEET` jobs rendered by a template-backed `ResultSheetArtifactRenderer` (HTML → DomPDF; DOCX/ODT → fill + LibreOffice headless, inside the queue worker — never the request), sensitive-disk storage with checksums, status polling, capability-reauthorized downloads with download audit, retry from pinned inputs, duplicate dedupe, retention pruning.
- **Bulk printing**: one job per batch (≤200 applicants, copies 1–10), progress counters, per-item manifest with per-item states, ready-with-partial when some items fail, merged bulk PDF for stack printing, merged Word + ZIP downloads from the DOCX path.
- **Printed tracking**: `result_printed_at` on `result_releases` (per release version; a re-release starts unprinted); set idempotently when a result-sheet job for released records completes; manual mark/unmark toggle, capability-gated and audited (legacy parity).
- **Release seam**: `PrepareResultDocument` stub replaced with a template-backed document generation operation that produces a real private artifact before release can proceed.
- **UI**: legacy-shaped pages in the locked UI kit — Setup › Result Sheet Templates (list/create/edit with placeholder guide + analyzer + preview) and Release › Print Batch (selection, bulk print, copies, PDF/DOCX/ZIP, mark-printed, job status strip), single result sheet, bulk preview with paper/scale controls. `release_mode` online gate disables printing (F2F/Both only), matching legacy.

## Capabilities

### New Capabilities

- `result-sheet-printing`: versioned template management with Word/ODT import and validation; placeholder-driven rendering of released result sheets through the async print pipeline; single and bulk printing with progress, partial states, and retry; printed-status tracking; audited downloads and retention.

### Modified Capabilities

<!-- None: canonical specs under openspec/specs/ are not yet promoted; the existing release/print behavior is stubbed. -->

## Impact

- New table: `result_sheet_templates`; forward-fix column `result_printed_at` on `result_releases`.
- Reuses: `print_jobs` pipeline (`PrintJob`, `RenderPrintJob`, `PrintingController`), `SensitiveAssetStore`, `CapabilityAuthorizer`, `RecordAuditEvent`, database queue, scheduler.
- Adds: `phpoffice/phpword` (DOCX fill), `setasign/fpdi` (PDF merge fallback), LibreOffice headless in the app/queue images, `config/result-sheet.php`.
- Replaces: `PrepareResultDocument` stub body with a template-backed generation flow behind the same `ResultDocument` contract (`ReleaseResult` unchanged).
- No dependency on legacy runtime, role middleware, or the legacy `result_printed_at` pivot model.
