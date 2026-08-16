# OMR profile test-readiness handoff

## Seed and reset

Run from the repository root:

```bash
docker compose -f system/compose.yaml exec -T app php artisan foundation:reset --force
docker compose -f system/compose.yaml exec -T app php artisan db:seed --class="Database\\Seeders\\GradingDemoSeeder" --force
```

`foundation:reset` runs the foundation seed chain, including the compact profile template/key pair. `GradingDemoSeeder` creates the profile capture batch and self-heals an interrupted prior batch.

## Staff login

- Email: `guidance.counselor@nexiam.test`
- Password: `password`
- Capability: `guidance.scoring.omr`

## Expected seeded state

- Template code: `SYN-OMR-PROFILE-150`, one active long-bond page, six 25-question blocks, 150 questions, compact 40×40 bubbles.
- Native identity: applicant number only, compact grid origin `[200,390]`; native birthdate is disabled. The six answer columns start at y=`940`, leaving the EDQ header and identity labels clear while preserving the 80px page margin.
- Booklet code: `SYN-OMR-PROFILE-KEY`, one active matching authored key with deterministic A/B/C/D answers.
- Render-only overlay: `EDQ / HEADER ZONE`, compact exam/batch line, one-line instruction, manual `BIRTHDATE:`/`MMDDYYYY` box, manual `NAME (PRINT):` box, and no footer/calibration copy.
- Capture batch: one open profile batch with three sheets / three pages:
  - `needs_review`: one-page profile sheet bound to applicant number `1234567890`; proposal/provider confidence values remain null until qualification.
  - `pending_pages`: one queued page for the one-page profile sheet.
  - `failed_terminal`: one retryable failed page with a private asset mirror.

## URLs

- OMR hub: `http://127.0.0.1:18000/setup/omr`
- Template list: `http://127.0.0.1:18000/setup/omr/templates`
- Grading OMR capture: `http://127.0.0.1:18000/grading/sessions/1/omr`
- Profile preview: open the active `SYN-OMR-PROFILE-150` version from the template list; after a clean reset its version id is currently `6`, so the direct route is `/setup/omr/templates/6/preview`.

## Verification evidence

- Clean `app`/`queue` image rebuild completed before the final run. `OmrTemplateLayoutSolverTest`, `OmrProfileReadinessTest`, and `PrintPipelineTest`: **28 passed, 257 assertions**.
- Fresh profile preview: `200 application/pdf`, 648,148 bytes; rasterized at 96 DPI and visually checked for a clear EDQ header, identity labels/bubbles, six answer columns, 150 rows, manual name/birthdate boxes, bottom margin, and no footer/calibration copy.
- Fresh v4/v5 previews: `200 application/pdf`; all four pages remained raster/vision-checked for page margins, label/bubble separation, footer placement, and calibration separation.
- Manual name fallback: `BundleRenderer` keeps `NAME (PRINT):` on every page, preferring the upper-left gap and using a 50mm upper-right box for compact identity bands or full headers; the active blueprint builder preview shows the same upper-right box inside the dashed printable margin.
- Seed verification: profile pair active once; capture batch has `needs_review` 1, `pending_pages` 1, `failed_terminal` 1, with 3 sheets / 3 pages.

## OpenSpec checks

```bash
openspec validate omr-profile-test-readiness --strict
```

Result: `Change 'omr-profile-test-readiness' is valid`.
