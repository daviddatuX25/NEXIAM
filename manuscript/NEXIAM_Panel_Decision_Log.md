# NEXIAM Phase 2 — Consolidated Revision Notes
## Scope + Research Instrument + Data Analysis | SO4 removed | TAM → PU+PEOU only

---

## 1. REVISED OBJECTIVES

**General Objective:** Develop NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus.

**Specific Objectives:**
- **SO1:** Analyze the existing college admission testing processes, operational workflows, and process gaps at ISPSC Tagudin Campus through descriptive inquiry.
- **SO2:** Determine the system requirements of NEXIAM in terms of functional requirements and system features needed to address the identified process gaps.
- **SO3:** Evaluate the acceptance of NEXIAM using the Technology Acceptance Model (TAM) in terms of perceived usefulness and perceived ease of use across four respondent groups.

**Removed:**
- ~~SO4~~ (paired comparison of manual vs NEXIAM)
- ~~ATU and BIU~~ constructs from SO3
- ~~Null Hypotheses~~ (H₀₁, H₀₂ — deleted entirely)

---

## 2. REVISED SCOPE AND LIMITATIONS *(organize per objective)*

### Scope aligned to SO1 (Process Analysis)
Covers the full admission testing pipeline at ISPSC Tagudin Campus — applicant registration, examination administration, scoring, result release, and post-examination counseling support. The descriptive inquiry captures operational workflows, process gaps, and inter-office coordination failures through semi-structured questionnaires and field observation at the Guidance and Registrar Offices. The analysis covers the admission cycle running June through September 2026.

### Scope aligned to SO2 (System Requirements)
The system requirements cover seven RBAC-governed user roles: Applicant, Proctor (duty overlay), Guidance Counselor, Guidance Administrator, Registrar Staff, Registrar Administrator, and System IT Administrator. The functional scope spans:

- **Normal Admission Functions.** Applicant registration, examination scheduling, result processing, and inter-office coordination through RBAC-governed dashboards. Staff access is provided through a dedicated staff interface serving the Guidance Office, Registrar Office, and Program Heads, with role-isolated views scoped to each office's operational functions. Applicants access the system through a web-based portal authenticated via a unique identifier key embedded in their applicant profile, replacing traditional account creation. Applicants receive email notifications for schedule assignments and result releases. The portal provides 24/7 access to result retrieval and chatbot interaction.

- **Optical Mark Recognition (OMR).** A computer vision OMR engine automates bubble-sheet scoring from scanned answer sheets. The module uses gated decisioning: answer sheets are processed in batches, and any marked bubble falling below a configurable confidence threshold is flagged for human verification rather than auto-scored.

- **RAG Chatbot and AI Scheduling.** The Retrieval-Augmented Generation chatbot is a structural service layer architecturally coupled to the AI scheduling engine. The chatbot handles applicant-specific inquiries — retrieving the applicant's own examination results, checking their own assigned schedule — and surfaces AI-optimized schedule assignments drawn from the scheduling database in natural language. The chatbot draws exclusively from ISPSC institutional documents. Below the retrieval confidence threshold, it declines to answer rather than fabricating.

- **Admission Support Models (ML Engine).** The system integrates an extensible interface wrapper capable of loading an unsupervised model for Historical Cohort Profiling and a simple baseline supervised model for Preliminary Program Matching. The engine surfaces aptitude patterns through an Admission Support Dashboard for counselor review. The algorithm is strictly descriptive and does not make admission decisions. Every placement requires manual counselor confirmation. Program capacity is managed through a separate quota interface and excluded from the clustering algorithm.

- **Audit Trail.** All system events are logged with HMAC-SHA256 cryptographic chaining, covering data access, role actions, placement decisions, and schedule modifications.

### Limitations aligned to SO2 (System Boundaries)
The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment. The proctoring interface is designed with offline resilience via service-worker caching for examination venues with intermittent connectivity. All other system functions require cloud connectivity.

**OMR.** The study measures OMR accuracy as part of the system validation discussed in the Results and Discussion chapter. However, accuracy is validated through the research instrument (TAM acceptance measures) rather than benchmarked against industry-standard OMR accuracy metrics. OMR is an established technology with proven implementations in Philippine educational contexts (Cuerdo); this study's contribution is its integration into a governed admission pipeline, not re-proving the underlying computer vision technique. Accuracy findings are reported descriptively alongside TAM results.

