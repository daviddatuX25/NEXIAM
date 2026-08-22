# OMR profile test-readiness handoff

## Seed and reset

Run from the repository root:

```bash
docker compose -f system/compose.yaml exec -T app php artisan foundation:reset --force
docker compose -f system/compose.yaml exec -T app php artisan db:seed --class="Database\\Seeders\\GradingDemoSeeder" --force
```

`foundation:reset` runs the foundation seed chain, including the compact profile template/key pair. `GradingDemoSeeder` creates the profile capture batch and self-heals an interrupted prior batch.

## Staff login

- Email: `guidance.counselor@nexiam.test`
- Password: `password`
- Capability: `guidance.scoring.omr`

## Expected seeded state

- Template code: `SYN-OMR-PROFILE-150`, one active long-bond page, six 25-question blocks, 150 questions, compact 40×40 bubbles.
- Native identity: applicant number only, compact grid origin `[200,300]`; native birthdate is disabled. The six answer columns start at y=`940`, leaving the EDQ header and identity labels clear while preserving the 80px page margin.
- Booklet code: `SYN-OMR-PROFILE-KEY`, one active matching authored key with deterministic A/B/C/D answers.
- Render-only overlay: `EDQ / HEADER ZONE`, compact exam/batch line, one-line instruction, manual `BIRTHDATE:`/`MMDDYYYY` box, manual `NAME (PRINT):` box, and no footer/calibration copy. `reserve_native_identity: true` places EDQ content after the native identity footprint; the builder preview uses the same geometry.
- Capture batch: one open profile batch with exactly one sheet / one page:
  - `needs_review`: one complete profile sheet bound to applicant number `1234567890`; proposal/provider confidence values remain null until qualification.
  - No `pending_pages` or `failed_terminal` synthetic rows are seeded in the default near-production dataset; capture-state fixtures remain available through `OmrDemoMultiStateBatchSeeder` for targeted workflow tests.

## URLs

- OMR hub: `http://127.0.0.1:18000/setup/omr`
- Template list: `http://127.0.0.1:18000/setup/omr/templates`
- Grading OMR capture: `http://127.0.0.1:18000/grading/sessions/7/omr` (current seeded session; reset/reseed may allocate a different session id)
- Profile preview: open the active `SYN-OMR-PROFILE-150` version from the template list; after a clean reset its version id is currently `1`, so the direct route is `/setup/omr/templates/1/preview`.

## Verification evidence

