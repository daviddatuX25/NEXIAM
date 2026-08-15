---
paths:
  - 'app/Assessment/Actions/Omr/**'
---

# Omr

## Builder-saved bundles must clean up the canonical store on validation failure
SaveAuthoredTemplateDraft writes native bundle files into app/Assessment/Omr/NativeBundles/<code>/v<N> BEFORE NativeBundle::load/derive validation. Any throwable after the mkdir must rm -rf the directory (exec('rm -rf '.escapeshellarg($dir))), otherwise the half-written vN blocks every future save of that code with 'Version vN already exists'. Manifest 'files' key order must be template.json, config.json, reference-page-*.svg (validator compares array_keys exactly — ksort breaks it). bubbleDimensions canonical = [56,56] (~6mm at 242dpi); [120,120] triggers overlap errors.
