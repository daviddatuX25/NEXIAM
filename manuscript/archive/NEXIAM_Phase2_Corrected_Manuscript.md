# NEXIAM — Corrected Manuscript Sections (Phase 2)
## For ODT insertion | SO4 removed | TAM → PU+PEOU | 0 em dashes | No AI-isms

---

## Conceptual Framework of the Study

Structured as a descriptive-developmental study, NEXIAM's conceptual framework utilizes an Input-Process-Output model. The input captures two groups. The first is institutional data: applicant profiles, examination preferences, admission inquiries, advisory notes, scanned answer sheets, program quotas, and activity logs. The second is development foundations integrated during construction: a computer vision OMR engine for automated scoring, an AI orchestration framework for model routing, and admission support models for historical cohort profiling and preliminary program matching. These development inputs are not runtime data. They are the technical building blocks selected and integrated during Rapid Construction.

The process iterates through the AI-Augmented<!-- already reterm as we move towards a solid model proven in  reserach (howeever discus the ai augmentation without mentioning the whole rad as "AI-augmenteeed RAD model as theres none who does) --> RAD model. Requirements Planning captures the operational baseline through descriptive inquiry. User Design produces the RBAC schema and authorization matrix. Rapid Construction integrates the AI  <!-- only AI components?! dont overmention ai as the promary sopurce eagain as our development might be assisted by AI but this is hyuman gated *btw this things in this comment im sayingh,  dont mention on conceptual frameework as they are for rad discussion * -->components under human code review. Cutover deploys the system for evaluation. The Technology Acceptance Model is embedded within this flow. Evaluators complete a single post-test TAM questionnaire after executing role-specific simulatio scenarios. The instrument captures absolute acceptance levels across two constructs: Perceived Usefulness and Perceived Ease of Use.

The output is organized into five functional groups. Communication covers chatbot responses and email updates. Platform Access covers the applicant portal accessed via identifier key, the staff interface for Guidance, Registrar, and Program Head personnel, and HMAC-SHA256 audit trails. Scheduling covers human-gated, AI-optimized exam schedules. Reports and Results covers exam scores and prints, results and release sheets, and pre-enrolment lists. Analytics covers admission support dashboards surfacing aptitude patterns for counselor review. Alongside these platform outputs, the research produces descriptive TAM acceptance measures reported through pooled and per-role means. These outputs address Objective 3. The Input and Process framing serves Objectives 1 and 2.

### Figure 1. Input-Process-Output Diagram

**INPUT**

*Institutional Data*
- Applicant profiles *(identifier key embedded)*
- Exam and schedule preferences
- Admission inquiries
- Advisory notes
- Filled answer sheets
- Program quotas

*Development Foundation*
- OMR Libraries
- AI orchestration framework (RAG)
- Admission support models

**PROCESS**
Development and Evaluation of NEXIAM: Next Generation Examination and Cross-role Intelligent Admission Management

**OUTPUT**

*Communication*
- Chatbot responses
- In-app messaging 
- Email Updates

*Platform Access*
- Applicant portal
- Staff interface
- Optimized exam scheduling 
- Audit trails 

*Scheduling*
- Optimized exam schedules

*Reports and Results*
- Exam scores
- Printed results
- Pre-enrolment lists

*Analytics*
- Admission support dashboards
  
<!--
### Refined IPO Conceptual Framework for NEXIAM

#### **INPUT**

_Data entities and constraints required to drive the system logic._

**1. Stakeholder Data & Preferences**

- Applicant Profiles (with embedded identifier keys)
    
- **Schedule Preferences** (Availability constraints for applicants and proctors)
    
- **Admission Inquiries** (Requests originating from applicants)
    
- **Advisory Notes** (Internal assessments from Program Heads/Guidance Counsellors)
    

**2. Institutional Parameters**

- **Venue Catalog** (Defined inventory of rooms, capacities, and equipment)
    
- Program Quotas & Course Definitions
    
- Role Definitions (Access levels for Admin, Proctor, Applicant)
    

**3. Assessment & Technical Assets**

- Filled Answer Sheets (OMR Data)
    
- **OMR Libraries** (Scanning and recognition modules)
    
- **AI Orchestration Framework (RAG)** (Admission support models and logic)
    

---

#### **PROCESS**

_The core development and evaluation flow._

**The Development and Evaluation of NEXIAM** _(Next Generation Examination and Cross-role Intelligent Admission Management)_

1. **Process Analysis (SO1):** Mapping existing admission workflows to digital requirements.
    
2. **System Feature Identification (SO2):** Defining logic for AI scheduling, OMR grading, and role-based access.
    
3. **Role-Based Evaluation (SO3):** Assessing system acceptance via **Technology Acceptance Model (TAM)** across different user roles.
    

---

#### **OUTPUT**

_The tangible deliverables, interfaces, and analytical tools._

**1. Communication & Engagement**

- Chatbot Responses (AI-driven)
    
- In-app Messaging & Email Updates
    

**2. Role-Based Interfaces**

- **Applicant View:** Application tracking, Schedule viewing, Result access.
    
- **Administrative View:** Resource management, Schedule generation, Audit trail access.
    

**3. Operational Artifacts and Reports** _(Renamed & Updated)_

- **Optimized Schedules** (Conflict-free exam timetables)
    
- Exam Scores & Printed Results
    
- Pre-enrolment Lists
    
- **Audit Trails** (System logs of user actions, schedule changes, and data modifications)
    

**4. Counselling and Analytics**

- Admission Support Dashboards
    
- **Course Slots Monitoring** (Real-time tracking of quota utilization and slot availability)
-->

---

## Objectives of the Study

This study aims to develop NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus. The web-based platform centralizes the admission testing pipeline covering applicant registration, testing, scoring, admission support, and result delivery.

More specifically, this study seeks to accomplish the following:

1. To analyze the existing college admission testing processes, operational workflows, and process gaps at ISPSC Tagudin Campus through descriptive inquiry.

2. To determine the system requirements of NEXIAM in terms of functional requirements and system features needed to address the identified process gaps.

3. To evaluate the acceptance of NEXIAM using the Technology Acceptance Model in terms of perceived usefulness and perceived ease of use across four respondent groups: Registrar Personnel, Guidance Personnel, First-Year Students, and Program Heads.

---

## Scope and Limitations of the Study

### Scope Aligned to Objective 1 (Process Analysis)

This research covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, for the admission cycle running June through September 2026. The study covers the full admission testing pipeline from applicant registration through result release and post-examination counseling support. The descriptive inquiry captures operational workflows, process gaps, and inter-office coordination failures through semi-structured questionnaires and field observation at the Guidance and Registrar Offices. Flow charts and process diagrams visualize the current admission workflows, establishing the operational baseline that NEXIAM replaces.

### Scope Aligned to Objective 2 (System Requirements)

The system requirements cover seven RBAC-governed user roles: Applicant, Proctor (duty overlay), Guidance Counselor, Guidance Administrator, Registrar Staff, Registrar Administrator, and System IT Administrator. A Super Admin role exists for explicit oversight access and future IT personnel assignment but is not evaluated in this study. The functional scope spans the following components.

**Normal Admission Functions.** Applicant registration, examination scheduling, result processing, and inter-office coordination operate through RBAC-governed dashboards. Staff access is provided through a dedicated staff interface serving the Guidance Office, Registrar Office, and Program Heads, with role-isolated views scoped to each office's operational functions. Applicants access the system through a web-based portal authenticated via a unique identifier key embedded in their applicant profile, replacing traditional account creation with a simpler, single-key access mechanism. Applicants receive email notifications for schedule assignments and result releases rather than checking an in-app status feed. The portal provides 24/7 access to result retrieval and chatbot interaction without requiring physical campus visits.

**Optical Mark Recognition (OMR).** A computer vision OMR engine automates bubble-sheet scoring from scanned answer sheets. The module uses gated decisioning. Answer sheets are processed in batches, and any marked bubble falling below a configurable confidence threshold is flagged for human verification rather than auto-scored.

**RAG Chatbot and AI Scheduling.** The Retrieval-Augmented Generation chatbot is a structural service layer architecturally coupled to the AI scheduling engine. The chatbot handles applicant-specific inquiries: retrieving the applicant's own examination results, checking their own assigned schedule, and surfacing AI-optimized schedule assignments drawn from the scheduling database in natural language. The chatbot draws exclusively from ISPSC institutional documents. Below the retrieval confidence threshold, it declines to answer rather than fabricating a response.

**Admission Support Models (Machine Learning Engine).** The machine learning component of this study is bounded strictly to the architectural design and functional integration of an extensible interface wrapper within the admission system. While the module is engineered as a pluggable framework capable of loading external data assets — specifically an unsupervised model for Historical Cohort Profiling and a simple baseline supervised model for Preliminary Program Matching — the intrinsic mathematical accuracy, parameter optimization, algorithmic tuning, and predictive validity of these underlying models remain completely unmeasured and outside the scope of this research. The evaluation of this module is restricted purely to its system utility, multi-role data serving capabilities, and user acceptance testing. The engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard. The algorithm is strictly descriptive and does not make admission decisions. Every placement requires manual counselor confirmation. Program capacity is managed through a separate quota interface and excluded from the clustering algorithm itself.

**Audit Trail.** All system events — data access, role actions, placement decisions, and schedule modifications — are logged with HMAC-SHA256 cryptographic chaining. This ensures any unauthorized modification of log entries is structurally detectable.

**Proctoring Interface.** The proctoring interface is designed with offline resilience for examination venues where network connectivity may be unavailable or intermittent, using service-worker caching to maintain examination session state during connectivity loss, with synchronization to the cloud database upon connection restoration. All other system functions outside the proctoring interface require cloud connectivity. Proctor is a duty overlay assignable to any existing staff role at administrative discretion rather than a distinct RBAC role.

### Limitations Aligned to Objective 2 (System Boundaries)

The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment, a decision driven by local campus hardware constraints.

**OMR.** The study measures OMR accuracy as part of the system validation discussed in the Results and Discussion chapter. However, accuracy is validated through the research instrument — TAM acceptance measures — rather than benchmarked against industry-standard OMR accuracy metrics. OMR is an established technology with proven implementations in Philippine educational contexts; this study's contribution is its integration into a governed admission pipeline, not re-proving the underlying computer vision technique. Accuracy findings are reported descriptively alongside TAM results.

**RAG and AI Scheduling.** AI inference for the chatbot and scheduling assistant relies on external model access through OpenRouter, bounded by the operational funding allocated by the office for conducting the admission cycle. Paid inference capacity beyond this allocation is outside scope. The study does not benchmark the LLM's response accuracy against industry-standard NLP metrics; the chatbot is validated through TAM acceptance measures evaluating whether applicants find its responses useful and easy to interact with.

**Admission Support Models.** As stated in the scope above, model accuracy, tuning, and predictive validity are unmeasured. Evaluation is restricted to system utility and user acceptance through the TAM instrument.

### Limitations Aligned to Objective 3 (Evaluation Boundaries)

The evaluation instrument is the Technology Acceptance Model, administered as a single post-test measuring two constructs: Perceived Usefulness and Perceived Ease of Use. Acceptance is reported through descriptive means per construct and per respondent role without inferential comparison to a manual baseline.

Because the September 2026 evaluation window occurs outside the active admission cycle, the study utilizes simulated evaluators executing role-based scenarios to simulate live operational conditions. The study's evaluation window therefore cannot produce findings based on live applicant behavior, and results are accordingly bounded by simulated operational conditions.

The total sample consists of 271 evaluators divided into two categories. Administrative staff are purposively selected because they represent the complete available staff complement at the Tagudin Campus: 11 Program Heads, 3 Registrar Office personnel, and 2 Guidance Office personnel (n=16). No larger pool of eligible personnel exists at this campus. First-year student evaluators are drawn using Slovin's formula at a 0.05 margin of error from an estimated population of 700 applicants per admission cycle, reduced from the typical 1,000 as a baseline for budgetary and operational constraints. This yields n = 255 first-year students, stratified across 11 courses (approximately 23 per course). Total evaluators: 271.

No IT Expert evaluation is conducted. The system's architecture, RBAC implementation, and data privacy compliance are validated through the iterative design review in Objective 2 and the development team's internal peer review process during Rapid Construction.

While NEXIAM is designed to support compliance with Republic Act No. 10173 through RBAC and application-layer data governance, formal National Privacy Commission certification and the Privacy Impact Assessment fall outside this study's scope. Applicant consent for digital processing is assumed to be covered under the existing ICAT application form's data collection procedures. Formal verification of this consent basis is identified as future work.

<!-- Although we have likee per objective devision, we should dissolve it natrually so that we only have to form concise but precise and detailed paragrpoh to detail this be an scope AND limitations. so this means dont gropu based on scope and limitations but still note connections when it comes to objectives still.-->

---

## [Null Hypotheses — SECTION REMOVED]

The null hypotheses (H₀₁ and H₀₂) and the paired samples t-test specification are removed. The study no longer performs inferential comparison between manual and system processes. Evaluation is purely descriptive through TAM post-test means.

---

## Research Design *(partial — only changed paragraphs)*

This study employs a descriptive-developmental research design (Frey, 2022) to sequentially diagnose existing admission workflows at ISPSC Tagudin Campus and construct an evidence-based platform that resolves them. In the descriptive phase, data gathered through semi-structured administrator questionnaires and formally documented field observations undergo thematic analysis <!-- were picky about this, AMD GET THIS: WE WILL NOT USE THE WORD THEMATIC ANALYSIS as its implicating a wider application of the method itsellf that is standard ... for this we eare actualy advised to present through flow charts and discussion... so formally like this "**process mapping**, **workflow analysis**, or **descriptive analysis**", and this may follow to refactor various parts of the document by then or now then...  -->, structuring preliminary operational pain points into validated emergent themes. The developmental phase subsequently converts these finalized themes into the functional scope of NEXIAM, ensuring every integrated system requirement traces directly to a confirmed administrative failure.

The developmental component encompasses the iterative design, construction, and validation of NEXIAM, addressing Specific Objectives 2 and 3. System requirements extracted from the descriptive findings are organized into a functional requirements table and a system features table constituting the output of Specific Objective 2. The system is then constructed through the RAD workflow and evaluated through simulated user acceptance testing with evaluators selected through purposive sampling for administrative staff and Slovin's formula for first-year students, using the TAM questionnaire as the primary evaluation instrument.

---

## Population and Locale of the Study

The study is conducted at ISPSC Tagudin Campus, with the evaluation administered during the September 2026 Cutover phase. Because the September 2026 evaluation occurs outside the active admission period, actual applicants are unavailable, necessitating simulated evaluators.

The sample consists of 271 evaluators divided into two categories. Administrative staff are purposively selected because they represent the complete available staff complement at the Tagudin Campus: 11 Program Heads, 3 Registrar Office personnel, and 2 Guidance Office personnel (n=16). No larger pool of eligible personnel exists at this campus, so no further sampling was possible within these units.

First-year student evaluators are drawn using Slovin's formula at a 0.05 margin of error from an estimated population of 700 applicants per admission cycle (reduced from the typical 1,000 for budgetary and operational constraints). This yields n = 255 first-year students, stratified across 11 courses (approximately 23 per course).

During simulated testing, researchers direct all evaluators through role-specific use cases, after which each participant independently completes the Technology Acceptance Model questionnaire to ensure unbiased data collection. This structured approach is designed to ensure that the simulated scenarios closely mirror real-world administrative workflows. Furthermore, it is intended to measure the platform's readiness for actual institutional deployment once Rapid Construction is complete.

### Table 2. Distribution of Respondents

| Respondents | n |
|---|---|
| First-Year Students | 255 |
| Registrar Office Personnel | 3 |
| Guidance Office Personnel | 2 |
| Program Heads | 11 |
| **Total** | **271** |

---

## Research Instruments

This study utilizes the Technology Acceptance Model questionnaire as its primary evaluation instrument. Grounded in Davis (1989), the tool measures two constructs: Perceived Usefulness (PU) and Perceived Ease of Use (PEOU). This follows the original Davis formulation where PU and PEOU are the primary determinants of user acceptance. Attitude Toward Use and Behavioral Intention to Use, which function as mediating constructs in TAM2 and TAM3 extensions (Venkatesh & Davis, 2000; Venkatesh & Bala, 2008) <!-- for therefore exclusions no need to mention, and hence well remove their equivalnece on the reference section as well-->, are excluded because those extensions are designed for longitudinal adoption studies. For a single-shot post-implementation evaluation in a descriptive-developmental study, the two-construct model provides sufficient measurement while reducing respondent fatigue across four role-specific variants.

The instrument uses role-adapted phrasing because each respondent group interacts with different NEXIAM features, as defined in the use case diagram (Appendix B). Items preserve the underlying PU and PEOU constructs while tailoring referents to each role's operational reality. This approach is supported by role-based TAM adaptations in multi-stakeholder information systems (Lippert & Forman, 2005; Holden & Karsh, 2010).

The instrument is administered as a single post-test. Evaluators complete the questionnaire once after executing their role-specific simulation scenarios. No pre-test is administered because the study does not compare manual versus system performance. The instrument captures absolute acceptance levels.

Items are measured on a five-point Likert scale from 1 (Strongly Disagree) to 5 (Strongly Agree). Items per role vary based on the features each role accesses. Registrar Personnel have 8 items reflecting their limited scope of applicant intake and record management. Guidance Personnel have 13 items reflecting their broad feature surface spanning OMR scoring, scheduling, admission support dashboards, result processing, and proctoring. First-Year Students have 9 items covering the applicant portal, chatbot, email notifications, and result retrieval. Program Heads have 9 items covering pre-enrolment lists, quota management, and admission support dashboards.

### Table 3. TAM Indicators for Registrar Personnel (PU and PEOU)

| # | Construct | Post-Test Indicator |
|---|---|---|
| 1 | PU | NEXIAM allows me to process applicant intake and registration efficiently. |
| 2 | PU | NEXIAM helps me manage admission-related records faster. |
| 3 | PU | NEXIAM gives me sufficient visibility into an applicant's status. |
| 4 | PU | NEXIAM reduces my workload when coordinating with Guidance personnel. |
| 5 | PEOU | NEXIAM's digital applicant intake and records are easy to navigate. |
| 6 | PEOU | Learning how to use NEXIAM took little effort. |
| 7 | PEOU | It is easy to correct mistakes in applicant records using NEXIAM. |
| 8 | PEOU | Interacting with NEXIAM is generally clear and understandable. |

### Table 4. TAM Indicators for Guidance Personnel (PU and PEOU)

| # | Construct | Post-Test Indicator |
|---|---|---|
| 1 | PU | NEXIAM's admission support dashboard helps me evaluate applicant readiness for placement. |
| 2 | PU | NEXIAM's admission support output provides useful profiles for counseling sessions. |
| 3 | PU | NEXIAM gives me sufficient visibility into an applicant's status. |
| 4 | PU | NEXIAM reduces my workload when coordinating with Registrar and Program Head personnel. |
| 5 | PU | Automated OMR scoring saves time compared to manual stencil checking. |
| 6 | PU | AI-assisted scheduling improves exam session planning. |
| 7 | PU | The proctoring interface supports effective exam monitoring and attendance. |
| 8 | PEOU | It is easy to navigate AI-assisted scheduling, OMR scoring, and admission support in NEXIAM. |
| 9 | PEOU | Learning how to use NEXIAM took little effort. |
| 10 | PEOU | It is easy to read and interpret the admission support profiles in NEXIAM. |
| 11 | PEOU | Interacting with NEXIAM is generally clear and understandable. |
| 12 | PEOU | The proctoring interface is easy to use during exam sessions. |
| 13 | PEOU | Result sheet printing and schedule release are straightforward. |

### Table 5. TAM Indicators for First-Year Students (PU and PEOU)

| # | Construct | Post-Test Indicator |
|---|---|---|
| 1 | PU | The applicant portal provides useful access to my application status. |
| 2 | PU | The chatbot gives helpful answers to my admission questions. |
| 3 | PU | Email updates keep me adequately informed about my application. |
| 4 | PU | The results and release sheet is clear and useful. |
| 5 | PU | Identifier key access is convenient. |
| 6 | PEOU | The applicant portal is easy to use. |
| 7 | PEOU | The chatbot is easy to interact with. |
| 8 | PEOU | Finding my exam results is straightforward. |
| 9 | PEOU | The identifier key login process is simple. |

### Table 6. TAM Indicators for Program Heads (PU and PEOU)

| # | Construct | Post-Test Indicator |
|---|---|---|
| 1 | PU | Pre-enrolment lists support timely program decisions. |
| 2 | PU | Quota visibility provides an accurate enrollment capacity picture. |
| 3 | PU | Admission support dashboards surface useful aptitude patterns. |
| 4 | PU | The staff interface improves coordination with Guidance. |
| 5 | PU | Optimized exam schedules aid department planning. |
| 6 | PEOU | The pre-enrolment list interface is easy to navigate. |
| 7 | PEOU | Quota management controls are straightforward. |
| 8 | PEOU | Admission support dashboards are clear and interpretable. |
| 9 | PEOU | The staff interface is simple to operate. |

---

## Data Analysis

**Specific Objective 1 — Thematic Analysis.** Qualitative thematic analysis is applied to semi-structured questionnaire responses and field observation notes from Guidance and Registrar staff. Responses are transcribed, coded, and organized into operational themes: scoring bottlenecks, inter-office coordination failures, data integrity vulnerabilities, and notification deficiencies. The resulting thematic structure constitutes the empirical foundation from which every NEXIAM feature is derived. No requirement enters development without a documented operational failure.

**Specific Objective 2 — Iterative Design Validation.** Completed modules are demonstrated to Guidance and Registrar staff during the construction cycle. Misalignments between the system's design and field-confirmed needs are logged as revision items, connecting every design change to a specific field finding. The functional requirements and system features tables simultaneously constitute the Chapter 3 system design section and the reference structure against which Cutover testing scenarios are built. User Design feedback, including mockup preferences and feature acceptance, is captured iteratively and documented within the Objective 2 validation process. 

**Specific Objective 3 — Descriptive TAM Means.** Each respondent's items per construct are averaged into a single per-person construct score. These per-person scores are then averaged across all evaluator post-test responses to produce the pooled mean for Perceived Usefulness and Perceived Ease of Use. Means above the midpoint of the five-point scale (greater than 3.0) indicate positive acceptance. Standard deviations per construct are reported alongside the means to describe response consistency. Pooled means are reported with a breakdown of per-role descriptive means for each construct: Registrar Personnel, Guidance Personnel, First-Year Students, and Program Heads. Results are interpreted against established TAM benchmarks from the original Davis (1989) instrument and subsequent validation literature.

---

## Appendix B — Use Case Diagram Description

The use case diagram maps NEXIAM's actors against eighteen use cases covering intake, testing, admission support, and audits. Access is strictly role-isolated where actors only access functions their offices perform. A Super Admin role exists for explicit oversight access and future IT personnel assignment but is not evaluated in this study. Account creation is distributed per office: Registrar Admin creates Registrar Staff accounts; Guidance Admin creates Guidance Counselor accounts.

**Applicant:** Authenticate, Submit Application Form, Query Chatbot, Track Application Status

**Proctor (duty overlay):** Authenticate, Scan QR for Attendance, Monitor Exam Sessions

**Guidance Counselor:** Authenticate, Schedule Exam Sessions, Input OMR/CSV/Raw Scores, Print Result Sheets, Schedules Release and Consultation, Configure Admission Support, Analyze Admission Support Indicators, Generate and View Reports

**Guidance Admin:** Configure Settings, Authenticate, Schedule Exam Sessions, Input OMR/CSV/Raw Scores, Print Result Sheets, Schedules Release and Consultation, Analyze Admission Support Indicators, Generate and View Reports, Review Audit Logs, Create Guidance Counselor Accounts

**Program Head:** Authenticate, Analyze Admission Support Indicators, Manage Program Quotas, Generate and View Reports, Review Audit Logs

**Registrar Staff:** Authenticate, Process Admission Entries, Track Application Status, Generate and View Reports

**Registrar Admin:** Configure Settings, Authenticate, Process Admission Entries, Track Application Status, Generate and View Reports, Review Audit Logs, Create Registrar Staff Accounts

---

## Cross-Document Consistency Checklist

The following must be updated across all document sections to prevent inconsistency:

| Section               | Action                                                                                                                                                                                                                                                                                                       |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Objectives            | Remove SO4. TAM constructs: PU + PEOU only. Four respondent groups.                                                                                                                                                                                                                                          |
| Null Hypotheses       | Delete H₀₁ and H₀₂. Remove test specification paragraph.                                                                                                                                                                                                                                                     |
| Conceptual Framework  | Replace old IPO with grouped Phase 2 diagram. Remove paired TAM language.                                                                                                                                                                                                                                    |
| Scope and Limitations | Replace entire section with Phase 2 per-objective version.                                                                                                                                                                                                                                                   |
| Research Design       | Change "Objectives 2, 3, and 4" to "Objectives 2 and 3." Replace "proxy" with "simulated evaluators."                                                                                                                                                                                                        |
| Population            | Update to 271 total. Remove IT Expert entirely. Registrar and Guidance as separate rows.                                                                                                                                                                                                                     |
| Research Instruments  | Replace 16 old TAM tables with 4 new tables. Remove all pre-test columns. 5-point Likert.                                                                                                                                                                                                                    |
| Data Analysis         | Remove SO4 paragraph. Update SO3 midpoint to 3.0. Four per-role groups.                                                                                                                                                                                                                                      |
| Glossary              | Remove: Paired T-Test, Shapiro-Wilk, Wilcoxon, Pre-Test, IT Expert. Update: TAM (2 constructs, 5-point, post-test), Purposive Sampling (Slovin's for applicants), Applicant Portal (identifier key), Aptitude Pattern → Admission Support Dashboard. Add: Identifier Key, Staff Interface, Slovin's Formula. |
| References            | Verify all citations (Lippert & Forman 2005, Holden & Karsh 2010) are in the reference list.                                                                                                                                                                                                                 |
| Table of Contents     | Update page numbers and remove Null Hypotheses entry.                                                                                                                                                                                                                                                        |
| List of Tables        | Remove old Tables 3–18. Add new Tables 3–6 (four TAM tables). Update Table 2 (Distribution of Respondents).                                                                                                                                                                                                  |
<!-- for followup and maybe lets create a new version of our manu .. whereien we hveee like a better version for the purpose of making this a better study ... such that we ehave the appropriate objectives (the inclusion of developing (given we can freely discus on analysis how we used ai in development and the rapid development phase of rad), and other objecives more specirffic given the masssive scope of our feature set (which for whatever even as it may that we are focusing on integration, we still have that thought ( so lits like an objective to target those but not those on themselves only but in releeation to the ewhole ... -->