# Spec: nexi-applicant-inquiry

## Requirements

### REQ-AI-APP-01 — Applicant turns
Applicant-authenticated users on applicant surface may submit Inquiry turns; conversations store `actor_type=applicant`.

### REQ-AI-APP-02 — Policy audience
Applicant retrieval includes only `staff_and_applicant_inquiry` and `applicant_inquiry` sources. `staff_only` never returns.

### REQ-AI-APP-03 — Own-data
Answers about application/exam status use the same ownership scope as the applicant portal. Result/score fields appear only when a current `ResultRelease` exists.

### REQ-AI-APP-04 — Isolation
Snapshot/stream/cancel require matching `actor_id`. Cross-applicant access is denied.

### REQ-AI-APP-05 — Clear
Applicant may clear/segment their open conversation and start a fresh epoch.

### REQ-AI-APP-06 — No mutation
Applicant surface remains Inquiry-only — no Apply tools or staff packs.
