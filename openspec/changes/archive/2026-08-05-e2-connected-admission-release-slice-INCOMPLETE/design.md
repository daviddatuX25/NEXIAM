# E2 Design Notes

## Authority

Founder decisions → master reference → PRD/GF contracts → this OpenSpec → tests → legacy evidence.

## Modules (target)

Align with GF-17/GF-18 modular monolith boundaries:

- Admission (identity, attempt, access challenge)  
- Examination (session, assignment, proctor duty, attendance/submission, close)  
- Assessment/Grading (manual scores, finalize)  
- Consultation & Release (decision, document, release/unrelease, notify)  
- Shared: Authorization (GF-21), Audit, Notifications outbox, Reference data (E1)

## Authorization

- `CapabilityAuthorizer` decides allow/deny with office/program/own/assigned/contextual-duty scopes.  
- Proctor is assignment-scoped only.  
- Denied paths must not leak protected fields.

## Transactions and side effects

- Critical mutations commit invariant + state + mandatory audit atomically.  
- Notifications/document generation are after-commit; UI uses pending/accepted wording until evidence exists.  
- Idempotency keys on attendance, submission, notify, dangerous commands.

## UI

- Server is page truth; `page_state` vocabulary from UX catalog.  
- Reuse locked kit: PageHeaderBar, filters/bulk, ToastManager, shells, NexiAssistPanel (presentation only).  
- Risk confirmations via accessible Dialog — not `confirm()` alone.

## Explicit holds

| REQ-VS | Hold | E2 proof instead |
|---|---|---|
| 010 | OMR E3 | Manual scoring E2-S10 |
| 024 | RAG E3 | Nexi chrome only |
| 025/029 | Evidence hold / conditional | Deny unapproved individual data; optional minimal projection |
| 032 OMR portion | E3 | Document provenance for release artifacts |