**RAG and AI Scheduling.** AI inference for the chatbot and scheduling assistant relies on external model access through OpenRouter, bounded by the operational funding allocated by the office for conducting the admission cycle. Paid inference capacity beyond this allocation is outside scope. The study does not benchmark the LLM's response accuracy against industry-standard NLP metrics; the chatbot is validated through TAM acceptance measures evaluating whether applicants find its responses useful and easy to interact with.

**Admission Support Models.** The intrinsic mathematical accuracy, parameter optimization, algorithmic tuning, and predictive validity of the underlying models remain completely unmeasured and outside the scope of this research. The evaluation of this module is restricted purely to its system utility, multi-role data serving capabilities, and user acceptance testing.

### Limitations aligned to SO3 (Evaluation Boundaries)
The evaluation instrument is the Technology Acceptance Model, administered as a single post-test measuring two constructs: Perceived Usefulness and Perceived Ease of Use. Acceptance is reported through descriptive means per construct and per respondent role without inferential comparison to a manual baseline.

Because the September 2026 evaluation window occurs outside the active admission cycle, purposive sampling utilizes simulated evaluators executing role-based scenarios. The study's evaluation window cannot produce findings based on live applicant behavior, and results are bounded by simulated operational conditions.

The total sample consists of 271 evaluators divided into two categories. Administrative staff are purposively selected because they represent the complete available staff complement at the Tagudin Campus: 11 Program Heads, 3 Registrar Office personnel, and 2 Guidance Office personnel (n=16). No larger pool of eligible personnel exists at this campus, so no further sampling was possible within these units.

First-year student evaluators are drawn using Slovin's formula at a 0.05 margin of error from an estimated population of 700 applicants per admission cycle (reduced from the typical 1,000 as a baseline for budgetary and operational constraints). This yields n ≈ 255 first-year students, stratified across 11 courses (~23 per course). Total evaluators: 255 first-year students + 16 administrative staff = 271.

The IT Expert role is removed from this study's evaluation entirely. The system's architecture, RBAC implementation, and data privacy compliance are validated through the iterative design review in SO2 and the development team's internal peer review process during Rapid Construction, not through a separate expert evaluation.

While NEXIAM is designed to support compliance with Republic Act No. 10173 through RBAC and application-layer data governance, formal NPC certification and the Privacy Impact Assessment fall outside scope. Applicant consent for digital processing is assumed under the existing ICAT application form. Formal verification is identified as future work.

---

## 3. REVISED RESEARCH INSTRUMENT

### TAM Instrument: PU + PEOU only

**Constructs measured:** Perceived Usefulness (PU) and Perceived Ease of Use (PEOU). Attitude Toward Use (ATU) and Behavioral Intention to Use (BIU) are removed. This follows the original Davis (1989) formulation where PU and PEOU are the primary determinants of user acceptance. ATU and BIU function as mediating constructs in the full TAM2/TAM3 extensions (Venkatesh & Davis, 2000; Venkatesh & Bala, 2008), which are designed for longitudinal adoption studies. For a single-shot post-implementation evaluation in a descriptive-developmental study, the two-construct model provides sufficient measurement while reducing respondent fatigue across the four role-specific variants.

**Role-based adaptation.** The instrument uses role-adapted phrasing because each respondent group interacts with different NEXIAM features. The four role groups and their feature access, as defined in the use case diagram (Appendix B):

- **Registrar Personnel (n=3):** Limited to applicant intake, record management, status visibility, and inter-office coordination. The Registrar does NOT interact with OMR scoring, scheduling, admission support dashboards, or proctoring.
- **Guidance Personnel (n=2):** Access to OMR scoring, AI-assisted scheduling, admission support dashboards, counseling tools, result sheet printing, schedule release, AND the Proctor duty overlay for exam monitoring. Guidance has the broadest feature surface of any role.
- **First-Year Students (n=255):** Applicant portal, chatbot, email notifications, result retrieval, identifier key login.
- **Program Heads (n=11):** Pre-enrolment lists, quota management, admission support dashboards, inter-office coordination.

**Administration.** Post-test only. Evaluators complete the instrument once after executing their role-specific simulation scenarios. No pre-test is administered because the study does not compare manual vs. system performance. The instrument captures absolute acceptance levels rather than a shift in perception.

