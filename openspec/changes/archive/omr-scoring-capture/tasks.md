# Tasks: OMR Scoring Capture

> **Status (2026-08-12):** Phase A online Manual + Auto capture is shipped and browser-proven with `FakeOmrDetection`. Phase B below delivers a development-qualified, pinned OMRChecker adapter and deterministic sheet pack. Institutional production activation remains blocked.
>
> **Why this file was replaced:** the prior checklist contained 47 literal `[lean-ctx: omitted …]` placeholders and could not be a safe junior handoff. This checklist is reconstructed from the current code, `design.md` §§16–23, and normative OMR-18–OMR-26.
>
> **Execution rule:** complete sections in order. Do not start the adapter before §2 passes; do not switch the driver before §5 passes; do not create a qualification before §7 passes; never call synthetic evidence “production-ready.”

## 0. Verified starting point

- [x] 0.1 Phase A provider envelope exists: `OmrDetectionRequest/Result`, `OmrPinnedSnapshot`, validator, deterministic fake, queue job, proposal/issues persistence
- [x] 0.2 `OmrDetectionEnvelopeTest` passes 10 tests, including the fixed `OmrRegion` snapshot-hash path
- [x] 0.3 Camera/file capture, Manual → review, Auto → queue/gallery, camera selector, protected images, and review UI are runnable
- [x] 0.4 `OmrDemoSeeder` creates `SYN-OMR-ONE-PAGE` v1 and `SYN-OMR-BOOKLET-A` v1 with template/question/option rows
- [x] 0.5 OMRChecker upstream baseline is pinned to `de3f6982822895491be760325fd43e65e90fa293` and its MIT license/source behavior is recorded
- [x] 0.6 Phase B architecture, requirements, story trace, page states, gates, and handoff are recorded in this change

## 1. Establish the single native geometry authority

- [ ] 1.1 Add a regression test proving no hand-calculated geometry path remains: seeders contain no coordinate math; every relational projection carries projection_source=activation_manifest; template rows trace to native bundle hashes (the historical overlap proof is recorded as a documented snapshot, not a live test)
- [x] 1.2 Define the immutable native bundle contract: `template.json`, `config.json`, required marker/reference assets, page dimensions, exact engine/patch identity, file hashes, and bundle hash
- [x] 1.3 Add forward-fix persistence for the native bundle identity/content and activation-derived review-manifest JSON/hash; keep historical migrations unchanged
- [x] 1.4 Mark relational area/question/option rows as activation-derived immutable projections, not independently editable layout authority
- [x] 1.5 Author one visibly synthetic OMRChecker-native bundle with stable question labels, options, alignment requirements, and bubbled applicant-number positions using symbols `0..9`
- [x] 1.6 Keep free-form name/birthdate OCR out of Phase B; mark undeclared identity fields unsupported/manual
- [x] 1.7 Remove the old seeder's hand-authored coordinate calculations; do not repair, retain, or add a NEXIAM-to-OMRChecker reverse compiler
- [ ] 1.8 Prove the synthetic bundle cannot satisfy production qualification

**Gate 1:** one immutable native bundle exists as the only detection-geometry source; the broken duplicate coordinate source is gone.

## 2. Pin OMRChecker and derive the NEXIAM review manifest

