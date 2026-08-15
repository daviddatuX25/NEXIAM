# Design: Exam Proctor Duty Assign

## Vocabulary

| Term | Meaning |
|---|---|
| Test Administrator | D-016 capability **set** — not a role. Inherent on `guidance_counselor` seed via `testAdministratorCapabilities()`. |
| Proctor | D-015 **session duty** on `proctor_duties` — never permanent Spatie role. |
| Assign authority | `guidance.exam.proctor.assign` only (D-022). |
| Eligible assignee | Active Guidance office staff (clerk/support helpers) who are not already active on this session. |

## Page contract (legacy feel)

- Stay on **Examination/Show** (list → show pattern).  
- Section **Proctors**: list active duties + assign form (select eligible user) + revoke per row.  
- `/proctor` dashboard remains “my duties” for assignees — not the assign management page.

## Authz path

```text
Request → CapabilityAuthorizer(guidance.exam.proctor.assign)
  → AssignProctorDuty / RevokeProctorDuty
  → audit exam.proctor_duty_*
```

No role middleware. No AI path in this cut.

## Eligibility (server)

Eligible if all hold:

1. Active `StaffProfile`  
2. Office = `guidance`  
3. Role helper in `{guidance_clerk, guidance_support_staff}` (Proctor-eligible per D-015; counselor already TA — do not assign self as proctor by default)  
4. No other active duty on this session for that user  

Reject registrar/HR/program_head users even if somehow posted.

## Files

```text
ExamSessionController::show          + eligible_proctors prop
Examination/Show.svelte              assign/revoke UI
AssignProctorDuty                    eligibility preflight
StaffIdentitySeeder                  guidance.support seed
```