- Clean `app`/`queue` image rebuild completed before the final run. Full suite: **401 passed, 1 skipped, 22,970 assertions**. Focused renderer regression: **10 passed, 65 assertions**; setup/profile suites: **12 passed, 1 skipped, 243 assertions**.
- Fresh profile preview: `200 application/pdf`, 648,260 bytes; rasterized at 96 DPI and visually checked for a clear EDQ header, native identity labels/bubbles, aligned manual name/birthdate boxes, six answer columns, 150 rows, bottom margin, and no footer/calibration copy.
- Fresh v4/v5 previews: `200 application/pdf`; all four pages remained raster/vision-checked for page margins, label/bubble separation, footer placement, and calibration separation.
- Manual name fallback: `BundleRenderer` keeps `NAME (PRINT):` on every page, preferring the upper-left gap and using a 50mm upper-right box for compact identity bands or full headers; the active blueprint builder preview shows the same upper-right box inside the dashed printable margin.
- Seed verification after `foundation:reset --force` + `GradingDemoSeeder --force`: exactly 1 template, 1 booklet, 1 active pair, 1 open batch, 1 sheet, 1 `needs_review` sheet, and 1 roster-bound examinee sheet.
- Focused readiness suites: **33 passed, 1,610 assertions** across the layout solver, profile readiness, and detection envelope suites. Frontend UUID fallback suite: **3 passed**.
- Browser verification at `/grading/sessions/7/omr`: headless preflight reports `Camera device unavailable` with upload fallback and disabled capture; gallery exposes one focused seeded scan; review opens private evidence with `Unqualified` confidence and truthful `OMRChecker` provenance.
- Upload smoke test with `crypto.randomUUID` removed from the browser runtime: no console/page errors; the new sheet transitioned `processing → needs_review`, and its worker run completed `succeeded` with OMRChecker commit `de3f6982822895491be760325fd43e65e90fa293`. The database was reset afterward to restore the one-sheet focused state.
- The physical acceptance matrix (baseline, blank, double mark, erase, rotation, shadow, fold, wrong paper) remains pending because this environment has no camera/scanner or physical sheets; do not treat synthetic previews or fake-provider evidence as scan acceptance.
- Verification refresh: `foundation:reset --force` followed by `GradingDemoSeeder --force` completed successfully; a read-only database check observed exactly 1 template, 1 active template version, 1 booklet, 1 active booklet version, 1 active pair, 150 authored questions, 1 capture batch, 1 capture sheet, 1 `needs_review` sheet, 1 capture page, and 1 roster-bound sheet.
- Verification refresh: OMR browser checks at `/setup/omr`, `/setup/omr/templates/1/preview`, and `/grading/sessions/7/omr` passed; setup showed `1 template`, `1 booklet`, and `1 active` pair, the preview returned `200 application/pdf`, and capture showed one scan with camera-unavailable upload fallback.
- Verification refresh: opening the focused review showed private evidence, `Unqualified` sheet/identity/item confidence, truthful OMRChecker provenance, the bound `Demo Accepted` examinee, and all six 25-item score areas.
- Verification refresh: worker smoke matrix passed for EXIF-oriented, manual-camera, and native aligned fixtures; all three returned `succeeded` with aligned 2060×3144 output and no provider crash.
- Verification refresh: the focused UUID fallback suite passed (`3 tests`); a browser reload of the OMR route produced no page or console errors. Workspace `svelte-check` and focused ESLint remain red on pre-existing unrelated diagnostics (`app.ts`, legacy Svelte warnings/errors, and TypeScript parser configuration), not on this UUID helper change. A local `npm run build` attempt was terminated by host memory exhaustion (exit 137; bounded retry hit Node heap exhaustion), with no source compilation diagnostic.
- Latest focused regressions: `OmrProfileReadinessTest` **5 passed, 181 assertions**; `OmrDetectionEnvelopeTest` + `OmrSheetMisalignmentTest` **22 passed, 1,417 assertions**; UUID fallback **3 passed**.
- Latest reset/reseed verification: exactly 1 template, 1 active template version, 1 booklet, 1 active booklet version, 1 active pair, 150 authored questions, 1 capture batch, 1 capture sheet, 1 `needs_review` sheet, 1 capture page, and 1 roster-bound sheet; queue health was `healthy`.

## 2026-08-18 real-photo blocker evidence

- Historical queue blocker: `foundation:reset` once raced the queue against the dropped `cache` table and required a queue restart; the latest reset left `queue` running and healthy, but a repeatable no-manual-intervention proof remains open under task 9.1.
- Latest browser run kept the selected review synchronized: the same sheet rendered `PROCESSING` with progress text, then transitioned to `NEEDS REVIEW` without a manual reload; refreshed issues and overlays replaced the processing state.
- The served aligned evidence is now the clean unannotated raster. NEXIAM-owned green/red/amber overlays render from typed geometry, the legend is visible, the item crop contains the row label plus A/B/C/D bubbles, and the handwritten-name crop is explicitly crop-only.
- Declared ground truth for the supplied photo remains: answers `1=C, 2=B, 3=C, 4=D, 5=D`; items `6–150` blank; applicant-number shading `2000600002`; handwritten `NAME (PRINT)` is crop-only evidence.
- The worker smoke replay succeeds and now emits bounded `provider_diagnostics` with raw per-label values, local bubble means, and OMRChecker threshold keys (`global_thr`/page thresholds). The supplied photo still produces false marks/ambiguous items and no qualified identity; this is diagnostic evidence, not acceptance.
- The unsafe field-block gate is active: option-crossing shifts derive required `sheet_misaligned`, suppress answer/score proposals, and pass the focused misalignment suite.
- Current blockers are physical acceptance and real-photo recognition: tasks 7.7, 9.1, and 9.7–9.9/9.11 remain open. Synthetic previews and fake-provider evidence remain transport/UI evidence only; do not qualify automated checking from this one sample.

## 2026-08-19 marker registration, capture preflight, and web-runtime fix evidence

### Founder directive (2026-08-19)

The quadrilateral four-corner markings are STRICTLY REQUIRED — never accept a sheet without all four (`required_marker_count=4`, no three-of-four fallback, fail-closed typed rejection). The fiducial doubles as version-unique template identification: the pattern is derived from template code + immutable version, so photographing the wrong form fails the marker match loudly instead of registering (task 10.2).

