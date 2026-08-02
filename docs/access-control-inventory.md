# NEXIAM — Proposed Role Architecture & Screen-Use Case Matrix
## Working Proposal | 2026-07-29

> **Decision status:** D-014 naming direction and the current role/capability corrections are approved with conditions in the greenfield decision register. The grouped authority catalog below is the human-readable review source; the wide screen matrix is a legacy/build-status reconciliation appendix. No role migration should start until the target catalog, capability matrix, temporary-duty model, and legacy-role mapping are fully promoted.

---

## 1. ROLE ARCHITECTURE — PROPOSED TARGET MODEL

### Permanent Roles (created/managed by Staffing/HR authority, with office support-staff delegation)

| # | Role | Inherent Permissions |
|---|---|---|
| 1 | `hr_staff_admin` | Staff onboarding/offboarding and creation/deactivation of approved permanent and support-staff accounts; no applicant or operational data; cannot grant Test Administrator/Proctor capability |
| 2 | `registrar_admin` | Courses, rooms, academic years, privacy policies, reports, Registrar intake, create `registrar_support_staff` |
| 3 | `registrar_clerk` | Application intake, applicant import, Registrar-owned application/status work; no admission-slip requirement for answer-sheet identity |
| 4 | `guidance_counselor` | Inherent Test Administrator capability for sensitive exam/test operations, grading, OMR, score conversion, result preparation/printing, approved release coordination, scheduling, create `guidance_support_staff`, grant/revoke Test Administrator capability, and assign/revoke Proctor duty |
| 5 | `guidance_clerk` | Guidance Clerk operations including scheduling/knowledge support and only explicitly delegated test capabilities; no inherent Test Administrator or Proctor duty |
| 6 | `program_head` | Read-only program-scoped pipeline, quotas, pre-enrolment projections, authorized released result evidence, consultation history, program notes, and model provenance |

### Break-glass oversight

| Account | Boundary |
|---|---|
| `super_admin` | Optional non-evaluated break-glass support for exceptional technical/configuration/recovery scenarios; time-bound, reasoned, audited, and never routine office operations |


### Temporary/Seasonal Roles (created by HR or the owning office head within role ceilings)

| # | Role | Created By | Inherent Permissions |
|---|---|---|---|
| 8 | `registrar_support_staff` | `hr_staff_admin` or `registrar_admin` | Same as `registrar_clerk` by default (scoped); no Registrar Administrator decision or cross-office authority |
| 9 | `guidance_support_staff` | `hr_staff_admin` or `guidance_counselor` | Same as `guidance_clerk` by default (scoped); receives Test Administrator capability or Proctor duty only when explicitly granted/assigned |

### Test Administrator — Permission Set (NOT a role)

The `guidance_counselor` is inherently a Test Administrator. The capability is an approved Guidance-scoped set for sensitive exam/test operations; it is not a permanent role. The Counselor may grant/revoke applicable capabilities to Guidance Clerk or Guidance Support Staff with scope, dates, and audit evidence:

| Permission | Description |
|---|---|
| `configure-test` | Define test-specific areas, sub-tests, rubrics, and approved scoring configuration |
| `process-omr` | Receive, identify by Applicant No/Reference, scan/import, review uncertainty, and reconcile answer sheets |
| `grade-exams` | Score exams, manage grading sessions, and finalize/correct scores within the versioned lifecycle |
| `record-attendance` | Mark attendance and manage assigned exam-session roster |
| `collect-answer-sheets` | Collect and maintain physical/digital answer-sheet custody for assigned sessions |
| `prepare-print-results` | Generate and print internal result sheets after score finalization |
| `manage-consultation` | Publish F2F batches/online availability, assign or reserve consultation slots, and record consultation evidence |
| `assign-proctor` | Assign/revoke the separate session-scoped Proctor duty for eligible Guidance staff |
| `release-results` | Release/unrelease approved result artifacts when the holder also has the separate release authorization |

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
- View personnel-lifecycle audit evidence.

Limited/denied:

