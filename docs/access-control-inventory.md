# NEXIAM — Proposed Role Architecture & Screen-Use Case Matrix
## Working Proposal | 2026-07-29

> **Decision status:** Not authoritative or settled. This inventory is a target proposal that must be reconciled against (1) the live-code catalog—`super_admin`, `registrar_administrator`, `test_administrator`, and `proctor`; (2) manuscript role terminology; and (3) the permission/use-case needs below. No role migration should start until an ADR/OpenSpec approves the target catalog, capability matrix, temporary-duty model, and legacy-role mapping.

---

## 1. ROLE ARCHITECTURE — PROPOSED TARGET MODEL

### Permanent Roles (assigned by system_it_admin / HR)

| # | Role | Inherent Permissions |
|---|---|---|
| 1 | `system_it_admin` | User management, system settings, audit logs, assign ALL permanent roles |
| 2 | `registrar_admin` | Courses, rooms, academic years, privacy policies, reports, create registrar_support_staff |
| 3 | `registrar_clerk` | Application intake, applicant import, admission slips |
| 4 | `guidance_counselor` | **ALL test_admin perms** (grade, score, attendance, answer sheets, release) + grading + aptitude areas + scheduling + create guidance_support_staff + grant test_admin perms + assign proctor role |
| 5 | `guidance_clerk` | OMR scanning, scheduling setup, knowledge documents |
| 6 | `program_head` | View program pipeline, quotas, pre-enrolment lists |
| 7 | `proctor` | Session roster, attendance, submissions, start/close (scoped per exam session via `exam_session_user` pivot) |

### Temporary/Seasonal Roles (created by office heads)

| # | Role | Created By | Inherent Permissions |
|---|---|---|---|
| 8 | `registrar_support_staff` | registrar_admin | Same as registrar_clerk (scoped) |
| 9 | `guidance_support_staff` | guidance_counselor | Receives test_admin perms + proctor role as granted by counselor |

### Test Administrator — Permission Set (NOT a role)

The `guidance_counselor` IS inherently test_admin. She can grant these permissions to any guidance staff:

| Permission | Description |
|---|---|
| `grade-exams` | Score exams, manage grading sessions |
| `input-scores` | Manual score entry + CSV import |
| `record-attendance` | Mark attendance, manage session roster |
| `collect-answer-sheets` | Collect physical/digital answer sheets |
| `release-results` | Release/unrelease consultation summaries |
| `manage-grading` | Grading dashboard, workflow states |
| `manage-release` | Result sheet printing, release management |

### Proctor — Role (assigned by guidance_counselor)

Proctor IS a role. The guidance_counselor assigns it to any guidance staff (clerk or support_staff). The `exam_session_user` pivot scopes WHICH exam sessions a proctor oversees — authorization at the Policy level, not middleware.

---

## 2. SCREEN & USE CASE MATRIX

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

### Matrix

| # | Use Case | system_it_admin | registrar_admin | registrar_clerk | registrar_support | guidance_counselor | guidance_clerk | guidance_support | program_head | proctor | applicant | Change |
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
| 12 | **REGISTRAR** — Generate admission slips (single, bulk, PDF print) | Built ✓ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Not built ✗ | Built ✗ | Built ✗ | Built ✓ | Stays |
| 13 | **GUIDANCE** — Define aptitude sub-tests and score formulas | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 14 | **GUIDANCE** — Conduct walk-in direct assessments outside scheduled exams | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 15 | **GUIDANCE** — Oversee grading dashboard, manage grading session workflow | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 16 | **GUIDANCE** — Input exam scores (manual entry + CSV import) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 17 | **GUIDANCE** — Release/unrelease consultation summaries and result sheets | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✓ | Changed |
| 18 | **GUIDANCE** — Print individual and bulk result sheets (PDF/DOCX) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Built ✗ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 19 | **GUIDANCE** — OMR scanning: upload and process scanned answer sheets | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built ✗ | Not built |
| 20 | **GUIDANCE** — Setup exam scheduling configuration, manage venues+dates | Built ✓ | Built ✓ | Built ✗ | Built ✗ | Built ✓ | Built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 21 | **GUIDANCE** — Grade exams, manage grading sessions (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 22 | **GUIDANCE** — Input scores manually or via CSV (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✗ | Changed |
| 23 | **GUIDANCE** — Record attendance, manage session roster (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✓ | Built ✗ | Changed |
| 24 | **GUIDANCE** — Collect and manage answer sheets | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✗ | Not built ✓ | Not built ✓ | Not built ✓ | Not built ✗ | Not built ✗ | Not built ✗ | Not built |
| 25 | **GUIDANCE** — Release results and print result sheets (test_admin perm) | Built ✓ | Built ✗ | Built ✗ | Built ✗ | Built ✓ | Not built ✓ | Not built ✓ | Built ✗ | Built ✗ | Built ✓ | Changed |
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

---

## 3. DELEGATION SUMMARY

| Action | Who Can Do It | Who Can Receive It |
|---|---|---|
| Create permanent roles | system_it_admin only | registrar_admin, registrar_clerk, guidance_counselor, guidance_clerk, program_head, proctor |
| Create registrar_support_staff | registrar_admin | Temp/seasonal registrar staff |
| Create guidance_support_staff | guidance_counselor | Temp/seasonal guidance staff |
| Grant test_admin perms | guidance_counselor | guidance_clerk, guidance_support_staff |
| Assign proctor role | guidance_counselor | guidance_clerk, guidance_support_staff |
| Assign proctor to exam session | guidance_counselor | Any proctor (via exam_session_user pivot) |

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
