---
paths:
  - 'system/app/Applicant/**'
---

# Applicant

## Applicant stateless key authentication
Applicant access is keyed by the public Application No. plus a separate randomized Private Key; date of birth is not a password or authentication credential. Applicant verification remains stateless and must include brute-force controls, auditability, and explicit browser-persistence consent. Staff authentication is unchanged. Current ApplicantAccessKey is hashed and 8-character display-formatted, so any move to a 6-character key or recovery behavior requires a dedicated security-reviewed migration; resend/reissue is safer than allowing staff to read plaintext keys.
