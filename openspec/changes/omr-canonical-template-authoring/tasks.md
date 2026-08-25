## 1. Foundation: config, schema, dimension math, marker profiles, QR contracts, QR renderer pin

- [x] 1.1 Add `system/config/omr/canonical.php` freezing config-owned limits and defaults: upload ≤ 25 MB, pages per source ≤ 20, max pixels 20M/page and 100M/job, physical custom-size bounds (`min_paper_mm`/`max_paper_mm`, e.g. 127×178 … 216×356), conversion timeout 300 s/page, one conversion per draft, queue concurrency 2, blank-print chunk 100, undo stack 200, autosave debounce 800 ms, effective-DPI floor 150, minimum bubble 4.2 mm, minimum option edge gap 1.26 mm, PPI 300, snap 4 px, keyboard 1 px / Shift+10 px, and the source-type-aware enlargement DPI gate.
- [x] 1.2 Add migration `system/database/migrations/<ts>_create_omr_marker_profiles_table.php` creating `omr_marker_profiles` (code + version UNIQUE, family, asset_path, asset_sha256, size_mm/quiet_zone_mm nullable, matching_config JSON, status check `candidate|qualified|retired`, qualification_evidence_hash nullable, created_by_user_id, qualified_by_user_id nullable, qualified_at nullable, timestamps); single-qualified invariant enforced in the qualification action.
- [x] 1.3 Add migration `system/database/migrations/<ts>_create_omr_template_authoring_drafts_table.php` creating `omr_template_authoring_drafts` (template_id FK nullable, code, name, source_kind, stage check `draft|source_confirmed|page_prepared|mapping_complete|digital_verified|compiled`, marker_profile_id FK `omr_marker_profiles` nullable, authoring_json, lock_version, owner_user_id, compiled_version_id FK nullable, timestamps) — staged work NEVER lives on the immutable version.
- [x] 1.4 Add migration `system/database/migrations/<ts>_create_omr_template_assets_table.php` creating `omr_template_assets` with REAL FKs — nullable `authoring_draft_id` FK and nullable `template_version_id` FK (both indexed) plus a DB CHECK that exactly one is non-null (`asset_owner_violation`); kind `source|intermediate_page|raster_page|print_pdf|evidence|digital_proof|marker_profile`, page_no nullable, disk, path, mime, bytes, sha256, metadata, timestamps; immutable once written.
- [x] 1.5 Add migration `<ts>_add_omr_authoring_manifest_to_template_versions.php` adding the compact `authoring_manifest` JSON column (hashes + summary only) to `omr_answer_sheet_template_versions`; additive, no rewrite of existing rows.
- [x] 1.6 Add migration `<ts>_create_omr_blank_instances_table.php` creating `omr_blank_instances` (FK compiled template version restrict, unique ≥128-bit `instance_code` (26-char Crockford base32 random), UNIQUE checksummed `display_code`, monotonic `page_set_no`, `page_count` check `> 0`, status check `printed|captured|superseded|void`, `print_job_id`, `superseded_by_instance_id`, `qr_manifest_hash` = SHA-256 of ordered per-page signed payload hashes, `signing_key_id`, `signature_version`, `created_by_user_id`) with unique `(template_version_id, page_set_no)` and unique `display_code`; NO applicant FK.
- [x] 1.7 Add migration `<ts>_create_omr_blank_instance_pages_table.php` creating `omr_blank_instance_pages` (instance_id FK cascade, page_no check `≥ 1`, qr_payload_hash, signing_key_id, print_artifact_hash, status check `printed|captured|void`, captured_page_id FK `omr_capture_sheet_pages` nullable — the real capture-page model `OmrCaptureSheetPage`) with unique `(instance_id, page_no)`; NO applicant FK.
- [x] 1.8 Add `system/app/Assessment/Omr/Canonical/OmrPageDimensionMath.php` implementing `px = round(mm × 300 / 25.4)`, inverse, the NORMALIZED preset transform from ACTUAL rendered-PDF page dimensions (`x_new = round(x_old / old_page_width × new_page_width)`, likewise y/w/h — NO fixed PPI-ratio formula), 9-point alignment, proportional scale with source-type-aware enlargement DPI gate (downscale always; enlarge only when effective DPI after placement ≥ floor), offset from reference point, white-padding-only placement, effective DPI, and pixel-budget/paper-bounds checks.
- [x] 1.9 Add `system/app/Assessment/Omr/Canonical/OmrMarkerProfileService.php` + action `system/app/Assessment/Actions/Omr/Canonical/QualifyOmrMarkerProfile.php`: seed upstream OMRChecker sample marker and current custom fiducial as CANDIDATES; TEMPLATE-INDEPENDENT qualification from the system marker-calibration artifact (task 1.15) — evidence bound to marker asset hash + renderer version + printer/device/paper, NO target template version required; promote EXACTLY ONE profile to `qualified` (others → `retired`); compile gate on `marker_profile_unqualified`; staff edit insets only (physical size/quiet zone immutable after qualification).
- [x] 1.10 Add `system/app/Assessment/Omr/Canonical/OmrQrPayload.php` + `system/config/omr/qr-keyring.php` implementing the signed versioned payload `nexiam:blank:v1:<instance_code>:p<page_no>:p<page_count>:k<signing_key_id>:<hmac>` (HMAC-SHA256 against `key[signing_key_id]`, `signature_version=1`), keyring verification so key rotation verifies historical prints, per-page payload-hash + key-id + version storage, set-level `qr_manifest_hash` composition, and reject paths (`qr_signature_invalid`, `qr_key_unknown`).
- [x] 1.11 RESEARCH AND PIN the server-side QR renderer (FOUNDATION PREREQUISITE): a maintained PHP-runtime package with deterministic SVG/PNG output, fixed ECC level and quiet zone, vetted license/security/version, and no network calls at render time; npm `qrcode` is client-only and insufficient. This research task MUST be completed BEFORE the QR rendering implementation tasks (9.x); record the research outcome and chosen dependency in the change `HANDOFF.md`.
- [x] 1.12 RESEARCH AND PIN the HTML sanitizer (FOUNDATION PREREQUISITE, before the HTML adapter in 2.3): a maintained server-side allowlist sanitizer with CSS property filtering, remote-off, deterministic output, and vetted license/security/version; do not assume a package; record the research outcome in `HANDOFF.md`.
- [x] 1.13 RESEARCH AND PIN the deterministic ICC conversion pipeline, or choose the FIRST-CUT REJECTION path: image sources with non-sRGB ICC profiles are converted only by the researched pipeline, otherwise rejected with typed `image_icc_unsupported`; no-profile/sRGB accepted directly; record the outcome in `HANDOFF.md` before the image adapter (2.6).
- [x] 1.14 Add unit tests `system/tests/Unit/Assessment/OmrPageDimensionMathTest.php` (rounding, normalized preset transform from actual page dims, 9-point alignment, scale bounds incl. DPI-gated enlargement, padding-only, effective DPI, pixel budgets, paper bounds), `OmrMarkerProfileTest.php` (candidates, template-independent calibration qualification, single-qualified promotion, insets-only editing, compile gate), `OmrQrPayloadTest.php` (sign/verify via keyring, forged payload rejection, key-rotation verification, unknown key → `qr_key_unknown`, `qr_manifest_hash` ordering), and constraint tests for stage/status/page_no/page_count/exactly-one-owner/unique-display-code. Stop gate: all green, no regressions in `OmrCapturePolicyTest`.
- [x] 1.15 Add `OmrMarkerCalibrationArtifactGenerator` + action `system/app/Assessment/Actions/Omr/Canonical/GenerateOmrMarkerCalibrationArtifact.php` (+ `GenerateOmrMarkerCalibrationArtifactRequest`, route `/setup/omr/templates/canonical/marker-profiles/{profile}/calibration-artifact`) and job `RenderOmrMarkerCalibrationArtifactJob.php`: render a SYSTEM marker-calibration sheet through the canonical renderer — four candidate markers + a controlled background/corner false-positive corpus — bound to marker asset hash + renderer version + printer/device/paper evidence; does NOT require a target template version; add `OmrMarkerCalibrationTest` (artifact content, hash binding, template-independence). Stop gate: calibration suite green.

## 2. Source adapters (all five) with hostile preflight and conversion jobs