### Frozen capture policy (10.1)

- `config/omr/capture-policy.php` freezes `nexiam.omr.capture-policy.v1`: `capture_mode=upload_first`, `target_dimensions=[2060,3144]`, `frame_anchor` (printer margin 50 / marker 142 / quiet 40 / inset 232), fiducial family `nexiam.omr.fiducial.v1`, `sheet_to_marker_width_ratio=14.0`, rescale `[30,140]`/12 steps, thresholds `0.3`/`0.41`, quadrant split `w/2 × h/3` with `required_marker_count=4`, `calibration_status=unqualified`.
- `OmrCapturePolicy` derives per-version policies (solver version, native bundle hash, review-manifest hash, engine/patch identity) and never duplicates field geometry or answer keys.

### Hardened fiducial contract (10.2)

- The original single-radius 5×5 lattice measured NO wrong-form/rotation discrimination at the engine gate (aligned TM_CCOEFF_NORMED cross-seed 0.59–0.91; end-to-end wrong-form/rotated/marker-less sheets all accepted). Replaced by a **multi-radius 2-bit level lattice (0.32/0.55/0.78 × pitch) inside a version-unique 16-segment dashed square ring** — same lattice positions and marker/inset budget (features never smaller than before).
- Measured under the engine's exact erode-subtract preprocessing: self=0.353, wrong-seed max 0.042, 90/180/270° rotation max 0.110 (margin ≥ 0.30); correct-form end-to-end quadrant scores rose from ~0.35 to 0.691 uniform.
- Cross-language contract: PHP `OmrFiducial::matrixHash` == Python `fiducial.matrix_hash` == golden `bf24baecf7c033caa01b040736da77dfb42ba24eda13b741b799d3fbb4dfb669` for `SYN-OMR-PROFILE-150|v2` (pinned in `OmrFiducialTest` and `test_marker_pipeline`).
- Marker-bearing is strictly opt-in via blueprint `markers` (`OmrMarkerlessContractTest`: marker-less solve emits no fiducial/marker/capture-policy vocabulary; `markers.enabled=false` is byte-identical to absent; enabled without seed is refused).

### Solver frame anchoring (10.2) and bundle carriage (10.3)

- `OmrTemplateLayoutSolver::markerLayout` derives the fiducial-center frame (pageDimensions = paper − 2×inset = 1596×2680 for long bond), re-derives identity/flow inside the frame, asserts the `w/2 × h/3` quadrant contract, and swaps `CropPage` for `CropOnMarkers` (with the fiducial asset) on marker pages only.
- `OmrMarkerBundleTest` (5 passed): the fiducial rides the immutable bundle (staged by `SaveAuthoredTemplateDraft`, SHA-256 + manifest hash covered, `OmrPinnedSnapshot` carries the bytes to the worker); a bundle missing the asset fails validation — the engine `exit(31)` path is unreachable.

### Worker marker-only normalization, no Hough fallback (10.4), layout verification (10.5), preflight

- Marker-bearing templates use the marker-only `CropOnMarkers` chain; `detect.py` never runs the Hough `align_camera_page` fallback for marker versions (asserted in `test_marker_pipeline.py`); failures map to typed states (`marker_missing`, `marker_mismatch`, `sheet_partial`, `preprocessing_failed`, `marker_asset_missing`) with corrective copy and orientation hints; no three-of-four fallback.
- `/v1/layout-verification` (verify.py, `OmrCheckerDetection::verifyLayout`, `OmrLayoutVerificationResult`) returns the clean aligned raster, the `draw_template_layout` overlay, per-bubble means, marker matches, and field-block shifts — read-only, HMAC-enveloped, never recognition output.
- `/v1/preflight` (preflight.py, `PreflightOmrCapture`, `OmrCapturePreflight`) answers `mode=marker` validated/rejected (four gates: marker_count, registration, dimensions, shifts) or `mode=scan, outcome=not_applicable` for CropPage batches.

### Test capture panel (10.6) and capture preflight HUD (10.7/10.18)

