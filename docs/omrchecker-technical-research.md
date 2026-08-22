# OMRChecker in NEXIAM: Technical Research Note

**Status:** research / corrective-evidence reference; not a production-activation approval
**Pinned upstream:** `Udayraj123/OMRChecker@de3f6982822895491be760325fd43e65e90fa293` (vendor snapshot: `system/.research/omrchecker-de3f698/`, byte-identical to the pinned archive except `.git/`)
**Local engine boundary:** `system/docker/inference/` (`server.py`, `detect.py`, `Dockerfile`, `patches/`, `SOURCE.lock`)
**Normative authority:** [`openspec/changes/omr-profile-test-readiness/`](../openspec/changes/omr-profile-test-readiness/) and the archived [`openspec/changes/archive/omr-scoring-capture/`](../openspec/changes/archive/omr-scoring-capture/) design §21–22; [`GREENFIELD-MASTER-REFERENCE.md`](GREENFIELD-MASTER-REFERENCE.md) §13 records the current checkpoint.

This note answers one question with evidence: **why the supplied 3000×4000 phone photo is not currently normalized into a scan-like page in NEXIAM** — by walking OMRChecker's real pipeline, separating OMRChecker's *general* mobile claims from *NEXIAM's pinned configuration*, and mapping the observed NEXIAM symptoms to the responsible stage.

---

## 1. Executive summary

- OMRChecker is a **pixels-to-observed-bubbles** engine: it decodes an image, runs configured pre-processors, aligns the page, samples mean bubble intensity, thresholds, and returns observed field values plus a marked canvas (`src/core.py:32-44`, `48-437`).
- The upstream README advertises phone support ("Read OMR sheets ... using a scanner or your phone", "~90% accurate on mobile images") — but upstream achieves that with **template-specific pre-processors** (`CropPage`, `CropOnMarkers` with printed corner markers, `FeatureBasedAlignment`) tuned per sample. The claim is not a property of the engine alone; it is a property of *engine + tuned template*.
- NEXIAM pins **one** upstream commit, applies a two-file patch set, and configures every bundle with **`CropPage` only** (no markers, no feature alignment) and `auto_align` enabled. NEXIAM templates have **no printed registration markers**, so the upstream mobile recipe (`CropOnMarkers`) cannot apply.
- The 3000×4000 photo (sheet occupies most of the frame but **not all of it**: desk background top/sides, fabric at bottom; mild trapezoid perspective; non-uniform lighting; **no printed corner markers**; EXIF orientation 1) fails normalization in two recorded stages:
  1. `CropPage.find_page()` finds no valid quadrilateral → pre-processor returns `None` (`src/processors/CropPage.py:66-83,85-140`);
  2. NEXIAM's camera fallback `align_camera_page()` (`system/docker/inference/detect.py:352-423`) fails to fit a homography with the committed Hough row-clustering gap factors `(0.45, 0.35, 0.5, 0.65)` → child raises `DetectionError("preprocessing_failed")` (`detect.py:570-572`).
- A temporary experiment adding a `0.30` row-gap candidate let preprocessing succeed and reach OMRChecker classification, but returned **false marks/ambiguous items** instead of the declared `1=C, 2=B, 3=C, 4=D, 5=D`, and left applicant `2000600002` unresolved — recorded in [`GREENFIELD-MASTER-REFERENCE.md:541`](GREENFIELD-MASTER-REFERENCE.md) and task 9.7 of `openspec/changes/omr-profile-test-readiness/tasks.md`. That experiment is **not** accepted (task 9.7: "not accepted until recognition also passes").
- The engine **can** process JPEGs: MIME sniffing, decode, and EXIF stages all pass for this photo. The failure is **geometry normalization**, not codec support.

---

## 2. Upstream capability claims vs. NEXIAM's pinned configuration

### 2.1 What OMRChecker claims upstream (official URLs)

| Claim | Source | Exact wording |
|---|---|---|
| Scanner **and** phone | [`README.md#L3`](https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md) | "Read OMR sheets fast and accurately using a scanner 🖨 or your phone 🤳." |
| Min resolution 640×480 | [`README.md#L34`](https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md) | Specs badge: `Min Resolution 640x480` |
| Scan vs mobile accuracy | [`README.md#L36`](https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md) | "nearly 100% accurate on good quality document scans; and about 90% accurate on mobile images." |
| Robustness scope | [`README.md#L37`](https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md) | "Supports low resolution, xeroxed sheets. See **Robustness**" (wiki link) |
| Mobile demo media | [`README.md#L68-71`](https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md) | "Code in action on images taken by a mobile phone" (`checking_xeroxed_mobile.gif`) |
| Mobile capture recipe | [Wiki: `[v1]-User-Guide` — "Note for capturing using mobile phones"](https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-User-Guide) | "If you can modify your OMR sheets with these markers, it will give you much higher accuracy when scanning using mobile camera. We enable the markers plugin [CropOnMarkers + `omr_marker.jpg`]." |