- [x] 2.1 Add `system/app/Assessment/Omr/Canonical/OmrSourceAdapter.php` (interface: `kind()`, `preflight()`, `convert()`, `result()`) and `OmrSourceAdapterRegistry.php` mapping `html|docx|pdf|image|preset`.
- [x] 2.2 Add `OmrSourcePreflight.php`: MIME vs magic bytes, extension match, upload/page caps, pixel budgets, paper bounds, DOCX zip member count/size ratio + `[Content_Types].xml`/`word/document.xml` presence + VBA/macro rejection, PDF xref parse + encryption/password rejection + MediaBox page size/orientation, HTML allowlist sanitizer using the pinned dependency from 1.12 (no scripts/events/iframes/remote assets) + dynamic-token rejection (`{{…}}`/dynamic-looking tokens must map to fixed `data-omr-overlay` slots; plain static text fine), image EXIF orientation + no-profile/sRGB acceptance + non-sRGB ICC via the 1.13 pipeline or `image_icc_unsupported` + alpha-flatten-white/DPI floor.
- [x] 2.3 Add `OmrHtmlSourceAdapter.php` using the existing HTML→PDF path exactly once, rendering ordered 300-PPI page images + mm sizes; dynamic slots recognized ONLY via fixed `data-omr-overlay` markers; static content rendered as-is (no reflow).
- [x] 2.4 Add `OmrDocxSourceAdapter.php` running LibreOffice headless → PDF exactly once inside the queue worker (result-sheet DOCX pattern), then rasterizing pages.
- [x] 2.5 Add `OmrPdfSourceAdapter.php` rasterizing after MediaBox inspection, rejecting mixed page sizes with the typed offending-page list, page-count cap enforced.
- [x] 2.6 Add `OmrImageSourceAdapter.php` (single page; multipage TIFF rejected in v1) with EXIF/ICC/alpha/DPI normalization per 1.13; authoring photos flow through `OmrQuadRectifier.php` which calls the worker seam `POST /v1/source-rectification` (task 2.10) with staff-confirmed four points — the frontend may suggest/drag corners but the server output is the authority; quality gates and confirmation required before acceptance; ordinary capture never exposes a manual quad.
- [x] 2.7 Add `OmrPresetSourceAdapter.php` wrapping the existing solver/renderer path: measure the ACTUAL page box of the existing rendered PDF, apply the normalized physical transform to `fieldBlocks`, run semantic/normalized-region parity checks; never use a fixed PPI-ratio formula and never label the source as natively 300 PPI.
- [x] 2.8 Add `system/app/Jobs/RenderOmrSourcePagesJob.php` (queued conversion with progress/cancel/retry, converter version + settings hashed into provenance) and `system/app/Http/Requests/Omr/Canonical/IngestOmrSourceRequest.php` with idempotency key.
- [x] 2.9 Add tests `system/tests/Unit/Assessment/OmrSourceAdapterTest.php` covering all five adapters: HTML script/event/remote rejection + slot markers + `dynamic_token_rejected`, DOCX zip-bomb/macro rejection, encrypted PDF rejection, image EXIF/ICC/alpha/DPI floors (`image_icc_unsupported`), preset normalized-transform parity from actual page dims, quad confirmation requirement and quality-gate failures. Stop gate: adapter suite green; fixtures committed under `system/tests/fixtures/omr/canonical/`.
- [x] 2.10 Add `system/docker/inference/rectify.py` (bounded child, existing child-process/HMAC envelope pattern) served as `POST /v1/source-rectification` in `server.py`: accepts staff-confirmed four points + image, returns the rectified raster + quality metrics (edge straightness residual, content aspect ratio, resolution after warp); add app action `RectifyOmrSourcePage` (+ `RectifyOmrSourcePageRequest`, route `/drafts/{draft}/source/rectify`) and python test `test_source_rectification.py`; ordinary capture never uses this seam.

## 3. Staff page confirmation and Stage-2 composer

- [x] 3.1 Add action `system/app/Assessment/Actions/Omr/Canonical/ConfirmOmrSourcePages.php` (+ `ConfirmOmrSourcePagesRequest`) requiring every page confirmed; reject reasons (`wrong_page`, `duplicate_page`, `upside_down`, `illegible`) recorded as user classifications with actor, never automatic claims; re-ingest returns draft to `draft` and voids prior confirmations; stage `source_confirmed`.
- [x] 3.2 Add action `system/app/Assessment/Actions/Omr/Canonical/ComposeOmrCanonicalPages.php` (+ `ComposeOmrCanonicalRequest`) running Stage-2: per-page printer-safe/marker-inset/content margins in mm, scale/offset/9-point alignment with the source-type-aware enlargement DPI gate, 300-PPI PNG per page + exact-size image-backed PDF print authority at internal scale 100%, `composition_hash`; refuses unconfirmed pages (`page_not_confirmed`), mixed sizes (`mixed_page_sizes`), and below-floor enlargement (`insufficient_resolution`).
- [x] 3.3 Add `system/app/Assessment/Omr/Canonical/OmrCanonicalComposer.php` implementing the compose pipeline; record actual-size instruction metadata surfaced by every print surface (system renders exact-size; external printer Fit cannot be enforced).
- [x] 3.4 Add tests `system/tests/Feature/Setup/OmrCanonicalCompositionTest.php`: deterministic mm→px (216×330 → 2551×3898), mixed-size rejection list, unconfirmed-page blocking, scale/offset/9-point placements, DPI-gated enlargement accept/reject, exact-size PDF page box, actual-size instruction present on print surfaces. Stop gate: stage `page_prepared` reachable only via confirmed compose.

## 4. Overlay layer and closed tag set

- [x] 4.1 Add `OmrOverlaySpec.php` validating the closed tag set (`institution`, `assessment`, `template`, `blank_instance_code`, `page_number`, `page_count`, `blank_instance_qr`), rejecting applicant tags (`tag_forbidden`), fixed boxes with no reflow, template-time vs print-job vs blank-instance scopes, and `data-omr-overlay` HTML slot recognition.
- [x] 4.2 Add action `system/app/Assessment/Actions/Omr/Canonical/SaveOmrCanonicalOverlay.php` (+ `SaveOmrOverlayRequest`) persisting overlay JSON, recomputing `overlay_hash`, invalidating mapping/verification hashes (`stale_mapping`, `verification_invalidated`).
- [x] 4.3 Add `OverlayEditorPanel.svelte` in `system/resources/js/Pages/Setup/OmrConfiguration/Canonical/` (fixed-box drag/resize, 4px snap, tag picker from the closed set, no applicant tags selectable).
- [x] 4.4 Add `OmrOverlaySpecTest` (closed set, applicant-tag rejection, no-reflow, `data-omr-overlay` slots, dynamic-token rejection, raster-instance-independence: same version + different instance ⇒ identical static raster bytes). Stop gate: overlay suite green; canonical raster hash-stable across instances.

## 5. Mapping editor (native SVG), validator, fieldBlocks authority

- [x] 5.1 Add `system/resources/js/Pages/Setup/OmrConfiguration/Canonical/CanonicalMapEditor.svelte`: raster `<image>` underlay, scene viewBox and stored coordinates in CANONICAL PIXELS, pointer events converted via inverse CTM, inspector derives mm via `px × 25.4 / 300`; native pointer events only, NO Konva/Fabric dependencies; tablet/desktop (≥1024 px) editing with phone view-only; overlay drag/resize, OMR block/grid creation, per-row uniform structural adjustment; bubble click = read-only inspector.
- [x] 5.2 Add `MapGeometryInspector.svelte` and interaction contract: 4px snap, keyboard 1 px / Shift+Arrow 10 px, touch drag/resize with ≥44 px hit targets, undo/redo (200 steps), autosave debounce 800 ms with saved/saving/conflict indicators, `concurrency_conflict` stale-draft banner (draft `lock_version`), ARIA labels + focus rings + screen-reader readouts + `prefers-reduced-motion`.
- [x] 5.3 Add `system/app/Assessment/Omr/Canonical/OmrMappingValidator.php`: unique contiguous labels, missing-question warnings, regular options v1 (MCQ4/MCQ5/int), duplicate/overlap/out-of-bounds, marker/content/identity/header collisions, min bubble ≥ 4.2 mm, min option edge gap ≥ 1.26 mm, page-count parity with source; overfull/irregular rows are BLOCKING (`overfull_row`, `irregular_options`) with NO automatic split — staff must explicitly split the block into one-label regular blocks, preview, then re-validate (`explicit_split_required` until done).
- [x] 5.4 Add action `system/app/Assessment/Actions/Omr/Canonical/SaveOmrCanonicalMapping.php` (+ `SaveOmrMappingRequest`): server re-validates, rewrites `mapping.field_blocks` deterministically (client never posts raw bubble coordinates), computes `mapping_hash`, emits `fieldBlocks` in the OMRChecker shape; recognition algorithm and `fieldBlocks` contract stay pinned while compiler/bundle manifest and worker envelope may extend; stage `mapping_complete`.
- [x] 5.5 Add action `system/app/Assessment/Actions/Omr/Canonical/SelectOmrMarkerProfile.php` (+ `SelectOmrMarkerProfileRequest`) binding `marker_profile_id` to the draft from the candidate/qualified list.
- [x] 5.6 Add tests `OmrMappingValidatorTest` (regular geometry, collisions, min sizes, parity, overfull/irregular blocking, explicit-split recheck, `bubble_too_small`, `pitch_too_tight`, `page_parity_mismatch`) and `OmrCanonicalMappingBundleTest` (mapping → native bundle; `fieldBlocks` contract unchanged). Stop gate: mapping save blocked on any error; overfull rows never auto-split; bundle hash stable across identical mappings.

## 6. Digital verification (draft-scoped): proof, no-go matrix, Test-capture evidence