- [x] 2.1 Record and verify the upstream source archive SHA-256 for commit `de3f6982822895491be760325fd43e65e90fa293`
- [x] 2.2 Retain the MIT notice and record a dependency/license review for the exact worker image contents
- [x] 2.3 Create a fully pinned Python 3.12 dependency lock with hashes and `opencv-python-headless`; remove floating `>=` resolution from the image build
- [x] 2.4 Pin the Python base image digest and fail the build on source/patch/dependency checksum mismatch
- [x] 2.5 Add only the initial owned patches: remove import-time monitor/GUI dependence in provider mode, and fix or disable the stale/unbound `x, y` overlay-drawing branch
- [ ] 2.6 Add focused tests, rationale, exact upstream references, and checksum verification for each patch; prove they do not alter geometry expansion, preprocessing order, thresholds, or bubble classification
- [x] 2.7 Set `MPLBACKEND=Agg` and prove import/activation with no `DISPLAY`, monitor, `namedWindow`, `imshow`, or GUI package
- [x] 2.8 Implement an isolated activation tool that loads the native bundle through the exact pinned engine, expands every `FieldBlock`/`Bubble`, and emits the derived NEXIAM review manifest
- [x] 2.9 Validate stable label/option coverage, uniqueness, contiguity, finite/in-bounds/non-overlapping bubbles, identity positions, and deterministic output; use typed `template_invalid|manifest_derivation_failed` failures
- [x] 2.10 Persist the manifest hash and relational projections atomically during `ActivateOmrConfiguration`; repeat derivation must be byte-identical
- [x] 2.11 Add tests proving every expected option maps to exactly one native bubble and no code path reverse-compiles NEXIAM coordinates into OMRChecker input

**Gate 2:** the pinned engine deterministically derives one valid review manifest from the native bundle, and the manifest cannot drift independently.

## 3. Clean-cut the provider envelope and durable run boundary

- [x] 3.1 Introduce `nexiam.omr.detection.v2` across request, result, fake provider, analyzer, persistence, and tests; remove v1 callers rather than adding a compatibility alias
- [x] 3.2 Remove `correctOptionCode`, answer-key data, correctness, score, and area totals from provider request/result DTOs
- [x] 3.3 Move selected-option × pinned-answer-key comparison and area-score derivation entirely into NEXIAM after result validation
- [x] 3.4 Add native bundle/config/derived-manifest/patch hashes, expected stable labels/options, decoded MIME/dimensions, engine/commit expectations, response version, and bounded diagnostics to v2
- [x] 3.5 Add canonical aligned-image MIME/dimensions/hash/bytes, field-block shifts, `confidence: null`, and `calibration_status: unqualified` to the real-provider success contract
- [x] 3.6 Reject answer-key/correctness/score fields, non-null real-engine confidence, hash/engine mismatches, item coverage errors, invalid identities/issues/shifts, and malformed aligned-image evidence
- [x] 3.7 Replace the current batch-named analysis implementation with a per-sheet `RunOmrSheetAnalysis` service and migrate every caller
- [x] 3.8 Make both Manual and Auto uploads create one `pending` processing run and dispatch only after commit
- [x] 3.9 Add current-run ID, claim token/expiry, expected sheet version, and actor/scope rechecks before invocation and persistence
- [x] 3.10 Make Manual wait/poll the same run while Auto restores capture immediately; a disconnect must not cancel or duplicate work
- [x] 3.11 Map retryable outcomes to the same run/backoff; exhaustion becomes terminal/manual recovery; staff Retry creates the next run
- [x] 3.12 Test duplicate delivery, stale result, actor revocation, browser disconnect/resume, retry-vs-remove, review/close races, forged provider key/score, and invalid aligned-image evidence
- [x] 3.13 Keep `FakeOmrDetection` deterministic under v2 and prove the existing fake demo remains green

**Gate 3:** the provider cannot influence correctness, and only a current claimed run with a validated bundle/manifest identity can persist a proposal.

## 4. Build the deterministic sample answer-sheet pack

