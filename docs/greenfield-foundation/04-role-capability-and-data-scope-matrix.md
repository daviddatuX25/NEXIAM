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
| ACT-03 | Registrar Administrator | Base staff role | Yes | Registrar oversight, configuration, approvals, and delegated accounts | D-ROLE-01 |
| ACT-04 | Guidance Counselor | Base staff role | Yes | Counseling, scoring, release, and approved assessment capabilities | D-ROLE-01 |
| ACT-05 | Guidance Clerk | Base staff role | Yes | Guidance operational administration and delegated staff authority | D-ROLE-01 |
| ACT-06 | Program Head | Base staff role | Yes | Read-only, program-scoped aggregate visibility by default | D-ROLE-01/D-ROLE-05 |
| ACT-07 | Staffing/HR Administrator | Personnel lifecycle role | No separate product evaluation | Create/deactivate approved permanent and support-staff accounts and role records; no operational applicant/admission authority; cannot grant Test Administrator/Proctor | D-ROLE-01/D-017/D-019 |
| ACT-08 | Super Admin | System oversight account | No | BREAK-GLASS only if retained; every use reasoned and audited | D-ROLE-04 |
| ACT-09 | Registrar Support Staff | Subordinate/temporary office role | Yes | Registrar Clerk responsibilities by default; Registrar scope only | D-ROLE-01/D-020 |
| ACT-10 | Guidance Support Staff | Subordinate/temporary office role | Yes | Guidance Clerk responsibilities by default; Test Administrator/Proctor only through explicit overlays | D-ROLE-01/D-015/D-016/D-020 |
| DUTY-01 | Proctor | Temporary contextual duty | No separate role | ASSIGNED examination session only | D-ROLE-02 |
| CAPSET-01 | Test Administration | Explicit capability set | No separate role | Assigned only to approved Guidance actors | D-ROLE-03 |

## 3. Proposed target capability matrix

Abbreviations: APP Applicant, RC Registrar Clerk, RA Registrar Admin, GC Guidance Counselor, GCL Guidance Clerk, PH Program Head, HR Staffing/HR Admin, SA Super Admin. Registrar Support Staff and Guidance Support Staff inherit RC/GCL by default and receive only the explicit overlays in §3.1.

