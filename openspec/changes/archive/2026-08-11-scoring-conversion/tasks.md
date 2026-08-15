# Tasks: Scoring Conversion (Confidential Conversion Table)

## A — Open

- [x] A1 Park `notifications-delivery-outbox` in archive (DEFERRED) and open `scoring-conversion` as the single active change

## B — Schema

- [x] B1 Migration: create `score_conversion_rows` (`aptitude_area_id` FK cascade, `min_score`, `max_score`, `output_value`, unique `(aptitude_area_id, min_score)`)
- [x] B2 Migration: alter `aptitude_areas` add `scoring_method` (default `conversion_table`) + `max_items` nullable
- [x] B3 Migration: alter `score_records` add `converted_score` nullable string; cast `score` + `converted_score` to `encrypted` on the model

## C — Domain

- [x] C1 `ScoreConversionRow` model (+ `belongsTo AptitudeArea`)
- [x] C2 Extend `AptitudeArea`: `scoring_method`/`max_items` fillable+casts, `conversionRows()`, `convertScore()`
- [x] C3 `ScoreConverter` support (single conversion core, eager-loaded rows)
- [x] C4 `ConversionTableValidator` (empty/order/overlap/gap/coverage rules)

## D — Actions

- [x] D1 `SaveAptitudeScoring` (transactional replace + validator + audit)
- [x] D2 Extend `RecordScores` to convert + freeze `converted_score`; reject out-of-coverage scores

## E — HTTP + UI

- [x] E1 Routes `setup.aptitude.scoring` GET/PUT under auth group
- [x] E2 `SetupController::scoring`/`storeScoring` + `StoreAptitudeScoringRequest`
- [x] E3 `Setup/Aptitude.svelte`: scoring badges + per-row Scoring link
- [x] E4 `Setup/AptitudeScoring.svelte`: rows editor + paste/copy + client validation + confidentiality banner
- [x] E5 `GradingScoreController::show` conversion projection (rows + frozen converted_score)
- [x] E6 `GradingScoreInput.svelte`: live conversion preview + max_items label + read-only frozen output

## F — Seeds

- [x] F1 `ReferenceCatalogSeeder`: conversion tables covering 0..100 for VERBAL/QUANT (+ `GradingDemoSeeder` compatibility)

## G — Proof

- [x] G1 Pest `ScoringConversionTest`: range/exact lookup; freeze on record; out-of-coverage rejection; encrypted at rest; projection snapshot
- [x] G2 Pest `AptitudeScoringTest`: save; gap/overlap/coverage/out-of-max rejected; 403; replace idempotency
- [x] G3 Run `Assessment` + `Setup` + `Release` suites via Compose; record results in HANDOFF

## Recorded follow-ons (NOT this cut)

- OMR scoring interface + captured/overlayed-photo encryption (uses `ScoreConverter` seam; HMAC asset manifest design in `design.md` §6).
- Result-sheet printing pipeline (separate from release/consultation; parked for release-mode discussion).
- Global "auto-compute normalized" toggle was REJECTED per founder guidance — conversion tables only.
