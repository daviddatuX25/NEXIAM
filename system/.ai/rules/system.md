---
paths:
  - 'system/**'
---

# System

## Release and Consultation operating surface
Present Release and Consultation as one staff-facing operational surface, while retaining separate release and consultation milestones internally. Consultation scheduling mirrors ExamSession 1:1 and is available to applicants through their own schedule/reservation flow. Release occurs after consultation in both modes: F2F documents are prepared/printed before the meeting and handed over after consultation; online consultation is completed first, then a digitally signed result is delivered through the portal with notification/email. F2F remains the normal mode; online is the emergency/exception mode.

## Canonical applicant identifier vocabulary
Use “Application No.” as the institution-facing and applicant-facing label; do not introduce “reference code” or “reference number” in new UI, events, docs, or tests. The existing reference_code field/generator requires a separate terminology migration task; do not rename it opportunistically in unrelated changes. Keep any Application Key distinct until its exact verification role is confirmed.
