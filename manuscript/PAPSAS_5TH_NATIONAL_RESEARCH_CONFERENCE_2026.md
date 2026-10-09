# 5th National Research Training Conference (PAPSAS Inc.) — Official Event Brief & Digest

**Source File:** `gdrive:A.Y. 2026-2027/Capstone/Invitation-Letter-5th-National-Research-Training-Conference.pdf`  
**Extracted Date:** 2026-10-07  

---

## 1. Event Overview

- **Event:** 5th National Research Training Conference
- **Organizer:** Philippine Association of Practitioners of Student Affairs and Services, Inc. (PAPSAS, Inc.)
- **Institutional Partners:** PAPSAS Negros Island Region and Silliman University
- **Dates:** November 18–20, 2026 (Wednesday to Friday)
- **Venue:** Bethel Guest House, Rizal Boulevard, Dumaguete City, Negros Oriental
- **Overall Theme:** *"From Data to Discovery: Harnessing Emerging Technologies for SAS Research"*

---

## 2. Conference Sub-Themes

1. **Promoting Student Well-being in the Digital Age**
2. **Social Media and the Emerging Student Beliefs, Values, and Norms**
3. **Professionalization of SAS Practitioners in the Era of Disruptions**
4. **Emerging Technologies in SAS Research** *(Target sub-theme for NEXIAM DSS / TabFM)*

---

## 3. Critical Deadlines & Milestone Schedule

| Milestone | Deadline / Date | Status (as of Oct 7, 2026) |
|---|---|---|
| **Abstract Submission Deadline** | **October 16, 2026** | **9 days remaining** |
| **Full Paper Submission Deadline** | **October 23, 2026** | 16 days remaining |
| **Conference Registration Deadline** | **October 30, 2026** | 23 days remaining |
| **Conference Day 1 (Arrival, Plenary AI Talk)** | November 18, 2026 | — |
| **Conference Day 2 (Paper Presentations)** | **November 19, 2026** | Presentation Day |
| **Conference Day 3 (Synthesis & Closing)** | November 20, 2026 | — |

---

## 4. Submission Specifications & Formatting Rules

- **Abstract Length:** Strictly **300–350 words**
- **Keywords:** Maximum of **5 keywords**
- **Mandatory Abstract Components:**
  1. Title
  2. Rationale
  3. Objectives
  4. Significance
  5. Scope
  6. Research Design
  7. Findings
- **Registration Fees:**
  - Live-in Participants: Php 9,500.00
  - Live-out Participants: Php 8,000.00
- **Payment Account:** Bank of the Philippine Islands (BPI Morayta Branch), Current Account `1581-0025-44` (PAPSAS Inc.)
- **Registration Form:** `https://bit.ly/5thNatConRegistration`

---

## 5. Strategic Alignment for NEXIAM (BSIT Capstone)

### Why PAPSAS is the Ideal Venue
In Philippine Higher Education Institutions (SUCs and LUCs), the **Guidance and Admissions Offices operate directly under Student Affairs and Services (SAS)**. This makes the conference audience—Directors of Student Affairs, Guidance Counselors, Psychometricians, and College Deans—the exact primary stakeholders of NEXIAM.

### Alignment with Conference Theme & Plenary Talks
The conference specifically features:
- **Plenary Talk 1 (Day 1):** *Using AI in Research (Efficiency, Integrity, and Insight in an AI-Driven Research)* by Dr. Arthur Patungan (UST).
- **Sub-theme 4:** *Emerging Technologies in SAS Research*.

### Core Positioning for the Showcase
1. **Human-in-the-Loop ("Models Advise, Counselors Decide"):** The module provides advisory decision support, not automated exclusion. It respects counselor judgment and student autonomy.
2. **Tabular Foundation Model (TabPFN):** Demonstrates in-context Bayesian inference over institutional curriculum benchmarks without requiring brittle local retraining on small cohorts.
3. **Transparent Evidence & Missingness Handling:** Handles missing high school grades gracefully without penalizing applicants.

---

## 6. Official Submission Abstract Draft (Strictly 310 Words, 5 Keywords)

### Title
**Evaluating College Course Compatibility Using In-Context Tabular Foundation Models in Student Admission Decision Support**

### Target Sub-Theme
*Emerging Technologies in SAS Research*

### Abstract Content

**Rationale:** College admission advising in state universities requires guidance personnel to synthesize multifaceted applicant evidence—including cognitive entrance examination profiles, secondary academic records, and career interest orientations—against shifting departmental intake benchmarks. Traditional admission workflows rely on manual evaluations that risk cognitive fatigue or opaque automated score thresholds that disqualify applicants lacking non-mandatory academic records.

**Objectives:** This study developed and evaluated an advisory admission decision-support module within NEXIAM, a web-based admission system for Ilocos Sur Polytechnic State College Tagudin Campus. The module evaluates applicant-program compatibility using pre-trained in-context tabular foundation models (TabPFN) paired with psychometrician-calibrated curriculum stepped ladders.

**Significance:** By providing non-prescriptive compatibility indices alongside explicit feature drivers and data coverage indicators, the module operationalizes ethical artificial intelligence in Student Affairs and Services (SAS), empowering counselors to conduct informed, transparent course triage while safeguarding applicant agency.

**Scope:** The technical scope encompasses the admission consultation subsystem, benchmark governance engine, and tabular inference pipeline across ten undergraduate academic programs. The study evaluates decision-support usability and technical inference integrity rather than autonomous admission entitlement.

**Research Design:** Using a descriptive-developmental research design, the system architecture was developed using Rapid Application Development (RAD). The tabular inference pipeline maps institutional benchmark exemplars through in-context Bayesian attention masks to accommodate optional high school grade missingness. Evaluation utilized scripted consultation tasks assessed via Technology Acceptance Model (TAM) dimensions alongside automated backend execution telemetry.

**Findings:** Evaluation across simulated counseling sessions demonstrated that in-context foundation model inference produced calibrated course match indices within an operational latency of 60 to 180 milliseconds on central processing units without model retraining. The module gracefully preserved structural missingness in secondary metrics without artificial deficit penalties. Guidance practitioners affirmed high perceived usefulness and ease of use, emphasizing that transparent evidence cards and human-in-the-loop decision boundaries prevented opaque placement errors while accelerating multi-program advising.

### Keywords
Admission Decision Support, Tabular Foundation Models, TabPFN, Student Affairs and Services, Human-in-the-Loop AI

---

## 7. Immediate Action Checklist for Dumaguete Event

1. **Adviser Sign-off:** Present this exact 310-word abstract draft to your thesis adviser (Dr. Zeus Vincent Mendoza) for approval under Sub-Theme 4 (*Emerging Technologies in SAS Research*).
2. **Submit Abstract by October 16, 2026:** Submit via the official portal/email before the 11:59 PM deadline.
3. **Prepare Paper by October 23, 2026:** Extract the Admission Support / TabFM chapter sections from `manuscript/sections/` into a standalone conference paper format.
4. **Register by October 30, 2026:** Complete registration at `https://bit.ly/5thNatConRegistration`.
5. **Offline Demo Preparation for Dumaguete:**
   - Pre-warm the Docker Compose `tabfm-inference` service on a laptop.
   - Prepare 3 synthetic consultation test cases:
     - **Case A (High STEM Alignment):** BSIT match with top aptitude.
     - **Case B (Cross-Strand / TVL):** Demonstrating compensatory evaluation.
     - **Case C (Incomplete Optional Grades):** Demonstrating missingness tolerance without 0-penalty.
   - Keep an unedited screen recording of the consultation workflow in case the venue projector/WiFi has connectivity issues.
