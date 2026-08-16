## Why

The requested one-page 150-item answer sheet is geometrically implemented and visually verified as a draft preview, but it is not yet a durable active template with a matching answer key and repeatable capture data. A developer needs a seeded, paired profile that can be printed, scanned, reviewed, and tested without inventing fixtures.

## What Changes

- Add a durable six-column, one-page long-bond profile template using the server-owned layout solver.
- Persist compact 40×40 bubble geometry, 52px option pitch, one native applicant-number grid, a render-only manual birthdate box, and a printed name line.
- Split the printed page into an extensible EDQ/header zone and a lower aptitude-answer zone; header blocks may independently expose identity, instructions, and exam information.
- Omit instructional footer text and calibration copy from the printed artifact; the bottom margin remains visually blank.
- Seed a matching 150-question authored booklet and activate the template/booklet pair.
- Seed a profile-specific capture batch with deterministic review, incomplete-page, and retryable failure states.
- Add focused regression coverage for persisted geometry, answer-key alignment, idempotent seeding, header rendering, and capture visibility.
- Verify the generated PDF and captured profile page visually on every page before handoff.

## Capabilities

### New Capabilities

- `omr-profile-test-readiness`: Durable one-page 150-item OMR profile, matching answer key, developer-ready capture states, and visual verification evidence.

### Modified Capabilities

- `omr-configuration-printing`: shared renderer supports an extensible EDQ/header zone, manual birthdate/name boxes, and no instructional footer text.

## Impact

- `system/database/seeders/OmrDemoSeeder.php` and `OmrDemoBatchSeeder.php` gain idempotent profile fixtures.
- OMR template/booklet activation and capture projections gain regression coverage for the profile pair.
- The setup template list, print preview, and grading capture surfaces gain a durable profile dataset without changing existing v1–v5 demo pairs.
- No provider seam, recognition contract, authorization boundary, or production OMR engine behavior changes.
