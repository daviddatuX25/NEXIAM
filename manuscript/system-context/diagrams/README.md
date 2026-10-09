# System Diagrams, Activity Models & Visual Architecture Index

This index catalogs all visual models, data flow diagrams, entity-relationship diagrams, and process flowcharts associated with NEXIAM. Each asset is tagged with its source file, SHA-256 integrity hash, methodological role, and verification status.

---

## 1. Primary Academic Figures (Chapter 3 SO1)

These figures illustrate the baseline institutional workflow derived from stakeholder interviews and applicant surveys:

| Figure ID | Visual Title | File Path & Format | Status & Provenance |
|---|---|---|---|
| **Figure 3.1** | *Evidence-to-Process Mapping Sequence* | `manuscript/V2_RESOURCES/figures/figure_3_1_evidence_to_process_mapping.png` (`.svg`, `.d2`) | `[OBSERVED-EVIDENCE]` — D2 author-created chart mapping interview cohorts to baseline workflow. |
| **Figure 3.2** | *Current-State Admission-Testing Flowchart* | `manuscript/V2_RESOURCES/figures/figure_3_2_current_state_flowchart.png` (`.svg`, `.d2`) | `[OBSERVED-EVIDENCE]` — D2 flowchart summarizing paper ICAT, stencil grading, and manual queues. |

---

## 2. Institutional Technical Diagrams (Appendices C–F)

These diagrams were provided directly by the primary researchers (David Datu N. Sarmiento, Jaypee G. Pagaduan, Christine M. Lopez) and reflect the system's structural blueprint:

### A. Use Case Diagram (Appendix C, Figure C.1)
- **Local Path:** `manuscript/V2_RESOURCES/latest_diagrams/new-usecase-diagram.jpg`
- **SHA-256:** `88d0b0bcd87cb72cb6f89598cb88236620809d3fda523309444a3cb6ca2762ef`
- **Status:** `[TARGET-DESIGN]`
- **Confirmed Diagram Quirk:** In `new-usecase-diagram.jpg`, the connection line from `Guidance Counselor / Test Administrator` to `Consultation` was omitted in the visual export. However, DFD Level 1 (Processes 9 & 10) and founder lock confirm that the Guidance Counselor is the primary consultation actor.

### B. Data Flow Diagram Level 0 Context Model (Appendix D, Figure D.1)
- **Local Path:** `manuscript/V2_RESOURCES/latest_diagrams/new-DfD-level-0.png`
- **SHA-256:** `588bd6da7b91f70e3265d50d5fb9d9bd13ed4b18b12191b2e491a2726c905053`
- **Status:** `[TARGET-DESIGN]`
- **Scope:** Illustrates external entities (Applicant, Proctor, Registrar Staff, Guidance Counselor, Program Head) interacting with the core NEXIAM boundary.

### C. Data Flow Diagram Level 1 Detailed Decomposition (Appendix E, Figure E.1)
- **Local Path:** `manuscript/V2_RESOURCES/latest_diagrams/new-DfD-Level1.drawio.png`
- **SHA-256:** `9c85fe765a3da57946e241e216fca3e5286a04f5c405a59ddc7162b502f73b57`
- **Status:** `[TARGET-DESIGN]`
- **Decomposition:** Details 11 distinct process groups:
  1. *Institutional Config*
  2. *Account Activation & Profile*
  3. *Apply Admissions*
  4. *Manage Admissions*
  5. *Exam Scheduling*
  6. *Exam Monitoring*
  7. *Exam Scoring*
  8. *Print Result*
  9. *Consultation Scheduling*
  10. *Consultation Queue & Decision*
  11. *Validate Enrollment Eligibility*

### D. Entity-Relationship Diagram (Appendix F, Figure F.1)
- **Local Paths:**
  - `manuscript/V2_RESOURCES/latest_diagrams/NEXIAM_Core_Admissions_ERD_A4.png` (SHA-256: `4f246245620d9e561de4f47a402e980dc0880e6ef55b57484909b7220cb42ef2`)
  - `manuscript/V2_RESOURCES/latest_diagrams/nexiam-erd-compact.png` (SHA-256: `2c565443c287a63b7688e1a7d92a42c29fd4350f2807b36d1827e03eed015124`)
- **Status:** `[TARGET-DESIGN / SCHEMA RECONCILED]`
- **Entity Coverage:** Maps relational connections between users, identities, admission attempts, exam sessions, assignments, booklets, OMR captures, scores, consultations, and program quotas.

---

## 3. Conceptual Framework (Chapter 1, Figure 1.1)

- **Local Path:** `manuscript/V2_RESOURCES/latest_diagrams/updated_ipo_diagram.png`
- **SHA-256:** `0f1d2523eedf0daa73757379f19622a446c34f39fc7ac86aa6a49a576cbbe885`
- **Alternate:** `manuscript/V2_RESOURCES/latest_diagrams/ipo-diagram.png` (SHA-256: `0055e2a072cc3ef1d556acd2dd0398a1e64b12f050f50415613f33ea5296375d`)
- **Framework Model:** Input-Process-Output (IPO)
  - **Inputs:** Applicant Demographic Data, Senior High School Records, Examination Scores, Program Benchmarks & Quotas, Statutory Policies (RA 10173).
  - **Process:** Rapid Application Development (RAD) Lifecycle: Requirements Planning, User Design, Rapid Construction, Cutover.
  - **Outputs:** Evaluated NEXIAM System, Optimized Turnaround Time, Attributable Multi-Office Audit Trail, Verified TAM Acceptance.

---

## 4. Engineering Actor-Lane Activity Models (Appendix O)

- **Source Reference:** `manuscript/sections/04_appendices/04_appendix_o_use_case_activity_validation.md`
- **Engineering Origin:** `openspec/changes/action-core-authority-realignment/use-case-audit/`
- **Scope:** 38 rendered Mermaid activity diagrams mapping 146 discrete system use cases across 14 functional domains.
- **Verification Note:** Serves as technical traceability documentation; does not represent stakeholder usability ratings or TAM results.
