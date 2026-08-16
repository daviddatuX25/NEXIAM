# OMR Template Layout Solver — Adopted Refactor Verdict

> Status: **ADOPTED planning contract** (2026-08-15). Recorded verbatim from the
> architectural review; load-bearing claims spot-checked against the repository
> and verified (defaultConfig hardcodes `[2060,3144]`; version allocation is
> `max(version)+1`; per-page `pageDimensions` with long-bond fallback).
> One server-side solver; one active OpenSpec change; renderer + multipage
> capture/merge machinery preserved as-is.

---

## Verdict

Adopt the merged contract, including break-first with validated absolute pins.

A focused refactor is required before adding reorder/range editing or richer overrides. The current builder has the wrong authority seam: Svelte calculates geometry and sends canonical template.json to endpoints that mostly trust it.

### What the repository currently does

#### 1. Geometry is client-owned

`system/resources/js/Pages/Setup/OmrConfiguration/Templates/Builder.svelte` currently:

- calculates bounds, margins, collisions, and placement at lines 215–301;
- hardcodes bubble size, margins, and four column origins at lines 221–223;
- assembles canonical fieldBlocks at lines 357–383;
- sends that generated template_json to both save and preview at lines 396–450.

That contradicts "one server solver." The browser is presently a second geometry authority.

#### 2. Preview and save share rendering, but not solving

`OmrConfigurationController`:

- save accepts client-generated template_json at lines 292–327;
- preview accepts the same client-generated JSON and sends it directly to RenderBundlePdf at lines 545–563.

RenderBundlePdf correctly provides one rendering path, but there is no shared server-side layout path yet.

#### 3. Paper selection is internally contradictory

Current contracts disagree:

- Builder exposes long and legal dimensions.
- `SaveAuthoredTemplateDraft::defaultConfig()` always emits long-bond dimensions [2060, 3144].
- `NativeBundle::validateBundleShape()` requires every page to match those config dimensions.
- BundleRenderer uses one globally configured paper size for the entire PDF and refuses mismatches.

Therefore:

- legal authored sheets can fail during bundle loading;
- mixed paper sizes across pages are not supported;
- paper must be a blueprint-wide property, not a per-page choice, unless the renderer is deliberately redesigned.

Use one paper size for every page in a template. Boring and correct.

#### 4. Draft persistence is unsafe

`SaveAuthoredTemplateDraft` currently:

- allocates versions using `max(version) + 1`;
- creates files under `app/Assessment/Omr/NativeBundles/...` inside a database transaction;
- only removes the directory when bundle loading/derivation fails;
- performs duplicate detection after writing and derivation;
- can leave an orphan directory after duplicate refusal, audit failure, transaction rollback, or another post-derivation exception;
- writes mutable runtime data into the application source tree, which is unsuitable for immutable containers or multiple application replicas.

This should be fixed in the same refactor. Otherwise the solver will be clean while the save boundary remains fragile.

#### 5. Hashing is not fully canonical

`template_json_hash` hashes pretty-printed JSON bytes produced from the incoming array. Stable PHP insertion order helps, but it is not a sufficient canonical contract when input originates in JavaScript.

The solver must produce canonical bytes:

- recursively sort object keys;
- preserve list order;
- use integer geometry units;
- reject non-finite/fractional geometry;
- encode with fixed JSON flags;
- hash exactly the bytes written to the immutable bundle.

### Recommended module

Create one deep module:

```text
Semantic Blueprint
        │
        ▼
┌─────────────────────────────┐
│ OmrTemplateLayoutSolver     │
│                             │
│ • normalize                │
│ • validate semantics       │
│ • place atomic rows        │
│ • apply validated breaks   │
│ • build pages/fieldBlocks  │
│ • validate geometry        │
│ • canonicalize output      │
└─────────────┬───────────────┘
              │
              ▼
       SolvedTemplateLayout
        ├─ normalized blueprint
        ├─ canonical template
        ├─ canonical template bytes/hash
        ├─ page/layout projection
        └─ typed diagnostics
              │
       ┌──────┴──────┐
       ▼             ▼
 Preview/PDF      Draft save
```

Interface:

```php
final class OmrTemplateLayoutSolver
{
    public function solve(OmrTemplateLayoutBlueprint $blueprint): SolvedOmrTemplateLayout;
}
```

Keep validation, placement, canonicalization, and block aggregation internal. Do not expose a family of shallow calculator classes unless tests demonstrate a genuinely varying adapter.

An application action such as `SolveOmrTemplateLayout` can perform catalog lookups and invoke the pure solver. Both preview and save call that action.

### Blueprint contract

Store semantic intent, not coordinates:

```json
{
  "schema": "nexiam.omr.layout-blueprint.v1",
  "paper": "long",
  "columns": 4,
  "identity": {
    "applicant_number": true,
    "birthdate": true,
    "repeat_on_every_page": true
  },
  "sections": [
    {
      "area_id": 12,
      "question_from": 1,
      "question_to": 60,
      "option_count": 4,
      "break_before": null
    },
    {
      "area_id": 18,
      "question_from": 1,
      "question_to": 40,
      "option_count": 4,
      "break_before": "next_page"
    }
  ]
}
```

Recommended initial controls:

- paper: one value for the whole template;
- columns: 2 | 3 | 4;
- ordered sections;
- question ranges;
- option count/type;
- next_column;
- next_page;
- repeated identity grids.

Do not expose these as ordinary authoring controls:

- raw origin;
- bubblesGap;
- labelsGap;
- page coordinates;
- arbitrary per-page paper dimensions.

Those belong to a versioned geometry profile inside the solver.

### Break and pin semantics

Ship first:

```text
next_column
next_page
```

Both are monotonic and predictable:

- never move already placed content;
- never split a question row;
- a visible unused gap is allowed;
- if there is no next column, next_column starts column 1 of the next page.

Optional absolute column target — only add it if a concrete staff workflow requires it:

```json
{
  "break_before": {
    "kind": "column",
    "column": 3
  }
}
```

Contract:

- it is a validated break, not an arbitrary coordinate;
- target column must be within the selected column count;
- it must not move backward within the current page;
- the entire pinned section must fit in the target column's remaining capacity;
- otherwise return a typed refusal such as `section_does_not_fit_pinned_column`.

Never silently flow a failed pin somewhere else. That would make preview look valid while ignoring authored intent.

### Placement algorithm

Use deterministic integer geometry throughout:

1. Resolve the paper profile and reserved bands.
2. Reserve header, repeated identity grids, footer, frame, and calibration strip on every page.
3. Derive column rectangles for 2/3/4 columns.
4. Expand each section into atomic question rows.
5. Place rows in blueprint order using (page, column, y) as the fixed tie-break order.
6. Apply explicit breaks before placing their section.
7. Refuse any single row wider or taller than an empty column.
8. Group contiguous placed rows with identical geometry into canonical fieldBlocks.
9. Emit pages in ascending page_no.
10. Canonicalize and hash.

Atomic rows do not mean one fieldBlock per question. Placement uses rows as atoms; emission can compact consecutive rows back into range-based blocks.

### Validation order

Use the proposed order, with request-shape validation explicitly first:

1. Request/schema shape.
2. Blueprint semantics:
   - supported schema;
   - paper and column count;
   - valid ranges;
   - valid break target;
   - no empty section.
3. Feasibility:
   - one question row fits a column;
   - identity grids fit;
   - pinned section fits its target.
4. Bounds and reserved margins.
5. Overlap, duplicate labels, and duplicate question positions.
6. Catalog coverage:
   - area exists;
   - requested range is valid;
   - booklet coverage is exact when pairing/activating.
7. Pinned-engine manifest derivation.
8. Activation invariants.

Return typed diagnostics with section, range, page, and column context. Do not reduce everything to `RuntimeException('invalid template')`.

### Metadata placement

Add explicit version fields:

```text
layout_blueprint_json
layout_blueprint_hash
layout_solver_version
```

Keep ownership clear:

- `layout_blueprint_json`: editable authoring intent;
- `native_bundle_json.template`: canonical solved engine input;
- `template_json_hash`: canonical solved-template hash;
- `print_overlay_json`: render-only institutional text;
- `provider_config_json`: provider selection/configuration only.

Do not put the blueprint inside provider_config_json; that would couple authoring intent to provider configuration. "Adjacent metadata" should mean adjacent columns, not a nested provider field.

### Save boundary refactor

Recommended lifecycle:

```text
solve blueprint
    ↓
derive/validate native manifest
    ↓
stage immutable content-addressed artifacts
    ↓
DB transaction:
  lock template row
  allocate version
  insert draft + blueprint/template/artifact hashes
  record audit
    ↓
publish artifact reference
```

Specific corrections:

- lock the parent template row before version allocation;
- enforce unique (template_id, version) at the database level;
- do duplicate detection before allocating a visible version;
- eliminate `exec('rm -rf ...')`;
- use Laravel Storage on a private shared disk;
- use content-addressed immutable keys;
- clean abandoned staged objects through an explicit retention job;
- migrate all `NativeBundle::load()` callers to the durable bundle store.

If production deployment is guaranteed single-node with a persistent shared application volume, local storage could work, but writing under `app/` is still the wrong location.

### Preview contract

Two server-owned surfaces:

Live layout preview — a debounced endpoint accepts blueprint + overlay and returns:

```json
{
  "normalized_blueprint": {},
  "layout": {
    "pages": [],
    "page_count": 2
  },
  "template_json_hash": "...",
  "diagnostics": []
}
```

Svelte draws only returned positions. It does not calculate placement.

PDF preview — the PDF endpoint accepts the same blueprint + overlay, invokes the same solver, then RenderBundlePdf.