- No applicant, application, answer-sheet, OMR, score, result, consultation, program-projection, or operational exam data.
- No academic-cycle/program/room configuration.
- No Test Administrator grant/revoke.
- No Proctor assignment.
- No score, counseling, release, or applicant-communication authority.
- Account creation must not exceed the approved role ceiling.

Audit:

- Every personnel creation, activation, deactivation, transfer, role assignment, and support-staff lifecycle change is attributable to HR or the office authority that performed it.

### 2.2 Registrar Administrator

Allowed:

- Manage Registrar-owned academic reference data, application windows, intake records, imports, and Registrar reports.
- Accept, dismiss, correct, archive, or reopen applications under the approved Registrar workflow.
- Create Registrar Support Staff with Registrar-scoped delegation.
- View approved shared workflow fields, schedules, and application/status information.

Limited/denied:

- No converted result/remark sheet, converted per-domain score, raw answer sheet, bubble scan, or confidential score-conversion table.
- No grading, score conversion, result printing/release, Test Administrator capability, or Proctor assignment.
- No Guidance-owned consultation decision or model override.

Audit:

- Acceptance/dismissal, correction, import, support-staff creation, and protected cross-office views are audited.

### 2.3 Registrar Clerk and Registrar Support Staff

Default inheritance:

- Registrar Support Staff inherits Registrar Clerk responsibilities by default.
- The support account may be created by HR or Registrar Administrator, but creation does not grant Administrator authority.

Allowed:

- Create and correct permitted application/intake records.
- Perform validated applicant imports when delegated.
- View Registrar-owned application and status fields.
- Participate in approved Registrar correction/reconciliation work.

Denied:

- No permanent-role or support-staff creation unless separately authorized by the owning authority.
- No converted result/remark sheet, converted score, raw answer sheet, OMR, grading, result release, Test Administrator, or Proctor authority.
- No admission slip is required to identify an answer sheet; Applicant No/Reference is the binding key.

### 2.4 Guidance Counselor

Allowed:

- Own Guidance exam/test configuration and sensitive test-specific operations.
- Conduct direct assessments, process OMR, review uncertainty, manage grading, enter/import/correct/finalize scores, and apply approved score conversion.
- Prepare and print result sheets before external release where required.
- Publish F2F consultation batches and online consultation availability.
- Confirm human consultation decisions and preserve Test Administrator suggestions separately from Applicant selections.
- Create Guidance Support Staff.
- Grant/revoke Test Administrator capabilities to Guidance Clerk or Guidance Support Staff.
- Assign/revoke Proctor duty for specific exam sessions.
- Coordinate approved result release/unrelease.

Limited/denied:

- No HR personnel lifecycle authority outside approved staff-management actions.
- No unrestricted Registrar application administration.
- No raw/result access outside Guidance scope or assigned workflow.
- No autonomous model-driven admission decision; model output is evidence only.

### 2.5 Guidance Clerk and Guidance Support Staff

Default inheritance:

- Guidance Support Staff inherits Guidance Clerk responsibilities by default.
- The support account may be created by HR or Guidance Counselor, but creation does not grant Test Administrator or Proctor authority.

Allowed by default:

- Guidance scheduling/knowledge support and other ordinary Clerk work within office scope.
- View permitted Guidance records.

Allowed only with explicit Test Administrator capability:

- OMR intake/review, answer-sheet custody, grading-session operation, score entry/import/correction, result preparation/printing, consultation scheduling, and other sensitive test-specific operations listed in the capability set.
- Result release/unrelease only when the separate release authorization is also present.

Allowed only with explicit Proctor duty:

- Attendance, submission, roster, start/close, and other operations for the assigned ExamSession only.

Denied:

- No permanent-role creation, HR lifecycle, Registrar authority, cross-office result access, or unassigned exam-session access.

### 2.6 Program Head

Allowed:

- Read-only, program-scoped pipeline, quota, and pre-enrolment projections.
- View authorized released result/remark sheets and converted per-domain scores.
- View authorized consultation history, program notes, model/inference provenance, Test Administrator suggestions, and Applicant-selected program/course values.
- Use approved projection views for enrollment preparation.

