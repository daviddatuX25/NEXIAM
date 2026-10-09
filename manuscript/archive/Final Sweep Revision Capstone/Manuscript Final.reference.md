# Original Manuscript Reference — Manuscript Final.odt

**Read-only historical reference conversion.** The extraction did not alter the original. Current working sections are reviewed separately; obsolete methods and counts below are not instructions to restore them.

- Source: `manuscript/archive/Final Sweep Revision Capstone/Manuscript Final.odt`
- SHA-256: `0c57f004a0aa4f39a003c9437f68db2cfbb131b1920d679503311668e89cb02c`
- Provenance: this filename is cited as the source in the old defense bank. The specific adviser-checked version still needs matching; this is not a declaration that every historical claim remains approved.
- Preserve the original wording, names, counts, tables, and citations below. Older SO4, K-Means, sampling, and paired-test text is historical, not a direction to restore superseded methods.
- Word layout, page numbering, header/footer styling, and handwritten image text are not reconstructed. Embedded and native visuals are replaced by source/context placeholders.

---

<!-- ORIGINAL P001 -->
NEXIAM: A ROLE-BASED COLLEGE ADMISSION TESTING SYSTEM FOR THE GUIDANCE AND REGISTRAR OFFICES AT ISPSC TAGUDIN

<!-- ORIGINAL P002 -->


<!-- ORIGINAL P003 -->


<!-- ORIGINAL P004 -->


<!-- ORIGINAL P005 -->


<!-- ORIGINAL P006 -->
SARMIENTO, DAVID DATU N.
PAGADUAN, JAYPEE G.
LOPEZ, CHRISTINE M.

<!-- ORIGINAL P007 -->


<!-- ORIGINAL P008 -->


<!-- ORIGINAL P009 -->


<!-- ORIGINAL P010 -->


<!-- ORIGINAL P011 -->
ILOCOS SUR POLYTECHNIC STATE COLLEGE

<!-- ORIGINAL P012 -->
COLLEGE OF ARTS AND SCIENCES

<!-- ORIGINAL P013 -->
TAGUDIN, ILOCOS SUR

<!-- ORIGINAL P014 -->


<!-- ORIGINAL P015 -->


<!-- ORIGINAL P016 -->


<!-- ORIGINAL P017 -->
BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY

<!-- ORIGINAL P018 -->


<!-- ORIGINAL P019 -->


<!-- ORIGINAL P020 -->


<!-- ORIGINAL P021 -->
JUNE 2026

<!-- ORIGINAL P022 -->
# Chapter 1

<!-- ORIGINAL P023 -->
# INTRODUCTION

<!-- ORIGINAL P024 -->
## Background of the Study

<!-- ORIGINAL P025 -->
Assessment serves two purposes. The first is validation which gives feedback to students on what they have learned. The second is academic filtering that tests whether learners can excel in a certain field, or in this case, whether they are aligned with the university course they intend to pursue. Assessments remain prevalent in higher education. A 2024 scoping review by French et al. found that although assessments offer pedagogical benefits like stronger knowledge retention and increased motivation for some learners, these benefits are often outweighed by drawbacks such as heightened student anxiety, surface-level learning, and equity concerns. This means assessment design continues to receive scrutiny, including the psychometric properties of examinations. However, besides psychometric issues, there is also a gap concerning the operational logistics of administering examinations at scale. This operational perspective is comparatively under-researched, especially from the standpoint of institutional impact (Woelert, 2023).

<!-- ORIGINAL P026 -->
This revelation along with the researcher's background sets the direction of this study to solve the bottlenecks on the non-psychometric side of admissions, specifically the infrastructure surrounding the admission process. Patil et al. (2021) found that large-scale examinations and admissions encounter challenges with record integrity, applicant verification, and resource coordination, particularly when paper-based workflows are used. In contrast, Thakur et al. (2025), through their TrueGradeAI framework, showed that digital admission pipelines do more than automate scoring. They introduce architectural patterns that reduce grading bias and human subjectivity. The point is that while psychometric properties get considerable attention, the operational side of admissions remains under-invested despite being the component responsible for administering examinations. As a result, institutions often lack the downstream structures needed to process scores and release results efficiently. Liu et al. (2025) further explained that conventional admissions still rely on fixed historical yield assumptions while overlooking applicant behavior uncertainty. This means prolonged decision timelines distort enrollment projections and make it harder for institutions to convert admissions into enrollment. After results are released, speed matters from the applicant’s perspective because many applicants receive multiple offers and will commit to whichever institution processes them faster (Ruffalo Noel Levitz, 2023). Slow admissions are not merely an inconvenience. These become barriers that prevent the admission process from the proper guidance of applicants toward enrollment.

<!-- ORIGINAL P027 -->
In the global landscape, admission systems have also moved toward innovation. Ever since the COVID-19 pandemic, many institutions have fast tracked their adoption of artificial intelligence and cloud platforms to modernize administrative services (EDUCAUSE, 2023; Marinoni et al., 2020). Likewise, the application of machine learning to admission processes is also becoming more relevant. Lira et al. (2023), publishing in Science Advances, found that machine learning models can evaluate applicants using unstructured application materials like essays, and that these assessments correlate with long-term life outcomes. This strengthens the case for AI within admissions. However, Van Busum and Fang (2025) examined bias in AI admission models and raised fairness concerns about algorithm-assisted decision-making. Therefore, AI should not completely replace human judgment but instead function as part of a hybrid model where professional judgment remains central.

<!-- ORIGINAL P028 -->
With regards to unified admission platforms, Adwan et al. (2023) developed a centralized mobile-based admission application for universities in the GCC region. Their system achieved a 97.7% usability using Nielsen heuristics, which shows that unified admission platforms are practical across institutions. Inferring from this, a unified admission pipeline combines several operational features into one platform to address such admission-related bottlenecks. One onset feature is AI-powered chatbots, which is shown to handle routine applicant questions consistently at scale (Labadze et al., 2023). Another feature is role-based access control, which is important for keeping educational data secure at an institutional scale (Sandhu et al., 1996; Kabier et al., 2023).

<!-- ORIGINAL P029 -->
Nationally, the Philippines’ digitalization has been driven more by policy rather than by market pressure. The CHED Strategic Plan (CHED, 2022) sets such institutional direction. Rooting from the Republic Act No. 10931 (2017) which increased admission volumes after removing tuition fees in public higher education, putting more pressure on staff and infrastructure. In hindsight, the Republic Act No. 10173 (2012) adds to this pressure the  requirement to secure handling of applicant records, which paper systems cannot fully support. These laws make digitization both necessary and challenging. The National Privacy Commission (2020) has issued advisories for the education sector on handling applicant records. On top of this, Villarino (2025) found that students in provincial state colleges already use consumer AI tools ahead of their own institutions. This creates a governance gap that bypasses proper compliance with Republic Act No. 10173, despite students being ready and AI-literate. Admissions should therefore take advantage of this familiarity by using students’ AI literacy into institutional applications instead of leaving it under utilized. AI chatbots are one application of how this can work.

<!-- ORIGINAL P030 -->
Another relevant point is that every feature within a unified admission pipeline should first be validated independently. Cuerdo et al. (2021) validated EvalBee, an optical mark recognition tool, in a Philippine public school. Dayata and Yap (2024) deployed a retrieval-augmented generation pipeline at the University of San Carlos, published at IEEE COMNETSAT. Tan (2025) validated K-Means optimization for student course selection at an ACM conference. Together, these independent solutions support the development of an integrated admission pipeline.

<!-- ORIGINAL P031 -->
Locally, ISPSC Tagudin reflects these national issues. The campus processes about 700 to 1,000 freshman applicants every year using a fully manual workflow covering intake, examination, and result release. Previous campus studies have tried to improve institutional processes, but none addressed admissions directly. Yukee et al. (2025) validated K-Means profiling on the campus’s own entrance exam data. Ballesteros et al. (2025) confirmed that K-Means works for student profiling at ISPSC. Eustaquio and Nisperos (2023) built a web-based registration system with decision-support for billing, grading, scholarship, and retention tracking. These studies show that decision-support systems already exist on campus. Eustaquio and Nisperos in particular demonstrate that a single digital platform can support multiple administrative functions, providing a conceptual basis for an integrated admission platform. However, their system focused on post-admission processes, not the admission testing phase itself. Angala et al. (2023) implemented a document management system, and Cenezan and Gacusan (2025) built analytics-driven records management.

<!-- ORIGINAL P032 -->
The campus does not lack successful digitization projects or local research. What it lacks is a unified admission testing system. What is needed is a platform that is unified, role-governed, and architecturally integrated, bringing together scoring, aptitude assessment, applicant classification, and structured guidance within one pipeline. This integration has to comply with Republic Act No. 10173 by securing both physical records and digitally processed applicant information, especially when data is processed externally (Slade and Prinsloo, 2013). For the AI component, retrieval-augmented generation should draw from institutional policy documents rather than unrestricted language models to reduce hallucinations (Lewis et al., 2020).

<!-- ORIGINAL P033 -->
Therefore, a unified, role-governed admission testing system is the gap that surfaces from these literature reviews and contemporary necessities.  A platform that can integrate applicant registration,  streamlined testing operations, automated scoring, decision-support with AI advisory services, and result delivery into one admission ecosystem serving the Guidance Office, Registrar, and other institutional stakeholders. The remaining question is whether this integrated pipeline can outperform the current manual workflow and whether the offices using it will find it usable and use it with ease.

<!-- ORIGINAL P034 -->


<!-- ORIGINAL P035 -->
## Conceptual Framework of the Study