### 2.2 The upstream mobile claim is pre-processor-mediated (source evidence)

Upstream mobile/angled samples succeed **because their templates configure the right pre-processors**:

| Upstream sample | Pre-processors in its `template.json` | Input type |
|---|---|---|
| `samples/sample1/` (mobile camera) | `CropPage` (morphKernel 10×10) **then** `CropOnMarkers` with `omr_marker.jpg`, `sheetToMarkerWidthRatio: 17` | phone photos (`samples/sample1/MobileCamera/sheet1.jpg`) |
| `samples/sample5/` (scanner batch) | `CropOnMarkers` only | `camscanner-*.jpg` |
| `samples/sample2/` (Adrian) | `CropPage` only | flat scans |

Mechanics of each pre-processor (pinned source):

- **`CropPage`** (`src/processors/CropPage.py:58-140`): normalizes, truncates, morph-closes, Canny-edges, finds contours, keeps the 5 largest, and accepts only a 4-point convex quad passing a max-cosine rectangle check (`check_max_cosine` `CropPage.py:29-42`, `validate_rect` `:44-46`); then `four_point_transform` warps to a bird's-eye view. **On failure returns `None`** and logs `"Error: Paper boundary not found for: '<file>'"` (`CropPage.py:69-72`).
- **`CropOnMarkers`** (`src/processors/CropOnMarkers.py:25-188`): template-matches a printed marker image in four quadrants over a rescaled search range (`getBestMatch` `:231-272`), requires each quadrant match ≥ `min_matching_threshold` (default 0.3) and within `max_matching_variation` (default 0.41); **returns `None` if any quadrant fails** (`:88-101`).
- **`FeatureBasedAlignment`** (`src/processors/FeatureBasedAlignment.py:17-96`): ORB keypoints of a reference image vs. the input, brute-force Hamming matching, RANSAC homography/affine warp.
- **Built-ins** (`src/processors/builtins.py:9-58`): `Levels`, `MedianBlur`, `GaussianBlur`; registry `src/processors/manager.py:68` (`PROCESSOR_MANAGER`).

`Template.setup_pre_processors` (`src/template.py:67-77`) instantiates exactly the processors named in the template's `preProcessors` array, in order. `ImageInstanceOps.apply_preprocessors` (`src/core.py:32-44`) resizes to `processing_width × processing_height` first, then runs each in sequence and **stops on the first `None`**.

### 2.3 NEXIAM's pinned/configured pipeline (local evidence)

NEXIAM does **not** reproduce the upstream mobile recipe:

- **Engine pin:** `Dockerfile` downloads the archive of commit `de3f6982822895491be760325fd43e65e90fa293`, verifies SHA-256 (`d1a988ee…eac0`), applies the owned patch `patches/0001-headless-overlay.patch`, and sets `NEXIAM_PATCH_IDENTITY=nexiam-headless-overlay-v1` (`system/docker/inference/Dockerfile`; pin also in `SOURCE.lock`, `config/omr.php` `omrchecker.engine_commit`, and every bundle `manifest.json`).
- **Owned patch set (only two changes, neither touches detection math):** (1) headless imports — removes `get_monitors()[0]` from `src/utils/interaction.py`, fixes the stale/unbound `x, y` overlay branch in `src/core.py:344-360`; (2) `read_omr_response` returns the **clean unannotated raster** (`final_align`) instead of the debug-annotated `final_marked` (`src/core.py:59-62, 198-208, 413-436` per patch hunk headers).
- **Configured pre-processors:** every NEXIAM bundle page ships `[{"name": "CropPage", "options": {"morphKernel": [5, 5]}}]` — set by the layout solver (`system/app/Assessment/Omr/Layout/OmrTemplateLayoutSolver.php:368`), the seeder (`system/database/seeders/OmrDemoSeeder.php:507`), and present in shipped bundles (`system/app/Assessment/Omr/NativeBundles/REAL-TEST-5AF4C427/v1/template.json`, pages 1–2). **No `CropOnMarkers`, no `FeatureBasedAlignment`** — the NEXIAM sheet prints no registration markers (confirmed visually on the phone photo).
- **Configured config** (`SaveAuthoredTemplateDraft::defaultConfig`, `system/app/Assessment/Actions/Omr/SaveAuthoredTemplateDraft.php:225-247`; materialized in `NativeBundles/REAL-TEST-5AF4C427/v1/config.json`): `processing_width/height = page dimensions` (long bond `[2060, 3144]`), `auto_align: true` (`match_col 5`, `max_steps 30`, `stride 8`, `thickness 2`), `GAMMA_LOW 0.9`, `MIN_JUMP 50`, `MIN_GAP 30`, `PAGE_TYPE "NORMAL"`. Compare upstream defaults `processing 666×820`, `auto_align False`, `MIN_JUMP 25`, `PAGE_TYPE_FOR_THRESHOLD "white"` (`src/defaults/config.py:3-39`). Note: upstream `get_global_threshold` reads `PAGE_TYPE_FOR_THRESHOLD`; `"NORMAL"` falls through to the black-page default `GLOBAL_PAGE_THRESHOLD_BLACK = 100` (`src/constants/common.py:26-38`).
- **Headless runtime guarantees:** `merge_config` forces `show_image_level = 0`, `save_image_level = 0`, `save_detections = False` (`system/docker/inference/detect.py:46-49`); supervisor/child isolation, HMAC auth, size caps, and one-child-per-sheet semantics (`server.py`).

