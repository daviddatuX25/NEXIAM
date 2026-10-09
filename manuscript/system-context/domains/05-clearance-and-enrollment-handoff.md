# Domain 05: Admission Clearance & Enrollment Handoff

## 1. Domain Responsibility & Academic Value

The Admission Clearance & Enrollment Handoff domain manages the final administrative transition within NEXIAM. It provides the Registrar with attributable tools to verify that an applicant has satisfied all academic, testing, and counselling requirements before issuing official pre-enrollment credentials.

In the capstone research, this domain directly addresses problem statements **FM2** (Fragmented and delayed applicant clearance status) and **FM5** (Lack of persistent, attributable tracking between campus offices).

---

## 2. The 4-Point Eligibility Verification Model

Before an applicant can receive admission clearance, the system evaluates four independent institutional checkpoints:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   4-POINT ADMISSION ELIGIBILITY GATES                  │
├────────────────────────────────────────────────────────────────────────┤
│  [1. Valid Identity & Document Requirements]                           │
│  Verified birthdate, Form 138 (report card), Certificate of Good Moral, │
│  and PSA birth certificate confirmed by Registrar intake staff.       │
│                                                                        │
│  [2. Completed Examination & Finalized Scores]                         │
│  Verified attendance in an authorized ICAT session with finalized     │
│  subtest scores committed by a Test Administrator.                     │
│                                                                        │
│  [3. Approved Program Endorsement & Consultation Receipt]              │
│  Recorded consultation outcome signed by Guidance Counselor confirming │
│  qualification for the designated degree program and available quota.  │
│                                                                        │
│  [4. Resolved Departmental Follow-Ups (If Applicable)]                 │
│  Satisfactory completion of Program Head interview or physical test    │
│  requirements for specialized degrees.                                │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Human Actor Journeys & Interaction Flow

### A. Registrar Eligibility Queue (`/registrar/eligibility`)
- Authorized staff (`registrar.clearance.confirm`, `registrar.applications.view`) inspect candidates whose status has reached **`ready_for_clearance`**.
- The interface displays the candidate's verified document checklist, stanine scores, endorsed program, and consultation receipt hash.

### B. Admission Clearance Confirmation
- **Action:** `ConfirmAdmissionClearance`.
- **Authority:** Registrar Admin or Registrar Clerk.
- **Outcome:** The candidate's status transitions to **`cleared`**. An immutable clearance audit record is committed, locking the candidate's program allocation.

### C. Physical Enrollment-Sheet Handoff
- **Action:** `RecordEnrollmentSheetIssue`.
- **Interaction:** The candidate presents their physical documents at the Registrar counter. Staff physically hand over the college's official Enrollment Authorization Sheet and record the physical handoff in NEXIAM.
- **Lifecycle Termination:** This action marks the **terminal state of NEXIAM's workflow**.

---

## 4. Key Actions & CQRS Queries

| Class | Type | Responsibility & Invariants |
|---|---|---|
| `ConfirmAdmissionClearance` | Domain Action | Verifies 4-point gates; transitions attempt to `cleared`; commits clearance receipt. |
| `RecordEnrollmentSheetIssue`| Domain Action | Records physical paper issuance; transitions attempt to terminal `enrolled_handed_off`. |
| `ResolveProgramFollowUp` | Domain Action | Records Program Head interview completion or academic prerequisite fulfillment. |
| `ReverseAdmissionClearance` | Domain Action | Reopens a cleared case; **Registrar Admin only**; requires mandatory justifying audit note. |
| `EnrollmentEligibilityQuery`| CQRS Query | Returns real-time filtered roster of candidates meeting all clearance prerequisites. |

---

## 5. Strict System Boundary vs University Matriculation

A critical finding from `SRC-OMP-01` and `SRC-SPEC-02` is the explicit separation between **Admission Clearance** and **Institutional Enrollment**:

| Lifecycle Stage | System Responsible | Operational Meaning |
|---|---|---|
| **Admission Clearance** | **NEXIAM** | Confirmation that the applicant has met all entrance criteria for their specific program. |
| **Enrollment-Sheet Handoff** | **NEXIAM** | Physical delivery of the college pre-enrollment pass to the candidate. |
| **Course Subject Enlistment** | External SIS | Registration of class schedules, subjects, and professor assignments. |
| **Tuition & Fee Assessment** | External Accounting | Cashier assessment and official receipt issuance. |
| **Student ID & Matriculation** | External Campus Registrar | Formal enrollment into the university student body. |

**Academic Writing Directive:** The manuscript must *never* claim that NEXIAM enrolls students into the college or acts as a Student Information System (SIS). NEXIAM is strictly an *Admission, Assessment, and Consultation Management System*.

---

## 6. Implementation & Verification Status

- **Status:** `[IMPLEMENTED-TESTED]` & `[OBSERVED-EVIDENCE]`
- **Backend Evidence:**
  - `admission-clearance-boundary-and-workflow-cohesion` task suite: 10 files / 68 tests / 771 assertions passing in Compose (`tests/Feature/Admission/AdmissionFlowTest.php`).
  - Idempotency and transaction safety verified under concurrent PostgreSQL locking.
- **Frontend / Browser Evidence:**
  - Registrar eligibility queue renders at `/registrar/eligibility` with responsive card and table views.
  - Clearance confirmation modal verified with explicit error states for incomplete prerequisites.
