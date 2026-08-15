# OMRChecker Strategic Research

**Change:** `omr-scoring-capture`  
**Status:** source-evidence report; OMRChecker selected for gated development integration. Implementation decisions were reconciled into `design.md`, `specs/nexi-omr/spec.md`, and `tasks.md` on 2026-08-12; those artifacts override earlier hypotheses below.
**Prepared:** 2026-08-12

## Executive decision framing

NEXIAM should treat OMRChecker as a replaceable **detection provider**, not as the owner of grading state, templates, authorization, or score conversion.

The first integration target is:

- staff-confirmed scoring only; no automatic score acceptance;
- shipped online Manual and Auto capture use one durable per-sheet run; Batch and offline/PWA storage remain separate changes;
- Manual waits/polls the same run and opens review; Auto restores capture while processing continues;
- NEXIAM applies green/red correctness only after validation against its pinned booklet; unresolved marks require staff action;
- one immutable OMRChecker-native bundle is the detection-geometry authority and is pinned to the grading session through an approved template/booklet pair;
- real-engine confidence is null until a separately qualified calibration exists;
- original and aligned-review images remain encrypted, hash-linked, and auditable.

**Current evidence:** OMRChecker has a useful coordinate/template model, preprocessing/alignment processors, bubble-reading, answer matching, and overlay drawing. It is not yet verified as a headless, service-ready, multi-page student-grouping engine. The first spike must prove those boundaries on NEXIAM representative sheets.

## Source baseline

