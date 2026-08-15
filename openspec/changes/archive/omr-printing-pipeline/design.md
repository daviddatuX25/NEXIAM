# Design: Printing Pipeline Module

## Context

Legacy printing (admission slips, result sheets, reports) is synchronous DomPDF/DOCX merge in the request — proven output, blocking UX, and it bakes template-merge machinery we do not need yet. Greenfield has placeholder release documents and no printing. The founder wants printing as its own module: **reusable, scalable, queued**, serving result sheets (release/consultation) and blank answer sheets, with paper = long bond 8.5×13 (US Legal alternative).

## Goals / Non-Goals

**Goals:**

- One module renders any printable artifact through one queue pipeline; adding an artifact type never rewrites the pipeline.
- Blank sheets derive 1:1 from `template.json` geometry (no hand-authored visuals).
- Result sheets ship fixed-layout first with printed tracking; bulk works with progress.
- Nothing renders in a request; UI polls status and downloads ready files.

**Non-Goals:**

- No booklet printing this cut. No report exports this cut (reuse later). No result-sheet template editor yet (fixed layout first). No admission-slip printing this cut (separate future reuse).

## Decisions

1. **Pipeline = job + `print_jobs` row + private disk + status.** `RenderPrintJob` claims the row, renders through a `PrintRenderer` interface, stores the artifact, marks ready/failed with safe codes. Bulk = one job with a progress counter (per-item rows optional later). Download routes re-authorize and stream `private, no-store`. Rationale: legacy's synchronous rendering is the known pain; the queue (database driver) already exists.
2. **Derived renderer is the only visual source.** `BundleRenderer` walks the bundle's pages/blocks and draws circles, letters (centered by construction), row numbers, page headers, and margins; paper size maps pageDimensions → physical size (long bond default). Hand-authored SVGs are retired; the physical test kit is renderer output. Rationale: every misalignment bug so far came from a second hand-maintained visual source.
3. **Renderer behind an interface.** DomPDF first (legacy-proven, no new services); headless-Chromium behind the same interface as the quality/scale upgrade path. Pipeline, storage, and UI never change when the renderer swaps.
4. **Blank sheets = one PDF page per sheet page**, queued like everything else, non-sensitive private disk. Result sheets = sensitive disk with HMAC (applicant scores).
5. **Result sheet fixed layout.** Identity, per-area table (raw + converted + rating), institution header from existing config, counselor signature line, watermark; `result_printed_at` lives on `ResultRelease` (per release version) so a re-release after correction starts unprinted; a capability-gated manual mark/unmark toggle mirrors legacy `PrintBatchService` for physical-print reality; every job creation/transition/download is audited with actor + checksum.
6. **Paper config — never scale geometry.** `print.paper` = `long` (216×330mm) | `legal` (216×356mm); bundle pageDimensions define the sheet. The renderer prints 1:1 when bundle and paper match; on mismatch it refuses with a typed error or letterboxes with cut marks — bubble geometry is NEVER scaled (single-geometry authority). Blank output includes a calibration strip + "print at 100%" instructions.
7. **Reuse contract.** Any future artifact (booklets, reports, slips) = artifact type + payload provider + renderer; the pipeline, status UI, and downloads are untouched. Bulk jobs persist a per-item manifest (id → path + checksum) and per-item statuses; a merged bulk PDF is ALSO produced for stack printing (legacy parity) when the artifact type declares it. Failed jobs retry from pinned inputs (bundle hash / release version + checksum captured at creation); duplicate requests return the existing job. Ready artifacts expire via a scheduled retention pass.

## Risks / Trade-offs

- [Renderer bugs visible only after print] → Mitigation: renderer unit tests assert letter centers == bubble centers, page counts, margins; vision-verified kit output; Chromium print-to-PDF for pixel-true output.
- [Bulk renders swamp the single queue worker] → Mitigation: one bulk job with progress; `--timeout` per job tuned; failed jobs typed, retryable, never silent.
- [Printed sheet drifts from engine geometry] → Mitigation: renderer reads the same activated bundle the batch pins; versions immutable.
- [DomPDF text/quality limits] → Mitigation: fixed layout; Chromium swap behind the interface.
- [Orphan files] → Mitigation: per-job directory, cleanup on failure, retention governs ready artifacts.
- [Re-print races / duplicate jobs] → Mitigation: unique job per (artifact, scope, checksum) where applicable; idempotent `result_printed_at` upsert.

## Merge order with omr-configuration (planner finding resolved)

Single agreed order: printing §1 (pipeline) → printing §2 (renderer + v4 long-bond bundle) → config §1 (intake/templates) → config §2–§4 (builder/booklets/pairs) → printing §3–§4 (blank-sheet UI, result sheets) → config §5 (settings wiring). `BundleRenderer` accepts arbitrary draft JSON synchronously (builder previews); only finished artifacts flow through print_jobs.

## Deferred legacy behaviors (recorded decisions)

- DOCX template mode + per-applicant DOCX/zip + LibreOffice conversion: DEFERRED (fixed-layout PDF first); founder may re-open after first deployment.
- Half/full logical units (two-up printing): DEFERRED — re-open if paper-cost feedback demands it.
- Report exports (xlsx/pdf): later cut on the same pipeline.
- Watermark text: INCLUDED in the fixed layout.

## Migration Plan

- Add `print_jobs` migration + `result_printed_at` forward-fix on the release pivot.
- v4 long-bond bundle ships as new files; seeder activates the v4 pair idempotently.
- Rollback: drop the table/column; routes degrade to 404; no data loss.

## Open Questions

- None blocking. (Result-sheet template editor and report exports are later cuts on the same pipeline.)
