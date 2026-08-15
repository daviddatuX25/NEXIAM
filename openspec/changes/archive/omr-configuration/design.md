# Design: OMR Configuration & Template Builder

## Context

Configuration exists only as seeder output; the reject/retry actions exist without UI reach. Founder decisions (2026-08-13): import geometry, never draw a second authority; the compliant builder edits the native JSON itself; paper = long bond 8.5×13; configuration lives under System Settings. The shared `BundleRenderer` (in `omr-printing-pipeline`) powers previews and blank-sheet thumbnails.

## Goals / Non-Goals

**Goals:**

- Staff can register a real answer-sheet design (intake), author a booklet + answer key, activate a pair, and — when needed — design new sheets with a visual builder whose output IS the native bundle.
- Configuration surfaces follow the legacy list→create/edit convention under System Settings, capability-gated.

**Non-Goals:**

- No independent NEXIAM coordinate tables (founder lock). No booklet printing. No result-sheet template editor here (printing change owns rendering). No production qualification changes.

## Decisions

1. **Intake = upload + verify + activate.** Files are hash-verified against each other and the pinned engine identity before a new immutable version is created; `NativeManifestDeriver` produces the preview. Rejected alternative: NEXIAM-side coordinate tables (lock violation).
2. **Builder writes `template.json`.** The editor manipulates pages/blocks/origins/gaps/bubble sizes and saves the native JSON; every save runs the full bundle validation (labels unique, regions in-bounds, no overlap, contiguity) before a draft version is created; preview comes from the shared renderer. Activated versions are immutable (existing triggers). This preserves the single-geometry authority: the builder is the authoring UI for the canonical file, not a parallel source.
3. **Booklets = NEXIAM CRUD over immutable versions.** Questions per area with `correct_option_code`; key stored as a SHA-256 hash on the version row; editing an active booklet creates a new draft version; activation one-way.
4. **Pair activation screen** runs `ActivateOmrConfiguration` and surfaces typed compatibility errors (area max_items, option coverage, conversion coverage).
5. **System Settings placement**: a dedicated OMR Configuration section in the Setup hub with readiness checks (active pair exists, booklet key coverage, paper size configured).
6. **Reject/retry reach**: buttons on the capture surface constrained by sheet/batch state; the actions already enforce the rules.

## Risks / Trade-offs

- [Builder produces invalid geometry] → Mitigation: save-time bundle validation + typed errors; preview before save; drafts never affect live batches.
- [Builder drifts from renderer] → Mitigation: preview uses the same shared renderer that prints; no second visual path.
- [Answer key exposure] → Mitigation: hashed storage, audit on edit, provider seam unchanged.
- [Staff activate an incomplete booklet] → Mitigation: activation re-checks contiguity/coverage and fails atomically.

## Migration Plan

- No new tables expected beyond what already exists (pages/bundles); the builder saves bundles into existing draft version rows.
- Rollback: remove routes/screens; seeder path unchanged.

## Merge order with omr-printing-pipeline (planner finding resolved)

Single agreed order: **printing §1 (pipeline) → printing §2 (renderer + v4 bundle) → config §1 (intake/templates) → config §2–§4 (builder/booklets/pairs) → printing §3–§4 (blank-sheet UI, result sheets) → config §5 (settings wiring)**. The shared `BundleRenderer` ships in printing and accepts arbitrary draft JSON synchronously for builder previews; only finished artifacts flow through print_jobs.

## Open Questions

- None blocking.