| Capability ID | Capability | APP | RC | RA | GC | GCL | PH | HR | SA | Status/evidence |
|---|---|---|---|---|---|---|---|---|---|---|
| CAP-001 | Create admission attempt | PUBLIC/OWN | DENY | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Legacy permits public and staff intake; target pre-account identity/account activation follows D-073 |
| CAP-002 | View admission attempt | OWN | OFFICE (admission fields) | OFFICE (admission fields) | OFFICE/REVIEW | OFFICE/REVIEW | PROGRAM approved record view | DENY | BREAK-GLASS | Registrar record access excludes converted result/remark evidence; Program Head may view authorized applicant records within program scope; raw sensitive artifacts remain separately restricted |
| CAP-003 | Correct application data before acceptance | OWN under guard | OFFICE | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Own-edit freeze after assignment is useful legacy intent |
| CAP-004 | Accept/dismiss application | DENY | REVIEW | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Legacy allows broad roles; target delegation needs decision |
| CAP-005 | Reopen/archive/correct accepted attempt | DENY | DENY | OFFICE with reason | DENY | REVIEW | DENY | DENY | BREAK-GLASS | Must be non-destructive |
| CAP-006 | Import applications | DENY | OFFICE | OFFICE | DENY | DENY | DENY | DENY | BREAK-GLASS | Built; requires idempotency and error report |
| CAP-007 | Configure academic cycles/application windows | DENY | DENY | ALLOW | DENY | DENY | REVIEW | DENY | BREAK-GLASS | Ownership requires decision; HR has no academic-cycle authority |
| CAP-008 | Configure programs/courses/rooms | DENY | DENY | ALLOW | DENY | DENY | PROGRAM read | DENY | BREAK-GLASS | Reference-data owner requires validation; HR has no academic-reference authority |
| CAP-009 | Create exam session | DENY | DENY | REVIEW | OFFICE | OFFICE | PROGRAM read | DENY | BREAK-GLASS | Legacy grants Registrar Administrator; proposed Guidance ownership is unresolved under D-038 |
| CAP-010 | Assign applicants to session | DENY | DENY | REVIEW | OFFICE | OFFICE | DENY | DENY | BREAK-GLASS | Transactional eligibility/capacity required |
| CAP-011 | Publish/unpublish/cancel/reopen session | DENY | DENY | REVIEW | OFFICE | OFFICE | PROGRAM read | DENY | BREAK-GLASS | Reversal requires reason/audit |
| CAP-012 | Assign/revoke Proctor duty | DENY | DENY | DENY | REVIEW | OFFICE | DENY | DENY | BREAK-GLASS | Proposed Guidance Admin owner; stakeholder decision required |
| CAP-013 | View assigned proctor roster | DENY | DENY | REVIEW | ASSIGNED/office | ASSIGNED/office | DENY | DENY | BREAK-GLASS | Proctor scope is session assignment only |
| CAP-014 | Start/monitor/close assigned session | DENY | DENY | DENY | ASSIGNED | ASSIGNED | DENY | DENY | BREAK-GLASS | Requires active duty and legal time/state window |
| CAP-015 | Record attendance/submission | DENY | DENY | DENY | ASSIGNED | ASSIGNED | DENY | DENY | BREAK-GLASS | Idempotent session command |
| CAP-016 | Configure aptitude areas/rubrics | DENY | DENY | DENY | OFFICE | OFFICE | PROGRAM read | DENY | BREAK-GLASS | Guidance-owned proposed default |
| CAP-017 | Import/enter/correct scores | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Active legacy routes grant Super Admin/Test Administrator; target counselor/admin capability and corrections require D-076 |
| CAP-018 | Finalize/reopen grading | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Completeness and version/reason required |
| CAP-019 | Run OMR batch/review uncertainty | DENY | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | DENY | DENY | BREAK-GLASS | Not built; spike-gated |
| CAP-020 | Record counseling/triage decision | DENY | DENY | DENY | OFFICE | REVIEW | DENY | DENY | BREAK-GLASS | Active release route bypasses counselor-oriented request; target human author/release separation follows D-077 |
| CAP-021 | Release/unrelease result | DENY | DENY | DENY | OFFICE | OFFICE | DENY | DENY | BREAK-GLASS | Exact Guidance authority needs confirmation |
| CAP-022 | View released own result/schedule | OWN | DENY | OFFICE operational view | OFFICE | OFFICE | PROGRAM aggregate only | DENY | BREAK-GLASS | Applicant own-data boundary |
| CAP-023 | Preview/generate/distribute admission/result documents | OWN released/approved docs | OFFICE intake docs | OFFICE intake docs | OFFICE result preview/release | OFFICE result preview/release | PROGRAM-scoped result/remark sheet view | DENY | BREAK-GLASS | Program Head may view authorized converted result evidence; raw answer sheets and confidential conversion tables remain restricted |
| CAP-024 | View Program Head dashboard and authorized applicant detail | DENY | DENY | REVIEW aggregate | REVIEW aggregate | REVIEW aggregate | PROGRAM aggregate + approved record detail | DENY | BREAK-GLASS | Program scope, field visibility, and individual-data audit follow D-080; no score/release/admission writes |
| CAP-025 | Modify program quota | DENY | DENY | REVIEW | DENY | DENY | REVIEW | DENY | BREAK-GLASS | Default is no Program Head write authority |
| CAP-026 | Use institutional RAG | OWN/authorized | OFFICE/authorized | OFFICE/authorized | OFFICE/authorized | OFFICE/authorized | PROGRAM/public corpus | DENY | BREAK-GLASS | Policy-corpus access and own released-data access are separate gates under D-079; technical corpus administration is not an HR function |
| CAP-027 | Retrieve own data through assistant | OWN released/authorized data | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | No cross-applicant retrieval; own score/result/consultation visibility follows release authority |
| CAP-028 | Send/receive bounded inquiry thread | OWN confirmed thread | OFFICE assigned | OFFICE assigned | OFFICE assigned | OFFICE assigned | REVIEW | DENY | BREAK-GLASS | Not built; conditional workflow requires participant/routing/retention/SLA decisions and applicant confirmation |
| CAP-029 | Manage users in own office | DENY | DENY | OFFICE | DENY | OFFICE | DENY | ALLOW personnel | BREAK-GLASS | HR manages approved permanent personnel lifecycle; offices manage subordinate support staff |
| CAP-030 | Create/assign approved permanent and support-staff roles | DENY | DENY | OFFICE-limited | DENY | OFFICE-limited | DENY | ALLOW approved permanent and support-staff roles | BREAK-GLASS | HR may create/manage approved personnel accounts; Registrar and Guidance may create their own support staff; HR cannot grant Test Administrator/Proctor capability |
| CAP-031 | Manage system/integration settings | DENY | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | Technical support is Super Admin break-glass only |
| CAP-032 | Manage knowledge corpus | DENY | DENY | REVIEW | REVIEW | REVIEW | DENY | DENY | BREAK-GLASS | Content owner versus technical operator must be split |
| CAP-033 | View/export audit/report evidence | OWN access history optional | DENY | OFFICE/REVIEW | DENY | OFFICE/REVIEW | PROGRAM aggregate/REVIEW | personnel audit only | BREAK-GLASS | Every protected view/export is itself scoped and audited; Program Head reporting follows D-080 |
| CAP-034 | Verify audit chain/checkpoints | DENY | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | Break-glass technical security operation |
| CAP-035 | Override protected transition/access | DENY | DENY | DENY | DENY | DENY | DENY | DENY | BREAK-GLASS | Must require reason, time bound, alert, and review |
| CAP-036 | View authorized converted result/remark sheet and per-domain scores | OWN released | DENY | DENY | OFFICE/CAPSET | OFFICE/CAPSET | PROGRAM approved record | DENY | BREAK-GLASS | Raw answer sheets, bubble scans, and confidential conversion table are denied; every protected view emits an audit event; applicant-facing access-history is a later presentation/notification policy |

