# Manuscript Critical Revisions Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Apply all 25 critical revisions from `SECURECAT_CRITICAL_CHANGES_FINAL.md` to the markdown manuscript, rebuild the Word document, and sync with the cloud.

**Architecture:** Perform find-replace edits in `/home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md` for Chapters 1 and 2, verify formatting against the post-edit consistency checklist, run the repair/extraction pipeline to compile back to DOCX, and push to Drive.

**Tech Stack:** python-docx, difflib, rclone

---

### Task 1: Apply Chapter 1 Critical Revisions (Changes 1 to 14)

**Files:**
- Modify: `/home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`

**Step 1: Write/apply the Chapter 1 changes**
Use the code editor to perform the following replacements in the markdown file:
- **Change 1 (Background P4 data reframe):** Reframe physical filing re: longitudinal analysis.
- **Change 2 (Background P4 result venue):** Adjust "requires an in-person consultation at the Guidance Office" to "requires applicants to attend a scheduled in-person session on campus".
- **Change 3 (Background P4 Facebook page):** Clarify that updates are posted to general-purpose Facebook page without a fixed schedule.
- **Change 4 (Background P4 slot access):** Correct the advising session interrupt fact to reliance on stale quota figures.
- **Change 5 (Background P4 closing clause):** Remove the pandemic reference and extra citations at the end of Paragraph 4.
- **Change 6 (Background P5 research gap reframe):** Rewrite the three gap sentences to globally frame the internet, data privacy, and organizational issues before stating the local gap.
- **Change 7 (Background P5 RAG jargon):** Change "retrieval-augmented companion" to plain "AI-powered guidance chatbot".
- **Change 8 (Background P5 SHS subjects):** Generalize "mathematics, English, and science" to "subject-specific senior high school performance records".
- **Change 9 (Background P6 opener):** Replace the methodological statement with "Against this backdrop of operational constraint and untapped local research...".
- **Change 10 (Background P6 failures):** Change "These failures" to "These operational bottlenecks".
- **Change 11 (Scope SHS subjects):** Generalize the second SHS subject list to "subject-specific senior high school performance records".
- **Change 12 (Scope QR/Name matching):** Correct the QR sentence and restore name matching as a backup identity link.
- **Change 13 (Importance - Distance Travel):** Remove the incorrect "exceeding two hours" travel time for Alilem and reframe around the 3-visit scheduling burden.
- **Change 14 (Importance - The Respondents rewrite):** Rename heading to "The Applicants" and rewrite paragraph focused entirely on applicant-specific benefits.

**Step 2: Verify git diff of Chapter 1**
Run: `git diff /home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`
Expected: Diff matches Changes 1-14 from the revision spec.

---

### Task 2: Apply Chapter 2 Critical Revisions (Changes 15 to 21)

**Files:**
- Modify: `/home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`

**Step 1: Write/apply the Chapter 2 changes**
Use the code editor to perform the following replacements in the markdown file:
- **Change 15 (Table 1 QA Tester row):** Remove "administers SUS and NASA-TLX" and replace with "administers TAM questionnaire evaluations in pre-test and post-test conditions".
- **Change 16 (Software Development Model reframe):** Reframe RAD model to AIDLC model as primary. Replaces five paragraphs and a sub-heading with three paragraphs. Rename Figure 2 caption to "AI-Driven Development Lifecycle (AIDLC)".
- **Change 17 (Sub-heading before Phase descriptions):** Change "RAD Phases Applied to SecureCAT" to "AIDLC Phases Applied to SecureCAT".
- **Change 18 (Project Plan narrative):** Change "the RAD model" Gantt narrative reference to "the AIDLC".
- **Change 19 (Phase 3 construction workflow):** Change "the AI-augmented development workflow described above" to "the AIDLC's AI-human collaborative workflow".
- **Change 20 (Importance - Researchers paragraph):** Change "under the RAD model with AI-augmented development" to "under the AIDLC".
- **Change 21 (Importance - Future Researchers):** Change "AI-augmented RAD methodology" to "AIDLC methodology".

**Step 2: Verify git diff of Chapter 2**
Run: `git diff /home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`
Expected: Diff matches Changes 15-21 from the revision spec.

---

### Task 3: Perform Reference List Audit & Dead Citations Check (Changes 22 to 25)

**Files:**
- Modify: `/home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md`

**Step 1: Check and audit reference citations**
- Confirm that Bangor (2008, 2009), Brooke (1996), and Sauro & Lewis (2016) do not appear in the references section of the MD file.
- Verify if **Chhor et al. (2024)** and **Mapalad et al. (2025)** are cited in the text of the MD file. If not cited, remove them from the references list.

**Step 2: Commit all markdown changes**
Run:
```bash
git add /home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md
git commit -m "docs: apply 25 critical manuscript revisions for chapters 1 and 2"
```

---

### Task 4: Run Unified Pipeline and Verify Roundtrip Integration

**Files:**
- Modify: `/home/user/Projects/SecureCAT-v2/capstone/output/SecureCAT_Ch1_Ch2_Manuscript[never delete].docx` (updated via script)
- Modify: `/home/user/Projects/SecureCAT-v2/capstone/manuscript/SecureCAT_Ch1_Ch2_Manuscript.md` (updates SHA256 meta tag)

**Step 1: Run local pipeline update**
Run: `python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action update`
Expected: Applies the markdown revisions to the local DOCX file, updates the SHA256 meta tag in the markdown file, and succeeds with exit code 0.

**Step 2: Run verification script**
Run: `python3 capstone/manuscript/fix_and_verify_pipeline.py`
Expected: Repipes the MD to DOCX and confirms "PERFECT MATCH: All content preserved in roundtrip!".

---

### Task 5: Push Updates to Drive & Create Backup

**Files:**
- Push: Local DOCX to Google Drive remote

**Step 1: Push to Drive**
Run: `python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action push`
Expected: Copies the local updated DOCX to Google Drive via `rclone copy` and finishes successfully.

**Step 2: Create local backup**
Verify that the pipeline created a timestamped backup of the final document under `/home/user/Projects/SecureCAT-v2/capstone/output/backups/`.
Verify that the Git workspace is clean and all metadata tags are committed.
