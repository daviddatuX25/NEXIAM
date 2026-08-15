# omr-configuration-usability Spec

## ADDED Requirements

### Requirement: Navigation spine on every OMR configuration page

Every OMR configuration page SHALL render a breadcrumb trail (Setup → OMR Configuration → current page) via the shared `StaffLayout` breadcrumb prop. The OMR Configuration index SHALL present the four artifacts (templates, booklets, pairs, print queue) as anchored sections with per-section counts, each section's primary action visible in its header, and cross-links between related artifacts (template ↔ booklet ↔ pair).

#### Scenario: Breadcrumbs visible
- **WHEN** staff open any OMR configuration page
- **THEN** a breadcrumb trail shows Setup, OMR Configuration, and the current page, with working links for every ancestor

#### Scenario: Journey hub
- **WHEN** staff open the OMR Configuration index
- **THEN** all four sections are reachable from in-page anchors, each shows its count and its primary action, and pair rows link to their template and booklet

### Requirement: Sheet preview equals print

Staff SHALL preview any template version as an inline PDF rendered by the same `BundleRenderer` + DomPDF path that produces printed blank sheets. The builder SHALL offer a full preview of an unsaved draft without persisting anything. Preview responses MUST be capability-gated, `Cache-Control: private, no-store`, and must never create jobs or audit rows.

#### Scenario: Preview a version
- **WHEN** staff open the preview of a template version
- **THEN** the response is an inline PDF showing the sheet exactly as it prints, including print overlays

#### Scenario: Preview an unsaved draft
- **WHEN** staff request a full preview of the builder's current draft
- **THEN** the PDF renders the draft's pages and overlay without saving a version

#### Scenario: Preview failure is typed
- **WHEN** a preview render fails
- **THEN** staff see a typed error message, never a blank frame

### Requirement: Print overlays are render-only

Template versions SHALL carry an optional `print_overlay_json` (title, header lines, footer lines, name-box label, per-page notes). The bundle renderer MUST render overlays when present and fall back to its current defaults when null. Overlays MUST NOT be part of the native bundle, MUST NOT be sent to the engine, and MUST NOT affect geometry, labels, or hashes.

#### Scenario: Overlay renders on print and preview
- **WHEN** a version has an overlay and staff print or preview it
- **THEN** the title, header/footer lines, and name-box label appear in both outputs identically

#### Scenario: No overlay means defaults
- **WHEN** a version has no overlay
- **THEN** the sheet renders exactly as it does today

#### Scenario: Engine seam unchanged
- **WHEN** analysis runs for a sheet captured on a template with an overlay
- **THEN** the worker request contains exactly the native bundle as before (overlay absent)

### Requirement: Booklet editing does not retype work

Staff SHALL edit a draft booklet on a dedicated Create page that pre-populates from the draft, copy an active booklet version into a new draft with one action (audited), and preview the answer key per version (positions grouped by area, A–D letters, distribution counts).

#### Scenario: Editing a draft pre-populates
- **WHEN** staff open the booklet editor for an existing draft
- **THEN** code, name, and every question row are pre-filled from the draft

#### Scenario: New draft from active version
- **WHEN** staff copy an active booklet version to a new draft
- **THEN** the new draft contains the same questions, the active version is untouched, and the action is audited

#### Scenario: Key preview
- **WHEN** staff open a booklet version's key preview
- **THEN** positions render grouped by area with each position's correct option letter and per-area totals

### Requirement: Bulk answer-key entry

Staff SHALL paste answer keys in bulk (`position area_code option` rows, whitespace/comma separated, `#` comments). The parser MUST validate positions and A–D options per line and report failures with line numbers; the existing save validation remains the final authority.

#### Scenario: Valid bulk paste
- **WHEN** staff paste valid rows and apply them
- **THEN** the question table fills with the pasted rows in order

#### Scenario: Invalid rows named by line
- **WHEN** a pasted row has an out-of-range position or invalid option
- **THEN** the error names the line number and the row, and no rows are applied

### Requirement: Builder loads drafts and authors overlays

The builder SHALL load an existing draft version for editing (activated versions refuse), expose a per-page meta panel (title, header lines, footer lines, name-box label, page note), and save the overlay alongside the authored template as `print_overlay_json` on the draft version. The builder's instant preview SHALL show option letters, question numbers, identity digit labels, the page border, and header text.

#### Scenario: Load draft into builder
- **WHEN** staff open the builder for a draft version
- **THEN** pages, blocks, origins, gaps, and overlay fields are pre-filled

#### Scenario: Activated versions refuse editing
- **WHEN** staff attempt to open an activated version in the builder
- **THEN** the builder refuses with a typed message and offers the show page

#### Scenario: Overlay saved with draft
- **WHEN** staff save a builder draft with overlay fields set
- **THEN** the draft version row stores the overlay and the preview reflects it

### Requirement: Retirement shows its consequences

Retiring a template version, booklet version, or pair SHALL present an inline confirmation panel listing the artifact and every pair that will retire with it. The backend action (cascade + audit) is unchanged.

#### Scenario: Cascade visible before confirming
- **WHEN** staff click Retire on an active template version with active pairs
- **THEN** the panel lists the version and its active pairs before the final Retire click

#### Scenario: Pair retirement lists itself
- **WHEN** staff click Retire on a pair
- **THEN** the panel lists only that pair

### Requirement: Optional bubbled birthdate identity field (founder lock extension)

Templates MAY include an optional birthdate grid per page — an engine-native `QTYPE_INT` grid with six digit positions (`BIRTHDATE_1..6`), identical mechanics to the applicant-number grid, interpreted by NEXIAM as MMDDYYYY. The system SHALL project the region into `identity_regions_json`, reconcile per-page birthdate detections like the applicant number, record conflicts in identity-conflict issue evidence naming the differing values, and show "Applicant No." and "Birthdate" lines on the review examinee card with MMDDYYYY sanity validation (month 1–12, day 1–31, year 1900–current). Birthdate is identity, never a scored booklet item.

#### Scenario: Sheet with birthdate grid
- **WHEN** a template has the birthdate grid enabled and staff capture a sheet
- **THEN** detection reports six digits, review shows the parsed date, and invalid dates surface as review issues

#### Scenario: Conflict evidence
- **WHEN** two pages detect different birthdates
- **THEN** the identity-conflict issue evidence names both values per page

#### Scenario: No key impact
- **WHEN** a template has a birthdate grid
- **THEN** booklet questions and the answer key are unaffected

## MODIFIED Requirements

<!-- None: canonical specs under openspec/specs/ are not yet promoted; the in-flight omr-configuration change remains authoritative for the state machine. -->
