# NEXIAM — Revised Manuscript Sections (Phase 3, Version A)
## 3 objectives | IPO from refined comment | Scope direct & concise | RAD not AI-Augmented RAD | Process mapping not thematic analysis | No ATU/BIU discussion

---

## Objectives of the Study

This study aims to develop NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus. The web-based platform centralizes the admission testing pipeline covering applicant registration, testing, scoring, admission support, and result delivery.

More specifically, this study seeks to accomplish the following:

1. To analyze the existing college admission testing processes, operational workflows, and process gaps at ISPSC Tagudin Campus through descriptive inquiry.

2. To determine the system requirements of NEXIAM in terms of functional requirements and system features needed to address the identified process gaps. (The RAD development methodology — including component construction and AI-assisted implementation — is discussed within this objective as the means by which the requirements are realized. The study's contribution is the integrated admission system that results, not the AI tools used during construction.)

3. To evaluate the acceptance of NEXIAM using the Technology Acceptance Model in terms of perceived usefulness and perceived ease of use across four respondent groups: Registrar Personnel, Guidance Personnel, First-Year Students, and Program Heads.

---

## Conceptual Framework of the Study

Structured as a descriptive-developmental study, NEXIAM's conceptual framework utilizes an Input-Process-Output model. The input captures three groups. Stakeholder data and preferences cover applicant profiles with embedded identifier keys, examination and schedule preferences, admission inquiries, and advisory notes. Institutional parameters cover program quotas, the venue catalog, and role definitions. Development foundations cover the computer-vision OMR engine, the AI orchestration framework, and the admission support models integrated during construction. These are the technical building blocks selected and integrated during Rapid Construction — not runtime data.

The process follows the Rapid Application Development model. Requirements Planning captures the operational baseline through descriptive inquiry. User Design produces the RBAC schema and authorization matrix. Rapid Construction integrates the system components under human code review. Cutover deploys the platform for evaluation. The Technology Acceptance Model is embedded within this flow. Evaluators complete a single post-test TAM questionnaire after executing role-specific simulation scenarios. The instrument captures absolute acceptance levels across two constructs: Perceived Usefulness and Perceived Ease of Use.

The output is organized into four functional groups. Communication covers chatbot responses and email updates. Role-Based Interfaces cover the applicant portal accessed via identifier key and the RBAC-governed staff interface serving Guidance, Registrar, and Program Head personnel. Operational Artifacts and Reports cover optimized exam schedules, exam scores and printed results, pre-enrolment lists, and HMAC-SHA256 audit trails. Analytics covers admission support dashboards and course slots monitoring. Alongside these platform outputs, the research produces descriptive TAM acceptance measures reported through pooled and per-role means. The platform outputs are produced through the development methodology discussed under SO2. The acceptance measures address Objective 3. The input framing serves Objectives 1 and 2.

### Figure 1. Input-Process-Output Diagram

**INPUT**

*Stakeholder Data & Preferences*
- Applicant profiles (identifier keys embedded)
- Exam and schedule preferences
- Admission inquiries
- Advisory notes

*Institutional Parameters*
- Program quotas
- Venue catalog
- Role definitions

*Development Foundations*
- Computer vision OMR engine
- AI orchestration framework
- Admission support models

**PROCESS**

Development and Evaluation of NEXIAM through Rapid Application Development

1. Process Analysis (SO1)
2. System Requirements (SO2)
3. Role-Based Evaluation (SO3)

**OUTPUT**

*Communication*
- Chatbot responses
- In-app messaging
- Email updates

*Role-Based Interfaces*
- Applicant portal (identifier key access)
- Staff interface (RBAC-governed dashboards)

*Operational Artifacts and Reports*
- Optimized exam schedules
- Exam scores and printed results
- Pre-enrolment lists
- HMAC-SHA256 audit trails

*Analytics*
- Admission support dashboards
- Course slots monitoring

---

## Scope and Limitations of the Study

This study covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, during the June through September 2026 admission cycle. The pipeline runs from applicant registration through result release and post-examination counseling support. Descriptive inquiry captures operational workflows, process gaps, and inter-office coordination failures through semi-structured questionnaires and field observation. Process flow charts visualize current admission workflows, establishing the operational baseline that NEXIAM replaces.

**System Coverage.** Seven RBAC-governed user roles: Applicant, Proctor (duty overlay), Guidance Counselor, Guidance Administrator, Registrar Staff, Registrar Administrator, and System IT Administrator. A Super Admin role exists for oversight and future IT assignment but is not evaluated. Normal admission functions — registration, scheduling, result processing, and inter-office coordination — operate through RBAC-governed dashboards. Staff interfaces serve Guidance, Registrar, and Program Heads with role-isolated views. Applicants authenticate via a unique identifier key given upon registration. Email delivers schedule and result notifications. The portal provides 24/7 information retrieval and chatbot access. In-app messaging covers fallback inquiries from applicants to staff and a consultation dashboard for cross-staff communication. 

**OMR.** A computer-vision engine scores scanned answer sheets in batches. Bubbles below a configurable confidence threshold are flagged for human verification. Accuracy is discussed within the development phase but is validated through TAM acceptance measures, not benchmarked against exhaustive methods.

**AI Orchestration Frameworks.** This constitutes the use of existing AI software development kits to create AI-powered features including a RAG chatbot and AI-assisted scheduling. It retrieves applicant-specific information — own results, own schedule, AI-optimized assignments — from the scheduling database and ISPSC institutional documents. Below retrieval confidence, it declines to answer. The scheduler proposes; authorized officers confirm. AI inference uses OpenRouter free-tier access; however, paid capacity is an option for future use. These features are validated through TAM, not NLP benchmarks.

**Admission Support Models.** An extensible interface wrapper loads an unsupervised model for Historical Cohort Profiling and a baseline supervised model for Preliminary Program Matching. The engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard. Model accuracy, tuning, and predictive validity are unmeasured. Evaluation is restricted to system utility and user acceptance through TAM. The algorithm is descriptive — every placement requires manual counselor confirmation.

**Audit Trail.** All system events — data access, role actions, placement decisions, schedule modifications — are logged with HMAC-SHA256 cryptographic chaining. Unauthorized modification is structurally detectable.

**Proctoring Interface.** Service-worker caching provides offline resilience during examination sessions with intermittent connectivity, synchronizing to the cloud on reconnection. All other functions require cloud connectivity. Proctor is a duty overlay, not a distinct RBAC role.

**System Boundaries.** Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. Deployment is restricted to a cloud-hosted environment driven by local hardware constraints. Formal NPC certification and a Privacy Impact Assessment are excluded. Applicant consent is assumed under the existing ICAT application form; formal verification of this consent basis is future work.

**Development.** NEXIAM is constructed through the RAD model. AI-assisted implementation accelerates coding under human review, but the study's contribution is the integrated admission system that results, not the AI tools used during construction. The development process and component integration decisions are documented in Chapter 3.

**Evaluation.** TAM is administered as a single post-test measuring Perceived Usefulness and Perceived Ease of Use on a five-point Likert scale. Acceptance is reported through descriptive means per construct and per respondent role. No inferential comparison to a manual baseline is performed. The September 2026 evaluation window falls outside the active admission cycle, necessitating simulated evaluators executing role-based scenarios. Results are bounded by simulated operational conditions. No IT Expert evaluation is conducted. Architecture, RBAC implementation, and data privacy compliance are validated through iterative design review and the development team's internal peer review during Rapid Construction.

---

## [Null Hypotheses — SECTION REMOVED]

The null hypotheses (H₀₁ and H₀₂) and the paired samples t-test specification are removed. The study no longer performs inferential comparison between manual and system processes. Evaluation is purely descriptive through TAM post-test means.

---

## Software Model

In developing institutional web systems, the Rapid Application Development (RAD) model (Martin, 1991; Pressman & Maxim, 2020) has proven effective for ensuring functional suitability and security, particularly in recent Philippine state university deployments (Olipas, 2020; Melendres & Aranda, 2024). RAD organizes development into four phases: Requirements Planning, User Design, Rapid Construction, and Cutover. Within Rapid Construction, the team uses AI-assisted development to accelerate routine implementation work while maintaining human code review on every generated module. This approach aligns with productivity gains documented in recent software engineering studies (Peng et al., 2023) and reflects the transition from ad-hoc prompting to agentic engineering described in contemporary industry practice (Osmani et al., 2026). The study's contribution is the integrated admission system that results, not the AI tools used during construction.

**Requirements Planning.** This phase establishes an empirical baseline of institutional workflow gaps drawn from the descriptive inquiry in Specific Objective 1. It systematically gathers verified operational evidence through questionnaires and field observation to produce the formal technical specifications and the initial database schema. These validated operational failures become the specific local context that grounds all subsequent development, preventing the generation of generic software solutions.

**User Design.** This phase translates the field-verified requirements into deterministic architectural blueprints. It formulates the relational RBAC database schema, establishes the authorization matrix, produces architectural diagrams including data flow, entity-relationship, and business requirements diagrams, and generates interface mockups for all seven user roles. By finalizing these blueprints before coding begins, the team ensures the system architecture inherently complies with the Data Privacy Act (Republic Act No. 10173).

**Rapid Construction.** This phase forms the technical development core. The team systematically researches and evaluates application frameworks to accommodate complex system requirements. AI-assisted development accelerates the construction of processing layers such as the computer-vision OMR scoring engine and the RAG chatbot orchestration. Every AI-generated module undergoes rigorous peer code review. Construction proceeds iteratively between design refinement and incremental build, with completed modules demonstrated to Guidance and Registrar staff for validation against the functional requirements specified in Specific Objective 2.

**Cutover.** This phase transitions the development cycle into empirical evaluation. It deploys the completed NEXIAM platform into a cloud-hosted environment to demonstrate operational capacity. Following deployment, simulated evaluators execute role-specific scenarios corresponding to the use cases defined in Appendix B. Each participant independently completes the Technology Acceptance Model questionnaire as a single post-test. This protocol measures absolute user acceptance levels across two constructs: Perceived Usefulness and Perceived Ease of Use, addressing Specific Objective 3.

---

### Figure 2. Rapid Application Development (RAD) Model

*[Diagram showing the four RAD phases: Requirements Planning, User Design, Rapid Construction, Cutover — with feedback loops between Rapid Construction and User Design, and between Cutover and Requirements Planning.]*

---

## Research Design *(changed paragraphs only)*

This study employs a descriptive-developmental research design (Frey, 2022) to sequentially diagnose existing admission workflows at ISPSC Tagudin Campus and construct an evidence-based platform that resolves them. In the descriptive phase, data gathered through semi-structured administrator questionnaires and formally documented field observations undergo process mapping and workflow analysis, structuring operational pain points into validated process diagrams. Flow charts visualize current admission workflows from intake through result delivery, establishing the operational baseline that NEXIAM replaces. The developmental phase converts these validated findings into the functional scope of NEXIAM, ensuring every integrated system requirement traces directly to a confirmed administrative failure.

The developmental component encompasses the iterative design, construction, and validation of NEXIAM, addressing Specific Objectives 2 and 3. System requirements extracted from the descriptive findings constitute the output of Specific Objective 2, with the RAD development methodology discussed within this objective. The system is constructed through the RAD workflow and evaluated through simulated user acceptance testing with evaluators selected through purposive sampling for administrative staff and Slovin's formula for first-year students, using the TAM questionnaire as the primary evaluation instrument for Objective 3.

---

## Population and Locale of the Study

The study is conducted at ISPSC Tagudin Campus. The evaluation is administered during the September 2026 Cutover phase, which falls outside the active admission period. Actual applicants are unavailable, necessitating simulated evaluators.

The sample consists of 271 evaluators divided into two categories. Administrative staff are purposively selected — they represent the complete available staff complement at the Tagudin Campus: 11 Program Heads, 3 Registrar Office personnel, and 2 Guidance Office personnel (n=16). No larger pool of eligible personnel exists at this campus.

First-year student evaluators are drawn using Slovin's formula at a 0.05 margin of error from an estimated population of 700 applicants per admission cycle (reduced from the typical 1,000 for budgetary and operational constraints). This yields n = 255 first-year students, selected through stratified random sampling across 11 courses (approximately 23 per course).

Researchers direct all evaluators through role-specific use cases. Each participant independently completes the TAM questionnaire afterward to ensure unbiased data collection. This structured approach is designed to mirror real-world administrative workflows and measure the platform's readiness for actual institutional deployment once Rapid Construction completes.

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

This study utilizes the Technology Acceptance Model questionnaire as its primary evaluation instrument. Grounded in Davis (1989), the tool measures two constructs: Perceived Usefulness (PU) and Perceived Ease of Use (PEOU). The instrument uses role-adapted phrasing because each respondent group interacts with different NEXIAM features, as defined in the use case diagram (Appendix B). Items preserve the underlying PU and PEOU constructs while tailoring referents to each role's operational reality. This approach is supported by role-based TAM adaptations in multi-stakeholder information systems (Lippert & Forman, 2005; Holden & Karsh, 2010).

The instrument is administered as a single post-test. Evaluators complete the questionnaire once after executing their role-specific simulation scenarios. No pre-test is administered; the study captures absolute acceptance levels rather than a shift from a manual baseline.

Items are measured on a five-point Likert scale (1 = Strongly Disagree to 5 = Strongly Agree). Item counts per role reflect the features each role accesses. Registrar Personnel: 8 items (applicant intake and record management). Guidance Personnel: 13 items (OMR scoring, scheduling, admission support, result processing, proctoring). First-Year Students: 9 items (applicant portal, chatbot, email notifications, result retrieval). Program Heads: 9 items (pre-enrolment lists, quota management, admission support dashboards).

*The four TAM indicator tables (Tables 3–6) remain unchanged from the Phase 2 corrected manuscript. Refer to NEXIAM_Phase2_Corrected_Manuscript.md for full table content.*

---

## Data Analysis

**Specific Objective 1 — Process Mapping and Workflow Analysis.** Semi-structured questionnaire responses and field observation notes from Guidance and Registrar staff are documented through process flow charts and analyzed through workflow analysis. The resulting process maps identify scoring bottlenecks, inter-office coordination failures, data integrity vulnerabilities, and notification deficiencies. These maps constitute the empirical foundation from which every NEXIAM feature is derived. No requirement enters development without a documented operational failure.

**Specific Objective 2 — Iterative Design Validation.** Completed modules are demonstrated to Guidance and Registrar staff during the construction cycle. Misalignments between system design and field-confirmed needs are logged as revision items, connecting every design change to a specific field finding. The functional requirements and system features tables simultaneously constitute the Chapter 3 system design section and the reference structure against which Cutover testing scenarios are built. User Design feedback, including mockup preferences and feature acceptance, is captured iteratively and documented within this objective. The RAD construction process — including Requirements Planning, User Design, Rapid Construction, and Cutover — is documented as part of this objective's methodology discussion, alongside the component integration decisions and architectural trade-offs encountered during development.

**Specific Objective 3 — Descriptive TAM Means.** Each respondent's items per construct are averaged into a single per-person construct score. These per-person scores are then averaged across all evaluator post-test responses to produce the pooled mean for Perceived Usefulness and Perceived Ease of Use. Means above the midpoint of the five-point scale (greater than 3.0) indicate positive acceptance. Standard deviations per construct are reported alongside the means to describe response consistency. Pooled means are reported with a breakdown of per-role descriptive means for each construct: Registrar Personnel, Guidance Personnel, First-Year Students, and Program Heads. Results are interpreted against established TAM benchmarks from the original Davis (1989) instrument and subsequent validation literature.

---

## Appendix B — Use Case Diagram Description

The use case diagram maps NEXIAM's actors against eighteen use cases covering intake, testing, admission support, and audits. Access is strictly role-isolated where actors only access functions their offices perform. A Super Admin role exists for explicit oversight access and future IT personnel assignment but is not evaluated. Account creation is distributed per office: Registrar Admin creates Registrar Staff accounts; Guidance Admin creates Guidance Counselor accounts.

**Applicant:** Authenticate, Submit Application Form, Query Chatbot, Track Application Status

**Proctor (duty overlay):** Authenticate, Scan QR for Attendance, Monitor Exam Sessions

**Guidance Counselor:** Authenticate, Schedule Exam Sessions, Input OMR/CSV/Raw Scores, Print Result Sheets, Schedules Release and Consultation, Configure Admission Support, Analyze Admission Support Indicators, Generate and View Reports

**Guidance Admin:** Configure Settings, Authenticate, Schedule Exam Sessions, Input OMR/CSV/Raw Scores, Print Result Sheets, Schedules Release and Consultation, Analyze Admission Support Indicators, Generate and View Reports, Review Audit Logs, Create Guidance Counselor Accounts

**Program Head:** Authenticate, Analyze Admission Support Indicators, Manage Program Quotas, Generate and View Reports, Review Audit Logs

**Registrar Staff:** Authenticate, Process Admission Entries, Track Application Status, Generate and View Reports

**Registrar Admin:** Configure Settings, Authenticate, Process Admission Entries, Track Application Status, Generate and View Reports, Review Audit Logs, Create Registrar Staff Accounts

---

## Cross-Document Consistency Checklist

| Section | Action |
|---|---|
| Objectives | Three specific objectives. SO2 includes RAD development methodology discussion. SO3 = TAM evaluation (PU+PEOU, 4 groups). |
| Null Hypotheses | Delete H₀₁ and H₀₂. Remove test specification paragraph. |
| Conceptual Framework | Replace old IPO with 3-input-group, 4-output-group diagram from refined comment. "RAD model" not "AI-Augmented RAD model." No AI overmention in framework. |
| Scope and Limitations | Direct, concise paragraphs. Component-level boundaries. No objective wrappers. |
| Research Design | "Process mapping and workflow analysis" replaces "thematic analysis." Objectives 2 and 3. |
| Population | 271 total. Remove IT Expert entirely. Registrar and Guidance as separate rows. |
| Research Instruments | 4 TAM tables (Tables 3–6). PU+PEOU only. No ATU/BIU discussion. 5-point Likert. Post-test only. |
| Data Analysis | SO1 = process mapping and workflow analysis. SO2 = iterative design validation (RAD phases documented within). SO3 = descriptive TAM means. |
| Software Model | Included in Phase 3. "RAD Model" (Figure 2). AI-assisted development discussed as implementation approach within Rapid Construction, not as a named model variant. "Paired TAM" → single post-test. "Purposive selected panels" → simulated evaluators. All four phases linked to specific objectives. |
| Glossary | Remove: Paired T-Test, Shapiro-Wilk, Wilcoxon, Pre-Test, IT Expert, Thematic Analysis. Update: TAM (2 constructs, 5-point, post-test). Add: Identifier Key, Staff Interface, Slovin's Formula, Process Mapping, Workflow Analysis. |
| References | Remove Venkatesh & Davis (2000), Venkatesh & Bala (2008), King & He (2006). Verify Lippert & Forman (2005) and Holden & Karsh (2010) in reference list. |
| Table of Contents | Remove Null Hypotheses entry. Update page numbers. |
| List of Tables | Remove old Tables 3–18. Add new Tables 3–6 (four TAM tables). Update Table 2 (Distribution of Respondents). |
