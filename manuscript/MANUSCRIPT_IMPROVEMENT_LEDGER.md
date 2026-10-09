# NEXIAM Manuscript Improvement Ledger and Methodological Decisions

**Date Recorded:** 2026-10-02  
**Hard Project Deadline:** Monday  
**Capstone Adviser:** Dr. Zeus Vincent Mendoza  
**Researchers:** David D. Sarmiento, Jaypee G. Pagaduan, C. M. Lopez  
**Document Purpose:** Persistent tracking ledger of methodological justifications, operational decisions, and queued refinements to be integrated into the final manuscript.

---
## 0. Manuscript Alignment Mission and Section-by-Section Checklist

**Meta-mission:** Keep the manuscript and NEXIAM aligned. The documenter works with the system developer to describe the project accurately, from its objectives and methods to its features and evaluation. Clearly separate features that work from features that are planned or not yet verified.

**For each manuscript section:**
- [ ] Read the matching original-manuscript section using `MANUSCRIPT_REFERENCE_INDEX.md`; identify the section's purpose and each paragraph's job.
- [ ] Compare the original, Yukee's matching presentation, and the current section. Keep useful approved changes; do not add system facts merely because they exist.
- [ ] Check relevant claims against the objectives, approved decisions, and available evidence.
- [ ] Ask the developer to confirm unclear or changing details.
- [ ] Record mismatches, missing evidence, and decisions in this ledger.
- [ ] Show proposed paragraph and table changes to the researcher and obtain approval before editing manuscript text. Do not use BMAD skills for capstone manuscript work.
- [ ] Update the section in clear, simple language.
- [ ] If verified evidence shows that a section needs a change, update that matching manuscript section, not only this ledger.
- [ ] Check that terms and claims agree across related sections.
- [ ] Mark the section reviewed only after the alignment check is complete.

**Progress:** SO1's two existing diagram drafts are rendered and placed beside their discussion as Figures 3.1 and 3.2. Full technical-diagram slots are now only in Appendices C to F; the researcher's existing versions will be selected later. Full TAM questions are only Appendix J, with long coverage/task maps in Appendix N and a concise Chapter 2 explanation. All 38 items are unchanged. Next: visual review of the current-flow chart, then selection of an existing macro/activity diagram for the NEXIAM-supported side; no measured before/after improvement is claimed.

---


## 1. Evaluator Cohort Asymmetry and Disaggregated Defense

**Historical cohort note:** The three-group, 260-person plan below is superseded by the researcher correction recorded in §9. Do not use it as the current sample plan.

### Context and Rationale
In institutional admissions systems, the user population is naturally asymmetric: a large end-user client cohort (incoming applicants and examinees) alongside a small, specialized operational cadre (Guidance and Registrar personnel). 

In NEXIAM, the evaluation sample consists of:
* First-Year Student Examinees: $n = 255$ (Slovin sample from $N = 700$ at $e = 0.05$)
* Registrar Office Personnel: $n = 3$ (Complete purposive census)
* Guidance Office Personnel: $n = 2$ (Complete purposive census)
* Total Evaluation Sample: $n = 260$

### The Three Methodological Firewalls Against Drowning Bias
To prevent the larger student sample from statistically obscuring or overwhelming administrative feedback, the study implements three research controls:
1. *Strict Role-Adapted Measurement (Lippert & Forman, 2005; Holden & Karsh, 2010):* Evaluators evaluate only the capabilities they directly operated. Students answer 9 questions probing the self-service portal, chatbot, Reference Code login, and result cards. Staff answer 8 (Registrar) and 13 (Guidance) questions probing intake queues, OMR scoring, TabPFN triage, and eligibility boards. No cross-role evaluation occurs.
2. *Complete Purposive Census for Administrative Staff (Babbie, 2020):* Because only 5 individuals staff these two operational units at ISPSC Tagudin Campus, surveying all 5 constitutes a 100% census ($n = N = 5$), reducing administrative sampling error to zero ($e = 0$).
3. *Disaggregated Reporting and Independent Interpretation:* Analytical findings in Chapter 3 (Tables 3.5, 3.6, and 3.7) are reported in independent, dedicated rows by respondent role rather than as a single pooled aggregate. This preserves the operational voice of the administrative staff as an independent dimension of system quality.

### Ready-to-Inject Paragraph for Section 2.4 and Section 3.3
> "The evaluation cohort reflects the operational structure of an institutional admission platform, characterized by an asymmetric user distribution: a large client stakeholder body (incoming students) and a small, specialized administrative operations cadre (Registrar and Guidance personnel). To prevent the larger student sample from statistically drowning out administrative feedback, the research design couples complete purposive census sampling of all available campus administrative staff (n = N = 5) with stratified sampling of student examinees (n = 255). Analytical results are disaggregated across operational roles, ensuring that administrative efficacy and student usability are evaluated as independent operational dimensions."

