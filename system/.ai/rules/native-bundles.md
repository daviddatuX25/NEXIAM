---
paths:
  - 'app/Printing/**,app/Assessment/Omr/NativeBundles/**'
---

# Native Bundles

## Paper is long bond 8.5x13in; geometry is never scaled
OMR paper = long bond 216x330mm (pixel space ~2060x3144 @242dpi); US Legal is the alternative. Bundles are authored in the matching paper space and NEVER scaled at render — a mismatch refuses with typed error 'paper_mismatch'. v4 bundle = standard pair; v2/v3 are interim A4 synthetics that must never be pinned by fixture packs or qualifications.
