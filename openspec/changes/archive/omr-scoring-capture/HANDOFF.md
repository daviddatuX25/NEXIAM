# HANDOFF — OMR Scoring Capture

**Change ID:** `omr-scoring-capture`  
**Status:** REAL OMRChecker PROVIDER ACTIVE IN DEV (2026-08-13) — native bundle → derived manifest → isolated signed worker → aligned evidence → staff review, all exercised end to end
**Runnable OMR demo:** YES (`http://127.0.0.1:18000/grading/sessions/1/omr`)

## Current truth

- The first cut is online Manual + Auto capture through the mockup-aligned capture/gallery/review UI.
- The review surface now includes a protected identity crop, per-area detected/final score table, and per-item cropped evidence with detected answer, confidence-derived mark, score, and manual-editor handoff links.
- The dev runtime now runs the **real OMRChecker engine** (`driver=omrchecker`); the deterministic `nexiam.fake` provider remains the forced test-suite provider only.
- The inference worker is a required Compose service (no longer `profiles: ['optional']`); `app`/`queue` wait on its health before starting.
- The reviewed sheet image is the **encrypted aligned derivative** produced by OMRChecker (persisted via `aligned_asset_*` on the sheet); the original stays immutable forensic evidence.
- The current observed demo database has grading session `1` (EX-DEMO-1), open batch `1` on the **A4 v2 pair**, needs-review Auto sheet `1` (real OMRChecker, 200 items, applicant `2345678901`, 0 issues).
- **A4 answer-sheet bundle v2** (`SYN-OMR-ONE-PAGE` v2, 2000×2828): true A4 page, NAME/EXAM DATE boxes, labeled applicant-number grid (positions 1–10 × digits 0–9), 200 questions in VERBAL/QUANT sections with 5 numbered columns each and A–D headers. Seeded alongside v1 by `OmrDemoSeeder`; capture batches select the v2 pair.
- **Multi-page answer sheets shipped (v3, 2026-08-13; UI + v4 standard 2026-08-14):** two-page A4 bundle (`SYN-OMR-ONE-PAGE` v3) — page 1 VERBAL + identity + name box, page 2 QUANT + identity, ≥4.6mm bubbles, "PAGE X OF 2" headers, safe print margins. Capture is page-aware: pages attach in any order, analysis dispatches only when the set is complete, per-page runs with page-level claims/retries/sweeper, guarded merge into one proposal, identity reconciliation across pages, page-level aligned evidence, duplicate-page refusal, incomplete-set resume state (`pending_pages`). Real-engine proof: two-page fixture set → both pages succeeded, merged 200 items, applicant `4567890123`, 0 issues. `OmrMultipageTest` covers out-of-order upload, duplicate refusal, incomplete-set gating. **2026-08-14: the multipage UI shipped** — capture stepper ("Set page X of N"), page chips on gallery cards, Resume set / Retake page actions (audited `ReplaceOmrSheetPage`, route `grading.sessions.omr.sheets.pages.replace`), review page tabs with per-page detected identity, structured confirm errors naming offending sheets, and reject-batch / retry-scan actions on the capture surface. The **v4 long-bond pair** (216×330mm, 4 cols × 25 rows per page, kit `omr-test-kit/answer-sheet-longbond-v4.pdf`) is the standard; v2/v3 A4 remain interim-synthetic.
- **Physical test kit:** `omr-test-kit/` (repo root, served on the static server) — `answer-sheet-fit-a4.pdf` (true A4) + `answer-sheet-a3.pdf` + `README.md` with six shade-and-scan tests and expected outcomes (key repeats A B C D per area). Printed sheets are machine-tested end to end (seed-2 fixture: 200/200 items, applicant `2345678901`, 0 issues).
- The protected OMR surface requires the dedicated OMR capability; manual-scoring capability alone is insufficient.
- Sensitive sheet assets use the private encrypted store and protected image projection.
- The current observed demo database (2026-08-14 browser session) has grading session `1`, open batch `1` on the **v4 long-bond pair** with four sheets (one needs-review with fabricated per-page identity, others terminal-failed on 1×1 test PNGs — expected, the real engine rejects degenerate images), plus configuration demos: `DEMO-BUILT` template v1 (draft, 10-item QUANT) + v2 (activated, 100-item QUANT), `DEMO-BKLT` booklet v1 (activated, 100 QUANT), active pair #5. Recreate with `foundation:reset --force` + `db:seed --class=GradingDemoSeeder`; the DEMO-* artifacts were created through the browser/tinker and are not part of the seeder.
- **OMR configuration usability cut shipped 2026-08-14** (change `omr-configuration-usability`): breadcrumbs on every OMR page (StaffLayout, not PageHeaderBar), journey-hub index with anchored sections + print-queue list, **sheet previews that equal print** (`GET /setup/omr/templates/{version}/preview` + `POST /setup/omr/templates/preview-draft`, shared `RenderBundlePdf` service), **print overlays** (`print_overlay_json` — title/header/footer/name-box/per-page notes, render-only, never sent to the engine), booklet Show with **key preview grid**, dedicated booklet Create page with draft pre-population, **new-draft-from-active copy** (audited), **bulk key paste** (`position area_code option`, per-line errors), builder **draft hydration** + upgraded SVG sketch + full-preview PDF, inline retire confirm panels listing cascading pairs, and the **optional bubbled birthdate identity grid** (founder lock extension; BIRTHDATE_1..6 MMDDYYYY, derivier + activation projection + per-page reconcile + review-card display + birthdate_invalid issues). Usability tests: 7 feature tests in `OmrConfigurationUsabilityTest`.
- Founder decision lock (2026-08-12): OMRChecker-native geometry is canonical; NEXIAM derives review geometry from it, never compiles NEXIAM coordinates back into it; the answer key remains NEXIAM-only; real-engine confidence is null; review uses an encrypted aligned-sheet derivative; one isolated child handles one sheet and exits.

