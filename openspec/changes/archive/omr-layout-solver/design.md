# omr-layout-solver — Design

## Module

```text
Semantic Blueprint
        │
        ▼
┌─────────────────────────────┐
│ OmrTemplateLayoutSolver     │
│ • normalize                │
│ • validate semantics       │
│ • place atomic rows        │
│ • apply validated breaks   │
│ • build pages/fieldBlocks  │
│ • validate geometry        │
│ • canonicalize output      │
└─────────────┬───────────────┘
              ▼
       SolvedOmrTemplateLayout
        ├─ normalized blueprint
        ├─ canonical template (pages + fieldBlocks)
        ├─ canonical template bytes + hash
        ├─ page/layout projection
        └─ typed diagnostics
```

```php
final class OmrTemplateLayoutSolver
{
    public function solve(OmrTemplateLayoutBlueprint $blueprint): SolvedOmrTemplateLayout;
}
```

Pure and deterministic: same blueprint → same bytes, same hash, every time. No I/O, no catalogs inside the solver. `SolveOmrTemplateLayout` (application action) performs area/catalog lookups and delegates to the solver; preview and save both call the action.

## Geometry profile (v1, inside the solver)

| Constant | Value | Note |
|---|---|---|
| PPI | 242 | canonical |
| BUBBLE | 56×56 | canonical |
| MARGIN | 80px | printable inset |
| ROW_PITCH | 80px | vertical row pitch |
| IDENTITY_LABELS_GAP | 66px / 60px bubbles gap | existing identity grid |
| HEADER_ZONE | y ≤ ~500px | title/header/page/note |
| FOOTER/CALIBRATION reserve | bottom 80px + strip | from BundleRenderer |
| Paper long | 2060×3144 | default |
| Paper legal | 2060×3560 | single paper per template |

Column rectangles: derive N equal bands inside `[MARGIN, pageW − MARGIN]` with a fixed inter-column gap (e.g. 40px), starting below the reserved header + identity zone. Identity grids repeat on every page above the flow zone when `identity.repeat_on_every_page`.

## Blueprint schema v1

```json
{
  "schema": "nexiam.omr.layout-blueprint.v1",
  "paper": "long",
  "columns": 4,
  "identity": { "applicant_number": true, "birthdate": true, "repeat_on_every_page": true },
  "sections": [
    { "area_id": 12, "question_from": 1, "question_to": 60, "option_count": 4, "break_before": null },
    { "area_id": 18, "question_from": 1, "question_to": 40, "option_count": 4, "break_before": "next_page" }
  ]
}
```

Controls exposed initially: paper (single value), columns (2|3|4), ordered sections, question ranges, option count/type, `next_column`, `next_page`, repeated identity. Not exposed: raw origin, bubblesGap, labelsGap, page coordinates, per-page paper dimensions — those stay in the versioned geometry profile.

## Placement algorithm

1. Resolve paper profile + reserved bands (header, identity, footer, frame, calibration).
2. Derive column rectangles for the chosen column count.
3. Expand each section into atomic question rows (never split a question).
4. Place rows in blueprint order with (page, column, y) tie-break; `break_before` applied before its section.
5. Refuse a row wider/taller than an empty column (typed).
6. Compact contiguous rows with identical geometry into range-based fieldBlocks.
7. Emit pages ascending; canonicalize + hash.

## Break semantics

- `next_column`: continue at column+1, same page; if last column, start column 1 of next page.
- `next_page`: start column 1 of next page.
- Validated pin `{"kind":"column","column":N}` (later): N within count, no backward move, section must fit remaining capacity of N, else `section_does_not_fit_pinned_column`. Never silently re-flow.

## Validation order

1. Request/schema shape → 2. Blueprint semantics (schema, paper, columns, ranges, break target, non-empty) → 3. Feasibility (row fits column, identity fits, pinned section fits) → 4. Bounds/margins → 5. Overlap, duplicate labels, duplicate positions → 6. Catalog coverage (area exists, range valid, booklet exact at activation) → 7. Manifest derivation → 8. Activation invariants.

Diagnostics carry section/range/page/column context (typed, not `RuntimeException('invalid template')`).

## Metadata columns (migration)

`omr_answer_sheet_template_versions` +=
- `layout_blueprint_json` jsonb nullable
- `layout_blueprint_hash` char(64) nullable
- `layout_solver_version` varchar nullable

Ownership: blueprint = editable intent; `native_bundle_json.template` = canonical solved input; `template_json_hash` = canonical solved hash; `print_overlay_json` = render-only; `provider_config_json` = provider only.

## Canonicalization

- Recursively sort object keys; preserve list order; integer geometry units; reject non-finite/fractional geometry; fixed JSON flags; hash exactly the bytes written to the bundle.

## Preview contract

- `POST /setup/omr/layout/preview` (debounced): blueprint + overlay → `{normalized_blueprint, layout:{pages, page_count}, template_json_hash, diagnostics}`. Svelte draws only returned positions.
- `POST /setup/omr/layout/preview-pdf`: same inputs → same solver → `RenderBundlePdf`.
- Save re-solves; test asserts `hash(preview) == hash(saved)`.

## Save boundary (same pass)

solve → derive/validate manifest → stage content-addressed artifacts (Laravel Storage, private disk) → DB transaction (lock template row, allocate version, insert draft + hashes, audit) → publish reference. Corrections: row lock before `max(version)+1`; DB unique (template_id, version); duplicate detection before visible allocation; no `rm -rf`; retention job for abandoned staged objects; migrate `NativeBundle::load` callers to the durable store.

## Delivery order (from verdict)

1. (done) Authority consolidation — one active change; others parked; usability archived PASS.
2. (done) Founder decision + requirements recorded in OpenSpec (this change).
3. Blueprint persistence + pure solver.
4. Deterministic/hash/golden solver tests.
5. Durable bundle staging + version allocation.
6. Wire server solving into save + PDF preview.
7. Replace Svelte geometry with blueprint editing + server projections.
8. Reorder, range editing, break controls.
9. Absolute pins only if a workflow demands.
10. Solver-generated multipage recognition + real-engine smoke.
11. Browser evidence, HANDOFF, checkpoint, strict validation.
12. Provider qualification — separate track.
