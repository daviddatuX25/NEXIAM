## 1. Catalog and solver contract

- [x] 1.1 Extend `ReferenceCatalogSeeder` with active 25-item profile areas `LOGIC`, `SPATIAL`, `MEMORY`, and `LANGUAGE` while preserving `VERBAL` and `QUANT` behavior.
- [x] 1.2 Add a focused solver regression for the six-column profile blueprint: one long-bond page, six 25-question blocks, 150 labels, compact 40×40 geometry, and bounded margins.

## 2. Durable template and answer key

- [x] 2.1 Add an idempotent `OmrDemoSeeder` profile method that saves the six-column blueprint through `SaveAuthoredTemplateDraft` with identity and printed-name overlay metadata.
- [x] 2.2 Generate and save the six-area 150-question authored booklet with contiguous per-area positions and deterministic A/B/C/D keys.
- [x] 2.3 Activate the profile pair through existing OMR actions and assert active rows are never rewritten on re-seed.

## 3. Capture fixtures and regression coverage

- [x] 3.1 Extend `OmrDemoBatchSeeder` with a stable, idempotent focused profile capture batch; keep needs-review, pending-pages, and failed-terminal fixtures in the opt-in `OmrDemoMultiStateBatchSeeder`.
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
- [x] 7.5 Verify served clean aligned-image dimensions and the real per-item/name crop coordinate frame against a labeled phone photo; the 2026-08-18 capture disproved the earlier synthetic crop evidence. Final browser evidence shows the unannotated page, a row crop with label plus A/B/C/D bubbles, and the handwritten-name crop.
- [x] 7.6 Block batch confirmation for unresolved required OMR issues unless staff resolves, abstains, or applies an approved manual score adjustment.
- [ ] 7.7 Run the physical acceptance matrix: baseline, blank, double mark, erase, rotation, shadow, fold, wrong paper; record outcomes in HANDOFF.md.
- [x] 7.8 Browser-verify the preflight, upload, review evidence, issue, and confirmation states with targeted checks only.

## 8. Focused near-production dataset + capture context fix (founder decision 2026-08-17)

- [x] 8.1 Default seeded OMR dataset is production-like: exactly ONE active pair (`SYN-OMR-PROFILE-150` + `SYN-OMR-PROFILE-KEY`) and ONE capture batch with ONE needs-review sheet bound to one whole examinee; pending/failed fixture states are opt-in via `OmrDemoMultiStateBatchSeeder`; the full v1–v5 catalog remains in `OmrDemoSeeder` for parity/state tests.
- [x] 8.2 Replace insecure direct `crypto.randomUUID()` calls (Capture, Monitoring, GradingScoreInput, nexi-transport) with `newIdempotencyKey()` so OMR capture and Nexi/score surfaces work on non-secure contexts (LAN IP).
- [x] 8.3 Browser-verify OMR hub, template list, and capture screen end to end on the focused dataset; record evidence in HANDOFF.
- [x] 8.4 Fix single-page upload server-side: `UploadOmrCaptureSheet` now fills the asset mirror at sheet insert (satisfying `omr_capture_sheets_asset_check` for `expected_page_count = 1`); browser-verified upload → sheet → queue → provider transition with zero JS errors. Regression: `single-page profile upload carries the asset mirror and queues analysis`.
- [x] 8.5 Real-scan upload size fix: PHP image defaulted to `upload_max_filesize=2M` (rejected realistic 3–6MB scans before Laravel validation); image now ships `25M/32M` limits via `docker/php/conf.d/uploads.ini`, the request cap is `max:25600`, and the Capture upload `onError` surfaces the real field error instead of the generic toast. Browser-verified: 5.2MB JPEG upload → sheet created → analysis.

## 9. Real-photo correctness recovery (blocking)

