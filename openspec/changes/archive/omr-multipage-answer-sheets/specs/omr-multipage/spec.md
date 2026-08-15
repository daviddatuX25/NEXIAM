# omr-multipage Spec

## ADDED Requirements

### Requirement: Bundles declare pages

A native bundle SHALL be able to declare an ordered `pages` list, each page with its own field blocks and reference asset. A bundle without `pages` MUST behave as a single-page bundle. Activation MUST project one `omr_answer_sheet_pages` row per page with per-page dimensions and a per-page derived-manifest hash, and MUST assign globally contiguous item positions in page order.

#### Scenario: Two-page bundle activates
- **WHEN** a two-page bundle is activated
- **THEN** two page rows exist with page 1 positions preceding page 2 positions and contiguity enforced per area across both pages

#### Scenario: Single-page bundle unchanged
- **WHEN** a bundle without a pages list is loaded
- **THEN** its behavior is identical to today (one page, positions unchanged)

### Requirement: Capture requires every page before analysis

The capture surface SHALL require exactly N page shots (N = pair page count) before a logical sheet is enqueued for analysis. Uploading a page MUST be idempotent per (sheet set, page_no); a duplicate or missing page MUST produce a typed error and never start analysis.

#### Scenario: Auto two-page capture
- **WHEN** staff capture page 1 of 2 then page 2 of 2 in Auto mode
- **THEN** the logical sheet is enqueued only after page 2 and the gallery shows one sheet

#### Scenario: Missing page blocks analysis
- **WHEN** staff upload page 1 of a two-page sheet and no page 2 arrives
- **THEN** the set stays incomplete, no analysis runs, and the UI offers resume of page 2

#### Scenario: Duplicate page refused
- **WHEN** staff upload two photos both labeled page 1 for one sheet set
- **THEN** the second upload is refused with a typed duplicate-page error

### Requirement: Per-page detection and merge

Each page SHALL be detected as one signed worker request with that page's geometry, with its own processing run, retries, and aligned evidence. After every page validates, NEXIAM MUST merge page proposals into one sheet proposal with globally ordered items and per-area scores derived from the pinned booklet. The merge SHALL be its own guarded step: it persists atomically only when every page has succeeded and the logical sheet's version is still current; a stale or partial merge MUST be refused.

#### Scenario: Both pages succeed
- **WHEN** all pages of a sheet analyze successfully
- **THEN** the sheet becomes `needs_review` with one merged proposal covering every item across pages

#### Scenario: One page fails
- **WHEN** one page fails and others succeed
- **THEN** the sheet shows the failed page's typed diagnostic and retry re-analyzes only that page

#### Scenario: Page-level aligned evidence
- **WHEN** a page analyzes successfully
- **THEN** its encrypted aligned derivative is stored and shown on that page's review tab

#### Scenario: Merge is guarded and atomic
- **WHEN** a page result arrives after the set was already merged or the sheet version changed
- **THEN** the stale merge is refused and the sheet state is not corrupted

### Requirement: Per-page identity visible in review

The review panel SHALL show each page's detected applicant number side by side with the reconciled value, so staff can spot a page photographed from the wrong physical sheet before binding.

#### Scenario: Page numbers differ visibly
- **WHEN** page 1 proposes one number and page 2 another
- **THEN** both values are shown per page alongside the conflict issue

### Requirement: Pages are visually distinct

Multi-page printable sheets SHALL print a page header on every page ("PAGE X OF N") and section titles, so photographed pages cannot be mistaken for one another.

#### Scenario: Two-page printout
- **WHEN** the v3 kit renders
- **THEN** page 1 shows "PAGE 1 OF 2 — VERBAL" and page 2 shows "PAGE 2 OF 2 — QUANT" with matching outer margins on both pages


### Requirement: Identity reconciliation across pages

When the applicant-number grid appears on multiple pages, NEXIAM SHALL reconcile page proposals: identical proposals merge into one detected number; mismatches SHALL be surfaced as an identity issue. Staff binding remains mandatory and is never automated.

#### Scenario: Matching identity on both pages
- **WHEN** both pages propose the same applicant number
- **THEN** the sheet shows one detected applicant number without an identity issue

#### Scenario: Conflicting identity
- **WHEN** pages propose different applicant numbers
- **THEN** the sheet shows an identity conflict issue and requires manual binding

### Requirement: Multi-page review and confirm gating

The review panel SHALL show one tab per page with per-page evidence. Binding SHALL apply once to the logical sheet. Batch confirm MUST remain blocked until every page of every sheet is reviewed.

#### Scenario: Reviewing a two-page sheet
- **WHEN** staff open a two-page sheet
- **THEN** both pages are visible with their own aligned images and item lists under one binding

#### Scenario: Confirm blocked on unreviewed page
- **WHEN** any page of any sheet is not reviewed
- **THEN** confirm fails with a typed error naming the sheet and page
