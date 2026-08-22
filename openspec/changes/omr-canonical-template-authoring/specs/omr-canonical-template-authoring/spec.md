## ADDED Requirements

### Requirement: The system SHALL provide staged canonical-template authoring for five source kinds

The system SHALL provide a staged canonical OMR template authoring pipeline that accepts HTML, DOCX, PDF, image, and preset sources. Every source SHALL produce ordered intermediate page images plus detected physical metadata (paper size in mm, orientation, page count) and provenance through a source adapter. Conversion SHALL run as a queued job with progress, cancel, and retry, and SHALL be gated by per-source hostile-file preflight before conversion starts. Authoring photos (image sources without print-ready vector content) SHALL require manual-confirmed quadrilateral rectification with quality gates before the page is accepted. The preset adapter SHALL wrap the existing solver output by measuring the ACTUAL page dimensions of the existing rendered PDF and rescaling `fieldBlocks` with a normalized physical transform `x_new = round(x_old / old_page_width × new_page_width)` (likewise y/w/h) that preserves normalized regions; the preset path SHALL NOT use a fixed PPI-ratio formula, because the renderer's labeled PPI is not exact physical DPI.

#### Scenario: HTML source ingests through the sanitized adapter
- **WHEN** staff uploads an HTML source with scripts, event handlers, and remote assets
- **THEN** the preflight sanitizer strips or rejects those constructs, the queued conversion produces ordered intermediate pages, and the source is recorded with its SHA-256 and converter version

#### Scenario: Dynamic-looking tokens require allowlisted slots
- **WHEN** an HTML source contains `{{…}}` or other dynamic-looking tokens that are not represented by fixed `data-omr-overlay` slots
- **THEN** ingestion fails with `dynamic_token_rejected` and no conversion runs, while plain static text is accepted

#### Scenario: DOCX source with macros is rejected before conversion
- **WHEN** staff uploads a DOCX containing VBA/macros or exceeding decompression/member caps
- **THEN** ingestion fails with a typed `macro_rejected` or `zip_bomb` error, no conversion runs, and the rejection is audited

#### Scenario: Encrypted PDF is rejected with a typed error
- **WHEN** staff uploads a password-protected or otherwise encrypted PDF
- **THEN** ingestion fails with `encrypted_pdf` and no rasterization occurs

#### Scenario: Authoring photo requires server-confirmed rectification
- **WHEN** staff uploads a photographed supplier page and the quadrilateral rectification is not yet confirmed
- **THEN** the pipeline blocks at source stage until staff positions the four corners (frontend drag is advisory only), the server rectifies via the `POST /v1/source-rectification` worker seam from the confirmed points, the quality gates pass, and staff confirms the rectified page

#### Scenario: Preset source transforms geometry from actual page dimensions
- **WHEN** staff selects the preset adapter for a solved blueprint
- **THEN** the existing rendered PDF's actual page box is measured and `fieldBlocks` are rescaled by the normalized physical transform with parity checks, without rectification and without a fixed PPI-ratio formula

### Requirement: Staff SHALL confirm each intermediate page before composition

The pipeline SHALL require staff confirmation of every intermediate page before Stage-2 composition. Staff SHALL be able to reject a page with a typed reason (`wrong_page`, `duplicate_page`, `upside_down`, `illegible`); these reasons SHALL be recorded as staff classifications and SHALL NOT be asserted as automatic pixel-based claims. Re-ingestion SHALL return the draft to the `draft` stage and void prior confirmations. Composition SHALL refuse to start while any page is unconfirmed or rejected.

#### Scenario: Unconfirmed pages block composition
- **WHEN** staff uploads a three-page source and confirms only two pages
- **THEN** the compose action refuses with a typed `page_not_confirmed` error naming the missing page

#### Scenario: Staff reject reason is a recorded classification
- **WHEN** staff rejects a page as `wrong_page`
- **THEN** the draft records that reason as a user classification with the rejecting actor, and the pipeline treats it as a gate, not as an automatic pixel-derived claim

#### Scenario: Re-ingest voids confirmations
- **WHEN** staff re-ingests a source after confirming pages
- **THEN** the draft returns to the `draft` stage, confirmations are voided, and compose is blocked again

### Requirement: The system SHALL manage versioned marker profiles with qualified selection

The system SHALL maintain versioned marker profiles (`omr_marker_profiles`) with unique `code+version`, family, asset path and SHA-256, physical size_mm and quiet_zone_mm, matching options/config JSON, status `candidate|qualified|retired`, qualification evidence hash, and created/qualified-by/at. The upstream OMRChecker sample marker SHALL start as a candidate and SHALL NOT be assumed to be the production marker; the current custom fiducial profile SHALL be a comparison candidate. Digital and physical qualification SHALL promote EXACTLY ONE profile to `qualified` (any other qualified profile becomes `retired`). A draft SHALL select its `marker_profile_id`; compile and activation SHALL require a qualified profile (`marker_profile_unqualified` otherwise). Staff SHALL adjust marker insets only and SHALL NOT edit a profile's physical size or quiet zone.

