# Proposal: Printing Pipeline Module

## Why

The grading system needs two printable artifact families: **blank answer sheets** (from the native template geometry, for examinees and for the physical OMR test kit) and **result sheets** (the legacy release/consultation feature — per-applicant results with scores/ratings/signatures, single + bulk). Legacy renders PDFs synchronously in the request (DomPDF + DOCX merge) — proven conceptually, but slow and request-blocking. The greenfield upgrade: **one reusable, async printing module** — queue render jobs, private storage, status polling, authorized download — so rendering never blocks the frontend and every future artifact (reports, booklets if ever, slips) reuses it. Institutional paper: **long bond 8.5 × 13 in (216 × 330 mm)**, US Legal as alternative.

## What Changes

- **`print_jobs` pipeline**: artifact type + scope, status `queued → rendering → ready | failed`, file path + checksum, safe error codes, progress counters for bulk; queued rendering only; downloads re-authorize scope and stream `private, no-store`.
- **Derived bundle renderer**: draws any bundle version from `template.json` (pages, blocks, origins, gaps, bubble sizes) at render time — A–D/0–9 letters centered by construction, row numbers, page headers, margins and paper size as config. Replaces hand-authored reference SVGs as authored artifacts; powers blank-sheet printing, builder/preview thumbnails, and the physical test kit.
- **Blank answer-sheet printing**: per active template version, one PDF page per sheet page, long-bond default. Serves the physical OMR acceptance testing.
- **Result sheets**: fixed layout first (identity, per-area raw + converted scores, descriptive ratings where configured, institution header, counselor signature block, watermark placeholder), single + bulk per grading session, `result_printed_at` tracking on release records (legacy parity).
- **Long-bond v4 two-page bundle** ships as the standard demo pair (≈6mm bubbles, safe margins).
- Booklet printing stays excluded (physical copies); reports reuse the pipeline later (not this cut).

## Capabilities

### New Capabilities

- `print-pipeline`: async print-job lifecycle, private artifact storage, authorized downloads, derived bundle renderer, blank answer-sheet rendering, result-sheet rendering (fixed layout), bulk generation with progress, printed-status tracking, long-bond paper configuration.

### Modified Capabilities

<!-- None: canonical specs under openspec/specs/ are not yet promoted; existing behavior is unchanged. -->

## Impact

- New tables: `print_jobs` (+ `result_printed_at` on the release pivot).
- Reuses: `NativeBundle`, `NativeManifestDeriver`, `SensitiveAssetStore` (result sheets), database queue, scheduler, capability authorizer (OMR + release capabilities).
- Depends on: configuration change for preview integration (renderer shared); no dependency the other way.
- DomPDF first behind a renderer interface; headless-Chromium swap path reserved.
