# Research Method & Objective Traceability Map

This document bridges the capstone's academic research methodology with NEXIAM's technical system modules. It maps each Specific Objective (SO) to its theoretical framework, data gathering instruments, respondent populations, and corresponding software implementations.

---

## 1. Methodological Framework: RAD & TAM

The study employs a **Descriptive-Developmental Research Design** (Olipas, 2020; Melendres & Aranda, 2024), integrating:
1. **Rapid Application Development (RAD)** (Martin, 1991; Pressman & Maxim, 2020) for rapid iterative software construction.
2. **Technology Acceptance Model (TAM)** (Davis, 1989) for quantitative user acceptance evaluation across two core constructs: **Perceived Usefulness (PU)** and **Perceived Ease of Use (PEOU)**.

---

## 2. Objective-by-Objective Traceability Matrix

### Specific Objective 1 (SO1): Process Analysis & Bottleneck Identification
> *"To analyze the existing admission, examination, and enrollment-recommendation processes of ISPSC Tagudin Campus to identify operational bottlenecks and user pain points."*

- **RAD Phase:** Phase 1 — Requirements Planning.
- **Data Gathering Instruments:**
  - Guidance Office Admission Workflow Validation Protocol (Round 1 Questionnaire & Round 2 Interview).
  - Program Head Enrollment Operations Survey (Round 1).
  - ISPSC Tagudin Campus Admission & Testing Journey Survey (n=22 baseline applicants).
- **Core Findings (Baseline Failure Modes):**
  - **FM1 (Scoring Turnaround):** Manual scoring of 1,000+ paper answer sheets using physical plastic stencils takes weeks and delays score release.
  - **FM2 (Applicant Information Gap):** Applicants rely on unofficial Facebook posts or campus bulletin boards to check exam dates and document acceptance.
  - **FM3 (Session Coordination Friction):** Overcrowded exam rooms, schedule clashes, and lack of real-time attendance rosters.
  - **FM4 (Consultation Queues):** Long physical lines outside Guidance Office; subjective course advising without quota visibility.
  - **FM5 (Tracking Persistence):** Paper endorsement slips lost between Guidance and Registrar; lack of cross-office audit trails.
- **Academic Visual Evidence:** Figures 3.1 & 3.2 in Chapter 3; Appendices G, H, and I.

---

### Specific Objective 2 (SO2): System Design & Rapid Construction
> *"To design and develop NEXIAM using Rapid Application Development (RAD), integrating computer-vision OMR, capability-based access control, and decision support."*

- **RAD Phases:** Phase 2 (User Design) & Phase 3 (Rapid Construction).
- **Architectural Reference:** Greenfield Foundation suite (`GF-01` to `GF-24`) and Promoted PRD (`docs/prd.md`).
- **Functional Module Mapping:**

| Baseline Issue | Corresponding NEXIAM Functional Domain | Primary Software Artifacts | Verification Status |
|---|---|---|---|
| **FM1 (Scoring)** | Domain 03: Assessment Scoring & OMR | Docker CV Worker, `OmrItemReview`, `EvaluateItemResponses` | `[IMPLEMENTED-TESTED]` (48 challenge tests) |
| **FM2 (Intake)** | Domain 01: Admissions & Intake | `/apply`, PSGC Selector, `ApplicantPortalProjection` | `[IMPLEMENTED-TESTED]` (19 tests / 244 asserts) |
| **FM3 (Scheduling)**| Domain 02: Examination Operations | `ExamSessionController`, `CloseExamSession`, Bulk Actions | `[IMPLEMENTED-TESTED]` (68 tests / 771 asserts) |
| **FM4 (Advising)** | Domain 04: Consultation & Decision Support | 5-Card Decision Modal, TabFM Pairwise Matcher, Radar | `[IMPLEMENTED-TESTED]` (84 tests / 655 asserts) |
| **FM5 (Handoff)** | Domain 05: Admission Clearance | `ConfirmAdmissionClearance`, 4-Point Eligibility Gates | `[IMPLEMENTED-TESTED]` (10 files / 68 tests) |

- **Academic Visual Evidence:** Figures 3.3 to 3.7; Appendices C, D, E, F, and O.

---

### Specific Objective 3 (SO3): User Acceptance Evaluation (TAM)
> *"To evaluate the user acceptance of NEXIAM using the Technology Acceptance Model (TAM) across four stakeholder respondent groups."*

- **RAD Phase:** Phase 4 — Cutover & Evaluation.
- **Data Gathering Instrument:** Role-Adapted TAM Questionnaire (38 total item statements, Appendix J).
- **Target Population & Sampling Plan (Planned N=263):**

| Respondent Group | Planned Count | Sampling Strategy | Evaluation Scenario Role | Instrument Items |
|---|---|---|---|---|
| **Registrar Personnel** | 3 | Complete Census | Application review, requirement validation, clearance sign-off | 8 items (Table J.1) |
| **Guidance Personnel** | 2 | Complete Census | Session creation, OMR review, 5-card consultation advising | 13 items (Table J.2) |
| **Academic Program Heads** | 3 | Purposive (CAS, CBME, CTE) | Quota review, applicant advising notes, clearance receipts | 8 items (Table J.4) |
| **First-Year College Students**| 255 | Convenience / Purposive | Public intake, portal login, result review, advising reception | 9 items (Table J.3) |
| **Total** | **263** | — | — | **38 total items** |

- **Evaluation Protocol (Appendix N):**
  - Standardized simulation scenario: Participants execute realistic, role-specific operational tasks before completing the questionnaire.
  - Evaluation environment: Hosted staging (`https://nexiam.dxtechph.online` or local Compose port `18000`).
- **Statistical Analysis Plan:**
  - Construct means and standard deviations computed separately for **PU** and **PEOU** per respondent group.
  - 5-point Likert Scale (1 = Strongly Disagree, 5 = Strongly Agree).
  - Explicit rule: Means are reported continuously without arbitrary "Acceptance Bands"; incomplete constructs are excluded with documented reasons.

---

## 3. Methodological Guardrails & Limitations

1. **Engineering Proofs vs User Acceptance:** An automated test passing in Pest or Vitest proves *software correctness and stability*; it does *not* prove user acceptance or operational satisfaction. TAM findings must be derived strictly from human participant questionnaires.
2. **Planned Sample vs Collected Findings:** The 263 planned participant count in Appendix N is an operational target. If actual evaluation sessions collect fewer responses, Chapter 3 must report actual valid *n* without inflating sample statistics.
3. **Synthetic Persona Isolation:** First-year student evaluators operate pre-seeded test personas (e.g., `PORT-DEFL`) during the simulation; their personal identities must never be linked to the synthetic admission records.
