# Role, Capability, and Data-Scope Matrix

**Artifact:** NEXIAM-GF-04  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Replace scattered role-name checks with one proposed capability and scope contract.  
**Default:** Deny unless an approved row grants the action within its scope.

## 1. Matrix vocabulary

- **ALLOW:** May perform the capability across the explicitly named institutional scope.
- **PUBLIC:** Available before authentication only through the explicitly bounded intake contract; grants no protected read scope.
- **OFFICE:** Limited to the actor’s approved office scope.
- **PROGRAM:** Limited to assigned program(s).
- **OWN:** Applicant’s own account/admission attempts/data only.
- **ASSIGNED:** Limited to a specific temporary assignment, such as one exam session.
- **BREAK-GLASS:** Exceptional, reasoned, audited access; not routine authority.
- **DENY:** No access.
- **REVIEW:** Stakeholder/founder decision required.

## 2. Proposed actor catalog

| Actor ID | Proposed actor | Nature | Evaluated | Proposed boundary | Decision |
|---|---|---|---|---|---|
| ACT-01 | Applicant | Base role/account type | Yes | Own portal and own admission data | D-ROLE-01 |
| ACT-02 | Registrar Clerk | Base staff role | Yes | Daily intake and record operations within Registrar scope | D-ROLE-01 |
| ACT-03 | Registrar Administrator | Base staff role | Yes | Registrar oversight, institutional/reference configuration, approvals, and delegated accounts | D-ROLE-01 |
| ACT-04 | Guidance Counselor | Base staff role | Yes | Counseling, scoring, release, and approved assessment capabilities | D-ROLE-01 |
| ACT-05 | Guidance Clerk | Base staff role | Yes | Guidance operational administration and delegated staff authority | D-ROLE-01 |
| ACT-06 | Program Head | Base staff role | Yes | Assigned-program projections; own quota; direct pre-enrolling decline; existing edge-follow-up resolution through enrolling; bounded messaging and Tier B read-only admission-support evidence | D-ROLE-01/D-ROLE-05/D-080/D-087/D-089–D-093 |
| ACT-07 | Staffing/HR Administrator | Personnel lifecycle role | No separate product evaluation | Create/deactivate approved permanent and support-staff accounts and role records; no operational applicant/admission authority; cannot grant Test Administrator/Proctor | D-ROLE-01/D-017/D-019 |
| ACT-08 | Super Admin | System oversight account | No | BREAK-GLASS only if retained; every use reasoned and audited | D-ROLE-04 |
| ACT-09 | Registrar Support Staff | Subordinate/temporary office role | Yes | Explicit Registrar operational subset: delegated intake/correction, accept/dismiss, and status visibility; no Registrar Clerk/Admin setup pages | D-ROLE-01/D-020/D-036 |
| ACT-10 | Guidance Support Staff | Subordinate/temporary office role | Yes | Guidance Clerk responsibilities by default; Test Administrator/Proctor only through explicit overlays | D-ROLE-01/D-015/D-016/D-020 |
| DUTY-01 | Proctor | Temporary contextual duty | No separate role | ASSIGNED examination session only | D-ROLE-02 |
| CAPSET-01 | Test Administration | Explicit capability set | No separate role | Assigned only to approved Guidance actors | D-ROLE-03 |

## 3. Proposed target capability matrix

Abbreviations: APP Applicant, RC Registrar Clerk, RA Registrar Admin, GC Guidance Counselor, GCL Guidance Clerk, PH Program Head, HR Staffing/HR Admin, SA Super Admin. Registrar Support Staff and Guidance Support Staff are not automatically equivalent to RC/GCL; each receives only the explicit overlays in §3.1.