**Conclusion:** OMRChecker's "~90% mobile" claim and NEXIAM's pinned pipeline are different objects. The claim applies to upstream's tuned templates (markers + CropPage/FeatureBasedAlignment); NEXIAM's bundle is `CropPage`-only, marker-free, `auto_align`-on, at 2000×2828/2060×3144 native resolution.

---

## 3. End-to-end pipeline at the pinned commit

### 3.1 Request envelope (HTTP supervisor)

`system/docker/inference/server.py`:

- `POST /v1/detections`, contract `nexiam.omr.detection.v2` (L18), multipart `manifest` (JSON) + `image` (raw bytes) (L187-211).
- HMAC-SHA256 request signature over `POST\n/v1/detections\ntimestamp\nrun_id\nmanifest_sha256\nimage_sha256` (L33-35, verified L141-146); ±30 s clock skew (L137-139); `X-Nexiam-*` headers (L129-135).
- Bounds: request ≤ 12 MiB, manifest ≤ 2 MiB, image ≤ 10 MiB, result ≤ 2 MiB, aligned ≤ 15 MiB, concurrency ≤ 2, child deadline 30 s (L20-31, enforced L114-119, L177-184, L220-222).
- One child process per sheet (`python detect.py manifest image dir`), child exits after one result (L152-163); opaque temp dir cleaned in `finally` and at startup (L45-50, L183).
- Response: signed multipart `result` JSON + `aligned_image` PNG with `X-Nexiam-Result-SHA256` / `X-Nexiam-Aligned-SHA256` (L213-241).

The Laravel adapter (`system/app/Assessment/Omr/OmrCheckerDetection.php:31-77`) builds the same envelope (`manifest()` `:79-118`), verifies response identity/hashes/signature (`successfulResponse` `:188-292`), and maps failures to typed outcomes (`failedResponse` `:163-186`; 429/5xx retryable, `child_output_invalid`/`contract_mismatch`/`engine_mismatch` terminal, 422 `image_type_invalid`/`image_decode_failed`/`image_metadata_mismatch`/`input_hash_mismatch` invalid-input). Upload validation limits live in `system/config/omr.php` (`upload.max_bytes` 10 MiB, `max_width/height` 5000, `max_pixels` 20 M — the 3000×4000 photo passes).

### 3.2 Image decode, MIME, EXIF

`system/docker/inference/detect.py`:

- `sniff_mime` (L58-66): magic-byte gate — PNG/JPEG/WebP only; the photo (`ff d8 ff`) passes.
- `cv2.imdecode` grayscale with `IMREAD_IGNORE_ORIENTATION` (L545-546); decoded dimensions must equal the declared `image_width/height` (L548-549).
- Bounded EXIF orientation reader (L68-104 TIFF, L106-130 JPEG) + `apply_exif_orientation` (L132-148). The photo's EXIF orientation is **1** (no rotation needed), so this stage is a no-op for it.
- Manifest hash/engine/patch validation (`validate_manifest` L507-536) precedes decode.

### 3.3 Pre-processors (upstream, called at detect.py:565)

`ImageInstanceOps.apply_preprocessors` (`src/core.py:32-44`):

1. `resize_util` to `processing_width × processing_height` (`src/utils/image.py:28-33`); for NEXIAM bundles that is the native page size (e.g. 2000×2828), so a **scan at exactly processing size is `camera_input = False`** (`detect.py:564`).
2. Run each configured pre-processor in order; first `None` aborts the chain.
3. For NEXIAM: `CropPage` only (see §2.3). Page-boundary detection details: `find_page` (`CropPage.py:85-140`) — normalize → `THRESH_TRUNC` → morph-close 5×5 → Canny → contours by area → convex hull → `approxPolyDP` → `validate_rect`. Requires the **sheet edge to be the dominant clean quadrilateral** in the frame.