Denied:

- No raw answer sheets, bubble scans, confidential conversion table, score editing, score conversion, result release, role creation, Test Administrator assignment, or Proctor assignment.
- No applicants or programs outside the assigned scope.

Audit:

- Every protected applicant-result, consultation-history, model-provenance, or projection view/export is audited.

### 2.7 Proctor

Allowed:

- Only assigned ExamSession operations: roster, attendance, submission, session monitoring, and authorized start/close actions.
- Answer-sheet custody actions explicitly assigned by the Guidance workflow.

Denied:

- No score conversion, grading, result/remark view, converted per-domain score, confidential conversion table, result release, or unrelated-session access.
- Cannot assign another Proctor unless separately holding the authorized Test Administrator capability.

Lifecycle:

- Proctor is a revocable, session-scoped duty overlay—not a permanent base role.

### 2.8 Applicant

Allowed:

- Submit an application and receive an Applicant No/Reference.
- Receive a one-time setup/access challenge after submission through an approved channel.
- View own submitted/accepted status, schedule, consultation availability, and currently released result artifacts.
- Reserve an online consultation slot when that mode is enabled.
- View own consultation history and approved applicant-facing data.

Denied:

- No other applicant’s records, raw answer sheets, bubble scans, confidential conversion table, internal notes, or unreleased result data.
- Applicant No/Reference is not itself sufficient authentication; birthday alone is not sufficient recovery.

### 2.9 Super Admin

Allowed only as break-glass:

- Exceptional technical/configuration/recovery support with explicit reason, scope, expiry, audit, and post-use review.

Denied as routine authority:

