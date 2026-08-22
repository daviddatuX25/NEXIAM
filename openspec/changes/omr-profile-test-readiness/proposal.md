## Why

The requested one-page 150-item answer sheet is geometrically implemented and visually verified as a draft preview, but it is not yet a durable active template with a matching answer key and repeatable capture data. The current camera layer reports device readiness, while the supplied real phone photo still demonstrates that page alignment and recognition are not qualified. A developer needs a seeded, paired profile plus a no-license capture-preflight contract that can print, normalize, scan, review, and qualify evidence without inventing fixtures or purchasing a commercial scanner SDK.

## What Changes

- Add a durable six-column, one-page long-bond profile template using the server-owned layout solver.
- Persist compact 40×40 bubble geometry, 52px option pitch, one native applicant-number grid, a render-only manual birthdate box, and a printed name line.
- Split the printed page into an extensible EDQ/header zone and a lower aptitude-answer zone; header blocks may independently expose identity, instructions, and exam information.
- Omit instructional footer text and calibration copy from the printed artifact; the bottom margin remains visually blank.
- Seed a matching 150-question authored booklet and activate the template/booklet pair.
- Seed a focused profile-specific capture batch with exactly one needs-review sheet by default; keep deterministic incomplete-page and retryable failure states in an explicit opt-in fixture seeder.
- Add focused regression coverage for persisted geometry, answer-key alignment, idempotent seeding, header rendering, and capture visibility.
- Verify the generated PDF and captured profile page visually on every page before handoff.
- Add a physical-readiness gate: NEXIAM owns conservative camera framing and capture states; OMRChecker remains a completed-image analysis provider.
- Reuse the pinned engine's `CropOnMarkers` preprocessor for no-license registration: print solver-rendered corner fiducials, materialize the fiducial asset through the bundle contract, and normalize server-side; the browser keeps only camera/upload UX plus optional advisory quality nudges (no OpenCV.js, no paid SDK).
- Derive a versioned capture-policy contract from the solver output, including the four required corner-quadrant fiducial matches, normalized target dimensions, bundle/manifest hashes, typed failure states, and an unqualified calibration status.
- Extend the template builder with draft-only constrained block dragging, numeric inspection, collision/margin diagnostics, read-only bubble/manifest inspection, and builder-to-PDF parity proof; never allow freehand per-bubble geometry edits.
- Retain the original upload and let the worker's marker preprocessor register and normalize the page before recognition. Capture is automatic four-corner go/no-go; ordinary operators do not manually crop, drag page corners, or align bubbles.
- Make provider states, evidence crops, required issues, and unqualified confidence explicit in review before any batch commit.
- Prove crop coordinate parity against aligned evidence, protect a labeled real-photo regression, and execute a recorded physical acceptance matrix before production use.

## Capabilities

### New Capabilities

- `omr-profile-test-readiness`: Durable one-page 150-item OMR profile, matching answer key, developer-ready capture states, and visual verification evidence.

### Modified Capabilities

- `omr-configuration-printing`: shared renderer supports an extensible EDQ/header zone, manual birthdate/name boxes, and no instructional footer text.

## Impact

- `system/database/seeders/OmrDemoSeeder.php` and `OmrDemoBatchSeeder.php` gain idempotent profile fixtures.
- OMR template/booklet activation and capture projections gain regression coverage for the profile pair.
- The setup template list, print preview, and grading capture surfaces gain a durable profile dataset; the default reset is the focused one-pair/one-booklet/one-sheet state, while the full v1–v5 catalog remains available for opt-in parity and state tests.
- Capture review gains explicit physical-evidence and issue-resolution gates; the worker contract remains proposal-only and confidence remains unqualified until calibration evidence exists.
- Browser capture gains typed preflight states driven by server registration results plus an optional advisory pure-JS quality nudge; the worker performs marker registration and normalization and preserves original plus normalized evidence. No commercial SDK, OpenCV.js dependency, or external recognition service is added.
- Add a read-only builder `Test capture` diagnostic backed by a worker layout-verification seam returning the engine's template-layout overlay over the normalized page.
- The native OMR template remains the detection-geometry authority. Any marker-bearing profile is a new immutable template version whose fiducial asset is hash-covered by the native bundle and materialized beside `template.json` for activation and inference.
- No provider seam, authorization boundary, or production OMR acceptance behavior changes; recognition remains fail-closed and unqualified until labeled and physical evidence passes.