#### Scenario: Candidates require qualification
- **WHEN** a draft selects a marker profile that is still `candidate`
- **THEN** the draft may proceed to mapping, but compile fails with `marker_profile_unqualified` until that profile is promoted to `qualified` on evidence

#### Scenario: Qualification promotes exactly one profile
- **WHEN** a qualification run succeeds for a profile
- **THEN** that profile becomes `qualified` with its evidence hash recorded, any other `qualified` profile becomes `retired`, and the single-qualified invariant holds

#### Scenario: Staff adjust insets only
- **WHEN** staff edits marker geometry on a draft
- **THEN** only per-side inset values within the quadrant split change, and the profile's physical size and quiet zone remain unchanged

### Requirement: Authoring drafts SHALL own staged work and compile atomically into immutable versions

Incomplete staged work SHALL live in an `omr_template_authoring_drafts` table (code, name, source_kind, stage, marker_profile_id, authoring_json, lock_version, owner, compiled_version_id) and SHALL NOT be written directly onto the immutable template version. Draft assets SHALL live in an `omr_template_assets` table with nullable `authoring_draft_id` and `template_version_id` foreign keys and a DB CHECK that exactly one is non-null, plus kind, page number, disk path, mime, byte count, SHA-256, and metadata; assets SHALL be immutable once written. On compile, assets SHALL be transferred/copy-pinned to version ownership, and garbage collection SHALL remove ONLY unreferenced draft-owned and temporary assets — version-owned provenance source assets SHALL NEVER be collected. Autosave SHALL use the draft `lock_version` for optimistic concurrency.

#### Scenario: Draft work never mutates the immutable version
- **WHEN** staff stages a source, confirms pages, and maps blocks on a draft
- **THEN** all staged state is written to the draft row and draft-owned asset rows, and no immutable template version row changes

#### Scenario: Asset ownership is a real foreign-key check
- **WHEN** an asset row would reference both a draft and a compiled version, or neither
- **THEN** the write is rejected by the exactly-one-owner check with `asset_owner_violation`

#### Scenario: Compile pins assets and preserves provenance
- **WHEN** compile succeeds
- **THEN** source, raster, PDF, and evidence assets are copy-pinned to version-owned rows, and a later GC pass deletes only unreferenced draft/temp rows while the version-owned provenance assets remain

#### Scenario: Autosave conflict uses the draft lock
- **WHEN** two staff autosave the same draft concurrently and the second save arrives with a stale `lock_version`
- **THEN** the save fails with `concurrency_conflict` and the editor shows a stale-draft banner without overwriting server state

### Requirement: The system SHALL sequence digital verification before compile and physical qualification after compile

Authoring-draft stages SHALL progress `draft → source_confirmed → page_prepared → mapping_complete → digital_verified → compiled`. Compile SHALL require the current mapping, a qualified marker profile, AND digital verification, SHALL atomically create the immutable template version with status `draft`, transfer assets and evidence, and freeze the canonical PDF and bundle; a failed compile SHALL leave no partial version. Physical qualification SHALL run against the exact compiled template version and PDF, SHALL bind evidence to version plus renderer plus marker-profile hashes, and SHALL NEVER be required before compile. Activation SHALL require a compiled version plus `physical_qualified` evidence and SHALL atomically swap the active version, leaving the previous active live until commit.

#### Scenario: Digital verification precedes compile
- **WHEN** staff attempts to compile a draft that is not `digital_verified` or has no qualified marker profile
- **THEN** compile fails with `compile_gate_missing` or `marker_profile_unqualified` and no immutable version is created

#### Scenario: Compile creates a draft-status immutable version
- **WHEN** compile succeeds on a digitally verified draft with a qualified profile
- **THEN** an immutable template version with status `draft` is created atomically, the canonical PDF and bundle are frozen, evidence transfers to version-owned assets, and the draft records `compiled_version_id` and stage `compiled`

#### Scenario: Physical qualification targets the compiled version
- **WHEN** staff runs physical qualification
- **THEN** it runs against the compiled template version and PDF and records evidence bound to version, renderer, and marker-profile hashes

#### Scenario: Activation requires compiled plus physical
- **WHEN** staff attempts to activate a version without `physical_qualified` evidence, or an uncompiled draft
- **THEN** activation fails with `activation_gate_missing` and no active swap occurs

#### Scenario: Old active stays live until swap
- **WHEN** a replacement template version activates
- **THEN** the previous active version remains live and immutable until the new activation commits, then is retired

### Requirement: Stage-2 composition SHALL produce an exact-size 300-PPI canonical page set with a DPI-gated enlargement

