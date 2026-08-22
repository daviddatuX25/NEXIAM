## ADDED Requirements

### Requirement: The system SHALL provide a canonical compact one-page profile

The profile SHALL use the server-owned layout solver with a long-bond page, six columns, six non-overlapping 25-question sections, one native applicant-number identity grid, a render-only manual birthdate field, and a printed-name overlay. The solved native template SHALL use compact 40×40 bubbles, 52px option spacing, 80px printable margins, and one page containing exactly 150 scorable question labels. Birthdate, name, instructions, and exam-information text SHALL remain outside native OMR geometry and automatic OCR.

#### Scenario: Profile blueprint solves to one bounded page

- **WHEN** the profile blueprint is solved through the application layout action
- **THEN** the result has one 2060×3144 page, six question blocks, 150 questions, compact 40×40 bubbles, and no geometry diagnostic

#### Scenario: Profile print preview uses persisted solver output

- **WHEN** staff opens the profile preview or print route
- **THEN** the response renders the same persisted native bundle geometry and does not scale or substitute a client-only layout

#### Scenario: Profile geometry remains margin-safe

- **WHEN** every question and option region is projected from the solved template
- **THEN** each region remains inside the 80px page margin and no two answer regions overlap
### Requirement: Every rendered answer-sheet page SHALL provide a manual name fallback

The shared print renderer SHALL include a clearly labeled `NAME (PRINT):` field and blank writing box on every rendered answer-sheet page, even when no overlay metadata is supplied or the header stack leaves no room above the first OMR block. The field SHALL be placed in a collision-free printable area, SHALL remain outside native OMR geometry, and SHALL be usable by staff as a manual identity fallback when bubbled identity data is inaccurate. The system SHALL not infer or OCR the handwritten name automatically.

#### Scenario: Default print output includes a manual name field

- **WHEN** staff previews or prints any supported answer-sheet page without a custom name overlay
- **THEN** the page contains a visible `NAME (PRINT):` label and writing box inside the printable margins

#### Scenario: Manual name fallback does not alter detection geometry

- **WHEN** the name field is rendered and a sheet is processed by the OMR pipeline
- **THEN** the native bundle, derived manifest, and provider geometry contain no name field and remain byte-identical to the non-name render input
### Requirement: The profile SHALL provide an extensible EDQ/header zone

The profile print overlay SHALL support independent exam-information lines, instructions lines, a manual `BIRTHDATE:` write-in box, and the existing manual name box. These render-only blocks SHALL occupy the top header zone, SHALL not overlap applicant-number or answer bubbles, and SHALL not add identity fields to the derived manifest. The profile SHALL omit instructional footer text and calibration copy, leaving the lower print margin clear.

#### Scenario: Header fields render without changing OMR geometry

- **WHEN** staff previews or prints the profile
- **THEN** the page shows the title, EDQ/header content, applicant-number bubbles, manual birthdate/name boxes, and six answer areas while the persisted native bundle remains unchanged

#### Scenario: Profile footer remains clear

- **WHEN** staff renders the profile page
- **THEN** no `Print at 100% scale`, calibration bar, or instructional footer line appears below the answer frame


### Requirement: The system SHALL provide a matching six-domain answer key

The profile SHALL use six active aptitude-area codes (`PROFILE_VERBAL`, `PROFILE_QUANT`, `LOGIC`, `SPATIAL`, `MEMORY`, and `LANGUAGE`), with 25 questions per area and contiguous per-area question numbers; the existing 100-item `VERBAL` and `QUANT` areas SHALL remain unchanged for legacy pairs. The authored booklet SHALL contain exactly 150 rows associated with the profile template and SHALL cycle correct options deterministically as A, B, C, D.

#### Scenario: Catalog provides the profile domains

- **WHEN** the reference catalog is seeded
- **THEN** all six profile area codes exist as active aptitude areas with `max_items=25` and stable names

#### Scenario: Answer key aligns with the six solved blocks

- **WHEN** the profile pair is activated
- **THEN** activation succeeds, each of the six areas has 25 authored questions, and each area’s question numbers are contiguous from 1 through 25

#### Scenario: Profile pair is immutable after activation

- **WHEN** the profile seeder runs after the pair is active
- **THEN** it does not rewrite the active template, booklet, bundle, or answer key

### Requirement: The system SHALL seed deterministic profile capture states