| Capability ID | Capability | APP | RC | RA | GC | GCL | PH | HR | SA | Status/evidence |
|---|---|---|---|---|---|---|---|---|---|---|
| CAP-001 | Create admission attempt | PUBLIC/OWN | DENY | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Legacy permits public and staff intake; target pre-account identity/account activation follows D-073 |
| CAP-002 | View admission attempt | OWN | OFFICE (admission fields) | OFFICE (admission fields) | OFFICE/REVIEW | OFFICE/REVIEW | PROGRAM tiered candidate/record view | DENY | BREAK-GLASS | D-080: preference/pool candidate overview after Registrar acceptance; protected record only after result release + applicant-acknowledged selection or explicit targeted follow-up; raw artifacts remain denied |
| CAP-003 | Correct application data before acceptance | OWN under guard | OFFICE | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Own-edit freeze after assignment is useful legacy intent |
| CAP-004 | Accept/dismiss application | DENY | OFFICE | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Registrar Clerk and explicitly authorized Registrar Support Staff may decide within Registrar scope; reason/version/audit required |
| CAP-005 | Reopen/archive/correct accepted attempt | DENY | DENY | OFFICE with reason | DENY | REVIEW | DENY | DENY | BREAK-GLASS | Must be non-destructive |
| CAP-006 | Import applications | DENY | OFFICE | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Built; requires idempotency and error report |
| CAP-007 | Configure academic cycles/application windows | DENY | OFFICE (approved setup pages) | ALLOW | OFFICE | OFFICE | DENY | DENY | BREAK-GLASS | **D-087 shared setup:** Registrar Clerk/Admin and Guidance Counselor/Clerk may maintain; Support Staff denied; one Setup hub |
| CAP-008 | Configure programs/courses/rooms | DENY | OFFICE (approved setup pages) | ALLOW | OFFICE | OFFICE | PROGRAM read | DENY | BREAK-GLASS | **D-087 shared setup:** rooms and courses interchangeable between Registrar and Guidance; Program Head remains read on programs |
| CAP-009 | Create exam session | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | PROGRAM read | DENY | BREAK-GLASS | Examination ownership resolves through the concrete holder of Test Administrator capability, not a generic Guidance role |
| CAP-010 | Assign applicants to session | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Includes applicant-reservation finalization and authorized manual late-cohort assignment; transactional eligibility/capacity required |
| CAP-011 | Publish/unpublish/cancel/reopen session | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | PROGRAM read | DENY | BREAK-GLASS | Reversal requires reason/audit; applicant notification follows publication |
| CAP-012 | Assign/revoke Proctor duty | DENY | DENY | DENY | CAPSET | CAPSET | DENY | DENY | BREAK-GLASS | Only the authorized Test Administrator capability holder may assign/revoke session-scoped Proctor duty |
| CAP-013 | View assigned proctor roster | DENY | DENY | REVIEW | ASSIGNED/office | ASSIGNED/office | DENY | DENY | BREAK-GLASS | Proctor scope is session assignment only |
| CAP-014 | Start/monitor/close assigned session | DENY | DENY | DENY | OFFICE/CAPSET | ASSIGNED | DENY | DENY | BREAK-GLASS | Test Administrator may manage within authorized scope; Proctor remains ASSIGNED; force-open/close uses the approved effective window |
| CAP-015 | Record attendance/submission | DENY | DENY | DENY | ASSIGNED | ASSIGNED | DENY | DENY | BREAK-GLASS | Idempotent session command |
| CAP-016 | Configure aptitude areas/rubrics | DENY | DENY | DENY | OFFICE | OFFICE | PROGRAM read | DENY | BREAK-GLASS | Guidance-owned proposed default |
| CAP-017 | Import/enter/correct scores | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Active legacy routes grant Super Admin/Test Administrator; target counselor/admin capability and corrections require D-076 |
| CAP-018 | Finalize/reopen grading | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Completeness and version/reason required |
| CAP-019 | Run OMR batch/review uncertainty | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Not built; spike-gated |
| CAP-020 | Record counseling/triage decision | DENY | DENY | DENY | OFFICE | CAPSET/REVIEW | DENY | DENY | BREAK-GLASS | Guidance Counselor remains final human decision authority; Test Administrator capability may draft/assist only where explicitly granted |
| CAP-021 | Release/unrelease result | DENY | DENY | DENY | OFFICE | OFFICE | DENY | DENY | BREAK-GLASS | Exact Guidance authority needs confirmation |
| CAP-022 | View released own result/schedule | OWN | DENY | OFFICE operational view | OFFICE | OFFICE | PROGRAM aggregate only | DENY | BREAK-GLASS | Applicant own-data boundary |
| CAP-023 | Preview/generate/distribute admission/result documents | OWN released/approved docs | OFFICE intake docs | OFFICE intake docs | OFFICE result preview/release | OFFICE result preview/release | PROGRAM-scoped result/remark sheet view | DENY | BREAK-GLASS | Program Head may view authorized converted result evidence; raw answer sheets and confidential conversion tables remain restricted |
| CAP-024 | View Program Head dashboard and authorized applicant detail | DENY | DENY | REVIEW aggregate | REVIEW aggregate | REVIEW aggregate | PROGRAM aggregate + tiered candidate/record detail | DENY | BREAK-GLASS | D-ROLE-05/D-080/D-090: Potential/Upcoming/Follow-up/Enrolling/Enrolled projections; no score/release or Registrar Accept/Dismiss writes |
| CAP-025 | Modify program quota | DENY | DENY | DENY | OFFICE/CAPSET | DENY | PROGRAM | DENY | BREAK-GLASS | **D-087:** Program Head writes own program quotas; Guidance Counselor (TA) may also adjust with audit + PH communication seam; before result release / pre-enrolment |
| CAP-026 | Use institutional RAG | OWN/authorized | OFFICE/authorized | OFFICE/authorized | OFFICE/authorized | OFFICE/authorized | PROGRAM/public corpus | DENY | BREAK-GLASS | Policy, own-data, and staff-task contexts are separate; retrieval scope resolves before prompting and technical corpus administration is not an HR function |
| CAP-027 | Retrieve own data through assistant | OWN released/authorized data | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | No cross-applicant retrieval; own score/result/consultation visibility follows release authority |
| CAP-028 | Send/receive bounded inquiry thread | OWN confirmed thread | OFFICE assigned | OFFICE assigned | OFFICE assigned | OFFICE assigned | REVIEW | DENY | BREAK-GLASS | Not built; conditional workflow requires participant/routing/retention/SLA decisions and applicant confirmation |
| CAP-029 | Manage users in own office | DENY | DENY | OFFICE | DENY | OFFICE | DENY | ALLOW personnel | BREAK-GLASS | HR manages approved permanent personnel lifecycle; offices manage subordinate support staff |
| CAP-030 | Create/assign approved permanent and support-staff roles | DENY | DENY | OFFICE-limited | DENY | OFFICE-limited | DENY | ALLOW approved permanent and support-staff roles | BREAK-GLASS | HR may create/manage approved personnel accounts; Registrar and Guidance may create their own support staff; HR cannot grant Test Administrator/Proctor capability |
| CAP-031 | Manage system/integration settings | DENY | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | Technical support is Super Admin break-glass only |
| CAP-032 | Manage knowledge corpus | DENY | DENY | REVIEW | REVIEW | REVIEW | DENY | DENY | BREAK-GLASS | Content owner versus technical operator must be split |
| CAP-033 | View/export audit/report evidence | OWN access history optional | DENY | OFFICE/REVIEW | DENY | OFFICE/REVIEW | PROGRAM aggregate/REVIEW | personnel audit only | BREAK-GLASS (FULL AUDIT SCOPE) | Super Admin is the master full-audit user view; audit visibility does not grant routine business-data browsing, and audit-view use is itself logged/alerted |
| CAP-034 | Verify audit chain/checkpoints | DENY | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | Break-glass technical security operation |
| CAP-035 | Override protected transition/access | DENY | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | Must require reason, time bound, alert, and review |
| CAP-036 | View authorized converted result/remark sheet and per-domain scores | OWN released | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | PROGRAM protected record after release + strong case relationship | DENY | BREAK-GLASS | D-080: requires applicant-acknowledged current selection or explicit targeted follow-up; preference/pool alone is insufficient; other-program choices and raw artifacts never serialize; every protected view is audited and appears in applicant access history |
| CAP-037 | Use authorized assistant task commands | OWN approved context | DENY | OFFICE/approved setup/task scope | CAPSET/office scope | CAPSET/office scope | PROGRAM/read-only approved context | DENY | BREAK-GLASS | Assistant may propose or invoke only typed UI-equivalent commands after server-side authorization; mutations require confirmation and audit; no direct model/database path |
| CAP-038 | Manage Guidance program pool and advised default | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | D-090: Guidance appends immutable pool versions and normally records an attributable quota-holding advised default; applicant may acknowledge through portal/Guidance or Registrar sheet submission under CAP-043 |
| CAP-039 | Select/acknowledge/change provisional program | OWN before enrolling | DENY | DENY | ASSISTED before enrolling | ASSISTED before enrolling | DENY | DENY | BREAK-GLASS | D-090: ordinary choice stays in Guidance pool and is FCFS/idempotent; after enrolling only PH edge resolution or Registrar paper finalization may change/release provisional commitment |
| CAP-040 | Open targeted PH follow-up | DENY | DENY | DENY | OFFICE/CAPSET before enrolling | OFFICE/CAPSET before enrolling | DENY | DENY | BREAK-GLASS | D-089: Guidance routes the edge case; after opening, assigned PH terminal activity is required; it permits enrolling and blocks enrolled |
| CAP-041 | Resolve assigned-program follow-up or directly decline selection | DENY | DENY | DENY | DENY | DENY | PROGRAM staged | DENY | BREAK-GLASS | D-089: direct decline is pre-enrolling; already-open follow-up clear/decline/request-appearance remains available during enrolling; PH cannot alter Registrar paper-final truth |
| CAP-042 | Participate in bounded admission-support thread | OWN current case | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | PROGRAM current selection/follow-up | DENY | BREAK-GLASS | D-092: internal PH↔Guidance and bounded PH↔applicant support with unread/read projection; preference alone cannot message |
| CAP-043 | Stamp/revert enrollment completion and verify paper program | DENY | OFFICE | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | D-091: Registrar starts enrolling on sheet submission, may record applicant confirmation, and finalizes signed-paper program after PH edge resolution; paper truth may exceed quota but never changes configured capacity |
| CAP-044 | Resolve universal applicant QR into authorized action hub | OWN QR display | OFFICE underlying scope | OFFICE underlying scope | OFFICE underlying scope | OFFICE underlying scope | PROGRAM underlying scope | DENY | BREAK-GLASS | D-092: one opaque applicant locator has no feature destination; scan re-evaluates capability, duty, office/program/cycle relationship, case state, and scope; returns only authorized destinations and never mutates automatically |

