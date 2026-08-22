## Context

The OMR stack already has a server-owned `OmrTemplateLayoutSolver` that turns a semantic blueprint into canonical OMRChecker `fieldBlocks`, and `SaveAuthoredTemplateDraft` persists the solved bundle as an immutable draft. The focused one-page compact profile, matching key, and one-sheet demo dataset now exist and are browser-verified at the transport/review level; real-phone recognition remains unqualified because the current camera layer lacks marker registration and deterministic normalization. The profile must use long-bond geometry without scaling, preserve the provider-native bundle seam, and remain safe to re-seed.

## Goals / Non-Goals

**Goals:**

- Add one active, paired compact profile using six columns of 25 questions each.
- Keep the applicant number as the only native identity grid; render birthdate and printed name as manual header fields.
- Use one solver blueprint for preview, persisted bundle, and printed PDF.
- Provide deterministic answer keys and capture states for review, resume, and retry flows.
- Add a no-license registration, normalization, and quality-preflight path for uploads first and live camera frames second, while retaining OMRChecker as the provider.
- Protect real-photo recognition with typed diagnostics, fail-closed alignment, a labeled regression, and a physical acceptance matrix before qualification.
- Leave v1–v5 seed behavior and active configurations unchanged.

**Non-Goals:**

- No new OMR provider, OCR path, recognition algorithm, or authorization behavior.
- No migration or schema change; existing layout metadata, capture states, and action APIs are sufficient.
- No result-sheet printing or production applicant data.

## Decisions

1. **Use the existing layout solver as the only geometry authority.**
   - Build a `nexiam.omr.layout-blueprint.v1` blueprint with `paper=long`, `columns=6`, applicant number enabled, native birthdate disabled, identity repeated on every page, and six 25-question sections using `PROFILE_VERBAL`, `PROFILE_QUANT`, `LOGIC`, `SPATIAL`, `MEMORY`, and `LANGUAGE`. Each section after the first uses `break_before=next_column`; the solver produces one page, 40×40 bubbles, 52px option gap, 80px row pitch, compact identity origin `[200,390]`, answer-flow origin y=`940`, and bounded origins.
   - Persist manual birthdate/name/header text in the render-only overlay (`birthdate_label`, `birthdate_placeholder`, `instructions_lines`, `exam_information_lines`, and `show_calibration`); these values never enter native OMR geometry or OCR.
   - Do not hand-author `fieldBlocks` or compile geometry in the seeder. This keeps save, preview, renderer, and capture geometry on the same solver contract.
   - Alternative rejected: copying the earlier v4/v5 four-column JSON, because it cannot provide six domains on one page and bypasses the current persistence metadata.

2. **Persist the profile through `SaveAuthoredTemplateDraft` and activate through existing actions.**
   - Add an idempotent profile method to `OmrDemoSeeder`; it exits when the natural template/version or active pair already exists, resolves the guidance actor, saves the blueprint-backed draft with overlay metadata, creates the 150-row booklet through `SaveAuthoredBookletVersion`, and calls `ActivateOmrConfiguration`.
   - Use a stable profile code and booklet code distinct from SYN-OMR-ONE-PAGE and SYN-OMR-BOOKLET-A/B/C. Existing v1–v5 rows remain immutable.
   - Alternative rejected: direct `updateOrCreate` of activated rows, because activated bundles are immutable and direct writes would bypass audit, hashing, and staged bundle verification.

3. **Generate the answer key from the same six domains and ranges.**
   - Resolve six active `AptitudeArea` rows by stable codes, map each 25-question section to sequential booklet positions 1–150, and cycle A/B/C/D deterministically. Build all rows before invoking the existing save action.
   - If the seed environment lacks the six required areas, fail loudly rather than create a partial profile. This protects the one-page contract from silently degrading.
   - Alternative rejected: reusing VERBAL/QUANT rows or an all-A key, because the capture surface must exercise six-domain rendering and a real answer-key projection.

