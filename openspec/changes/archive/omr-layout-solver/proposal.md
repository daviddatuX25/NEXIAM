# omr-layout-solver

> **Status:** ACTIVE (sole active OMR change). Supersedes and parks `omr-scoring-capture`, `omr-configuration`, `omr-multipage-answer-sheets`, `omr-printing-pipeline` (in-flight states preserved in `openspec/changes/archive/`). `omr-configuration-usability` archived PASS 2026-08-15.
>
> Adopted contract: [`docs/omr-layout-solver-verdict.md`](../../../docs/omr-layout-solver-verdict.md).

## Why

The template builder's geometry is client-owned: Svelte computes bounds, margins, collisions, and canonical `fieldBlocks`, and save/preview endpoints trust the client's JSON. That makes the browser a second geometry authority, breaks the one-server-solver invariant, and blocks multi-column (2/3/4) sheets with question sections that flow across columns and pages. Paper selection is internally contradictory (`defaultConfig()` hardcodes long-bond; bundles reject mismatched pages), draft persistence writes mutable bundles into the application source tree with `exec('rm -rf')` cleanup, and `template_json_hash` hashes non-canonical incoming bytes.

## Proposal

Introduce one server-owned deep module — `OmrTemplateLayoutSolver` — that turns a semantic blueprint into canonical OMRChecker `fieldBlocks`:

- Blueprint stores intent, not coordinates: `{schema, paper, columns, identity, sections[{area_id, question_from, question_to, option_count, break_before}]}`.
- Deterministic integer geometry: reserved bands (header, repeated identity, footer, frame, calibration) → column rectangles → atomic question rows placed in (page, column, y) order → contiguous rows compacted into range-based fieldBlocks → canonical JSON bytes + hash.
- Break-first semantics: `next_column` / `next_page` monotonic; absolute column pins only as validated breaks (`section_does_not_fit_pinned_column` typed refusal; never silent re-flow).
- Validation order: request shape → blueprint semantics → feasibility → bounds/margins → overlap/duplicates → catalog coverage → manifest derivation → activation.
- Blueprint persists in adjacent version columns (`layout_blueprint_json`, `layout_blueprint_hash`, `layout_solver_version`) — never inside `template.json` or `provider_config_json`.
- Preview (live layout + PDF) and save invoke the same solver; test proves `hash(preview solved) == hash(saved)`.
- Save boundary hardened in the same pass: row-locked version allocation, DB-unique (template_id, version), duplicate detection before visible allocation, content-addressed immutable staging via Laravel Storage, no `rm -rf`, retention job.

Renderer, capture, merge, and recognition machinery are preserved untouched; one solver-generated two-page bundle runs through the existing capture/merge path as integration proof.

## Out of scope

- Release/result-sheet printing (deferred cut, existing).
- Provider fixtures/benchmark/qualification (separate track, per verdict delivery order step 12).
- Free-form drag placement (rejected: reintroduces overlap/out-of-bounds failure modes; second geometry authority).
