access-control-inventory.md 342L
# NEXIAM — Proposed Role Architecture & Screen-Use Case Matrix
## Working Proposal | 2026-07-29
> **Decision status:** D-014 naming direction and the current role/capability corrections are approved with conditions in the greenfield decision register. The grouped authority catalog below is the human-readable review source; the wide screen matrix is a legacy/build-status reconciliation appendix. No role migration should start until the target catalog, capability matrix, temporary-duty model, and legacy-role mapping are fully promoted.
---
## 1. ROLE ARCHITECTURE — PROPOSED TARGET MODEL
### Permanent Roles (created/managed by Staffing/HR authority, with office support-staff delegation)
| # | Role | Inherent Permissions |
... [lean-ctx: omitted 3 lines]
| 3 | `registrar_clerk` | Application intake, applicant import, Registrar-owned application/status work, and approved Registrar setup-page maintenance; no admission-slip requirement for answer-sheet identity |
| 4 | `guidance_counselor` | Inherent Test Administrator capability for sensitive exam/test operations, grading, OMR, score conversion, result preparation/printing, approved release coordination, scheduling, create `guidance_support_staff`, grant/revoke Test Administrator capability, and assign/revoke Proctor duty |
... [lean-ctx: omitted 1 lines]
| 6 | `program_head` | Program-scoped pipeline/pre-enrolment views + **quota write** (D-087); authorized released result evidence; no admission/score/release writes |
### Break-glass oversight
| Account | Boundary |
... [lean-ctx: omitted 1 lines]
| `super_admin` | Optional non-evaluated break-glass support for exceptional technical/configuration/recovery scenarios plus the master full-audit user view; time-bound, reasoned, audited, and never routine office business operations |
### Temporary/Seasonal Roles (created by HR or the owning office head within role ceilings)
| # | Role | Created By | Inherent Permissions |
... [lean-ctx: omitted 1 lines]
| 8 | `registrar_support_staff` | `hr_staff_admin` or `registrar_admin` | Explicit Registrar operational subset: delegated intake/correction, accept/dismiss, and application-status visibility; not equivalent to `registrar_clerk`, with no institutional/reference/setup-page authority and no cross-office authority |
| 9 | `guidance_support_staff` | `hr_staff_admin` or `guidance_counselor` | Same as `guidance_clerk` by default (scoped); receives Test Administrator capability or Proctor duty only when explicitly granted/assigned |
### Test Administrator — Permission Set (NOT a role)
The `guidance_counselor` is inherently a Test Administrator. The capability is an approved Guidance-scoped set for sensitive exam/test operations; it is not a permanent role. The Counselor may grant/revoke applicable capabilities to Guidance Clerk or Guidance Support Staff with scope, dates, and audit evidence. “Guidance” is not itself an operational role; every sensitive command resolves to the holder’s concrete office role plus this capability:
... [lean-ctx: omitted 3 lines]
| `manage-exam-schedule` | Open/close applicant slot reservation, finalize reservation outcomes, manually assign accepted applicants, publish/unpublish schedules, and use the approved late/direct-assessment paths |
| `process-omr` | Receive, identify by Applicant No/Reference, scan/import, review uncertainty, and reconcile answer sheets |
... [lean-ctx: omitted 2 lines]
| `collect-answer-sheets` | Collect and maintain physical/digital answer-sheet custody for assigned sessions |
... [lean-ctx: omitted 2 lines]
| `assign-proctor` | Assign/revoke the separate session-scoped Proctor duty for eligible Guidance staff |
... [lean-ctx: omitted 1 lines]
### Proctor — Contextual duty overlay (assigned by Test Administrator)
Proctor is not a permanent base role. An authorized Test Administrator assigns or revokes Proctor duty for eligible Guidance staff (clerk or support_staff). The `exam_session_user` pivot scopes WHICH exam sessions a proctor oversees — authorization at the Policy level, not middleware.
---
## 2. GROUPED ROLE AND USE-CASE AUTHORITY CATALOG
This section is the primary human-readable authority for role review. Each group states the role’s responsibilities, limits, delegation boundary, and audit obligations. The wide matrix below is retained as a screen/build-status cross-check and must be regenerated/reconciled before implementation promotion.
### 2.1 HR/Staffing Administrator
Allowed:
- Create, activate, deactivate, and maintain approved permanent personnel accounts.
- Create and maintain approved Registrar Support Staff and Guidance Support Staff accounts.
- Record employment status, office assignment, effective dates, and lifecycle changes.
- Revoke access when personnel transfer, separate, or lose authorization.
... [lean-ctx: omitted 2 lines]
- No applicant, application, answer-sheet, OMR, score, result, consultation, program-projection, or operational exam data.
... [lean-ctx: omitted 6 lines]
- Every personnel creation, activation, deactivation, transfer, role assignment, and support-staff lifecycle change is attributable to HR or the office authority that performed it.
### 2.2 Registrar Administrator
Allowed:
- Manage Registrar-owned academic reference data, application windows, intake records, imports, and Registrar reports.
... [lean-ctx: omitted 2 lines]
- View approved shared workflow fields, schedules, and application/status information.
... [lean-ctx: omitted 1 lines]
- No converted result/remark sheet, converted per-domain score, raw answer sheet, bubble scan, or confidential score-conversion table.
... [lean-ctx: omitted 3 lines]
- Acceptance/dismissal, correction, import, support-staff creation, and protected cross-office views are audited.
### 2.3 Registrar Clerk and Registrar Support Staff
Default boundary:
... [lean-ctx: omitted 1 lines]
- The support account may be created by HR or Registrar Administrator, but creation grants only the explicit Registrar operational subset; it does not grant Clerk/Admin setup authority.
... [lean-ctx: omitted 2 lines]
- Accept or dismiss applicants when delegated through the Registrar decision capability.
... [lean-ctx: omitted 2 lines]
Registrar Clerk may additionally maintain approved institutional/reference/setup pages. Registrar Administrator owns the office-level configuration ceiling and setup activation rules.
... [lean-ctx: omitted 1 lines]
- No permanent-role or support-staff creation unless separately authorized by the owning authority.
... [lean-ctx: omitted 2 lines]
- No admission slip is required to identify an answer sheet; Applicant No/Reference is the binding key.
### 2.4 Guidance Counselor
Allowed:
- Own Guidance exam/test configuration and sensitive test-specific operations.
- Conduct direct assessments, process OMR, review uncertainty, manage grading, enter/import/correct/finalize scores, and apply approved score conversion.
... [lean-ctx: omitted 2 lines]
- Confirm human consultation decisions and preserve Test Administrator suggestions separately from Applicant selections.
... [lean-ctx: omitted 5 lines]
- No HR personnel lifecycle authority outside approved staff-management actions.
- No unrestricted Registrar application administration.
... [lean-ctx: omitted 1 lines]
- No autonomous model-driven admission decision; model output is evidence only.
### 2.5 Guidance Clerk and Guidance Support Staff
Default inheritance:
... [lean-ctx: omitted 1 lines]
- The support account may be created by HR or Guidance Counselor, but creation does not grant Test Administrator or Proctor authority.
... [lean-ctx: omitted 4 lines]
- OMR intake/review, answer-sheet custody, grading-session operation, score entry/import/correction, result preparation/printing, consultation scheduling, and other sensitive test-specific operations listed in the capability set.
- Result release/unrelease only when the separate release authorization is also present.
... [lean-ctx: omitted 1 lines]
- Attendance, submission, roster, start/close, and other operations for the assigned ExamSession only.
... [lean-ctx: omitted 1 lines]
- No permanent-role creation, HR lifecycle, Registrar authority, cross-office result access, or unassigned exam-session access.
### 2.6 Program Head
Allowed:
- Program-scoped pipeline, pre-enrolment projections, and **quota write** for assigned programs (D-087).
- View authorized released result/remark sheets and converted per-domain scores.
- View authorized consultation history, program notes, model/inference provenance, Test Administrator suggestions, and Applicant-selected program/course values.
... [lean-ctx: omitted 5 lines]
- Every protected applicant-result, consultation-history, model-provenance, or projection view/export is audited.
### 2.7 Proctor
Allowed:
- Only assigned ExamSession operations: roster, attendance, submission, session monitoring, and authorized start/close actions.
- Answer-sheet custody actions explicitly assigned by the Guidance workflow.
... [lean-ctx: omitted 1 lines]
- No score conversion, grading, result/remark view, converted per-domain score, confidential conversion table, result release, or unrelated-session access.
... [lean-ctx: omitted 3 lines]
### 2.8 Applicant
Allowed:
... [lean-ctx: omitted 1 lines]
- Receive an access key after submission (confirmation + approved email channel); login with access key + birthdate (D-085).
- View own submitted/accepted status, schedule, consultation availability, and currently released result artifacts.
... [lean-ctx: omitted 3 lines]
- No other applicant’s records, raw answer sheets, bubble scans, confidential conversion table, internal notes, or unreleased result data.
- Applicant No/Reference is not itself sufficient authentication; birthday alone is not sufficient; access key alone is not sufficient (D-085).
### 2.9 Super Admin
Allowed only as break-glass:
... [lean-ctx: omitted 1 lines]
- Full audit-scope user view across offices, users, programs, sessions, protected views, mutations, filters, and audit-chain/checkpoint evidence; the audit-view command is itself audited and alerted.
... [lean-ctx: omitted 1 lines]
- No ordinary HR, Registrar, Guidance, Proctor, scoring, counseling, result, or applicant business-data browsing workflow merely because the audit view can see the corresponding audit event.
## 3. SCREEN & USE CASE MATRIX
### Cell Format
Every cell: **Built** or **Not built** — followed by **✓** (role has access) or **✗** (role does not have access, or access is currently bugged).
```
Built ✓     = feature exists, this role can access it
Built ✗     = feature exists, this role has no access (or access is currently broken)
Not built ✓ = feature not built yet, but this role WILL have access once built
Not built ✗ = feature not built yet, and this role will NOT have access
```
### Change Column
```
Stays    = no change needed
Changed  = role renamed, split, re-scoped, or access modified
New      = feature or role being added
Not built = yet to be built
```
### Legacy/build-status reconciliation appendix
> The former `system_it_admin` matrix column is legacy comparison material and must be reconciled into the target `hr_staff_admin` personnel scope plus `super_admin` break-glass scope before promotion. It is not permission to give HR technical or applicant-data access. Do not treat `Built ✓` as an authorization grant; the grouped catalog and GF-04 capability rules govern.
| # | Use Case | hr_staff_admin / super_admin | registrar_admin | registrar_clerk | registrar_support | guidance_counselor | guidance_clerk | guidance_support | program_head | proctor | applicant | Change |
... [lean-ctx: omitted 2 lines]
| 2 | **INSTITUTIONAL** — Configure system-wide settings (branding, release mode, AI) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 3 | **INSTITUTIONAL** — Customize institution name, logo, and visual identity | Built ✓ | Built ✓ | Built ✓ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
... [lean-ctx: omitted 8 lines]
| 12 | **LEGACY/RETIRED** — Admission-slip/QR attachment for answer-sheet identity (omit from target flow) | Built ✓ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Not built ✗ | Built ✗ | Built ✗ | Built ✓ | Changed/retired |
| 13 | **GUIDANCE** — Define aptitude sub-tests and score formulas | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 14 | **GUIDANCE** — Conduct walk-in direct assessments outside scheduled exams | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
... [lean-ctx: omitted 2 lines]
| 17 | **GUIDANCE** — Release/unrelease consultation summaries and result sheets | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 18 | **GUIDANCE** — Print individual and bulk result sheets (PDF/DOCX) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
... [lean-ctx: omitted 8 lines]
| 27 | **EXAMS** — AI-assisted schedule optimization (propose → human confirm) | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 28 | **EXAMS** — Monitor live exam sessions in real-time dashboard | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
... [lean-ctx: omitted 1 lines]
| 30 | **EXAMS** — Manage session roster (attendance, submissions, start/close) | Built ✓ | Built ✓ | Built ✗ | Not built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✓ | Built ✗ | Changed |
... [lean-ctx: omitted 2 lines]
| 33 | **AUDIT** — Upload, edit, and organize institutional knowledge documents | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
... [lean-ctx: omitted 2 lines]
| 36 | **FEATURE** — Display aptitude clusters and interest matches (Triage Dashboard) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built |
... [lean-ctx: omitted 2 lines]
| 39 | **FEATURE** — Send messages between offices and applicant fallback inquiries | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built |
... [lean-ctx: omitted 1 lines]
| 41 | **FEATURE** — Surface aptitude profiles and preference matches (Admission Engines) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built |
| 42 | **RESULTS** — View authorized converted result/remark sheet and per-domain scores (scope/capability required) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✓ | New |
... [lean-ctx: omitted 1 lines]
## 3. DELEGATION SUMMARY
| Action | Who Can Do It | Who Can Receive It |
... [lean-ctx: omitted 1 lines]
| Create permanent office roles | hr_staff_admin | registrar_admin, registrar_clerk, guidance_counselor, guidance_clerk, and other institution-approved permanent office roles |
... [lean-ctx: omitted 1 lines]
| Grant Registrar intake decision subset | registrar_admin or authorized registrar_admin delegate | registrar_clerk, registrar_support_staff |
| Maintain Registrar institutional/reference setup | registrar_admin / authorized registrar_clerk | registrar_clerk within approved setup ceiling; registrar_support_staff is denied |
... [lean-ctx: omitted 2 lines]
| Assign/revoke Proctor duty | authorized Test Administrator | guidance_clerk, guidance_support_staff |
... [lean-ctx: omitted 2 lines]
## 4. CHANGE SUMMARY
| Change Type | Count | Items |
|---|---|---|
... [lean-ctx: omitted 4 lines]
---
## 5. KNOWN BUGS TO FIX DURING REFACTOR
1. **Proctor middleware block** — Items 29, 30: `exam_session_user` pivot works at Policy level but middleware `role:...,proctor,...` blocks non-proctor users before Policy runs. Fix: remove proctor from route middleware, let Policy handle session-level authorization via pivot.
2. **test_administrator is one merged role** — Currently `test_administrator` covers both guidance clerk AND counselor functions. Must split: counselor gets ALL test_admin perms by default, clerk receives them only when granted by counselor.
3. **No guidance_support_staff or registrar_support_staff roles exist** — These are new roles that need Spatie role entries + creation UI in office head dashboards.
... [lean-ctx: omitted 3 lines]


[lean-ctx] full source: read "/home/user/Projects/NEXIAM/docs/access-control-inventory.md" directly (no MCP)  ·  or ctx_read("/home/user/Projects/NEXIAM/docs/access-control-inventory.md", mode="full")
