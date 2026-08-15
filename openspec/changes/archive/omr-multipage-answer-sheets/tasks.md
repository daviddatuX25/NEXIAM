# Tasks: Multi-page Answer Sheets

> **Execution rule:** complete sections in order. Single-page behavior (v1/v2) must stay green throughout; every new path defaults page_no=1.

## 1. Bundle pages foundation

- [x] 1.1 Extend `NativeBundle` to parse an optional `pages` list (page_no, page template, page reference asset); validate page order, per-page field blocks, and hashes; single-page bundles unchanged
- [x] 1.2 Author the v3 two-page A4 bundle (page 1: identity + name box + VERBAL 100 in 4 numbered columns; page 2: identity + QUANT 100; ≥5mm bubbles, ≥6mm print margins)
- [x] 1.3 Extend `NativeManifestDeriver` to emit per-page manifests with globally contiguous positions and a combined manifest hash
- [x] 1.4 Add `omr_answer_sheet_pages` migration (template_version_id, page_no, width, height, page_manifest_json, page_manifest_hash) with a backfill for existing versions (one page row)
- [x] 1.5 Unit tests: v3 loads, per-page manifests derive deterministically, positions contiguous across pages, single-page bundles produce one page row

## 2. Capture page stepper

- [x] 2.1 Add `omr_capture_sheet_pages` migration (sheet_id, page_no, asset_path/hmac, original_mime/bytes, aligned columns, status) and page_no on processing runs; backfill existing sheets/runs as page 1
- [x] 2.2 Make `UploadOmrCaptureSheet` page-aware: first page creates the logical sheet, later pages attach by (batch, client_capture_id, page_no) in any arrival order; refuse duplicates; dispatch analysis only when the set is complete
- [x] 2.3 Capture UI stepper: show "Page X of N" from the pair's page count; Auto shutter per page; Manual proceeds to review only after the last page; uploads MUST send page_no and reuse one client_capture_id per set
- [x] 2.4 Incomplete-set gallery state: "page N missing" badge, resume at the next missing page, and replace-page-photo for not-yet-enqueued sets (audited) — REQUIRES a new replace-page endpoint (UploadOmrCaptureSheet currently hard-refuses duplicate pages)
- [x] 2.5 Tests: two-page Auto enqueues only after page 2; duplicate page refused; missing page stays resumable; out-of-order upload accepted; audited page replacement; resume-after-refresh

## 2.6 Controller props contract for page-aware UI

- [x] 2.6.1 Extend `GradingController::omr()` props: each sheet gains `expected_page_count` and `pages[] = {page_no, status, image_url (page route), aligned flag, detected_applicant_number (from page proposal_json), item positions covered}`
- [x] 2.6.2 Controller test asserting multipage sheets expose per-page data + per-page image URLs

## 3. Per-page detection and merge

- [x] 3.1 Per-page `OmrPinnedSnapshot` and per-page signed worker requests; page-level runs keep the existing claim/retry/sweeper machinery
- [x] 3.2 Merge validated page results into one sheet proposal as a guarded atomic step (global item order, min sheet confidence, issue attribution with page_no column, per-area scores from the pinned booklet; stale/partial merges refused)
- [x] 3.3 Identity reconciliation UI: expose per-page detected numbers in the omr() props and render them side by side in review; persist the conflicting per-page numbers in the identity-conflict issue evidence_json (audit must name what conflicted)
- [x] 3.4 `RetryOmrCaptureSheet` targets one failed page; sweeper handles per-page stale claims
- [x] 3.5 Tests: both-pages success, one-page failure + page retry, merge order correctness, identity match/conflict, forged page output rejected

## 4. Review and confirm

- [x] 4.1 Review panel page tabs with per-page aligned images, per-page detected numbers, and item lists; one binding per logical sheet. DATA PATH: either stamp page_no onto merged proposal items (merge version bump + test) or source tab items from each page row's proposal_json — state the choice in the implementation
- [x] 4.2 Confirm gating: block until every page of every sheet is succeeded/reviewed; structured error names sheet id + offending page_no list (errors.omr_confirmation) rendered with sheet and page labels. Requires controller props (see new task 2.6)
- [x] 4.3 Update `omr:recover-stale-runs` and hardening guards for page-level runs: on recovery reset the page row's run id + status to queued, re-run completeness (succeeded sets trigger maybeMerge), terminal page failure marks the page failed_terminal and the sheet reviewable/blocked per the failure model; test a lost job on page 2 of a two-page sheet
- [x] 4.4 Tests: multi-page review renders both pages, confirm blocks on unreviewed pages, duplicate-binding index still per logical sheet

## 5. Physical test kit v3

- [x] 5.1 Generate printable per-page PDFs from the **v4 long-bond bundle** via the shared BundleRenderer (omr-printing-pipeline) — paper 216×330mm, safe margins, "PAGE X OF N" headers — into `omr-test-kit/`
- [x] 5.2 Update kit README with the long-bond two-page shade-and-scan script (identity on both pages, VERBAL on page 1, QUANT on page 2) and expected merged scores
- [ ] 5.3 Real-engine smoke: two-page long-bond fixture set through the v4 pair (both pages succeed, merged 200-item proposal, 0 issues)

- [x] 6.1 Run focused multipage suites; record actual feature/unit counts at run time; no regressions vs the baseline recorded in HANDOFF.md
- [x] 6.2 Chromium walkthrough: two-page Auto capture → merged review → bind → confirm (GATED on 2.3/2.4/4.1 being visible in the browser first)
- [ ] 6.3 Update `HANDOFF.md` (delete stale 'multi-page out of scope' lines; complete the truncated multipage bullet), master checkpoint, and this change's evidence with observed results
- [ ] 6.4 Run `openspec validate omr-multipage-answer-sheets --type change --strict --no-interactive`