- No ordinary HR, Registrar, Guidance, Proctor, scoring, counseling, result, or applicant browsing workflow.

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
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | **INSTITUTIONAL** — Manage user accounts, assign permanent roles | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 2 | **INSTITUTIONAL** — Configure system-wide settings (branding, release mode, AI) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 3 | **INSTITUTIONAL** — Customize institution name, logo, and visual identity | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 4 | **INSTITUTIONAL** — Define scoring rubrics and rating scale thresholds | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 5 | **INSTITUTIONAL** — View and export admission pipeline reports | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 6 | **REGISTRAR** — Create and manage academic year periods | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 7 | **REGISTRAR** — Create and manage academic programs/courses | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 8 | **REGISTRAR** — Create and manage exam rooms and venues | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 9 | **REGISTRAR** — Draft, publish, and archive privacy policy documents | Built ✓ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Not built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 10 | **REGISTRAR** — Process new applications, review and edit applicant records | Built ✓ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Not built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 11 | **REGISTRAR** — Bulk import applicants from CSV with validation preview | Built ✓ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Not built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 12 | **LEGACY/RETIRED** — Admission-slip/QR attachment for answer-sheet identity (omit from target flow) | Built ✓ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Not built ✗ | Built ✗ | Built ✗ | Built ✓ | Changed/retired |
| 13 | **GUIDANCE** — Define aptitude sub-tests and score formulas | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 14 | **GUIDANCE** — Conduct walk-in direct assessments outside scheduled exams | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 15 | **GUIDANCE** — Oversee grading dashboard, manage grading session workflow | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 16 | **GUIDANCE** — Input exam scores (manual entry + CSV import) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 17 | **GUIDANCE** — Release/unrelease consultation summaries and result sheets | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 18 | **GUIDANCE** — Print individual and bulk result sheets (PDF/DOCX) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 19 | **GUIDANCE** — OMR scanning: upload and process scanned answer sheets | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built ✗ | Not built |
| 20 | **GUIDANCE** — Setup exam scheduling configuration, manage venues+dates | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 21 | **GUIDANCE** — Grade exams, manage grading sessions (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 22 | **GUIDANCE** — Input scores manually or via CSV (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 23 | **GUIDANCE** — Record attendance, manage session roster (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✓ | Built ✗ | Changed |
| 24 | **GUIDANCE** — Collect and manage answer sheets | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built ✗ | Not built |
| 25 | **GUIDANCE** — Release results and print result sheets (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 26 | **EXAMS** — Schedule exam sessions (create, assign applicants, publish, cancel) | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 27 | **EXAMS** — AI-assisted schedule optimization (propose → human confirm) | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 28 | **EXAMS** — Monitor live exam sessions in real-time dashboard | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 29 | **EXAMS** — View proctor's assigned exam sessions list | Built ✓ | Built ✓ | Built ✗ | Not built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✓ | Built ✗ | Changed |
| 30 | **EXAMS** — Manage session roster (attendance, submissions, start/close) | Built ✓ | Built ✓ | Built ✗ | Not built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✓ | Built ✗ | Changed |
| 31 | **AUDIT** — Review system audit logs with event filtering and export | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 32 | **AUDIT** — Manage AI companion persona, knowledge base, and sync status | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 33 | **AUDIT** — Upload, edit, and organize institutional knowledge documents | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Stays |
| 34 | **AUDIT** — Map permissions to roles via admin panel (Spatie RBAC UI) | Not built ✓ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | New |
| 35 | **FEATURE** — Answer applicant questions using ISPSC policy docs (RAG chatbot) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built |
| 36 | **FEATURE** — Display aptitude clusters and interest matches (Triage Dashboard) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built |
| 37 | **FEATURE** — View program applicant pipeline, quotas, pre-enrolment lists | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✗ | Not built ✗ | Not built |
| 38 | **FEATURE** — Automatically email exam schedules, results, status updates | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built |
| 39 | **FEATURE** — Send messages between offices and applicant fallback inquiries | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built |
| 40 | **FEATURE** — Cryptographically chain audit events with HMAC-SHA256 | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built |
| 41 | **FEATURE** — Surface aptitude profiles and preference matches (Admission Engines) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built |
| 42 | **RESULTS** — View authorized converted result/remark sheet and per-domain scores (scope/capability required) | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✓ | New |

---

## 3. DELEGATION SUMMARY

| Action | Who Can Do It | Who Can Receive It |
|---|---|---|
| Create permanent office roles | hr_staff_admin | registrar_admin, registrar_clerk, guidance_counselor, guidance_clerk, and other institution-approved permanent office roles |
| Create registrar_support_staff | registrar_admin | Temp/seasonal registrar staff |
| Create guidance_support_staff | guidance_counselor | Temp/seasonal guidance staff |
| Grant test_admin perms | guidance_counselor | guidance_clerk, guidance_support_staff |
| Assign/revoke Proctor duty | authorized Test Administrator | guidance_clerk, guidance_support_staff |
| Assign Proctor to exam session | authorized Test Administrator | Any eligible Proctor (via exam_session_user pivot) |

---

## 4. CHANGE SUMMARY

| Change Type | Count | Items |
|---|---|---|
| **Stays** | 7 | 1, 2, 6, 7, 8, 27, 32, 33 |
| **Changed** | 22 | 3, 4, 5, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 20, 21, 22, 23, 25, 26, 28, 29, 30, 31 |
| **New** | 1 | 34 (Spatie RBAC) |
| **Not built** | 11 | 19, 24, 35–41 |

---

## 5. KNOWN BUGS TO FIX DURING REFACTOR

1. **Proctor middleware block** — Items 29, 30: `exam_session_user` pivot works at Policy level but middleware `role:...,proctor,...` blocks non-proctor users before Policy runs. Fix: remove proctor from route middleware, let Policy handle session-level authorization via pivot.

2. **test_administrator is one merged role** — Currently `test_administrator` covers both guidance clerk AND counselor functions. Must split: counselor gets ALL test_admin perms by default, clerk receives them only when granted by counselor.

3. **No guidance_support_staff or registrar_support_staff roles exist** — These are new roles that need Spatie role entries + creation UI in office head dashboards.

4. **Program Head role completely absent** — Role, routes, policies, and dashboard screens must all be built.

---

*Generated: 2026-07-29 | Settled role architecture for NEXIAM*