Stage 2 SHALL compose confirmed pages into one 300-PPI PNG per page plus an exact-size image-backed PDF print authority. Physical mm SHALL convert deterministically as `px = round(mm × 300 / 25.4)`. Placement SHALL support proportional scale, X/Y offset, and a 9-point alignment reference, with white padding only. Downscaling SHALL always be permitted; proportional visual enlargement SHALL be permitted ONLY when the effective source DPI after physical placement stays at or above the source-type-aware floor, and SHALL be rejected with `insufficient_resolution` otherwise. One paper size and orientation SHALL apply to all pages in v1; pages that violate this SHALL be rejected with a typed list. Independent per-side printer-safe, marker-inset, and content margins SHALL be recorded in mm per page. The system SHALL render the print PDF at an internal scale of exactly 100%; it SHALL NOT claim to enforce an external printer dialog's Fit-to-page setting, and every print surface SHALL show an explicit actual-size instruction and warning.

#### Scenario: Composition converts mm deterministically
- **WHEN** a 216×330 mm long-bond page is composed at 300 PPI
- **THEN** the canonical raster is exactly 2551×3898 pixels and the print PDF page size is exactly 216×330 mm

#### Scenario: Enlargement below the DPI floor is rejected
- **WHEN** a placement would require proportional enlargement that drops the effective source DPI below the source-type-aware floor
- **THEN** composition fails with `insufficient_resolution` and names the page and resulting effective DPI

#### Scenario: Mixed page sizes are rejected with a typed list
- **WHEN** a source contains both A4 and long-bond pages
- **THEN** composition fails with `mixed_page_sizes` and lists the offending page indices

#### Scenario: Print surfaces instruct actual size
- **WHEN** a print surface for a canonical template is shown
- **THEN** the UI displays an explicit `Print at 100% / actual size — do not fit to page` warning, and physical qualification verifies measured output rather than assuming the printer honored it

### Requirement: Every new activation SHALL carry the mandatory four-marker registration from a qualified profile

Every NEW activated canonical template SHALL print four repeated markers from the qualified `omr_marker_profiles` record in the four corner quadrants (engine split `w/2 × h/3`), with `pageDimensions` equal to the marker-center rectangle. The system SHALL add no registration border; an imported supplier border SHALL NOT by itself fail a template — the corner-pattern false-positive probe SHALL prove that imported borders, decorative corners, and partial markers do not register before any activation. The marker contract SHALL have no three-of-four fallback and no Hough fallback. Marker-profile qualification SHALL be TEMPLATE-INDEPENDENT: a SYSTEM marker-calibration artifact/sheet generated by the canonical renderer (four candidate markers plus a controlled background/corner false-positive corpus) is bound to the marker asset hash + canonical renderer version + printer/device/paper evidence, and qualification SHALL NOT require a target template version. A qualified profile SHALL become selectable for drafts; compile SHALL require a qualified profile. After compile, a SEPARATE template physical qualification SHALL bind the compiled template version + the already-qualified marker profile + canonical renderer version + printer/device/paper and SHALL gate activation; template qualification SHALL NOT re-qualify the profile.

#### Scenario: Four markers from a qualified profile are mandatory
- **WHEN** staff attempts to activate a canonical template whose marker contract has fewer than four markers, adds a system registration border, or uses an unqualified profile
- **THEN** activation fails with a typed marker error and the template remains unactivated

#### Scenario: Corner patterns and imported borders never register
- **WHEN** the false-positive probe runs decorative corners, partial markers, border-like patterns, or an imported supplier border through the marker preprocessor
- **THEN** every probe yields `marker_missing` or `marker_mismatch`, none registers a page, and the source is not rejected merely for containing a border

#### Scenario: Marker profile qualifies from calibration evidence without a template
- **WHEN** a calibration-artifact run records marker-profile evidence bound to the marker asset hash, renderer version, and printer/device/paper, with the corner false-positive corpus passing
- **THEN** the physical marker size and quiet zone are frozen from those qualified results, the profile becomes `qualified` (single-qualified invariant), and no target template version is involved

#### Scenario: Template qualification binds version to an already-qualified profile
- **WHEN** template physical qualification runs against a compiled version
- **THEN** it records evidence binding the compiled version, the already-qualified marker profile, renderer version, and printer/device/paper, gates activation, and does not re-qualify the profile

### Requirement: Overlays SHALL be fixed boxes across three scopes with a closed tag set

Overlay layers SHALL support template-time, print-job, and blank-instance scopes with fixed-position boxes and no reflow. Allowed tags SHALL be limited to `institution`, `assessment`, `template`, `blank_instance_code`, `page_number`, `page_count`, and `blank_instance_qr`. Applicant identity tags SHALL be rejected. HTML source dynamic slots SHALL be fixed `data-omr-overlay` markers; unrecognized dynamic tokens SHALL be rejected at preflight rather than rendered.

