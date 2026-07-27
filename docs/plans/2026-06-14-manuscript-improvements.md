# Manuscript Improvements Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Surgically revise the capstone manuscript to address dynamic enrollment quotas, student admission choice dynamics (with post-2021 literature), and the removal of the System Usability Scale (SUS) in favor of the NASA Task Load Index (NASA-TLX).

**Architecture:** A three-phase revision strategy: first, document SY 2026-2027 registrar/guidance observations; second, update the research argument bank with post-2021 citations; third, perform surgical edits to the manuscript markdown file across Chapter 1 (Background, Objectives, Scope, Significance) and Chapter 2 (Research Instruments, Data Analysis).

**Tech Stack:** Python 3 (Manuscript Compilation Pipeline), Pandoc/Markdown, Git.

---

### Task 1: Document Qualitative Observations & Data

**Files:**
- Create: `capstone/references/integration/REGISTRAR_GUIDANCE_2026_ADMISSIONS_OBSERVATIONS.md`

**Step 1: Write the qualitative observations file**
Create the reference document detailing registrar and guidance findings from the SY 2026-2027 admissions cycle. Include:
- Verification of dynamic capacity constraints (enrollment quotas) for courses like BSIT, BSBA, and BSED due to physical room utilization limits and faculty loading issues.
- The admissions pipeline as the primary student acquisition and retention gate.
- The applicant enrollment choice dilemma: how process friction drives applicants who take exams at multiple institutions to choose competing schools.
- Counselor requirement for an extensible data collection interface to capture secondary metrics (grades in Math, Science, English, and GWA) to continuously refine recommendation algorithms and feed the RAG-based AI Companion.

**Step 2: Commit changes**
```bash
git add capstone/references/integration/REGISTRAR_GUIDANCE_2026_ADMISSIONS_OBSERVATIONS.md
git commit -m "docs: create registrar and guidance observations reference file"
```

---

### Task 2: Update Research Argument Bank

**Files:**
- Modify: `capstone/references/arguments/RESEARCH_ARGUMENT_BANK.md`

**Step 1: Update argument bank entries**
Surgically revise the following sections in `RESEARCH_ARGUMENT_BANK.md`:
- **C1-01 (Core Problem Statement) & C1-04 (Local Context):** Add points about the student experience/enrollment choice dynamics and dynamic program quotas as confirmed by the SY 2026-2027 observations.
- **C1-09 (Objectives) & C1-12 (Significance):** Remove all references to the System Usability Scale (SUS) and focus exclusively on the NASA-TLX workload assessment.
- **Chapter 2 Arguments (C2-01, C2-05, C2-06, C2-07):** Completely remove references to SUS. Update the research instruments and data analysis arguments to focus on NASA-TLX scoring computation, subscale profiling, and task workload validation.

**Step 2: Commit changes**
```bash
git add capstone/references/arguments/RESEARCH_ARGUMENT_BANK.md
git commit -m "docs: update research argument bank for SUS removal and quota arguments"
```

---

### Task 3: Revise Chapter 1 Manuscript Sections (Background & Objectives)

**Files:**
- Modify: `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`

**Step 1: Update Paragraphs 1, 4, and 5 of Background of the Study (`ch1_bg_of_the_study`)**
- **Paragraph 1:** Integrate the student choice dilemma. Add that the admissions experience acts as a critical first impression for applicants; since students commonly apply to multiple campuses, administrative delays directly lead to student attrition to more responsive competing institutions (Mapalad et al., 2025; Chhor et al., 2024).
- **Paragraph 4 (Local Context):** Describe the dynamic course quotas managed between Program Heads and the Guidance Office due to resource limitations (room utilization, faculty constraints) observed in the SY 2026-2027 admissions cycle. Show how the 1–2 week stenciling delay blind-spots counselors, leading to overallocation or mismatched placement recommendations.
- **Paragraph 5 (Research Gap):** Highlight the gap in current computerized admission research, which treats ML (K-Means) as static, post-hoc analyses. Frame the need for an extensible live profiling interface that lets counselors incrementally add secondary student metrics (subject grades in math/science, GWA) to dynamically refine the recommender and train the AI companion over time.
- **Paragraph 6 (Proposal Summary):** Adjust wording to show that SecureCAT's K-Means triage is "quota-aware" and "extensible."