**Scale.** Five-point Likert scale (1 = Strongly Disagree to 5 = Strongly Agree).

**Item count.** Items per role are determined by the features each role accesses, as defined in the use case diagram (Appendix B). Registrar and Guidance are separate respondent groups with different item counts reflecting their distinct feature access.

The items below represent the minimum set. Additional items may be added per role as the use case diagram is finalized.

### TAM Item Mapping — All Features Covered

#### Registrar Personnel (n=3)
*Evaluates: Staff interface — applicant intake, record management, status visibility, coordination with Guidance*

| # | Construct | Indicator probes... |
|---|---|---|
| 1 | PU | NEXIAM allows me to process applicant intake and registration efficiently |
| 2 | PU | NEXIAM helps me manage admission-related records faster |
| 3 | PU | NEXIAM gives me sufficient visibility into an applicant's status |
| 4 | PU | NEXIAM reduces my workload when coordinating with Guidance personnel |
| 5 | PEOU | NEXIAM's digital applicant intake and records are easy to navigate |
| 6 | PEOU | Learning how to use NEXIAM took little effort |
| 7 | PEOU | It is easy to correct mistakes in applicant records using NEXIAM |
| 8 | PEOU | Interacting with NEXIAM is generally clear and understandable |

*Registrar items are intentionally fewer (8 items: 4 PU + 4 PEOU) because the Registrar role is limited to admission intake and record management. The Registrar does not access OMR scanning, scheduling, admission support, or proctoring features.*

#### Guidance Personnel (n=2)
*Evaluates: Staff interface, OMR scoring, AI-assisted scheduling, admission support dashboards, result sheet printing, schedule release, AND proctoring interface (duty overlay)*

| # | Construct | Indicator probes... |
|---|---|---|
| 1 | PU | NEXIAM's admission support dashboard helps me evaluate applicant readiness for placement |
| 2 | PU | NEXIAM's admission support output provides useful profiles for counseling sessions |
| 3 | PU | NEXIAM gives me sufficient visibility into an applicant's status |
| 4 | PU | NEXIAM reduces my workload when coordinating with Registrar and Program Head personnel |
| 5 | PU | Automated OMR scoring saves time compared to manual stencil checking |
| 6 | PU | AI-assisted scheduling improves exam session planning |
| 7 | PU | Proctoring interface supports effective exam monitoring and attendance |
| 8 | PEOU | It is easy to navigate AI-assisted scheduling, OMR scoring, and admission support in NEXIAM |
| 9 | PEOU | Learning how to use NEXIAM took little effort |
| 10 | PEOU | It is easy to read and interpret the admission support profiles in NEXIAM |
| 11 | PEOU | Interacting with NEXIAM is generally clear and understandable |
| 12 | PEOU | Proctoring interface is easy to use during exam sessions |
| 13 | PEOU | Result sheet printing and schedule release are straightforward |

*Guidance items are more extensive (13 items: 7 PU + 6 PEOU) because Guidance has the broadest feature surface — OMR, scheduling, admission support, counseling, proctoring, and result processing.*

#### First-Year Students (5 PU + 4 PEOU = 9 items)
*Evaluates: Applicant portal, chatbot, email updates, result retrieval*

| # | Construct | Indicator probes... |
|---|---|---|
| 1 | PU | Applicant portal provides useful access to application status |
| 2 | PU | Chatbot gives helpful answers to admission questions |
| 3 | PU | Email updates keep me adequately informed |
| 4 | PU | Results and release sheet is clear and useful |
| 5 | PU | Identifier key access is convenient |
| 6 | PEOU | Applicant portal is easy to use |
| 7 | PEOU | Chatbot is easy to interact with |
| 8 | PEOU | Finding my exam results is straightforward |
| 9 | PEOU | Identifier key login process is simple |

#### Program Heads (5 PU + 4 PEOU = 9 items)
*Evaluates: Pre-enrolment lists, quota visibility, admission support dashboards, staff interface*