- [ ] 9.1 Make queue execution self-healing across `foundation:reset`: add a Compose restart policy/health recovery and prove an uploaded job cannot remain silently `pending`.
- [x] 9.2 Keep an open review synchronized with refreshed sheet props so `queued → processing → needs_review|failed` replaces the stale selected object without a manual reload. Browser proof observed the same selected sheet transition from `PROCESSING` to `NEEDS REVIEW`.
- [x] 9.3 Stop labeling OMRChecker `final_marked` as clean aligned evidence; return/store the unannotated aligned raster, then render NEXIAM-owned option overlays from typed geometry.
- [x] 9.4 Project `page_no` plus A/B/C/D option regions to review; draw green correct, red incorrect, and amber ambiguous/unreadable rings on the full aligned sheet, with a legend and no fabricated confidence.
- [x] 9.5 Use the correct aligned page URL for each item and expose a padded row crop containing the row label and all four bubbles; lazy-load crops and prove coordinates against the labeled photo.
- [x] 9.6 Fail closed on unsafe field-block shifts. A shift large enough to cross a bubble/option pitch must emit `sheet_misaligned` and suppress answer/score proposals rather than persist false marks.
- [ ] 9.7 Re-scoped 2026-08-18: the labeled 3000×4000 photo has no fiducials, so it cannot validate camera registration. Do NOT repair the Hough `align_camera_page` fallback for camera input — the marker-bearing version replaces that path (10.2–10.4). This task now only guarantees the marker-less scan pipeline (`CropPage`) keeps working for flat scans, and that the labeled photo continues to guard scan-path review evidence/crops.
- [ ] 9.8 Establish the protected labeled regression contract for the marker-less scan pipeline sheet: answers `1=C, 2=B, 3=C, 4=D, 5=D`, items `6–150=blank`, applicant number `2000600002`; require exact output or an explicit abstention—never false answers. The marker-bearing camera pipeline gets its own labeled contract in 10.9/10.10.
- [ ] 9.9 Instrument provider raw per-bubble response values and local/global thresholds, then tune or replace OMRChecker thresholding against the physical matrix; do not qualify from this one sample.
- [x] 9.10 Project the render-only `NAME (PRINT)` box as review geometry from the shared renderer/solver and show its crop; do not ask OMRChecker to recognize handwriting. Browser evidence shows the labeled handwritten-name crop with the explicit crop-only warning.
- [ ] 9.11 Browser-prove the full operator loop: upload, visible processing progress, clean aligned evidence, overlays, correct item/name crops, declared detection result, and fail-closed confirmation.

## 10. Engine-reuse registration and capture preflight (revised 2026-08-18 per `docs/omrchecker-capture-capability-research.md`)

**Founder directive (2026-08-19):** the quadrilateral four-corner markings are STRICTLY REQUIRED — never accept a sheet without all four (`required_marker_count=4`, no three-of-four fallback, fail-closed typed rejection). The fiducial also doubles as version-unique template identification (pattern derived from template code + version): a wrong form must fail the marker match loudly instead of registering.