- [ ] 4.1 Define `nexiam.omr.fixture-manifest.v1` with generator seed/version, native-bundle/derived-manifest/booklet/config/engine/patch hashes, file hashes, dimensions/MIME, transforms, expected identity, expected per-item observation, issues, and tags
- [ ] 4.2 Extend `system/docker/inference/generate_fixture.py` as the single renderer (input = native bundle + derived manifest, never a second coordinate table); add the §4.7 transforms and PDF/SVG/PNG emission there
- [ ] 4.3 Emit deterministic printable SVG source plus PDF, PNG, and blank alignment-reference derivatives
- [ ] 4.4 Render question/area labels, native bubble outlines, registration/alignment features, and bubbled applicant-number fields from the derived manifest
- [ ] 4.5 Generate clean blank, all-correct, all-incorrect, mixed-correctness, and single-mark baselines
- [ ] 4.6 Generate multi-mark, faint, erased/ghost, crossed-out, unreadable, stray/out-of-region, identity-unknown/conflict, wrong-template, and malformed cases
- [ ] 4.7 Generate deterministic rotation, perspective/skew, crop, blur, shadow, low-light, JPEG-compression, resolution, and combined camera transforms
- [ ] 4.8 Record uncertain-case ground truth and mandatory-review expectations without inventing confidence or assuming upstream can classify every human mark type
- [ ] 4.9 Add `omr:fixtures:generate` as a thin PHP command orchestrating the exact `docker compose run inference` pattern (mirroring HANDOFF) with seed/version/output/overwrite flags; Python-side deterministic-regeneration unit test
- [ ] 4.10 Commit the compact acceptance subset under `tests/fixtures/omr/` (total ≤5MB; PNGs downsampled ≤1200px long edge; PDF/SVG preferred); full benchmark pack regenerated on demand and gitignored; manifest documents commands + hashes
- [ ] 4.11 Test deterministic regeneration, file/manifest hashes, derived-geometry containment, and explicit drift after bundle/manifest/engine/patch changes

**Gate 4:** the acceptance pack is byte-reproducible from the native bundle, derived manifest, and exact ground truth.

## 5. Build the bounded inference service and PHP adapter

- [x] 5.1 Replace the health-check stub with a pinned API supervisor exposing `/health/live`, `/health/ready`, and authenticated `POST /v1/detections`
- [x] 5.2 Implement signed multipart `manifest` + original `image` request transport and bounded multipart `result` + `aligned_image` response transport with timestamp skew, HMAC verification, MIME/decode/pixel limits, and no redirects
- [x] 5.3 Launch one isolated OMRChecker child per sheet; it handles one request and exits. Keep the supervisor responsive and terminate a hung/crashed child at the deadline
- [x] 5.4 Give every request an opaque temp directory and guarantee cleanup in `finally` and startup reconciliation
- [x] 5.5 Run the container non-root with read-only root filesystem, bounded tmpfs, CPU/memory/PID limits, no source bind mount, and no production host port
- [x] 5.6 Call the narrow in-memory OMRChecker path: owned image decode → `ImageInstanceOps.apply_preprocessors(...)` → `read_omr_response(..., save_dir=None)`; never use `entry.py`, CSV, `evaluation.json`, or `check_and_move` online
- [x] 5.7 Return observed fields, upstream-derived states, declared applicant-number observations, field-block shifts, canonical aligned image, safe issues, and timings; return null confidence
- [x] 5.8 Implement `OmrCheckerDetection implements OmrDetectionPort` as a stateless HTTP/config mapper with no model, DB, storage, roster, answer-key, or score access
- [x] 5.9 Enforce connect/total deadlines, one HTTP attempt per job attempt, response limits, strict multipart/JSON parsing, exact contract/engine/commit/bundle/manifest/patch/hash echo, and safe diagnostics
- [x] 5.10 Map network/429/5xx/child-timeout to retryable; invalid input to non-retryable input; auth/version/hash/malformed output to terminal configuration/output failure
- [x] 5.11 Add `config/omr.php` settings for endpoint, enabled gate, signing secret, contract versions, exact commit/image/bundle/manifest/patch IDs, timeouts, and limits
- [x] 5.12 Bind only `fake` or `omrchecker`; fail closed for unknown/incomplete/unready settings and never fall back between providers
- [ ] 5.13 PHP adapter contract tests via Laravel Http::fake with raw multipart assertions (HMAC signing, size limits, malformed multipart, hash echo); Python worker contract tests via pytest in the inference image using committed fixture bytes from tests/fixtures/omr/

