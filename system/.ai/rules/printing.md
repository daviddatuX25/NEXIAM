---
paths:
  - 'app/Printing/**,app/Jobs/Printing/**'
---

# Printing

## One async print pipeline for every artifact
All printable artifacts ride one pipeline: PrintJob row -> queued RenderPrintJob -> PrintRenderer interface (DomPDF now, Chromium later) -> private/sensitive disk -> status polling -> capability-reauthorized download. NEVER render synchronously in a request. Result sheets (release feature) plug into this foundation later; blank answer sheets ride it today. Every create/transition/download is audited; duplicate requests return the existing job; bulk jobs carry a per-item manifest + partial states.