Save invokes the same solver again. A test must prove:

```text
hash(preview solved template) == hash(saved template)
```

Do not trust a client-submitted solved template or hash.

### Required tests

Solver contracts:

- deterministic 2/3/4-column output;
- exact fit at the last valid row;
- one-row overflow starts the next column;
- final-column overflow starts the next page;
- no split inside a question;
- next_column and next_page;
- valid and invalid absolute target;
- identity grids repeated on every page;
- header/footer/calibration reserves;
- duplicate range and duplicate label refusal;
- impossible row-width refusal;
- same blueprint produces byte-identical template JSON and hashes.

Persistence contracts:

- concurrent saves allocate distinct versions;
- duplicate blueprint/template refusal leaves no files or rows;
- manifest-derivation failure leaves no visible version;
- audit failure rolls back the row and does not expose an artifact;
- preview never persists;
- active versions remain immutable;
- copying an active version preserves blueprint and overlay provenance.

Rendering goldens — for each generated page:

- assert PDF page count;
- assert MediaBox is exactly the selected paper size;
- rasterize at a fixed DPI;
- measure the 100mm calibration bar within a small tolerance;
- assert there is no unexpected blank page;
- assert identity and question regions remain inside reserved bounds.

This proves PDF generation geometry. It cannot prove a physical printer was configured to 100%; retain the printed calibration-bar check in the operational workflow.

Recognition integration — do not rebuild the multipage recognition subsystem. It already has page-aware upload, per-page runs, guarded merge, repeated identity, conflict evidence, review tabs, and confirmation gates.

Add one test using a solver-generated two-page bundle, not handcrafted template_json, then run it through the existing capture/merge path.

### Correct delivery order

1. Resolve planning authority. The repository currently reports four relevant in-progress changes:
   - omr-scoring-capture — 57/98;
   - omr-configuration — 18/24;
   - omr-multipage-answer-sheets — 25/28;
   - omr-printing-pipeline — 11/26.
   This violates the project's one-active-change rule. Designate one active OMR layout change and explicitly park the rest before implementation.
2. Record the founder decision and new blueprint/solver requirements in OpenSpec.
3. Add blueprint persistence and the pure solver.
4. Add deterministic/hash/golden solver tests.
5. Refactor durable bundle staging and version allocation.
6. Wire server solving into save and PDF preview.
7. Replace Svelte geometry logic with blueprint editing and server-returned projections.
8. Add reorder, question-range editing, and break controls.
9. Add absolute pins only if a demonstrated workflow needs them.
10. Run solver-generated multipage recognition and real-engine smoke.
11. Finish browser evidence, HANDOFF, master checkpoint, and strict OpenSpec validation.
12. Continue provider fixtures/benchmark/qualification separately; do not mix them into layout-solver implementation.

### Verification performed

Focused Compose tests passed:

- draft preview: 1 test, 11 assertions;
- two-page capture merge: 1 test, 12 assertions;
- renderer centering/paper refusal: 1 test, 4 assertions.

The combined three-file run timed out after 360 seconds, so there is no full focused-suite green claim.

Best path: refactor now around the server solver and durable bundle save seam. Preserve the existing renderer and multipage capture/merge machinery; those are already the useful deep modules.

## Founder clarification — one-page six-domain profile

Decision recorded 2026-08-16 after physical-sizing research and geometry review:

- Paper is **long bond only** for the default profile: 216 × 330 mm, canonical geometry 2060 × 3144 px at 242 PPI. US Legal remains an explicit alternate profile, not a scaling fallback.
- Capacity is **one physical page containing 150 questions**: six domains × 25 questions, mapped as one domain per question column.
- Mark profile is **40 × 40 px at 242 PPI**, which renders to approximately **4.20 mm outer diameter** when the outline is included in that envelope. This is the selected compromise between the founder's 3 mm reference and reliable clean/mobile capture.
- MCQ option center pitch is **52 px** for the dense six-column profile; row pitch remains **80 px**. The solver must validate every column, label gutter, printable margin, and reserved band rather than assume four-column geometry.
- The detector remains template-driven: OMRChecker resizes input to the bundle's canonical page dimensions and reads the bundle-defined rectangles/gaps. It may adapt scan coordinates to the selected template, but it cannot compensate for blur, poor fill, perspective, or marks that are too small in the source image.
- Descriptive identity is a **printed name line rendered for human filing and review crop only**. It is never OCR input, never a binding signal, and never part of the provider identity seam. Applicant number and optional bubbled birthdate remain the only machine identity fields.
- The 40 px profile requires fresh solver-generated PDFs, fixture images, and real-engine qualification before activation. Existing v4/v5 bundles remain immutable evidence for the prior four-column geometry.

This decision supersedes the earlier four-column-only sizing recommendation for the default one-page profile; the server-owned solver remains the geometry authority and the renderer/multipage capture machinery remain unchanged at the module boundary.