The default development seed SHALL expose a focused near-production profile capture batch with exactly one active profile pair, one booklet, one capture batch, and one complete needs-review sheet bound to one whole examinee. Incomplete-page/resume and retryable terminal-failure fixtures SHALL remain available through an explicit opt-in multi-state seeder, using the same OMR models and action boundaries. Every fixture set SHALL identify itself through a stable natural marker and SHALL be idempotent.

#### Scenario: First focused profile seed creates one coherent examinee

- **WHEN** the developer runs the approved grading demo seeder after a reset
- **THEN** the profile pair, one booklet, one open capture batch, one needs-review sheet, one page, and one roster-bound examinee are available from the grading OMR route, with no pending-page or failed-terminal rows in the default state

#### Scenario: Opt-in state seeder exposes workflow branches

- **WHEN** the developer runs the explicit multi-state capture seeder
- **THEN** needs-review, incomplete-page/resume, and retryable terminal-failure examples are available without changing the focused default dataset

#### Scenario: Re-running either profile seed is safe

- **WHEN** the developer runs the same focused or opt-in seeder again
- **THEN** profile template versions, booklet questions, capture batches, sheets, and pages are not duplicated

#### Scenario: Profile capture remains fake-fixture data

- **WHEN** a seeded profile sheet is opened for review
- **THEN** fake fixture metadata is explicit and provider confidence remains null until a real qualification run supplies it

### Requirement: The system SHALL provide focused regression evidence

The OMR test suite SHALL cover the profile solver contract, persisted pair and answer-key alignment, seed idempotency, preview response, and profile capture route visibility without relying on generated database IDs.

#### Scenario: Focused profile tests detect geometry drift

- **WHEN** the focused OMR layout and printing tests run
- **THEN** they fail if the profile changes page count, bubble dimensions, column count, question count, margins, or printed-name overlay

#### Scenario: Focused profile tests detect seed drift

- **WHEN** the profile seeder test runs twice
- **THEN** it passes only when one active pair and exactly 150 aligned authored questions remain

#### Scenario: Capture route exposes focused default state

- **WHEN** an authenticated guidance counselor visits the profile grading OMR route after the approved default seed
- **THEN** the response exposes exactly one focused needs-review sheet bound to the seeded whole examinee

#### Scenario: Opt-in capture route exposes workflow states

- **WHEN** an authenticated guidance counselor visits the profile grading OMR route after the explicit multi-state fixture seed
- **THEN** the response exposes needs-review, incomplete-page, and retryable failure states without changing the focused default dataset

### Requirement: NEXIAM SHALL provide conservative camera capture guidance

The capture page SHALL own camera permission, device selection, live preview, sheet framing guidance, page progress, and upload fallback. OMRChecker SHALL remain an image-analysis provider and SHALL NOT be treated as a live camera overlay or focus/readiness service. The UI SHALL distinguish camera unavailable, requesting, preflight, ready-to-capture, uploading, and page-progress states. `Ready to capture` SHALL mean only that configured frame and geometry gates passed; it SHALL NOT imply recognition confidence or production qualification.

#### Scenario: Camera readiness is visible only after preflight

- **WHEN** the staff member opens a capture batch with camera access available and a frame satisfies the configured marker/page gates
- **THEN** the page shows a live stream, a printable-sheet framing guide, the current page number, and a `Ready to capture` state before enabling capture

#### Scenario: Upload remains available without camera access

- **WHEN** camera permission is denied or no camera is available
- **THEN** the page shows the unavailable state and keeps image-file upload available

#### Scenario: Preflight failure is actionable

- **WHEN** an upload or camera frame fails decode, registration, perspective, framing, or calibration-quality guidance
- **THEN** the page shows a typed failure such as `marker_missing`, `sheet_partial`, `geometry_rejected`, `frame_too_blurry`, or `lighting_glare`, gives a corrective instruction, and does not submit that frame as a recognized scan

### Requirement: The profile SHALL publish a versioned capture-policy contract

Each camera-capable profile version SHALL derive a `nexiam.omr.capture-policy.v1` contract from the server-owned solver and immutable native bundle. The contract SHALL include the capture mode, normalized page dimensions, registration-fiducial family `nexiam.omr.fiducial.v1`, corner-quadrant placement, marker size/quiet zone, marker-to-sheet width ratio, `required_marker_count=4`, solver version, native bundle hash, review-manifest hash, engine/patch identity, and an explicit unqualified calibration status. The first production profile SHALL not use a three-of-four marker fallback. It SHALL reference native geometry without duplicating field blocks or answer-key semantics. Marker-bearing profiles SHALL be new immutable template versions whose fiducial asset is hash-covered by the native bundle and materialized beside `template.json` for activation and inference.