### 3.4 Camera-input branch (NEXIAM-owned fallback, detect.py:352-423)

Only when `camera_input == True` **and** the configured pre-processors returned `None` (detect.py:565-572):

1. `camera_option_rows` (L150-179): expected bubble centers from the manifest at processing resolution, grouped into rows (tolerance `max(2, page_height*0.002)`), keeping rows below 20% of page height. Requires ≥ 4 rows (L356).
2. `hough_circle_rows` (L180-224): `HoughCircles` on median-blurred grayscale (`dp 1.2`, `param1 80`, `param2 18`), radius range from `bubble_size × source_scale` (`min_radius = max(6, …0.24)`, `max_radius = max(min+5, …0.9)`; `source_scale = min(w/page_w, h/page_h)`), bounds `(0.03, 0.18, 0.97, 0.98)`, row clustering with `cluster_gap = max(min_radius*1.5, row_gap*gap_factor)`.
3. Row-clustering gap factors tried in order: **`(0.45, 0.35, 0.5, 0.65)`** (L391-401 comment: "start with the stable grouping for phone photos, then keep fallbacks for scans"). `choose_camera_rows` (L320-351) scores sliding windows of Hough rows against expected row count/medians with y-gap-ratio penalties.
4. `select_row_points` (L225-319): per-row linear least-squares fit of detected points onto expected x-positions, per-block grouping to tolerate printed row numbers, `max_error = max(10, bubble_size*0.4)`.
5. Homography: `cv2.findHomography(RANSAC, max(6, bubble_size*0.25))`, requires ≥ 3 matched rows, ≥ 16 point pairs, inlier count ≥ `max(16, 45% of points)`; then `warpPerspective` to `(processing_width, processing_height)` (L409-422).
6. On success: `normalize_camera_lighting` (L425-431) — divide by large-sigma Gaussian background (`sigma ≥ 25`, `scale 220`) to flatten uneven illumination.
7. On failure: `DetectionError("preprocessing_failed")` (L570-572) → exit code 2 → supervisor maps it to HTTP `422` with the child's typed code (`server.py:160-162`; code extraction `safe_child_code` `server.py:243-251`).

### 3.5 OMRChecker core reading (`read_omr_response`, `src/core.py:48-437`)

1. Resize to `template.page_dimensions` (L52-54); min-max normalize (L54-58).
2. Alignment morphology (auto_align, NEXIAM: on): CLAHE → gamma `GAMMA_LOW` → `THRESH_TRUNC 220` → normalize (L65-77); vertical open `(2,10)×3` (L102) → trunc 200 (L104) → invert → threshold 60 (L117-118) → erode 5×5 (L120); per-field-block horizontal shift search comparing left/right column means (`match_col 5`, `max_steps 30`, `stride 8`, `thickness 2`) (L133-195, `left/right_mean > 100` at L182). Mutates `field_block.shift` (L195); the final per-block shifts are surfaced to NEXIAM as `field_block_shifts` (`detect.py:659-664`).
3. Bubble sampling: `cv2.mean` over each `box_w × box_h` rect at `(x + shift, y)` (L223-232); per-question std-dev recorded (L231).
4. Global threshold: `get_global_threshold` (L499-600) — largest-gap heuristic over **sorted** bubble intensities (`MIN_JUMP 25`, `looseness 4`); plus `global_std_thresh` over per-question stds (L242).
5. Local threshold: `get_local_threshold` (L602-701) — largest-gap per question strip, `confident_jump = MIN_JUMP + CONFIDENT_SURPLUS`; if not confident and strip has no outliers, fall back to global threshold. `MIN_JUMP` here comes from NEXIAM's config (50).
6. Classification: bubble marked iff `per_q_strip_threshold > bubble_mean` (L306-310); multiple marks concatenate into one field value (L355-363); no marks → `emptyValue` (L367).
7. Outputs: `omr_response` (label → value string), `final_marked` (debug canvas: rectangles + value labels + translucent overlay, L317-345, L383), and — **after the NEXIAM patch** — the clean `final_align` raster (patch hunk 2). Upstream would return `final_marked` here; NEXIAM stores the unannotated raster as aligned evidence (`detect.py:653-656`).

### 3.6 Result mapping (detect.py:581-695)

- Raw provider values → per-item `state`: `marked` (value ∈ option codes and `selected_option_is_supported` — selected bubble mean at least `LOCAL_SUPPORT_DELTA = 10.0` darker than the best competitor, L477-485, L19), `blank` (empty), else `ambiguous` with required issue `ambiguous_mark` (L585-608).
- Identity: digit-bubbled applicant number; any non-single-digit → `identity_unresolved` required issue (L610-637).
- Outputs: `result.json` (contract, hashes, engine identity, items, issues, `field_block_shifts`, `provider_diagnostics.raw_values/mark_evidence/thresholds`, timings) and `aligned.png` (PNG-9, L653-656). Confidence is always `null`, `calibration_status` `"unqualified"` (L677, L675).
- NEXIAM validates the result (`OmrDetectionResultValidator`, `OmrCheckerDetection.php:188-292`), fails closed on option-crossing shifts (`CODE_SHEET_MISALIGNED`, `system/app/Assessment/Omr/OmrDetectionIssue.php:23`; task 9.6), and requires human review.

