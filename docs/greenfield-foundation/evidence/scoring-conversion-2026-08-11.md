# E2 Scoring Conversion (Confidential Conversion Table) — Implementation Evidence

**Date:** 2026-08-11
**OpenSpec:** `openspec/changes/2026-08-11-scoring-conversion/`
**Runtime:** `system/` (Laravel 13 / Inertia 3 / Svelte 5)

## Status

**CUT SHIPPED.** The guidance-locked score conversion mechanism: raw manual scores resolve through a **confidential conversion table** (range rows per aptitude area) and the output is frozen on each `score_records` row at entry. The legacy `normalized_score`/formula basis is **REJECTED** (founder consultation 2026-08-11) and was not ported. `notifications-delivery-outbox` was parked DEFERRED in `openspec/changes/archive/` by founder priority (design preserved, zero code shipped).

## What was built

| Feature | Entry | Notes |
|---|---|---|
| Conversion table schema | `score_conversion_rows` (min/max/output, unique lower bound, FK cascade) | `aptitude_areas` gains `scoring_method` (guidance-locked `conversion_table`) + `max_items` |
| Confidential editor | `GET/PUT /setup/aptitude/{area}/scoring` | `SaveAptitudeScoring` transactional replace + `ConversionTableValidator` (start at 0, no gaps/overlaps, covers max) + audit; `Setup/AptitudeScoring.svelte` rows editor with paste (`0-10 [tab] 85th`) / copy / client validation |
| Scoring conversion | `ScoreConverter` (single core) | `RecordScores` converts at entry and freezes `converted_score`; out-of-coverage scores rejected with a staff-facing message |
| Snapshot semantics | frozen `converted_score` | Editing the table never rewrites recorded grades; new entries use the live table |
| Encryption at rest | `score_records.score` + `converted_score` `encrypted` cast | AES-256 via APP_KEY; presence-only finalize queries unaffected; score column moved decimal → string for ciphertext |
| Readiness | `ComputeSetupReadinessQuery` | New "Every active area has a complete conversion table" check (important) |
| Seeds | `ReferenceCatalogSeeder` | VERBAL/QUANT get `max_items=100` + 4 rows covering `0..100` so any demo score converts |

## Capability gates

`GUIDANCE_ASSESSMENT_CATALOG` gates table management and the editor page; `GUIDANCE_SCORING_MANUAL` gates grading surfaces; scoring UI shows only converted outputs (no table rows to graders). Program Head / applicant surfaces never receive rows or outputs (GF contracts).

## Pest evidence (2026-08-11)

```text
tests/Feature/Assessment/ScoringConversionTest.php → 7 passed (converter range lookup, freeze on record,
  out-of-coverage rejection, encrypted at rest, snapshot on table edit, score input projection)
tests/Feature/Setup/AptitudeScoringTest.php → 8 passed (save/replace, gap/overlap/coverage/start
  rejection, 403, request validation, failed-save keeps prior table)
```

Run:

```bash
cd system
docker compose exec app php artisan test --compact \
  tests/Feature/Assessment/ScoringConversionTest.php tests/Feature/Setup/AptitudeScoringTest.php
```

Regression (2026-08-11, clean single-process run): `Assessment + Setup + Release + Examination + Admission + Authorization` → **118 passed (1297 assertions)**. `ReferenceCatalog + Capabilities + FoundationHealth + FoundationSeams` → **27 passed (192 assertions)**. Frontend `npm run build` clean (`Aptitude`, `AptitudeScoring`, `GradingScoreInput` emit).

Note: two transient failures during the first parallel regression run were traced to a stale background test process running concurrently on the same test DB; the isolated re-runs and final clean regression pass.

## REQ-VS / guidance map

| Requirement | Evidence |
|---|---|
| Guidance conversion-table-only (range values; normalized basis rejected) | `ScoreConverter` + `ConversionTableValidator`; `StoreAptitudeScoringRequest` restricts `scoring_method` to `conversion_table`; no formula/normalized code |
| REQ-VS-021 transactional multi-area writes + replay | Existing `RecordScores` idempotency preserved; conversion added inside the same transaction |
| REQ-VS-009 finalize completeness | Unchanged — presence-based; encrypted cast does not affect presence queries |
| Confidentiality (CAP-036; Program Head excludes conversion tables) | Table rows only on `GUIDANCE_ASSESSMENT_CATALOG` surfaces; grading projection carries rows only to the scoring page |

## Gated non-goals (follow-on cuts, do not fold in)

- **OMR scoring interface + captured/overlayed-photo encryption** — next founder-priority cut; `ScoreConverter` is the shared seam; HMAC asset manifest + encrypted-upload design recorded in `design.md` §6.
- **Result-sheet printing / mark-printed** — distinct pipeline between scoring and release/consultation; parked for the release-mode discussion (online vs offline).
- **Notifications outbox** — parked DEFERRED; revisit after scoring + OMR.
- **Nexi grading assistant** — deferred; derived completion status only (no manual lock toggle).
