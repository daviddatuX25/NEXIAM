# Capstone Proposal Alignment Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Align all Chapter 1 and Chapter 2 drafts to future tense (as required for the proposal defense) and verify thematic consistency before compiling the final manuscripts.

**Architecture:** Edit individual draft files in `capstone/drafts/` to apply tense adjustments and verify thematic checkpoints. Then, execute the python assembly scripts to compile the final unified Markdown and Word docx documents.

**Tech Stack:** Markdown, Python 3, python-docx (for assembly script)

---

### Task 1: Align C2-01 Research Design to future tense (SecureCAT-v2-ezd)

**Files:**
- Modify: `capstone/drafts/C2-01_David_Research_Design.md`

**Step 1: Write the changes**
Modify the file to describe the developmental component in the future tense:
- Change "involves the iterative design..." to "will involve the iterative design..."
- Change "code generation is governed by..." to "code generation will be governed by..."

**Step 2: Run verification**
Run a git diff check to verify only the tense changes were made.
Run: `git diff capstone/drafts/C2-01_David_Research_Design.md`

**Step 3: Commit**
Run:
```bash
git add capstone/drafts/C2-01_David_Research_Design.md
git commit -m "docs: align C2-01 Research Design to future tense (SecureCAT-v2-ezd)"
```

---

### Task 2: Align C2-02 Software Model to future tense (SecureCAT-v2-aq2)

**Files:**
- Modify: `capstone/drafts/C2-02_David_Software_Model.md`

**Step 1: Write the changes**
Modify the file to use future tense across all phases:
- Intro: Change "was constructed using AI" to "will be constructed using AI"
- Inception: Change past/present references to future tense ("will be conceptualized", "will collaborate", "will identify", "will use", "will include").
- Construction: Change past tense of system development to future tense ("will be developed and tested", "will generate", "will act", "will be constructed", "will be designed and implemented", "will be integrated", "will run", "will be flagged and corrected").
- Operations: Change past tense of evaluation to future tense ("will undergo", "will be deployed", "will be executed", "will use", "to validate", "to confirm", "will end", "will be finalized").

**Step 2: Run verification**
Run: `git diff capstone/drafts/C2-02_David_Software_Model.md`

**Step 3: Commit**
Run:
```bash
git add capstone/drafts/C2-02_David_Software_Model.md
git commit -m "docs: align C2-02 Software Model to future tense (SecureCAT-v2-aq2)"
```

---

### Task 3: Align C2-05 Population & Locale to future tense (SecureCAT-v2-akq)

**Files:**
- Modify: `capstone/drafts/C2-05_Christine_Population_Locale.md`

**Step 1: Write the changes**
Modify the sampling descriptions to use future tense:
- Change "selects the administrative..." to "will select the administrative..."
- Change "is used for the applicant..." to "will be used for the applicant..."
- Change "splits into..." to "will be split into..."
- Change "is selected through..." to "will be selected through..."

**Step 2: Run verification**
Run: `git diff capstone/drafts/C2-05_Christine_Population_Locale.md`

**Step 3: Commit**
Run:
```bash
git add capstone/drafts/C2-05_Christine_Population_Locale.md
git commit -m "docs: align C2-05 Population & Locale to future tense (SecureCAT-v2-akq)"
```

---

### Task 4: Align C2-06 Research Instruments to future tense (SecureCAT-v2-d65)

**Files:**
- Modify: `capstone/drafts/C2-06_Christine_Research_Instruments.md`

**Step 1: Write the changes**
Modify the instrument administration details to use future tense:
- Change "uses two peer-validated..." to "will use two peer-validated..."
- Change "are administered to..." to "will be administered to..."
- Change "Scale is administered..." to "Scale will be administered..."
- Change "is also administered..." to "will also be administered..."

**Step 2: Run verification**
Run: `git diff capstone/drafts/C2-06_Christine_Research_Instruments.md`

**Step 3: Commit**
Run:
```bash
git add capstone/drafts/C2-06_Christine_Research_Instruments.md
git commit -m "docs: align C2-06 Research Instruments to future tense (SecureCAT-v2-d65)"
```