**Gate 5:** one clean fixture runs through an isolated child and returns observed marks plus aligned evidence; happy, malformed, timeout, crash, auth, version, hash, and size-limit tests pass with no DB/key/score access.

## 6. Integrate proposals, identity, review, and truthful UI states

- [x] 6.1 Persist exact provider/contract/commit/image/bundle/manifest/patch hashes and stage timings on each processing run
- [x] 6.2 Encrypt and persist the canonical aligned-sheet derivative with its own provenance/HMAC; retain the original photo unchanged as forensic evidence
- [x] 6.3 Persist only validated/redacted normalized output; never raw stack traces, paths, unrestricted identity text, or arbitrary native output files
- [x] 6.4 Derive proposal correctness and area totals from the pinned booklet after validation and prove a forged provider key/score cannot affect them
- [x] 6.5 Resolve bubbled applicant number to the in-session roster only after detection; keep binding staff-confirmed and reject ambiguous/foreign/conflicting identities
- [ ] 6.6 Render review crops from the aligned derivative using the activation-derived manifest plus bounded returned field-block shifts; fail safe on any cross-row/out-of-bounds mapping. UI: pass shifts + aligned/original flag in props, clamp crops to image bounds; server: validate returned shifts against manifest geometry before persisting; test a forged/out-of-bounds shift produces a safe fallbackross-row/out-of-bounds mapping
- [ ] 6.7 Keep the existing capture/gallery/review layout; add only truthful provider queued, unavailable, retrying, terminal, uncalibrated, and manual-fallback states — state source: config gate + cached worker health (short TTL, from supervisor /health/ready) + run-outcome aggregation; manual fallback always reachable; outage simulation in Chromiumback states
- [x] 6.8 Hide confidence percentages for real-engine output; human review remains mandatory for every sheet and applicant binding
- [x] 6.9 Ensure provider outage never blocks the ordinary manual score editor or silently claims a scan succeeded
- [ ] 6.10 Test Manual straight-to-review at healthy latency, Manual disconnect/resume, Auto background capture, uncertain review, identity conflict, stale review, aligned-image failure, and manual fallback

**Gate 6:** a real fixture travels capture → queue → isolated OMRChecker → validated proposal/aligned evidence → staff review while NEXIAM remains the only applicant-binding and scoring authority.

## 7. Benchmark and durable qualification

- [ ] 7.1 Implement `omr:benchmark` against a fixture manifest, provider image, declared hardware/concurrency/warmup/soak count, and output report path
- [ ] 7.2 Record sample/confusion counts, false-green, clean definite accuracy, uncertain-case outcomes, identity precision/coverage, aligned-review crop p95/max, latency p50/p95, throughput, CPU, memory, determinism, and failures
- [ ] 7.3 Make report JSON canonical and hash-addressed; include native-bundle/derived-manifest/booklet/config/contract/commit/source/patch/dependency/image/fixture-manifest hashes
- [ ] 7.4 Enforce synthetic gates: 0% false-green; ≥99% clean definite accuracy; explicit uncertain-case results with mandatory review; ≥99.5% identity precision with ≥95% clean suggestion coverage; no auto-bind; aligned-review crop p95 ≤2% smaller aligned-page dimension; Manual warm p95 ≤5s
- [ ] 7.5 Add deterministic repeat, sustained soak, timeout, malformed response, duplicate delivery, worker restart, and dependency-outage benchmark/drill cases
- [ ] 7.6 Add immutable `omr_provider_qualifications` (or equivalent approved record) binding scope, exact artifacts, report/manifest hashes, metrics, approver/time, and invalidation
- [ ] 7.7 Introduce ONE `OmrProviderResolution` service owning driver selection + qualification verdict; replace the four config('omr.provider.driver') reads; seed a development qualification (synthetic bundle hashes) so the Compose demo keeps working; missing/invalidated qualification → refuse omrchecker dispatches with an explicit provider state
- [ ] 7.8 Invalidate qualification on any bundle/manifest/dependency/commit/patch/image/fixture/contract change: blocks NEW dispatches + new confirmations only; persisted proposals/runs remain immutable evidence; invalidation audit names the changed artifact; re-enablement needs a fresh benchmark pass + record
- [ ] 7.9 Keep `production` qualification impossible without the approved client sheet/key, representative de-identified corpus, named approvers, retention/legal-hold decision, and production drills
- [ ] 7.10 Keep confidence null in this cut. Any later percentage requires a separate instrumentation patch, labeled corpus, versioned calibration mapping, benchmark, qualification, and invalidation rule