## 3.1 Role inheritance and capability overlays

- `registrar_support_staff` is not Registrar Clerk-equivalent. It receives only delegated intake/correction, accept/dismiss, and status visibility; it cannot access institutional/reference/setup pages or Registrar role management.
- `guidance_support_staff` inherits Guidance Clerk responsibilities by default. It does not inherit Test Administrator or Proctor authority unless explicitly granted/assigned.
- HR may create both permanent staff and support-staff accounts, but account creation does not grant office capabilities beyond the approved role ceiling.
- Guidance Counselor has the Test Administrator capability inherently. Guidance Clerk and Guidance Support Staff may receive it only through explicit Guidance delegation with grant/revoke dates and audit evidence.
- Proctor is a session-scoped duty overlay. It is assigned only to an eligible staff account and grants only the assigned ExamSession operations.
- Test Administrator capability covers sensitive exam/test-specific operations: OMR intake/review, grading-session operation, score entry/import/correction within the approved lifecycle, result preparation/printing, exam slot-reservation control, manual scheduling/direct-assessment paths, release coordination where granted, consultation scheduling, and Proctor assignment. It does not grant HR, Registrar, or Program Head authority.
- Program Head remains read-only and program-scoped by default. Exceptions are own-program quota write (D-087), tiered protected record access (D-080), direct pre-enrolling decline, already-open follow-up resolution through `enrolling` (D-089), and bounded messaging (D-092). PH never Registrar-Accepts/Dismisses, writes scores/releases, alters paper-confirmed final enrollment, or accesses raw artifacts.

