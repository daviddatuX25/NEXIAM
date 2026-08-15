# Proposal: OMR Configuration & Template Builder

## Why

The OMR capture cut shipped with configuration created only by the seeder (bundles, booklets, pairs). Staff cannot register a real answer-sheet design, author an exam booklet/answer key, activate a template–booklet pair, or design new sheets. The founder locked: template geometry is **imported and verified, never hand-authored as a second authority**; the compliant builder **writes the native `template.json` itself**; booklets are NEXIAM-authored data with a private answer key; configuration surfaces live under **System Settings**.

## What Changes

- **System Settings → OMR Configuration** section (Setup hub, legacy list→create/edit shape): answer-sheet templates, question booklets, template–booklet pairs, print-queue link, readiness checks.
- **Template screens**: list/show with pinned bundle identity (hashes, engine commit, versions, per-page geometry); **bundle intake** (upload `template.json`/`config.json`/reference assets → hash + engine verification → derived-manifest preview → activate as a new immutable version).
- **Template builder (compliant)**: visual editor that writes native `template.json` — pages, page dimensions/paper, identity grid (positions/digits/gaps), question blocks (area, questions, options, gaps, bubble size) — with live preview from the shared renderer. Output must pass full bundle validation before saving as a draft; activation stays one-way.
- **Booklet authoring**: questions per aptitude area with correct options (answer key), versioning, draft→active lifecycle; answer key stays NEXIAM-only (hashed, never on the provider seam).
- **Pair activation**: select template version × booklet version → `ActivateOmrConfiguration` with typed compatibility feedback.
- **Capture-surface reject/retry wiring** (actions already shipped; UI reach follows the same screens).

## Capabilities

### New Capabilities

- `omr-configuration`: template intake/activation, compliant template builder, booklet + answer-key authoring, pair activation, System Settings placement, and the configuration state machine (draft/active/retired, immutability after activation).

### Modified Capabilities

<!-- None: canonical specs under openspec/specs/ are not yet promoted. -->

## Impact

- Reuses: `NativeBundle`, `NativeManifestDeriver`, `ActivateOmrConfiguration`, `SensitiveAssetStore`, capability authorizer.
- Depends on `omr-printing-pipeline` for the shared `BundleRenderer` (builder previews and blank-sheet thumbnails); no dependency the other way.
- No changes to the provider boundary, answer-key authority, or qualification gates.