## 3.1 Role inheritance and capability overlays

- `registrar_support_staff` inherits Registrar Clerk responsibilities by default. It does not inherit Registrar Administrator decision, role-management, or cross-office authority.
- `guidance_support_staff` inherits Guidance Clerk responsibilities by default. It does not inherit Test Administrator or Proctor authority unless explicitly granted/assigned.
- HR may create both permanent staff and support-staff accounts, but account creation does not grant office capabilities beyond the approved role ceiling.
- Guidance Counselor has the Test Administrator capability inherently. Guidance Clerk and Guidance Support Staff may receive it only through explicit Guidance delegation with grant/revoke dates and audit evidence.
- Proctor is a session-scoped duty overlay. It is assigned only to an eligible staff account and grants only the assigned ExamSession operations.
- Test Administrator capability covers sensitive exam/test-specific operations: OMR intake/review, grading-session operation, score entry/import/correction within the approved lifecycle, result preparation/printing, release coordination where granted, consultation scheduling, and Proctor assignment. It does not grant HR, Registrar, or Program Head authority.
- Program Head access is read-only and program-scoped. It includes approved converted result evidence and consultation/projection data but not raw answer sheets, bubble scans, or the confidential conversion table.

## 4. Data-scope rules

1. **Applicant own scope:** one authenticated ApplicantAccount may access only its own admission attempts and approved derived artifacts.
2. **Admission-cycle scope:** operational queries must specify the active or selected cycle; historical cycles are not silently merged.
3. **Office scope:** Registrar and Guidance cross-office visibility is capability-specific, not role-wide.
4. **Program scope:** Program Head sees only assigned program projection(s) and approved applicant result evidence, not all applicants, raw answer sheets, bubble scans, or confidential conversion tables.
5. **Session-assignment scope:** Proctor access begins and ends with an active, auditable exam-session assignment.
6. **Purpose limitation:** technical administrators do not gain routine counseling/admission authority merely because they administer the system.
7. **Break-glass:** exceptional access requires explicit reason, short duration, append-only audit event, and post-use review.
8. **Default deny:** absence of a matrix row is denial.

## 5. Separation-of-duty candidates

- Account/role administrators should not approve their own operational access elevation.
- A counselor who changes scores should not silently finalize/release without traceable authority where institutional workflow requires review.
- Program Head recommendation/visibility must not become autonomous admission authority.
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
- D-ROLE-05: Program Head raw-data, aggregate, audit, and quota authority.
- D-SCOPE-01: exact Registrar/Guidance office ownership and shared data.
- D-SCOPE-02: account-creation delegation and role ceiling.
- D-SCOPE-03: audit-view and knowledge-corpus content ownership.
- D-INTAKE-01/D-STATUS-01: public intake/account activation and canonical admission vocabulary.
- D-RELEASE-01/D-DOCUMENT-01/D-INQUIRY-01/D-REPORT-01: release, document, inquiry, and reporting scope reconciliation.