- [x] 10.1 Freeze `nexiam.omr.capture-policy.v1`: capture mode, target dimensions, fiducial family `nexiam.omr.fiducial.v1`, corner-quadrant placement, marker size/quiet zone, marker-to-sheet width ratio, `required_marker_count=4`, solver/bundle/manifest/engine hashes, and `calibration_status=unqualified`. Evidence: `config/omr/capture-policy.php` + `OmrCapturePolicy` (derive/forVersion/markerMetadata/frameBudget/assertQuadrantPlacement) + `OmrCapturePolicyTest` (6 passed, 41 assertions).
- [x] 10.2 Extend the solver/print renderer with a repeated rotation-asymmetric corner fiducial, version-unique per template version (pattern derived from template code+version hash so a wrong form fails marker match). FRAME ANCHORING (critical): OMRChecker warps by the four marker centers and then resizes to `pageDimensions`, so `pageDimensions` MUST equal the fiducial-center rectangle and ALL block origins/identity/name/header geometry MUST be re-derived in that frame; the physical paper is the frame plus an inset ring carrying the fiducials. Solver must fit frame + 2×inset within fixed long-bond 2060×3144 (reduce inter-column gaps before shrinking the marker below matchable size; markers must keep two fiducials in the top third and two in the bottom two-thirds). Evidence: `OmrTemplateLayoutSolver::markerLayout`/`buildPages` (frame anchoring, quadrant assertion), hardened `OmrFiducial` (multi-radius 2-bit lattice + version-unique dashed ring; aligned TM_CCOEFF_NORMED cross-seed scores dropped from 0.59–0.91 to ≤ 0.34 with self-margin ≥ 0.30 under the engine's exact preprocessing), `OmrTemplateLayoutSolverMarkerTest` (5 passed) + `OmrFiducialTest` (9 passed) + `OmrMarkerlessContractTest` (6 passed — marker-bearing strictly opt-in).
- [x] 10.3 Carry the fiducial asset through the bundle contract: `SaveAuthoredTemplateDraft` staging, `NativeBundle::load` file list + SHA-256 + `bundleHash`, activation payload, `OmrPinnedSnapshot` carrying the asset bytes to the worker, and worker temp-dir materialization next to `template.json`; prove a bundle missing the asset fails validation and that `exit(31)` cannot occur. Evidence: `OmrMarkerBundleTest` (5 passed, 24 assertions).
- [x] 10.4 Enable `CropOnMarkers` on the marker-bearing version only (marker-only chain; scan profiles keep `CropPage`), DISABLE the Hough `align_camera_page` fallback for marker-bearing versions (a marker failure must map to a typed state, never degrade into the brittle fallback that misreads), and map chain failure to typed states (`marker_missing`, `marker_mismatch`, `sheet_partial`, `preprocessing_failed`) with corrective copy; no three-of-four fallback. Include an orientation-hint message for 180°/90° shots (rotation-asymmetric pattern fails match when the sheet is upside down or sideways). Evidence: `test_marker_pipeline.py` (17 checks — Hough forbidden, typed states, missing asset → `marker_asset_missing`, no exit(31), aligned output = fiducial-center frame).
- [x] 10.5 Add a worker layout-verification capability (diagnostic flag or sibling endpoint) returning `draw_template_layout(aligned, shifted=True, draw_qvals=True)` as PNG plus per-bubble means and `field_block_shifts`, reusing the existing HMAC/child-process envelope; it SHALL NOT alter recognition output. Evidence: `docker/inference/verify.py` + `/v1/layout-verification`, `OmrCheckerDetection::verifyLayout`, `OmrLayoutVerificationResult`, `test_layout_verification.py` (all checks pass).
- [x] 10.6 Add the builder `Test capture` button: upload a photo of the printed sheet, run the marker pipeline in diagnostic mode, and show the normalized page, engine layout overlay, NEXIAM manifest circles/labels, per-bubble means, and pass/fail gate chips; strictly read-only (no activation/pair/key/score mutation); record evidence in `HANDOFF.md`. Evidence: `TestCapturePanel.svelte` + `BlueprintBuilder`/`Show` integration; live HTTP 200 `outcome: succeeded` with `aligned_url`, `overlay_url`, four quadrant matches (0.652/0.651/0.653/0.651) on the real endpoint.
- [x] 10.7 Keep the capture page free of registration CV: render typed server preflight results with retake guidance, retain camera permission/device/framing/upload fallback, and optionally add a pure-JS advisory quality nudge (canvas brightness/overexposure/blur variance) that never gates submission; no OpenCV.js dependency. Evidence: `Capture.svelte` preflight HUD (`Checking`/`Validated`/`Rejected`/error, gate chips, `Unqualified`, retake/replace, upload fallback intact, no browser CV); live-verified scan `not_applicable` → normal upload and marker typed rejection over real HTTP.
- [x] 10.8 Expose bounded diagnostics to authorized review: per-quadrant marker match scores, registration scale, per-bubble means, threshold keys; confidence stays `Unqualified` and unsafe shifts keep suppressing proposals. Evidence: worker `marker_matches`/`field_block_shifts`/`bubble_means` projected into the Test capture panel (bounded) and provider diagnostics; `OmrSheetMisalignmentTest` proves unsafe shifts emit `sheet_misaligned` and suppress proposals.
- [x] 10.9 (harness/fixture-generation portion) Create a labeled fixture for the marker-bearing sheet (the existing labeled photo has no fiducials and cannot validate registration) plus fixtures for missing-marker, wrong-form (different fiducial pattern), rotated (90°/180°), partial, perspective, EXIF, blur, dark, glare, and too-small inputs; the focused default seed stays on the marker-less pair per the founder decision until 10.10/10.11 pass. Evidence: `generate_fixture.py` marker-seed overrides + `test_marker_pipeline.py`/`test_preflight.py` fixture coverage (missing, partial, marker-less, wrong-seed, rotations at pattern level). Remaining: swap the focused default seed to the marker-bearing active pair after physical acceptance.
- [ ] 10.10 Execute the protected labeled regression through the marker pipeline (exact declared answers/identity or explicit abstention); false answer proposals fail the task.
- [ ] 10.11 Run the physical acceptance matrix against the marker pipeline, explicitly including sheet rotation (90°/180° — plain fiducials carry no encoded orientation), fold/glare over a marker corner, and uneven light; record every gallery/review/evidence/identity/item/staff outcome in `HANDOFF.md`.
- [ ] 10.12 Re-run the complete operator loop and queue recovery proof on the focused one-sheet dataset, then update the master checkpoint and developer handoff with the exact implementation/qualification status.
- [ ] 10.13 Add TemplateEditor `Inspect geometry` and draft-only `Adjust geometry` modes; activated/locked versions remain read-only.
- [ ] 10.14 Add block-level drag/origin handles only; convert SVG coordinates to native pixels, snap to 4px, preserve pitch/labels/answer semantics, and prohibit individual bubble/label dragging.
- [ ] 10.15 Add keyboard movement (1px, Shift+Arrow 10px), Escape cancel, Reset-to-solver-origin, visible `Δx/Δy`, ghost bounds, and origin crosshair behavior.
- [ ] 10.16 Add preview toggles and read-only bubble inspection for margins, block bounds, bubble centers, labels/options, identity lock, marker corners, review crops, rulers, manifest label, pixel rectangle, and normalized region.
- [ ] 10.17 Add an exact validation panel and persistence gate: margin/overlap/header collision/page-bound/manifest errors block save and activation; warnings remain visible and recorded.
- [x] 10.18 Add the capture HUD with `Checking`, `Rejected`, `Validated`, `Uploading`, and `Needs review` states; server preflight drives marker/coverage results, and retake/replace guidance is exposed without manual cropping. Evidence: `Capture.svelte` (preflight gate before every file/camera-frame/page-replace upload; typed HUD states; shutter gated; `Ready to check` wording; no crop/drag UI).
- [x] 10.19 Keep review marker quadrilateral, normalized page, item crops, and name crop read-only; prove no ordinary operator path can drag a page, bubble, or crop to rewrite recognition geometry. Evidence: review surface renders read-only overlays/crops (`OmrRegionCrop` display-only); no drag handlers exist in the ordinary operator path.
- [ ] 10.20 Add builder interaction browser coverage for drag snap, keyboard deltas, cancel/reset, numeric parity, invalid collision/out-of-margin blocking, locked mode, and bubble-inspection non-draggability.
- [ ] 10.21 Add builder-to-PDF parity coverage comparing page dimensions, marker positions, block/bubble bounds, labels, identity/name/birthdate overlays, margins, hashes, and raster output.
- [ ] 10.22 Add capture fixture/browser coverage for all-four pass, one-missing, wrong-form, rotated, partial, perspective, EXIF, blur, glare, dark, wrong-paper, and insufficient-resolution no-go states.
- [ ] 10.23 Execute the human acceptance script: builder overlays/drag/collision/PDF parity plus Test capture, valid four-marker upload through review, and missing-marker/wrong-paper/partial rejection before recognition; attach evidence to `HANDOFF.md`.