## 4. Data-scope rules

1. **Applicant own scope:** one authenticated ApplicantAccount may access only its own admission attempts and approved derived artifacts.
2. **Admission-cycle scope:** operational queries must specify the active or selected cycle; historical cycles are not silently merged.
3. **Office scope:** Registrar and Guidance cross-office visibility is capability-specific, not role-wide.
4. **Program scope:** Program Head sees assigned-program aggregates and accepted Potential rows from application preferences/Guidance pool; protected results/notes require release + applicant-acknowledged current selection or explicit targeted follow-up (D-080). Preference/pool membership alone never grants sensitive record access or messaging.
5. **Session-assignment scope:** Proctor access begins and ends with an active, auditable exam-session assignment.
6. **Purpose limitation:** technical administrators do not gain routine counseling/admission authority merely because they administer the system.
7. **Break-glass:** exceptional access requires explicit reason, short duration, append-only audit event, alert, and post-use review. Super Admin may view the full audit scope, but that does not turn audit visibility into routine access to the underlying business records.
8. **Default deny:** absence of a matrix row is denial.

## 5. Separation-of-duty candidates

- Account/role administrators should not approve their own operational access elevation.
- A counselor who changes scores should not silently finalize/release without traceable authority where institutional workflow requires review.
- Program Head visibility never becomes ordinary admission authority: normal sheet processing requires no PH system action; only a Guidance-routed edge follow-up requires assigned-PH terminal activity before `enrolled` (D-089).
- Super Admin must not edit admission outcomes outside an explicitly approved, audited break-glass recovery action.
- Proctor assignment and use must be separately recorded.

## 6. Required acceptance evidence

For every approved capability row:

- one allowed scenario;
- one denied scenario;
- one cross-scope denial where applicable;
- one revoked/expired-assignment scenario for contextual duties;
- actor, target, scope, reason, and audit-event assertions;
- browser/API behavior with no information leakage.

## 7. Decisions required before promotion

- D-ROLE-01: final base-role catalog and evaluated actors.
- D-ROLE-02: eligible Proctor assignees and assignment/revocation authority.
- D-ROLE-03: Test Administration capability membership/delegation.
- D-ROLE-04: retain or remove Super Admin; define break-glass process.
- D-ROLE-05: **RESOLVED 2026-08-15** — assigned-program metrics, candidate tiers, audit/access-history, and staff-only privacy boundary (D-ROLE-05/D-080/D-090).
- D-SCOPE-01: exact Registrar/Guidance office ownership and shared data.
- D-SCOPE-02: account-creation delegation and role ceiling.
- D-SCOPE-03: audit-view and knowledge-corpus content ownership.
- D-INTAKE-01/D-STATUS-01: public intake/account activation and canonical admission vocabulary.
- D-RELEASE-01/D-DOCUMENT-01/D-INQUIRY-01/D-REPORT-01: release, document, inquiry, and reporting scope reconciliation.
