# NEXIAM Manuscript 48-Hour Rush Execution Plan & Durable Decisions

**Date Recorded:** 2026-09-30  
**Deadline:** 2 days away (Target Completion: October 2, 2026)  
**Status:** ACTIVE BINDING PLAN — Preserved across all working sessions  
**Researchers:** David D. Sarmiento, Jaypee G. Pagaduan, C. M. Lopez  
**Capstone Adviser:** Dr. Zeus Vincent Mendoza  
**Reference Benchmark:** ISPSC Tagudin BSIT Capstone Precedent (`Yukee et al., November 2025`, `manuscript/inspiration/yuuke_researche_pages.txt`)

---

## 1. Non-Negotiable Working Rules (Founder Lock)

1. **Dissected Section Files Only:**
   * **Do NOT** assemble a monolithic `final_manuscript.md` or compile `.docx` documents prematurely.
   * Work strictly within modular, dissected chapter/section markdown files under `manuscript/sections/`.
2. **Explicit Plate Placeholders:**
   * System screenshots follow the institutional standard ("Plates", as in Yukee thesis Plate 1 to Plate N).
   * In Chapter 3 (SO2), mark exact visual anchors as:
     `[PLATE X: Title of Screen — Description of visible interface, fields, and actor perspective]`
   * These remain as blanks/placeholders now and will be filled with real screenshots immediately after tomorrow's deployment.
3. **Chapter 3 Status Cut-Line:**
   * **SO1 (Process Analysis):** FULL NARRATIVE & METRICS (grounded in June 2026 office instruments + September 2026 applicant survey $n=22$).
   * **SO2 (System Requirements & RAD Construction):** FULL SPECIFICATION & RAD NARRATIVE + Plate Placeholders (FR-01 to FR-13, TabPFN triage architecture, OMR lifecycle).
   * **SO3 (TAM Acceptance Evaluation):** METHODOLOGY & INSTRUMENT TABLES ONLY. Actual evaluation data is left pending post-deployment simulated scenario execution.
4. **Style Profile (Anti-AI Enforcement):**
   * Zero em dashes (`—` or `--`) in prose.
   * No formulaic transitions (`"Once X, Y..."`).
   * No textbook definition dumps; explain concepts through how NEXIAM specifically uses them.
   * Varied clause rhythm and parenthetical citations.

---

## 2. Schedule & Field Operations (Tomorrow, Day 1)

### Operation 1: Cloud/Staging Deployment
* Deploy `system/` modular monolith runtime to staging/cloud.
* Verify login, applicant portal, session scheduling, OMR workbench, and counselor course triage card.
* Capture real screenshots to populate `[PLATE X]` placeholders in Chapter 3 SO2.

### Operation 2: Guidance Office Model Tuning & Recorded Psychometrician Validation
* **Objective:** Validate the adoption of the in-context TabPFN tabular foundation model (`Hollmann et al., 2025`) and the 3-tier indicator input model with the Guidance Counselor / Psychometrician.
* **Recording / Protocol:** A brief recorded validation discussion to solidify academic and research integrity.
* **Key Questions for Counselor / Psychometrician:**
  1. *Sub-test Alignment:* Do the six ICAT sub-tests (General Ability, Verbal, Numerical, Spatial, Perceptual, Manual Dexterity) alongside SHS Strand and Grade 12 GWA represent the primary quantitative baseline for counseling?
  2. *Missing Data Realism:* In actual intake, do students often lack granular subject grades (e.g., specific Grade 11/12 Physics or Calculus marks) on initial consultation day?
  3. *Advisory Decision Support:* Confirm that the counselor requires advisory compatibility percentages and primary feature drivers (e.g., high spatial/numerical match for engineering/IT), while all final placement decisions remain strictly human-confirmed.
  4. *Research Asset:* Confirm acceptance of de-identified consultation tracking for long-term institutional research on student retention.

