# Chapter 2: Review of Related Literature and Studies

## 2.8 Synthesis of Reviewed Literature and Conceptual Anchor

The literature reviewed across this chapter establishes that higher education institutions worldwide are modernizing their admissions and assessment infrastructures to manage growing applicant cohorts and enhance operational equity. However, an analysis of existing scholarly contributions reveals that technological solutions have historically been developed and evaluated in isolation.

Table 2.1 presents a comparative synthesis of the key studies reviewed, highlighting the technological focus, administrative scope, and structural boundaries of each contribution relative to the NEXIAM platform.

**Table 2.1**  
*Comparative Synthesis of Related Literature and System Architectures*

| Author / Study | Primary Technology / Methodology | Administrative Scope | Evaluated Population | Key Structural Limitation Relative to NEXIAM |
|---|---|---|---|---|
| Adwan et al. (2023) | Centralized mobile admission portal (Agile-SDLC) | Multi-university college application tracking | Prospective university applicants | Focuses on intake and tracking; excludes examination scheduling, OMR scoring, and counseling triage. |
| Rutvik et al. (2024) | Web-based admission system | Single-college digital application intake | College applicants and office staff | Treats testing and assessment as an external, disconnected activity. |
| Lira et al. (2023) | Machine learning predictive modeling for admissions | Holistic personal quality assessment | Large-scale university applicant cohorts | Focuses on predictive research; lacks integration into operational office workflows. |
| Van Busum & Fang (2025) | Algorithmic bias analysis of admission models | Fairness auditing of undergraduate classifiers | Historical applicant datasets | Focuses on algorithmic auditing without implementing operational mitigation pipelines. |
| Tan (2025) | K-Means clustering for course routing | Student cognitive preference grouping | College applicants | Purely descriptive; cannot compute calibrated degree compatibility without secondary heuristic tables. |
| Yukee et al. (2025) | K-Means clustering algorithm (CRISP-DM) | Retrospective ICAT examination score analysis | Archived historical student dataset at ISPSC Tagudin | Retrospective offline script; lacks live admission intake, OMR scoring, and real-time counseling support. |
| Ballesteros et al. (2025) | Student demographic and academic clustering | Retrospective student grouping using GWA | Enrolled freshmen at ISPSC Tagudin | Operates as retrospective research; not connected to live admission decision-making. |
| Hollmann et al. (2025) | Tabular Prior-data Fitted Networks (TabPFN) | Foundation model in-context tabular Bayesian inference | Synthetic and benchmark tabular datasets | Core algorithmic foundation; requires domain adaptation and role-governed user interfaces for educational deployment. |
| Cuerdo (2023) | EvalBee computer-vision OMR application | Commodity-hardware bubble sheet grading | Teachers and basic education examinees | Standalone grading utility; not integrated into institutional student records or counseling systems. |
| Dayata et al. (2023) | Carolinian Chatbot using RAG architecture | University handbook and policy inquiry answering | University undergraduate students | Informational Q&A utility; not connected to live student status records or examination databases. |
| Olipas (2020) | Rapid Application Development (RAD) | SUC administrative web platform engineering | State university staff and students | Validates RAD in Philippine SUCs; addresses general administration rather than admission testing. |
| Davis (1989); Lippert & Forman (2005) | Technology Acceptance Model (role-adapted TAM) | Information systems user acceptance measurement | Enterprise and multi-stakeholder users | Theoretical measurement framework; requires adaptation to specific college admission roles. |
| **NEXIAM (This Study)** | **Modular monolith (Laravel, PostgreSQL, Inertia), OMRChecker, TabPFN foundation triage, HMAC-SHA256 audit, RAD, role-adapted TAM** | **End-to-end admission testing pipeline: intake, scheduling, proctoring, OMR scoring, TabPFN course triage, result release, enrollment clearance** | **Complete operational office census (n = 5 administrative staff) and first-year students (n = 255 via Slovin's; total n = 260)** | **First unified platform connecting Guidance, Registrar, Academic Departments, and Applicants under Republic Acts 10931 and 10173.** |

---

### The Overarching Research and Engineering Gap

As demonstrated in the comparative synthesis, prior research provides robust technical validation for individual components:
* Unified mobile and web portals successfully eliminate applicant encoding bottlenecks (Adwan et al., 2023; Rutvik et al., 2024).
* Software-defined computer-vision OMR on commodity hardware eliminates the financial barrier of proprietary optical mark readers (Cuerdo, 2023; Thakur et al., 2025).
* Tabular foundation models resolve the small-sample overfitting dilemma inherent in small regional college cohorts while accommodating structural feature missingness (Hollmann et al., 2025).
* Retrieval-augmented conversational agents eliminate language model hallucinations when communicating institutional policies (Dayata et al., 2023; Lewis et al., 2020).
* The Rapid Application Development model and role-adapted Technology Acceptance Model provide proven frameworks for constructing and evaluating multi-stakeholder educational technologies in Philippine state universities (Olipas, 2020; Lippert & Forman, 2005).

At ISPSC Tagudin Campus, local researchers successfully verified that student entrance test scores group into meaningful cognitive clusters (Yukee et al., 2025) and that adjacent administrative functions can be digitized (Angala et al., 2023; Cenezan & Gacusan, 2025). 

However, the critical institutional gap remains: **no existing system unifies these capabilities into a single, role-governed pipeline.** Guidance staff continue to score paper sheets with plastic stencils over five-day periods; results are withheld until in-person counseling; coordination with the Registrar relies on informal text messages; sixty percent of applicants travel repeatedly from outside municipalities; and counselors advise applicants from memory without persistent records or real-time quota visibility.

NEXIAM bridges this operational gap by synthesizing these validated components into one cohesive architecture. By grounding the system design in the empirical failure modes confirmed under Specific Objective 1, engineering the platform through the RAD model under Specific Objective 2, and evaluating user acceptance across three primary operational stakeholder groups under Specific Objective 3, this study provides an integrated, legally compliant solution for modernizing college admission testing in Philippine state institutions.
