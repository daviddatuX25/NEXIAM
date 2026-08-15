---
paths:
  - app/Printing/BundleRenderer.php
---

# App Printing

## BundleRenderer is the single visual source
BundleRenderer is the ONLY visual source for sheets: it draws bubbles/labels/headers from template.json at render time (letters centred on bubble centres by construction). Hand-authored reference SVGs are stopgaps, never the render path. It accepts arbitrary draft JSON for builder previews; only finished artifacts flow through print_jobs.
