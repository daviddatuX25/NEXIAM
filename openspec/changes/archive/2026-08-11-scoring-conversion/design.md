# Design: Scoring Conversion (Confidential Conversion Table)

**Change ID:** `scoring-conversion`

## 1. Domain model

### 1.1 `aptitude_areas` (alter)

| Column | Type | Notes |
|---|---|---|
| `scoring_method` | string(32), default `conversion_table` | Guidance-locked. Validated `in:conversion_table` this cut. |
| `max_items` | unsignedSmallInteger nullable | Highest raw score that must be covered by the table (e.g. 100). |

Existing rows get `scoring_method=conversion_table`, `max_items=null` (nullable until a table is saved).

### 1.2 `score_conversion_rows` (new)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `aptitude_area_id` | FK `aptitude_areas` cascadeOnDelete | |
| `min_score` | unsignedSmallInteger | |
| `max_score` | unsignedSmallInteger | `>= min_score` |
| `output_value` | string(50) | The converted label/value (e.g. `85th`, `High`). Opaque string — institution-configured. |
| timestamps | | |
| unique | `(aptitude_area_id, min_score)` | No duplicate lower bounds. |

### 1.3 `score_records` (alter)

| Column | Type | Notes |
|---|---|---|
| `converted_score` | string(50) nullable | Frozen snapshot of the output at entry time. |

Casts: `score` + `converted_score` → `encrypted` (AES-256 at rest). Presence-only queries (finalize) are unaffected.

## 2. Domain code

### 2.1 `App\Models\Foundation\ScoreConversionRow`

Fillable `[aptitude_area_id, min_score, max_score, output_value]`; int casts; `belongsTo AptitudeArea`.

### 2.2 `App\Models\Foundation\AptitudeArea` (extend)

- const `SCORING_METHOD_CONVERSION_TABLE = 'conversion_table'`
- fillable adds `scoring_method`, `max_items`; casts add `max_items => integer`
- `conversionRows(): HasMany` (ordered by `min_score` when loaded)
- `convertScore(int $raw): ?string` — row lookup `min <= raw <= max` → `output_value`

### 2.3 `App\Assessment\Support\ScoreConverter` (shared, one core)

```php
public function convert(AptitudeArea $area, int $rawScore): ?string
```

- table-only; returns `null` when no row covers the raw score (caller rejects).
- Eager-loads rows on the area to avoid N+1 (`conversionRows`).

### 2.4 `App\Assessment\Support\ConversionTableValidator`

`validate(array $rows, int $maxItems): void` — throws `RuntimeException` when:

- table is empty;
- `min > max`, or `min < 0`, or `max > maxItems`;
- ranges overlap (`next.min <= prev.max`);
- a gap exists (`next.min !== prev.max + 1`);
- coverage incomplete — first `min` must be `0` and last `max` must be `>= maxItems`.

## 3. Actions

### 3.1 `App\Setup\Actions\SaveAptitudeScoring`

`handle(AptitudeArea $area, User $actor, string $scoringMethod, ?int $maxItems, array $rows): AptitudeArea`

One DB transaction:

1. `lockForUpdate` the area.
2. Validate: table required when `conversion_table` (with `max_items`), rows validated by `ConversionTableValidator`.
3. `update(['scoring_method', 'max_items'])`.
4. `conversionRows()->delete()` then insert rows (replace semantics; versioned area retains history only via audit + code/version).
5. `RecordAuditEvent('setup.aptitude_scoring_saved', ['area_id','code','max_items','row_count'])`.

Snapshot note: existing `score_records.converted_score` are **not** touched — already-recorded grades keep the table that was live at entry.

### 3.2 `RecordScores` (extend)

Per entry, when the area is `conversion_table`:

- `ScoreConverter::convert($area, (int) score)`; if `null` → `RuntimeException("Score {n} for {code} is outside its conversion table.")` (validation flash surfaces it).
- Store `converted_score` on the record. Raw `score` remains the entry value (0–100 per `RecordScoresRequest`).

## 4. HTTP + UI

### 4.1 Routes (inside existing auth group)

```php
Route::get('/setup/aptitude/{area}/scoring', [SetupController::class, 'scoring'])->name('setup.aptitude.scoring');
Route::put('/setup/aptitude/{area}/scoring', [SetupController::class, 'storeScoring'])->name('setup.aptitude.scoring.update');
```