The inspected upstream baseline is master commit [`de3f6982822895491be760325fd43e65e90fa293`](https://api.github.com/repos/Udayraj123/OMRChecker/commits/master), dated 2026-06-30.

Primary sources:

- Repository and license: [OMRChecker](https://github.com/Udayraj123/OMRChecker)
- README and published claims: [README at inspected commit](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/README.md)
- Template model: [`src/template.py`](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/template.py)
- Current template schema: [`src/schemas/template_schema.py`](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/schemas/template_schema.py)
- Evaluation schema: [`src/schemas/evaluation_schema.py`](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/schemas/evaluation_schema.py)
- Core reading/overlay flow: [`src/core.py`](https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/src/core.py)
- Evaluation/matching: [`src/evaluation.py`](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/evaluation.py)
- Image/PDF loading: [`src/utils/image.py`](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/utils/image.py)
- Output/file handling: [`src/utils/file.py`](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/utils/file.py)
- Official user guide: [OMRChecker wiki](https://github.com/Udayraj123/OMRChecker/wiki/User-Guide)
- Releases: [GitHub releases](https://github.com/Udayraj123/OMRChecker/releases)
- Open issues: [GitHub issues](https://github.com/Udayraj123/OMRChecker/issues)
- Python subprocess contract: [Python `subprocess` documentation](https://docs.python.org/3/library/subprocess.html)
- OpenCV geometric transforms: [OpenCV imgproc transform docs](https://docs.opencv.org/4.x/da/d54/group__imgproc__transform.html)

The repository is MIT-licensed. The latest published release observed was v1.1.0 (2024), while master has newer commits; pinning a tested commit is safer than relying on an unpinned release or moving master branch.

## 1. Capability report

### 1.1 Template and coordinate model

The source template model represents page dimensions, bubble dimensions, field blocks, origins, gaps, direction, labels, and output columns. The current schema requires `bubbleDimensions`, `pageDimensions`, preprocessors, and field-block geometry; field blocks require an origin, gap configuration, labels, and either a field type or bubble values/direction.

**Locked implication:** OMRChecker supplies the geometric/detection substrate. Its immutable native bundle is canonical. Activation loads that bundle through the pinned engine and derives NEXIAM's read-only review manifest/projections; NEXIAM never reverse-compiles a second coordinate model into OMRChecker input.

### 1.2 Preprocessing and alignment

The inspected source includes processors for page cropping, marker-based cropping, feature-based alignment, Gaussian blur, levels, and median blur. The core flow uses preprocessing and ROI-based bubble reading with global/local gap thresholds. The repository README also describes skew/alignment and mobile-photo use cases.

**Verified limitation:** README performance/accuracy statements are upstream claims, not NEXIAM acceptance evidence. They must not become product guarantees until benchmarked against representative NEXIAM sheets.

### 1.3 Answer reading and correctness

The source exposes per-field/bubble reading and an evaluation layer with standard, multiple, weighted, and fraction-style marking concepts. The current schema source requires `source_type` values `csv` or `custom`, while wiki material describes older/different values such as `local` and `image_and_csv`.

**Locked decision:** NEXIAM owns the authoritative answer key and scoring policy. Online detection does not call OMRChecker evaluation or accept provider correctness/scores. The stable envelope contains:

- contract, run, input, native-bundle, derived-manifest, patch, and engine identity;
- one observed selected option or null for every expected stable field;
- upstream-derived mark state and bounded issue candidates;
- bubbled applicant-number observations and field-block shifts;
- canonical aligned-sheet bytes plus MIME, dimensions, and hash;
- `confidence: null` and `calibration_status: unqualified`;
- safe warnings and processing timings.

After strict validation, NEXIAM joins observations to its pinned booklet and derives correctness and scores.

Do not bind NEXIAM to the wiki's stale configuration vocabulary without source verification for the pinned commit.

### 1.4 Overlay feasibility

The core source includes template-layout overlay drawing, but its alignment paths do not preserve a complete reversible transform into the original camera image. The safe review surface is the canonical aligned-sheet derivative.

**Required adapter behavior:** return the aligned sheet, its hash/dimensions, stable item identity, and bounded field-block shifts. NEXIAM crops it with the activation-derived manifest and renders outcome colors after applying its own answer key. The immutable original remains separately available as forensic evidence; the UI shows no real-engine confidence label.

The desired visual semantics are implementable without inventing a second CV algorithm:

- green circle/region: detected option agrees with the pinned key;
- red circle/region: detected option disagrees with the pinned key;
- yellow circle/region: low-confidence, multi-mark, unreadable, or otherwise unresolved;
- identity regions use a separate identity/match state, not a grading color.

### 1.5 PDF and multi-page behavior

The inspected image utility supports PDF loading through PyMuPDF/fitz and exposes page/range handling, but pages are processed independently. No verified student/page grouping contract was found. An upstream issue remains open for related multi-page behavior.

**Implication:** first release should model one captured image/page as one sheet unless NEXIAM adds explicit grouping metadata. Multi-page forms require a separate spike: page identity, grouping, ordering, partial failure, and review UX.

### 1.6 File/output behavior and operational caveats

The output flow writes generated files/CSV artifacts. The current source also contains a `check_and_move` path reported as incomplete in upstream issue tracking; NEXIAM must not depend on that behavior for authoritative storage or cleanup.

The inspected interaction utility imports monitor/screen information and uses OpenCV window APIs for interactive display. This is a headless-container risk: even non-visual execution may need a patch/configuration that avoids monitor discovery and `namedWindow`/`imshow` paths. Open upstream pull requests address related headless concerns but were not merged at the inspected commit.

**Go/no-go condition:** a headless `python:3.12-slim` Compose image must process a sheet without display, monitor, or GUI dependencies.

### 1.7 Version and maintenance risk

The published release and active master branch are not the same stability signal. The source/schema/wiki mismatch and open issue count mean the integration must pin a commit, vendor a reproducible adapter configuration, and run a fixture benchmark in CI/operations before activation.

## 2. Architecture options

**Option 1 — Laravel starts one subprocess per sheet**
- **Coupling:** tight process/runtime coupling; simple initial call
- **Throughput:** poorer under batch load due startup cost
- **Failure/retry:** process exit maps directly to failure; retries need careful temp-file cleanup
- **Compose fit:** easy but duplicates Python setup concerns in PHP container
- **Recommendation:** use only for a short diagnostic spike, not production batch

**Option 2 — Long-lived Python API supervisor with one isolated detector child per sheet in the optional `inference` service**
- **Coupling:** stable HTTP boundary; Laravel owns command/state; mutable OMRChecker state remains child-local
- **Throughput:** supervisor stays warm, while child startup cost is measured explicitly
- **Failure/retry:** health, deadline, child termination, correlation, durable current-run claim, and reconciliation are required
- **Compose fit:** strong fit; `system/compose.yaml` already has the optional `inference` service
- **Recommendation:** **locked Phase B development shape**; benchmark evidence is required before considering child reuse

**Option 3 — Internal HTTP OMR service**
- **Coupling:** clean language boundary and independently testable contract
- **Throughput:** good if worker pool is bounded
- **Failure/retry:** HTTP timeout/retry/circuit-breaker and duplicate-request protection required
- **Compose fit:** fits existing optional service; still one Compose deployment
- **Recommendation:** use as the transport for the long-lived worker, not as a separate business system

**Option 4 — Laravel queue job directly owns detector call**
- **Coupling:** preserves domain queue semantics
- **Throughput:** good if worker is isolated; poor if PHP container carries Python
- **Failure/retry:** Laravel retries are useful, but external process lifecycle leaks into jobs
- **Compose fit:** fits current database queue but risks runtime coupling
- **Recommendation:** use queue jobs as orchestration; call the stable provider boundary

### Recommended staged topology

1. Keep `OmrDetectionPort` as the only application seam and `FakeOmrDetection` for deterministic tests/demo data.
2. Establish one immutable native bundle and derive the NEXIAM review manifest through the pinned engine during activation.
3. Clean-cut to envelope v2 with no answer key, correctness, score, storage reference, or second geometry table.
4. Make Manual and Auto create one durable per-sheet run after commit; Laravel queue orchestration claims the current run before invoking the provider.
5. Send signed bounded multipart image bytes plus verified bundle/manifest identity to the API supervisor.
6. Launch one isolated OMRChecker child for one sheet and terminate it on completion or deadline.
7. Return strict JSON observations plus canonical aligned-image bytes; Laravel validates and persists proposals/audit before staff review.
8. Preserve ordinary manual scoring when the worker is unavailable; never fall back to fake detection.

This remains a modular-monolith deployment with a language-isolated provider boundary, not a second grading system or independently exposed microservice.

## 3. Template and data-model direction

### NEXIAM owns

- template business identity, lifecycle, owner, activation, and audit;
- immutable native-bundle custody, file hashes, engine/patch identity, and approved template/booklet pairing;
- activation-derived review manifest and immutable relational projections;
- question booklet, answer key, scoring policy, and score conversion;
- grading-session pins, authorization, applicant binding, review edits, authoritative commits, audit, encryption, retention, and qualification.

### OMRChecker owns

- native template parsing and `FieldBlock`/bubble expansion;
- preprocessing/alignment mechanics and field-block shifts;
- bubble measurement, thresholding, and native field classification;
- canonical aligned-sheet production;
- engine-specific quirks contained behind the NEXIAM envelope.

### Suggested relational shape

- `omr_templates`: stable business identity, owner, status, current active version reference;
- `omr_template_versions`: immutable native-bundle identity/content, page dimensions, engine/patch/config/file/bundle hashes, derived-manifest JSON/hash, and activation evidence;
- `omr_template_areas/questions/options`: immutable activation-derived projections with no independent geometry edits;
- `omr_question_booklet_versions/questions`: separate immutable position-to-answer-key data;
- `omr_template_booklet_pairs`: approved compatibility between one native template version and one booklet version;
- `grading_sessions` and capture batches: pinned compatible pair; never resolve a historical sheet against today's active versions;
- `omr_capture_sheets`: original asset, aligned derivative provenance, proposals, identity suggestion, review edits, and pinned configuration;
- `omr_processing_runs`: current-run claim/version, input/contract/engine/patch/bundle/manifest hashes, status, timings, failures, retry state, and output provenance.

Do not build a drag-and-drop visual template editor in the first spike. Start with structured CRUD/imported geometry plus a preview/validation screen. Add visual editing only when a real template-authoring bottleneck is measured.

## 4. Offline/PWA feasibility

The agreed first offline scope is **capture and local draft only**. Detection, identity binding, and score commit remain online.

Recommended envelope:

```text
local draft
  capture_id / client_id
  session reference
  template-version hint (not authority)
  encrypted image blob
  local edit metadata
  client-created timestamp
  device/session key reference
        ↓ sync
server upload → dedupe by capture id + asset hash
        ↓
server detects with pinned template version
        ↓
reviewer resolves conflicts and confirms
```

Requirements:

- never trust a client template/version, actor, roster match, or score;
- use a client-generated capture ID and idempotency key;
- retain local draft state until server acknowledgment and explicit asset integrity verification;
- present manual conflict resolution when the server sheet was removed, reviewed, manually scored, or changed;
- do not silently merge item edits;
- expire unsynced local assets and provide an explicit recovery/export path;
- treat browser storage as sensitive: service workers and IndexedDB do not automatically provide institutional-grade encryption or secure deletion;
- do not claim offline detection until a separate device-runtime benchmark proves it.

NEXIAM's current package does not show a PWA plugin/manifest/service-worker implementation. The PWA work is therefore a separate feasibility slice, not an assumption of the OMR capture page.

## 5. Prototype and benchmark plan

### Fixture pack

Use de-identified real sheets where authorized, plus synthetic sheets with exact ground truth. Include:

- clean, skewed, rotated, cropped, shadowed, low-light, blurred, and compressed photos;
- blank, correct, incorrect, erased, multi-mark, faint-mark, and out-of-region bubbles;
- unreadable/multi-mark bubbled applicant numbers and roster-binding conflicts; free-form name/date OCR is excluded;
- every active template version and each area/question type;
- at least one unsupported or malformed sheet;
- one page first; multi-page grouping is a separate gate.

### Measurements

Record per item and per sheet:

- correct/incorrect/blank/ambiguous classification;
- false-green rate (wrong answer shown as correct);
- false-red rate (correct answer shown as incorrect);
- uncertain-case outcomes and mandatory-review coverage, without invented confidence;
- applicant identity suggestion precision and unresolved rate;
- aligned-review crop error against the canonical aligned sheet;
- detection latency, queue latency, memory, CPU, and throughput;
- retry behavior, duplicate requests, worker restart recovery, and asset cleanup.

### Proposed initial gates (recommendations, not observed facts)

- false-green rate: **0% in the acceptance fixture pack**;
- item-level definite-answer accuracy: **≥99%** on clean/controlled images;
- uncertain cases: record actual upstream outcome plus the required human-review expectation; do not invent a confidence threshold;
- identity suggestion precision: **≥99.5%** with **≥95%** clean suggestion coverage and no automatic binding;
- aligned-review crop p95: **≤2% of the smaller aligned-page dimension**;
- manual review: median **≤15 seconds per clean sheet** after proposal availability;
- online manual-mode proposal availability: **≤5 seconds at pilot concurrency**;
- batch mode: measure sustained throughput and queue tail latency rather than promise the upstream README rate;
- any failed/timeout/unsupported result must enter a recoverable manual-review state.

These synthetic thresholds qualify only the exact development artifact set. They do not calibrate confidence or grant production use.

## 6. Open gates before production OMRChecker activation

These do not block development of the adapter and synthetic fixture benchmark in the active change. They block institutional production qualification and enablement:

1. Supply the approved client one-page answer sheet, answer key, and representative labeled corpus.
2. Pin the canonical OMRChecker commit, dependency lock, container digest, and license review; never use floating master.
3. Prove bounded headless execution and remove or disable GUI-only paths in the Compose inference worker.
4. Verify native bubble mapping, aligned-review crops, null-confidence policy, bubbled identity output, failure taxonomy, and determinism for the pinned commit.
5. Prove one hashed immutable native bundle and byte-deterministic activation-derived NEXIAM review manifest; no reverse compiler or second geometry authority.
6. Name the production template and benchmark approvers and record repeatable go/no-go evidence.
7. Prove worker restart, timeout, retry, reconciliation, dependency outage, backup/restore, and key-rotation drills.
8. Approve institutional image retention and legal-hold policy.

Batch quality automation and browser-local/offline image persistence remain separate future changes; their gates must not be folded into OMRChecker activation.

## 7. Sequenced delivery

1. Preserve the shipped fake-provider Manual + Auto baseline and ordinary manual fallback.
2. Replace duplicate seeded coordinates with one immutable synthetic native bundle.
3. Pin OMRChecker/dependencies and apply only the minimal headless and stale-overlay patches.
4. Derive the NEXIAM review manifest/projections through the exact pinned engine.
5. Clean-cut envelope v2 and durable current-run persistence guards.
6. Generate deterministic fixtures from the native bundle plus derived manifest.
7. Implement the authenticated supervisor/isolated-child worker and PHP adapter.
8. Benchmark false-green, definite-answer accuracy, uncertain-case behavior, identity precision/coverage, aligned-crop error, latency, resources, and recovery.
9. Issue only a `development` qualification for the exact synthetic artifact set.
10. Close approved-client-corpus, retention, approver, and operational gates before any `production` qualification.
11. Evaluate calibrated confidence, automatic acceptance, zero-click Batch, and offline capture only in separate gated changes.

## Bottom line

OMRChecker is a credible candidate for NEXIAM's detection layer because it already models native template geometry, preprocessing/alignment, bubble reading, and aligned output. It is not NEXIAM's grading system. The locked Phase B shape is a pinned headless API supervisor that launches one isolated detector child per sheet, receives no answer key, returns observed marks plus aligned evidence with null confidence, and remains guarded by Laravel current-run claims, strict validation, staff review/binding, NEXIAM score conversion, audit, encryption, and manual fallback.