4. **Keep capture fixtures separate and idempotent.**
   - Extend `OmrDemoBatchSeeder` only after the profile pair is available, using model/action relationships already used by the current demo batch. Seed the focused one-page profile batch with one needs-review sheet; keep pending-pages and failed-terminal states in `OmrDemoMultiStateBatchSeeder`, retaining existing state transitions and idempotent guards.
   - **Founder decision 2026-08-17 (focused near-production dataset):** the *default* seeded state is production-like — exactly ONE active pair (profile template + profile key booklet) and ONE capture batch with ONE needs-review sheet bound to one whole examinee (`OmrFocusedDemoSeeder` in the `DatabaseSeeder` chain; `OmrDemoBatchSeeder` seeds the single examinee sheet). The pending-pages and failed-terminal fixture sheets move to the opt-in `OmrDemoMultiStateBatchSeeder` for capture-surface testing; the full v1–v5 synthetic catalog stays available via `OmrDemoSeeder` for parity/state tests but is not in the default reset state.
   - Prefer a distinct profile batch natural marker or existing command-receipt/idempotency seam rather than duplicating sheets on every `db:seed` run.
   - Alternative rejected: embedding capture rows in the template seeder transaction, because capture state has different lifecycle and retry semantics.

5. **Regression tests assert observable contracts.**
   - Add focused tests for solver output (one page, six blocks, 150 labels, compact dimensions and margins), seed persistence/key alignment/idempotency, and route-level profile visibility/preview. Use existing test traits and factories; do not make tests depend on incidental database IDs.
   - PDF visual inspection remains a release verification step; the automated tests assert MIME/status and geometry metadata, while raster evidence confirms no visible overlap or clipping.

## Risks / Trade-offs

- **[Risk] Six compact columns reduce mark size and horizontal whitespace.** → Keep the solver’s 40×40 geometry and 52px option gap, assert all rightmost bubble bounds remain within the 80px margin, and visually inspect the rasterized PDF at 100%.
- **[Risk] Six aptitude areas may differ between reset environments.** → Resolve by code and fail with the missing codes; document the required catalog seed order in the change handoff.
- **[Risk] Re-running a partially failed seed can leave staged artifacts or orphan draft rows.** → Guard on natural keys, use existing save/activation actions, and clean only known profile artifacts before rebuilding an incomplete profile.
- **[Risk] Capture fixtures can be mistaken for provider confidence.** → Keep fake fixture metadata explicit; real-engine confidence remains null until qualification.

## Physical Readiness Extension

6. **Keep camera guidance in NEXIAM, not OMRChecker.**
   - OMRChecker receives a completed image and returns observations; it does not own webcam access, live framing, focus, exposure, blur, or capture readiness.
   - NEXIAM owns camera permission/device state, live stream, sheet-in-frame guidance, page count, upload fallback, and typed preflight results. `Ready to capture` means the configured frame/geometry gates passed; it never means recognition confidence or production qualification has passed.

7. **Make the review contract explicit before physical acceptance.**
   - Persisted provider states remain `marked`, `blank`, `ambiguous`, and `unreadable`; the review UI must show the state, question, crop, issue, and confidence qualification status.
   - A null real-engine confidence is `Unqualified`, never zero. Staff review remains mandatory until a qualification run establishes thresholds.

8. **Use aligned evidence only when its coordinate frame is verified.**
   - The review image endpoint prefers the provider aligned image. Crop regions must be interpreted in that image coordinate frame; otherwise the UI falls back to the captured image or marks evidence unavailable.
   - A targeted regression must prove identity and item crop bounds against the served evidence dimensions, including scaled input and aligned output.

9. **Do not commit unresolved required issues silently.**
   - Blank, ambiguous, unreadable, and unresolved identity issues remain visible to staff. Batch confirmation must require explicit resolution/abstention or an approved manual score adjustment for every required issue.

10. **Physical acceptance is a gate, not a test fixture.**
   - Baseline, blank, double-mark, erase, rotation, shadow, fold, and wrong-input scans must be recorded against expected gallery/review outcomes before production use.

## Registration and Capture Preflight Design

The existing physical-readiness extension correctly assigns camera ownership to NEXIAM, but it does not yet validate whether a phone image is geometrically suitable for the native OMR bundle. This section closes that gap without adding a commercial SDK or a second recognition engine.

### 1. Preserve one geometry authority