**Step 2: Update Objectives (`ch1_objectives`)**
- Modify Specific Objective 2 to: "Design and develop a live, quota-integrated, and extensible K-Means course triage module..."
- Modify Specific Objective 3 to: "Evaluate the perceived task workload of the developed system using the NASA Task Load Index (NASA-TLX) as administered to Registrar staff and Guidance counselors at ISPSC Tagudin Campus." (Remove SUS entirely).

**Step 3: Commit changes**
```bash
git add capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md
git commit -m "feat: revise manuscript Chapter 1 background and objectives"
```

---

### Task 4: Revise Chapter 1 Manuscript Sections (Scope & Significance)

**Files:**
- Modify: `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`

**Step 1: Update Scope & Delimitations (`ch1_scope_delimitations`)**
- Add details that the K-Means triage module manages live quotas and captures extensible secondary metrics (subject-specific grades, GWA) for future recommender training.
- Verify that SUS is not mentioned in the scope.

**Step 2: Update Significance of the Study (`ch1_significance`)**
- **Guidance Office:** Clarify how real-time quota visibility and aptitude mapping relieve cognitive load.
- **The Researchers:** Remove SUS and focus on gaining competencies in NASA-TLX task workload evaluation.
- **Future Researchers:** Highlight the extensible data collection framework as a blueprint for iterative ML model optimization.

**Step 3: Commit changes**
```bash
git add capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md
git commit -m "feat: revise manuscript Chapter 1 scope and significance"
```

---

### Task 5: Revise Chapter 2 Manuscript Sections (Methodology, Instruments, & Analysis)

**Files:**
- Modify: `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`

**Step 1: Update Research Design & Project Plan (`ch2_research_design` and `ch2_project_plan`)**
- Remove SUS from the descriptive-assessment phase of the methodology overview.
- Update the Operations phase timeline (Paragraph under Table 2/Project Plan) to focus only on NASA-TLX.

**Step 2: Update Research Instruments (`ch2_research_instruments`)**
- Delete the entire paragraph explaining the System Usability Scale (SUS) and its 0-100 score calculations.
- Expand the description of the NASA-TLX to cover the pairwise comparison procedure used to compute weighted workload scores, citing Al-Qudah & Al-Sarrayriah (2023) and Loiacono & McCoy (2024).

**Step 3: Update Data Analysis (`ch2_data_analysis`)**
- Remove references to SUS descriptive statistics and Table 3 (SUS interpretation).
- Detail the weighted workload score calculation formula for the NASA-TLX: multiplying each subscale score by its weight (determined by pairwise comparisons), summing the weighted scores, and dividing by 15.

**Step 4: Update References**
- Remove Brooke (1996) and other SUS-specific citations.
- Add recent citations: Mapalad et al. (2025), Chhor et al. (2024).

**Step 5: Commit changes**
```bash
git add capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md
git commit -m "feat: revise manuscript Chapter 2 methodology, instruments, and analysis"
```

---

### Task 6: Compile and Verify Changes

**Files:**
- Modify: `capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`
- Test: Run script verification and compile to DOCX

**Step 1: Run drift check**
Verify that our manuscript is clear of conflicts and synchronized:
```bash
python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action check
```
Expected: PASS with no drift.

**Step 2: Compile manuscript markdown to DOCX**
Execute the update action to compile the revised markdown to the master Word document:
```bash
python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action update
```
Expected: Compilation completes successfully with exit code 0.

**Step 3: Run diff verification**
Generate the diff report to review the changes:
```bash
python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action diff
```
Expected: Shows surgical changes applied only to the targeted manuscript tags.

**Step 4: Commit compiled files**
```bash
git add capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md
git commit -m "build: compile revised manuscript markdown to Word document"
```