---

### Task 5: Align C2-07 Data Analysis to future tense (SecureCAT-v2-j45)

**Files:**
- Modify: `capstone/drafts/C2-07_David_Data_Analysis.md`

**Step 1: Write the changes**
Modify the analysis procedures to use future tense:
- Change "...procedures... follow..." to "...procedures... will follow..."
- Change "feedback loop ensures..." to "feedback loop will ensure..."
- Change "SUS yields..." to "SUS will yield..."
- Change "...successfully reduced... while improving..." to "...successfully reduces (or will reduce)... while improving (or will improve)..."

**Step 2: Run verification**
Run: `git diff capstone/drafts/C2-07_David_Data_Analysis.md`

**Step 3: Commit**
Run:
```bash
git add capstone/drafts/C2-07_David_Data_Analysis.md
git commit -m "docs: align C2-07 Data Analysis to future tense (SecureCAT-v2-j45)"
```

---

### Task 6: Align C1-12 Significance of the Study to future tense (SecureCAT-v2-5zc)

**Files:**
- Modify: `capstone/drafts/C1-12_Jaypee_Significance.md`

**Step 1: Write the changes**
Modify all verbs in the significance sections to describe future benefits (change present/past verbs to "will benefit", "will reduce", "will minimize", "will address", "will replace", "will experience", etc. as outlined in IMPROVEMENT_TASKS_2.md).

**Step 2: Run verification**
Run: `git diff capstone/drafts/C1-12_Jaypee_Significance.md`

**Step 3: Commit**
Run:
```bash
git add capstone/drafts/C1-12_Jaypee_Significance.md
git commit -m "docs: align C1-12 Significance of the Study to future tense (SecureCAT-v2-5zc)"
```

---

### Task 7: Verify Thematic Consistency and Bead Trends (SecureCAT-v2-tz9)

**Files:**
- Verify all files in `capstone/drafts/`

**Step 1: Run verification check on draft files**
Verify that all draft files conform to the following thematic rules:
1. No mentions of "Google Forms" exist.
2. Internet outages are framed as campus infrastructure/bandwidth limitations, not frequent weekly outages.
3. Existing systems (Registrar, Guidance, MIS/SIMS) are recognized; avoid "purely manual" or "entirely paper-based" absolute claims.
4. Guidance Office scoring baseline uses the stencil method.
5. Conversion table is confidential and role-locked.
6. RAG AI Companion is framed as augmenting counselor availability (24/7 preparation/post-exam guidance).
7. Machine Learning is live K-Means clustering integrated via FastAPI python microservice (adopt & tweak school-owned code).
8. Multi-tenancy is deferred to Recommendations (future enhancement).
9. Role assignment supports assigning multiple roles (e.g. examiner + encoder) to a single user.

**Step 2: Commit verification report**
We will update the task tracker and mark the verification bead as completed once all files are verified.

---

### Task 8: Compile and Assemble Updated Manuscript (SecureCAT-v2-5ls)

**Files:**
- Run: `capstone/assemble_manuscript_md.py`
- Run: `capstone/assemble_manuscript.py`
- Output: `capstone/SecureCAT_Ch1_Ch2_Manuscript.md`
- Output: `capstone/SecureCAT_Ch1_Ch2_Manuscript.docx`

**Step 1: Run the assembly scripts**
Run:
```bash
python3 capstone/assemble_manuscript_md.py
python3 capstone/assemble_manuscript.py
```

**Step 2: Verify outputs compile without errors**
Verify the compiled output files exist and contain updated future-tense content.
Verify:
```bash
ls -la capstone/SecureCAT_Ch1_Ch2_Manuscript.*
```

**Step 3: Commit final assembled manuscripts**
Run:
```bash
git add capstone/SecureCAT_Ch1_Ch2_Manuscript.md capstone/SecureCAT_Ch1_Ch2_Manuscript.docx
git commit -m "docs: assemble final aligned Chapter 1 & 2 manuscripts (SecureCAT-v2-5ls)"
```