- **2026-08-15 final OMR demo proof:** v5 is the active seeded long-bond pair (SYN-OMR-ONE-PAGE v5 + SYN-OMR-BOOKLET-C), with v4 retained as the two-page renderer baseline; booklet B remains an unpaired draft and pair #1 is retired. OmrDemoBatchSeeder provides a needs-review 2-page sheet (1234567890 / 01012010), a pending-pages 1-of-2 sheet, and a failed-terminal retry fixture at /grading/sessions/1/omr. Recreate with foundation:reset --force then db:seed --class=GradingDemoSeeder --force; seeded batch rows are idempotent/self-healing.
- **Margin/print proof:** final PDFs from /setup/omr/templates/4/preview and /setup/omr/templates/5/preview returned 200 application/pdf and were rasterized/vision-checked on all four pages. Labels clear the empty bubbles, identity grids and question columns stay inside the frame, v5 footer text stays above the border, and calibration text/bar remain below it without crossings. BundleRenderer and the builder share the explicit label clearance; the renderer name box is hidden when the header stack leaves no gap.
- **2026-08-15 authenticated download proof:** print jobs `1` (v4) and `2` (v5) were rendered through `RenderPrintJob`, downloaded with the authenticated `GET /printing/jobs/{id}/download` route, and returned `200 application/pdf`, `Content-Disposition: inline; filename="print-{id}.pdf"`, and `Cache-Control: no-store, private`. Downloaded artifact sizes were v4 `922,916` bytes and v5 `1,044,777` bytes; stored checksums matched the downloaded bytes exactly: v4 `76d34b618207b8960ccd8ac42c5d88f0b74ee2655d151805692e202dda2ae95d`, v5 `5f2f81920f195e242663969c7523738e82564c47ebd2cd024173ae2573e5cd6f`. `pdftoppm -png -r 96` produced 2 pages for each; every downloaded page was visually clean (no margin, label/bubble, footer, name-box, or calibration crossing). Raster hashes matched the corresponding preview pages exactly: v4 p1 `10183fe4...`, p2 `3284e861...`; v5 p1 `39056526...`, p2 `9bc35f0e...`.
- **2026-08-15 async worker proof:** a queued print job initially exposed a stale queue image (`bundle_invalid`); rebuilding `app` and `queue` with current source and restarting Compose made the same job `ready` with `progress_done=1`. Authenticated download of job `3` returned `200 application/pdf` (1,044,777 bytes; stored checksum `e39d59c5b33a50b94b15aea8e6fa7af28803637694415d69d6bdd318baf2119b`), and both raster page hashes matched the direct v5 artifact exactly (`39056526...`, `9bc35f0e...`).
- **Post-fix regression:** `PrintPipelineTest` passed **7 tests / 53 assertions** including selected-version pinning, DB snapshot + overlay rendering, checksum-verified download bytes, and download headers. The core OMR set (printing, configuration, multipage, native bundle/manifest, layout solver, envelope) passed **66 tests / 19,624 assertions** with 1 intentional skip; hardening cases passed on isolated reruns (7 tests / 61 assertions plus the previously flaky needs-review case at 8 assertions).
- **2026-08-15 continuation proof:** rebuilt frontend assets were deployed into the running Compose app; `/setup/omr/templates/builder` now renders the server projection as an answer-sheet SVG (one page, 2060×3144, dashed 80px margin guide, identity/question bubbles) instead of an empty Preview panel. Fresh authenticated `/setup/omr/templates/4/preview` and `/setup/omr/templates/5/preview` responses were `200 application/pdf` (922,916 and 1,044,777 bytes); all four raster pages were visually rechecked: v4 columns and identity grids stay inside the frame, v5 title/header/page-note text is clear of the top frame, footer stays inside the frame, and calibration text/bar remain below it without crossings. Focused OMR regression suites passed **55 tests / 19,593 assertions / 1 intentional skip**; `npm run build` passed with existing Svelte warnings. `openspec validate --changes --strict --no-interactive` passed the sole active change.