| # | Construct | Indicator probes... |
|---|---|---|
| 1 | PU | Pre-enrolment lists support timely program decisions |
| 2 | PU | Quota visibility provides accurate enrollment capacity picture |
| 3 | PU | Admission support dashboards surface useful aptitude patterns |
| 4 | PU | Staff interface improves coordination with Guidance |
| 5 | PU | Optimized exam schedules aid department planning |
| 6 | PEOU | Pre-enrolment list interface is easy to navigate |
| 7 | PEOU | Quota management controls are straightforward |
| 8 | PEOU | Admission support dashboards are clear and interpretable |
| 9 | PEOU | Staff interface is simple to operate |

#### IT Expert — REMOVED ENTIRELY
No IT Expert evaluation. Architecture validation occurs through SO2 iterative design review and internal peer review during Rapid Construction. No separate expert panel.

### Feature Coverage Audit

| IPO Output | Respondent Role | TAM Item # |
|---|---|---|
| Chatbot responses | First-Year Students | PU-2, PEOU-7 |
| Email updates | First-Year Students | PU-3 |
| Applicant portal | First-Year Students | PU-1, PU-5, PEOU-6, PEOU-9 |
| Staff interface (Registrar) | Registrar Personnel | PU-1, PU-2, PEOU-5 |
| Staff interface (Guidance) | Guidance Personnel | PU-3, PU-4, PEOU-11 |
| Optimized exam schedules | Guidance Personnel | PU-6 |
| Exam scores & prints | First-Year Students | PEOU-8 |
| Results & release sheet | First-Year Students, Guidance | PU-4 (FYS), PEOU-13 (G) |
| Pre-enrolment lists | Program Heads | PU-1, PEOU-6 |
| Admission support dashboards | Guidance, Program Heads | PU-1, PU-2, PEOU-10 (G); PU-3, PEOU-8 (PH) |
| Audit trails | Registrar, Guidance | Implicit in coordination items PU-4 |
| OMR scoring | Guidance Personnel | PU-5, PEOU-8 |
| Proctoring interface | Guidance Personnel | PU-7, PEOU-12 |

✅ All 13 IPO outputs have at least one dedicated TAM item across 4 role groups.

---

## 4. REVISED DATA ANALYSIS

**SO1 — Thematic Analysis.** Qualitative thematic analysis applied to semi-structured questionnaire responses and field observation notes from Guidance and Registrar staff. Responses are transcribed, coded, and organized into operational themes: scoring bottlenecks, inter-office coordination failures, data integrity vulnerabilities, and notification deficiencies. The resulting thematic structure is the empirical foundation from which every NEXIAM feature is derived.

**SO2 — Iterative Design Validation.** Completed modules are demonstrated to Guidance and Registrar staff during the construction cycle. Misalignments between system design and field-confirmed needs are logged as revision items. The functional requirements and system features tables constitute the Chapter 3 system design section and the reference structure for Cutover testing scenarios.

**SO3 — Descriptive TAM Means.** Each respondent's items per construct are averaged into a per-person construct score. These per-person scores are averaged across all evaluator post-test responses to produce the pooled mean for PU and PEOU. Means above the midpoint of the five-point scale (>3.0) indicate positive acceptance. Standard deviations per construct are reported alongside the means. Pooled means are reported with a breakdown of per-role descriptive means for each construct: Registrar Personnel, Guidance Personnel, First-Year Students, and Program Heads. Results are interpreted against established TAM benchmarks from the original Davis (1989) instrument and subsequent validation literature.

**Removed:**
- ~~SO4 paragraph~~ (paired t-test, Shapiro-Wilk, Wilcoxon, α = .05, effect size)

---

## 5. CASCADE CHECKLIST

