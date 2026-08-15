# Tasks: OMR Configuration & Template Builder

> **Execution rule:** complete sections in order. Intake ships before the builder; booklets before pair activation; System Settings wiring last. The builder must never bypass bundle validation.

## 1. Template intake and versions

- [x] 1.1 Template index/show pages (legacy list shape): versions, status, pinned hashes, engine identity, page geometry, active-pair membership
- [x] 1.2 Bundle intake page: upload template.json/config.json/reference assets → hash + engine verification → derived-manifest preview → **persist files into the canonical NativeBundles store with a code-allocation rule** → create draft version (duplicate-hash refusal)
- [x] 1.3 Activation path change: extend `ActivateOmrConfiguration` to accept staff-authored booklet questions (validate coverage/contiguity/conversion instead of generating keys); NO standalone per-version activation routes (pair activation is the only state flip)
- [x] 1.4 Tests: intake failure paths (tampered hash, unknown engine, invalid geometry, duplicate hash), activation immutability, authored-booklet activation, version listing

## 2. Template builder (compliant)

- [x] 2.1 Builder scaffold: load an existing bundle version or start blank; page list with add/remove pages; paper size per page (long bond / legal)
- [x] 2.2 Block placement: identity grid (positions/digits/gaps) and question blocks (area, questions per block, options, gaps, bubble size) with live preview from the shared `BundleRenderer`
- [x] 2.3 Save-as-draft: builder output MUST pass full bundle validation (unique labels, in-bounds non-overlapping regions, contiguity) before creating a draft version; activated versions uneditable
- [x] 2.4 Tests: valid design activates through the unchanged pin → verify → derive → activate path; invalid geometry refused; no second coordinate authority exists

## 3. Booklet authoring

- [x] 3.1 Booklet index/create/edit pages: questions per aptitude area with correct options, contiguous per-area positions, 4-option coverage validation
- [x] 3.2 Booklet version key digest: record SHA-256 of the key on the version row on save; drafts editable, activated immutable; audit on key edits
- [x] 3.3 Tests: key coverage/contiguity validation, immutability, key digest correctness, audit on key edits

## 4. Pair activation & retirement

- [x] 4.1 Pair activation page: select draft template version × draft booklet version → `ActivateOmrConfiguration` with typed compatibility errors
- [x] 4.2 Retire action for template versions, booklet versions, and pairs (retired pairs excluded from new batch selection; open batches unaffected; retired templates printable by reference) with audit
- [x] 4.3 Tests: compatibility failures fail atomically; successful pair usable by capture; retirement exclusion + audit

## 5. System Settings placement

- [x] 5.1 Add "OMR Configuration" section to the Setup hub (capability-gated) linking templates, booklets, pairs, print queue
- [x] 5.2 Readiness checks: active pair exists, booklet key coverage complete, paper size configured; define where print.paper is stored (config + seeder default) and who may change it
- [x] 5.3 Capture-surface reject/retry UI wiring (buttons on batch/sheet cards, typed errors)
- [x] 5.4 Tests: setup hub visibility + capability denial, readiness states, paper config reflects changes, reject/retry route guards

## 6. Verification & handoff

- [ ] 6.1 Run focused configuration suites in Compose and record counts
- [ ] 6.2 Keep the full suite green (283 feature + 10 unit baseline)
- [ ] 6.3 Chromium walkthrough: intake → builder save → booklet authoring → pair activation → capture with the new pair
- [ ] 6.4 Update `HANDOFF.md` with credentials, URLs, commands, and observed evidence
- [ ] 6.5 Update master/project-context checkpoints with observed evidence only
- [ ] 6.6 Run `openspec validate omr-configuration --type change --strict --no-interactive`
