# Tasks: Printing Pipeline Module

> **Execution rule:** complete sections in order. The pipeline ships before any artifact consumes it; the renderer ships before blank sheets; result sheets last. Nothing renders synchronously.

## 1. Print pipeline foundation

- [x] 1.1 Add `print_jobs` migration (artifact type, scope refs, status queued/rendering/ready/failed, requested_by actor, file path, checksum, safe error, progress counters, per-item manifest JSON, pinned-input identity JSON, timestamps) with indexes on (status, created_at)
- [x] 1.2 Add `result_printed_at` on `ResultRelease` (per release version; re-release resets it)
- [x] 1.3 Implement `RenderPrintJob` (claims the job, renders through the `PrintRenderer` interface, stores the artifact(s), marks ready/failed/partial with safe codes; per-job directory + cleanup on failure; retry from pinned inputs with stale-input refusal)
- [x] 1.4 Implement `PrintRenderer` interface + DomPDF adapter, container-bound so a headless-Chromium adapter can swap in later
- [x] 1.5 Add download route: re-authorize capability + scope, stream `private, no-store`, refuse non-ready jobs with typed errors; audit every download
- [x] 1.6 Duplicate-job detection: same artifact + scope with an open job returns the existing job id
- [ ] 1.7 Retention pass: scheduled deletion of ready artifacts past their window (configurable) with cleanup audit
- [ ] 1.8 Feature tests: job lifecycle, failure safety, download authorization + audit, non-ready refusal, bulk progress + partial states, retry + stale-input refusal, duplicate detection, orphan cleanup, retention

## 2. Derived bundle renderer

- [x] 2.1 Author the **v4 two-page long-bond bundle** (216×330mm space ≈ [2060, 3144]; 4 columns × 25 rows per page; bubbles ≈6mm; identity grid + name box + `PAGE X OF 2` headers; safe 8mm margins) and activate via the seeder
- [x] 2.2 Implement `BundleRenderer`: draws any bundle version OR arbitrary draft JSON from `template.json` (circles, A–D/0–9 letters centered per column, row numbers, page titles, margins, paper-size config) emitting PDF (one page per sheet page) + preview PNG; NEVER scales geometry — mismatch refuses or letterboxes; blank output carries a calibration strip + print-at-100% instructions
- [ ] 2.3 Regenerate the physical test kit from the renderer (long-bond paper) and stop treating hand-authored SVGs as the render path
- [x] 2.4 Tests: letter centers equal bubble centers; page count equals bundle pages; long-bond mapping 1:1 with nothing clipped; deterministic output

## 3. Blank answer-sheet printing

- [x] 3.1 Action `RenderBlankOmrSheets` (capability-gated): queues a print job for an active template version via the renderer
- [ ] 3.2 Blank-sheet download surface on the template show page (job status + download)
- [x] 3.3 Tests: no-active-version refusal, geometry containment (every manifest bubble present in render), one PDF page per sheet page

## 4. Result sheets

- [ ] 4.1 Fixed-layout result sheet renderer (identity, per-area raw + converted, ratings where configured, institution header, counselor signature block, watermark) inspired by legacy `ReleasePrintController`/`ResultSheetTemplate`
- [ ] 4.2 Action `RenderResultSheet` (single applicant) queuing a print job; sensitive disk + HMAC
- [ ] 4.3 Bulk action `RenderResultSheetsBulk` (per grading session): per-item manifest + statuses, merged bulk PDF for stack printing, progress counters
- [ ] 4.4 On `ready` jobs for released records, set `result_printed_at` on the release version idempotently; add the manual mark/unmark printed toggle (legacy parity, audited); surface in the release UI
- [ ] 4.5 Tests: content coverage + watermark, sensitive storage + HMAC, bulk progress + partial failure, printed-status recording + manual toggle, release capability gating

## 5. Verification & handoff

- [ ] 5.1 Run focused print-pipeline suites in Compose and record counts
- [ ] 5.2 Keep the full suite green (283 feature + 10 unit baseline)
- [ ] 5.3 Chromium walkthrough: blank-sheet request → poll → download → print-ready PDF; result sheet single + bulk
- [ ] 5.4 Update `HANDOFF.md` with commands, URLs, and observed evidence
- [ ] 5.5 Update master/project-context checkpoints with observed evidence only
- [ ] 5.6 Run `openspec validate omr-printing-pipeline --type change --strict --no-interactive`