### 3.7 Output rasters and debug stacks

- Upstream: `final_marked` annotated canvas; `draw_template_layout` (L438-497) previews; `save_image_stacks` (L707-730) writes level-keyed debug stacks — all disabled in NEXIAM (`detect.py:46-49`).
- NEXIAM: only `aligned.png` (clean) is returned to Laravel; original photo is retained as forensic evidence elsewhere (SensitiveAssetStore; `omrchecker-integration-explainer.md` §8).

---

## 4. Image assumptions (explicit)

Upstream assumes, and NEXIAM's pinned config inherits:

1. **The sheet is the dominant clean quadrilateral in the frame** (for `CropPage`). Background around the paper, shadows on the paper edge, or a trapezoid that fails the max-cosine check → `find_page` returns nothing → chain aborts (`CropPage.py:44-46, 69-72, 116-139`).
2. **Or printed registration markers** exist in all four quadrants (`CropOnMarkers`) — NEXIAM sheets do not print them.
3. **After resize, bubble centers coincide with template geometry**: `read_omr_response` samples at template coordinates + per-block horizontal shift only (L223-232); it does **not** re-detect the page. Any residual perspective/rotation after preprocessing shifts bubble sampling off-center.
4. **Enough source resolution**: engine floor is 640×480 whole-page (README L34); NEXIAM renders at 242 PPI with 40 px bubbles (solver `PPI 242`, `COMPACT_BUBBLE 40`, `COMPACT_MCQ4_OPTION_GAP 52`, `ROW_PITCH 80`; `OmrTemplateLayoutSolver.php:31-49`), far above the floor — resolution is not the binding constraint.
5. **Uniform-ish illumination** for thresholding: upstream thresholds on intensity gaps (global largest-gap, per-strip local largest-gap, `src/core.py:499-600, 602-701`); strong lighting gradients move bubble means and corrupt the gap heuristics. NEXIAM flattens lighting **only on the camera path after successful alignment** (`detect.py:573-574`).
6. **Proportional geometry**: template pixels are arbitrary units; images are resized to `pageDimensions` before reading, so physical size must map proportionally to template coordinates.

---

## 5. Failure modes and error paths

| Trigger | Stage | Result |
|---|---|---|
| Non-PNG/JPEG/WebP magic | `sniff_mime` (detect.py:58-66) | `image_type_invalid` → 422 invalid-input |
| Decode failure / dimension mismatch | detect.py:545-549 | `image_decode_failed` / `image_metadata_mismatch` |
| Bundle/engine/patch/config hash mismatch | `validate_manifest` (detect.py:507-536) | `contract_mismatch` / `engine_mismatch` / `*_hash_mismatch` → terminal |
| **Page boundary not found in photo** | `CropPage.find_page` returns `[]` → `apply_filter` returns `None` (CropPage.py:69-83) | pre-processor chain aborts (`core.py:38-43`) |
| **Camera homography cannot be fit** | `align_camera_page` (detect.py:352-423) | `preprocessing_failed` → 422 (`detect.py:570-572`; `server.py:160-162`) |
| Alignment succeeds but is wrong | hough 0.30 experiment (see §6) | false marks / `ambiguous` items; identity unresolved; never qualified |
| Multi-mark / erasure / faint fill | classification + `selected_option_is_supported` (detect.py:477-485) | `ambiguous` + required issue |
| Option-crossing block shift | Laravel validator / task 9.6 | `sheet_misaligned`, proposals suppressed |
| Child timeout / crash / oversized output | server.py:152-163, 177-184 | `child_timeout`, `child_failed`, `child_output_invalid` (retryable/terminal per `OmrCheckerDetection.php:163-186`) |
| Signature/contract/timestamp | server.py:129-146 | 401; `request_*_invalid` |

Full failure/recovery matrix: `openspec/changes/archive/omr-scoring-capture/design.md` §22.

---

## 6. The 3000×4000 phone-photo diagnosis

### 6.1 Fixture and recorded outcome