Both delegate authz to `requireAssessment` (`GUIDANCE_ASSESSMENT_CATALOG`).

### 4.2 `SetupController`

- `aptitude()` — list projection adds `scoring_method`, `max_items`, `conversion_rows_count`.
- `scoring(AptitudeArea $area)` — renders `Setup/AptitudeScoring` with `area` (code/name/version/max_items/scoring_method) + `conversion_rows` (ordered).
- `storeScoring(StoreAptitudeScoringRequest, AptitudeArea, SaveAptitudeScoring)` — action call; `RuntimeException` → `errors.scoring`; success flash `Scoring configuration saved.`.

### 4.3 `StoreAptitudeScoringRequest`

- `scoring_method` required `in:conversion_table`
- `max_items` required integer `1..1000`
- `conversion_rows` required array
- `conversion_rows.*.min_score` required integer `0..1000`
- `conversion_rows.*.max_score` required integer `>= *.min_score` `<=1000`
- `conversion_rows.*.output_value` required string `max:50`
- `conversion_rows.*.min_score` distinct (server validator re-checks semantics)

### 4.4 `Setup/Aptitude.svelte` (list)

Per-row action link **Scoring** → `/setup/aptitude/{id}/scoring`; show `scoring_method`/`max_items` badges. Legacy shape: list → inner edit page (§3.4).

### 4.5 `Setup/AptitudeScoring.svelte` (new)

- Header: area code/name/version; confidentiality banner ("Test Administrator only — confidential conversion table").
- `max_items` number input.
- Rows editor: `{min_score, max_score, output_value}`; add/remove; paste box (`0-10 [tab] 85th`, `15: 90th`, ranges collapsed/expanded client-side) + copy button (legacy behavior).
- Save via `router.put`. Client pre-validation mirrors the server validator (gaps/overlaps/coverage) so errors surface before submit.

### 4.6 `GradingScoreController::show`

Per-domain projection adds `scoring_method`, `max_items`, `conversion_rows` (min/max/output), and `converted_score` from the existing record.

### 4.7 `GradingScoreInput.svelte`

- Conversion-table domains: raw score input (0–max_items) + **live converted preview** from rows; when finalized, show frozen `converted_score` read-only.
- Label reflects max_items (not the old `/100 points` hard-code).

## 5. Seeds (developer-ready)

`ReferenceCatalogSeeder`: VERBAL/QUANT areas get `scoring_method=conversion_table`, `max_items=100`, and rows covering `0..100` (e.g. `0–49 Below Average`, `50–74 Average`, `75–89 Above Average`, `90–100 Excellent`). `GradingDemoSeeder` (score 85) stays in-coverage. A `SaveAptitudeScoring`-shaped seeder helper keeps the demo reproducible.

## 6. Security (sensitive-data plan)

- **At rest:** `score_records.score` + `converted_score` encrypted (Laravel `encrypted` cast). Conversion tables are plaintext-in-DB but capability-gated at every read surface (list/scoring/grading projection); no applicant/program-head surface.
- **In motion:** Inertia projections only expose rows/outputs to authorized staff; staff capability `GUIDANCE_SCORING_MANUAL` gates grading surfaces, `GUIDANCE_ASSESSMENT_CATALOG` gates table management.
- **OMR (designed now, built next):** captured/overlayed answer-sheet images must be stored encrypted (storage driver with `encrypted` at-rest driver or per-object envelope key) + HMAC manifest (SHA-256 of image bytes + record id) so tamper/truncation is detectable. Manual scores + converted outputs share the `encrypted` cast. Key rotation stays Laravel-native (`APP_KEY`); a `sensitive_assets` table design is deferred to the OMR change.

## 7. Proof

- Pest `ScoringConversionTest`: converter range/exact lookup; RecordScores freezes `converted_score`; out-of-coverage rejected; encrypted-at-rest (raw SQL shows ciphertext); score input projection carries rows + snapshot; table edit keeps prior records' snapshots.
- Pest `AptitudeScoringTest`: save happy path; gap/overlap/coverage/out-of-max rejected; unauthorized 403; idempotent replace.
- Full affected suite: `Assessment`, `Setup`, `Release` via Compose.
