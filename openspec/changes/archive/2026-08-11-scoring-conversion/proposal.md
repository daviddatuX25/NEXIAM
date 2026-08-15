# Change Proposal: Scoring Conversion (Confidential Conversion Table)

**Change ID:** `scoring-conversion`
**Status:** ACTIVE
**Phase:** E2 connected admission-to-release slice (post-grading core)
**Depends on:** `grading-workflow` PASS (manual grading core + `GradingSession`/`ScoreRecord`), `grading-roster-eligibility` PASS (attendance/submission roster gate)
**Archived prior:** `openspec/changes/archive/2026-08-11-notifications-delivery-outbox/` (parked DEFERRED by founder priority — design preserved, zero code shipped)

## Intent

Adopt the **guidance-locked score conversion mechanism**: raw manual scores are converted through a **confidential conversion table** (range-based rows on each aptitude area, exactly like legacy `percentile_conversions`). This cut:

1. Replaces the placeholder raw-only scoring with a real conversion engine — `raw score → converted output` resolved at score-entry time and **frozen on the score record** (immutable snapshot).
2. **Drops the normalized-score / formula basis entirely** — founder consultation (2026-08-11): guidance only uses conversion tables; legacy `normalized_score` basis is **REJECTED**, not ported.
3. Delivers the confidential **conversion table editor** for Test Administrators (range rows + paste/copy, mirroring legacy `Admin/AptitudeAreas/Edit`).
4. Hardens grading data at rest: manual score + converted output stored via Laravel `encrypted` cast (HMAC/AES) — the most sensitive fields in the release path; OMR overlay/captured photo encryption is designed here and implemented with the OMR cut.

## Disposition from legacy (authority: `legacy_codebase/application/app/Services/ScoreInputService.php`, `app/Models/AptitudeArea.php`, `database/migrations/2026_05_19_223738_create_percentile_conversions_table.php`, `resources/js/Pages/Admin/AptitudeAreas/{Index,Create,Edit}.svelte`, `resources/js/Pages/Grading/ScoreInput.svelte`)

| Legacy | Disposition |
|---|---|
| `AptitudeArea.scoring_method` ∈ {`formula`, `conversion_table`} + `formula` string + `max_items` | **REWRITE (narrow)** — greenfield stores `scoring_method` (guidance-locked to `conversion_table`; formula/normalized REJECTED) + `max_items` (required for table coverage). |
| `percentile_conversions` table (`raw_score` → `percentile_output`, exact rows; UI flattens ranges to exact rows) | **REWRITE (ranges native)** — `score_conversion_rows` stores `min_score`/`max_score`/`output_value` ranges directly; contiguous non-overlapping coverage of `0..max_items` validated at save. Keeps the founder's "range value" model without legacy row bloat. |
| `ScoreInputService::saveScores` converts at save (`lookupPercentile`) | **REWRITE** — `RecordScores` Action converts via shared `ScoreConverter` and freezes `converted_score` on `score_records`; re-entering a score recomputes from the **current** table (snapshot semantics documented). |
| `Grading/ScoreInput.svelte` conversion preview + table rows | **PRESERVE shape** — greenfield `GradingScoreInput` shows raw input + live converted preview from the area's rows; read-only when finalized. |
| `Admin/AptitudeAreas/Edit.svelte` table editor (rows, paste `0-10 [tab] 85th`, copy, ranges) | **PRESERVE shape** — greenfield `Setup/AptitudeScoring` inner page (list → per-area scoring edit, legacy shape per §3.4). |
| `SettingsController` global `enable_normalized_scores` toggle | **REJECT** — global toggle removed; conversion is per-area and always on for active areas. |
| `FormulaEvaluator` / `computeNormalizedScore` | **REJECT** — not ported (founder: guidance uses conversion table only). |
| `AptitudeArea::resolveScore` (normalized OR percentile) | **REWRITE** — `ScoreConverter::convert` (table only). |

## Founder locks (apply)

1. **Confidentiality** — conversion tables are Test-Administrator-only (`GUIDANCE_ASSESSMENT_CATALOG` gate, same as area CRUD today). Program Head / applicant / proctor never see rows or outputs they are not entitled to (GF contracts CAP-036; no confidential conversion tables for Program Head). The scoring UI only shows the **converted output** to staff authorized for grading.
2. **Snapshot, not recompute** — `converted_score` is frozen on `score_records` at entry; editing the table later never mutates already-recorded grades (audit trails own the history).
3. **Encryption at rest** — `score_records.score` + `converted_score` use the `encrypted` cast (Laravel AES-256 via APP_KEY). Design covers OMR overlay/captured photo encryption (encrypted upload bundle + HMAC manifest) for the OMR cut.
4. **One conversion core** — manual entry, future OMR import, and Nexi grading all resolve through `ScoreConverter`; no second conversion path.
5. **Thin controllers + transactional actions** — `SaveAptitudeScoring` Action owns table replace + coverage validation inside one transaction; `StoreAptitudeScoringRequest` owns validation; controllers delegate (§3.8).
6. **Developer-ready demo** — seeded conversion tables for the seeded areas cover `0..100`; `HANDOFF` records demo logins + recreate steps (§3.7).

## Out of scope (explicit)

- **OMR scoring interface / captured-photo overlay** — next cut (this change reserves the `ScoreConverter` seam + encryption design).
- **Result-sheet printing / mark-printed** — separate pipeline between scoring and release/consultation; parked for the release/consultation discussion (founder 2026-08-11).
- **Nexi grading assistant** — deferred; only derived completion status exists today (no manual lock toggle).
- **Formula/normalized engine** — REJECTED by guidance; never built.
- **Staged score import (CSV/XLSX)** — REQ-VS-021 import part remains a separate cut.
- **Notifications outbox** — parked `notifications-delivery-outbox` remains DEFERRED; its design is untouched in `archive/`.