- The semantic blueprint remains the source for `template.json`, the persisted native bundle, the review manifest, print output, and capture-policy derivation. The browser never receives hand-authored bubble coordinates.
- Add a versioned `nexiam.omr.capture-policy.v1` object to the existing template-versioned configuration/derived metadata path. It records capture mode, normalized target dimensions, fiducial family `nexiam.omr.fiducial.v1`, corner-quadrant placement, marker size/quiet zone, marker-to-sheet width ratio, `required_marker_count=4`, solver version, native bundle hash, manifest hash, engine/patch identity, and `calibration_status=unqualified`. The first profile capture gate requires all four markers; three-of-four recovery is not used for production capture.
- The capture policy may reference native geometry but must not duplicate `fieldBlocks`, bubble positions, or answer-key semantics.
- Any marker-bearing profile is a new immutable template version. Existing printed v1–v5/profile stock is never mutated in place.

### 2. Use the free implementation path

- Do not add Dynamsoft, Scanbot, Microblink, Regula, Anyline, or another paid document SDK.
- Use browser APIs for camera/decode/frame scheduling only. Registration, homography, warp, and normalization run server-side through the engine's `CropOnMarkers` preprocessor; the browser performs no registration geometry and ships no OpenCV.js/WASM dependency. An optional pure-JS advisory quality nudge (downscaled canvas brightness/overexposure/blur-variance) may hint before upload, but it never gates submission.
- Implement upload normalization first because it is deterministic and testable in this environment. Add live camera sampling after the upload pipeline is stable.
- Reuse the existing OMRChecker/OpenCV worker runtime for server-side authoritative checks; do not create a second recognition provider.

### 3. Reuse OMRChecker for printed registration

- Audit result (`docs/omrchecker-capture-capability-research.md`): the pinned engine equals current master for every runtime file, and `CropOnMarkers` is the official mobile recipe (`samples/sample1`, `samples/sample5`) already shipped inside the worker image. It matches one printed fiducial asset across the four image quadrants (split at `w/2`, `h/3`), requires a match in every quadrant, and warps by the four marker centers. It has no encoded marker IDs, no AprilTag payload, and no subpixel pose — corner identity comes from quadrant placement.
- The blocker is the NEXIAM asset contract, not the engine: today no stage materializes a marker file (`SaveAuthoredTemplateDraft`, `NativeBundle`, activation, worker), and enabling `CropOnMarkers` without the file exits the child at template load (`exit(31)`).
- **Decision:** marker-bearing profile versions print a repeated, rotation-asymmetric corner fiducial (`nexiam.omr.fiducial.v1`) rendered by the solver, and the worker performs registration with `CropOnMarkers` as the only normalizer. A NEXIAM-owned browser marker detector, AprilTag decoding, and OpenCV.js homography are REJECTED as redundant for this cut; they return only if the physical matrix proves plain fiducials insufficient.
- **Frame anchoring (critical):** the engine warps by the four marker centers and then resizes the result to `pageDimensions`. Therefore `pageDimensions` MUST equal the fiducial-center rectangle, and every block origin, identity grid, name/birthdate box, and header zone MUST be re-derived in that frame. The physical paper is the frame plus an inset ring carrying the fiducials. Keeping `pageDimensions` equal to paper size would scale-shift all geometry and guarantee misreads. The solver derives inset + marker size within the fixed long-bond 2060×3144 budget (shrink inter-column gaps before shrinking the marker below matchable size).
- **Quadrant geometry:** the engine splits the frame at `w/2` and `h/3`; two fiducials must land in the top third and two in the bottom two-thirds, one per quadrant. Page-corner placement satisfies this; the solver MUST assert quadrant membership.
- The fiducial asset is deterministic, content-addressed, hash-covered by the native bundle, and written next to `template.json` in activation and worker temp dirs (`OmrPinnedSnapshot` carries the bytes to the worker). Marker-bearing versions use a marker-only preprocessor chain (a failing `CropPage` would abort the chain before markers are tried); scan profiles keep `CropPage`. The pattern is version-unique (derived from template code + version hash), so photographing the wrong form fails marker match instead of registering and misreading.
- **No Hough fallback on marker versions:** `detect.py` must skip `align_camera_page` when the pinned template is marker-bearing; a marker failure is a typed terminal state, never a degrade into the brittle row-clustering path that produced false marks.
- **Orientation semantics:** the rotation-asymmetric pattern makes 180°/90° orientations fail the match loudly (typed `marker_mismatch` with a "hold the sheet upright" hint) rather than registering an upside-down page that AutoAlign (horizontal shifts only) cannot repair. Identity-grid mismatch and shift gates remain the second line of defense.
- **Aspect-distortion watch:** `apply_preprocessors` stretches the capture to processing dimensions before matching, so a 3:4 photo is squashed ~13% horizontally vs a 2060×3144 frame; upstream mobile samples run the same path. If the physical matrix shows weak matches from this, the NEXIAM-owned mitigation is letterbox-padding the frame before the chain in `detect.py` — never modifying upstream code.
- Marker sizing versus the printable margin is solver-derived within the paper budget above; `sheetToMarkerWidthRatio` and match thresholds remain calibration data until the matrix passes.
- A QR/form identifier remains optional supporting metadata only; it may reject a wrong form but cannot replace page registration.
- **Test-capture primitive:** the engine's `ImageInstanceOps.draw_template_layout` (already in the worker) renders block/bubble rectangles and optional per-bubble mean values over the aligned page; a worker diagnostic seam returns this overlay for the builder `Test capture` button. The CLI `--setLayout` (GUI, blocking, no file output) is not used.