- [x] 6.1 Extend the EXISTING `/v1/layout-verification` endpoint (`server.py` route, child `verify.py` — NOT a new HTTP endpoint) so its multipart/result payload additionally carries item evidence driven by the DECLARED OPTION SET (A–D for MCQ4, A–E for MCQ5, 0–9 for int): per-item rects + crops, bubble means, threshold delta/status, field-block shifts, marker matches, and source/mapping/bundle hashes; retain the existing HMAC envelope; extend `OmrLayoutVerificationResult`/`OmrCheckerDetection::verifyLayout` projections; add python test `test_item_evidence.py`; recognition semantics in `detect.py` untouched.
- [x] 6.2 Extend `Templates/TestCapturePanel.svelte` with whole-page + interactive per-item/row evidence and a `Return to mapping and select row` path into `CanonicalMapEditor`; strictly read-only; thresholds labeled `Unqualified` until calibration.
- [x] 6.3 Add `system/app/Assessment/Omr/Canonical/OmrDigitalProofGenerator.php` + `GenerateOmrDigitalProofJob.php` (DRAFT-scoped): start from the draft's final composed exact-size PDF, shade a DETERMINISTIC declared answer vector derived from mapped labels (booklet NOT required), add signed instance QR + display code, and shade a deterministic identity ONLY when a mapped identity grid exists — never a fabricated identity field; booklet compatibility tested separately when a pairing exists.
- [x] 6.4 Add `OmrNoGoMatrixRunner.php` + `RunOmrNoGoMatrixJob.php` (draft-scoped): clean/perspective/rotation/light/blur/glare/low-res/partial/missing-marker/wrong-marker/wrong-instance/superseded/duplicate/multipage-order variants, each producing the declared outcome or a typed rejection; fixtures via `generate_fixture.py` additions.
- [x] 6.5 Add `system/docker/inference/test_corner_false_positive.py` + fixtures (decorative corners, partial markers clipped by edge, border-like patterns, imported supplier borders, dark corner shadows) proving `marker_missing`/`marker_mismatch`, never registration; imported borders are NOT rejected at ingest, only proven non-registering.
- [x] 6.6 Add action `system/app/Assessment/Actions/Omr/Canonical/RunOmrDigitalVerification.php` (+ `RunOmrDigitalVerificationRequest`) moving the draft to `digital_verified` only when proof + matrix pass.
- [x] 6.7 Add tests `OmrDigitalProofTest` (draft-scoped booklet-independent vector; identity ONLY when identity grid mapped — no fabricated identity; booklet compatibility when paired; post-compile hash-parity smoke), `OmrNoGoMatrixTest` (every variant outcome), `OmrItemEvidenceTest` (declared-option-set payload contract in the existing `/v1/layout-verification` response, HMAC envelope retained), `OmrCornerFalsePositiveTest` (python gate), `OmrSourceRectificationTest` (worker seam contract). Stop gate: draft reaches `digital_verified` only via proof + passing matrix.

## 7. Atomic compile and immutable artifacts

