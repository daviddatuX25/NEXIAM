# Result-sheet printing handoff

## What shipped

Full legacy-parity result-sheet printing on the async print pipeline:

- **Versioned result-sheet templates** (`result_sheet_templates`): HTML or Word/ODT upload, paper/orientation/logical-unit, watermark, single-active swap, private checksummed document storage, placeholder analyzer (found/missing required+recommended/extra), live sample preview. Gated by `guidance.assessment.catalog`.
- **Placeholder engine**: legacy ICAT alias map (`{{verbal_aptitude}}` → VERBAL, etc.), per-domain raw/rating/pct, `scores_rows` table injection, dual-slot `_2` variants, course checkmarks, institution + personnel fields, fragmented DOCX macro repair (DOM-safe).
- **Queued printing**: `result_sheet` print jobs — single + bulk (≤200, copies 1–10), pinned inputs, duplicate dedupe, per-item manifest with partial/ready states, merged stack PDF, typed failures, retry with stale-release refusal, `result_printed_at` idempotent on ready, manual mark/unmark (audited), download audit, retention prune (`result-sheet:prune`, scheduled daily).
- **DOCX/ODT → PDF**: LibreOffice headless inside the worker only (real ICAT template conversion proven); HTML → DomPDF.
- **Release seam**: `PrepareResultDocument` now produces a template-backed, checksummed private artifact (no more stub HTML).
- **UI (locked kit)**: Setup › Result Sheet Templates (index/create/edit with placeholder guide, analyzer panel, live preview) and Release › Print Batch (selection, copies, bulk print, preview stack, mark-printed, job status strip + polling), bulk stack preview page.
- Online releases refuse printing; F2F/Both print normally.

## Seed and reset

```bash
docker compose -f system/compose.yaml up -d --build   # image now includes libreoffice-writer
docker compose -f system/compose.yaml exec -T app php artisan migrate:fresh --force
docker compose -f system/compose.yaml exec -T app php artisan db:seed --force
docker compose -f system/compose.yaml exec -T app php artisan db:seed --class="Database\\Seeders\\GradingDemoSeeder" --force
```

`foundation:reset` and `db:seed` run `ResultSheetTemplateSeeder`: active HTML template **ICAT Results (HTML)** + inactive Word template **ICAT Results (Word)** (real SecureCAT `template_main_icat_test1.docx` copied into `database/seed-assets/result-sheets/`).

## Staff login

- Email: `guidance.counselor@nexiam.test` — Password: `password`
- Capabilities: `guidance.assessment.catalog` (template CRUD), `guidance.results.release` (printing/mark-printed)
- Registrar admin (`registrar.admin@nexiam.test` / `password`) must be denied from template + print surfaces.

## Expected seeded state

- Templates: `ICAT Results (HTML)` active; `ICAT Results (Word)` inactive.
- Grading demo: finalized session with scored applicants (raw 85 → `Above Average`). Release one applicant (Guidance › Release › prepare → release, mode f2f) to make it printable — or re-run the demo seeder, which does not auto-release.

## URLs

- Template list: `http://127.0.0.1:18000/setup/result-templates`
- Create: `http://127.0.0.1:18000/setup/result-templates/create`
- Print batch (finalized session id 3 after demo seed): `http://127.0.0.1:18000/release/print/batches/3`

## Verification evidence

- Full suite: **444 passed, 2 skipped (LibreOffice binary + OMR physical matrix — environment), 23,346 assertions**. Result-sheet suites: 42+ tests covering CRUD/single-active/analyzer/preview, data provider (frozen converted ratings), macro repair, HTML/DOCX/dual renderers, real ICAT LibreOffice conversion (pdftotext contains filled name), print jobs (pins, dedupe, online refusal, printed tracking idempotency, bulk manifest, ready-with-partial, stale retry, download auth + audit, prune), mark-printed, template-backed prepare.
- Browser-proven on a live worktree app: template create with live sample preview (iframe renders Juan + scores rows); DOCX analyzer on the real 5.2MB ICAT template (16 placeholders found, required/recommended gaps flagged); print batch → select → Print → job ready → authenticated PDF download; full sheet PDF text: institution header, applicant APP-2026-00006, name, GWA, course/strand, Verbal Reasoning 85.00 → Above Average.

## Open follow-ups (not in this cut)

- Reports/export reuse of the pipeline; admission-slip printing; result-sheet template editor beyond HTML/DOCX; global `release_mode` settings toggle (currently per-release mode gates printing).
