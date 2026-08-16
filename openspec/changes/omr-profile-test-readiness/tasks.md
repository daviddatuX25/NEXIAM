## 1. Catalog and solver contract

- [x] 1.1 Extend `ReferenceCatalogSeeder` with active 25-item profile areas `LOGIC`, `SPATIAL`, `MEMORY`, and `LANGUAGE` while preserving `VERBAL` and `QUANT` behavior.
- [x] 1.2 Add a focused solver regression for the six-column profile blueprint: one long-bond page, six 25-question blocks, 150 labels, compact 40×40 geometry, and bounded margins.

## 2. Durable template and answer key

- [x] 2.1 Add an idempotent `OmrDemoSeeder` profile method that saves the six-column blueprint through `SaveAuthoredTemplateDraft` with identity and printed-name overlay metadata.
- [x] 2.2 Generate and save the six-area 150-question authored booklet with contiguous per-area positions and deterministic A/B/C/D keys.
- [x] 2.3 Activate the profile pair through existing OMR actions and assert active rows are never rewritten on re-seed.

## 3. Capture fixtures and regression coverage

- [x] 3.1 Extend `OmrDemoBatchSeeder` with a stable, idempotent profile capture batch covering needs-review, pending-pages, and failed-terminal states.
- [x] 3.2 Add feature coverage for profile persistence, one active pair, 150 aligned questions, second-run idempotency, preview response, and grading-route state visibility.
- [x] 3.3 Add print/render assertions for the compact profile name line, identity fields, all six blocks, and paper/margin constraints.

## 4. Verification and handoff

- [x] 4.1 Reset and reseed the development database with the approved commands; record profile pair and capture counts.
- [x] 4.2 Render and rasterize the profile preview/print PDF at 100%; inspect the full page for clipping, overlap, identity readability, footer/calibration separation, and right margin.
- [x] 4.3 Run focused OMR suites and strict OpenSpec validation; update the new change `HANDOFF.md`, master checkpoint, and developer seed instructions with observed evidence.

## 5. Manual name fallback

- [x] 5.1 Update the print contract so every rendered page has a safe manual name field without changing native OMR geometry.
- [x] 5.2 Render and test the fallback on compact profile, v4, and v5 answer-sheet pages.
- [x] 5.3 Update handoff evidence and verify the focused rendering suite.

## 6. EDQ header redesign

- [x] 6.1 Replace profile native birthdate identity with a render-only manual birthdate field and preserve applicant-number geometry.
- [x] 6.2 Add extensible profile header overlay fields for exam information, instructions, manual birthdate/name, and optional calibration visibility.
- [x] 6.3 Remove profile footer instructions and calibration copy while keeping v4/v5 legacy overlays unchanged.
- [x] 6.4 Add regression assertions for header content, no native birthdate fields, no footer/calibration copy, and non-overlap with answer geometry.
- [x] 6.5 Re-render the profile PDF and visually inspect the full page plus top and bottom crops.
- [x] 6.6 Reserve the native identity footprint before placing configurable EDQ columns, keeping builder preview and PDF output aligned.

## 7. Physical capture readiness

- [x] 7.1 Keep camera framing/readiness guidance in NEXIAM; document that OMRChecker analyzes completed images only.
- [x] 7.2 Add camera preflight states: permission/device unavailable, requesting, ready-to-capture, uploading, and page progress; retain upload fallback.
- [x] 7.3 Expose provider item states and required issues in review rows; show explicit marked/blank/ambiguous/unreadable labels.
- [x] 7.4 Render null real-engine confidence as `Unqualified` at sheet, area, identity, and item levels.
- [x] 7.5 Verify served aligned-image dimensions and crop coordinate frame; add targeted identity/item crop regression coverage.
- [x] 7.6 Block batch confirmation for unresolved required OMR issues unless staff resolves, abstains, or applies an approved manual score adjustment.
- [ ] 7.7 Run the physical acceptance matrix: baseline, blank, double mark, erase, rotation, shadow, fold, wrong paper; record outcomes in HANDOFF.md.
- [x] 7.8 Browser-verify the preflight, upload, review evidence, issue, and confirmation states with targeted checks only.
