# Manuscript Improvements Design: Quota-Aware Triage and Student Choice Dynamics

## 1. Overview & Context

This design outlines the narrative framing and technical documentation updates for the SecureCAT capstone manuscript. The objective is to elevate the "Background of the Study" and related sections to address real-world enrollment pressures, dynamic program quotas, student decision-making dynamics, and machine learning extensibility.

Preliminary observations from the Ilocos Sur Polytechnic State College (ISPSC) Tagudin Campus Registrar and Guidance Offices for SY 2026-2027 show that:
1. State university enrollment pressures (exacerbated by RA 10931 free tuition) force hard capacity limits (quotas) on popular courses due to room utilization, faculty availability, and laboratory resources.
2. The Guidance Office leads the routing process but must coordinate dynamically with department heads to prevent over-enrollment or mismatching students to full courses.
3. The admission process serves as a critical student acquisition gate. When the experience is slow or archaic, students who have taken examinations at multiple competing schools are more likely to enroll elsewhere.
4. Counselors require an extensible machine learning interface that can be updated with additional applicant metrics (subject-specific grades in Math/Science, GWA) to continuously improve placement algorithms and feed the RAG-based AI Companion.

---

## 2. Proposed Narrative Framings

### A. The Student Choice & Enrollment Dilemma (Background P1)
* **Argument:** Admissions is not just a database record task or an administrative hurdle. The efficiency and quality of the admission experience directly impact the student's decision to enroll. Since applicants often take exams at multiple institutions, a sluggish, paper-reliant process with a 1–2 week score release lag creates anxiety and alienates students, driving them to choose more responsive competing colleges.
* **Placement:** `ch1_bg_of_the_study` (Paragraph 1) - injected as a customer-experience/retention argument.

### B. Dynamic Quota Realities & Inter-Office Coordination (Background P4)
* **Argument:** SUC capacity constraints are structural. Dynamic course quotas are established by Program Heads based on room/faculty constraints and coordinated with the Guidance Office as the gatekeeper. Guidance cannot suggest programs whose quotas are already exhausted, nor can they recommend courses where the student's test score fails the prerequisites. Currently, tracking these available slots and cross-referencing them with paper scores during live consultations is done manually, creating operational blind spots and placement mismatches.
* **Placement:** `ch1_bg_of_the_study` (Paragraph 4) - integrated with local ISPSC Tagudin observations.

### C. Live, Extensible ML Triage vs. Static Research (Background P5)
* **Argument:** Existing literature treats K-Means/ML models as retrospective research tools run on static databases post-hoc. SecureCAT bridges this gap by embedding a live K-Means clustering algorithm at the point of consultation. Crucially, the system provides an *extensible data collection schema* allowing counselors to incrementally add secondary features (Math, Science, English subject grades, GWA) to the profile. This dynamic collection refines the recommender engine over time and generates high-fidelity local datasets to train the AI Companion.
* **Placement:** `ch1_bg_of_the_study` (Paragraph 5) - research gap and ML positioning.

---

## 3. Reference Data Preservation

A new reference document will be saved under `capstone/references/integration/REGISTRAR_GUIDANCE_2026_ADMISSIONS_OBSERVATIONS.md` to capture these findings as persistent qualitative observations.

---

## 4. Document Updates Map

| Component | Target File | Action |
|-----------|-------------|--------|
| **Observations** | `capstone/references/integration/REGISTRAR_GUIDANCE_2026_ADMISSIONS_OBSERVATIONS.md` | [NEW] Document the SY 2026-2027 registrar and guidance observations, room utilization constraints, and dynamic quota coordination. |
| **Background** | `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md` | [MODIFY] Revise Paragraphs 1, 4, and 5 of the Background to inject the new narrative threads. |
| **Objectives** | `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md` | [MODIFY] Update Objective 2 to specify that the K-Means course triage module is live, quota-integrated, and extensible. |
| **Scope** | `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md` | [MODIFY] Update Scope & Delimitations to cover dynamic quota limits and extensible secondary metrics (subject grades, GWA). |
| **Significance** | `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md` | [MODIFY] Update the Guidance Office and Future Researchers beneficiary paragraphs to reflect the quota matching relief and extensible data model benefits. |
