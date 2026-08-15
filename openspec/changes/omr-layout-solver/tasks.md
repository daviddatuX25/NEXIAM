# Tasks: omr-layout-solver

> **Execution rule:** phases in order; no task is done without its tests. One active change — the four predecessor OMR changes are parked in `openspec/changes/archive/` (in-flight states preserved).

## 1. Authority consolidation

- [x] 1.1 Designate `omr-layout-solver` as the sole active OMR change; archive `omr-configuration-usability` as PASS (36/36); park `omr-scoring-capture` (57/98), `omr-configuration` (18/24), `omr-multipage-answer-sheets` (25/28), `omr-printing-pipeline` (11/26) with state notes in the archive README
- [x] 1.2 Record founder decision + blueprint/solver requirements in OpenSpec (this change's proposal/design/spec)
- [x] 1.3 Validate the new change (`openspec validate omr-layout-solver --type change --strict --no-interactive`)

## 2. Blueprint persistence + pure solver

- [x] 2.1 Migration: `layout_blueprint_json`, `layout_blueprint_hash`, `layout_solver_version` on `omr_answer_sheet_template_versions` (applied 2026-08-15)
- [x] 2.2 Blueprint normalization (schema v1: paper, columns, identity, sections with ranges + break_before) — implemented as in-solver normalize() returning the normalized blueprint in the DTO (array style, no separate VO class)
- [x] 2.3 `OmrTemplateLayoutSolver`: geometry profile (long 2060x3144 / legal 2060x3392, margins 80, row pitch 80, identity reserves, footer/calibration reserve 230), column origins per count (4-col == shipped v4 exactly, byte-verified), atomic-row placement, validated breaks (next_column/next_page/pin), fieldBlock compaction, canonicalization (sorted keys, integer units, fixed flags, exact-bytes sha256)
- [x] 2.4 `SolvedOmrTemplateLayout` DTO (normalized blueprint, canonical sorted template, canonical bytes + sha256 hash, pages projection)
- [x] 2.5 `SolveOmrTemplateLayout` application action (area-code catalog lookup + solver delegation); save, preview-draft, and the live layout preview call it

## 3. Solver contract tests

- [x] 3.1 Deterministic 2/3/4-column output; byte-identical hashes (16 solver tests / 60 assertions green; v4 shape byte-match verified)
- [x] 3.2 Exact fit at last valid row; one-row overflow → next column; final-column overflow → next page
- [x] 3.3 No split inside a question; `next_column` / `next_page` semantics; valid + invalid absolute target refusals (pin_fits / section_does_not_fit_pinned_column / pin_moves_backward)
- [x] 3.4 Identity repeated per page; header/footer/calibration reserves respected (identity at reserved top zone on every page)
- [x] 3.5 Duplicate range, empty section, unknown schema/paper/columns, invalid range/break refusals with typed context (impossible row-width unreachable in v1 geometry: min column 400px > 254px MCQ4 row)

## 4. Durable save boundary

- [x] 4.1 Row-locked version allocation (unique constraint pre-existed) + duplicate detection moved BEFORE staging/visible allocation
- [x] 4.2 Immutable staging on the private local disk (`storage/app/private/omr-bundles`), no source-tree writes, no `rm -rf` (Storage deleteDirectory), `omr:prune-staged-bundles` retention sweep (10 orphans cleaned in dev)
- [x] 4.3 `NativeBundle::load` resolves durable-first with the legacy in-tree fallback — all callers (activation, print, preview, seeder) migrate without individual edits
- [ ] 4.4 Persistence tests: concurrent saves, failure leaves no rows/files, audit rollback, preview never persists, active immutability, copy preserves blueprint + overlay provenance

## 5. Server solving wired into save + preview

- [x] 5.1 Save lifecycle: solve blueprint → pre-stage duplicate check → stage immutable files → derive/validate → transaction (row lock, version re-check, insert, audit) → publish; `template_json_hash` = canonical solved bytes
- [x] 5.2 Preview: `POST /setup/omr/layout/preview` (projection + page_count + hash + diagnostics, 422 typed refusals) and `preview-draft` accepts `layout_blueprint_json` through the shared solver
- [x] 5.3 Test: layout preview hash == saved `template_json_hash`; preview never persists (version count unchanged)

## 6. Builder moves to blueprint editing

- [x] 6.1 Svelte: builder split into `Builder.svelte` (dispatcher, Layout/Advanced toggle) + `BlueprintBuilder.svelte` (blueprint knobs + debounced server projection + typed diagnostics) + `TemplateEditor.svelte` (legacy path preserved); Svelte computes no geometry
- [x] 6.2 Controls: paper, columns 2|3|4, identity toggles, ordered sections with area/range/break selects, add/remove/reorder; raw origin/gaps/coordinates removed from the blueprint editor
- [x] 6.3 Browser evidence: 3-column re-solve (1→2 pages), duplicate-range diagnostics, save → show page, blueprint hydration via ?version=; save route gap fixed (POST /setup/omr/templates was never registered)

## 7. Richer controls (post-foundation)

- [x] 7.1 Section reorder (up/down) + range editing in the blueprint editor
- [x] 7.2 Break controls (Continue flow / Start next column / Start next page) per section
- [ ] 7.3 Absolute column pin UI deferred (solver supports the validated pin contract; no workflow demands it yet)

## 8. Integration + closeout

- [x] 8.1 Recognition integration test: solver-generated 2-page bundle → save → activate → capture → merge → needs_review (200 items)
- [x] 8.2 Real-engine smoke (2026-08-15): fixture pages rendered via worker generate_fixture.py → upload → OMRChecker runs succeeded (3.2s/2.7s) → needs_review, 200 items, applicant 2345678901 detected, confidence null (founder lock)
- [ ] 8.3 Browser evidence, HANDOFF, master checkpoint, `openspec validate --changes --strict` green
- [ ] 8.4 Provider fixtures/benchmark/qualification stays on its own track (not part of this change)

## Out of scope

Release/result-sheet printing; free-form drag; provider qualification; per-page paper sizes (single paper per template).