- Fixture: `system/tests/Fixtures/Omr/Profile150/phone-marked-20260818.jpg` (3,888,651 bytes; SHA-256 `a2cdc31bc5c3ed015ba34ff00da53d9969ae6dc921f10bf7e2aa99fabd1acf5e`; 3000×4000; JPEG; EXIF orientation 1) with sidecar `phone-marked-20260818.json` declaring expected `1=C, 2=B, 3=C, 4=D, 5=D`, items 6–150 blank, applicant `2000600002`, acceptance "exact answers and identity, or explicit abstention; never false answers".
- Recorded outcomes:
  - "the exact replay fails preprocessing" — replay harness `system/docker/inference/verify_fixture.py` (runs `detect.detect()` with the manifest + image; checks the five declared answers).
  - "the temporary `0.30` alignment hypothesis reaches OMRChecker but returns false marks/ambiguous items ... leaves applicant `2000600002` unresolved, and is not qualified" — `docs/GREENFIELD-MASTER-REFERENCE.md:541`.
  - "adding the observed `0.30` row-gap candidate only makes preprocessing succeed and is not accepted until recognition also passes" — `openspec/changes/omr-profile-test-readiness/tasks.md` task 9.7 (open).
  - The observed failure shape (five false marked answers plus 145 blanks) is codified in `system/tests/Feature/Assessment/OmrSheetMisalignmentTest.php:129-132`.

### 6.2 Stage-by-stage walk with source evidence

1. **Envelope/decode/EXIF — PASS.** MIME JPEG (detect.py:58-66), decode succeeds (L545-549), EXIF orientation 1 is a no-op (L106-130, 132-148). The engine has no JPEG limitation; the claim "cannot process the photo" is false at this stage.
2. **`camera_input` — TRUE.** 3000×4000 ≠ processing 2000×2828 (`detect.py:564`; config from `SaveAuthoredTemplateDraft.php:225-247`).
3. **`CropPage` — FAILS.** The photo shows the sheet occupying most, but not all, of the frame (desk surface top/sides, fabric at bottom), slightly trapezoidal page edges, non-uniform lighting (darker left/lower), **no printed corner markers** (vision inspection, §2.3). Under `find_page` (CropPage.py:85-140) the sheet edge must emerge as a clean 4-point quad from Canny contours after morph-close 5×5; background clutter + trapezoid + lighting gradient defeat the rectangle validation (`CropPage.py:44-46`), so `apply_filter` returns `None` (CropPage.py:69-83) — the upstream-documented failure mode ("Paper boundary not found ... Have you accidentally included CropPage preprocessor?").
4. **Camera fallback `align_camera_page` — FAILS with committed gap factors.** With `processed = None`, the child retries on the **original** photo (detect.py:570): Hough circle detection (`hough_circle_rows` L180-224) finds bubble candidates, but row clustering at gap factors `(0.45, 0.35, 0.5, 0.65)` (L391-401) cannot form windows that pass `choose_camera_rows` (L320-351) + `select_row_points` (L225-319) + the RANSAC homography gates (≥3 rows, ≥16 points, ≥45% inliers; L409-419). Returns `None` → `DetectionError("preprocessing_failed")` (L570-572).
5. **The 0.30 experiment — reaches classification but misreads.** Inserting the observed 0.30 row-gap candidate into the loop let a homography fit, so preprocessing succeeded and `read_omr_response` ran on the warped photo. The warp is not good enough for 40 px bubbles over 150 rows at 242 PPI: bubble sampling (`core.py:223-232`) lands off-center, lighting is only flattened *after* the warp (detect.py:573-574), and the intensity-gap thresholds (`core.py:499-600, 602-701`) then classify wrong bubbles → five false marks + 145 blanks, applicant digits unresolved. This is why task 9.7 explicitly rejects the candidate: "only makes preprocessing succeed ... not accepted until recognition also passes."

### 6.3 Why "not normalized into a scan-like page" is accurate

A scan-like page means: sheet fills the frame with a detectable clean quad (or printed markers), uniform lighting, and bubble centers mapping to template coordinates after the configured pre-processors. The photo satisfies none of the NEXIAM-pinned normalization preconditions (§4): no markers, no clean quad, background in frame, mild perspective, uneven light. Every stage that could produce a normalized page (`CropPage`; then `align_camera_page` fallback) is failing, and the only experimental loosening that gets past preprocessing demonstrably corrupts recognition. The pipeline is **fail-closed by design** here — it refuses (`preprocessing_failed`) rather than persisting a wrong proposal, which is the correct safety posture (§3.4, §5).

---

## 7. Actionable remediation

### 7.1 Capture guidance (staff-facing, zero engine change)

- Fill the frame: sheet must cover ~100% of the image with **no desk/background** at any edge (CropPage quad detection).
- Photograph straight-on (top-down), avoid trapezoid/keystone; keep edges parallel to the frame.
- Even lighting: avoid window shadows, table lamps, vignetting; no glare.
- Higher fill contrast: dark pencil, full bubble shading (40 px bubbles at 242 PPI need solid fills).
- Keep the printed sheet clean — no folds/creases crossing the bubble grid.
- Mirror this in the capture preflight copy (task 7.1 already assigns framing guidance to NEXIAM UI; `omr-profile-test-readiness/tasks.md`).

