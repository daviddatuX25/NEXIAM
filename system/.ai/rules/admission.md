---
paths:
  - 'system/app/Admission/**'
---

# Admission

## Admission reconsideration and enrollment reversal semantics
ReconsiderAdmissionAttempt is a Registrar authority action for an accepted-attempt decision mistake: accepted -> pending, reason required, version/audit/event recorded, assignments/history preserved for scheduling reconciliation; it is not an applicant action and is distinct from accepted-data amendment, which stays accepted. The current action rejects dismissed/archived attempts despite the broader workflow wording. ReverseEnrollmentStamp is a Registrar correction: append-only reverse-enrolling or reverse-enrolled stamp, reason/idempotency/audit required; reversing enrolled can restore a paper-finalized program selection to active. Neither reversal deletes history.
