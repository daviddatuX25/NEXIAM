# Standardized Research Simulation Task Protocol (Appendix N Summary)

This document provides researchers with the complete inventory of the **24 standardized simulation evaluation tasks** defined in Appendix N. During the capstone evaluation study, participants execute these role-specific scenario tasks before completing the 38-item Technology Acceptance Model (TAM) questionnaire.

---

## 1. Evaluation Architecture & Personas

Participants are assigned to one of four stakeholder roles, each operating with specific capabilities and pre-seeded test fixtures:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   STANDARDIZED SIMULATION SCENARIO FLOW                │
├────────────────────────────────────────────────────────────────────────┤
│  1. Participant Briefing: Neutral explanation of task objectives.      │
│  2. Scenario Task Execution: Participant executes role-specific tasks. │
│  3. Task Logging: Facilitator records completion and assistance.       │
│  4. Post-Test Survey: Participant completes role-adapted TAM post-test.│
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Registrar Personnel Tasks (Tasks R1–R5)

- **Target Participants:** 3 Registrar staff members (Lead, Clerk, Support).
- **Default Seed Login:** `registrar.clerk@nexiam.test` / `password`.

| Task ID | Task Description | Target Interface | Verification Criteria |
|---|---|---|---|
| **`TASK-R1`** | **Review New Online Application:** Inspect incoming applicant submission, verify demographic data and attached secondary Form 138. | `/admissions` | Candidate details loaded; requirements checklist reviewed. |
| **`TASK-R2`** | **Request Application Correction:** Flag an unreadable document upload and send an official correction notice to the applicant. | `/admissions/{id}` | Status transitions to `correction_requested`; notice visible on applicant portal. |
| **`TASK-R3`** | **Accept Application for Examination:** Validate complete academic requirements and approve the candidate for ICAT scheduling. | `/admissions/{id}` | Status transitions to `accepted`; candidate added to Guidance scheduling pool. |
| **`TASK-R4`** | **Verify 4-Point Admission Clearance:** Open the eligibility queue and verify applicant entrance criteria. | `/registrar/eligibility` | 4-point checklist verified; clearance sign-off enabled. |
| **`TASK-R5`** | **Log Physical Enrollment-Sheet Handoff:** Confirm physical issuance of pre-enrollment slip to candidate. | `/registrar/eligibility` | Status transitions to terminal `enrolled_handed_off`; audit record committed. |

---

## 3. Guidance Personnel Tasks (Tasks G1–G8)

- **Target Participants:** 2 Guidance personnel (Counselor / Test Administrator, Clerk).
- **Default Seed Login:** `guidance.counselor@nexiam.test` / `password`.

| Task ID | Task Description | Target Interface | Verification Criteria |
|---|---|---|---|
| **`TASK-G1`** | **Create Examination Session:** Schedule a 30-capacity ICAT timeslot in the Audio-Visual Room. | `/examination` | Session created in `draft` status; room conflict check passes. |
| **`TASK-G2`** | **Publish Examination Roster:** Finalize and publish session to open reservations or assign candidates. | `/examination` | Session published; occupancy gauge shows allocated seats. |
| **`TASK-G3`** | **Record Live Session Attendance:** Take candidate attendance (`present` / `absent`) during test administration. | `/exams/monitoring` | Attendance flags saved; session ready for atomic closure. |
| **`TASK-G4`** | **Close Session & Reconcile Roster:** Close exam session; verify present candidates become score-eligible. | `/exams/monitoring` | Session closed; candidates routed to OMR grading pool. |
| **`TASK-G5`** | **Execute OMR Optical Ingestion:** Ingest scanned answer sheet photo; verify corner quad-fitting autocrop. | `/assessment/omr/capture` | Homography rectification completes; bubbles analyzed. |
| **`TASK-G6`** | **Resolve Ambiguous Bubble Review:** Inspect low-confidence mark at `OmrItemReview` desk and commit override. | `/assessment/omr/review` | Optical snippet inspected; manual decision signed to audit log. |
| **`TASK-G7`** | **Review Algorithmic Course Match:** Evaluate TabFM compatibility index and Honest Hexagon radar. | `/consultation/applicants/{id}` | 6-axis radar visualizes student stanines against cutoffs. |
| **`TASK-G8`** | **Commit Consultation Decision:** Submit 5-card decision modal endorsing candidate to degree program. | `/consultation/applicants/{id}` | Atomic decision receipt committed; departmental quota updated. |

---

## 4. Academic Program Head Tasks (Tasks A1–A4)

- **Target Participants:** 3 Deans / Department Chairs (CAS, CBME, CTE).
- **Default Seed Login:** `program.head.cbme@nexiam.test` / `password`.

| Task ID | Task Description | Target Interface | Verification Criteria |
|---|---|---|---|
| **`TASK-A1`** | **Inspect Department Program Quotas:** Review target enrollment capacity versus confirmed endorsements. | `/program-head/dashboard` | Live quota gauges render with capacity and available slots. |
| **`TASK-A2`** | **Review Referred Candidate Dossier:** Access profile of applicant referred for academic interview. | `/program-head/applicants` | Candidate ICAT subtests, SHS strand, and interview referral notes loaded. |
| **`TASK-A3`** | **Record Academic Interview Assessment:** Input departmental interview ratings and advising remarks. | `/consultation/applicants/{id}` | Interview remarks saved; case updated for final clearance. |
| **`TASK-A4`** | **Bilateral Case Coordination:** Reply to a Guidance Counselor inquiry via the internal office messaging rail. | `/consultation/applicants/{id}` | Internal staff thread message sent without applicant visibility. |

---

## 5. First-Year Student Evaluator Tasks (Tasks S1–S7)

- **Target Participants:** First-year college students acting as simulated candidates (planned N=255).
- **Default Test Persona:** Synthetic applicant credentials (Access Key `PORT-DEFL` / Birthdate `2003-01-01`).

| Task ID | Task Description | Target Interface | Verification Criteria |
|---|---|---|---|
| **`TASK-S1`** | **Submit Online Admission Application:** Complete public intake form with PSGC address and privacy consent. | `/apply` | Access key issued; confirmation screen rendered. |
| **`TASK-S2`** | **Authenticate to Applicant Portal:** Log in using issued access key and birthdate. | `/applicant/login` | Portal home loads showing active application milestone. |
| **`TASK-S3`** | **Review Examination Schedule:** Inspect scheduled test date, room assignment, and exam day reminders. | `/applicant/portal` | Room, date, and QR exam slip visible. |
| **`TASK-S4`** | **View Examination Result Sheet:** Access digital ICAT result slip after official scoring release. | `/applicant/portal` | Verified subtest scores and qualifying stanines displayed. |
| **`TASK-S5`** | **Inspect Program Advising Guidance:** Review endorsed course selection and qualification notice. | `/applicant/admission-support` | Endorsed degree program and next steps displayed clearly. |
| **`TASK-S6`** | **Send Bilateral Support Inquiry:** Post an inquiry to the admissions desk regarding requirements. | `/applicant/admission-support` | Message posted; appears in Registrar/Guidance inquiry desk. |
| **`TASK-S7`** | **Verify Admission Clearance Milestone:** Verify final admission clearance notice for campus enrollment. | `/applicant/portal` | Pre-enrollment authorization pass displayed for campus presentation. |
