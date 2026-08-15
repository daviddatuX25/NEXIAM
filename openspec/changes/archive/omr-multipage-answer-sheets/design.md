# Design: Multi-page Answer Sheets

## Context

Single-page bundles (v1 long sheet, v2 A4) work end to end: one capture → one signed worker request → one proposal. The founder now requires multi-page sheets (two-page A4: one section per page, comfortable bubbles, safe margins). Constraint set: the worker stays single-sheet (OMRChecker is inherently one template per image); the founder locks one-pair-per-batch; identity is bubbled digits only with mandatory staff binding; confirm is atomic and gated.

## Goals / Non-Goals

**Goals:**

- A bundle may declare N pages; activation projects per-page geometry; capture requires exactly N page shots before a logical sheet is enqueued.
- Per-page detection with page-level runs, retries, and aligned evidence; NEXIAM merges into one proposal with globally contiguous item positions.
- Identity reconciliation across pages; page-tabbed review; confirm gating across pages.
- v3 two-page A4 bundle as the reference implementation (≥5mm bubbles, safe margins).

**Non-Goals:**

- No double-sided single-image processing (both sides in one photo) — out of scope.
- No free-form name OCR (unchanged founder lock).
- No per-page applicant binding; binding stays per logical sheet.
- No changes to worker protocol, answer-key authority, or qualification gates.

## Decisions

1. **Logical sheet = one applicant submission; pages = physical captures of it.** `omr_capture_sheets` keeps its meaning (one binding, one proposal, one score set); new `omr_capture_sheet_pages` rows hold per-page assets/status/runs. Alternative (N sheets per applicant) rejected: breaks the duplicate-binding index, confirm semantics, and roster UX.
2. **Bundle pages as first-class geometry.** Bundle manifest gains `"pages": [ {page_no, template, reference} ]`; single-page bundles omit it (page count 1). Each page's field blocks feed a per-page derived manifest; item positions are assigned globally in page order, so the booklet's 200 questions stay contiguous across pages. Alternative (N separate bundles) rejected: pair/activation complexity for no gain.
3. **Projection:** `omr_answer_sheet_pages` (template_version_id, page_no, width, height, page_manifest_json/hash) created during activation; `OmrPinnedSnapshot` gains per-page snapshots. Single-page rows are defaulted so v1/v2 remain untouched.
4. **Capture stepper.** Batch pair page count drives the UI: shutter labeled "Page 1 of N". The upload action becomes page-aware: first page creates the logical sheet (status `pending_write` for the set), later pages attach by (batch, client_capture_id, page_no); idempotency key = set id + page_no. Auto enqueues analysis only when the last page lands; Manual opens review then. Wrong page order or duplicates → typed errors.
5. **Per-page runs.** Each page has its own `omr_processing_runs` row (page-level claim, retry, backoff, sweeper — existing machinery unchanged). The logical sheet stays `processing` until all pages succeed; any page failure → sheet `failed_terminal`-like state with per-page diagnostics; retry targets the failed page only.
6. **Merge in NEXIAM.** After every page validates, the sheet proposal is merged: items ordered by global position; per-area scores derived from the pinned booklet across pages; `sheet_confidence` = min across pages; issues concatenated with page attribution. The answer key never crosses the boundary (per-page requests carry geometry only).
7. **Identity reconciliation.** The applicant grid prints on every page; each page returns its proposal; NEXIAM keeps identical digits, flags mismatches as an identity issue, and never auto-binds (staff binding unchanged).
8. **Review & confirm.** Review panel renders page tabs (per-page aligned image + items); binding once per sheet; confirm remains blocked until every page of every sheet is `reviewed`. Duplicate-binding unique index unchanged (logical sheet level).
9. **v3 bundle.** Two-page A4: page 1 = identity + name box + VERBAL (100 rows, 4 columns of 25 with numbering), page 2 = identity + QUANT (same layout); bubbles 5mm; margins ≥6mm; one printable PDF per page generated from the same page geometry.

## Risks / Trade-offs

- [Partial page set stranded (app killed between page 1 and 2)] → Mitigation: page rows are idempotent; a sweep (existing stale-run machinery) plus UI "resume page 2" state; incomplete sets never reach analysis.
- [Merge ordering bugs across pages] → Mitigation: activation enforces global contiguity; merge test matrix with two-page fixtures; positions are assigned at activation, not at capture.
- [Identity mismatch across pages confuses staff] → Mitigation: UI shows per-page proposals + a single combined field with conflict badge; binding always manual.
- [Page 1 and page 2 photographed from different sheets] → Mitigation: applicant-number reconciliation flags the mismatch as an identity issue; staff must confirm binding; worst case a typed review issue, never silent scoring.
- [Single-page regressions] → Mitigation: page-aware code paths default page_no=1; v1/v2 tests must stay green untouched.

## Migration Plan

- Forward-fix migrations: `omr_answer_sheet_pages`, `omr_capture_sheet_pages` (+ page_no on runs). Backfill: existing template versions get one page row derived from current data; existing sheets get one page row pointing at current assets/runs.
- v3 bundle ships as new files (no change to v1/v2); seeder activates v3 pair idempotently.
- Rollback: page tables can be dropped; code paths degrade to page_count=1 behavior.

## Open Questions

- Whether identity repeats on every page or only page 1 in the final institution design (design supports both; v3 repeats it).
- Whether bulk capture of page stacks (batch scan all page 1s, then all page 2s) is a later convenience mode — out of scope now.

## Paper decision (planner finding resolved)

- Shipped v2/v3 bundles use A4-ratio geometry (2000×2828) — recorded as **interim synthetic geometry**, superseded by the **v4 long-bond bundle** (216×330mm ≈ [2060, 3144]) authored in `omr-printing-pipeline` §2. v2/v3 remain active as historical demos but MUST NOT be pinned by the §4 fixture pack or §7 qualifications; the long-bond v4 pair is the standard test/production pair.
