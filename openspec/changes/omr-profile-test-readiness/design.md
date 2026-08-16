## Context

The OMR stack already has a server-owned `OmrTemplateLayoutSolver` that turns a semantic blueprint into canonical OMRChecker `fieldBlocks`, and `SaveAuthoredTemplateDraft` persists the solved bundle as an immutable draft. Existing demo data covers four legacy native pairs and a two-page authored v5 pair, but no active one-page compact profile exists. The profile must use long-bond geometry without scaling, preserve the provider-native bundle seam, and remain safe to re-seed.

## Goals / Non-Goals

**Goals:**

- Add one active, paired compact profile using six columns of 25 questions each.
- Keep the applicant number as the only native identity grid; render birthdate and printed name as manual header fields.
- Use one solver blueprint for preview, persisted bundle, and printed PDF.
- Provide deterministic answer keys and capture states for review, resume, and retry flows.
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
   - Extend `OmrDemoBatchSeeder` only after the profile pair is available, using model/action relationships already used by the current demo batch. Seed one one-page profile batch with needs-review, pending-pages, and failed-terminal states; retain existing state transitions and idempotent guards.
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
   - NEXIAM owns a conservative preflight overlay: camera permission/device state, live stream, sheet-in-frame guide, page count, upload fallback, and an explicit `Ready to capture` state. It must not claim OMR-quality readiness from pixels it cannot validate.

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

## Migration Plan

1. Add the new OpenSpec artifacts and focused tests.
2. Extend the demo seeders with guarded profile creation and capture data.
3. Reset the development database, run `GradingDemoSeeder`, and verify one active profile pair plus expected capture states.
4. Render the profile preview/print PDF, rasterize at 100%, inspect all pages, then run focused OMR suites and strict OpenSpec validation.
5. If rollback is required in development, run the approved foundation reset; no production migration is introduced.

## Open Questions

None; the profile is fixed to one long-bond page, six columns, six 25-question sections, compact 40×40 bubbles, and the existing identity lock.