### 7.2 Canonicalization / alignment changes (engine-adjacent, gated)

- **Decision needed (not taken here):** either (a) print registration markers and add `CropOnMarkers` to the profile bundle (the upstream mobile recipe, wiki User Guide), or (b) harden `align_camera_page` — e.g. validate candidate homographies by re-checking bubble centers against sampled intensities, or search gap factors adaptively from the median row gap instead of a fixed list — while keeping task 9.7's acceptance bar ("recognition also passes").
- Consider relaxing `CropPage`'s rectangle validation for mild trapezoids only in the camera path, never for scans.
- Keep `normalize_camera_lighting` applied on every camera path, and evaluate running it *before* homography fitting.
- Fail-closed behavior (task 9.6, `sheet_misaligned`) stays; any alignment loosening must be paired with the same rejection gates.

### 7.3 Threshold calibration (task 9.9)

- Instrument per-bubble raw intensities and local/global thresholds into `provider_diagnostics` (harness `threshold_diagnostics`, detect.py:486-506) — partially shipped.
- Tune or replace thresholding only against the physical matrix (baseline, blank, double mark, erase, rotation, shadow, fold, wrong paper; task 7.7), never from this one photo ("do not qualify from this one sample" — task 9.9).
- Calibrated confidence and `calibration_status` changes are out of scope until a labeled corpus fits a versioned mapping (design §21.3).

### 7.4 Protected regression fixtures (task 9.8)

- Promote the labeled photo to a protected regression: exact `1=C, 2=B, 3=C, 4=D, 5=D`, items 6–150 blank, applicant `2000600002`; acceptance is exact output or explicit abstention — **never false answers**.
- Keep `verify_fixture.py` as the replay oracle and `OmrSheetMisalignmentTest` as the fail-closed contract (5 false marks + 145 blanks must remain *rejected*).
- Re-run the focused OMR suites and strict OpenSpec validation per task 4.3 before any of §7.2/§7.3 ships.

---

## 8. Source list

**Official upstream (pinned commit `de3f6982822895491be760325fd43e65e90fa293`; local vendored mirror `system/.research/omrchecker-de3f698/` is byte-identical, diff-verified):**

- README claims — https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md (L3, L34, L36-37, L68-71)
- Wiki mobile-capture note — https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-User-Guide ("Note for capturing using mobile phones")
- `src/core.py` — class `ImageInstanceOps` L22; `apply_preprocessors` L32-44; `read_omr_response` L48-437; `draw_template_layout` L438-497; `get_global_threshold` L499-600; `get_local_threshold` L602-701; `append_save_img` L703; `save_image_stacks` L707-730
- `src/template.py` — `Template` L20; `setup_pre_processors` L67-77; `FieldBlock` L212; `generate_bubble_grid` L286
- `src/processors/CropPage.py` — `check_max_cosine` L29-42; `validate_rect` L44-46; class L58; `apply_filter` L66-83; `find_page` L85-140
- `src/processors/CropOnMarkers.py` — class L25; `apply_filter` L52-188; `load_marker` L191-229; `getBestMatch` L231-272
- `src/processors/FeatureBasedAlignment.py` — class L17; `apply_filter` L47-96
- `src/processors/builtins.py` — `Levels` L9; `MedianBlur` L36; `GaussianBlur` L52
- `src/processors/manager.py` — `PROCESSOR_MANAGER` L68
- `src/defaults/config.py` — `CONFIG_DEFAULTS` L3-39
- `src/constants/common.py` — `FIELD_TYPES` L14-25; `GLOBAL_PAGE_THRESHOLD_WHITE/BLACK` L26-38
- `src/utils/image.py` — `CLAHE_HELPER` L16; `resize_util` L28-33; `normalize_util` L72-74; `four_point_transform` L101-129; `order_points` L262
- Sample templates proving preprocessor-mediated mobile support — `samples/sample1/template.json` (CropPage+CropOnMarkers), `samples/sample5/template.json` (CropOnMarkers), `samples/sample2/template.json` (CropPage)

**NEXIAM local:**

