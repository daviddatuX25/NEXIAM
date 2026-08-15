# HANDOFF — Scoring Conversion (Confidential Conversion Table)

**Change ID:** `scoring-conversion` · **Status:** SHIPPED 2026-08-11 (evidence in `docs/greenfield-foundation/evidence/scoring-conversion-2026-08-11.md`)

## Demo logins

| Who | Email | Notes |
|---|---|---|
| Test Administrator / Guidance Counselor | `guidance.counselor@nexiam.test` | Sets conversion tables (`GUIDANCE_ASSESSMENT_CATALOG`) and grades (`GUIDANCE_SCORING_MANUAL`). |
| Registrar clerk | `registrar.clerk@nexiam.test` | Seeds exams/sessions used by the demo. |

(Password per `foundation:reset` seed output.)

## Seed / reset

```bash
cd system
docker compose build app && docker compose up -d app
docker compose exec app php artisan foundation:reset --force
```

`ReferenceCatalogSeeder` seeds VERBAL + QUANT with `scoring_method=conversion_table`, `max_items=100`, and 4 range rows covering `0..100` (`Below Average / Average / Above Average / Excellent`).

## Expected starting state

- `/setup/aptitude` lists VERBAL/QUANT with **100 · 4 rows** badges and a **Scoring** link.
- `/setup/aptitude/{id}/scoring` shows 4 seeded range rows; editing + saving replaces them (client + server validation: start at 0, no gaps/overlaps, covers max).
- A grading session converts any `0..100` raw score to its row output, frozen at entry; finalized sessions show frozen outputs.
- Readiness hub shows a new "Every active area has a complete conversion table" check (important) under Aptitude areas.

## Recreate (one-shot from clean DB)

1. `docker compose exec app php artisan foundation:reset --force`
2. Log in as `guidance.counselor@nexiam.test`.
3. `GET /setup/aptitude` → open **Scoring** for VERBAL → confirm rows → edit `max_items`/rows → Save → `GET /setup` shows the new readiness check green.
4. `GET /grading` → open a grading session → score input → enter raw score (e.g. 85) → live "Converted: Above Average" preview → Save → session roster shows scored; finalize works.
5. To see encryption: enter a score, then `docker compose exec app php artisan tinker --execute 'dump(\Illuminate\Support\Facades\DB::table("score_records")->value("score"));'` → ciphertext (not `85.00`).

## Feature URLs

| Page | URL |
|---|---|
| Aptitude list | `GET /setup/aptitude` |
| Conversion table editor | `GET /setup/aptitude/{area}/scoring` |
| Save table | `PUT /setup/aptitude/{area}/scoring` |
| Grading dashboard | `GET /grading` |
| Score input | `GET /grading/sessions/{gs}/attempts/{attempt}` |

## Evidence

- Pest `ScoringConversionTest` (7) + `AptitudeScoringTest` (8) → 15 passed (114 assertions).
- Affected regression: `Assessment + Setup + Release + Examination + Admission + Authorization` → **118 passed (1297 assertions)**.
- Remaining seed/capability features (`ReferenceCatalog`, `Capabilities`, `FoundationHealth`, `FoundationSeams`) → 27 passed (192 assertions).
- Frontend `npm run build` clean (`AptitudeScoring`, `GradingScoreInput`, `Aptitude` emit).

