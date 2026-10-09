# Capstone Adviser Comprehensive Checkpoint Briefing Agenda

**ILOCOS SUR POLYTECHNIC STATE COLLEGE**  
**Tagudin Campus, Tagudin, Ilocos Sur**  
**College of Information Technology**

---

### ADVISER CONSULTATION AND MILESTONE REVIEW AGENDA

**To:** Dr. Zeus Vincent Mendoza, Capstone Project Adviser  
**From:** David D. Sarmiento, Jaypee G. Pagaduan, C. M. Lopez  
**Project Title:** NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus  
**Target Completion Deadline:** Monday  

---

#### 1. Executive Summary of Progress

The development team has completed the systematic review, structural alignment, and empirical grounding of the capstone manuscript following the institutional benchmark of ISPSC Tagudin (Yukee et al., 2025). 

The platform architecture unites the Guidance Office, Registrar Office, Academic Program Departments, and applicant examinees into a secure, role-governed web application. The core deliverables are compiled into the master manuscript across Front Matter, Chapters 1 through 3, References (40 APA 7th edition sources), and Appendices A through N.

---

#### 2. Key Agenda Items for Review and Consultation

### Item 1: The Seven-Stage Operational Pipeline and Architecture Models
* **Presentation:** The platform organizes all administrative workflows across seven sequential stages: (1) Configuration and Setup, (2) Admission and Application Intake, (3) Examination Scheduling, (4) Examination and Testing Day, (5) Result Preparation, (6) Consultation and Course Triage, and (7) Post-Admission Handoff and Reporting.
* **Diagrammatic Alignment:**
  * **Figure 1 (IPO Diagram):** Modernized to clearly distinguish Operational Artifacts (schedules, scores, pre-enrollment lists) from Analytics and Reports (consultation dashboard, post-admission reports).
  * **DFD Level 0 (Context):** Maps the central engine against six external stakeholder entities.
  * **DFD Level 1 (Decomposition):** Decomposes operations into eleven processes, eight data stores, and verified data flows.
  * **Use Case Diagram:** Maps seven operational actor groups across eight use cases.
* **Adviser Feedback Requested:** Verification of diagram completeness and pipeline stage definitions.

### Item 2: Empirical Grounding of Baseline Process Gaps (Specific Objective 1)
* **Presentation:** Specific Objective 1 is grounded in field questionnaires (Guidance, $n=2$), follow-up counselor interviews, Program Head surveys, and applicant journey surveys ($n=22$).
* **Key Empirical Metrics:**
  * 60% of applicants reside outside Tagudin, facing significant provincial travel expenses.
  * 70% relied exclusively on informal Facebook posts for examination schedules.
  * 70% experienced document verification uncertainty due to lack of digital receipts.
  * 60% waited 30 minutes to over half a day in physical consultation queues.
  * 20% experienced forced program shifts at enrollment due to full course quotas.
* **Adviser Feedback Requested:** Confirmation that these empirical metrics sufficiently substantiate Failure Modes 1 through 5.

### Item 3: TAM Acceptance Evaluation Sample and Research Rigor (Specific Objective 3)
* **Presentation:** User acceptance is evaluated through a role-adapted Technology Acceptance Model (TAM) post-test questionnaire measuring Perceived Usefulness and Perceived Ease of Use across $n = 260$ participants:
  * Registrar Personnel: $n = 3$ (Complete purposive census)
  * Guidance Personnel: $n = 2$ (Complete purposive census)
  * First-Year Student Examinees: $n = 255$ (Slovin sample from $N = 700$ at $e = 0.05$)
* **Methodological Grounding:**
  * *Simulated Work Tasks:* Grounded in Davis (1989), Borlund (2000), Carroll (2000), and Kushniruk and Patel (2004), establishing valid pre-implementation evaluation outside the live intake window.
  * *Ecological Validity of Proxy Students:* Grounded in Dumas and Redish (1999), noting that first-year students completed the manual ICAT process months earlier, providing a vivid comparative baseline.
  * *Asymmetry and Disaggregated Defense:* To prevent 255 students from drowning out administrative feedback, results are reported in independent, disaggregated rows in Chapter 3 (Tables 3.5 and 3.6).
* **Adviser Feedback Requested:** Confirmation of the $n = 260$ sampling distribution and disaggregated reporting structure.

### Item 4: Strategic Proposal on Fine-Graining Specific Objective 2
* **Discussion Point:** In traditional capstone manuscripts, Specific Objective 2 is framed as a single broad objective: *"Determine system requirements and develop the platform using RAD."*
* **Proposal for Consideration:** To highlight the engineering contributions (computer-vision OMR, TabPFN tabular foundation model adapter, and cryptographic audit chaining), should SO2 be formally partitioned into:
  * **SO2a (Architecture and Blueprints):** Derivation of functional requirements (FR-01 to FR-13), relational RBAC schema, and architectural models.
  * **SO2b (Component and Pipeline Construction):** Engineering the modular monolith, OMR scanner, TabPFN triage adapter, and result release pipeline across the seven stages.
  * **SO2c (System Verification and Relational Integrity):** Automated test suite assertions, homography validation, and cryptographically chained audit logging.
* **Adviser Decision:** [ ] Maintain single unified SO2 | [ ] Adopt partitioned SO2a/SO2b/SO2c

---

#### 3. Adviser Guidance and Endorsement Notes

**General Remarks and Directives:**  
_________________________________________________________________________________________________  
_________________________________________________________________________________________________  
_________________________________________________________________________________________________  

**Adviser Signature:** _______________________________________  
**Dr. Zeus Vincent Mendoza**, Capstone Adviser  
Date: ________________________