<!-- ORIGINAL P036 -->
NEXIAM uses an IPO model as its framework as this fits the  descriptive-developmental study. The input serves as variables that covers campus data like applicant profiles, exam scores, and manual workflow data. Following that is the process which iterates on the development through the defined software model, and detailed through the project planning and assignments. After the development stage, the system will be deployed and tested. This phase will be the conduct of Technology Acceptance Model or TAM. This will measure NEXIAM against the manual process and while being part of the process stage, TAM will transform system use to measurable evidences. Lastly, the output variables will be traced through two connected parts. First is the admission testing system platform itself and the second one is the research results from the paired TAM measures before and after deployment. These results will prove if NEXIAM outperforms manual methods with statistical backing. This conceptual framework combines system building and user testing into one design, fulfilling the objectives as a whole.

<!-- ORIGINAL P037 -->
[ORIGINAL VISUAL 1: native editable drawing element. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P037; native editable drawing element.
> **Nearby text:** Conceptual Framework of the Study

<!-- ORIGINAL P038 -->
INPUT


<!-- ORIGINAL P039 -->
Applicant profiles


<!-- ORIGINAL P040 -->
Exam & schedule preferences


<!-- ORIGINAL P041 -->
Counseling data


<!-- ORIGINAL P042 -->
Support queries


<!-- ORIGINAL P043 -->
Scanned answer sheets


<!-- ORIGINAL P044 -->
Program quotas


<!-- ORIGINAL P045 -->
Status updates


<!-- ORIGINAL P046 -->
Activity logs



[ORIGINAL VISUAL 2: native editable drawing element. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P037; native editable drawing element.
> **Nearby text:** Conceptual Framework of the Study

<!-- ORIGINAL P047 -->
OUTPUT


<!-- ORIGINAL P048 -->
Chatbot responses


<!-- ORIGINAL P049 -->
Pre-enrollment slates


<!-- ORIGINAL P050 -->
Status notifications


<!-- ORIGINAL P051 -->
Aptitude dashboards


<!-- ORIGINAL P052 -->
Exam scores & prints


<!-- ORIGINAL P053 -->
Optimized Exam Schedules


<!-- ORIGINAL P054 -->
Applicant portal


<!-- ORIGINAL P055 -->
Audit trails



[ORIGINAL VISUAL 3: native editable drawing element. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P037; native editable drawing element.
> **Nearby text:** Conceptual Framework of the Study

<!-- ORIGINAL P056 -->
PROCESS


<!-- ORIGINAL P057 -->
Development and Implementation of NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin

<!-- ORIGINAL P058 -->
[ORIGINAL VISUAL 4: native editable drawing group. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P058; native editable drawing group.
> **Nearby text:** PROCESS

<!-- ORIGINAL P059 -->



<!-- ORIGINAL P060 -->


Figure 1.  Input-Process-Output Diagram

<!-- ORIGINAL P061 -->


<!-- ORIGINAL P062 -->
## Objectives of the Study

<!-- ORIGINAL P063 -->
This study aims to develop NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus.  The web-based platform centralizes the admission testing pipeline that composes of applicant registration, testing, scoring, decision-support and result delivery.

<!-- ORIGINAL P064 -->
More specifically, this study seeks to accomplish the following:

<!-- ORIGINAL P065 -->
To analyze the existing college admission testing processes, operational workflows, and process gaps at ISPSC Tagudin Campus through descriptive inquiry.

<!-- ORIGINAL P066 -->
To determine the system requirements of NEXIAM in terms of functional requirements and system features needed to address the identified process gaps.

<!-- ORIGINAL P067 -->
To evaluate the acceptance of NEXIAM using the Technology Acceptance Model (TAM) in terms of perceived usefulness, perceived ease of use, attitude toward use, and behavioral intention to use.

<!-- ORIGINAL P068 -->
To determine whether there is a significant difference between the existing manual admission testing process and NEXIAM in terms of perceived usefulness and perceived ease of use.

<!-- ORIGINAL P069 -->
Null Hypotheses

<!-- ORIGINAL P070 -->
H₀₁: There is no significant difference in the perceived usefulness between the existing manual admission testing process and NEXIAM.

<!-- ORIGINAL P071 -->
H₀₂: There is no significant difference in the perceived ease of use between the existing manual admission testing process and NEXIAM.

<!-- ORIGINAL P072 -->
Test: Paired samples t-test, with the Wilcoxon signed-rank test as a non-parametric alternative contingent on Shapiro-Wilk normality results | α = .05 | Effect size: Cohen's d (parametric case) or matched-pairs rank-biserial correlation (non-parametric case)

<!-- ORIGINAL P073 -->
## Scope and Limitation of the Study

<!-- ORIGINAL P074 -->
This research covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, for the admission cycle running June through September 2026. The study covers the full admission testing pipeline from applicant registration through result release and post-examination counseling support, across seven user roles governed by Role-Based Access Control (RBAC). A Proctor actor perspective is additionally reflected in the system's use case model, representing a duty overlay assignable to any existing staff role at administrative discretion rather than a distinct eighth role.

<!-- ORIGINAL P075 -->
The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment, a decision driven by local campus hardware constraints. The proctoring interface is designed with offline resilience for examination venues where network connectivity may be unavailable or intermittent, a condition that reflects the documented infrastructure reality of ISPSC Tagudin Campus, where network access is concentrated in designated office areas and examination venues may have limited or no reliable connectivity. The proctoring interface uses service-worker caching to maintain examination session state during connectivity loss, with synchronization to the cloud database upon connection restoration. All other system functions outside the proctoring interface require cloud connectivity.

<!-- ORIGINAL P076 -->
AI inference for the hybrid RAG chatbot and the human-gated AI scheduling assistant relies on free-tier external model access, which bounds the volume of concurrent inference requests the system can sustain. Paid or dedicated inference capacity is outside this study's scope.

<!-- ORIGINAL P077 -->
Methodologically, because the September 2026 evaluation window occurs outside the active admission cycle, purposive sampling utilizes proxy evaluators executing role-based scenarios to simulate live operational conditions, measured via paired pre- and post-test Technology Acceptance Model (TAM) questionnaires. The study's evaluation window therefore cannot produce findings based on live applicant behavior, and results are accordingly bounded by simulated operational conditions.

<!-- ORIGINAL P078 -->
While NEXIAM is designed to support compliance with Republic Act No. 10173 through Role-Based Access Control and application-layer data governance, formal National Privacy Commission (NPC) certification and the Privacy Impact Assessment required for K-Means applicant profiling fall outside this study's scope. Applicant consent for digital processing is assumed to be covered under the existing ICAT application form's data collection procedures, which applicants complete as part of the standard admission process; formal verification of this consent basis is identified as future work alongside the NPC certification and Privacy Impact Assessment noted above.

<!-- ORIGINAL P079 -->
## Importance of the Study

<!-- ORIGINAL P080 -->
The Community. Local residents benefit from a streamlined admission process that reduces the financial and time costs associated with multiple mandatory campus visits. By digitizing result retrieval and notifications, the system lowers access barriers for applicants living in distant municipalities.

<!-- ORIGINAL P081 -->
The Registrar Office. Automated intake and scheduling tools replace manual document encoding with a traceable, digital workflow for better inter-office coordination. Role-based data isolation supports the office's compliance posture under Republic Act No. 10173 by operationalizing application-layer data protection aligned with the Act's key obligations.

<!-- ORIGINAL P082 -->
The Guidance Office. Computer-vision scoring and live data triage automate time-consuming clerical tasks, allowing counselors to focus on professional student placement and profile analysis. The chatbot and digital audit trails further enhance office efficiency and data accountability.

<!-- ORIGINAL P083 -->
The Program Heads. By shifting quota management to a real-time dashboard, Program Heads are freed from fielding continuous, informal status inquiries from counselors during active consultation sessions, gaining an immediate overview of their department's enrollment capacity.

<!-- ORIGINAL P084 -->
The Applicants. Applicants gain access to a self-service portal for 24/7 status updates, with result records secured by the platform's tamper-evident HMAC-SHA256 audit logging. The platform eliminates the need for physical visits for result retrieval while providing immediate answers through the chatbot.

<!-- ORIGINAL P085 -->
Ilocos Sur Polytechnic State College. The institution gains a deployable reference implementation that operationalizes local capstone research into active administrative tools. This demonstrates how modern technical frameworks can solve specific operational failures within a state college environment, establishing an empirical blueprint that directly reconciles ambitious macroeconomic digitization mandates with the acute resource limitations of regional public institutions.

<!-- ORIGINAL P086 -->
The Students. Incoming students validate that the admission exam process effectively reflects their true performance, ensuring that subsequent course placement is accurate and satisfying. This persistent profile remains securely accessible for personal verification and distributed multi-office institutional use without any record duplication.

<!-- ORIGINAL P087 -->
The Researchers. The team develops advanced technical competencies in AI-assisted development, security architecture, and offline synchronization within a full project lifecycle. Methodologically, the study builds expertise in descriptive-developmental inquiry and empirical evaluation using the TAM framework.

<!-- ORIGINAL P088 -->
Future Researchers. This study provides a technical blueprint for role-based, offline-resilient platforms in constrained institutions. It establishes a framework for human-directed, AI-augmented software development, showing how small teams leverage generative models under strict public mandates. Additionally, it offers a replicable model for transitioning machine learning from post-hoc analysis into live decision-support, enabling longitudinal verification of applicant profiling outcomes.

<!-- ORIGINAL P089 -->
# Chapter 2

<!-- ORIGINAL P090 -->
# METHODOLOGY

<!-- ORIGINAL P091 -->
This chapter presents the procedures for data collection and analysis, covering the research design, software model, project plan, project assignments, population and locale, research instruments, and data analysis.

<!-- ORIGINAL P092 -->
## Research Design

<!-- ORIGINAL P093 -->
This study employs a descriptive-developmental research design (Frey, 2022) to sequentially diagnose existing admission workflows at ISPSC Tagudin Campus and construct an evidence-based platform that resolves them. In the descriptive phase, data gathered through semi-structured administrator questionnaires and formally documented field observations undergo thematic analysis, structuring preliminary operational pain points into validated emergent themes. The developmental phase subsequently converts these finalized themes into the functional scope of NEXIAM, ensuring every integrated system requirement traces directly to a confirmed administrative failure

<!-- ORIGINAL P094 -->
The developmental component encompasses the iterative design, construction, and validation of NEXIAM, addressing Specific Objectives 2, 3, and 4. System requirements extracted from the descriptive findings are organized into a functional requirements table and a system features table constituting the output of Specific Objective 2. The system is then constructed through the RAD workflow described in the following section and evaluated through simulated user acceptance testing with purposively selected proxy evaluators, using the TAM questionnaire as the primary evaluation instrument.

<!-- ORIGINAL P095 -->
## Software Model

<!-- ORIGINAL P096 -->
In developing institutional web systems, the Rapid Application Development (RAD) model (Martin, 1991; Pressman & Maxim, 2020) has proven effective for ensuring functional suitability and security, particularly in recent Philippine state university deployments (Olipas, 2020; Melendres & Aranda, 2024). This study advances traditional RAD methodology by systematically integrating AI-augmented development, aligning with recent Department of Science and Technology (2025) initiatives that equip State Universities and Colleges with generative AI to accelerate prototyping. This reflects what a 2026 Google whitepaper terms the transition from ad-hoc prompting to agentic engineering (Osmani et al., 2026). Under this paradigm, AI agents reliably generate the majority of routine implementation work, while developers act as system orchestrators focused entirely on architectural intent, specification quality, and complex edge cases. Crucially, to ensure generation velocity does not compromise system integrity, every AI-produced artifact is strictly governed by the workflow illustrated in Figure 2. This continuous feedback loop ensures human judgment controls the codebase prior to integration.

<!-- ORIGINAL P097 -->
[ORIGINAL VISUAL 5: embedded image. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P097; embedded image.
> **Embedded source:** `Pictures/100000010000140B0000086C77D9F60C.png`.
> **Nearby text:** Software Model

Figure 2. AI-Augmented Rapid Application Development (RAD) Model

<!-- ORIGINAL P098 -->
Requirements Planning. This phase constitutes the descriptive inquiry of the methodology, establishing an empirical baseline of institutional workflow gaps. It systematically gathers verified operational evidence through questionnaires and field observation to produce the formal technical specifications and the initial database schema. These validated operational failures become the highly specific local context used to ground all subsequent AI prompts, preventing the generation of generic software solutions.

<!-- ORIGINAL P099 -->
User Design. This phase translates the field-verified requirements into deterministic architectural blueprints. It formulates the relational Role-Based Access Control database schema, establishes the authorization matrix, and generates interface mockups. By finalizing these deterministic blueprints before coding begins, the team ensures the AI generates database structures that inherently comply with the Data Privacy Act (Republic Act No. 10173), structurally preventing the model from outputting non-compliant architectures.

<!-- ORIGINAL P100 -->
Rapid Construction. This phase forms the technical development core, where the team systematically researches and evaluates potential application frameworks to accommodate complex system requirements. The team directs AI models to construct the heavy processing layers, such as the logic for computer-vision OMR scoring and the orchestration for the Retrieval-Augmented Generation chatbot. The developers conduct rigorous peer code reviews on every AI-generated module to prevent architectural debt and align with the productivity gains documented in recent AI software engineering studies (Peng et al., 2023).

<!-- ORIGINAL P101 -->
Cutover. This final phase transitions the development cycle into empirical evaluation. It deploys the completed NEXIAM artifact into a secure cloud-hosted environment to fully demonstrate its operational capacity. Following deployment, the phase conducts simulated user testing with proxy panels and administers the paired Technology Acceptance Model evaluation protocol to measure system acceptance through rigorous statistical validation.

<!-- ORIGINAL P102 -->
## Project Plan

<!-- ORIGINAL P103 -->
The project timeline spans four months, from June to September 2026, organized by the four RAD phases and presented in Figure 3 as a Gantt chart.

<!-- ORIGINAL TABLE T01 -->
|  | June 2026 | July 2026 | August 2026 | September 2026 |
| --- | --- | --- | --- | --- |
| Requirements Planning |  |  |  |  |
| User Design |  |  |  |  |
| Rapid Construction |  |  |  |  |
| Cutover |  |  |  |  |

<!-- ORIGINAL P131 -->
Figure 3. Project Gantt Chart

<!-- ORIGINAL P132 -->
The Requirements Planning phase occupies June 2026, covering administrator questionnaires, workflow observations, thematic analysis, formal specification drafting, and initial database schema. Findings from this phase are confirmed directly with the Guidance and Registrar Offices before the team proceeds to User Design, ensuring that every documented pain point is validated by the office that actually experiences it rather than assumed from observation alone. User Design runs from late June to early July 2026, producing the role-based schema, authorization matrix, application service architecture, and interface mockups for all seven roles. This phase exists specifically to validate which feature needs are confirmed before construction begins, rather than to accumulate additional features. Rapid Construction spans July to August 2026 as the build-and-test core, structured as an iterative back-and-forth between design refinement and incremental construction rather than a single linear build, prioritizing core functionality over feature breadth wherever development time is constrained. Coding work is scheduled around the team's ongoing academic course load, including weekend sessions during heavier class weeks, and the extent of AI-assisted code generation is bounded by the team's own funding for model access. Cutover occupies September 2026, encompassing cloud-hosted deployment, proxy evaluation, and manuscript finalization and documentation. Milestones include Title Defense and Proposal Defense, which falls within June 2026, with Final Defense anticipated in the September-to-October 2026 window.

<!-- ORIGINAL P133 -->
This timeline deliberately allocates its largest continuous block to Rapid Construction. Because the team utilizes the agentic engineering methodology, the traditional timeline bottleneck of manual syntax generation is removed from the schedule. This specific logistical shift is what allows a compact three-person academic team to successfully execute heavy enterprise layers within a strict four-month operational window. By recovering hours that would otherwise be spent writing boilerplate code, the researchers reallocate their scheduling capacity directly into complex algorithmic tuning, such as refining the K-Means aptitude clustering thresholds and validating the HMAC-SHA256 cryptographic auditing trails. Ultimately, this approach secures vital integration testing time for the complex subsystems without compressing the Cutover evaluation, thereby supporting system stability before the campus's next admission cycle.

<!-- ORIGINAL P134 -->
## Project Assignments

<!-- ORIGINAL P135 -->
Five project roles are distributed across the three team members, as detailed in Table 1. This cross-functional distribution is intentionally structured to maximize the operational capacity of a compact academic team. By establishing dual roles and overlapping development duties, the researchers ensure continuous project momentum, facilitate rigorous peer code review during complex technical integrations, and maintain strict adherence to the defined timeline. This formal matrix isolates system construction from objective testing to prevent internal evaluation bias.

<!-- ORIGINAL P136 -->
Table 1. Project Roles and Responsibilities

<!-- ORIGINAL TABLE T02 -->
| Role | Name | Function |
| --- | --- | --- |
| Project Manager | David Datu N. Sarmiento | Coordinates project timelines, stakeholder communication, and overall research execution across all RAD phases. |
| System Analyst and Designer | David Datu N. Sarmiento,<br/>Jaypee G. Pagaduan | Designs the system architecture, database schema, RBAC security models, and converts field requirements into technical specifications. |
| Lead Developer and Programmer | Jaypee G. Pagaduan | Implements backend services, frontend components, and specialized integrations (OMR, AI Features, K-Means clustering). |
| Quality Assurance Tester | Christine M. Lopez | Manages the evaluation protocol, administers the TAM instrument, and tracks system defects during the Cutover testing phase. |
| Technical Writer and Documenter | David Datu N. Sarmiento,<br/>Jaypee G. Pagaduan,<br/>Christine M. Lopez | Collaboratively drafts, edits, and finalizes the research manuscript, evaluation results, and formal system documentation. |

<!-- ORIGINAL P158 -->
## Population and Locale of the Study

<!-- ORIGINAL P159 -->
The study is conducted at the Ilocos Sur Polytechnic State College (ISPSC) Tagudin Campus, targeting the administrative offices and academic personnel managing the admission cycle. Because the September 2026 evaluation occurs outside the active admission period, actual applicants are unavailable, necessitating purposive sampling. The sample consists of 49 purposively selected evaluators divided into two cohorts: an operational proxy panel (n=46) and a technical expert panel (n=3). The operational panel comprises Guidance Office staff (n=2) and Registrar Office personnel (n=3) for administrative functions, Program Heads (n=11) for course triage, and first-year students (n=30) evaluating the applicant portal. Guidance Office (n=2) and Registrar Office personnel (n=3) represent the complete available staff complement of their respective offices at the Tagudin Campus; no further sampling was possible within these units, as no larger pool of eligible personnel exists at this campus. The technical panel consists of three (n=3) IT experts evaluating system architecture, role-based security, data privacy compliance, and the super administrator operational role. During simulated testing, researchers direct all evaluators through role-specific use cases, after which each participant independently completes the Technology Acceptance Model (TAM) questionnaire to ensure unbiased data collection. This structured approach is designed to ensure that the simulated scenarios closely mirror real-world administrative workflows. Furthermore, it is intended to measure the platform's readiness for actual institutional deployment once Rapid Construction is complete.

<!-- ORIGINAL P160 -->


<!-- ORIGINAL P161 -->
Table 2. Distribution of Respondents

<!-- ORIGINAL TABLE T03 -->
| Respondents | n |
| --- | --- |
| First Year Students | 30 |
| Registrar Office Personnel | 3 |
| Guidance Office Personnel | 2 |
| Program Head | 11 |
| IT Experts | 3 |
| Total | 49 |

<!-- ORIGINAL P176 -->
## Research Instruments

<!-- ORIGINAL P177 -->
This study utilizes an extended Technology Acceptance Model (TAM) questionnaire as its primary evaluation instrument. Grounded in Davis (1989), the tool measures perceived usefulness and perceived ease of use. It integrates attitude toward use and behavioral intention to use, following Venkatesh and Davis (2000) and King and He (2006). This extended TAM framework has been empirically validated for assessing digital service transitions specifically within Philippine higher education institutions (Ampuan & Deleña, 2024; Quiban, 2025). Evaluating these constructs pinpoints potential user resistance points regarding the adoption of novel system features like automated scheduling and OMR scoring.

<!-- ORIGINAL P178 -->
To ensure construct validity, the 16-item instrument employs role-adapted phrasing detailed in Tables 3 through 6. Indicators are tailored to the operational realities of administrators, applicants, and IT experts while preserving underlying psychometric properties. Although constructs remain fixed per Davis (1989), role-based item adaptation preserves content validity across the heterogeneous sample. Pooling across variants assumes measurement equivalence despite wording differences, a limitation this study acknowledges as an unvalidated psychometric property. Items are measured on a seven-point Likert scale from 1 (strongly disagree) to 7 (strongly agree), with four items per construct. This expanded scale provides greater granular variance than five-point alternatives, empowering respondents to articulate subtle shifts in perception between manual and digital workflows.

<!-- ORIGINAL P179 -->
Methodologically, a within-subject, parallel object design requires evaluators to complete the instrument twice, assessing the baseline manual process and NEXIAM post-simulation. This paired structure neutralizes between-group variability, yielding the empirical comparison required to test the null hypotheses in Specific Objective 4. Contrasting baseline paper inefficiencies against post-test data directly quantifies system success in resolving bottlenecks.

<!-- ORIGINAL P180 -->
Table 3.1. TAM Indicators for Perceived Usefulness (PU) of Registrar & Guidance Personnel

<!-- ORIGINAL TABLE T04 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | The current manual process allows me to process admissions efficiently. | NEXIAM allows me to process admissions efficiently. |
| 2 | The current manual process helps me complete admission-related tasks faster. | NEXIAM helps me complete admission-related tasks faster. |
| 3 | The current manual process gives me sufficient visibility into an applicant's status when needed. | NEXIAM gives me sufficient visibility into an applicant's status when needed. |
| 4 | The current manual process reduces my overall workload when coordinating with other offices. | NEXIAM reduces my overall workload when coordinating with other offices. |

<!-- ORIGINAL P196 -->


<!-- ORIGINAL P197 -->
Table 3.2. TAM Indicators for Perceived Usefulness (PU) of Applicant (First-Year Student Proxy)

<!-- ORIGINAL TABLE T05 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | The current way of getting updates and results is fast and helpful. | The NEXIAM portal and chatbot are fast and helpful for getting updates and results. |
| 2 | The current way of submitting my application is convenient. | The NEXIAM portal makes submitting my application convenient. |
| 3 | The current process keeps me adequately informed about my application status. | NEXIAM keeps me adequately informed about my application status. |
| 4 | The current process makes it easy for me to verify that my exam results are accurate. | NEXIAM's results portal makes it easy for me to verify that my exam results are accurate. |

<!-- ORIGINAL P213 -->


<!-- ORIGINAL P214 -->
Table 3.3. TAM Indicators for Perceived Usefulness (PU) of Program Head

<!-- ORIGINAL TABLE T06 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | The current informal method of communicating quota updates effectively supports student placement. | The real-time quota visibility in NEXIAM effectively supports student placement. |
| 2 | My current level of visibility regarding incoming applicants helps me make timely program decisions. | The pre-enrollment slate in NEXIAM helps me make timely program decisions. |
| 3 | The current method of coordinating with Guidance counselors is productive. | Coordinating with Guidance counselors through NEXIAM is productive. |
| 4 | The current method gives me an accurate picture of my department's enrollment capacity. | NEXIAM gives me an accurate picture of my department's enrollment capacity. |

<!-- ORIGINAL P230 -->


<!-- ORIGINAL P231 -->


<!-- ORIGINAL P232 -->
Table 3.4. TAM Indicators for Perceived Usefulness (PU) of IT Expert

<!-- ORIGINAL TABLE T07 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | The current paper-based data handling effectively manages operational bottlenecks. | The NEXIAM architecture effectively addresses the institution's operational bottlenecks. |
| 2 | The current data-handling approach supports reliable record-keeping. | The NEXIAM architecture supports reliable record-keeping. |
| 3 | The current setup makes it easy to verify that data privacy requirements and examination record integrity are being met. | The NEXIAM architecture make it easy to verify that data privacy requirements and examination record integrity are being met. |
| 4 | The current infrastructure is sufficient for the institution's operational needs. | The NEXIAM architecture is sufficient for the institution's operational needs. |

<!-- ORIGINAL P248 -->


<!-- ORIGINAL P249 -->
Table 4.1. TAM Indicators for Perceived Ease of Use (PEOU) of Registrar & Guidance Personnel

<!-- ORIGINAL P250 -->


<!-- ORIGINAL TABLE T08 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | It is easy to navigate the current manual scheduling and stencil scoring. | It is easy to navigate the AI-assisted scheduling and automated OMR scoring. |
| 2 | Learning how to carry out the current admission procedures took little effort. | Learning how to use NEXIAM took little effort. |
| 3 | It is easy for me to correct mistakes when using the current manual process. | It is easy for me to correct mistakes when using NEXIAM. |
| 4 | Interacting with the current process is generally clear and understandable. | Interacting with NEXIAM is generally clear and understandable. |

<!-- ORIGINAL P266 -->


<!-- ORIGINAL P267 -->


<!-- ORIGINAL P268 -->
Table 4.2. TAM Indicators for Perceived Ease of Use (PEOU) of Applicant (First-Year Student Proxy)

<!-- ORIGINAL TABLE T09 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | Completing the admission steps through the current physical process is easy to understand. | Completing the admission steps through the NEXIAM portal is easy to understand. |
| 2 | It is easy for me to find the information I need during the current admission process. | It is easy for me to find the information I need on the NEXIAM portal. |
| 3 | Becoming skillful at the current admission process requires little effort on my part. | Becoming skillful at using NEXIAM requires little effort on my part. |
| 4 | The steps required by the current process are clear at every stage. | The steps required by NEXIAM are clear at every stage. |

<!-- ORIGINAL P284 -->


<!-- ORIGINAL P285 -->


<!-- ORIGINAL P286 -->
Table 4.3. TAM Indicators for Perceived Ease of Use (PEOU) of Program Head

<!-- ORIGINAL TABLE T10 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | It is easy to track and communicate enrollment quota changes to counselors. | It is easy to update and monitor enrollment quotas through NEXIAM. |
| 2 | Anticipating incoming student routing through the current method is simple. | Reviewing the pre-enrollment slate through the NEXIAM dashboard is simple. |
| 3 | The current method requires minimal effort to keep updated. | NEXIAM requires minimal effort to keep updated. |
| 4 | The current method is flexible enough to interact with the way I want. | NEXIAM is flexible enough to interact with the way I want. |

<!-- ORIGINAL P302 -->


<!-- ORIGINAL P303 -->


<!-- ORIGINAL P304 -->


<!-- ORIGINAL P305 -->
Table 4.4. TAM Indicators for Perceived Ease of Use (PEOU) of IT Expert

<!-- ORIGINAL TABLE T11 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | The existing physical workflow is simple for the institution to maintain. | The new digital framework is logically structured and simple to maintain. |
| 2 | It is easy to learn how the current system's processes function. | It is easy to learn how NEXIAM's architecture functions. |
| 3 | Troubleshooting issues within the current setup requires little technical effort. | Troubleshooting issues within NEXIAM requires little technical effort. |
| 4 | The current security and access arrangements are easy to manage. | NEXIAM's role-based access control is easy to manage. |

<!-- ORIGINAL P321 -->


<!-- ORIGINAL P322 -->
Table 5.1. TAM Indicators for Attitude Toward Use (ATU) of Registrar & Guidance Personnel

<!-- ORIGINAL TABLE T12 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I have a favorable attitude toward the current manual admission workflow. | I have a favorable attitude toward using the NEXIAM platform. |
| 2 | Using the current manual process is a good idea for managing admissions. | Using NEXIAM is a good idea for managing admissions. |
| 3 | I find the current manual process to be a pleasant way of getting work done. | I find NEXIAM to be a pleasant way of getting work done. |
| 4 | Overall, I am satisfied with the current manual process. | Overall, I am satisfied with NEXIAM. |

<!-- ORIGINAL P338 -->


<!-- ORIGINAL P339 -->


<!-- ORIGINAL P340 -->
Table 5.2. TAM Indicators for Attitude Toward Use (ATU) of Applicant (First-Year Student Proxy)

<!-- ORIGINAL TABLE T13 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I feel positive about my experience with the current in-person admission process. | I feel positive about my experience using the automated NEXIAM process. |
| 2 | Using the current process to apply is a good way to handle my application. | Using NEXIAM to apply is a good way to handle my application. |
| 3 | I find the current admission process to be a pleasant experience overall. | I find NEXIAM to be a pleasant experience overall. |
| 4 | Overall, I am satisfied with the current admission process. | Overall, I am satisfied with NEXIAM. |

<!-- ORIGINAL P356 -->


<!-- ORIGINAL P357 -->
Table 5.3. TAM Indicators for Attitude Toward Use (ATU) of Program Head

<!-- ORIGINAL TABLE T14 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I have a favorable attitude toward using informal channels (e.g., texts, calls) for quota updates. | I have a favorable attitude toward using the NEXIAM dashboard for quota updates. |
| 2 | Relying on my current level of applicant visibility is a good approach for my department. | Relying on the NEXIAM pre-enrollment slate is a good approach for my department. |
| 3 | I find the current method of receiving updates to be a pleasant part of my work. | I find NEXIAM to be a pleasant part of my work. |
| 4 | Overall, I am satisfied with the current method of managing quota communication. | Overall, I am satisfied with NEXIAM for managing quota communication. |

<!-- ORIGINAL P373 -->


<!-- ORIGINAL P374 -->
Table 5.4. TAM Indicators for Attitude Toward Use (ATU) of IT Expert

<!-- ORIGINAL TABLE T15 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | The current infrastructure is a sound operational model for the campus. | The cloud-hosted NEXIAM framework is a sound technical model for the campus. |
| 2 | The current infrastructure reflects good technical practice for this institution. | NEXIAM's architecture reflects good technical practice for this institution. |
| 3 | I find the current setup to be a reasonable use of the institution's resources. | I find NEXIAM to be a reasonable use of the institution's resources. |
| 4 | Overall, I am satisfied with the current technical setup. | Overall, I am satisfied with NEXIAM's technical architecture. |

<!-- ORIGINAL P390 -->


<!-- ORIGINAL P391 -->
Table 6.1. TAM Indicators for Behavioral Intention to Use (BIU) of Registrar & Guidance Personnel

<!-- ORIGINAL TABLE T16 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I would prefer to keep using the manual process for future academic cycles. | I intend to use NEXIAM for future academic admission cycles. |
| 2 | I would recommend continuing the current manual process to colleagues in other offices. | I would recommend NEXIAM to colleagues in other offices. |
| 3 | I plan to keep relying on the current process for upcoming admission cycles. | I plan to rely on NEXIAM for upcoming admission cycles. |
| 4 | Given the choice, I would continue using the current manual process. | Given the choice, I would continue using NEXIAM. |

<!-- ORIGINAL P407 -->


<!-- ORIGINAL P408 -->


<!-- ORIGINAL P409 -->
Table 6.2. TAM Indicators for Behavioral Intention to Use (BIU) of Applicant (First-Year Student Proxy)

<!-- ORIGINAL TABLE T17 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I would recommend the current manual process to future incoming freshmen. | I would highly recommend NEXIAM to future incoming freshmen. |
| 2 | I would choose to go through the current process again if I had to reapply. | I would choose to use NEXIAM again if I had to reapply. |
| 3 | I intend to continue following the current process for any future transactions with the institution. | I intend to continue using NEXIAM for any future transactions with the institution. |
| 4 | I plan to rely on the current process to stay updated on my application. | I plan to rely on NEXIAM to stay updated on my application. |

<!-- ORIGINAL P425 -->


<!-- ORIGINAL P426 -->
Table 6.3. TAM Indicators for Behavioral Intention to Use (BIU) of Program Head

<!-- ORIGINAL TABLE T18 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I intend to continue using informal communication for department quota management. | I intend to use the NEXIAM dashboard for department quota management in future admission cycles. |
| 2 | I would recommend the current way of tracking incoming applicants to other Program Heads. | I would recommend NEXIAM's pre-enrollment slate to other Program Heads. |
| 3 | I plan to keep using the current method for future admission cycles. | I plan to keep using NEXIAM for future admission cycles. |
| 4 | Given the choice, I would continue relying on the current method. | Given the choice, I would continue relying on NEXIAM. |

<!-- ORIGINAL P442 -->
Table 6.4. TAM Indicators for Behavioral Intention to Use (BIU) of IT Expert

<!-- ORIGINAL TABLE T19 -->
| Item | Pre-Test Indicator | Post-Test Indicator |
| --- | --- | --- |
| 1 | I would endorse maintaining the current manual system. | I would endorse the institutional deployment of NEXIAM. |
| 2 | I would recommend the current system setup to other technical staff. | I would recommend NEXIAM to other technical staff. |
| 3 | I plan to continue supporting the current setup in future cycles. | I plan to continue supporting NEXIAM in future cycles. |
| 4 | Given the choice, I would continue maintaining the current setup over adopting a new system. | Given the choice, I would continue maintaining NEXIAM over reverting to the current setup. |

<!-- ORIGINAL P458 -->
## Data Analysis

<!-- ORIGINAL P459 -->
For Specific Objective 1, qualitative thematic analysis is applied to questionnaire responses from Guidance and Registrar staff. Responses are transcribed, coded, and analyzed to structure the operational themes observed during field inquiry: scoring bottlenecks, inter-office coordination failures, data integrity vulnerabilities, and notification deficiencies. Researcher field observation notes supply the procedural context that questionnaire data alone cannot fully capture. The resulting thematic structure constitutes the empirical foundation from which every NEXIAM feature is derived: no requirement enters development without a documented operational failure.

<!-- ORIGINAL P460 -->
For Specific Objective 2, design validation proceeds iteratively during the construction cycle. Completed modules are demonstrated to Guidance and Registrar staff, and any misalignment between the system's design and field-confirmed needs is logged as a revision item, connecting every design change to a specific field finding. The functional requirements and system features tables simultaneously constitute Chapter 3's system design section and the reference structure against which Cutover testing scenarios are built.

<!-- ORIGINAL P461 -->
For Specific Objective 3, each respondent's four items per construct are averaged into a single per-person construct score; these per-person scores are then averaged across all evaluator post-test responses to produce the pooled mean for each TAM construct. Means above the midpoint of the seven-point scale (>4.0) indicate positive acceptance; standard deviations per construct are reported alongside the means to describe response consistency. Pooled means are reported alongside a breakdown of per-role descriptive means (Registrar/Guidance, Applicant proxy, Program Head, IT Expert) for each construct. Results are interpreted against established TAM benchmarks from the original instrument and its subsequent validation literature.

<!-- ORIGINAL P462 -->
For Specific Objective 4, a paired samples t-test compares pre-test and post-test scores for perceived usefulness and perceived ease of use at a significance threshold of α = 0.05. However, because the total sample size is relatively small (n = 49), a Shapiro-Wilk test evaluates the normality distribution of the paired differences. If the normality assumption is violated, the Wilcoxon signed-rank test is deployed as the robust non-parametric alternative to ensure statistical validity. Effect size is reported as Cohen's d when the paired t-test is used, or as the matched-pairs rank-biserial correlation when the Wilcoxon signed-rank test is used instead.

<!-- ORIGINAL P463 -->
Glosary

<!-- ORIGINAL P464 -->
Admission Testing System. A structured platform or set of procedures through which an educational institution assesses the academic aptitude, readiness, or qualifications of prospective students. In the context of this study, it refers to the digitized, web-based system that manages the full admission testing lifecycle at ISPSC Tagudin—from applicant registration to the release of examination results.

<!-- ORIGINAL P465 -->
Applicant Portal. A dedicated web-based interface accessible to prospective students, enabling them to register, submit requirements, monitor their application status, receive examination schedules, and view results without requiring physical presence on campus. In NEXIAM, the portal operates on a 24/7 basis to close the equity gap caused by distance and limited office hours.

<!-- ORIGINAL P466 -->
Aptitude Pattern. A descriptive grouping or cluster of examination results that reveals trends in applicant performance across different subject areas or program tracks. In NEXIAM, aptitude patterns are surfaced through K-Means Clustering and are intended to support—not replace—counselor decision-making.

<!-- ORIGINAL P467 -->
Artificial Intelligence (AI). A branch of computer science concerned with building systems capable of performing tasks that typically require human intelligence, such as understanding language, recognizing patterns, and making decisions. In this study, AI is applied in three components: the RAG-based chatbot for FAQ responses, AI-assisted examination scheduling, and computer-vision-based optical mark recognition.

<!-- ORIGINAL P468 -->
AI-Augmented Rapid Application Development (AI-RAD). A software development methodology that integrates artificial intelligence tools—particularly AI code generation agents—into the iterative phases of Rapid Application Development. AI-Augmented RAD accelerates coding cycles while maintaining a human review gate that approves or rejects all AI-generated code before integration. In this study, AI-Augmented RAD is the adopted software development model for constructing NEXIAM.

<!-- ORIGINAL P469 -->
Audit Trail. A chronological, tamper-evident record of system events and user actions that enables the verification and tracing of data changes over time. In NEXIAM, audit trail integrity is enforced using HMAC-SHA256 cryptographic hashing, ensuring that any unauthorized modification of log entries is detectable.

<!-- ORIGINAL P470 -->
Bubble Sheet.A standardized paper answer form on which examinees mark their responses by filling in circles (bubbles) corresponding to answer choices. In the context of this study, bubble sheets are the physical medium used during the paper-based admission examination and are subsequently digitized and scored by NEXIAM's Optical Mark Recognition module.

<!-- ORIGINAL P471 -->
Chatbot. A conversational software agent designed to simulate dialogue with users by automatically responding to queries in natural language. In NEXIAM, the chatbot is built using Retrieval-Augmented Generation (RAG) and is restricted to the institution's own data to prevent inaccurate or out-of-scope responses.

<!-- ORIGINAL P472 -->
Cloud-Based System. A computing architecture in which data storage, processing, and application services are hosted on remote servers accessible via the internet, rather than on local hardware. NEXIAM operates as a cloud-based system overall, providing anywhere-access to administrators, guidance counselors, registrar staff, and applicants.

<!-- ORIGINAL P473 -->
Computer Vision. A field of artificial intelligence that enables computers to interpret and process visual information from images or video. In NEXIAM, computer vision is applied in the Optical Mark Recognition module to automatically detect and score marked bubbles on physical answer sheets, replacing error-prone manual checking.

<!-- ORIGINAL P474 -->
Data Privacy Act (RA 10173). Republic Act 10173, known as the Data Privacy Act of 2012, is a Philippine law that mandates the protection of personal data collected, stored, and processed by both government and private entities. NEXIAM is designed to comply with the data protection provisions of RA 10173 at the application layer, though it does not pursue formal certification from the National Privacy Commission (NPC).

<!-- ORIGINAL P475 -->
Free Higher Education Act (RA 10931). Republic Act 10931, enacted in the Philippines in 2017, mandates the provision of free tuition and other school fees in all state universities and colleges. The legislation significantly increased the volume of college applicants in public higher education institutions, creating demand for scalable, efficient admission systems such as NEXIAM.

<!-- ORIGINAL P476 -->
Guidance Office. The administrative unit within an educational institution responsible for student counseling, psychological assessment, and related support services. In the NEXIAM system, the Guidance Office is assigned a specific user role with access to aptitude cluster visualizations and relevant applicant data, while remaining bounded by Role-Based Access Control.

<!-- ORIGINAL P477 -->
HMAC-SHA256. Hash-based Message Authentication Code using the Secure Hash Algorithm 256-bit variant. It is a cryptographic function that combines a secret key with message data to produce a fixed-length hash, ensuring both data integrity and authenticity. In NEXIAM, HMAC-SHA256 is used to generate tamper-evident signatures on audit log entries, preventing unauthorized alteration of records.

<!-- ORIGINAL P478 -->
Human-Gated Approval. A system design principle wherein an automated process or AI-generated recommendation cannot take effect until a designated human actor reviews and explicitly approves it. In NEXIAM, AI-assisted exam scheduling is human-gated—the system proposes slot arrangements, but a Guidance or Registrar officer must authorize before any schedule is committed.

<!-- ORIGINAL P479 -->
Input-Process-Output (IPO) Model. A conceptual framework used in systems analysis to describe a system in terms of the data it receives (Input), the operations it performs (Process), and the results it produces (Output). In NEXIAM's conceptual framework, the IPO model is mapped alongside layer categories to illustrate how Role-Based Access Control and AI features operate across each functional output.

<!-- ORIGINAL P480 -->
K-Means Clustering. An unsupervised machine learning algorithm that partitions a dataset into a predefined number (k) of clusters by minimizing the variance within each cluster. In NEXIAM, K-Means Clustering is applied to examination result data to surface descriptive aptitude groupings for counselors. It is strictly a visualization and pattern-discovery tool and does not make or recommend admission decisions.

<!-- ORIGINAL P481 -->
Likert Scale. A psychometric scale commonly used in survey instruments to capture respondents' attitudes, perceptions, or levels of agreement along an ordered continuum. In this study, a 7-point Likert scale (ranging from Strongly Disagree to Strongly Agree) is used to measure user acceptance of NEXIAM across the four constructs of the Technology Acceptance Model.

<!-- ORIGINAL P482 -->
NEXIAM. The proposed system developed in this study. The name stands for the system's core identity as a Next-generation Examination and Intelligent Admission Management platform. NEXIAM is a web-based, role-governed college admission testing system designed for implementation at ISPSC Tagudin, integrating Role-Based Access Control, computer-vision OMR, K-Means aptitude clustering, a RAG chatbot, AI-assisted scheduling, and HMAC-SHA256 audit logging.

<!-- ORIGINAL P483 -->
Offline Resilience. The capacity of a software system or module to continue functioning in a degraded or limited mode when internet connectivity is unavailable. In NEXIAM, offline resilience is implemented specifically in the proctoring interface via service-worker caching, acknowledging that examination venues in the institution may experience connectivity interruptions.

<!-- ORIGINAL P484 -->
Optical Mark Recognition (OMR). A technology that uses computer vision or image processing algorithms to detect and interpret marks—typically filled circles or boxes—on specially designed paper forms. In NEXIAM, OMR automates the checking of bubble-sheet answer forms, replacing a process that previously required five days to two weeks of manual hand-scoring.

<!-- ORIGINAL P485 -->
Paired T-Test. A parametric inferential statistical test used to determine whether the mean difference between two related sets of observations is statistically significant. In this study, a paired t-test will be used in Specific Objective 4 to compare pre-NEXIAM and post-NEXIAM performance metrics, provided the Shapiro-Wilk test confirms the normality of the data distribution.

<!-- ORIGINAL P486 -->
Post-Test. An evaluation instrument administered after a subject has experienced an intervention, treatment, or system usage. In this study, the Post-Test is a second administration of the TAM survey, conducted after evaluators have interacted with NEXIAM, to measure the actual shift in user acceptance perceptions compared to the Pre-Test baseline.

<!-- ORIGINAL P487 -->
Pre-Test. An evaluation instrument administered before a subject interacts with a system or undergoes an intervention, providing a baseline measurement for comparison. In this study, the Pre-Test is the initial administration of the TAM survey, capturing evaluators' acceptance perceptions before using NEXIAM.

<!-- ORIGINAL P488 -->
Proctor. An authorized individual responsible for supervising the conduct of an examination, ensuring adherence to testing rules, and recording relevant observations. In NEXIAM, the Proctor is one of the seven defined user roles, with access rights scoped to the proctoring interface, including inter-office logging and attendance marking.

<!-- ORIGINAL P489 -->
Purposive Sampling. A non-probability sampling method in which participants are deliberately selected based on specific characteristics relevant to the research objectives, rather than at random. In this study, purposive sampling is used to identify 49 evaluators—46 for the Operational Panel and 3 for the Technical Panel—who can meaningfully assess NEXIAM's functionality and security.

<!-- ORIGINAL P490 -->
Rapid Application Development (RAD). A software development methodology that prioritizes iterative prototyping, continuous user feedback, and accelerated delivery over extensive upfront planning. RAD is structured around four phases: Requirements Planning, User Design, Construction, and Cutover. In this study, RAD is enhanced with AI code generation tools to form the AI-Augmented RAD model.

<!-- ORIGINAL P491 -->
Registrar Office. The administrative unit of an educational institution responsible for managing official academic records, enrollment data, and related documentation. In NEXIAM, the Registrar Office holds a dedicated user role with access to applicant records within the admission testing scope, while enrollment and tuition data remain out of scope.

<!-- ORIGINAL P492 -->
Retrieval-Augmented Generation (RAG). A natural language processing architecture that enhances the responses of a large language model by first retrieving relevant documents or data from a curated knowledge base, then using that context to generate accurate, grounded replies. In NEXIAM, the RAG-based chatbot draws exclusively from the institution's own data, preventing the system from producing responses based on unverified external information.

<!-- ORIGINAL P493 -->
Role-Based Access Control (RBAC). A security model that restricts system access and functionality based on the predefined roles assigned to individual users. Each role is granted only the permissions necessary for its designated functions, enforcing the principle of least privilege. In NEXIAM, RBAC governs seven distinct user roles—including Applicant, Proctor, Guidance, Registrar, and IT Administrator—and is enforced end-to-end across nearly every system output.

<!-- ORIGINAL P494 -->
Service Worker. A background script executed by the browser, independent of the main web page, that can intercept network requests and serve cached responses when connectivity is unavailable. In NEXIAM, service workers enable the proctoring interface to maintain offline resilience, allowing proctors to continue recording examination data during internet disruptions in the testing venue.

<!-- ORIGINAL P495 -->
Shapiro-Wilk Test. A statistical test used to assess whether a given dataset follows a normal (Gaussian) distribution. In this study, the Shapiro-Wilk test is applied as a normality check in Specific Objective 4. If the data is found to be normally distributed, a paired t-test is used; if not, the non-parametric Wilcoxon signed-rank test is applied instead.

<!-- ORIGINAL P496 -->
State University or College (SUC). A government-funded higher education institution in the Philippines established by law and operating under the Commission on Higher Education. ISPSC Tagudin, the locale of this study, is a SUC. NEXIAM is designed as a deployable blueprint applicable to other regional state colleges facing similar admission volume and process challenges.

<!-- ORIGINAL P497 -->
Technology Acceptance Model (TAM). A theoretical framework introduced by Davis (1989) that explains and predicts user acceptance of information systems based on two primary constructs: Perceived Usefulness (PU) and Perceived Ease of Use (PEOU). In this study, TAM is extended to four constructs—Usefulness, Ease of Use, Attitude Toward Use, and Behavioral Intention to Use—measured through a 16-item, role-adapted, 7-point Likert survey administered as both a Pre-Test and Post-Test.

<!-- ORIGINAL P498 -->
Thematic Analysis. A qualitative research method for identifying, analyzing, and reporting recurring patterns or themes within data, typically gathered through interviews or focus group discussions. In this study, thematic analysis is applied in Specific Objective 1 to extract and categorize pain points from interviews with Guidance and Registrar staff regarding the current manual admission testing process.

<!-- ORIGINAL P499 -->
User Role. A defined set of permissions and access rights assigned to a category of system users based on their organizational function. NEXIAM supports seven distinct user roles: Applicant, Proctor, Guidance Counselor, Guidance Administrator, Registrar Staff, Registrar Administrator, and System IT Administrator. Each role is governed by RBAC to ensure data confidentiality and operational integrity.

<!-- ORIGINAL P500 -->
Web-Based System. An application that runs on a web server and is accessed by users through a standard web browser over a network connection, without requiring local software installation. NEXIAM is a web-based system, enabling multi-role access from any device with an internet connection, with the exception of the offline-resilient proctoring interface.

<!-- ORIGINAL P501 -->
Wilcoxon Signed-Rank Test. A non-parametric inferential statistical test used to compare two related samples when the assumption of normality cannot be met. It evaluates whether the median difference between paired observations is significantly different from zero. In this study, the Wilcoxon signed-rank test serves as the alternative to the paired t-test for Specific Objective 4, applied when the Shapiro-Wilk test indicates that the data is not normally distributed.

<!-- ORIGINAL P502 -->
# REFERENCES

<!-- ORIGINAL P503 -->
Adwan, E. J., Al-Aradi, Y., Essa, M., & Malabari, H. (2023). A comprehensive study and development of unified mobile-based admission system for GCC universities. *Journal of Computing Theories and Applications*, *1*(2), 212–230. https://doi.org/10.62411/jcta.9793

<!-- ORIGINAL P504 -->
Ampuan, A. D., & Deleña, R. D. (2024). A quantitative evaluation of online appointment system at Mindanao State University–Main Campus: Employing the System Usability Scale (SUS) and Technology Acceptance Model (TAM). In *2024 3rd International Conference on Digital Transformation and Applications (ICDXA)* (pp. 1–6). IEEE. https://doi.org/10.1109/icdxa61007.2024.10470770

<!-- ORIGINAL P505 -->
Angala, D. T., Casugay, B. C. O., Estillore, H. M. Q., Lebantino, J. B., Marcha, S. O., Valdez, J. E. C., & Villanueva Jr., G. R. (2023). Development and implementation of document management system for Ilocos Sur Polytechnic State College, Tagudin Campus. *E-DAWA: An International Multidisciplinary Research Journal*, *3*(Special Issue), 33–42. https://doi.org/10.56901/HPCB6748

<!-- ORIGINAL P506 -->
Ballesteros, B. N. V., Habon, P. M., Lopez, J. D. O., & Tan, L. D. (2025). *FreshGroup: Clustering first-year student profiles using unsupervised machine learning* [Unpublished undergraduate capstone study]. ISPSC — Tagudin Campus, Tagudin, Ilocos Sur, Philippines.

<!-- ORIGINAL P507 -->


<!-- ORIGINAL P508 -->
Calfoforo, J. C., & Raga, R. C. (2024). Unleashing AI in education: A pre-trained LLMs for accurate and efficient question-answering systems. In *2024 21st International Conference on Information Technology Based Higher Education and Training (ITHET)* (pp. 1–6). IEEE. https://doi.org/10.1109/ithet61869.2024.10837606

<!-- ORIGINAL P509 -->
Catalan, J. A. (2017). A framework for automated multiple-choice exam scoring with digital image and assorted processing using readily available software. In *DLSU Research Congress 2017* (pp. 1–6). De La Salle University.

<!-- ORIGINAL P510 -->
Cenezan, J. F., & Gacusan, R. M. (2025). Development of a student support services system through data analytics approach in Students' Affairs and Services Office records management system of Ilocos Sur Polytechnic State College – Main Campus. *E-DAWA: An International Multidisciplinary Research Journal*, *5*(2), 32–41. https://doi.org/10.56901/edawa2025V5I2-03

<!-- ORIGINAL P511 -->
Cenezan, J. F., & Valdez, H. E. U. (2023). Enhanced faculty performance evaluation system with data analytics. *E-DAWA: An International Multidisciplinary Research Journal*, *3*(2), 107–118. https://doi.org/10.56901/KXPW6512

<!-- ORIGINAL P512 -->
CHED. (2022). *CHED strategic plan for higher education 2022–2028*. Commission on Higher Education, Republic of the Philippines. https://ched.gov.ph

<!-- ORIGINAL P513 -->
Cohen, J. (1988). *Statistical power analysis for the behavioral sciences* (2nd ed.). Lawrence Erlbaum Associates.

<!-- ORIGINAL P514 -->


<!-- ORIGINAL P515 -->
Cuerdo, R., Ison, M. J. B., & Oñate, C. D. T. (2021). Effectiveness of automation in evaluating test results using EvalBee as an alternative Optical Mark Recognition (OMR): A quantitative-evaluative approach from a Philippine public school. *International Journal of Theory and Application in Elementary and Secondary School Education*, *3*(1), 71–85. https://doi.org/10.31098/ijtaese.v3i2.661

<!-- ORIGINAL P516 -->
Dash, P. P., Rachana, R. L., Chauhan, A., Bhargavi, V., & Chandana, A. C. (2024). *A unified AI-assisted platform for college discovery and application: Design, development, and user needs analysis* [Unpublished manuscript].

<!-- ORIGINAL P517 -->
Davis, F. D. (1989). Perceived usefulness, perceived ease of use, and user acceptance of information technology. *MIS Quarterly*, *13*(3), 319–340. https://doi.org/10.2307/249008

<!-- ORIGINAL P518 -->
Dayata, W. M. A., & Yap, S. Y. C. (2024). Carolinian chatbot: End-to-end question-answer pipeline on the University of San Carlos policies, procedures, and guidelines via retrieval augmented generation. In *2024 IEEE International Conference on Communication, Networks and Satellite (COMNETSAT)* (pp. 145–152). IEEE. https://doi.org/10.1109/comnetsat63286.2024.10862750

<!-- ORIGINAL P519 -->
Department of Science and Technology – Advanced Science and Technology Institute. (2025, May 16). *KAIa Natin! DOST unveils local tech to build a future-ready AI ecosystem in the Philippines* [Press release]. https://asti.dost.gov.ph/news-articles/press-release-kaia-natin-dost-unveils-local-tech-to-build-a-future-ready-ai-ecosystem-in-the-philippines/

<!-- ORIGINAL P520 -->


<!-- ORIGINAL P521 -->
EDUCAUSE. (2023). *2023 EDUCAUSE Horizon Report: Teaching and learning edition*. https://library.educause.edu/resources/2023/3/2023-educause-horizon-report

<!-- ORIGINAL P522 -->
Esquivel, J. A., & Esquivel, J. A. (2020). Using a binary classification model to predict the likelihood of enrolment to the undergraduate program of a Philippine university. *International Journal of Computer Trends and Technology*, *68*(5), 6–10. https://doi.org/10.14445/22312803/ijctt-v68i5p103

<!-- ORIGINAL P523 -->
Eustaquio, J. Z., & Nisperos, Z. A. V. (2023). A web-based student registration and information system of Ilocos Sur Polytechnic State College with decision support capability. *International Journal of Engineering Research in Computer Science and Engineering*, *10*(9), 117–123. https://www.technoarete.org/common_abstract/pdf/IJERCSE/v10/i9/Ext_18597.pdf

<!-- ORIGINAL P524 -->
French, S., Dickerson, A., & Mulder, R. A. (2024). A review of the benefits and drawbacks of high-stakes final examinations in higher education. *Higher Education*, *88*(3), 893–918. https://doi.org/10.1007/s10734-023-01148-z

<!-- ORIGINAL P525 -->
Frey, B. B. (2022). *The SAGE encyclopedia of research design* (2nd ed.). SAGE Publications. https://doi.org/10.4135/9781071812082

<!-- ORIGINAL P526 -->
Ilagan, J. B., Yu, W. S., See, S. M., & Rayco, S. (2025). A virtual support agent for university students powered by a large language model: Conversational user experience design considerations. In *International Conference on Human-Computer Interaction (HCII 2025), Lecture Notes in Computer Science* (pp. 238–247). Springer. https://doi.org/10.1007/978-3-031-93415-5_15

<!-- ORIGINAL P527 -->
Kabier, M. K., Yassin, A. A., Abduljabbar, Z. A., & Lu, S. (2023). Role based access control using biometric in educational system. *Basrah Researches Sciences*, *49*(1), 8–18. https://doi.org/10.56714/bjrs.49.1.8

<!-- ORIGINAL P528 -->
King, W. R., & He, J. (2006). A meta-analysis of the technology acceptance model. *Information & Management*, *43*(6), 740–755. https://doi.org/10.1016/j.im.2006.05.003

<!-- ORIGINAL P529 -->
Labadze, L., Grigolia, M., & Machaidze, L. (2023). Role of AI chatbots in education: Systematic literature review. *International Journal of Educational Technology in Higher Education*, *20*(1), Article 56. https://doi.org/10.1186/s41239-023-00426-1

<!-- ORIGINAL P530 -->
Lewis, P., Perez, E., Piktus, A., Petroni, F., Karpukhin, V., Goyal, N., Küttler, H., Lewis, M., Yih, W., Rocktäschel, T., Riedel, S., & Kiela, D. (2020). Retrieval-augmented generation for knowledge-intensive NLP tasks. *Advances in Neural Information Processing Systems*, *33*, 9459–9474.

<!-- ORIGINAL P531 -->
Lira, B., Gardner, M., Quirk, A., Stone, C., Rao, A., Ungar, L., Hutt, S., Hickman, L., D'Mello, S. K., & Duckworth, A. L. (2023). Using artificial intelligence to assess personal qualities in college admissions. *Science Advances*, *9*(41), eadg9405. https://doi.org/10.1126/sciadv.adg9405

<!-- ORIGINAL P532 -->
Liu, T., Schenk, C., Braun, S., & Frey, A. (2025). A machine-learning-based approach to informing student admission decisions. *Behavioral Sciences*, *15*(3), 330. https://doi.org/10.3390/bs15030330

<!-- ORIGINAL P533 -->
Marinoni, G., van't Land, H., & Jensen, T. (2020). *The impact of COVID-19 on higher education around the world: IAU Global Survey Report*. International Association of Universities. https://iau.global

<!-- ORIGINAL P534 -->
Martin, J. (1991). *Rapid application development*. Macmillan Publishing Company.

<!-- ORIGINAL P535 -->
Melendres, U. M., & Aranda, K. M. (2024). Development and evaluation of a web-based resident information management system. *Journal of Computer, Software, and Program*, *1*(1), 14–22. https://doi.org/10.69739/jcsp.v1i1.50

<!-- ORIGINAL P536 -->
Mittal, S., & Malhotra, I. (2026). Recent advancements in course recommendation systems in the era of large language models (2022–2025). *IEEE Access*, *13*, 41365–41398. https://doi.org/10.1109/access.2026.3681211

<!-- ORIGINAL P537 -->
Nafrees, A. C. M., & Kariapper, R. K. A. R. (2020). Application of online management information system to the examination department, state universities in Sri Lanka. *Journal of Critical Reviews*, *7*(12), 758–762.

<!-- ORIGINAL P538 -->
National Privacy Commission. (2020). *Data Privacy Council Education Sector Advisory No. 2020-01: Guidelines for educational institutions*. Republic of the Philippines. https://privacy.gov.ph/wp-content/uploads/2023/05/DP-Council-Education-Sector-Advisory-No.-2020-1.pdf

<!-- ORIGINAL P539 -->
Neupane, S., Hossain, E., Keith, J., Tripathi, H., Ghiasi, F., Golilarz, N. A., Amirlatifi, A., Mittal, S., & Rahimi, S. (2024). From questions to insightful answers: Building an informed chatbot for university resources. In *2024 IEEE Frontiers in Education Conference (FIE)* (pp. 1–9). IEEE. https://doi.org/10.1109/fie61694.2024.10892994

<!-- ORIGINAL P540 -->
Olipas, C. N. P. (2020). The design and development of student information and violation management system (SIVMS) for a higher educational institution. *International Journal for Innovative Research in Multidisciplinary Field*, *6*(8), 72–80.

<!-- ORIGINAL P541 -->
Osmani, A., Saboo, S., & Kartakis, S. (2026). *The new SDLC with vibe coding: From ad-hoc prompting to agentic engineering*. Google. https://www.kaggle.com/whitepaper-the-new-SDLC-with-vibe-coding

<!-- ORIGINAL P542 -->
Patil, Y. N., Kiwelekar, A. W., Netak, L. D., & Deosarkar, S. B. (2021). A decentralized and autonomous model to administer university examinations. In S. W. Lee, I. Singh, & M. Mohammadian (Eds.), *Blockchain technology for IoT applications* (pp. 119–134). Springer. https://doi.org/10.1007/978-981-33-4122-7_6

<!-- ORIGINAL P543 -->
Peng, S., Kalliamvakou, E., Cihon, P., & Demirer, M. (2023). The impact of AI on developer productivity: Evidence from GitHub Copilot. *arXiv:2302.06590*. https://arxiv.org/abs/2302.06590

<!-- ORIGINAL P544 -->
Pressman, R. S., & Maxim, B. R. (2020). *Software engineering: A practitioner's approach* (9th ed.). McGraw-Hill Education.

<!-- ORIGINAL P545 -->
Quiban, J. (2025). Modeling faculty acceptance of LMS: A PLS-SEM validation of the technology acceptance model in Philippine higher education. *Business, Education, Social Sciences, and Technology*, *1*(1). https://doi.org/10.69478/best2025v1n1a016

<!-- ORIGINAL P546 -->
Republic Act No. 10173. (2012). *Data Privacy Act of 2012*. Republic of the Philippines. https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html

<!-- ORIGINAL P547 -->
Republic Act No. 10844. (2016). *Department of Information and Communications Technology Act of 2015*. Republic of the Philippines. https://lawphil.net/statutes/repacts/ra2016/ra_10844_2016.html

<!-- ORIGINAL P548 -->
Republic Act No. 10931. (2017). *Universal Access to Quality Tertiary Education Act*. Republic of the Philippines. https://lawphil.net/statutes/repacts/ra2017/ra_10931_2017.html

<!-- ORIGINAL P549 -->
Ruffalo Noel Levitz. (2023). Landing the grad student: The critical role of admissions efficiency. *RuffaloNL Blog*. https://www.ruffalonl.com/blog/graduate-online-enrollment/landing-the-grad-student-the-critical-role-of-admissions-efficiency/

<!-- ORIGINAL P550 -->
Rutvik, K., Piyush, D., Abhay, W., Vedant, K., & Rutika, G. (2024). Web based college admission system. *International Journal of Trend in Scientific Research and Development (IJTSRD)*, *8*(3), 1190–1194.

<!-- ORIGINAL P551 -->
Sánchez-Mendiola, M., García-Minjares, M., Buzo-Casanova, E. R., & Martínez-González, A. (2025). A large-scale high-stakes university admission exam: Pre- and trans-pandemic comparison. *Educational Research and Evaluation*, *31*(3-4), 273–293. https://doi.org/10.1080/13803611.2025.2608787

<!-- ORIGINAL P552 -->
Sandhu, R. S., Coyne, E. J., Feinstein, H. L., & Youman, C. E. (1996). Role-based access control models. *IEEE Computer*, *29*(2), 38–47. https://doi.org/10.1109/2.485845

<!-- ORIGINAL P553 -->


<!-- ORIGINAL P554 -->
Slade, S., & Prinsloo, P. (2013). Learning analytics: Ethical issues and dilemmas. *American Behavioral Scientist*, *57*(10), 1510–1529. https://doi.org/10.1177/0002764213479366

<!-- ORIGINAL P555 -->
Tamascelli, M., Bunch, O., Fowler, B., Taeb, M., & Cohen, A. (2025). Academic advising chatbot powered with AI agent. In *Proceedings of the 2025 ACM Southeast Conference* (pp. 182–189). ACM. https://doi.org/10.1145/3696673.3723065

<!-- ORIGINAL P556 -->
Tan, Z. (2025). Modeling student course selection preferences using K-Means optimization for intelligent recommendation. In *Proceedings of the 2nd International Conference on Intelligent Education and Computer Technology* (pp. 432–438). ACM. https://doi.org/10.1145/3764206.3764289

<!-- ORIGINAL P557 -->
Thakur, R., Kaushik, S., Chopra, G., & Rohilla, H. (2025). TrueGradeAI: Retrieval-augmented and bias-resistant AI for transparent and explainable digital assessments. *arXiv:2509.22516*. https://arxiv.org/abs/2509.22516

<!-- ORIGINAL P558 -->
Van Busum, K., & Fang, S. (2025). Bias analysis of AI models for undergraduate student admissions. *Neural Computing and Applications*, *36*(12), 6675–6683. https://doi.org/10.1007/s00521-024-10762-6

<!-- ORIGINAL P559 -->
Van Deventer, H., Mills, M., & Evrard, A. (2024). From interests to insights: An LLM approach to course recommendations using natural language queries. *arXiv:2412.19312*. https://arxiv.org/abs/2412.19312

<!-- ORIGINAL P560 -->


<!-- ORIGINAL P561 -->
Venkatesh, V., & Davis, F. D. (2000). A theoretical extension of the technology acceptance model: Four longitudinal field studies. *Management Science*, *46*(2), 186–204. https://doi.org/10.1287/mnsc.46.2.186.11926

<!-- ORIGINAL P562 -->
Yukee, A. J. M., Bonifacio, C. L., Salvador, J. M. A., & Macabitas, A. P. (2025). *A clustering student ICAT score using machine learning algorithm* [Unpublished undergraduate capstone study]. Ilocos Sur Polytechnic State College (ISPSC), Tagudin Campus, Tagudin, Ilocos Sur, Philippines.

<!-- ORIGINAL P563 -->


<!-- ORIGINAL P564 -->


<!-- ORIGINAL P565 -->


<!-- ORIGINAL P566 -->


<!-- ORIGINAL P567 -->


<!-- ORIGINAL P568 -->


<!-- ORIGINAL P569 -->


<!-- ORIGINAL P570 -->


<!-- ORIGINAL P571 -->


<!-- ORIGINAL P572 -->


<!-- ORIGINAL P573 -->


<!-- ORIGINAL P574 -->
# APPENDICES

<!-- ORIGINAL P575 -->
## APPENDIX A

<!-- ORIGINAL P576 -->
SCAN OF SIGNED LETTER OF CONDUCT

<!-- ORIGINAL P577 -->
Given the revision of the study concering its brand name as advised by its panelist, the name NEXIAM is put in place in replacement to SECURECAT.

<!-- ORIGINAL P578 -->
[ORIGINAL VISUAL 6: SCAN OF SIGNED LETTER OF CONDUCT. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P578; embedded image.
> **Embedded source:** `Pictures/1000000000000A6C00000EBF39BE8D29.jpg`.
> **Nearby text:** SCAN OF SIGNED LETTER OF CONDUCT

<!-- ORIGINAL P579 -->
## APPENDIX B

<!-- ORIGINAL P580 -->
USE CASE DIAGRAM

<!-- ORIGINAL P581 -->
[ORIGINAL VISUAL 7: USE CASE DIAGRAM. Retain the original document visual here; do not generate replacement evidence.]
> **Source context:** P581; embedded image.
> **Embedded source:** `Pictures/100000010000077D000008551A429A5C.png`.
> **Nearby text:** APPENDIX B

The use case diagram maps NEXIAM's eight actors namely: Applicant, Proctor, Guidance Counselor, Program Head, Super Admin, Registrar Staff, Registrar Admin, and Guidance Admin against eighteen use cases covering intake, testing, K-Means triage, and audits. Access is strictly role-isolated where actors only access functions their offices perform, with the Super Admin managing overall function and accounts.