- `TestCapturePanel.svelte` (builder + template show, marker-bearing versions only): uploads a photo of the printed sheet to the real `POST /setup/omr/templates/{versionId}/test-capture`, shows the normalized raster + engine overlay, gate chips, bounded diagnostics, and the `Unqualified` calibration note; marker-less drafts show a clear explanation, never a fake green state.
- `Capture.svelte` preflight HUD: `Checking` / `Validated` (four gate chips + normalized dimensions) / `Rejected` (typed code + detail + orientation hint + retake/replace) / error states; the preflight gate runs before every file, camera-frame, and page-replace upload; scan `not_applicable` passes straight to the normal upload path; camera permission/device/upload fallback intact; no browser CV, no manual crop/drag; wording is `Ready to check` and recognition stays `Unqualified`.

### Web-runtime fix (root cause + resolution)

- **Root cause:** `php artisan serve` spawns the built-in `php -S` child with a STRIPPED environment (only two variables), so the compose `OMR_WORKER_*` env vars never reached the web runtime → `config('omr.omrchecker.signing_secret')` was empty → `OmrCheckerDetection::secret()` threw `RuntimeException('OMRChecker signing secret is invalid.')` → every direct worker call (test-capture, preflight) returned `worker_configuration_invalid`, while CLI tinker/queue worked.
- **Fix:** the OMR worker transport vars are now baked into `.env` at image build (`docker/php/Dockerfile` ARG + guarded append, mirroring the existing APP_KEY pattern) and documented in `.env.example`; `OmrCheckerDetection` now logs the exact exception (class + message + stack) in the `detect()`/`verifyLayout()`/`preflight()` build catches so future transport failures are visible in `storage/logs/laravel.log`.
- **Live verification (real HTTP through the rebuilt container):**
  - `POST /setup/omr/templates/2/test-capture` → **HTTP 200, `outcome: succeeded`**, `aligned_url` + `overlay_url`, aligned/overlay image metadata (1596×2680), `marker_matches` four quadrant scores 0.652/0.651/0.653/0.651, field-block shifts present.
  - `POST /grading/sessions/8/omr/batches/2/preflight` (marker batch, synthetic photo) → HTTP 200, `mode: marker, outcome: rejected, code: geometry_rejected` with gates `{marker_count: pass, registration: pass, dimensions: fail, shifts: not_run}` and `submit_allowed: false` — typed fail-closed through the real worker.
  - Same endpoint with a marker-less photo → HTTP 200 typed rejected with low quadrant scores (0.31–0.38).
  - Scan batch preflight → HTTP 200, `mode: scan, outcome: not_applicable` (normal upload path stays authoritative).

### Exact test suite outcomes (2026-08-19 final run)

| Suite | Result |
|---|---|
| `OmrFiducialTest` | 9 passed |
| `OmrCapturePolicyTest` | 6 passed |
| `OmrTemplateLayoutSolverMarkerTest` | 5 passed |
| `OmrMarkerlessContractTest` | 6 passed |
| `OmrMarkerBundleTest` | 5 passed |
| `OmrTestCaptureTest` | 5 passed |
| `OmrCapturePreflightTest` | 5 passed |
| `OmrDetectionEnvelopeTest` | 11 passed |
| `OmrProfileReadinessTest` | 5 passed |
| **PHP total (9 suites)** | **58 passed, 1,789 assertions** |
| `test_marker_pipeline.py` | 17 checks pass (matrix hash `bf24baec…`, Hough-free, typed failures, frame-sized alignment) |
| `test_layout_verification.py` | all checks pass (overlay PNGs decode to the frame, bubble means numeric/bounded, marker-less rejected, typed failures) |
| `test_preflight.py` | all checks pass (four quadrant matches, frame dimensions, orientation hint, no recognition output, scan rejected) |
| `test_server_contract.py` | all checks pass (signed envelope, preflight shares envelope, 404/health paths) |

### Physical qualification status (unchanged)

`calibration_status` remains **`unqualified`**. The protected labeled regression (10.10), the physical acceptance matrix (10.11, incl. rotation/fold/glare/wrong-paper), and the operator-loop re-proof on the focused dataset (10.12) still require REAL printed sheets — no synthetic preview or fake-provider evidence qualifies automated checking. The focused default seed intentionally stays on the marker-less profile pair (founder decision 2026-08-17) until the marker-bearing pair passes 10.10/10.11.

## OpenSpec checks

```bash
openspec validate omr-profile-test-readiness --strict
```

Result: `Change 'omr-profile-test-readiness' is valid`.
