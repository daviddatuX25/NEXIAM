---
paths:
  - 'app/Assessment/**'
---

# Assessment

## Identity lock extended: applicant number + optional bubbled birthdate
Founder decision 2026-08-14 extends the identity lock: identity = bubbled applicant-number digits + an OPTIONAL bubbled birthdate grid (BIRTHDATE_1..6, engine-native QTYPE_INT, MMDDYYYY). Free-form OCR stays out. The derivier requires exactly 6 birthdate positions with symbols 0-9; activation projects both identity regions page-keyed into identity_regions_json; merge reconciles per-page birthdates and flags invalid dates as birthdate_invalid issues. Print overlays (print_overlay_json) are render-only and NEVER sent to the engine.

## Scorable-uniqueness checks must exclude BIRTHDATE_ too
Every 'scorable labels unique across pages' check over derived OMR fields (NativeManifestDeriver::assertUniqueFields, OmrPinnedSnapshot::manifestExpectations, bundle shape validator) must exclude BIRTHDATE_ in addition to APPLICANT_NUMBER_ — identity grids repeat per page. OmrPinnedSnapshot was missing the BIRTHDATE_ exclusion until 2026-08-15; without it, two-page templates with birthdate grids throw 'duplicate field bubbles' at snapshot build. Also: detected_birthdate must stay in OmrCaptureSheet::$fillable (silent data loss otherwise); fake detector per-page values use omr.fake.birthdate_by_page (config() sets are unreliable due to afterCommit job timing); docker compose run containers use image-baked code — rebuild after PHP changes.