#### Scenario: Applicant tags are rejected
- **WHEN** an overlay or tag payload references applicant name, number, or birthdate
- **THEN** the overlay action fails with `tag_forbidden` and no overlay is saved

#### Scenario: Static raster is instance-independent
- **WHEN** two blank instances of the same template version are composed
- **THEN** the canonical static raster bytes are identical and only the print-job overlay differs

#### Scenario: HTML dynamic slots are fixed markers
- **WHEN** an HTML source declares a dynamic slot
- **THEN** the slot is recognized only through a fixed `data-omr-overlay` marker, and dynamic tokens outside such slots are rejected at preflight

### Requirement: Blank instances SHALL use signed versioned QR payloads with a keyring and unique display codes

The blank-instance QR SHALL carry an opaque, versioned, signed payload encoding the instance code, page number, page count, signing key id, and an HMAC signature over those fields (`signature_version=1`), and SHALL print a UNIQUE checksummed display code beneath it as a human fallback. The system SHALL store each page's payload hash and signing key id, and the set's `qr_manifest_hash` (SHA-256 of the ordered per-page signed payload hashes — every page payload differs), SHALL verify signatures against a configured keyring so key rotation still verifies historical prints, and SHALL reject unknown keys or invalid signatures with `qr_key_unknown`/`qr_signature_invalid`. The QR SHALL NOT carry applicant identity.

#### Scenario: Signed payload verifies through the keyring
- **WHEN** a capture registration presents a QR whose payload signature verifies under the configured signing key
- **THEN** registration proceeds to instance and page validation

#### Scenario: Key rotation verifies historical prints
- **WHEN** a payload was signed with a rotated-out key that remains in the keyring
- **THEN** verification succeeds via the historical key id, and an unknown key id fails with `qr_key_unknown`

#### Scenario: Forged or altered payload is rejected
- **WHEN** a payload signature is invalid or missing
- **THEN** registration fails with `qr_signature_invalid` and records no capture

#### Scenario: Display code is a unique lookup aid only
- **WHEN** staff enters a display code for manual recovery
- **THEN** the code resolves to exactly one instance, and the code alone never authorizes a capture or bypasses signature verification

### Requirement: Blank instances SHALL track sets and pages with a terminal lifecycle and no applicant binding

The system SHALL record printed page sets in `omr_blank_instances` (one row per set, unique ≥128-bit instance code, unique short checksummed display code, monotonic per-version `page_set_no` that is never reused) and per-page rows in `omr_blank_instance_pages` (page number, QR payload hash, signing key id, print artifact hash, page status, captured page reference to `omr_capture_sheet_pages`). Neither table SHALL contain any applicant foreign key. Set status SHALL transition `printed → captured | superseded | void`; all three non-printed states SHALL be terminal, and `void` SHALL NOT be permitted after `captured` or `superseded`. A set SHALL become `printed` only when the complete page set rendered; failed or partial renders SHALL never mark a set printed. A `printed` set SHALL remain `printed` while partially captured, with page progress derived from page rows, and SHALL become `captured` only when ALL required pages are captured. Superseding a partially captured set SHALL require explicit whole-set replacement with audit, and old page captures SHALL remain evidence. Capture registration SHALL return typed issues for duplicate capture, missing or mixed pages, tamper, template mismatch, and superseded instances, while marker registration remains the recognition authority. DB CHECK constraints SHALL enforce status/page/page-count values and unique display codes, and terminal lifecycle transitions SHALL be enforced in the actions and covered by tests.

#### Scenario: Completed print batch creates set and page rows
- **WHEN** a blank-print batch completes for a template version
- **THEN** one `omr_blank_instances` row exists per printed page set with status `printed`, every page of the set has a `printed` page row, and page-set numbers are monotonic and never reused

#### Scenario: Partial render never prints a set
- **WHEN** one page of a page set fails to render
- **THEN** the failed page has no `printed` row, the set does not become `printed`, and the batch completes with partial status listing the failed page and its typed error

#### Scenario: Partial capture keeps the set printed until complete
- **WHEN** some but not all pages of a printed set are captured
- **THEN** the set remains `printed`, page rows show per-page `captured` status, and the set becomes `captured` only when all required pages are captured

#### Scenario: Duplicate and missing-page capture are typed
- **WHEN** a second sheet registers against an already-`captured` instance, or a set is missing page rows
- **THEN** registration returns `duplicate_instance` or `page_missing_from_set` respectively, and no duplicate capture is recorded

#### Scenario: Replacement supersedes the old page set
- **WHEN** staff performs an explicit, audited whole-set replacement
- **THEN** a new page set with the next page-set number is created, the previous set becomes `superseded` with a `superseded_by` link across all its pages, and prior page captures remain evidence

#### Scenario: Void follows only printed
- **WHEN** staff attempts to void a set that is `captured` or `superseded`
- **THEN** the void action is refused with a typed lifecycle error