## Live tunnel (current session)

- Public HTTPS URL: `https://2f77-136-239-226-2.ngrok-free.app` (ngrok tunnel to `:18000`; URL is ephemeral — restart with `ngrok http 18000`)
- Proxy trust is configured in `app/Providers/AppServiceProvider.php::boot()` via `TrustProxies::at(env('TRUSTED_PROXIES', '*'))`, so asset URLs stay `https://` through the tunnel. (The repo's `bootstrap/app.php` sits on an un-writable mount owned by `ddx25`; do NOT rely on it — the provider is the durable home.)
- Camera capture is live in `resources/js/Pages/Assessment/Omr/Capture.svelte`:
  - Real `getUserMedia` feed (reactive `videoEl`/`cameraStream` via `$state`) → snapshot to canvas → JPEG upload.
  - Manual mode opens review immediately after capture; Auto mode queues in the background and stays on camera.
  - Settings (top-right gear) dropdown lists every camera the browser reports (`enumerateDevices`, refreshed on open, manual refresh button, live `devicechange` re-scan); selecting one uses `deviceId: {exact}` and keeps the selection.
- Responsive: the desktop Split/Focus layout only applies at ≥1024px. Below that the toggle is hidden and the workspace is single-panel (camera OR gallery/review), driven by screen state.

## Demo credentials

All seeded staff passwords are `password` unless overridden in `system/.env`.

| Account | Email | Access |
|---|---|---|
| Test Administrator / guidance counselor | `guidance.counselor@nexiam.test` | OMR capture, gallery, review, and confirmation |
| Guidance clerk | `guidance.clerk@nexiam.test` | ordinary guidance surfaces; OMR is denied |
| Registrar clerk | `registrar.clerk@nexiam.test` | ordinary registrar surfaces; OMR is denied |
| HR administrator | `hr.admin@nexiam.test` | personnel/admin surfaces; OMR is denied |

## Recreate the demo

Run from `system/`:
```bash
docker compose build app queue inference
docker compose up -d --force-recreate app queue inference
docker compose exec app php artisan foundation:reset --force
docker compose exec app php artisan db:seed --class=GradingDemoSeeder --force
```

To capture a deterministic real-engine sheet afterwards (optional; the seeder's sheet `1` is already real-engine):

```bash
docker compose run --rm --no-deps \
  -v "$PWD/app/Assessment/Omr/NativeBundles/SYN-OMR-ONE-PAGE/v1/template.json:/probe/template.json:ro" \
  -v /tmp:/probe inference \
  python /app/generate_fixture.py /probe/template.json /probe/omr-filled.png --seed 3
```

then upload `/tmp/omr-filled.png` through the capture page (Auto) — it analyzes through OMRChecker and lands in Needs review with 200 detected items.
The reset/seed flow recreates the synthetic institution, completed grading session, active OMR template/booklet pair, open capture batch, encrypted review fixture, and demo staff accounts.

## Observed URLs and workflow

| Surface | URL |
|---|---|
| Grading session | `http://127.0.0.1:18000/grading/sessions/1` |
| Capture/gallery | `http://127.0.0.1:18000/grading/sessions/1/omr` |
| Sheet review | `http://127.0.0.1:18000/grading/sessions/1/omr/sheets/5` |
| Protected image | `http://127.0.0.1:18000/grading/sessions/1/omr/sheets/5/image` |

Chromium proof completed against the running Compose app:

1. Opened the capture page and observed camera, Gallery, Capture sheet, Manual, Auto, and Upload controls.
2. Uploaded a synthetic screenshot through Manual; the gallery showed `Scan 5 — Needs review`.
3. Opened the review surface and observed protected evidence, unbound identity, server proposal, confidence, and roster binding.
4. Bound the sheet to `APP-2026-00002 · Demo Accepted` and saved; the gallery showed `Scan 5 — Reviewed` with the truthful notice that batch confirmation writes scores.
5. Auto upload produced a queued/needs-review gallery path; transient states are refreshed by the page.

## Queue and recovery

The Compose queue service runs:

```bash
php artisan queue:work --sleep=1 --tries=1 --timeout=90
```

OMR Auto analysis is dispatched after upload commit and persists the sheet state; failed terminal sheets remain visible for staff retry/fallback rather than disappearing.

## Verification

Observed commands and results:
```bash
docker compose exec -T app php artisan test tests/Unit
# Tests: 10 passed (10697 assertions)

docker compose exec -T app php artisan test tests/Feature
# Tests: 272 passed (3890 assertions)

docker compose exec -T app php artisan test tests/Feature/Assessment/OmrDetectionEnvelopeTest.php
# Tests: 11 passed (1359 assertions)
```

Real-provider proof (observed 2026-08-13): a seed-3 fixture sheet uploaded through `UploadOmrCaptureSheet` analyzed through the signed worker and produced `run_status=succeeded`, `provider=OMRChecker`, 200 items, applicant `3456789012`, aligned hash `161d8aa6…fe181` (691,165 bytes) persisted encrypted; the review page rendered the aligned image from `/grading/sessions/1/omr/sheets/1/image`.

Frontend/UI proof:

```bash
npm run build
# ✓ built in 12.24s (existing Svelte warnings only)
```

Chromium smoke on `/grading/sessions/1/omr`: camera → gallery → `Scan 6` review rendered 2 score rows and 200 item rows with protected image evidence; the item table exposed `Item evidence`, `Detected`, `Confidence`, `Mark`, `Score`, and `Action`; binding `APP-2026-00002` exposed edit links; the first edit link resolved to `/grading/sessions/1/attempts/2` and rendered `APP-2026-00002 — Scores`.

The SQLite test migrations now skip PostgreSQL-only checks, trigger functions, and encrypted-column `ALTER` statements while production PostgreSQL retains those constraints and triggers.

## Phase B activation state (updated 2026-08-13)

The 2026-08-12 preflight blockers are resolved:

- `OmrDetectionEnvelopeTest` passes 11 tests (1359 assertions); the prior snapshot-hash TypeError is fixed.
- Seed geometry was replaced by one visibly synthetic OMRChecker-native bundle (`SYN-OMR-ONE-PAGE/v1`): 10 bubbled applicant-number digits + 200 four-option questions, full bubble grid in `reference.svg`, manifest hashes pinned. NEXIAM derives the review manifest and relational rows from the pinned bundle; no reverse compiler exists.
- Envelope v2 is the live provider boundary: no `correctOptionCode`, answer key, correctness, or scores cross it; run/correlation/booklet identity is echoed and validated.
- Durable current-run claim/token/version persistence guard is in place (`omr_processing_runs` claim lease + expected-sheet-version checks).
- Identity is bubbled applicant-number digits only; staff binding is mandatory.
- Real engine returns `confidence: null` and `calibration_status: unqualified`; the validator rejects confidence from unqualified providers.
- Review uses the encrypted aligned-sheet derivative (worker-aligned PNG, `aligned_asset_*` columns) plus bounded field-block shifts; the original image stays immutable forensic evidence.
- Worker patches remain limited to headless import safety and the stale/unbound overlay-coordinate fix. Native geometry, preprocessing, thresholds, and classification are unchanged.
- Label expansion now mirrors upstream OMRChecker exactly (no zero-padding: `VERBAL_Q_1`, not `VERBAL_Q_001`); `PinnedSnapshot` derives native labels as `{AREA}_Q_{n}`.

Still open (unchanged gates): production activation (client corpus, qualification/approvers, retention/legal-hold, operational drills), real-engine confidence calibration, and multi-page grouping — none of these are first-cut behavior.

### Provider selection mechanics (env-resolution gotcha)

- `config/omr.php`: driver defaults to `omrchecker` (non-testing) or `fake` when `app.env=testing`; `allow_fake` defaults likewise. PHPUnit additionally forces `OMR_PROVIDER=fake` / `OMR_ALLOW_FAKE_PROVIDER=true`.
- PHPUnit 12 applies `<env force>` to `$_ENV` + `putenv` only, never `$_SERVER`; Laravel's `env()` reads `$_SERVER` first (Dotenv adapter order) and this image runs `variables_order=EGPCS`, so a Compose `environment:` entry would silently beat phpunit. **Do not add `OMR_PROVIDER` or `OMR_ALLOW_FAKE_PROVIDER` to `compose.yaml`** — this was hit and fixed on 2026-08-13.
- OMR images remain on the authenticated private projection with `private, no-store`.
- The worker receives signed image bytes plus the approved native bundle identity/content and expected derived-manifest labels only; it receives no asset URL, database/storage access, roster record, answer key, score, encryption key, or second coordinate table.
- No OMRChecker failure falls back to fake detection.
- Synthetic benchmark success grants development evidence only. Production remains blocked on approved client input/corpus, qualification/approvers, retention/legal-hold, and operational drills.

---

# NEXT PATH — Junior execution map

Use these files in this order:

1. `proposal.md` — Phase A/Phase B boundary and non-goals.
2. `design.md` §§16–23 — observed gaps, architecture decisions, native bundle/derived manifest, protocol, aligned evidence, worker, failures, qualification.
3. `specs/nexi-omr/spec.md` OMR-18–OMR-26 — normative behavior and edge-case scenarios.
4. `tasks.md` — exact ordered implementation checklist and stop gates.
5. This handoff — runnable demo, credentials, URLs, Compose facts, and observed evidence.

## First implementation slice

1. Add a regression test documenting why the current hand-calculated seed geometry is rejected.
2. Define and persist the immutable native bundle contract and hashes.
3. Author one visibly synthetic `template.json`/`config.json` bundle with stable question labels and bubbled applicant-number digits.
4. Pin OMRChecker/dependencies and apply only the two minimal headless/overlay patches.
5. Implement activation-time expansion into the deterministic NEXIAM review manifest and relational projections.
6. Delete the old seed coordinate calculations and prove no reverse compiler exists.
7. Continue with envelope v2/current-run claims, then generate the compact acceptance pack before the online adapter.

Follow `tasks.md` in order; every Gate is a stop condition.

## Pinned upstream facts

- OMRChecker commit: `de3f6982822895491be760325fd43e65e90fa293` (verified signed GitHub commit dated 2026-06-30).
- License: MIT.
- Native template requires `bubbleDimensions`, `pageDimensions`, `preProcessors`, and regular `fieldBlocks`.
- Upstream core emits thresholded field values and a sheet multi-mark flag. Phase B consumes those observations through the narrow in-memory seam, returns null confidence, and never parses CSV/output folders online.
- One captured image is one sheet. **Multi-page grouping shipped 2026-08-14**: two-page v4 long-bond pair is the standard; capture stepper ("Page X of N"), incomplete-set resume, audited page replacement, review page tabs with per-page identity (see `omr-multipage-answer-sheets` + `omr-configuration` changes).

## Pinned-source exploration (2026-08-12)

- Junior-readable architecture guide: [`../../../docs/omrchecker-integration-explainer.md`](../../../docs/omrchecker-integration-explainer.md). It covers ownership, native-bundle activation, the derived review manifest, use cases, aligned evidence, upstream seams, envelope v2, isolated worker topology, failures, and qualification gates.
- Read-only checkout: `system/.research/omrchecker-de3f698/` at `de3f6982822895491be760325fd43e65e90fa293`; `system/.research/` is gitignored and is not shipped code.
- Narrow upstream seam: owned grayscale decode → `ImageInstanceOps.apply_preprocessors(...)` → `read_omr_response(..., save_dir=None)`; bypass `entry.py`, directory recursion, CSV/evaluation, and output folders.
- Initial owned patches: lazy/headless interaction and the stale/unbound overlay-coordinate fix only. Per-bubble telemetry/calibration is a later separately reviewed change; every warp matrix is unnecessary because review uses the aligned derivative.
- One isolated detector child per sheet is the safe first runtime because upstream owns shared mutable state and process exits; only benchmark evidence may justify a recycled single-request pool.
- Upstream dependencies are not deployable as-is: lower bounds are open and `cv2` is not in `requirements.txt`; the worker needs a complete hash-locked dependency set.

## Target provider topology

Native bundle activation → derived review manifest; then `AnalyzeOmrCaptureSheetJob` → `OmrDetectionPort` → `OmrCheckerDetection` → signed multipart `/v1/detections` → one isolated headless OMRChecker child → observations + aligned sheet + block shifts → validator → proposal/issues → staff review/binding → NEXIAM answer key/score conversion.

## Development acceptance gates

- 0% false-green across the acceptance pack.
- ≥99% clean definite-answer accuracy.
- Record uncertain-case behavior with mandatory review; do not invent confidence.
- ≥99.5% identity precision with ≥95% clean suggestion coverage; never auto-bind.
- Aligned-review crop p95 ≤2% of the smaller aligned-page dimension.
- Manual warm p95 proposal ≤5 seconds at declared pilot concurrency.
- Deterministic repeats, bounded resources, restart/timeout/malformed-output recovery, and no score write on failure.

## Environment notes

- Compose services: `app`, `queue`, and **required** `inference` (app/queue wait on its health); code is baked into images, so rebuild after PHP/Python changes.
- Keep production inference internal; host port `8100` is development-only.
- Run PHP proof inside Compose: `docker compose exec -T app php artisan test`.
- Deterministic fixture generator is packaged in the worker image: `python /app/generate_fixture.py <template.json> <out.png> --seed N [--blank] [--margin-ratio R]` (see Recreate the demo).
- `bootstrap/app.php` is on an un-writable mount; keep framework configuration in `AppServiceProvider` or config files.
- Current HTTPS ngrok URL is ephemeral; restart with `ngrok http 18000` and update the observed URL when needed.

## Demo environment state (2026-08-15)

- **Login:** `guidance.counselor@nexiam.test` / `password` (guidance scoring + OMR capability).
- **Seed reset (dev):** `php artisan foundation:reset --force` then `php artisan db:seed --class=GradingDemoSeeder --force` (inside `docker compose -f system/compose.yaml exec -T app`). `GradingDemoSeeder` seeds the configuration demos and invokes `OmrDemoBatchSeeder`; re-running is idempotent, and `OmrDemoBatchSeeder` self-heals partial batches.
- **OMR demo state after seed:** template versions v1–v4 active pairs (v2–v4 with booklet A; v1 retired for retirement exclusion), v5 active pair (2-page long bond, identity + birthdate grids on every page, overlay title/header/footer/note, booklet C VERBAL 1–100 + QUANT 101–200), booklet B draft unpaired (all-A QUANT 100). Demo capture batch #1 on v5: 1 needs-review 2-page sheet (applicant `1234567890`, birthdate `01012010`, confidence 0.92, bound to an admission attempt), 1 pending_pages (1 of 2 → "Resume set"), 1 failed_terminal (retry-able).
- **Demo URLs:** `/setup/omr` (hub), `/setup/omr/templates`, `/setup/omr/templates/builder`, `/setup/omr/templates/{id}/preview` + `POST .../preview-draft` (preview-equals-print), `/grading/sessions/1/omr` (capture).
- **Full suite (isolated container):** `docker compose run --rm --no-deps app php artisan test` → 341 passed, 1 skipped, 22,414 assertions. OMR suites: 50 passed, 1 skipped.
- **Margin safety (vision-verified v4 + v5, all pages):** name box hidden when the header stack leaves no room; bottom 7.5mm above topmost field labels; calibration text/bar below the border in the bottom margin; footer lines inside the border; no bubble/border collisions.
- **Real bugs fixed by the demo batch + new tests (2026-08-15):** (1) per-page image URL missing `sheet` param; (2) `OmrCaptureSheet::$fillable` missing `detected_birthdate` (silent data loss); (3) `OmrPinnedSnapshot::manifestExpectations` missing the `BIRTHDATE_` scorable exclusion. All covered by regression tests in `OmrMultipageTest`.
- **Fake detector:** `omr.fake.birthdate_by_page` (page => MMDDYYYY) fabricates deterministic cross-page birthdate conflicts.
- **Image rebuild:** code is baked into the image (`COPY . .`); run `docker compose -f system/compose.yaml build app && docker compose up -d --no-deps app` after PHP changes. `docker compose run` containers always use image-baked code (cp patches to the running app container do not reach them).

## OMR layout solver + blueprint builder (2026-08-15)

- **Blueprint builder is the default authoring surface** at `/setup/omr/templates/builder`: paper, columns 2|3|4, identity toggles, ordered sections with area/range/break selects; every change debounces to `POST /setup/omr/layout/preview` and draws ONLY server-returned positions. Typed diagnostics inline (e.g. `duplicate_question_range`). "Advanced" toggle preserves the legacy geometry editor for old drafts. Drafts with `layout_blueprint_json` hydrate into the blueprint editor via `?version=`; activated versions still refuse.
- **Save boundary:** `SaveAuthoredTemplateDraft` stages immutable artifacts to `storage/app/private/omr-bundles/<code>/v<N>` (private disk), row-locks version allocation, detects duplicates before staging, no `rm -rf`; `php artisan omr:prune-staged-bundles` sweeps orphans. `NativeBundle::load` resolves durable-first with the legacy in-tree fallback. New save route `POST /setup/omr/templates` (was never registered before 2026-08-15).
- **Blueprint metadata:** `layout_blueprint_json` / `layout_blueprint_hash` / `layout_solver_version` on template versions; solver version string requires varchar(48).
- **Proof:** 4-column solve byte-matches the shipped v4 sheet; `hash(preview)==hash(saved)` test; recognition integration test (solver 2-page bundle → capture → merge → needs_review 200 items); real-engine smoke 2026-08-15 — fixture pages → OMRChecker runs succeeded (3.2s/2.7s), applicant `2345678901` genuinely detected, confidence null (qualification-gated). Full suite **361 passed, 1 skipped, 22,519 assertions** on the baked image.
- **2026-08-15 final margin regression proof:** `BundleRenderer` places the frame below the computed header stack, preserves a 0.5mm clearance above the first field labels, and gives per-page notes an explicit 8pt/3mm line box so note text cannot touch the frame. `TemplateEditor.svelte` mirrors the frame bounds and identity-grid geometry. Fresh authenticated v4/v5 previews returned `200 application/pdf` (v4 `922,912` bytes; v5 `1,044,768` bytes before the note line-box refinement, `1,044,770` after), rasterized to two pages each; v4 p1/p2 and v5 p1/p2 were visually clean. Final focused renderer suites: **17 passed, 1 skipped, 169 assertions**.
- **2026-08-15 compact one-page profile:** six columns use 40×40 geometry-unit bubbles (about 4.2mm at 242PPI), 52px option pitch, and question origins `[120,452,784,1116,1448,1780]` at y=900; six 25-question domains fit one long-bond page with the rightmost bubble ending at 1976, inside the 80px margin. Applicant/birthdate grids use origins `[200,315]` and `[1000,315]` with a 52px digit pitch, leaving the printed `NAME (PRINT):` line visible between the header and identity grids.
- **Compact verification:** server preview returns the compact identity gaps; deployed builder SVG now consumes each returned block's `labelsGap`/`bubblesGap` (identity rows 52px, not the legacy 60px fallback). Fresh builder PDF returned `200 application/pdf` (`706,210` bytes), rasterized one page: name line, both identity grids, all 150 answer rows, footer, calibration bar, margins, and rightmost column were visibly clear with no overlaps.
- **Regression proof:** `OmrTemplateLayoutSolverTest` 17 passed / 69 assertions; focused OMR suites 57 passed, 1 skipped / 465 assertions, including compact renderer name-line coverage. `BundleRenderer` rounds name-box coordinates to stable 2-decimal millimetre CSS values.
