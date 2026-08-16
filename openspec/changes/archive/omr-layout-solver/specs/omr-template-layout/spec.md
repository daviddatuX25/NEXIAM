# omr-template-layout Spec

## ADDED Requirements

### Requirement: Server-owned semantic layout solver

The system SHALL own sheet geometry server-side through a pure, deterministic solver: a semantic blueprint (paper, column count, identity options, ordered question sections with ranges) is normalized, validated, and expanded into canonical OMRChecker `fieldBlocks` with integer pixel geometry. The same blueprint SHALL always produce byte-identical template JSON and the same hash. The browser MUST NOT compute placement, bounds, or canonical fieldBlocks; it renders only server-returned positions.

#### Scenario: Deterministic solve
- **WHEN** the solver receives the same blueprint twice
- **THEN** both runs return byte-identical canonical template JSON and identical hashes

#### Scenario: Browser never solves
- **WHEN** a draft is previewed or saved
- **THEN** geometry, placement, and canonical fieldBlocks are produced by the server solver, and the client submits only the blueprint plus overlay

### Requirement: Multi-column flow with breaks

The solver SHALL support 2, 3, and 4 column layouts. Sections expand into atomic question rows placed top-to-bottom in the first free column; a row that does not fit the current column SHALL continue in the next column, and past the last column SHALL continue on the next page. A question row MUST never be split across columns or pages. Explicit `next_column` / `next_page` breaks SHALL be monotonic: they never move already placed content, and a visible unused gap is allowed.

#### Scenario: Overflow continues to the next column
- **WHEN** a section's rows fill the current column
- **THEN** the next row starts the following column on the same page

#### Scenario: Overflow continues to the next page
- **WHEN** rows fill the last column of the last page
- **THEN** the next row starts column 1 of a new page, and pages are numbered in ascending order

#### Scenario: Question rows stay atomic
- **WHEN** a question row would straddle a column or page boundary
- **THEN** the solver moves the whole row, never splitting a question's bubbles

### Requirement: Validated break targets only

Absolute placement SHALL be expressed only as validated breaks (for example "start section in column N"). A pinned section MUST fit the target column's remaining capacity; otherwise the solver SHALL return a typed refusal such as `section_does_not_fit_pinned_column` and MUST NOT silently place the section elsewhere.

#### Scenario: Pin that fits
- **WHEN** a section declares a column target and its rows fit that column's remaining space
- **THEN** the section starts at the target column and flows normally from there

#### Scenario: Pin that does not fit
- **WHEN** a section declares a column target that cannot hold all its rows
- **THEN** solving fails with a typed refusal naming the section and column, and nothing is placed

### Requirement: Blueprint persistence as adjacent metadata

Template versions SHALL persist the editable blueprint in `layout_blueprint_json` with `layout_blueprint_hash` and `layout_solver_version` columns. The blueprint MUST NOT be embedded in `template.json` or `provider_config_json`. `template_json_hash` SHALL hash exactly the canonical solved template bytes that are persisted, not client-submitted bytes.

#### Scenario: Blueprint stored beside the solved template
- **WHEN** a blueprint is saved as a draft
- **THEN** the version row stores the blueprint, its hash, and the solver version, and `template_json_hash` equals the hash of the solved canonical template

#### Scenario: No contamination of engine input
- **WHEN** a blueprint is present
- **THEN** the native bundle `template.json` contains only canonical engine geometry and no blueprint keys

### Requirement: Shared solver for preview and save

Live layout preview, PDF preview, and draft save SHALL invoke the same solver. Preview MUST NOT persist any version, job, or audit row. A test SHALL prove that the solved template hash from preview equals the hash of the saved version for the same blueprint and overlay.

#### Scenario: Preview solves without persisting
- **WHEN** staff preview a blueprint
- **THEN** the response returns the solved layout projection, page count, canonical hash, and typed diagnostics, and no version row is created

#### Scenario: Preview hash equals saved hash
- **WHEN** the same blueprint is previewed and then saved
- **THEN** the canonical template hash is identical in both

### Requirement: Typed diagnostics with context

Solver failures SHALL return typed diagnostics carrying section, range, page, and column context (for example a section that cannot fit, a duplicate question range, or a row wider than its column). Validation SHALL follow the order: request shape, blueprint semantics, feasibility, bounds and margins, overlap and duplicate labels/positions, catalog coverage, manifest derivation, activation invariants.

#### Scenario: Duplicate range refusal
- **WHEN** two sections declare overlapping question ranges for the same area
- **THEN** the solver returns a typed diagnostic naming both sections and the duplicated positions

#### Scenario: Impossible row width
- **WHEN** a question row is wider than an empty column
- **THEN** the solver returns a typed diagnostic naming the section and column width

### Requirement: Identity grids repeat per page

When the blueprint enables identity (applicant number and/or birthdate) with repetition enabled, every generated page SHALL carry the identity grids in the reserved top zone, outside the flow area, with geometry from the versioned profile.

#### Scenario: Identity on every page
- **WHEN** a blueprint enables identity repetition
- **THEN** every solved page includes applicant-number and birthdate grids above the question flow zone

### Requirement: Durable, immutable save boundary

Draft saves SHALL stage immutable content-addressed artifacts on a private Laravel Storage disk before any visible version exists. Version allocation SHALL lock the parent template row, enforce uniqueness of (template_id, version) at the database level, and detect duplicates before allocating a visible version. Bundle artifacts MUST NOT be written under the application source tree, and cleanup MUST NOT rely on `exec('rm -rf ...')`.

#### Scenario: Failed save leaves no residue
- **WHEN** duplicate detection or manifest derivation fails during save
- **THEN** no version row, no staged artifact reference, and no source-tree bundle directory are left behind

#### Scenario: Concurrent saves allocate distinct versions
- **WHEN** two saves race on the same template
- **THEN** each transaction allocates a distinct version and both drafts persist without collision