#### Scenario: Tamper and template mismatch are typed
- **WHEN** a sheet scans with a valid signature but an instance row that is missing or void, or with a different template version
- **THEN** registration returns `tamper_detected` or `template_mismatch` respectively, and no capture is silently recorded

#### Scenario: Constraints reject invalid rows
- **WHEN** a write violates a stage, status, page_no, page_count, exactly-one-owner, or unique-display-code constraint, or a terminal transition
- **THEN** the write is rejected by the constraint and/or action with a typed error and the database remains consistent

### Requirement: Blank printing SHALL chunk at high volume with idempotent retries, per-page partial failure, and manifest downloads

Blank printing SHALL process high-volume requests in chunks with a per-item and per-page manifest and statuses, and SHALL preserve page order in any chunk download or ZIP artifact. Retrying a failed chunk SHALL be idempotent from pinned inputs and MUST NOT duplicate set or page rows. A failed instance SHALL produce no `printed` status, while successful instances SHALL remain downloadable.

#### Scenario: Large print request chunks with progress
- **WHEN** staff requests 500 blank instances
- **THEN** the batch renders in chunks of 100 with progress counters, successful sets receive one set row plus one page row per page, and chunk manifests preserve page order for download/ZIP

#### Scenario: Retry does not duplicate
- **WHEN** a chunk fails and staff retries it with the same idempotency key
- **THEN** the retry resumes or completes the same set and page rows without creating duplicates

#### Scenario: Failed instance is not printed but others download
- **WHEN** one instance in a chunk fails to render
- **THEN** that instance has no `printed` status, the batch finishes with partial status naming the failed instance and its typed error, and the successful instances remain downloadable

### Requirement: Manual damaged-QR recovery SHALL be an explicit audited fallback that never overrides a bad signature

When a QR payload is PRESENT but its signature or key is invalid, registration SHALL fail hard with `qr_signature_invalid`/`qr_key_unknown`, and manual display-code entry SHALL NOT override that failure. When the payload cannot be DECODED or is ABSENT, capability-gated staff SHALL be able to enter the unique checksummed display code; the server SHALL resolve the set, page, and template from the database, SHALL require explicit staff confirmation, SHALL record `instance_resolved_manually` with an audit event, and SHALL keep recognition blocked until confirmed. Manual recovery is an explicit audited fallback, NOT signature verification — there is no payload to verify when the QR is unreadable.

#### Scenario: Present-but-invalid payload is a hard error
- **WHEN** a sheet carries a QR whose payload decodes but fails signature or key verification, and staff attempts manual display-code entry
- **THEN** registration fails with the underlying `qr_signature_invalid`/`qr_key_unknown` error and the manual entry does not override it

#### Scenario: Undecodable QR resolves via display code with confirmation
- **WHEN** staff enters a display code for an undecodable or absent QR and confirms the resolved instance
- **THEN** the system resolves the set/page/template from the database, records `instance_resolved_manually` with the actor, and recognition remains blocked until the confirmation completes

### Requirement: Mapping SHALL use a native Svelte SVG scene with canonical-pixel coordinates and constrained editing

Staff mapping SHALL run in a native Svelte SVG scene over the canonical raster with native pointer events and SHALL NOT use Konva or Fabric, on tablet/desktop widths (phone view-only). The scene viewBox and all stored coordinates SHALL be canonical pixels, converted from pointer events through the inverse CTM, with mm derived for display only. The editor SHALL support overlay box drag/resize with 4px snap, OMR block/grid creation, and per-row uniform structural adjustment. Individual bubbles SHALL be inspect-only. The editor SHALL implement keyboard movement (1 px, Shift+Arrow 10 px), touch interaction with adequate hit targets, undo/redo, debounced autosave with draft `lock_version` conflict detection, and keyboard/ARIA accessibility.

#### Scenario: Scene coordinates are canonical pixels
- **WHEN** staff drags a block and reads the inspector
- **THEN** stored and communicated coordinates are canonical pixels from the inverse CTM, and the inspector derives mm via `px × 25.4 / 300` for display

#### Scenario: Bubble inspection is read-only
- **WHEN** staff selects a bubble in the mapping editor
- **THEN** the inspector shows page, block, row, option, pixel rect, and normalized region, and the bubble cannot be moved or resized

#### Scenario: Per-row adjustment preserves row uniformity
- **WHEN** staff adjusts row pitch, option pitch, or bubble diameter for one row
- **THEN** every bubble in that row updates uniformly and no individual bubble geometry is created

#### Scenario: Autosave conflict is surfaced
- **WHEN** the draft's stored `lock_version` changed since the editor loaded
- **THEN** autosave reports `concurrency_conflict`, the editor shows a stale-draft banner, and no client geometry is silently overwritten