### Operation 3: Capstone Adviser Consultation (Dr. Zeus Vincent Mendoza)
* **Objective:** Consult Dr. Zeus Vincent Mendoza on refining the granularity of Specific Objectives to elevate the software engineering and machine learning contributions.
* **Strategic Proposal to Adviser:**
  * *Current State:* 3 broad objectives (SO1: Process Analysis, SO2: System Requirements & RAD Development, SO3: TAM Evaluation).
  * *Discussion Point:* Should SO2 be partitioned or made more fine-grained (e.g., explicitly separating **SO2a: Requirements & Architecture**, **SO2b: Component Construction & AI/Foundation Model Integration across the 7-Stage Pipeline**, and **SO2c: Verification & Engineering Benchmarks**)?
  * *Academic Benefit:* Allows deeper, formal discussion of the RAD methodology and the TabPFN foundation model in Chapter 3, while anchoring Chapter 2 (Methodology) and Chapter 3 (Results & Discussion) so they mirror each other seamlessly, and broadening Objective 3 (TAM) across those distinct user-facing capabilities.

---

## 3. Dissected File Layout (`manuscript/sections/`)

```
manuscript/sections/
├── 00_front_matter/
│   ├── 00_title_and_approval.md
│   └── 00_table_of_contents_and_lists.md    # Includes List of Tables, Figures, Plates
├── 01_chapter_1_introduction/
│   ├── 01_1_background_of_the_study.md      # Grounded in applicant survey + Yukee contrast
│   ├── 01_2_conceptual_framework.md        # IPO Model (3 inputs, RAD, 4 outputs)
│   ├── 01_3_objectives_of_the_study.md      # SO1, SO2, SO3 (flagged for adviser input)
│   ├── 01_4_scope_and_limitations.md        # 7 RBAC roles, TabPFN 3 data tiers, cloud bound
│   ├── 01_5_importance_of_the_study.md     # Institutional impact for ISPSC Tagudin
│   └── 01_6_definition_of_terms.md         # Locked operational terms
├── 02_chapter_2_methodology/
│   ├── 02_1_research_design.md              # Descriptive-developmental (Frey)
│   ├── 02_2_software_model.md               # RAD (Martin, Pressman, Olipas, TabPFN)
│   ├── 02_3_project_plan_and_assignments.md # Roles & timeline (Table 2.1)
│   ├── 02_4_population_and_locale.md        # ISPSC Tagudin, Table 2.2 (n=260, Slovin's)
│   ├── 02_5_research_instruments.md         # Role-adapted TAM (Tables 2.3–2.5, Audit 2.6)
│   └── 02_6_data_analysis.md                # SO1 mapping, SO2 validation, SO3 TAM means
├── literature_reference/                    # Preserving rich literature syntheses (Hollmann, Adwan, etc.)
├── 03_chapter_3_results_and_discussion/
│   ├── 03_1_so1_process_analysis.md         # 5 Failure Modes + Applicant Survey (n=22) metrics
│   ├── 03_2_so2_system_requirements_rad.md  # FR-01–FR-13, TabPFN SPI, [PLATE X] placeholders
│   └── 03_3_so3_tam_evaluation.md           # Post-test methodology & 4 role tables (Data pending)
└── 04_appendices/
    ├── 04_appendices_inventory.md                   # Appendices A–M mapped to Yukee benchmark
    └── 04_1_diagrams_and_visual_models_persisted.md # Permanent Use Case, DFD 0/1, ERD, IPO & 7-Stage Pipeline
```

---

## 4. Execution Sequence for Today & Tomorrow

1. **Step 1:** Establish the modular file structure under `manuscript/sections/`.
2. **Step 2:** Populate `03_1_so1_process_analysis.md` with the updated Table 3.1 and the $n=22$ applicant journey data (60% mobility burden, 70% Facebook uncertainty, queue wait times, student quotes).
3. **Step 3:** Populate `03_2_so2_system_requirements_rad.md` with the 13 Functional Requirements, the TabPFN in-context triage specification, and explicit `[PLATE 1]` to `[PLATE 14]` placeholders.
4. **Step 4:** Synchronize Chapter 1 (`01_1_background_of_the_study.md` and `01_4_scope_and_limitations.md`) and Chapter 2 (`02_3_course_recommender_models.md`) with the TabPFN literature and local Yukee contrast.
5. **Step 5:** Record adviser consultation outcomes and psychometrician interview notes tomorrow to finalize the draft.