#### Scenario: Capture policy matches persisted geometry

- **WHEN** staff opens a camera-capable profile version
- **THEN** the served policy and print output use the same solver-derived page dimensions, marker coordinates, bundle hash, and manifest hash, and no client-only bubble layout exists

#### Scenario: Existing profile versions remain immutable

- **WHEN** a marker or capture-policy change is introduced
- **THEN** activation creates a new template version and leaves every existing native bundle, manifest, print artifact, and active pair unchanged

### Requirement: Image capture SHALL normalize evidence before recognition through the engine marker preprocessor

The upload and camera paths SHALL decode EXIF orientation and validate input dimensions server-side, run the engine's marker-based registration preprocessor (`CropOnMarkers`) on marker-bearing profile versions, reject missing-markers, wrong-form, unsafe-perspective, and incomplete-page inputs with typed failures, and produce a normalized image at the persisted target dimensions before recognition. The original upload SHALL remain retained as forensic evidence. Browser-side checks SHALL be advisory quality hints only and SHALL NOT implement registration geometry or normalization.

#### Scenario: Valid phone photo reaches recognition in native coordinates

- **WHEN** a photo shows the printed fiducial matched in all four corner quadrants and the engine registration gates pass
- **THEN** the system stores the original, the worker registers the page from the four fiducial matches, produces a normalized page at the profile dimensions, and runs recognition on it

#### Scenario: Unsafe photo never creates false proposals

- **WHEN** a photo fails decode, fiducial count/match, perspective, page coverage, or normalized-dimension validation
- **THEN** the system records a typed preflight/misalignment issue, retains evidence for review, and suppresses answer and score proposals

### Requirement: Capture diagnostics SHALL remain unqualified until physical evidence passes

The system SHALL expose bounded marker, homography, blur/lighting, per-bubble, and threshold diagnostics to authorized review without treating diagnostics as recognition truth. Thresholds and confidence mapping SHALL remain unqualified until the protected labeled regression and physical acceptance matrix pass.

#### Scenario: Diagnostic output explains but does not accept

- **WHEN** the worker returns raw bubble values or threshold diagnostics for a real photo

- **THEN** review shows the diagnostics with `Unqualified` confidence and leaves staff confirmation subject to required issue resolution

#### Scenario: Qualification requires exact or abstained output

- **WHEN** the protected labeled photo or a physical-matrix case is processed
- **THEN** qualification succeeds only on exact declared answers and identity or an explicit abstention, never on a plausible but false proposal

### Requirement: The template builder SHALL expose constrained geometry inspection and draft adjustment

The template builder SHALL preserve the solver as geometry authority while exposing an inspectable page preview and controlled draft adjustment. It SHALL support block-level constrained dragging plus exact numeric origin/pitch inputs, but SHALL NOT permit individual bubble, label, or answer-option dragging. Every change SHALL re-run margin, overlap, identity/header collision, page-bound, and manifest diagnostics before save or activation.

#### Scenario: Block adjustment remains solver-safe

- **WHEN** a staff member drags an editable field block in Adjust mode
- **THEN** only its solver origin changes, movement snaps to the documented grid, the preview shows delta/bounds/labels, and bubble pitch, labels, options, and answer semantics remain unchanged

#### Scenario: Invalid geometry blocks persistence

- **WHEN** a block is dragged outside the printable margin, across another block, or into the reserved identity/header zone
- **THEN** the builder shows the exact affected block/region, marks the draft invalid, and blocks save/activation until the geometry is corrected or reset

#### Scenario: Bubble inspection is read-only

- **WHEN** staff selects a rendered bubble in the builder preview
- **THEN** the UI identifies its page, block, question, option, pixel rectangle, and normalized region without allowing the bubble to move independently

### Requirement: Capture SHALL use an automatic four-corner go/no-go gate

The first marker-bearing profile SHALL require the fiducial matched in all four corner quadrants with geometrically consistent page coverage before submission. The ordinary operator path SHALL provide retake/replace guidance, not manual page cropping, quadrilateral dragging, or per-bubble alignment.

#### Scenario: Four-corner validation permits submission