#### Scenario: Keyboard and touch contracts hold
- **WHEN** staff uses keyboard arrows, Shift+Arrow, or touch drag on an overlay box
- **THEN** movement follows the documented 1 px / 10 px / 4px-snap contract with visible feedback and accessible labels

### Requirement: Mapping validation SHALL enforce regular geometry with explicit splitting

Mapping validation SHALL enforce unique contiguous question labels, supported regular option sets (v1 MCQ4/MCQ5/int), no duplicate/overlap/out-of-bounds regions, no marker/content/identity/header collisions, minimum physical bubble diameter (≥4.2 mm) and option edge gap, and source/page-count parity. Irregular option spacing SHALL be unsupported in v1. Overfull rows and irregular option rows SHALL be BLOCKING (`overfull_row`, `irregular_options`); the validator SHALL NEVER apply a split automatically — staff SHALL explicitly split the block into one-label regular blocks, preview the result, and only then SHALL the validator recheck.

#### Scenario: Under-sized bubbles block save
- **WHEN** a mapping row uses a bubble diameter below 4.2 mm
- **THEN** save fails with `bubble_too_small` and the offending row is named

#### Scenario: Collisions are precise
- **WHEN** a block overlaps the marker zone, content zone, or another block
- **THEN** save fails with a typed collision error naming both regions

#### Scenario: Overfull row blocks until explicit split
- **WHEN** a row's option span exceeds its column band
- **THEN** save fails with `overfull_row`, the validator never rewrites the geometry automatically, and save succeeds only after staff explicitly splits the block into one-label regular blocks, previews, and re-validates

### Requirement: fieldBlocks SHALL remain the recognition authority with hash-invalidation and read-only compiled versions

The saved mapping SHALL emit `field_blocks` in the OMRChecker shape. The recognition algorithm and the `fieldBlocks` contract SHALL remain pinned and unchanged; the compiler/bundle asset manifest and the worker request/response envelope MAY extend to carry canonical assets, QR decode, and item evidence without altering recognition semantics. Any source, composition, or overlay change SHALL invalidate the stored mapping and all verification hashes. Compiled and activated versions SHALL be read-only, and the previously active version SHALL remain live until the replacement activation commits.

#### Scenario: Mapping feeds the pinned bundle path
- **WHEN** a canonical mapping is compiled into a native bundle
- **THEN** the bundle contains the same `fieldBlocks` shape the worker already consumes, and the recognition algorithm and `fieldBlocks` contract are unchanged

#### Scenario: Source change invalidates mapping and verification
- **WHEN** the source or composition of a mapped draft changes
- **THEN** the mapping is cleared and verification hashes are invalidated with `stale_mapping` / `verification_invalidated`, requiring re-mapping and re-verification

#### Scenario: Compiled versions are read-only
- **WHEN** any edit attempts to mutate a compiled or activated version
- **THEN** the mutation is refused and the version's bundle, PDF, and manifest remain byte-identical

### Requirement: Test capture SHALL provide whole-page and per-item evidence driven by the declared option set

The Test capture diagnostic SHALL show the whole normalized page with the engine `draw_template_layout` overlay and interactive per-item/row evidence driven by the DECLARED OPTION SET (A–D for MCQ4, A–E for MCQ5, 0–9 for int; A–D is the common example): option circles and rectangles, crops, bubble means, threshold delta and status, field-block shifts, and marker matches. The result SHALL carry source, mapping, and bundle hashes. The diagnostic SHALL be read-only and SHALL provide a `Return to mapping and select row` path that selects the investigated row in the mapping editor. Thresholds SHALL remain `Unqualified` until physical calibration.

#### Scenario: Per-item evidence matches the declared option set
- **WHEN** staff runs Test capture on a photo of the printed sheet and selects a row with four options
- **THEN** the panel shows A/B/C/D crops, rects, means, threshold status, and shift markers for that row, all read-only, with source/mapping/bundle hashes, and five-option or ten-option rows use their declared A–E or 0–9 sets

#### Scenario: Return-to-row selects the investigated row
- **WHEN** staff activates `Return to mapping and select row` from an evidence row
- **THEN** the mapping editor opens with that row selected and no geometry or score state is mutated

#### Scenario: Thresholds stay unqualified
- **WHEN** Test capture evidence shows per-bubble means and threshold deltas before calibration
- **THEN** the panel labels thresholds `Unqualified` and never presents them as accepted recognition truth

### Requirement: Activation SHALL require draft-scoped digital proof, a no-go matrix, and version-scoped physical qualification