| Document | Changes |
|---|---|
| **Objectives** | 3 objectives (SO1: Process Analysis, SO2: System Requirements, SO3: TAM Evaluation). SO2 includes RAD development methodology discussion — no standalone development objective. "Four respondent groups": Registrar, Guidance, First-Year Students, Program Heads. |
| **Null Hypotheses** | Delete H₀₁ and H₀₂ entirely. |
| **Conceptual Framework** | Remove "paired TAM measures" and statistical comparison. Replace with "descriptive TAM acceptance post-test." Update IPO diagram with Phase 2 items. |
| **Scope** | Replace current with Phase 2 version (objectives-aligned structure). Remove all IT Expert references. Add Slovin's formula for applicant sampling. Separate Registrar and Guidance scope per feature access. |
| **Research Design** | Update to say "Specific Objectives 2 and 3" — SO2 (requirements) and SO3 (TAM). Development methodology discussed within SO2. Remove 'proxy' → 'simulated evaluators.' |
| **Population** | Total n=271 (16 admin + 255 Slovin's). Admin: 11 Program Heads, 3 Registrar, 2 Guidance. Remove IT Experts (n=3). Replace "30 first-year students (purposive)" with Slovin's sample. Registrar and Guidance as separate rows in Table 2. |
| **Research Instrument** | Replace old 16-table structure with 4 role-specific tables (Registrar 8 items, Guidance 13 items, FYS 9 items, PH 9 items). PU+PEOU only. 5-point Likert. Post-test only. Remove all pre-test indicators. Remove IT Expert tables. |
| **Data Analysis** | 3 objectives: SO1 = process mapping, SO2 = iterative design validation (RAD phases documented within), SO3 = descriptive TAM means (midpoint 3.0, per-role breakdown for 4 groups). No standalone development data analysis section. Remove IT Expert qualitative reporting. |
| **Presentation slides** | Update Slide 4 (Objectives — remove SO4). Update Slide 5 (IPO — Phase 2). Update Slide 11 (Population — 4 groups, no IT Expert, Slovin's). Update Slide 12 (Instrument — 4 role tables, 5-point, 2 constructs). Update Slide 13 (Data Analysis — remove SO4). |
| **Speaker script** | Remove SO4 lines. Update TAM description to 4 groups. Remove IT Expert mentions. |
| **Q&A Defense Bank** | Remove traps about t-test, Wilcoxon, normality, effect size, IT Expert. Add trap: "Why no pre/post comparison?" Add trap: "Why PU+PEOU only?" Add trap: "Why role-based TAM?" Add trap: "Why no IT expert evaluation?" Add trap: "Why Slovin's for applicants?" Add trap: "Why separate Registrar and Guidance TAM?" |
| **Glossary** | Remove "Paired T-Test," "Shapiro-Wilk Test," "Wilcoxon Signed-Rank Test." Remove "Pre-Test." Update "Post-Test" to single-administration. Update "TAM" to 2 constructs, role-adapted, 5-point Likert, post-test only. Remove "IT Expert" entry. Update "Purposive Sampling" to mention Slovin's for applicants. Update "Applicant Portal" to mention identifier key. Add "Identifier Key," "Staff Interface," "Slovin's Formula." Rename "Aptitude Pattern" → "Admission Support Dashboard."

## 6. PENDING DECISIONS

| # | Item | Status |
|---|---|---|
| 1 | Slovin's margin of error | ✅ **e=0.05** → n≈255 applicants. Stratified across 11 courses (~23 per course). |
| 2 | Final applicant sample size | ✅ **255** (Slovin's, e=0.05, N=700). Total evaluators: 255 + 16 admin = **271**. |

## 7. METHODOLOGY NOTES *(for Chapter 3 drafting)*

### SO1 — Process Analysis
- Produce **flow charts and process diagrams** to visualize the current admission workflows
- Baseline pain points surfaced from questionnaire and field observation
- Diagrams serve as the "before" state — the operational baseline NEXIAM replaces

### SO2 — System Requirements & Development
- **Main text:** System features table, functional requirements derived from SO1 pain points
- **Appendices:** NFR/FR tables, ERD, DFD, use case diagrams — these are the "plates" (deliverables) but not the main discussion
- **User Design feedback:** Mockups and feature prototypes shown to Guidance/Registrar staff during User Design phase. Preference questions ("do you need this or prefer that?") captured as iterative validation
- **AI usage discussion:** Since there is no separate "development" objective, discuss AI-augmented development within SO2. AI is presented as the method, not the research focus — it accelerates coding under the RAD model, but the study is about the system that results, not the AI tools used to build it

### Software Model (RAD)
- **Primary framing:** RAD model with strongest published backing — Martin (1991), Pressman & Maxim (2020), Olipas (2020), Melendres & Aranda (2024)
- **AI mention:** Can discuss how AI agents were used (agentic development per Osmani et al., 2026) but this is secondary. AI is the tool, RAD is the model. The study's contribution is the integrated admission system, not the AI development methodology
- **Key positioning for defense:** "We used AI to build faster. The panel evaluates the system, not the tools we built it with."