### 4. Run the authoritative preflight server-side

1. Server decodes the upload with EXIF orientation, validates MIME/size/dimensions, and retains original bytes.
2. On marker-bearing versions, `CropOnMarkers` requires the fiducial matched in all four corner quadrants; missing, weak, or wrong-form matches fail with typed states before recognition. The operator never crops or drags the page during normal capture.
3. The engine warps by the four marker centers to the persisted processing dimensions; an unusable registration is a retake/replace decision, not a manual bubble-alignment task.
4. Recognition proceeds on the engine-normalized page; normalized dimensions and field-block alignment are verified before results are accepted.
5. Blur/brightness/glare measurements may surface as advisory guidance (cheap server or canvas metrics); their thresholds remain calibration data until the physical matrix passes.
6. Every failure returns a typed outcome with an actionable message and a retry/replace path; browser hints never override server rejection.

Typed outcomes include `image_decode_failed`, `image_too_small`, `sheet_out_of_frame`, `sheet_partial`, `marker_missing`, `marker_mismatch`, `geometry_rejected`, `perspective_too_high`, `frame_too_blurry`, `lighting_glare`, `lighting_too_dark`, `wrong_template`, `normalized_image_invalid`, `preflight_failed`, and existing `sheet_misaligned`. Every outcome has an actionable message and a retry/replace path.

### 5. Keep evidence and recognition fail-closed

- Store original upload evidence and the normalized/aligned evidence separately with hashes and source metadata. Review displays the clean raster; NEXIAM renders option overlays from typed manifest geometry.
- Worker diagnostics may expose raw per-label values, local bubble means, threshold keys, marker diagnostics, and homography metrics to authorized review, but diagnostics never become answer proposals by themselves.
- Unsafe field-block shifts crossing option pitch continue to emit `sheet_misaligned` and suppress answer/score proposals.
- Null confidence remains `Unqualified`. No threshold or confidence mapping becomes qualified from a single synthetic or real sample.

### 6. Make the operator loop observable

The capture page must show: camera unavailable/requesting/ready, preflight in progress, upload progress, normalized evidence, queue/processing progress, needs-review issues, item/name crops, declared provider output, and confirmation blocking. Polling refreshes the selected sheet object rather than leaving a stale review snapshot.

### 7. Qualification sequence

- Unit-test pure geometry/marker/preflight decisions with valid, missing-marker, wrong-form, rotated, partial, blurred, dark, glare, and too-small fixtures.
- Browser-test upload fallback and typed camera guidance with no camera device.
- Protect the labeled real-photo regression (`1=C, 2=B, 3=C, 4=D, 5=D`, `6–150=blank`, applicant `2000600002`) and require exact output or explicit abstention.
- Run the physical matrix: baseline, blank, double mark, erase, rotation, uneven light/shadow, folded corner, wrong paper, blur, glare, and partial page. Record gallery state, review state, evidence alignment, identity, item states, and staff action.
- Promote thresholds/confidence only after the matrix and labeled regression pass; otherwise keep the system in review/unqualified mode.

### 8. Template-builder precision UX

The existing `TemplateEditor.svelte` already has numeric origin/pitch controls, `pageIssues()`, margin-safe placement, SVG preview, and real PDF preview. Extend that surface; do not replace it with a freehand canvas.