Digital verification SHALL run on the draft and SHALL be required before compile. The digital proof SHALL start from the draft's final composed exact-size PDF, programmatically shade a deterministic declared answer vector derived from mapped labels (a paired booklet SHALL NOT be required for template proof), add the signed instance QR and display code, and SHALL shade a deterministic identity ONLY when a mapped identity grid exists — no fabricated identity field otherwise. Booklet compatibility with an answer key SHALL be tested separately when a pairing exists. After compile, the digital proof SHALL repeat as a hash-parity smoke against the frozen PDF and bundle. The no-go matrix SHALL run clean, perspective, rotation, light, blur, glare, low-resolution, partial, missing-marker, wrong-marker, wrong-instance, superseded, duplicate, and multipage-order variants, each producing the declared outcome or a typed rejection. Physical qualification SHALL run against the compiled version, bind evidence to the template version, canonical renderer version, and marker profile, and SHALL record printer identity, device, paper, measured output, and per-variant outcomes; it SHALL gate `physical_qualified` without claiming to enforce external printer settings. Activation SHALL NEVER occur without genuine physical evidence.

#### Scenario: Digital proof is draft-scoped and booklet-independent
- **WHEN** staff runs digital verification on a draft with no paired booklet
- **THEN** the proof raster derives from the draft's composed exact-size PDF with a deterministic declared answer vector from mapped labels and the signed instance QR

#### Scenario: Identity shading requires a mapped identity grid
- **WHEN** a template has no mapped identity grid
- **THEN** the digital proof adds no identity field and never fabricates one

#### Scenario: No-go variant fails loudly
- **WHEN** a missing-marker or wrong-instance variant is processed in the matrix
- **THEN** the matrix records the typed failure for that variant and compile/activation stays blocked

#### Scenario: Post-compile proof repeats as hash parity
- **WHEN** the digital proof re-runs against the compiled version
- **THEN** it asserts only hash parity of repeated identical runs, never cross-path byte equality

#### Scenario: Physical evidence is required and version-bound
- **WHEN** a version has digital verification but no physical qualification evidence against the compiled version
- **THEN** activation fails with `physical_evidence_missing` and the version remains compiled/unactivated

#### Scenario: Fabricated physical evidence never activates
- **WHEN** an activation is attempted without genuine recorded physical qualification evidence bound to the compiled version, renderer, and marker profile
- **THEN** activation is refused, and seeded demo data cannot fabricate `physical_qualified` for supplier templates

#### Scenario: Qualification evidence gates the swap
- **WHEN** physical qualification records renderer, marker profile, printer, device, paper, and measured-output evidence with passing variants
- **THEN** activation proceeds, the version becomes `active`, and the previous active version is retired

### Requirement: Canonical authoring SHALL enforce security, audit, and operational controls

Source ingestion SHALL validate MIME against magic bytes, enforce upload/page/memory/time/concurrency limits and bounded pixel budgets (per-page and per-job) plus physical custom-size bounds, sanitize HTML with a RESEARCHED/PINNED server-side allowlist sanitizer (CSS property filtering, remote-off, dynamic-token rejection, deterministic output), reject encrypted PDFs and macro-bearing DOCX, normalize image EXIF and accept no-profile/sRGB while converting non-sRGB ICC profiles ONLY through a researched deterministic pipeline or rejecting with `image_icc_unsupported`, flatten transparency, and require a minimum effective DPI (≥150 for 4.2 mm bubbles). Assets SHALL store privately with SHA-256 provenance; garbage collection SHALL remove ONLY unreferenced draft/temp artifacts and never version-owned provenance assets. Every new action SHALL require the `guidance.scoring.omr` capability and SHALL write an audit event. Diagnostics SHALL be staff-only. Mutating actions SHALL require idempotency keys. The server-side QR renderer used by the print queue SHALL be a researched, pinned, maintained dependency with deterministic SVG/PNG output, fixed ECC and quiet zone, vetted license/security/version, and no network calls at render time; the client `qrcode` package SHALL NOT be treated as sufficient for PHP queue rendering, and the research task SHALL be completed before QR rendering implementation tasks begin.

#### Scenario: Pixel budget or resolution is exceeded
- **WHEN** an image source exceeds the per-page or per-job pixel budget, violates physical paper bounds, or yields an effective DPI below 150
- **THEN** ingestion fails with `pixel_budget_exceeded`, `paper_out_of_bounds`, or `insufficient_resolution` and a corrective message

#### Scenario: Unsupported ICC profile is rejected or converted deterministically
- **WHEN** an image carries a non-sRGB ICC profile and no researched deterministic conversion pipeline is pinned
- **THEN** ingestion fails with `image_icc_unsupported` rather than promising an unsupported conversion, and no conversion runs

#### Scenario: Server-side QR rendering is pinned before printing
- **WHEN** the print queue renders blank-instance pages
- **THEN** the QR is rendered server-side by a researched, pinned, maintained renderer with deterministic output and no network calls, and the research task is recorded as complete in project documentation before QR rendering tasks begin

#### Scenario: Unauthorized action is denied and unobservable
- **WHEN** a staff member without `guidance.scoring.omr` calls an ingest, confirm, compose, map, verify, compile, activate, print, supersede, void, manual-resolve, qualify, or registration route
- **THEN** the route denies access without existence leakage and records no state change

