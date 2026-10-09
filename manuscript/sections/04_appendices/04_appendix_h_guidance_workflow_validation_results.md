# Appendix H: Guidance Office Admission Workflow Validation Instrument and Empirical Transcript

**ILOCOS SUR POLYTECHNIC STATE COLLEGE**  
**Tagudin Campus, Tagudin, Ilocos Sur**  
**Guidance and Counseling Office / College of Information Technology**

---

## 1. Instrument Overview

The Guidance Office Admission Workflow Validation inquiry was conducted in two sequential rounds during the Requirements Planning phase to establish the empirical operational baseline of entrance examination administration, scoring, and academic counseling at ISPSC Tagudin Campus.

* **Round 1 (Semi-Structured Workflow Questionnaire):** Administered on 14 June 2026 to Guidance Office operational personnel ($n = 2$: the Guidance Counselor and an Administrative Staff member), capturing scoring durations, staffing allocations, error rates, mental strain, and student consultation volumes.
* **Round 2 (Structured Follow-Up Interview):** Conducted on 25 June 2026 with the Guidance Counselor / Test Administrator, investigating examination proctoring constraints, room scheduling channels, past capstone clustering adoption barriers, and departmental quota communication.

---

## 2. Round 1: Questionnaire Findings and Baseline Metrics

### Section 1: Examination Scoring Operational Burden
* **Scoring Methodology:** Manual stencil scoring using physical clear-plastic overlays aligned over six-part ICAT test booklets.
* **Duration for Scoring One Full Cycle:** **5+ days** of continuous manual grading.
* **Personnel Dedicated to Scoring:** **2 to 3 full-time staff members** removed from counseling duties during testing weeks.
* **Frequent Scoring Errors Encountered:** Illegible pencil marks, faint bubble fills, double-marked answer choices, and manual counting errors when tallying correct responses across the six sub-tests.
* **Mental Fatigue Rating:** Rated **4 out of 5** by the Guidance Counselor, highlighting substantial cognitive exhaustion during mass scoring periods.

### Section 2: Student Consultation and Result Release
* **Peak Daily Consultation Volume:** **70 to 80 student visits per day** during official result release weeks.
* **Consultation Modality:** Individual face-to-face sessions where test results are unsealed, verbally explained, and manually signed off.
* **Average Duration per Consultation:** Approximately **2 to 5 minutes** during peak queue rushes.
* **Data Sources Referenced During Advising:** Physical entrance examination score sheets, high school report cards, departmental quota sheets, and applicant verbal statements.
* **Catchment Municipalities Observed by Staff:** Santa Cruz, Bangar, Sudipen, Tagudin, Santa Lucia, Suyo, and Alilem.

---

## 3. Round 2: Counselor Interview Transcript and Operational Decisions

The structured follow-up interview on 25 June 2026 recorded institutional stances and operational policies governing platform engineering:

### Item 1: Proctoring Governance and Duty Delegation
* *Question:* Regarding examination security, should proctoring be restricted strictly to Guidance personnel?
* *Counselor Response:* By baseline institutional policy, only the Guidance Counselor or licensed Psychometrician conducts formal psychological testing. However, during large applicant surges, the office must allow trained institutional staff to assist under supervision.
* *System Engineering Response:* Confirms the necessity of the **Proctor Duty Overlay (FR-03)**, a session-scoped authorization grant that allows eligible staff to monitor rooms and record attendance without conferring permanent administrative rights.

### Item 2: Batch Scheduling and Inter-Office Communication
* *Question:* How are examination batches scheduled and coordinated across offices?
* *Counselor Response:* Batches are scheduled around room availability and pooled applicant volumes. Coordination with fellow staff and administration relies on online messaging, with occasional miscommunications and short notices occurring.
* *System Engineering Response:* Confirms **FR-03** and **FR-13**, replacing disconnected chat groups with room-constrained session management and automated portal announcements.

### Item 3: Institutional Adoption Barriers of Past Clustering Research
* *Question:* Are you currently utilizing the machine learning clustering system developed in the previous capstone study (Yukee et al., 2025)?
* *Counselor Response:* **No.** The previous system was not utilized because it was *"not integrated into the workflow"* and functioned as a detached, retrospective standalone tool rather than a live operational pipeline.
* *System Engineering Response:* Validates the adoption of **in-context TabPFN triage embedded directly within the counselor's consultation interface**, ensuring that decision support is available during live advising without requiring separate data exports.

### Item 4: Departmental Quota Visibility
* *Question:* Where or how do you obtain departmental quota information during advising?
* *Counselor Response:* Quota allocations are coordinated informally through Guidance administration and academic deans. Live remaining capacities are not visible in real time during student interviews.
* *System Engineering Response:* Confirms **FR-08**, establishing live capacity utilization bars on the consultation workbench to prevent counselors from recommending full academic sections.