- [x] 7.1 Add `system/app/Assessment/Omr/Canonical/OmrAuthoringDraftService.php` (stage transitions incl. `digital_verified → compiled`, lock_version optimistic concurrency) and `OmrTemplateAssetStore.php` (FK-owned draft/version assets, exactly-one-owner check, copy-pin on compile, SHA-256 chain, immutable writes, provenance-preserving GC hooks — version-owned provenance assets never collected).
- [x] 7.2 Add action `system/app/Assessment/Actions/Omr/Canonical/CompileOmrCanonicalDraft.php` (+ `CompileOmrCanonicalDraftRequest`, DRAFT-scoped): requires `mapping_complete` + QUALIFIED marker profile + `digital_verified` (`compile_gate_missing`/`marker_profile_unqualified` otherwise); atomically create immutable template version with status `draft`, derive native bundle from `fieldBlocks`, FREEZE the canonical PDF/bundle, copy-pin assets/evidence to version ownership, store compact `authoring_manifest` on the version, set draft `compiled_version_id` + stage `compiled`; failures leave no partial version (`compile_failed`).
- [x] 7.3 Add tests `OmrTemplateAssetOwnershipTest` (exactly-one-owner via real FKs, immutable writes, copy-pin on compile, provenance never GC'd, GC of unreferenced draft/temp only) and `OmrDraftCompileTest` (atomic compile, gate validation incl. qualified profile, manifest-only version, no partial version on failure, frozen PDF/bundle hash). Stop gate: compile suite green; physical qualification/activation routes unreachable without a compiled version.

## 8. Physical qualification, marker-profile qualification, and activation (version-scoped)

- [x] 8.1 Add `OmrPhysicalQualification.php` + action `system/app/Assessment/Actions/Omr/Canonical/RunOmrPhysicalQualification.php` (+ `RunOmrPhysicalQualificationRequest`, VERSION-scoped — runs AGAINST the compiled template version/PDF): TEMPLATE evidence binds the compiled template version + the ALREADY-QUALIFIED marker profile + canonical renderer version; records printer identity, device, paper, measured output (e.g., marker-center distance vs expected mm), and per-variant outcomes; gates activation (`physical_evidence_missing`); template qualification NEVER re-qualifies the profile (profile qualification is template-independent via the calibration artifact, task 1.15); actual-size verified by measurement, not assumed.
- [x] 8.2 Add action `system/app/Assessment/Actions/Omr/Canonical/ActivateOmrCanonicalTemplate.php` (VERSION-scoped): requires compiled version + qualified marker profile + `physical_qualified` (`activation_gate_missing`); atomically swaps the active version; previous active stays live until commit, activated versions read-only.
- [x] 8.3 Wire `QualifyOmrMarkerProfile` (1.9) to accept calibration-artifact evidence (task 1.15), promote ONE profile to `qualified` (others `retired`), record `qualification_evidence_hash`/`qualified_by`/`qualified_at`, and serialize the single-qualified invariant; qualified profiles become selectable for drafts.
- [x] 8.4 Add tests `OmrPhysicalQualificationTest` (version-scoped after compile, template evidence binding, `physical_evidence_missing`), `OmrMarkerQualificationTest` (template-independent calibration evidence, single-qualified promotion, evidence hash, retirement of other qualified profiles), and `OmrActivationTest` (atomic swap, previous-active-live, read-only). Stop gate: a version cannot reach `active` without compiled artifact + qualified profile + genuine template physical evidence.

## 9. Blank instances, lifecycle, chunked printing, manual recovery

- [x] 9.1 Add `system/app/Assessment/Omr/Canonical/OmrBlankInstanceService.php`: ≥128-bit `NI-<crockford26>` instance codes (26-char Crockford base32 random — entropy ≥128 bits), UNIQUE checksummed short `display_code` (lookup aid only), signed QR via `OmrQrPayload` (keyring), human-readable fallback text, monotonic per-version `page_set_no` (never reused), set lifecycle `printed → captured|superseded|void` (all three terminal; `void` never after `captured`/`superseded`), per-page rows (`printed|captured|void`) with `captured_page_id` FK to `omr_capture_sheet_pages`/`OmrCaptureSheetPage`, set-level `qr_manifest_hash` = SHA-256 of ordered per-page signed payload hashes, `printed` only when the complete page set rendered, set stays `printed` while partially captured, and typed lifecycle errors.
- [x] 9.2 Add action `system/app/Assessment/Actions/Omr/Canonical/PrintOmrBlankInstances.php` (+ `PrintOmrBlankInstancesRequest`) and `system/app/Jobs/RenderOmrBlankInstanceBatchJob.php` via `OmrBlankPrintOrchestrator.php`: chunk of 100, set + per-page manifest and statuses, progress counters, chunk download/ZIP with preserved page order, idempotent retry from pinned inputs (version + page_set_no + chunk + page_no), partial-failure completion with typed per-page errors; failed instance never `printed`, successful instances downloadable. Server-side QR rendering uses the pinned renderer from task 1.11 (research MUST be complete first).
- [x] 9.3 Add actions `SupersedeOmrBlankInstanceSet.php` (explicit whole-set replacement; audited; allowed on partially captured sets — old page captures remain evidence), `VoidOmrBlankInstanceSet.php` (only while `printed`), `RegisterOmrBlankInstanceCapture.php` (+ `RegisterOmrBlankInstanceCaptureRequest`, lives in the grading/capture flow): typed issues `qr_signature_invalid`, `qr_key_unknown`, `duplicate_instance`, `page_already_captured`, `page_missing_from_set`, `tamper_detected`, `template_mismatch`, `superseded_instance`; marker registration remains the recognition authority.
- [x] 9.4 Add action `system/app/Assessment/Actions/Omr/Canonical/ResolveOmrBlankInstanceManually.php` (+ `ResolveOmrBlankInstanceManualRequest`) implementing the TWO-BRANCH contract: payload present but signature/key invalid → HARD `qr_signature_invalid`/`qr_key_unknown` that manual entry MUST NOT override; payload undecodable/absent → capability-gated staff enters the unique checksummed display code, server resolves set/page/template from DB, explicit confirmation required, `instance_resolved_manually` recorded (audited), recognition BLOCKED until confirmed. Add `ResolveOmrBlankInstanceIssue.php` for typed issue triage.
- [x] 9.5 Add staff-facing `BlankInstancePrintPanel.svelte` + `BlankInstanceHistoryPanel.svelte` (set + per-page status, supersede/void actions, manual display-code recovery UI, typed issue display).
- [x] 9.6 Add tests `OmrBlankInstanceLifecycleTest` (terminal rules incl. no void-after-captured/superseded, partial-capture stays `printed` until all pages captured, explicit audited supersede of partial capture, per-page rows, page_set_no monotonicity, typed issues, DB constraint violations) and `OmrBlankPrintChunkTest` (chunking, manifest/ZIP page order, idempotent retry no-duplicate, failed instance never `printed`, successful instances downloadable) and `OmrManualRecoveryTest` (present-but-invalid payload is a hard error manual entry cannot override; undecodable/absent payload resolves via display code with `instance_resolved_manually`, capability + audit, recognition blocked until confirmed). Stop gate: lifecycle suite green; failed chunk never marks a set `printed`.

## 10. Security, audit, ops, routes

- [x] 10.1 Add `system/app/Http/Controllers/Setup/OmrCanonicalTemplateController.php` with DRAFT-SCOPED routes under `/setup/omr/templates/canonical/drafts/{draft}/…` (create/list, show, delete, source/ingest, source/ingest/{run}, source/rectify, source/pages/confirm, compose, overlay, mapping, marker-profile, verify/digital, compile) plus marker-profile routes `/setup/omr/templates/canonical/marker-profiles[/{profile}/calibration-artifact|qualify]` and staff-only `/setup/omr/templates/canonical/diagnostics`; VERSION-SCOPED routes for `/setup/omr/templates/{templateVersion}/verify/physical`, `/activate`, `/blank/print`, `/blank/instances[/{instance}/supersede|void]`; QR capture registration and manual recovery live in the GRADING/capture flow (`/grading/…/omr/blank-instance/register`, `/…/blank-instance/manual-resolve`), not the setup builder.
- [x] 10.2 Gate every route behind existing `CapabilityAuthorizer` with `guidance.scoring.omr`; drafts owner-scoped for edit (authorized staff view); marker-profile qualification and manual recovery additionally capability-gated and audited; downloads re-authorize and stream `private, no-store`; diagnostics staff-only; denied access has no existence leakage.
- [x] 10.3 Add `RecordAuditEvent` calls to every new action (actor, draft/version/profile id, action, hashes) and idempotency keys (`newIdempotencyKey()`, `operations` table) on all mutating actions.
- [x] 10.4 Add `system/app/Jobs/GcOmrCanonicalTempAssetsJob.php` (scheduled GC for UNREFERENCED draft-owned and temp assets past TTL only; version-owned provenance never collected) and wire rate limits (ingest 10/min, print 5/min per staff) plus conversion caps/timeouts and pixel-budget enforcement.
- [x] 10.5 Add `OmrCanonicalAuthzTest` (denied without capability, audited actions incl. manual resolution and qualification, staff-only diagnostics, GC runs, no existence leakage). Stop gate: authz/audit suite green; no route reachable without capability.

## 11. Staged UX

- [x] 11.1 Add `CanonicalWorkflow.svelte` stage shell (NINE stages: Start → Convert/inspect → Prepare page → Overlay → Map → Digital verify → Compile → Physical qualify/Activate → Blank print) with stepper, stage gates blocking forward progress (compile blocked until mapping + qualified profile + digital; physical/activate blocked until compiled; blank print disabled until active), the shell pivoting from the draft to the compiled version at compile, back navigation, and phone view-only with open-on-tablet gate (editor requires ≥1024 px).
- [x] 11.2 Add `SourceIngestPanel.svelte` (adapter source cards, upload, conversion progress/cancel/retry) and `SourcePageConfirmPanel.svelte` (per-page confirm/reject with typed staff-classified reasons; photo quad controls with quality gates) implementing loading/empty/error/blocked/conflict/success/recovery states per design §UX.
- [x] 11.3 Add `CanonicalPagePreparePanel.svelte` (three-column controls/preview/layers, margins, scale/offset/9-point controls with live preview incl. enlargement DPI warnings), `OverlayEditorPanel.svelte` (tag picker from closed set), `MarkerProfilePanel.svelte` (candidate list, selection, qualification evidence), `DigitalProofPanel.svelte` (item evidence + identity-grid note), `CompilePanel.svelte` (atomic compile with gate display), `PhysicalQualificationPanel.svelte` (version-scoped, after compile), `ActivatePanel.svelte`, `BlankInstancePrintPanel.svelte` (quantity/chunks/history/replacement/manual QR recovery) with per-state microcopy, primary + blocked CTAs, unsaved-change guards, focus management, responsive behavior, and the actual-size print instruction/warning on every print surface.
- [x] 11.4 Add browser tests `system/tests/Feature/Setup/OmrCanonicalWorkflowBrowserProofTest.php` covering the nine stages, blocked gates incl. compile/physical sequencing and blank-print-disabled-until-active, conflict banner (lock_version), unsaved guard, phone view-only, keyboard/touch/undo/autosave in `CanonicalMapEditor`, explicit-split UX, actual-size warning, manual recovery UI (two-branch), and Test-capture return-to-row. Stop gate: browser suite green on the seeded canonical draft.

## 12. Migration, parity, and clean cut

- [x] 12.1 Add feature flag `omr.canonical.enabled` (config) and route old builder to remain default until cutover; `OmrPresetSourceAdapter` fully covers solver output. Parity-phase rollback = flag off / previous route (no data dropped).
- [x] 12.2 Add SEMANTIC parity checklist command (e.g. `artisan omr:canonical-parity`) comparing old builder vs canonical preset path for the profile pair and v1–v5 seeds using NORMALIZED transforms from actual page dimensions: same pages/labels/options, physical centers/regions within declared tolerance, normalized review regions within tolerance, deterministic recognition exactness, visual-diff threshold. Byte-identical hashes across render paths are explicitly NOT required and NOT expected; hash equality applies only to repeated canonical runs with identical inputs.
- [ ] 12.3 After parity passes: hide old builder entry points, make the canonical workflow the only authoring entry, then delete old builder code cleanly (no aliases/re-exports/dual builder); keep historical markerless versions immutable and scan-only (`CropPage` unchanged).
- [x] 12.4 Add `OmrCanonicalCutoverTest` (parity gate blocks cutover until checklist passes; after cutover no old-builder symbols remain; markerless scan path unchanged; identical canonical runs produce identical hashes). Stop gate: parity green before any UI change; clean cut after.
- [x] 12.5 Document and test rollback honesty: post-cutover rollback deploys the previous application release while KEEPING all additive canonical tables/data (forward-compatible deployment rollback); migration `down()` is documented and used for clean test/dev teardown ONLY with an explicit data-loss warning (destroys drafts/assets/instances and does not restore deleted code), never as an operational production rollback.

## 13. Seeds and handoff

- [x] 13.1 Add `system/database/seeders/OmrCanonicalDemoSeeder.php` seeding FIVE authoring drafts at useful stages (one per adapter kind: HTML, DOCX, PDF, image, preset) and marker profiles as CANDIDATES only; MAY compile the digitally verified examples; MUST NOT fabricate physical qualification and MUST NOT activate supplier templates — activation happens only with genuine physical evidence; idempotent, no applicant binding; record staff credentials and recreate steps in `openspec/changes/omr-canonical-template-authoring/HANDOFF.md`.
- [x] 13.2 Run `openspec validate omr-canonical-template-authoring --strict`, run focused suites (unit/feature/browser/python) on the seeded data, update the master checkpoint and `HANDOFF.md` with evidence (raster/PDF, four markers from the qualified profile, signed blank-instance QR + display code, digital proof, no-go matrix, physical qualification record incl. printer/device/paper and measured output, marker-profile qualification evidence, server-side QR renderer research outcome, explicit statement that supplier templates remain unqualified/draft).
- [ ] 13.3 Verify the full staged flow end to end on the seeded data (ingest → confirm → compose → overlay → map → explicit split where needed → digital verify → compile → physical qualify → activate → blank print) and record the physical acceptance/marker-profile run in `HANDOFF.md`.

## 14. Unified Template Studio rebuild (frontend-first, contract-frozen)

Contract freeze (verified against code): mapping PUT accepts NATIVE blocks (`origin_x/origin_y/width/height/fieldLabels/direction`, `SaveOmrMappingRequest` + `OmrMappingValidator`); draft raster streams via existing `drafts/{draft}/assets/{asset}` (`raster_url` in show props); `authoring_json` is free-form so parametric component state lives under `authoring_json.studio` with NO backend schema change; server stage machine (9 stages + gates) is UNCHANGED and workspaces are a pure visual grouping over it.

- [x] 14.1 Backend micro-fixes: (a) `CreateOmrAuthoringDraftRequest` auto-sanitizes `code` in `prepareForValidation` plus a custom message; (b) read-only capability-gated `GET drafts/{draft}/booklet-options` returning draft booklet versions with question labels/counts; (c) PHP test asserting the studio bridge fixture JSON passes `OmrMappingValidator` unchanged.
- [x] 14.2 Add `system/resources/js/lib/omrStudioGeometry.ts`: single mm↔px authority mirroring `OmrPageDimensionMath` (300 PPI, mm→px = 300/25.4), parametric→native block emitter, inset-constrained fiducial model, and preview-only rule warnings.
- [x] 14.3 Add `OmrTemplateStudio.svelte` interactive workspace inside the `CanonicalWorkflow.svelte` shell with toolbox, SVG canvas, property inspector, and live geometry validation.
- [x] 14.4 Verification wiring honesty: pre-compile diagnostics = client preview warnings + existing `verify/digital`; worker Test Capture supported with dual-input options (device camera + scan/photo upload); Print Preview in studio labeled layout preview while authoritative actual-size PDFs remain server-generated.
- [x] 14.5 Clean cutover: update `OmrCanonicalWorkflowBrowserProofTest` and authoring tests; run focused suites + `openspec validate --strict`; update `HANDOFF.md`.

## 15. Studio Atomic Persistence & Dual-Input Verification (Hardening H1–H11)

- [x] 15.1 Expand `OmrOverlaySpec::ALLOWED_TAGS` to support `header_title`, `instructions_box`, `signature_box`, `metadata_box`, `custom_text`, `blank_instance_qr` with sanitization for `content`, `name`, and `font_size` persistence.
- [x] 15.2 Add single atomic save endpoint `PUT /setup/omr/templates/canonical/drafts/{draft}/studio` with FormRequest `SaveOmrStudioStateRequest` and action `SaveOmrCanonicalStudioState` saving mapping, custom overlays, and studio state in one DB transaction without cross-invalidation.
- [x] 15.3 Add parametric compose mode in `OmrCanonicalComposer.php` creating clean white 300 PPI rasters with corner fiducials directly from paper dimensions and marker insets.
- [x] 15.4 Update `OmrBlankPrintOrchestrator.php` to position blank instance QR codes dynamically from overlay box reservations with safe top-right fallback.
- [x] 15.5 Implement dual-input test capture (live device camera viewfinder snapshot + drag-and-drop file upload) in Stage 5 of `OmrTemplateStudio.svelte` rendering OMRChecker diagnostic layout rasters and gate badges.

## 16. Sheet Designer — palette, drag/drop, inspector, entry gate (H12)

Rename the Stage-1 "Native Builder" workspace to **Sheet Designer** (one term everywhere): an entry-gate mode chooser before the canvas, blank starting sheet, draggable/click-to-add component palette, removable components, selection-driven inspector with full typography controls, compiled HTML + copyable suite reference, dynamic subject binding to system `aptitude_areas`, and no easy mode switch after edits.

- [x] 16.1 Add `system/resources/js/lib/omrComponents.ts`: typed `OmrComponent` union (`header|candidate_info|instructions|roll|question_grid|custom_text|signature`), `OmrTypography` (`fontFamily/fontSizePt/fontWeight/textColor/textAlign`), `renderFullSheetHtml()`, `componentToSectionData()` (OMR geometry bridge), `createComponent(kind)`, presets (`PRESET_150Q_COMPONENTS`), `COMPONENT_PALETTE`, `FONT_FAMILY_OPTIONS`, and the copyable `COMPONENT_SUITE_CSS` + `COMPONENT_SUITE_PROMPT` suite reference.
- [x] 16.2 Add `StudioTypographyControls.svelte` (bindable `typography` + `onChanged`): font size pt, family select, bold toggle, alignment, color picker with hex readout — used for every text component in the inspector.
- [x] 16.3 Entry gate in `OmrTemplateStudio.svelte`: `hasChosenMode` overlay with Sheet Designer / Upload Document cards before the canvas renders; mode change afterward only via the non-prominent "Change mode" action + confirm modal (`requestSwitchMode`/`confirmSwitchMode`) that warns and clears content.
- [x] 16.4 Sidebar rework (html mode): Visual Designer / Compiled HTML sub-tabs; palette grid (draggable cards with `window.__omrDragKind` fallback + click-to-add); Quick Scaffolds (150-Q/100-Q/50-Q presets + aptitude-area scaffold bound to system `aptitude_areas`); Sheet Components list (select/remove/lock); inspector per selected type; Compiled HTML view with readonly markup textarea + Copy buttons for markup and suite prompt.
- [x] 16.5 Canvas: `ondragover`/`ondrop={handlePaletteDrop}` wrapper; dropped roll/grid components render as interactive blocks in stage 1 (`StudioCanvas` html-mode condition); text components are HTML-only with sidebar-card selection.
- [x] 16.6 Persistence: `components` array is the single source of truth, persisted under `authoring_json.studio.components` via `performSave()`; `onMount` hydrates `components` (falls back to legacy `studio.sections`); `syncComponentsToSections()` derives `sections` + compiled `htmlCode`.
- [x] 16.7 CSRF: `validateCsrfTokens(except: ['setup/omr/studio/*', 'setup/omr/templates/canonical/drafts/*'])` + `csrfToken()` helper (meta tag → XSRF cookie → `window._token`) so the studio PUT never 419s.
- [x] 16.8 Seeds + handoff: `OmrCanonicalDemoSeeder` seeds `CANONICAL-PRESET` (preset) and `CANONICAL-HTML` (html) via `updateOrCreate` with no studio payload so the entry gate shows on a fresh seed; record credentials, recreate steps, and verification evidence in `HANDOFF.md`.
- [x] 16.9 Verify: rebuild + reseed + browser proof (gate → choose Sheet Designer → click-add + simulated DnD add → inspector typography → remove → Compiled HTML markup/suite reference/copy → change-mode confirm modal); focused PHP suites green (44 passed, 218 assertions).

## 17. Sheet Designer / Upload Document parity hardening

- [x] 17.1 Persist selected `source_kind` through the atomic studio save before file ingestion; accept PDF, DOCX, and image uploads without `source_kind_mismatch`.
- [x] 17.2 Reset raster, components, compiled HTML, sections, selection, and pending upload state on deliberate mode changes.
- [x] 17.3 Add typed `ComponentPath` / `ComponentPaletteFilter` contracts, path-filtered component and overlay palettes, and persist `studio.palette_filter`.
- [x] 17.4 Surface shared margins, marker patterns, QR reservation, and filtered overlay controls in both authoring paths; hide bubble/question cards from Upload Document.
- [x] 17.5 Add editable Bubble Pitch and Row Pitch controls to Sheet Designer inspectors and carry component spacing into Stage 3 sections.
- [x] 17.6 Harden source-run polling, stale conversion jobs, and concurrent normalized-page asset deduplication; verify upload → Layout & Overlays.
- [x] 17.7 Verify: 32 frontend tests, 47 focused OMR PHP tests (229 assertions), and browser proof for mode reset, path filtering, spacing handoff, and PNG upload.

## 18. Compiled decorations, QR controls, and editor history

- [x] 18.1 Extend the compiled HTML renderer with optional fiducial marker markup and a `data-omr-overlay="blank_instance_qr"` reservation while keeping signed QR composition server-authoritative.
- [x] 18.2 Add shared QR reservation controls with X/Y fields, lock state, and a 20–50 mm square size slider in Sheet Designer and Stage 2 overlays.
- [x] 18.3 Add bounded snapshot undo/redo for durable studio state, coalesce input/drag edits, reset history on mode/source changes, preserve autosave and lock conflict handling, and add keyboard shortcuts plus toolbar actions.
- [x] 18.4 Verify: 35 frontend tests, build success, compiled decoration browser proof, QR control proof, and toolbar/keyboard undo proof.

## 19. Functional Sheet Designer inputs, appearance, and layers

- [x] 19.1 Extend text-field contracts with input kind, input shape, input fill, background color, and z-order; add a Custom Input Box component.
- [x] 19.2 Render candidate fields with a distinct label region and usable input region; support box, dotted-line, underline, and multiline visual modes in compiled HTML and canvas.
- [x] 19.3 Add component background controls independent from typography color, with transparent/solid/custom values and legacy-state normalization.
- [x] 19.4 Add a compact draggable layer list with raise/lower controls, persisted ordering, and collapsible visibility.
- [x] 19.5 Minimize redundant sidebar copy into a draft-code header and mode tooltip.
- [x] 19.6 Verify: 36 frontend tests, production build, and browser proof for separate input areas, Custom Input Box selection, background controls, compact layers, and zero runtime/request errors.

## 20. Bubble-stage visual bounds and component customization follow-up

- [x] 20.1 Separate Stage 3 bubble-section visual bounds from Sheet Designer component bounds; include labels, rendered bubble diameter, padding, and row gaps in the green wrapper.
- [x] 20.2 Make Row Pitch use the same rendered row height and gap math as the Stage 3 canvas so spacing and wrapper height change together.
- [x] 20.3 Fix target fiducials to use absolute positioning at all four corners and render a distinct ArUco-style pattern instead of the square fallback.
- [x] 20.4 Clear Sheet Designer selection when clicking the canvas background.
- [x] 20.5 Add editable instruction label, ID matrix typography/bubble diameter controls, and Justify alignment.
- [x] 20.6 Verify: 38 frontend tests, production build, Stage 3 row-spacing proof, all-corner marker proof, selection clearing, and zero runtime/request errors.

## 21. Studio autosave serialization and refresh recovery

- [x] 21.1 Serialize all studio PUT saves through one promise queue so autosaves cannot reuse a stale `lock_version` concurrently.
- [x] 21.2 Verify the latest persisted components hydrate after a hard refresh.
- [x] 21.3 Remove the typography control runtime failure path and preserve truthful save status when a request fails.
- [x] 21.4 Verify: refresh regression retains components, captured studio requests return 200, and frontend suite remains green.

## 22. Excalidraw-style Sheet Designer controls

- [x] 22.1 Add a selection set with plain click, Shift-click toggle, blank-canvas clear, and Shift-click layer-list selection while preserving a single inspector target.
- [x] 22.2 Add Delete/Backspace selection removal and Ctrl/Cmd+D duplication with collision-safe IDs, offset geometry, history, and autosave.
- [x] 22.3 Add Alt/Option-drag duplication for one component or a selected group, preserve group movement, and keep locked components selectable but immobile/non-duplicable.
- [x] 22.4 Add copy-drag pointer feedback, keyboard shortcut guards for native text controls, layer bracket shortcuts, and visible interaction guidance.
- [x] 22.5 Verify: 38 frontend tests, production build, strict OpenSpec validation, browser proof for Shift selection, group drag, Delete/Backspace, Ctrl/Cmd+D, Alt-drag, text-control guards, refresh persistence, and zero runtime/failed-request errors.

## 23. Unified QR canvas-item and modifier drag controls

- [x] 23.1 Treat the unique QR reservation as a canvas item in the shared selection set and Layers list; support Shift selection, Delete removal, lock/unlock, and group movement.
- [x] 23.2 Add Shift-drag axis locking across Sheet Designer, QR overlays, and bubble sections; choose the dominant axis after the pointer crosses the drag threshold.
- [x] 23.3 Make Alt+Shift-drag duplicate the selected duplicateable components while preserving the unique QR-reservation constraint.
- [x] 23.4 Verify: 38 frontend tests, production build, QR/component multi-selection, axis-locked QR/group drag, Alt+Shift duplication, QR lock/remove controls, refresh persistence, and zero current runtime/HTTP errors.

## 24. Examinee ID Matrix footprint parity

- [x] 24.1 Make Stage-1 matrix bounds include title, write-in row, padding, bubble diameter, row gaps, and all ten digit rows.
- [x] 24.2 Replace the fixed Stage-1 matrix row gap with the configured Row Pitch-derived gap used by the bounds helper.
- [x] 24.3 Add frontend regression coverage proving the matrix border height tracks the full footprint and expands with Row Pitch.
- [x] 24.4 Verify the supplied overflow reproduction: last row remains inside the border at compact and spacious Row Pitch values, with zero runtime/HTTP errors.

## 25. Matrix pitch and printable-page constraints

- [x] 25.1 Use the content-hug Stage-1 matrix width for canvas placement instead of the legacy stored 58 mm width.
- [x] 25.2 Normalize matrix and grid pitch inputs to the rendered 5.2 mm minimum with explicit UI min/max attributes.
- [x] 25.3 Normalize oversized question grids to printable-page bounds by constraining pitch, row count, and X/Y placement.
- [x] 25.4 Verify: default Examinee ID Matrix reports Row Pitch 5.2 mm, lowering below the floor does not change bubbles, width has no right-side dead area, and oversized grids remain inside the sheet.

## 26. Canonical aptitude-area scaffold

- [x] 26.1 Seed the six Sheet Designer labels: General Ability, Verbal Aptitude, Numerical Aptitude, Spatial Aptitude, Perceptual Aptitude, and Manual Dexterity.
- [x] 26.2 Project only those six canonical areas into the canonical workflow in the declared order.
- [x] 26.3 Make the scaffold create exactly 25 MCQ items per area with contiguous question ranges.
- [x] 26.4 Verify: seeded projection exposes six areas, browser scaffold renders six 25-row grids, focused PHP tests and frontend build pass.

## 27. Scaffold inspector binding and grid controls

- [x] 27.1 Persist the scaffolded aptitude area code and bind the inspector select to it so the selected grid no longer shows “Choose Aptitude”.
- [x] 27.2 Restore configurable question-grid bubble size with bounded 4.2–8 mm input and matching canvas/compiled output.
- [x] 27.3 Add configurable question-label font size with bounded 5–12 pt input and matching canvas/compiled output.
- [x] 27.4 Verify: scaffolded grids show the selected area, 25 rows, bubble-size and label-size controls, 40 frontend tests pass, and the build is green.

## 28. Reliable Sheet Designer → Question Bubbles transition

- [x] 28.1 Bind `activeSectionIndex` into `StudioCanvas` so Stage 3 section selection cannot crash on transition.
- [x] 28.2 Make stage transitions save before committing the new stage and roll back the stage on failed persistence.
- [x] 28.3 Verify component-to-section conversion, Stage 3 matrix rendering, refresh persistence, and zero transition runtime/HTTP errors.

## 29. Roll mapping schema parity

- [x] 29.1 Emit the Examinee ID Matrix as `QTYPE_INT` with ten digit options in the exported JSON schema instead of treating it as MCQ4.
- [x] 29.2 Verify Stage-3/Stage-4 conversion retains roll geometry and produces no mapping validation/runtime error.

## 30. Sheet Designer → bubble-stage visual parity

- [x] 30.1 Keep HTML authoring on the same component render tree across Sheet Designer, Question Bubbles, and Verify & Export instead of replacing it with a second iframe renderer.
- [x] 30.2 Make HTML-mode Stage 3 guides outline the existing components without drawing duplicate bubbles or floating Q-range/ID labels.
- [x] 30.3 Synchronize Stage 3 section edits back into their source components and preserve question-grid bubble diameter through component-to-section conversion.
- [x] 30.4 Verify Draft 1 keeps all 11 component IDs and styles byte-for-byte identical across Stage 1 → Stage 3, with seven empty-text guides, no preview iframe, and zero runtime/request failures.

## 31. Engine-parity correction — implementation plan

### Cut A — Freeze engine behavior and establish one materializer

- [x] 31.1 Freeze current pinned behavior with characterization fixtures for native `Template`/`FieldBlock` expansion, `CropOnMarkers`, marker-center `pageDimensions`, bounded field shifts, clean aligned return bytes, and separate `draw_template_layout` output; detection math and the two owned patches remain unchanged.
- [x] 31.2 Add a server-validated `CanonicalSheetDefinition` for paper, visual components, explicit section-header visibility/text, OMR block intent, overlay slots, and qualified marker profile/insets; normalize recognition geometry to integer canonical pixels at 300 PPI.
- [x] 31.3 Extract reusable bundle assembly from `OmrCanonicalNativeBundleCompiler` and add one `CanonicalArtifactMaterializer` that emits the 300-PPI PNG, exact-size image-backed PDF, non-blank reference page, native `template.json`/`config.json`, exact marker asset, expanded review manifest, and one artifact-set hash.
- [x] 31.4 Make compile byte-promote the passing candidate artifact set instead of regenerating it; any definition, renderer, marker, mapping, engine, patch, config, or output-byte change invalidates candidate verification.

### Cut B — Make Question Bubbles the explicit accuracy boundary

- [x] 31.5 Add a draft candidate-preview action: save definition → materialize artifacts → load with the pinned native template path → derive native expanded bubbles → run the clean page through the existing signed `/v1/layout-verification` seam → return canonical underlay, engine overlay, hashes, and typed failures.
- [x] 31.6 Generalize `OmrDiagnosticRequestBuilder` over a pinned diagnostic-geometry value object so draft candidates and compiled versions share the same envelope, marker asset carriage, review-manifest derivation, unsafe-shift gates, and result validation.
- [x] 31.7 Replace Stage-3 client bubble rendering with the server canonical page underlay plus native expanded mapping guides only; remove compiled-HTML substitution and client bubble-origin calculations from the accuracy path.
- [x] 31.8 Make section headers explicit definition fields and render them through the same server path as the page/PDF/reference artifact; prove a label cannot first appear after Stage-1 transition.


### Cut C — Execute real digital and physical proof
- [x] 31.9 Replace `OmrNoGoMatrixRunner`'s expected-equals-actual loop with a queue-safe real worker job boundary for clean, perspective, rotation, uneven light, blur, glare, low resolution, partial page, missing/wrong marker, wrong/superseded/duplicate instance, and multipage order; persist input/output hashes, aligned/overlay assets, typed outcomes, and timings. The current request remains synchronous through `dispatchSync` so the existing lifecycle response is truthful; the job payload is asset-ID based and safe to switch to queued polling.
- [x] 31.10 Move a draft to `digital_verified` only when the clean answer vector is recovered exactly and every no-go variant produces its declared real outcome; worker unavailable/timeout/decode/hash mismatch is a failed or retryable variant, never a synthetic pass.
- [x] 31.11 Keep physical Test Capture version-scoped after compile; default Stage 4 to the worker-returned clean aligned raster, retain the upload as separate forensic input, expose the engine overlay/evidence tabs, and gate qualification on marker count, dimensions, unsafe shifts, and recorded printer/device/paper measurements.
### Cut D — Honest lifecycle, export, and proof

- [x] 31.12 Make Stage 4 lifecycle-aware: show candidate materialization/digital verification/compile gates, disable physical Test Capture before `compiled_version_id`, remove the nonexistent uncompiled fallback URL, and surface typed corrective actions.
- [x] 31.13 Stream the immutable bundle's actual `template.json` with capability reauthorization and `private, no-store`; relabel client JSON as a non-authoritative mapping preview and remove native-template wording from it.
- [x] 31.14 Delete blank reference generation and obsolete duplicate HTML/green-sheet accuracy paths after all callers use the materializer; no compatibility shim or second geometry compiler remains.
- [x] 31.15 Add parity coverage: repeated materialization byte/hash stability; definition→PNG/PDF/reference/native bubble coordinate equality; exact marker asset/inset/frame equality; candidate→compiled byte equality; real worker clean/no-go results; aligned-image dimensions/hash; actual native download; and rejection of stale candidates.
- [ ] 31.16 Browser-prove one canonical draft end to end: Stage 1 header choice → Stage 3 server underlay/native overlay → digital matrix → immutable compile → uploaded printed/photo fixture → worker aligned raster/overlay → actual `template.json`, with zero hidden labels, geometry drift, failed requests, or fabricated qualification.

- [x] 31.17 Do not start Cut B until Cut A proves one artifact set; do not mark digital verification passed until Cut C calls the real worker; do not activate until a qualified marker profile and genuine version-bound physical evidence exist. Production enablement remains blocked on the approved sheet/key, representative labeled corpus, and operational qualification.
- [x] 31.18 Make the printable artifact visible before mapping and stable across the boundary: add Sheet Designer `Print Preview` from the materializer; preserve it across navigation-only saves; reuse it entering Question Bubbles; keep it as the full-paper base for `Print Sheet` / marker-frame `Engine Aligned` / `Engine Overlay`; retain the QR reservation; use the same Question Bubbles interface for designed and uploaded sources.

## 32. Printable artifact → CropOnMarkers → mapping hardening

- [x] 32.1 Freeze the three authorities: full-paper `CanonicalArtifactSet` is print truth; native marker-frame `fieldBlocks` are recognition truth; OMRChecker aligned/overlay outputs are read-only diagnostics. Record the paper↔frame inset transform and QR-reservation rule.
- [x] 32.2 Expose the materializer in Sheet Designer as `Print Preview`; preserve byte-identical candidates across navigation-only saves; invalidate on any printable/source/mapping/profile/inset change; reuse the same candidate entering Question Bubbles.
- [x] 32.3 Run the exact candidate through signed `/v1/layout-verification` with `CropOnMarkers`, the selected marker bytes, native config/template, and hash checks; retain full paper as the default while aligned/overlay images stay inside the marker frame.
- [x] 32.4 Give Question Bubbles one source-agnostic interface: `Print Sheet`, `Engine Aligned`, `Engine Overlay`, QR reservation, native guides, typed failure/recovery. Sheet Designer and uploaded sources differ only before materialization.
- [ ] 32.5 Add the source matrix acceptance suite for Sheet Designer, HTML, DOCX, PDF, image, and preset: confirmed source → printable raster/PDF → CropOnMarkers → aligned dimensions → overlay → mapping guides, with no source-specific Question Bubbles branches.
- [ ] 32.6 Browser-prove one designed source and one uploaded source through preview reuse, edit invalidation/recovery, mapping save, digital verification, compile-byte promotion, and actual native download; keep physical qualification/activation blocked until genuine evidence exists.

## 33. Review findings

- [x] [Review][Patch] Revalidate the artifact/profile and lock state after long-running digital verification before setting `digital_verified`. [`RunOmrDigitalVerification.php`]
- [x] [Review][Patch] Revalidate the locked draft and current qualified marker geometry before promoting a candidate at compile. [`CompileOmrCanonicalDraft.php`]
- [x] [Review][Patch] Prevent failed idempotent digital/compile attempts from replaying as false HTTP success. [`OmrCanonicalTemplateController.php`]
- [x] [Review][Patch] Enforce draft ownership for workflow and draft-asset reads, not only mutation endpoints. [`OmrCanonicalTemplateController.php`]
- [x] [Review][Patch] Bound and terminate isolated raster mutation subprocesses and clean partial proof/evidence assets on failure. [`OmrNoGoMatrixRunner.php`, `RunOmrDigitalVerification.php`]
- [x] [Review][Patch] Restore the floating sidebar as a workspace sibling instead of a child of the bottom canvas-controls bar; browser geometry must remain 350 px wide and viewport-height bounded. [`OmrTemplateStudio.svelte`]
- [x] [Review][Patch] Separate Question Bubbles correctly: `Print Sheet` stays the immutable server raster; unlocked native mapping guides edit the same `template.json` recognition coordinates without moving printed content; locked guides cannot move; lock-only saves preserve the passing candidate; normal mapping edits retain the raster and show one amber saved/stale-diagnostics status—not a red error; Test & Verify reruns diagnostics and clears that status; genuine mapping refusals remain typed 422. [`StudioCanvas.svelte`, `StudioBubblePanel.svelte`, `OmrTemplateStudio.svelte`, `SaveOmrCanonicalStudioState.php`, `OmrCanonicalTemplateController.php`]
- [ ] [Review][Decision] Define evidence-backed expected outcomes and perturbation severities for perspective, rotation, uneven light, blur, glare, and low-resolution matrix cases; current real worker accepts them, so digital verification honestly blocks.
- [ ] [Review][Decision] Resolve the one-active-OpenSpec conflict between `omr-canonical-template-authoring` and `omr-profile-test-readiness` before further feature implementation.

## 34. Source-agnostic Recognition Mapping and physical test UX

- [x] 34.1 Rename the bubble stage to Recognition Mapping and render the same translucent MCQ/ID recognition overlay for Sheet Designer, image, PDF, DOCX, HTML, and preset candidates.
- [x] 34.2 Keep printable components and recognition field-block coordinates separate: moving a guide updates mapping only, keeps the canonical raster mounted, and requires an explicit recognition test.
- [x] 34.3 Persist guide lock state as editor metadata without invalidating artifacts; prevent guide collisions client-side so ordinary dragging cannot emit repeated 422 autosave failures.
- [x] 34.4 Replace the compressed `Single Q`/`Add Section` row with Stage-1-style Single question and Question range component cards.
- [x] 34.5 Separate `Run recognition test` from `Continue to Verification`; label the three views `Print Sheet`, `Aligned Check`, and `Recognition Overlay`.
- [x] 34.6 Remove native `template.json` download and client mapping JSON controls from normal staff UI; retain the authenticated endpoint for diagnostics and automatic scanner use.
- [x] 34.7 Expose the canonical candidate PDF in Verification & Test Capture with 100%/Actual Size instructions while retaining camera and uploaded-photo capture after immutable compile.
- [x] 34.8 Prove designed and uploaded candidates render the same recognition UI; prove collision drag is refused without 422; prove Verification shows print/camera/upload and no native download.
- [x] 34.9 Derive client overlay rings from expanded native field labels and exact paper-space bubble rectangles; prove MCQ25 renders 100 rings, Examinee ID renders 7×10 rings, and remove overlay labels/padding that obscured printed content.
- [x] 34.10 Remove the legacy `margin + 15 mm` top clamp from Recognition Mapping; enforce the normal printable margin uniformly and browser-prove the Examinee ID guide moves upward and restores without errors.
- [x] 34.11 Bind the shared Studio zoom state into `StudioCanvas`, replace the misleading hard-coded `Fit (78%)` label, and browser-prove Zoom In enlarges the sheet and Zoom Out restores it.
- [x] 34.12 Reduce the global utility bar to Undo, Redo, save status, and Save; collapse stage labels to numbered tooltips at narrow widths so essential controls remain visible.
- [x] 34.13 Move marker/profile detail into a Page registration disclosure in Recognition Mapping, explain its registration-only role, and hide comparison-only candidates from normal staff authoring.
- [x] 34.14 Theme workspace, sidebar, inspector, mapping, verification, inputs, and viewport controls with semantic light/dark tokens while keeping the physical printable sheet white.
- [x] 34.15 Browser-prove dark surfaces and white paper at desktop, essential Save visibility/no horizontal overflow at 1024px, desktop step labels, and comparison-profile absence.
- [x] 34.16 Validate recognition-block overlap in the browser before atomic save so invalid coordinate inputs show inline recovery and emit no failing PUT/422 request.
- [x] 34.17 Replace the hard-coded blue OMR information row with a theme-aware neutral Recognition guides explanation.
- [x] 34.18 Normalize Studio validation responses into a structured sidebar list, keep the toolbar status concise, prevent MouseEvent leakage into `source_kind`, and prove manual Save returns 200 while invalid overlap emits no PUT.

## 35. Shared verification kernel, physical challenge, and scanner evidence repair

### Cut A — Freeze behavior and make this the sole implementation owner

- [x] 35.1 Mark `omr-profile-test-readiness` implementation work paused/read-only and record this change as the sole active OMR implementation owner; copy no tasks blindly—cross-reference only the pinned worker/bundle/Test-capture evidence inherited from it.
- [x] 35.2 Add characterization fixtures for `TestCaptureOmrTemplate`, canonical candidate preview, `RunOmrSheetAnalysis`, `ActivateOmrConfiguration` projection rows, `Capture.svelte` template item projection, and `OmrRegionCrop`; record current pass/fail behavior and reproduce the broken/missing canonical per-item options/regions before refactoring.
- [x] 35.3 Define and fixture the no-go policy table for clean, mild perspective/rotation/light/blur/low-resolution expected exact recovery versus severe/partial/missing/wrong marker expected typed rejection. Stop: do not make the matrix green by changing expectations without fixture evidence.

### Cut B — Extract one template geometry projection and repair scanner inputs

- [ ] 35.4 Add `system/app/Assessment/Omr/OmrTemplateGeometryProjector.php` with `project(OmrAnswerSheetTemplateVersion $version, array $reviewManifest): GeometryProjectionResult`; move version-owned page/area/question/option persistence out of `ActivateOmrConfiguration`, including normalized regions, declared option order, semantic area/question identity, projection hash, idempotent identical retry, and stale/different-manifest rejection.
- [ ] 35.5 Make predecessor `ActivateOmrConfiguration` call `OmrTemplateGeometryProjector`; delete its duplicate projection implementation after all tests use the module.
- [ ] 35.6 Make both canonical compile paths call the same projector inside the compile transaction after version creation and before draft commit; failure SHALL remove the partial version/assets and leave the draft uncompiled.
- [ ] 35.7 Add `system/app/Assessment/Omr/Queries/OmrReviewGeometryQuery.php`; return page/question/declared-option normalized regions from template-owned projection and merge correct options from the pinned booklet by validated area code + question number/position coverage.
- [ ] 35.8 Replace `GradingController::omr()`'s booklet-question→optional-templateQuestion geometry projection with `OmrReviewGeometryQuery`; keep roster, applicant, batch, and score projections unchanged.
- [ ] 35.9 Add typed `geometry_projection_missing|geometry_projection_stale|pair_geometry_coverage_mismatch|pair_option_set_mismatch` failures and block pair activation/analysis rather than emitting `[0,0,1,1]` fallback regions.
- [ ] 35.10 Add feature tests proving canonical compile creates one idempotent page/area/question/option projection, MCQ4/MCQ5/int options are complete, compatible booklet merge returns exact correct options, and grading props contain nonzero exact regions without relying on `template_question_id`.

### Cut C — Define the deep shared diagnostic interfaces

- [ ] 35.11 Add `OmrDiagnosticTarget` interface exposing target kind/id, page count, native template/config/assets, review manifest, marker/profile identity, and artifact/native/manifest/config/mapping hashes; document rehearsal/qualification capability in the interface invariant.
- [ ] 35.12 Add `CandidateArtifactDiagnosticTarget` and `CompiledVersionDiagnosticTarget` adapters; candidate adapter fails stale draft/artifact hashes and reports `qualification_capable=false`; version adapter reads only pinned immutable version assets and reports `qualification_capable=true`.
- [ ] 35.13 Add immutable `OmrProofPlan` value object and `ProofPurpose` enum (`digital`, `quick_rehearsal`, `full_qualification`) with plan version, deterministic seed, challenge code, expected vector, calibration digits, per-page instructions, target hashes, and canonical plan hash.
- [ ] 35.14 Add `OmrDiagnosticEvidence` value object/projection containing captured/aligned/overlay assets, gates, marker matches, field shifts, declared-option item/ID evidence, expected/detected values, exact/ambiguous/missing summary, timings, hashes, safe issues, and calibration status; prohibit applicant/score mutation fields.
- [ ] 35.15 Add `OmrTemplateDiagnosticRunner::verify(OmrDiagnosticTarget $target, string $bytes, string $mime, ?OmrProofPlan $plan): OmrDiagnosticEvidence`; reuse `OmrDiagnosticRequestBuilder`, `OmrCheckerDetection`, `OmrDetectionPort`, `OmrLayoutVerificationPort`, unsafe-shift gates, and response hash/HMAC validation. No second detector or threshold implementation.
- [ ] 35.16 Add contract tests running candidate and compiled adapters through the same fake/real-worker seam and asserting identical normalized evidence shape, target/hash identity, typed failures, and candidate qualification refusal.

### Cut D — One deterministic proof planner and renderers

- [ ] 35.17 Add `OmrProofPlanner::plan(OmrDiagnosticTarget $target, ProofPurpose $purpose, string $planVersion='v1'): OmrProofPlan`; expand native labels through `NativeBundle::expandLabel`, use artifact/native hash + purpose + version as seed, balance declared options, cover every page, and generate calibration digits only for a mapped ID grid.
- [ ] 35.18 Refactor `OmrDigitalProofGenerator` to consume `OmrProofPlan`; keep machine-filled exact PDF/raster rendering and signed calibration QR, remove its internal modulo pattern ownership, and preserve existing proof hashes through the plan hash.
- [ ] 35.19 Add `OmrPhysicalChallengeRenderer` producing (a) exact printable challenge sheet with signed calibration QR/display code and no browser geometry, and (b) separate grouped human shading guide HTML/PDF; quick mode covers first/middle/last + every option/section/ID column, full mode assigns every expanded label.
- [ ] 35.20 Add unit/golden tests for repeated-plan determinism, option balance, full label coverage, no identity when unmapped, stable guide copy, multi-page order, challenge QR verification, and byte/hash stability of repeated rendering.

### Cut E — Durable verification runs and truthful progress

- [ ] 35.21 Add migrations/models for `omr_template_verification_runs`, `omr_physical_challenges`, and `omr_template_verification_captures` with real FKs, exactly-one draft/version target CHECK, status/phase/mode CHECKs, positive attempt/version constraints, private asset references, hashes, safe error, actor/timestamps, and no applicant/score FK. Define row/asset deletion order and indexes for target+status, run+attempt, challenge code, and active-run lookup.
- [ ] 35.22 Add `StartOmrTemplateVerification` action + FormRequest; idempotently create a run pinned to current target/proof-plan hashes, audit it, dispatch `RunOmrTemplateVerificationJob` after commit, and return `202 {run_id,status_url}`.
- [ ] 35.23 Add `OmrTemplateVerificationStatus` query + capability/owner-scoped GET route returning phase, real completed/total counts, current variant, safe issue, result summary, available challenge/capture actions, run version, and evidence URLs with `private, no-store`. The run/evidence rows are the sole resume authority; never reconstruct progress from operations/jobs.
- [ ] 35.24 Refactor `RunOmrNoGoMatrixJob`/digital verification into the durable job: persist `preparing_proof → clean_recovery → variant_matrix`, update progress after each actual variant, compare clean/mild exact vector, require severe typed outcomes, store evidence assets, and set `passed|failed` without a long synchronous HTTP request.
- [ ] 35.25 Add retry/cancel semantics: retry creates the next attempt against the same pinned plan; explicit new challenge creates a new plan hash; stale target fails closed; worker unavailable/timeout preserves completed evidence and exposes retry; no pause.
- [ ] 35.26 Add transition/idempotency/concurrency tests for every run phase, duplicate start replay, stale target, retry attempt number, worker timeout, partial evidence preservation, forbidden cross-owner read, and audit events.

### Cut F — Physical challenge capture and derived qualification

- [ ] 35.27 Add `CreateOmrPhysicalChallenge` action + request/route for quick/full mode; require current digital pass, render/store sheet+guide assets, persist expected vector/plan hash/signed calibration-purpose QR, transition run to `awaiting_print|awaiting_capture`, and return print/guide URLs.
- [ ] 35.28 Add shared capture submission action accepting JPEG/PNG/WebP/scanned PDF, reusing source preflight limits and `OmrTemplateDiagnosticRunner`; persist forensic/aligned/overlay assets and normalized evidence per attempt, compare detected vector exactly to plan, and transition `analyzing_capture → needs_review|passed|failed`.
- [ ] 35.29 Add camera/upload equivalence, wrong challenge/template/QR purpose, partial page, missing marker, ambiguous/missing answer, unsafe shift, image hash, repeated capture attempt, multi-page, and expected-vector comparison tests.
- [ ] 35.30 Replace the physical “matrix passed” checkbox path: `RunOmrPhysicalQualification` accepts only a compiled full-challenge run id and derives pass from exact recovery + gates + qualified profile + printer/device/paper/measurements; candidate or quick run returns typed refusal.
- [ ] 35.31 Wire `RegisterOmrBlankInstanceCapture` into canonical grading upload before analysis request construction; distinguish calibration challenge QR purpose from blank-instance QR, verify signed template/page against batch pair, add audited absent/unreadable manual recovery before recognition, and hard-reject present-invalid or mismatched signed QR.
### Cut G — Extract shared frontend modules without moving workflow authority

- [ ] 35.32 Move `OmrRegionCrop.svelte` to `resources/js/Components/Omr/Diagnostics/OmrRegionCrop.svelte` using LSP/file-aware import updates; preserve lazy aligned-image crop behavior and add declared-option/expected/detected states independent of grading correctness.
- [ ] 35.33 Add deep `OmrDiagnosticEvidencePanel.svelte` owning captured/aligned/recognition tabs, gate summary, `problems|all` filter, item and ID crop cards, expected/detected/mean/threshold/shift/hash display, loading/empty/error states, and optional return URL. Its only inputs are normalized evidence + labels/actions; it emits no domain mutation.
- [ ] 35.34 Add `OmrCaptureInput.svelte` owning camera lifecycle, camera switch, file picker/drop, accepted MIME/size copy, preflight display, capture/retry, and one `submit(File)` callback; no run, grading, qualification, or applicant knowledge.
- [ ] 35.35 Replace duplicated evidence/capture markup in `Templates/TestCapturePanel.svelte` and `Canonical/StudioExportVerifyPanel.svelte` with the shared modules; delete obsolete local state/functions/imports after parity.
- [ ] 35.36 Adopt `OmrDiagnosticEvidencePanel` in grading `Capture.svelte` review while retaining gallery/session/applicant/issues/confirm/score orchestration. Replace the existing per-item table crop source with aligned image + `OmrReviewGeometryQuery` option regions; do not redesign unrelated grading screens.
- [ ] 35.37 Add frontend contract tests for MCQ4/MCQ5/int evidence, expected≠detected, ambiguous/missing, failed crop, problems filter, hashes/gates, camera unavailable/upload fallback, and builder/scanner equivalent evidence rendering.

### Cut H — Stage 3 experience

- [ ] 35.38 Rebuild Verification & Test Capture as five visible checkpoints: Digital engine check → Physical challenge → Capture printed challenge → Evidence review → Qualification. Each checkpoint shows purpose, prerequisites, persisted phase/status, actual next action, retry/recovery, and completed evidence.
- [ ] 35.39 Add real polling for active verification run with bounded interval/teardown; show actual phase/variant and completed/total only; no fake percentages. Reload/re-login SHALL restore the current run and evidence.
- [ ] 35.40 Add challenge mode selector, printable sheet and separate shading guide, grouped expected marks, calibration-ID disclaimer, Actual Size instruction, camera/upload capture, captured/aligned/overlay tabs, gate summary, exact/ambiguous/missing totals, problems-first item list, and Return to Recognition Mapping.
- [ ] 35.41 Keep compile/qualification gates explicit: candidate rehearsal labels `Rehearsal only`; full compiled challenge enables derived qualification only after genuine evidence; activation remains unavailable until qualification.

### Cut I — Acceptance, cleanup, and handoff

- [ ] 35.42 Add focused backend suites `OmrProofPlanTest`, `OmrTemplateGeometryProjectorTest`, `OmrTemplateVerificationRunTest`, `OmrPhysicalChallengeTest`, and canonical/scanner integration tests using the real PostgreSQL constraints and private asset store.
- [ ] 35.43 Extend pinned inference characterization for expected exact mild variants and severe typed rejection, item/ID evidence option cardinality, aligned/overlay hashes, and QR/template mismatch; do not change pinned recognition math without a separate evidence-backed decision.
- [ ] 35.44 Browser-prove one designed and one uploaded candidate through digital progress, quick print/guide, camera or uploaded capture, aligned/overlay/per-item review, return-to-mapping, retry/reload; browser-prove one compiled full challenge through genuine physical qualification without fabricated evidence.
- [ ] 35.45 Browser-prove grading scanner with a canonical compiled template–booklet pair: signed QR resolves version, all item/ID crops use aligned geometry, declared options and correct answers are distinct, problems filter works, and review/confirm writes the same score contract as before.
- [ ] 35.46 Remove old duplicated diagnostic code, dead JSON shapes, optional template-question geometry fallback, manual physical-pass checkbox, synchronous digital verification response assumptions, unused imports/routes, and compatibility aliases after all callers migrate.
- [ ] 35.47 Run focused Pest/Vitest/python/browser/build plus strict OpenSpec and diff checks once; update demo seeder with a deterministic non-qualified verification run and printable challenge only (never physical_qualified), then update `HANDOFF.md` with credentials, reset command, URLs, challenge instructions, evidence hashes, known blockers, and exact reproduction steps.
