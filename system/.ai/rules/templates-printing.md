---
paths:
  - 'app/Assessment/**; resources/js/Pages/Setup/OmrConfiguration/Templates/**; app/Printing/**'
---

# Templates Printing

## Compact profile birthdate is render-only
The compact six-column profile uses native applicant-number bubbles only. Birthdate and name are manual print-overlay fields in the EDQ/header zone; they never enter native OMR geometry, derived identity regions, provider detection, or OCR. Existing explicitly authored legacy templates may retain native BIRTHDATE_1..6 compatibility.