---

## 2. Hard Deadline and Timeline Calibration

* **Target Submission Deadline:** Monday.
* **Adviser Engagement:** The upcoming consultation with Dr. Zeus Vincent Mendoza serves as the definitive comprehensive checkpoint. All relative time qualifiers (such as "tomorrow") are excised from manuscript text in favor of absolute milestones and cycle references.

---

## 3. Personnel Attribution and Data Privacy Governance

* **Guidance Counselor Identification:** In accordance with Republic Act No. 10173 (Data Privacy Act of 2012) and institutional research norms, institutional personnel are referenced strictly by their official functional title: **`Guidance Counselor / Test Administrator`**.
* **Super Administrator:** Formally documented as an emergency break-glass system capability outside routine administrative evaluation.

---

## 4. Pending Document Assets and Deferred Items

* **Appendix O (Biographical Sketches):** The source text has been prepared by the authors and will be integrated upon receipt prior to final binding.
* **Visual Plate Ingestion (Chapter 3 SO2):** High-resolution screenshots captured from the running deployment environment (`http://localhost:18000`) will populate the designated anchors `[PLATE 1]` through `[PLATE 14]`.
* **Optional IT Expert Evaluation Pillar:** Documented protocol to incorporate $n = 5$ external IT experts evaluating ISO/IEC 25010 software quality dimensions if specifically requested by the advisory committee during final consultation.

---

## 5. Admission Support Model Evolution: K-Means vs. TabFM and the Dual Guidance Interface

### 5.1 Architectural Contrast with Yukee et al. (2025)
A central contrast to highlight during oral defense lies between the exploratory retrospective clustering of Yukee et al. (2025) and the operational decision-support architecture of NEXIAM:
1. *What Yukee et al. Implemented for Insights:* Their script generated textual advisory notes using rigid boundary thresholds (such as `if avg_x > overall_mean_x: "higher than overall mean"`). This approach represents hard-coded rule checks layered on top of two-dimensional demographic scatter plots.
2. *The Operational Defense for TabFM:*
   * *Avoids Rigid, Brittle Thresholds:* Static if/else rules cannot balance compensatory strengths (for example, an examinee slightly below a mathematics cut-off who possesses exceptional spatial reasoning and a secondary STEM background).
   * *Continuous Multi-Variable Trade-Offs:* TabFM evaluates multi-dimensional aptitude vectors continuously, outputting calibrated 0 to 100% compatibility scores and explicit feature drivers rather than a binary pass/fail gate.
   * *Ethical Human-in-the-Loop Governance:* A hard-coded if/else acts as an automated gatekeeper, whereas TabFM provides continuous advisory indices that support rather than supplant the Guidance Counselor's professional discretion.