**Gate 7:** the exact synthetic combination has a reproducible development qualification; production and calibrated confidence remain explicitly blocked.

## 8. Operations, security, and recovery proof

- [ ] 8.1 Make liveness distinguish API process health from readiness for engine/contract/native-bundle/self-test/qualification (BLOCKED on §7: implement bundle/manifest/qualification identity checks only after 7.6 lands)
- [ ] 8.2 Add structured metrics (BLOCKED on §7 for qualification identity): choose transport up front (worker exposes authenticated /metrics JSON scraped by the app scheduler into a DB table, no PII); list exact metric names incl. qualification record id + artifact hashesn identity without PII/answers/paths
- [ ] 8.3 Prove log redaction via an automated check: run acceptance journeys (success, malformed, crash, wrong-hash, auth failure), scan laravel.log + captured worker stdout for forbidden patterns (10-digit applicant digits, answer/score tokens, HMAC hex, temp paths, tracebacks); enforce a closed allowlist for worker issue codes + PHP safe_error codes; wire the check into the test suite traces
- [ ] 8.4 Prove worker stop/restart mid-request, child crash, timeout, malformed output, wrong commit/bundle/manifest/patch/contract, bad signature, and rolling deploy mismatch recover safely
- [x] 8.5 Prove database duplicate/stale-run guards prevent duplicate or late proposal persistence
- [ ] 8.6 Decide + prove the key design first: either (a) envelope scheme (per-asset random data key encrypted under the keyring key, key_id on rows, retired keys readable until re-encryption, artisan re-key command with reconciliation + audit) or (b) APP_KEY-bound rotation procedure (stop writes, backup, re-encrypt all rows via command, verify HMACs, rotate, restore); tests must prove old assets stay readable and that flipping OMR_ACTIVE_KEY_ID alone is rejected or documented as inert
- [ ] 8.7 Document worker rebuild, source/patch/dependency checksum verification, readiness diagnosis, qualification inspection, rollback to explicit fake for non-production only, and manual fallback

## 9. Final verification and handoff

- [ ] 9.1 Run the bundle-activation/envelope/adapter/worker/fixture/benchmark focused suites in Compose and record exact counts
- [ ] 9.2 Keep grading workflow, scoring conversion, protected OMR access, and fake-provider regressions green
- [x] 9.3 Build the frontend and exercise the seeded OMRChecker Manual journey in Chromium
- [ ] 9.4 Exercise Auto queue/gallery/review and provider outage → retry/manual fallback in Chromium
- [ ] 9.5 Verify keyboard, narrow-screen, status announcement, protected original/aligned images, null-confidence display, and no-false-success behavior
- [ ] 9.6 Update `HANDOFF.md` with exact credentials, native-bundle/manifest/seed/generator/benchmark commands, fixture/report hashes, Compose profile/build (PARTIAL today: re-update after §4 omr:fixtures:generate and §7 omr:benchmark land) commands, URLs, expected states, readiness, recovery, and production blockers
- [x] 9.7 Update master/project-context checkpoints with observed evidence only
- [ ] 9.8 Run `openspec validate omr-scoring-capture --type change --strict --no-interactive`
- [ ] 9.9 Do not archive while production gates are represented as closed without their evidence
