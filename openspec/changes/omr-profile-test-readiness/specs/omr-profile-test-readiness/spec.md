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

The development seed SHALL expose a profile capture batch that exercises the needs-review, incomplete-page/resume, and retryable terminal-failure states using existing OMR capture models and action boundaries. The fixture SHALL identify itself through a stable natural marker and SHALL be idempotent.

#### Scenario: First profile seed creates the capture fixtures

- **WHEN** the developer runs the approved grading demo seeder after a reset
- **THEN** the profile pair and its capture batch are available from the grading OMR route with review, pending-page, and failed-terminal examples

#### Scenario: Re-running the profile seed is safe

- **WHEN** the developer runs the same seeder again
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

#### Scenario: Capture route exposes all seeded states

- **WHEN** an authenticated guidance counselor visits the profile grading OMR route
- **THEN** the response includes the seeded review, incomplete-page, and retryable failure states

### Requirement: NEXIAM SHALL provide conservative camera capture guidance

The capture page SHALL own camera permission, device selection, live preview, sheet framing guidance, page progress, and upload fallback. OMRChecker SHALL remain an image-analysis provider and SHALL NOT be treated as a live camera overlay or focus/readiness service. The UI SHALL distinguish camera unavailable, requesting, ready-to-capture, and uploading states without claiming that OMR quality has been proven.

#### Scenario: Camera readiness is visible before capture

- **WHEN** the staff member opens a capture batch with camera access available
- **THEN** the page shows a live stream, a printable-sheet framing guide, the current page number, and a `Ready to capture` state before enabling capture

#### Scenario: Upload remains available without camera access

- **WHEN** camera permission is denied or no camera is available
- **THEN** the page shows the unavailable state and keeps image-file upload available

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

The readiness evidence SHALL include real printed-sheet scans for baseline marks, blank input, double marks, erasures, rotation, uneven light, folded corners, and wrong-paper input. Each case SHALL record the gallery state, review state, evidence alignment, detected identity, per-item state, and final staff action.

#### Scenario: Baseline physical scan reaches review

- **WHEN** a correctly printed and shaded profile sheet is uploaded through the active pair
- **THEN** the sheet reaches `Needs review`, identity and item crops align to the served evidence image, and the proposal matches the physical marks before staff confirmation
