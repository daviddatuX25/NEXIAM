# Spec: Nexi Scoring Conversion

## Requirements

| ID | Requirement |
|---|---|
| SCV-1 | Every aptitude area carries a `scoring_method`; the guidance-locked method is `conversion_table` (formula/normalized basis rejected). |
| SCV-2 | A conversion table is a set of non-overlapping, contiguous, fully-covering range rows (`min_score`–`max_score` → `output_value`) over `0..max_items`. |
| SCV-3 | Raw scores convert through the area's table; the output is frozen on `score_records.converted_score` at entry and never recomputed from later table edits. |
| SCV-4 | A raw score with no covering row is rejected with an explicit validation message. |
| SCV-5 | `score_records.score` and `converted_score` are encrypted at rest. |
| SCV-6 | Conversion tables are Test-Administrator-only (`GUIDANCE_ASSESSMENT_CATALOG`); grading surfaces gate on `GUIDANCE_SCORING_MANUAL`. No applicant/Program Head surface exposes rows or outputs. |
| SCV-7 | Table edits replace rows transactionally with audit; prior grade snapshots are untouched. |
| SCV-8 | Score entry and future OMR/import flows share the single `ScoreConverter`. |

## Acceptance

- Staff with `GUIDANCE_ASSESSMENT_CATALOG` can open `/setup/aptitude/{area}/scoring`, configure `max_items` + range rows (paste/copy supported), and save.
- Gap, overlap, coverage, and out-of-range tables are rejected with staff-facing messages; the area keeps its previous table.
- In a grading session, entering a raw score shows the converted output live; saving freezes it; the finalized page shows the frozen output read-only.
- Scores outside the table are rejected with a clear message.
- Raw DB reads of `score_records.score`/`converted_score` return ciphertext.
- Seeded demo areas convert any `0..100` raw score (demo logins in HANDOFF).