### 5.2 The Dual Guidance Interface: TabFM Ranking + Aptitude Radar Polygon
In the Guidance consultation interface (Plates 9 and 10), decision support is structured as a complementary visual pair:
* **Component 1 (The Match Engine: TabFM):** Provides the sorted numerical ranking (for example, #1 BSIT: 88% Match; #2 BSCS: 81%; #3 BSEd: 54%), delivering fast, sorted triage based on tabular foundation models.
* **Component 2 (The Visual Evidence: Aptitude Radar Polygon):** Displays the multi-axis shape comparison, overlaying the applicant's six ICAT percentile scores against the program's exemplar benchmark polygon stored in `admission_benchmark_sets` (seeded via `AdmissionBenchmarkSeeder.php`).
* *Counseling Utility:* This dual design bridges complex machine learning and intuitive advising. The counselor does not need to explain Bayesian priors or transformer attention to an examinee; they point directly to the multi-axis radar chart: *"Observe how your aptitude shape fills out the Numerical and Spatial corners required for Information Technology."*

### 5.3 Chapter Alignment Strategy Across the Manuscript
1. *Chapter 1 (Background):* Cites Yukee et al. (2025) as proof that machine learning is feasible on ISPSC ICAT data, while noting its limitation as a retrospective, disconnected exercise without live counseling integration.
2. *Chapter 2 (Methodology & Literature Synthesis):* Contrasts unsupervised clustering against tabular foundation models: unsupervised clustering (Yukee et al., 2025; Tan, 2025) is descriptive and groups historical batches, whereas tabular foundation models (TabPFN; Hollmann et al., 2025) prescribe individual fit through in-context inference.
3. *Chapter 3 (System Requirements & SO2):* Documents the Dual Guidance Interface: TabFM computes the ranked Course Match Index (0 to 100%), while the Aptitude Radar Profile provides the visual multi-axis benchmark comparison.
4. *Recommendations:* Formulates the actionable institutional recommendation: profile fourth-year graduating seniors and professional licensure board passers to establish empirical graduate centroids for the radar benchmarks.

### 5.4 Panel Defense Script: "Why Not Kuya Yukee's K-Means Model?"
When a panelist inquires: *"Why did you not directly reuse Yukee et al.'s trained K-Means model?"*, the defense response is:

> "Yukee et al. (2025) provided an essential local proof-of-concept proving that machine learning can process ICAT scores at ISPSC Tagudin. However, their implementation was an exploratory, descriptive tool that ran two-dimensional clustering across demographic slices (such as Sex versus Aptitude) on batch uploads, rather than an operational recommendation engine.
>
> Because unsupervised clustering discovers historical clumps rather than program suitability, NEXIAM advances the campus decision support to Tabular Foundation Models (TabPFN). We evaluate applicants against approved institutional benchmark exemplars with explainable feature drivers, accompanied by an Aptitude Radar Profile that visually maps student strengths against program thresholds. Furthermore, we recommend expanding these benchmarks by empirically profiling graduating cohorts and board exam passers."

---


## 6. Confirmed Conceptual Framework Revisions (2026-10-04)

**Status:** Applied to `manuscript/sections/01_chapter_1_introduction/01_2_conceptual_framework.md`.

- Retain the original three Input groups and four Output groups. Keep each diagram item to three or four words, except the Process box, which contains only the exact capstone title.
- Stakeholder Data & Preferences: Applicant Profiles; Program Preferences; Admission Inquiries; Current Process Records.
- Institutional Parameters: Admission Policies & Requirements; Academic Calendar; Program Benchmarks & Quotas; Testing Venues.
- Development Foundations: Answer Sheets, Conversion Tables; OMR Library; Admission Support Models; AI Framework. The Guidance Office is the source for the answer sheets and conversion tables.
- Do not list staff access rules or record-keeping as inputs. Do not include survey responses as an input or User Acceptance Findings as an IPO output.
- Communication outputs: Inquiry Replies and Status Notifications. Do not add delivery-status detail. Use Released Result Sheets rather than technical print/delivery states.
- Program benchmarks inform program-match support; quotas represent capacity. They are not, by themselves, formal admission requirements or automated admission/placement decisions.
- Keep the TAM evaluation in the process description under Specific Objective 3, not as an Output bullet.

## 7. Section 1.3 Alignment Note (2026-10-04)

- Updated Specific Objective 2 to match the approved objective: determine system requirements and features needed to address identified process gaps. The RAD development process remains described within SO2, not as a separate objective.
- Updated Specific Objective 3 to name the four respondent groups specified in `NEXIAM_Panel_Decision_Log.md`: Registrar Personnel, Guidance Personnel, First-Year College Students, and Program Heads.
- Removed the researcher consultation note about whether to split SO2; it is superseded by the panel decision that retains three objectives and discusses RAD within SO2.
- **Current status:** Academic leaders are included as a fourth respondent group. The researcher has corrected their sample to three, one from each of CAS, CBME, and CTE. See §9 for the planned total and the remaining student sampling checks. Section 3.3 still needs alignment.

## 8. Section 1.4 Alignment and Sampling Confirmation (2026-10-05)

- Reorganized Section 1.4 around the verified admission-testing scope and RBAC roles.
- Restored the six ICAT cognitive aptitude areas (General Ability, Verbal, Numerical, Spatial, Perceptual, and Manual Dexterity), Senior High School academic background (strand and Grade 12 GWA), and the pre-trained TabPFN foundation model adapter with formal citation (Hollmann et al., 2025).
- Re-anchored decision support strictly to advisory recommendations: the system holds no autonomous authority to admit, set quotas, or enroll; all course recommendations require explicit Guidance Counselor confirmation, while quotas remain departmentally managed.
- **Superseded sampling assumption:** The earlier count of eleven academic leaders incorrectly treated degree programs as departments. The researcher corrected this to three departments: CAS, CBME, and CTE. See §9; do not use the former 271-person total.

## 9. Section 2.4 and Department Sample Correction (2026-10-05)

- Researcher decision: purposively select one academic leader from each of CAS, CBME, and CTE. Each representative may be a Program Head or a Dean with relevant admission/advising responsibilities. Do not select both for the same department.
- Current planned sample: Registrar personnel 3; Guidance personnel 2; Academic Program Heads or Deans 3; first-year students 255. Planned total: $n = 263$, conditional on retaining the current student target.
- Updated Section 2.4 and Table 2.2, and corrected the respondent wording and sample statement in Sections 1.3 and 1.4. Kept the scope's TabPFN discussion and Hollmann et al. (2025) citation unchanged.
- First-year students are included because they recently completed the admission examination and related steps at the start of the previous semester. They evaluate applicant functions through simulated tasks, not live admission participation or a measured pre/post comparison.
- Registrar and Guidance sampling is a complete census of eligible staff in those offices. Academic leadership selection is purposive. A staff census does not remove response or measurement error.
- **Student sampling checks still open:** verify whether the estimated 700 applicants is a defensible basis for the eligible first-year student sample; establish the selection list, program allocations, and actual recruitment/random-selection procedure. The retained formula produces a target of 255, not proof of valid population coverage.
- **Updated section status:** Section 2.5 and Appendix J now include the academic-leader instrument; Section 2.6 and Section 3.3 describe four-group analysis and reporting. Data collection and instrument review/pilot are not claimed complete.
- **Writing workflow:** no BMAD skills for capstone work. Use the project manuscript guidance; preserve relevant research citations and technical content. Show paragraph/table proposals first and edit only after researcher approval.

## 10. Approved Task-Based TAM Evaluation Boundaries (2026-10-05)

- Added Office Coordination to the IPO Communication group. Its three bullets are Inquiry Replies, Status Notifications, and Office Coordination: two words each. Other diagram items and the exact-title Process box are unchanged.
- Evaluated institutional workflow: staff-managed examination scheduling, use of prepared scoring, examination attendance, face-to-face consultation with physical result-sheet delivery, applicant portal/inquiry/notice tasks, and scoped departmental communication.
- Researchers prepare answer-sheet design/mappings, answer keys, score conversion, and benchmarks. These are preparation activities, not staff TAM tasks. Answer-sheet building, mark-configuration validation, uncertain-mark adjudication, applicant slot selection, online consultation, and separate online result release are excluded from this evaluation. Exclusion is not a negative finding about a feature.
- Guidance and the Program Head/Dean use a paired synthetic applicant-thread exchange. Registrar evaluates authorized handoff information, not restricted thread access. Academic leaders also review capacity, applicants, and referrals within their assigned scope.
- Kept TabPFN named in relevant instrument items and left the scope's Hollmann et al. (2025) citation intact. TAM does not establish model performance, scoring accuracy, or legal compliance.
- Revised instrument: Registrar 8 items (4 PU, 4 PEOU); Guidance 13 (7 PU, 6 PEOU); students 9 (5 PU, 4 PEOU); academic leaders 8 (4 PU, 4 PEOU). Total catalog: 38 items; each participant answers only their role's items. More Guidance items do not give that group extra statistical weight.
- Section 2.5 and Appendix J use matching item text. Appendix N supplies corresponding task cards and separate observation records. Table 2.6 now contains academic-leader items; coverage is Table 2.7; the Role → Task → TAM item map is Table 2.8. Updated the table list and appendix inventory accordingly.
- Analysis: separate per-person PU and PEOU; four group results primary; respondent-weighted pooled scores secondary. Use actual valid counts. No raw score addition, special Guidance weight, or combined PU/PEOU score.
- Missing-data rule: a construct mean requires all assigned construct items answered. Report incomplete constructs and reasons separately; never replace blanks with Neutral. Participants may rate a function they attempted even if its task failed. Untested, unavailable, and unauthorized functions remain unanswered. Record all failures and help separately so incomplete questionnaire data do not hide problems.
- Use plain agreement labels; do not treat 3.00 as a pass mark or claim acceptance from an unsupported verbal band. Very small staff groups limit generalization and require care against identifying individual respondents.
- **Before collection:** confirm student population/recruitment, obtain content review and pilot evidence, check the real evaluation fixture and role permissions, and establish the scoring/result-sheet/communication path. These document edits do not certify runtime readiness or completed research.
- **Document checks passed:** Section 2.5 and Appendix J contain the same 38 unique item statements; all item codes appear in the role/task map and facilitator protocol; role counts are 8/13/9/8; the planned sample sums to 263; each Communication bullet has two words; the scope retains Hollmann et al. (2025). These checks establish document consistency only, not instrument validity or runtime performance.

## 11. Chapter 2 and Chapter 3 Review Preparation (2026-10-05)

- Researcher instruction: prepare the chapter narrative and exact insertion locations now; replace pending material later with approved documentation, diagrams, plates, and collected results.
- Preserve the modular section files as the editing source. Provide separate Chapter 2 and Chapter 3 reading copies for review; do not treat those copies as independently maintained manuscripts or compile a final submission prematurely.
- Use the available Yukee et al. (2025) manuscript as a presentation reference: methodology sections in order, findings organized by objectives, numbered diagrams, and plates followed by captions and explanatory paragraphs. Do not adopt its CRISP-DM method, SUS scoring, results, or unverified claims. NEXIAM retains RAD and TAM.
- Approved placeholder types: `[FIGURE n: exact approved diagram/chart to insert]`, `[PLATE n: verified screenshot and visible details to insert]`, `[TABLE n: actual data and fields to insert]`, and `[EVIDENCE: source or behavior to confirm, with the associated text to revise]`.
- Pending evidence belongs at its actual insertion location, not only in this ledger. A screenshot placeholder does not prove a working feature, a model result, or a performance improvement.
- Keep the source-backed SO1 findings separate from the future TAM evaluation. Identify the denominator and cohort for applicant-survey percentages; the 22 baseline respondents are not the planned 255 student evaluators.
- Broader system documentation and the evaluated task subset must remain distinct. Researcher-prepared setup and optional modes can be documented without claiming staff acceptance of those functions.
- Preserve the TabPFN literature and Hollmann et al. (2025) citation, while reserving a precise check for the actual engine/backend and benchmark evidence. Do not infer model performance from a literature citation or TAM rating.
- Final integration must replace the approved actor/feature mappings, insert redacted or synthetic screenshots, confirm timeline and role assignments, reconcile student sampling, record instrument review/pilot evidence, and populate actual group results and evidence-based conclusions.
- Page numbers are assigned after final layout. Draft catalogs must not present old page estimates as verified pagination.
- **Reading copies prepared:** [Chapter 2: Methodology](NEXIAM_Chapter2_Methodology_REVIEW.md) and [Chapter 3: Results and Discussion](NEXIAM_Chapter3_Results_Discussion_REVIEW.md). The copies combine current section text for reading only; editing continues in `manuscript/sections/`.
- Reserved Figure 2.1 (RAD) and Figure 2.2 (schedule); Figures 3.1 to 3.3 (process inquiry and draft traceability); Figures 3.4 to 3.7 (use case, DFD Level 0, DFD Level 1, ERD); Plates 1 to 15; and the four reporting tables 3.5 to 3.8. Plate 15 covers Office Coordination.
- The Chapter 2 analysis text now explains the selected procedures without the former subsection defending removed statistical tests. The instrument's 38 item statements are unchanged.
- Source corrections retained: n = 22 refers only to the two applicant-survey rounds (12 on 28 August and 10 on 29 September), not to staff and interview participants combined. The n = 10 first-year CBME cohort supplies its own percentage denominator. Its queue table has one respondent waiting one to three hours and one waiting over three hours, not two over three hours.
- Timeline preparation uses June to October as five calendar months and includes both recorded applicant-survey dates. The remaining activity dates and named assignments still require records.
- Section 3.2 keeps the FR-01 to FR-13 identifiers but labels detailed mapping and implementation evidence as pending reconciliation. Optional functionality is distinguished from the evaluated subset, not claimed removed. Conclusions and recommendations reserve their evidence-dependent text.
- **Whole-chapter checks passed:** both reading copies reproduce their current section sources; the catalog matches 16 table captions, nine Chapter 2/3 figure slots, and 15 plate slots/captions. All checked table/figure references resolve, code fences are balanced, and chapter source prose contains no em dashes. The 38 instrument statements still match Appendix J; the planned sample arithmetic is 263. These are preparation and consistency checks, not completed source validation, instrument validation, or evaluation.

## 12. Reusable Slot Metadata and Browser Reader (2026-10-05)

- Researcher request: provide simple accurate metadata for later diagram/visual drafting and a browser reading/download interface so the team does not need SSH or VS Code to consume the drafts.
- Added 28 working-note blocks beside nine figure slots, fifteen plate slots, and four result tables. Each uses **Brief** (what to show), **Source** (the known reference), and **Confirm** (what still needs evidence). These notes are preparation metadata, not final manuscript prose.
- Technical diagram briefs include the reference actors, process/store outline, or entity groups already recorded. Connections, branches, keys, and cardinalities still require approved mapping. The newer Appendix O register is available as a documentation supplement, not institutional approval.
- Plate notes are instructions to capture real interfaces with synthetic/redacted data, not prompts to generate fake screenshots. Result notes require collected responses and task records, not invented sample values.
- Reader ownership: `openspec/changes/manuscript-browser-review/`. Implementation and access/update instructions are in its `HANDOFF.md`. No BMAD writing workflow was applied to the manuscript.
- The reader is a static snapshot on the existing Compose HTTP service. A persistent unguessable share path limits casual discovery, but anyone with that link and network access can read the published documents. No staff login is required and no internet tunnel was opened.
- Publication uses a fixed manifest, sanitizes rendered Markdown, and excludes unrelated repository files, runtime credentials, and database content. The private share token and generated assets are not tracked. Republish after source changes; do not edit browser output or the reading copies as separate sources.
- Verification: 28 complete metadata blocks with existing named sources; 21 published documents; two focused safety tests passed; strict OpenSpec validation passed; desktop/mobile reading and navigation exercised at 1440 and 390 px without page overflow; real section/full-chapter downloads matched source bytes. Research validity, final diagrams, provider readiness, and completed TAM collection are not claimed.

## 13. Ngrok Sharing (2026-10-05)

- Researcher requested use of the existing ngrok tunnel for team access. The tunnel already forwards to the Compose app on port 18000; no new tunnel or application route was created.
- Public reading and download controls were verified. Ngrok initially returned its warning HTML for a Markdown request; the reader now uses the documented warning-bypass request header and downloads the actual manuscript content. Three focused tests pass, including the failing-before/passing-after regression.
- Anyone with the full public reader link can read the published snapshot while the tunnel is available. Keep the share token outside tracked documents. Republish and reload after approved manuscript edits.
- Resume manuscript review; the reader change does not alter the study design, instrument, sampling decisions, or pending evidence.

## 14. Approved Research Design and Shared Documentation Role (2026-10-05)

- Researcher approved editing Section 2.1 and the project-role correction, while retaining section-by-section review. Do not treat the earlier purpose audit as approval to rewrite all sections.
- Section 2.1 now explains the descriptive-developmental relationship in two paragraphs. Inquiry sources are generic: questionnaires, interviews, field observations, and applicant journey surveys. Removed detailed office/role lists, baseline counts, the objective-by-objective walkthrough, and production-status prose from this overview. Those details remain in their owning sections.
- Restored the original five-role assignment structure in Table 2.1 and its documented names. Technical Writer and Documenter is one role shared by David Datu N. Sarmiento, Jaypee G. Pagaduan, and Christine M. Lopez. Removed the inferred separate UI/UX, ML/CV, and DBA job-role expansion. The project schedule and other manuscript sections were not revised in this cut.
- Persisted the original reference conversion at `archive/Final Sweep Revision Capstone/Manuscript Final.reference.md`, preserving historical wording, tables, and visual placeholders. It is not the current manuscript and does not restore obsolete SO4, paired tests, K-Means, or old samples.
- `MANUSCRIPT_REFERENCE_INDEX.md` defines the review loop: original section purpose → Yukee presentation → current section → keep/shorten/move/confirm → researcher approval → edit → publish and verify.
- Retain relevant citations and good newer choices. No additional roles, features, methods, or technical detail may be inferred from the system implementation alone.
- **Publication verified:** snapshot `2026-10-05T07:54:15.465Z` is live on the same reader link. Folder sections and the generated Chapter 2 copy match published Markdown. Actual ngrok reading confirmed the two-paragraph Research Design and five-row role table with all three names in the shared documentation role; browser errors were empty.

## 15. Researcher-Identified Defended Manuscript (2026-10-05)

- Primary defended baseline: [NEXIAM Capstone Manuscript.docx](https://docs.google.com/document/d/1JfYM1sM7sv3dwAsq7z_fto-aHOpUef43/edit#heading=h.a8ee0bfvnud6), identified by the researcher as the version used before the defense and its subsequent notes.
- The full Google Doc text export has now been retrieved. It is not identical to the local ODT: its July cover and three objectives differ from the local June/four-objective version. Current objectives/framework are partly revised, while Research Design, Scope, Cutover, and Data Analysis retain some SO4/paired-test language. Resolve these by section; do not restore obsolete methods.
- Review the defended manuscript and applicable defense notes together. Notes may not yet be applied to that document. Use Yukee additionally for section purpose and presentation, not as the sole manuscript authority.
- Preserve good current revisions and explicit researcher decisions. This clarification records the reference workflow only; no manuscript paragraphs or browser content were changed.

## 16. Next Review: Section 2.2 and Restored Reading Link (2026-10-05)

- Compared the current Software Model with the defended Google Doc, consolidated defense notes, and Yukee's actual model/phase explanation. RAD remains NEXIAM's development model; AI tooling is secondary. Yukee provides a presentation pattern, not CRISP-DM or SUS for NEXIAM.
- Proposed direction: retain the RAD introduction and relevant citations; explain each phase briefly; move the detailed technology inventory, scoring setup, and TabPFN explanation to the appropriate system-feature/literature discussion, preserving citations. Keep working evidence/visual metadata outside final prose.
- No Section 2.2 changes applied before researcher review.
- The old ngrok URL stopped. Restored `ManuscriptReviewNgrok` persistently with public base `https://74a4-136-239-226-74.ngrok-free.app`, using the existing reader path. Browser verification loaded 21 documents and all four Section 2.2 phase headings without errors; first-time visitors may need **Visit Site**.
- The reader is a review snapshot, not a completed defense-ready manuscript. Final DOCX preparation follows source reconciliation, approved diagrams/captures, actual evaluation results, and final consistency/layout checks.

## 17. Compact Prose, Student Selection, and Inclusion Discussion (2026-10-05)

- Researcher correction: main-section narrative must be compact paragraph prose, with optional short paragraph labels, not nested decimal subheadings such as 3.3.3. Follow the institutional template and defended/reference manuscripts. Required tables, captions, objectives, and questionnaire formats remain separate.
- “Confirm the student list…” is a team-check instruction, not an academic method paragraph. Move such checks to this ledger/working metadata during approved section revision. Do not merely erase the unresolved population issue or claim a sampling procedure that was not used.
- The first-year rationale already exists in Section 2.4. State it once near the population introduction: they recently completed the admission examination/process and bring that experience to simulated applicant tasks. Cutover should describe the simulation and post-task evaluation, not repeat the full rationale.
- **Student sampling proposal, not yet locked:** simple random selection within the eligible first-year group is viable if a complete eligible list is available and a real random draw is used. Volunteers/available students are not automatically a simple random sample. No equal program allocation is implied. Actual eligible population and recruitment/nonresponse remain checks before final sample arithmetic is adopted. Staff census and one purposively selected academic leader per CAS/CBME/CTE are unchanged.
- Sampling source: [Statistics Canada, Probability sampling](https://www150.statcan.gc.ca/n1/edu/power-pouvoir/ch13/prob/5214899-eng.htm). Its SRS definition requires an eligible population list and equal selection chance.
- **Proposed inclusion context:** digital access (device/connectivity) differs from digital literacy (ability to use digital services). Do not infer either from travel statistics or add a digital-literacy outcome to the PU/PEOU study without a separate approved design.
- SDG context source: [UN Goal 4](https://sdgs.un.org/goals/goal4), particularly Target 4.3 on equal access to tertiary education. Describe alignment/design intent only; the current TAM evaluation does not measure SDG achievement.
- Future no-device/staff-assisted pathways are a topic for later system/policy review. They are not declared implemented or evaluated in the manuscript. Evaluation equipment and neutral assistance can be planned so personal device ownership is not an arbitrary participant exclusion; record help without coaching questionnaire answers.
- No broad manuscript restructure, new questionnaire construct, sampling change, or system implementation applied in this discussion. Review the compact paragraph proposals before editing the relevant sections.

## 18. Messaging Channel Wording Correction (2026-10-05)

- Researcher approved “online messaging” for the Guidance scheduling channel. The CSV selected option is “Official Messaging App (e.g., Viber, Messenger)”; its examples do not establish use of both named apps.
- Updated the Chapter 3 process-analysis paragraph and Appendix H summary only for that channel wording. Do not substitute Messenger based on general local usage. Raw questionnaire data and the separate quota-communication wording remain unchanged.

## 19. Section 2.2 Compact Revision and AI-Assisted RAD Verification (2026-10-06)

- Updated Section 2.2 to compact paragraph format. Removed subheadings (3.3.3-style nesting) and inline team instructions, keeping RAD as the development model and placing AI assistance inside Rapid Construction.
- Rapid Construction states that AI-assisted coding is followed by researcher review, testing, and revision before integration, citing Osmani et al. (2026) for the verification emphasis. Removed separate productivity, security, and compliance guarantees; literature support describes the method, while actual system results require project records.
- Figure 2.1's diagram draft and slot metadata show AI assistance inside Phase 3 with feedback to User Design; no fifth phase was added. Updated the figure catalog entry.
- Detailed technology stacks, scoring setup, and the TabPFN explanation belong in Chapter 3 and literature references, where they remain with their citations.

## 20. Approved Project Plan Period and Deployment Milestone (2026-10-06)

- Researcher confirmed June–October 2026 as the project period and 6 October as the deployment milestone. Updated Section 2.3 to the approved single-paragraph project-plan wording.
- Removed the numbered phase list and repeated applicant-survey dates from the Project Plan narrative; the source survey records remain unchanged.
- Figure 2.2 retains its image slot and now records the deployment milestone. Remaining phase spans and evaluation dates must use actual or approved records, not inferred durations. Deployment and completed user evaluation are separate.
- Project Assignments uses a paragraph label. Table 2.1's five roles, names, and the shared Technical Writer and Documenter role are unchanged.
- **Publication verified:** Section 2.3 was republished and checked through ngrok. The June–October paragraph, 6 October deployment milestone, and unchanged five-row role table are visible; no nested subheadings or browser errors were found.

## 21. SO1 Visuals and Main-Text/Appendix Placement (2026-10-06)

- Researcher approved readable diagrams placed beside the owning discussion, with caption and a short explanation. D2 is already installed and renders the SO1 analysis-sequence and reported current-process charts to editable source, SVG, and PNG.
- Figure 3.1 summarizes source-separated process analysis; Figure 3.2 shows reported paper submission/review, staff-managed batches, paper ICAT, manual stencil scoring, and face-to-face results/counseling. Program Head quota information is an input to consultation. No invented Registrar-specific branch, platform brand, causal trigger, imposed visit count, or generalized cohort percentage is shown.
- Primary-source correction retained: the Program Head CSV records face-to-face meetings and daily updates, not a separate verbatim “verbal updates/manual reconciliation” claim. Both Guidance respondents report 5+ scoring days; one reports 2–3 staff and the other all trained staff.
- Main Chapter 3 no longer contains the full use-case/DFD/ERD slots. They are Figures C.1, D.1, E.1, and F.1 in their appendices. No new technical or activity model was generated; use the researcher's provided versions after identification. The later NEXIAM-flow comparison should reuse an existing suitable macro/activity view and must not masquerade as a measured before/after result.
- Yukee's full SUS questionnaire is in Appendix H, while the method chapter explains it. NEXIAM follows that placement pattern: main Section 2.5 has concise paragraphs and Table 2.3 item-count summary; Appendix J Tables J.1–J.4 contain all 38 unchanged questions. Appendix N Tables N.1–N.2 contain the detailed output coverage and role/task/item map.
- Core requirements/features, findings, and results stay in the main chapters. Long supporting material moves to appendices; this is not a rule to hide essential methods or evidence.
- Source and rendering guide: `V2_RESOURCES/figures/SO1_FIGURE_SOURCES.md`. The publication allows only the two explicit local PNGs and four new appendix reading entries, not arbitrary or remote images.
- **Verified publication:** snapshot `2026-10-05T23:12:06.821Z` has 25 fixed documents and two PNG figures. Desktop/mobile and the full Chapter 3 copy display both figures; main instruments show only the summary, while Appendix J retains all 38 questions. Six safety tests and strict OpenSpec validation pass. No technical/activity model was regenerated and no actual research result was invented.

## 22. Population Basis and Proposed Evaluation Administration

- Researcher reaffirmed that first-year students are the evaluation population. Obtain the actual eligible count and authorized selection list for the relevant campus and academic year, apply the eligibility criteria, then recheck the sample-size calculation and selection procedure. The current 255-student target and corresponding 263 total remain provisional.
- Keep approximately 700–1,000 as **Guidance-reported examination volume**, not an exact population of 700 or the count of enrolled eligible first-year students. Its reporting period, campus scope and admission-batch coverage remain clarification points; this note does not manufacture those details or replace the original interview evidence.
- Recommended administration, not yet researcher-approved: face-to-face, role-specific simulated tasks, followed by the same post-task questionnaire collected digitally where practical, with an identical paper alternative. Digital questionnaire delivery is distinct from remote system-task evaluation.
- Student task A6 still requires receiving and reading a physical result sheet during a face-to-face consultation simulation. A few in-person participants do not establish this experience for students evaluated entirely remotely. A remote-only student session cannot silently substitute an on-screen sheet or online consultation for A6; changing that task or its two questionnaire items requires explicit protocol/instrument review.
- If some students perform portal tasks remotely, each still needs the approved physical component before the complete post-task questionnaire; this is a proposed two-stage alternative, not the current approved procedure. Record actual task conditions, questionnaire format, failures and assistance separately. Paper and digital response formats alone do not establish a mixed-methods research design.
- Student invitations must follow the chosen sampling procedure, not be replaced by an unrestricted public form link. Keep selection lists, consent records and individual responses in approved restricted storage, not the publicly shared appendix/reference folder. Identical wording, scale, task exposure and missing-item rules must be retained across collection formats.
- No change to manuscript methods, canonical tasks, instrument items, sample arithmetic or published forms is approved by this recommendation. Confirm the administration choice before revising Sections 2.4/2.5 and Appendix N.


## 23. Researcher-Selected Same-Day Administration Plan

- Researcher selected the discussed modalities for conduct today and tonight: staff/academic-leader tasks in person; student portal tasks either in person or through a live Google Meet hands-on session, with the required physical result-sheet component completed in person. Participants use their assigned fictional accounts; watching a facilitator demonstration is not task completion.
- A prepared video is optional and will not be treated as a prerequisite. Use the same live neutral briefing and task cards instead. Do not provide a click-by-click solution to the tasks being evaluated.
- This records the researcher's operational choice, not verified adviser/institutional approval, completed pilot work, prepared accounts/records or actual participant conduct. Confirm those prerequisites before main collection. A technical rehearsal checks readiness but is not a substitute for required instrument review or pilot approval.
- Prefer fully in-person student sessions for participants available on site today. Use the remote-plus-physical route only where the same selected student can complete both stages; collect the complete post-task questionnaire afterward. A student who cannot complete the physical component by the cutoff remains an incomplete session, not a completed full evaluation or an opportunity to substitute online result release.
- Use role-specific digital collection only through an approved private form; identical paper forms remain available. Do not upload individual responses or selection/consent records to the publicly shared appendix folder. Record participant code, eligibility/consent, actual task conditions, attempts/failures/assistance, physical component and response format separately.
- Prioritize prerequisite checks, researcher rehearsal, coordinated staff/academic-leader sessions, then manageable student batches based on actual facilitator/device capacity. The deadline does not certify readiness or establish that 255 students can be evaluated tonight. Planned sample remains provisional; actual attendance, completions, missing data and valid construct counts must be reported without relabelling volunteers as a random sample.
- No research results, successful deployment/task execution, final sample calculation or retrospective methods statement is created by this plan. Update manuscript methods only with the approved procedure and later actual conduct records.

