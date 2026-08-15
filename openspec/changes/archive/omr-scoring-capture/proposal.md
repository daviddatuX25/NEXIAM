# Change Proposal: OMR Scoring Capture

**Change ID:** `omr-scoring-capture`  
**Status:** ACTIVE — PHASE A SHIPPED; PHASE B OMRChecker DEVELOPMENT INTEGRATION PLANNED  
**Phase:** E2/E3 gated OMR capability  
**Depends on:** archived PASS changes `grading-workflow`, `grading-roster-eligibility`, and `scoring-conversion`; shipped synthetic Phase A baseline

## Why

NEXIAM needs a staff-confirmed OMR path that reduces repetitive manual scoring without creating a second grading authority. The founder-approved interface is the mobile-first flow represented by `omr-capture-preview.html`: session landing → capture → scans gallery → per-sheet review. OMR proposals remain advisory until an authorized Test Administrator accepts them.

Phase A is now implemented and browser-proven: online Manual/Auto capture, protected review, deterministic fake detection, provider envelopes, queue dispatch, and synthetic template/booklet data are live. Phase B hardens that seam and adds a development-qualified OMRChecker provider without changing scoring authority or silently enabling institutional production.

## What changes

### Phase A — shipped baseline

1. Online, one-page composite answer-sheet workflow for **Manual** and **Auto** capture.
2. Immutable answer-sheet template and question-booklet versions pinned to a capture batch.
3. Dedicated Test Administrator OMR authority, protected encrypted assets, queue dispatch, review, and manual fallback.
4. Versioned `OmrDetectionRequest`/`OmrDetectionResult`, pinned-snapshot hashing, deterministic fake provider, validator, issues/proposal persistence, and existing scoring integration.
5. Mockup-aligned Svelte camera → gallery → review experience and developer-ready seeded demo.

### Phase B — this implementation plan

1. Repair the real-provider trust boundary before transport work: the detector never receives or returns the answer key; NEXIAM alone compares detected marks with the pinned booklet and derives scores.
2. Replace the dual-geometry plan with one immutable OMRChecker-native template bundle as detection geometry authority. Activation loads the pinned engine, expands native field blocks, validates uniqueness/bounds/coverage, and persists a hash-pinned NEXIAM review manifest derived from the native bubbles. No runtime NEXIAM-to-OMRChecker geometry compiler or independently maintained coordinate table is allowed.
3. Generate a versioned deterministic answer-sheet fixture pack from that native bundle and derived manifest: printable SVG/PDF/PNG, blank reference, marked variants, transformed camera variants, and a hash-addressed ground-truth manifest.
4. Pin OMRChecker commit `de3f6982822895491be760325fd43e65e90fa293`, its dependency lock, source checksum, inference image digest, and a minimal owned patch set limited initially to headless import safety and the stale/unbound overlay-coordinate defect. Bubble geometry and classification algorithms remain upstream-native.
5. Implement `OmrCheckerDetection` behind `OmrDetectionPort` and a versioned authenticated HTTP contract to the optional Compose inference service. The adapter remains stateless and has no database, storage, roster, answer-key, or score access.
6. Use the Laravel queue as the sole online orchestrator for both modes. Manual waits on the same durable per-sheet run and normally redirects to review within the latency SLO; Auto returns to capture immediately. A browser disconnect never cancels or duplicates detection.
7. Return observed marks, bubbled applicant-number proposals, field-block shifts, a canonical aligned-sheet derivative, typed issues, safe diagnostics, and timings. Phase B reports confidence as `null` and requires review until a separately versioned labeled-corpus calibration is qualified; it never fabricates percentages or attempts transform-heavy overlays on the original photo.
8. Add fail-closed provider selection, template/engine qualification, readiness, resource bounds, restart/timeout/duplicate recovery, benchmark reports, and operator proof. Synthetic qualification enables development only; production remains blocked on the approved client sheet/corpus, approvers, retention/legal-hold, and drills.

## Delivery boundary

This active change now has two bounded cuts. Phase A is shipped with `FakeOmrDetection`. Phase B may ship native template-bundle activation, the derived review manifest, fixture generator, pinned headless worker, adapter, benchmark harness, and real-provider development demo. It SHALL NOT grant institutional production activation merely because synthetic fixtures pass.

Batch auto-shutter, offline/PWA storage, QR attribution, multi-page grouping, OCR of free-form identity text, result printing, and Nexi confirmation remain separate changes.

## Legacy and existing-code disposition

| Source | Disposition |
|---|---|
| Legacy automated OMR goal | **REWRITE** — no authoritative legacy implementation exists. |
| Legacy staged score import | **MIMIC staged feel only** — retain analyze/review/confirm vocabulary; rewrite authorization, state, transactions, concurrency, and recovery. |
| Existing OMR migrations `170000`–`170002` | **KEEP history; FORWARD-FIX** with new migrations and upgrade tests. |
| Existing OMR models, DTOs, Actions, Requests, fake detector | **REPLACE/FORWARD-FIX** to the normative spec; unreferenced skeleton behavior is not compatibility authority. |
| Existing `SensitiveAssetStore` | **REPLACE FORMAT**; no compatibility promise before any production OMR asset exists. |
| Existing manual grading and `ScoreConverter` | **KEEP and reuse** as the only authoritative scoring core and fallback. |
| Founder mockup | **MIMIC** layout, navigation, vocabulary, review affordances, and status presentation. |

## Invariants

- Only an in-scope Test Administrator can capture, view, bind, edit, retry, remove, commit, or close OMR work.
- No detector, queue worker, controller, or UI writes `score_records` directly.
- No OMR score becomes authoritative before per-sheet human confirmation.
- Manual Save commits that sheet immediately; Auto review persists reviewer output, and session confirmation commits all reviewed uncommitted Auto sheets.
- A batch remains capture-open while Auto jobs run; analysis of one sheet never closes intake.
- Session → batch → sheet → score is the global lock order. External detection and filesystem deletion never run while database locks are held.
- Every mutation has scoped idempotency, a canonical payload fingerprint, expected version, typed outcome, and atomic audit.
- Original detection output, reviewer edits, score revisions, template/booklet pins, hashes, and reasons remain attributable.
- No committed evidence is purged until the institution approves a retention/legal-hold policy.

## Explicitly out of scope

- Institutional production activation before a non-synthetic client template, approved answer key, representative labeled corpus, named approvers, retention/legal-hold decision, and operational drills exist.
- Any silent fallback from OMRChecker to fake detection.
- Zero-click Batch capture, camera-quality automation, and background multi-sheet auto-shutter.
- Offline/PWA image storage, sync, conflict resolution, and browser key recovery.
- QR attribution, multi-page student grouping, free-form OCR identity, Nexi auto-confirm, result-sheet printing, and exam-navigation reorganization.

## Delivery gates

Phase B implementation is complete only when the new OMRChecker requirements and ordered tasks pass in Compose, the real-provider Manual and Auto seeded journeys are browser-exercised, the synthetic benchmark report is reproducible, the fake-provider regression path remains green, and `HANDOFF.md` records exact credentials, commands, URLs, hashes, expected states, and recovery steps.

Production activation remains fail-closed until every production qualification gate in `design.md` §23 is evidenced. A passing synthetic pack is development evidence, not a production accuracy claim.