- **WHEN** a submitted frame or upload matches all four corner quadrants and passes the server geometry/quality recheck
- **THEN** the UI shows a green four-corner outline and `Validated`, the worker produces the normalized native-size page, retains the original, and permits submission

#### Scenario: Missing or wrong corner is a hard no-go

- **WHEN** a frame or upload is missing a marker, shows the wrong form, or fails page coverage/perspective validation
- **THEN** the UI shows the specific marker/geometry failure, keeps the item out of recognition, and offers retake/replace without a manual crop editor

#### Scenario: Review overlays remain immutable

- **WHEN** a normalized sheet reaches review
- **THEN** the UI displays the read-only marker quadrilateral and manifest-derived item/name crops; it does not let an operator drag bubbles or silently rewrite geometry

### Requirement: The system SHALL expose a read-only builder test-capture diagnostic

Authorized staff SHALL be able to run a `Test capture` from the template builder against a draft or activated marker-bearing version by uploading a photo of the printed sheet. The diagnostic SHALL run the marker registration pipeline in read-only mode and show the normalized page, the engine layout overlay, manifest-derived bubble circles/labels, per-bubble mean values, and field-block shift gates. It SHALL NOT mutate activation, pairs, answer keys, or scores, and it SHALL NOT be offered to ordinary operators as a capture path.

#### Scenario: Test capture proves printed geometry

- **WHEN** staff uploads a photo of the printed marker-bearing sheet in the builder and runs `Test capture`
- **THEN** the view shows the normalized page with the engine layout overlay and manifest circles aligned, per-bubble means for unmarked bubbles near background levels, and pass/fail chips for marker count, registration, dimensions, and shifts, without writing any template or score state

#### Scenario: Test capture failure stays diagnostic

- **WHEN** the test photo fails marker registration or alignment gates
- **THEN** the view shows the typed failure and evidence, and no draft, activation, answer key, or score data changes

### Requirement: Review SHALL expose provider state and evidence issues

Each detected item SHALL expose its question number, evidence crop, detected option, provider state, confidence qualification status, and associated required issues. Real-engine null confidence SHALL render as `Unqualified`, not `0%`; the review surface SHALL preserve the distinction between marked, blank, ambiguous, and unreadable.

#### Scenario: Ambiguous mark is explicit

- **WHEN** the provider returns an ambiguous item
- **THEN** the review row shows `Ambiguous`, no selected answer, the evidence crop, and the required issue

#### Scenario: Real confidence is not yet qualified

- **WHEN** the real provider returns a null item or sheet confidence
- **THEN** the review shows `Unqualified` and does not imply a measured zero confidence

### Requirement: Confirmation SHALL protect unresolved required issues

The system SHALL NOT silently commit a reviewed batch while required OMR issues remain unresolved. Staff SHALL explicitly resolve the issue, abstain the sheet, or provide an approved manual score adjustment before confirmation. Roster binding SHALL remain mandatory for non-abstained sheets.

#### Scenario: Required issue blocks silent confirmation

- **WHEN** a reviewed sheet still has an open required blank, ambiguous, unreadable, or identity issue
- **THEN** batch confirmation refuses with the affected sheet/question identifiers and leaves score records unchanged

### Requirement: Physical acceptance SHALL cover capture quality

The readiness evidence SHALL include real printed-sheet scans through the normalized capture pipeline for baseline marks, blank input, double marks, erasures, rotation, uneven light/shadow, folded corners, wrong-paper input, blur, glare, and partial-page cases. Each case SHALL record the original and normalized evidence, preflight outcome, gallery state, review state, evidence alignment, detected identity, per-item state, confidence qualification, and final staff action. Synthetic previews and fake-provider output SHALL never satisfy this gate.

#### Scenario: Baseline physical scan reaches review

- **WHEN** a correctly printed and shaded marker-bearing profile sheet is uploaded through the active pair
- **THEN** the original is retained, the normalized sheet reaches `Needs review`, identity and item crops align to the served normalized evidence image, and the proposal matches the physical marks before staff confirmation or is explicitly abstained

#### Scenario: Unsafe physical scan fails closed

- **WHEN** a physical case has missing markers, unsafe perspective, wrong paper, unresolved blur/glare, or alignment that crosses an option pitch
- **THEN** the system records a typed preflight or `sheet_misaligned` issue, suppresses answer/score proposals, and requires retake, abstention, or approved manual adjustment