- `system/docker/inference/server.py` — envelope constants L18-31; signatures L33-43; `readiness` L52-71; `Handler` L80 (do_GET L83, do_POST L92, `handle_detection` L111-184, `parse_multipart` L187-211, `send_multipart` L213-241, `safe_child_code` L243-251)
- `system/docker/inference/detect.py` — constants L15-19; `load_omrchecker` L28-35; `merge_config` L37-51; `sniff_mime` L58-66; EXIF L68-148; `camera_option_rows` L150-179; `hough_circle_rows` L180-224; `select_row_points` L225-319; `choose_camera_rows` L320-351; `align_camera_page` L352-423; `normalize_camera_lighting` L425-431; `option_strengths` L448-476; `selected_option_is_supported` L477-485; `threshold_diagnostics` L486-506; `validate_manifest` L507-536; `detect` L537-695 (decode L545-550, camera branch L564-574, `read_omr_response` L579, aligned encode L653-656); `self_test` L697-703; `main` L705-724
- `system/docker/inference/Dockerfile`, `SOURCE.lock`, `patches/0001-headless-overlay.patch`, `verify_fixture.py`, `generate_fixture.py`
- `system/app/Assessment/Omr/OmrCheckerDetection.php` — detect L31-77; manifest L79-118; failedResponse L163-186; successfulResponse L188-292
- `system/app/Assessment/Omr/OmrDetectionRequest.php` (L25-44); `OmrDetectionIssue.php` (`sheet_misaligned` L23)
- `system/app/Assessment/Omr/Layout/OmrTemplateLayoutSolver.php` — constants L31-49; `PAPERS` L52-56; `COLUMN_X` L59-72; `CropPage` preprocessor L368
- `system/app/Assessment/Actions/Omr/SaveAuthoredTemplateDraft.php` — `defaultConfig` L225-247
- `system/app/Assessment/Omr/NativeBundles/REAL-TEST-5AF4C427/v1/{template.json,config.json,manifest.json}`
- `system/config/omr.php` — upload caps and `omrchecker` pin
- `system/database/seeders/OmrDemoSeeder.php` — profile blueprint L256+, CropPage L507
- `system/tests/Fixtures/Omr/Profile150/phone-marked-20260818.{jpg,json}`
- `system/tests/Feature/Assessment/OmrSheetMisalignmentTest.php` — failure shape L129-132
- `docs/GREENFIELD-MASTER-REFERENCE.md` L541; `docs/omrchecker-integration-explainer.md` (conventions/context)
- `openspec/changes/omr-profile-test-readiness/tasks.md` (7.x, 9.x); `openspec/changes/archive/omr-scoring-capture/design.md` §21-22

---

## 9. Evidence table: observed NEXIAM symptom → responsible stage

| Observed symptom (repo record) | Evidence | Responsible OMRChecker/NEXIAM stage |
|---|---|---|
| "The exact replay fails preprocessing" | `GREENFIELD-MASTER-REFERENCE.md:541`; `omr-profile-test-readiness/tasks.md` 9.7; harness `verify_fixture.py` | `CropPage.find_page` → `None` (`CropPage.py:66-140`) **then** `align_camera_page` → `None` (`detect.py:352-423, 570-572`) |
| "Adding the observed 0.30 row-gap candidate only makes preprocessing succeed" | tasks.md 9.7 | Hough row-clustering gap factors in `align_camera_page` (`detect.py:391-401`; `hough_circle_rows` L180-224) |
| "Returns false marks/ambiguous items instead of declared 1=C,2=B,3=C,4=D,5=D" | `GREENFIELD-MASTER-REFERENCE.md:541`; failure shape codified `OmrSheetMisalignmentTest.php:129-132` | `read_omr_response` classification on a poorly warped page: bubble sampling (`core.py:223-232`), intensity-gap thresholds (`core.py:499-600, 602-701`), `selected_option_is_supported` → `ambiguous` (`detect.py:477-485, 585-608`) |
| "Leaves applicant 2000600002 unresolved" | `GREENFIELD-MASTER-REFERENCE.md:541` | Identity digit mapping, `identity_unresolved` (`detect.py:610-637`) |
| "Is not qualified" | `GREENFIELD-MASTER-REFERENCE.md:541`; design §21.3 | `calibration_status: "unqualified"`, `confidence: null` (`detect.py:675-677`) — by contract, independent of recognition |
| Clean unannotated aligned evidence | tasks.md 9.3; patch file hunk 2 | Patched `read_omr_response` return (`patches/0001-headless-overlay.patch`; `detect.py:579`) |
| Fail closed on option-crossing shifts | tasks.md 9.6; `OmrDetectionIssue.php:23` | Laravel validator on `field_block_shifts` (`detect.py:659-664`) |
| EXIF / manual-camera / native aligned smoke fixtures succeed | `GREENFIELD-MASTER-REFERENCE.md:540` | Decode/EXIF (`detect.py:545-550`), synthetic flat images, scan-size inputs (`camera_input == False`) |
| Photo itself is valid JPEG | fixture SHA-256 + EXIF orientation 1 (read directly from `phone-marked-20260818.jpg`) | `sniff_mime` / `imdecode` pass (`detect.py:58-66, 545-546`) — engine limitation is normalization, not JPEG support |