#### Scenario: Every new action is audited
- **WHEN** a draft create, ingest, confirm, compose, overlay save, mapping save, marker-profile selection or qualification, digital verification, compile, physical qualification, activation, blank print, supersede, void, manual resolution, or capture registration succeeds
- **THEN** an audit event records actor, artifact, action, and relevant hashes

#### Scenario: Orphan draft and temp assets are collected without touching provenance
- **WHEN** a scheduled GC pass finds unreferenced draft-owned or temporary canonical assets past their TTL
- **THEN** they are deleted and the cleanup is recorded, while version-owned provenance assets are never collected

### Requirement: The authoring UX SHALL be staged with explicit per-screen states and microcopy

The authoring flow SHALL present stages Start, Convert/inspect, Prepare page, Overlay, Map, Digital verify, Compile, Physical qualify/Activate, and Post-activation blank print in one workflow shell, with stages 1–7 draft-scoped and the shell pivoting to the compiled template version for stages 8–9 after compile. Compile SHALL be blocked until mapping, a qualified marker profile, and digital verification pass; Physical qualify/Activate SHALL be blocked until compile; Blank print SHALL be disabled until the version is `active`. Every screen SHALL define loading, empty, error, blocked, conflict, success, and recovery states with corrective microcopy, primary and blocked CTAs, progress/cancel, back navigation, unsaved-change guards, focus management, and responsive behavior (phone view-only; editor requires ≥1024 px). Every print surface SHALL show the actual-size instruction and warning.

#### Scenario: Stage gates block forward progress
- **WHEN** a stage's required gate has not passed
- **THEN** the UI shows the blocked state with the missing gate and a corrective action, and the next stage is not reachable

#### Scenario: Compile and physical stages enforce sequencing
- **WHEN** a draft is not digitally verified or has no qualified marker profile, or a version has no compiled artifact
- **THEN** the Compile stage (for the draft) or Physical qualify/Activate stage (for the version) shows the blocked state and cannot be entered

#### Scenario: Unsaved mapping guards navigation
- **WHEN** staff navigates away with a pending autosave
- **THEN** the UI warns before leaving and keeps the pending save state visible

#### Scenario: Phone is view-only
- **WHEN** a phone-width viewport opens the mapping editor
- **THEN** the editor is read-only with an explicit open-on-tablet message, while earlier stages remain viewable

#### Scenario: Blank print stays disabled until active
- **WHEN** a compiled version is not yet `active`
- **THEN** the Blank print stage is disabled with a `No active compiled version` blocked state, and it enables only after activation commits

### Requirement: Migration SHALL retain the solver and old builder until semantic parity, then remove cleanly

The current layout solver SHALL be retained as the preset adapter. The old template builder SHALL remain live until a semantic parity checklist passes, then SHALL be hidden and removed in a clean cut with no dual builder and no re-export shims. Parity SHALL be semantic, using normalized transforms from actual page dimensions: same pages, labels, and options; physical centers and regions within a declared tolerance; normalized review regions within tolerance; deterministic recognition exactness; and a visual-diff threshold. Byte-identical rasters across render paths SHALL NOT be required and SHALL NOT be expected; hash equality SHALL apply only to repeated canonical runs with identical inputs. Historical markerless templates SHALL remain immutable and scan-only.

#### Scenario: Parity checklist gates the cutover
- **WHEN** the semantic parity checklist runs against the profile and seed templates
- **THEN** it passes only if pages, labels, options, physical regions within tolerance, normalized regions, and deterministic recognition outcomes agree, and the old builder is not hidden or removed before that

#### Scenario: Clean cut leaves no dual builder
- **WHEN** the old builder is removed
- **THEN** no old-builder code, aliases, or re-export shims remain and the canonical workflow is the only authoring entry

#### Scenario: Historical markerless templates stay scan-only
- **WHEN** an existing markerless template version is used for capture
- **THEN** it continues through the existing marker-less `CropPage` path unchanged and never gains a canonical marker contract

#### Scenario: Parity-phase rollback is a flag flip
- **WHEN** a regression appears during the parity phase
- **THEN** flipping the `omr.canonical.enabled` flag off restores the previous route and old builder without dropping any canonical data

#### Scenario: Post-cutover rollback is a forward-compatible deployment rollback
- **WHEN** a regression appears after the old builder is deleted
- **THEN** rollback deploys the previous application release while KEEPING all additive canonical tables and data, and the schema down-migrations are never used as an operational rollback because they destroy drafts, assets, and instances without restoring deleted code

#### Scenario: Down migrations are test/dev teardown only
- **WHEN** a developer runs the additive migrations' `down()` in a clean test or development environment
- **THEN** the tables drop with an explicit data-loss warning and no production data is affected