- Add an explicit `Inspect geometry` / `Adjust geometry` mode. Inspect mode is read-only and is the default when an activated or locked version is opened. Adjust mode is available only for an editable draft.
- Dragging operates on a whole field block bounding box or its origin handle, never on individual bubbles, labels, or answer options. A drag changes only the block origin; `bubblesGap`, `labelsGap`, dimensions, field labels, and answer semantics remain numeric/solver-controlled.
- Convert SVG pointer coordinates back through the page `viewBox` to native page pixels. Snap pointer movement to a 4px grid; keyboard arrows move 1px, Shift+Arrow moves 10px. Escape cancels the current drag; Reset restores the solver-proposed origin. The numeric inspector remains the exact authority for values that cannot be safely inferred from a drag.
- Show a live ghost rectangle, origin crosshair, delta (`Δx`, `Δy`), native bounds, and affected labels while dragging. Do not permit drop when the candidate leaves the printable margin, overlaps another block, crosses the reserved identity/header zone, or violates the page bottom limit.
- Keep the existing numeric inspector and add explicit readouts for page dimensions, bubble dimensions, direction, question range, origin, option pitch, row pitch, canonical bounds, visual bounds, and block hash. Every value change re-runs `pageIssues()` before save.
- Add preview toggles for printable margin, native block bounds, bubble centers, labels/options, identity lock, marker corners, review crop regions, and coordinate rulers. Clicking a bubble highlights its label/option/manifest rectangle for inspection only; it never makes that bubble draggable.
- Show a validation panel with severity and exact target: `0 errors` is required for draft save; any geometry error blocks save/preview activation; warnings are visible and recorded. The UI must say what is wrong (`LOGIC_Q_006 overlaps SPATIAL_Q_001 by 8px`), not merely show a red border.
- Show `solver version`, `native bundle hash`, `review manifest hash`, and `capture-policy hash` beside the preview. The server re-solves and revalidates on save; client coordinates are never trusted as final geometry.
- For the compact profile preset, lock page size, bubble dimensions, six-section order, identity footprint, and marker contract after preset selection. Draft authors may adjust approved origin/pitch fields only when the resulting solver diagnostics remain valid.

### 9. Capture and review UX: automatic four-corner go/no-go

The operator should not spend time manually cropping or dragging bubbles during ordinary checking. The capture decision is automatic and binary.

| State | Visible evidence | Operator action | Submit? |
|---|---|---|---|
| `Checking` | live image, four-corner framing guide, server registration check in progress | hold sheet steady / fill the frame | no |
| `Rejected` | red reason, missing/mismatched marker chips, page outline if available | retake, move, replace paper | no |
| `Validated` | four-corner outline with `TL/TR/BR/BL` chips, page coverage, normalized-size preview | capture/upload | yes, after server recheck |
| `Uploading` | original-retained and normalized-submission progress | wait/cancel | already accepted by preflight |
| `Needs review` | clean normalized evidence, read-only manifest overlays and issues | resolve/abstain/adjust through existing review rules | no until required issues resolved |

- `Validated` requires the fiducial matched in all four corner quadrants, registration geometry within tolerance, acceptable page coverage, and a successful server recheck. No three-of-four fallback is allowed for the first production profile.
- After each submitted frame, the server response drives marker chips (`TL`, `TR`, `BR`, `BL`), page outline, orientation, and a single concise corrective message; the live preview itself shows only the framing guide and optional advisory nudges. It does not display tunable thresholds to ordinary operators.
- On failure, the operator gets a retake/replace action. There is no operator page-cropping tool, no freehand quadrilateral editor, and no per-bubble drag in the normal capture/review path.
- Review displays the normalized page and read-only marker quadrilateral. Item/name crops are generated automatically from the manifest and renderer geometry. If registration fails, the system shows `sheet_misaligned`, suppresses answer proposals, and requires retake, abstention, or approved manual score adjustment.
- A developer/staff diagnostic view may show the original image, detected corners, homography, normalized image, and manifest coordinates side by side; it is evidence-only and cannot write new bubble coordinates or silently repair a score.

### 10. Precision verification plan

#### A. Builder math and interaction tests

