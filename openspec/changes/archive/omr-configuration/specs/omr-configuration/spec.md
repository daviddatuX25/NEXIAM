# omr-configuration Spec

## ADDED Requirements

### Requirement: Native bundle intake verifies before activation

Staff SHALL register an answer-sheet design by uploading the native bundle files (`template.json`, `config.json`, reference asset). The system MUST verify internal file hashes, page dimensions, engine identity (name/commit/patch), and derive the review manifest before a new template version can be created or activated. Any failed verification MUST reject the intake with typed errors and write nothing.

#### Scenario: Valid bundle intake
- **WHEN** staff upload a well-formed native bundle with matching hashes and engine identity
- **THEN** the system derives the review manifest, shows the preview, and creates a new draft template version

#### Scenario: Tampered bundle rejected
- **WHEN** an uploaded bundle file hash does not match its manifest or the engine identity is unknown
- **THEN** the intake is rejected with a typed error and no template version row is created

#### Scenario: Activation is one-way
- **WHEN** a draft template version is activated
- **THEN** its status becomes `active` and any later edit MUST create a new version rather than mutate the activated row

### Requirement: Booklet and answer-key authoring

Staff SHALL create question booklets with questions grouped by aptitude area, each carrying a correct option. The answer key MUST remain NEXIAM-only: `correct_option_code` is stored in the NEXIAM database (readable only to OMR-capable staff) and never crosses the provider seam; a SHA-256 digest of the key SHALL be recorded on the booklet version as an integrity record. Versions are immutable once activated.

#### Scenario: Authoring a booklet
- **WHEN** staff create a booklet with contiguous questions per area and complete correct-option coverage
- **THEN** the booklet version is saved as draft with its answer-key hash

#### Scenario: Incomplete key rejected
- **WHEN** a booklet version has missing correct options or non-contiguous question positions
- **THEN** activation MUST fail with typed errors and the version stays draft

#### Scenario: Editing an active booklet
- **WHEN** staff edit an active booklet's questions or key
- **THEN** the system creates a new draft version and leaves the active version untouched

### Requirement: Pair activation is the only state-flipping path

Pair activation SHALL be the ONLY path that moves template and booklet versions from draft to active: staff select a draft template version and a draft booklet version, and `ActivateOmrConfiguration` validates compatibility and activates both atomically. Standalone per-version activation routes MUST NOT exist. When the draft booklet carries staff-authored questions, activation MUST validate per-question correct-option coverage and contiguity against the derived manifest and conversion tables instead of generating keys, and persist the authored rows.

#### Scenario: Compatible drafts pair-activate
- **WHEN** a draft template version and a draft booklet version are compatible
- **THEN** both become `active`, the pair is created, and capture batches may select it

#### Scenario: Authored booklet activates
- **WHEN** a draft booklet carries staff-authored questions with complete keys
- **THEN** activation validates the authored rows (contiguity, A–D coverage per question, conversion coverage) and persists them

#### Scenario: Incompatible pair rejected
- **WHEN** the booklet question counts do not match the template area `max_items`
- **THEN** activation fails atomically and no pair row is created

### Requirement: Reject and retry reachable from the UI

Staff SHALL be able to reject an empty open batch and to retry a `failed_terminal` or `needs_review` sheet with no active run, from the capture surface. Each action MUST enforce the same rules as its domain action and surface typed errors.

#### Scenario: Rejecting an empty batch
- **WHEN** staff reject an open batch with no sheets
- **THEN** the batch becomes `rejected` and the capture page reflects it

#### Scenario: Retrying a failed sheet
- **WHEN** staff retry a `failed_terminal` sheet with no active run
- **THEN** the next processing run is created, the sheet becomes `queued`, and analysis dispatches after commit

#### Scenario: Retry blocked while active
- **WHEN** staff retry a sheet that has an active run
- **THEN** the action is refused with a typed error and no new run is created

### Requirement: Compliant template builder writes the native bundle

A visual builder SHALL edit the native `template.json` structure (pages, page dimensions, identity grid, question blocks, origins, gaps, bubble sizes). Every save MUST run full bundle validation (unique labels, in-bounds non-overlapping regions, contiguity) before creating a draft version; saved output MUST flow through the unchanged pin → verify → derive → activate path. The builder MUST NOT create any NEXIAM coordinate authority separate from the bundle.

#### Scenario: Builder saves a valid sheet design
- **WHEN** staff design a two-page sheet and save
- **THEN** the output validates as a native bundle, becomes a new draft template version, and previews from the shared renderer

#### Scenario: Builder rejects invalid geometry
- **WHEN** a design has overlapping or out-of-bounds bubbles
- **THEN** saving is refused with typed errors and no version is created

#### Scenario: Builder cannot mutate activated versions
- **WHEN** staff try to edit an active template version
- **THEN** the system refuses and requires a new draft version

### Requirement: Configuration surfaces under System Settings

The OMR Configuration section SHALL live in the Setup hub, capability-gated with the existing OMR capability, exposing templates, booklets, pairs, the print-queue link, and readiness checks (active pair exists, booklet key coverage complete, paper size configured).

#### Scenario: Setup hub shows OMR configuration
- **WHEN** an OMR-capable staff member opens Setup
- **THEN** the OMR Configuration section is visible with its sub-surfaces

#### Scenario: Unauthorized staff denied
- **WHEN** a staff member without the OMR capability opens the configuration surfaces
- **THEN** access is denied without existence leakage

### Requirement: Retiring configuration versions

Template versions, booklet versions, and pairs SHALL support a `retired` state. Retired pairs MUST be excluded from new batch creation; open batches are unaffected because they pin version IDs; retired template versions remain printable by explicit reference.

#### Scenario: Retiring an active pair
- **WHEN** staff retire an active pair
- **THEN** the pair no longer appears in new batch pair selection and existing open batches keep working

### Requirement: Duplicate configuration detection

Intake SHALL compare the computed bundle hash against existing versions and refuse with a typed "identical version already exists" response when matched. The builder SHALL compare the canonical JSON hash of a save against existing draft/active versions and offer to open the existing version instead of creating a duplicate.

#### Scenario: Duplicate bundle intake
- **WHEN** staff upload a bundle whose hash matches an existing version
- **THEN** intake is refused with a typed duplicate-version error

#### Scenario: Identical builder save
- **WHEN** a builder save is byte-identical to an existing version
- **THEN** the system offers to open the existing version instead of creating a new one
