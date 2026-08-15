# Proposal: Multi-page Answer Sheets

## Why

The printable A4 sheet (bundle v2) fits 200 questions on one dense page (~3mm bubbles). Real answer sheets for 200-item exams are multi-page: one page per aptitude section with comfortable 5–6mm bubbles, readable margins, and identity repeated on every page. Staff capturing such a sheet must photograph N pages as one logical submission ("Capture 1 of 2"), and NEXIAM must merge the per-page detections into one proposal, one binding, one set of scores. The current model assumes one capture = one sheet and cannot express this.

## What Changes

- **Native bundle gains pages**: a bundle may declare a `pages` list of page templates (each with its own field blocks and reference asset). Single-page bundles (v1, v2) remain valid with an implicit page count of 1.
- **Activation projects pages**: template versions gain page rows (`omr_answer_sheet_pages`) with per-page dimensions, geometry, and manifest hash; booklet questions map across pages with **globally contiguous item positions**.
- **Capture becomes a page stepper**: the batch's pair page count drives the UI — "Page 1 of N" → capture/shutter N times. Auto mode enqueues the logical sheet only after the last page; Manual proceeds to review after the last page. Missing/duplicate/wrong-order pages are typed errors, never silent.
- **Per-page detection, one logical sheet**: each page is one signed worker request with that page's sub-template; each page has its own processing run; the sheet's proposal merges validated page results. Page-level retry, page-level aligned evidence.
- **Identity reconciliation**: the applicant-number grid is printed on every page; NEXIAM reconciles proposals across pages (identical → strong; mismatch → identity issue, manual binding unchanged and mandatory).
- **Review & confirm**: the review panel shows page tabs with per-page evidence; binding is once per logical sheet; batch confirm stays blocked until every page of every sheet is reviewed.
- **v3 bundle ships as the reference implementation**: a two-page A4 sheet — page 1 VERBAL (100 items) + identity + name box, page 2 QUANT (100 items) + identity — with ≥5mm bubbles and safe print margins.

## Capabilities

### New Capabilities

- `omr-multipage`: multi-page native bundles, page projection, page-stepper capture, per-page detection and merge, identity reconciliation across pages, page-level review/retry, and multi-page confirm gating.

### Modified Capabilities

<!-- None: canonical specs under openspec/specs/ are not yet promoted; single-page behavior is unchanged and covered by the existing nexi-omr change spec. -->

## Impact

- New tables: `omr_answer_sheet_pages`, `omr_capture_sheet_pages` (forward-fix migrations; single-page rows are derived/defaulted so existing data stays valid).
- `NativeBundle`, `NativeManifestDeriver`, `ActivateOmrConfiguration`, `OmrPinnedSnapshot` learn page awareness (single-page bundles unchanged in behavior).
- `UploadOmrCaptureSheet` → page-aware coordinator (page_no, sheet-set idempotency); `RetryOmrCaptureSheet` retries a page; capture UI gains the stepper.
- Worker protocol unchanged: one signed request per page.
- `RunOmrSheetAnalysis` unchanged per run; merge happens in NEXIAM after page validation.