- Unit-test `blockBounds`, `visualBounds`, `pageIssues`, placement, snap conversion, keyboard movement, cancel/reset, and overlay metric calculations.
- Browser-test draft builder load, inspect mode, adjust mode, block drag, 4px snap, 1px/10px keyboard movement, Escape cancel, Reset, numeric edit, and lock behavior.
- Intentionally drag a block outside the margin and across another block; prove the exact error appears and Save/Preview activation is blocked.
- Click representative bubbles in the preview; prove the manifest label, option, page, pixel rectangle, and normalized region are shown and the bubble remains non-draggable.

#### B. Builder-to-print parity tests

- Build the six-column profile in the builder, save a draft, reload it, and compare the solver output/hash.
- Render the server PDF and compare its page dimensions, marker positions, block bounds, bubble centers, labels, identity grid, name/birthdate boxes, and margin boundary with the browser preview.
- Rasterize the PDF at 100%; require one page, six blocks, 150 labels, no overlap, no clipping, four visible markers, and no accidental footer/calibration copy.
- Record evidence for: valid profile, collision error, marker overlay, bubble-inspection readout, saved/reloaded parity, and PDF raster.

#### C. Capture go/no-go tests

- Browser-test no camera/device unavailable, permission denied, requesting, checking, rejected marker, validated four-marker frame, uploading, and processing states.
- Feed fixtures for all four markers, one missing marker, wrong form, rotated sheet, partial sheet, excessive perspective, EXIF rotation, blur, dark exposure, glare, wrong paper, and insufficient resolution.
- Prove only the all-four-marker validated fixture reaches recognition; every other fixture remains out of the queue or fails closed with an actionable message.
- Confirm no manual crop UI, page-quad drag, or per-bubble drag exists in the ordinary operator path.

#### D. Review and recognition tests

- Verify the normalized evidence is clean and unannotated; overlays are generated from the immutable manifest.
- Verify every item crop includes the row label and all options, and every crop uses the normalized image coordinate frame.
- Verify unsafe shifts produce `sheet_misaligned`, suppress proposals, preserve diagnostics, and block confirmation.
- Run the protected real-photo regression and require exact answers/identity or explicit abstention.

#### E. Human acceptance script

1. Open `/setup/omr/templates/builder` and create/open the profile draft.
2. Turn on geometry overlays and confirm page size, 80px margin, identity footprint, six blocks, and 150 labels.
3. Drag one block by pointer; confirm 4px snapping, visible `Δx/Δy`, changed origin, and unchanged bubble pitch/labels.
4. Move it into another block; confirm the precise collision message and blocked save.
5. Press Escape, use Reset, then edit origin/pitch numerically; confirm the SVG updates and the inspector matches.
6. Select a bubble; confirm its manifest label/option/rectangle appears and the bubble cannot be dragged.
7. Preview the PDF, rasterize it, and compare the browser/PDF geometry and four markers.
8. Open `/grading/sessions/{id}/omr`, upload a valid four-marker sheet, and confirm go/no-go → normalized evidence → queue → review.
9. Upload a missing-marker, wrong-paper, and partial sheet; confirm each is rejected before recognition with a corrective message.
10. In review, confirm the normalized page and read-only overlays; there is no manual crop or per-bubble alignment step.

## Migration Plan

1. Keep the focused default seed: one active profile pair, one booklet, one capture batch, one needs-review sheet; keep multi-state fixtures opt-in.
2. Freeze the capture-policy JSON shape and marker contract before implementation.
3. Add marker geometry and the fiducial asset to the solver/renderer and the bundle contract (`SaveAuthoredTemplateDraft`, `NativeBundle`, activation, worker materialization); create a new profile template version without mutating the existing active version.
4. Enable `CropOnMarkers` on the marker-bearing version and map chain failures to typed preflight states, retaining original evidence.
5. Add the worker layout-verification diagnostic seam and the builder `Test capture` button (read-only).
6. Add live camera sampling and typed retry guidance after upload mode is stable.
7. Repair inference alignment against the protected labeled photo; retain fail-closed behavior until exact output or abstention is proven.
8. Prove queue self-healing, selected-review synchronization, overlays/crops, and confirmation blocking end to end.
9. Run focused tests, browser verification, PDF/raster inspection, and the physical acceptance matrix; record evidence in `HANDOFF.md`.
10. Rollback in development by retiring the new profile version and running the approved foundation reset; no production migration or paid SDK is introduced.

## Open Questions

None; the profile is fixed to one long-bond page, six columns, six 25-question sections, compact 40×40 bubbles, and the existing identity lock.
