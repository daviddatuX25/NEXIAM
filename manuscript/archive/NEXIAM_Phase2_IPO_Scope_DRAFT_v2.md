# NEXIAM Phase 2 — IPO and Scope Revision (DRAFT v2)

**Status:** Locked except P4 (RAG data boundaries — David to fill)
**Basis:** Phase 1 panel feedback + David's Phase 2 direction

---

## REVISED IPO DIAGRAM (Figure 1)

### INPUT
1. Applicant profiles
2. Exam and schedule preferences
3. Admission inquiries
4. Advisory notes
5. Scanned answer sheets
6. Program quotas
7. Activity logs
8. Computer vision OMR engine
9. AI orchestration framework
10. Admission support models

### PROCESS
Development and Implementation of NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin

### OUTPUT
1. Chatbot responses
2. Pre-enrolment lists
3. Exam scores and prints
4. Results and release sheet
5. Admission support dashboards
6. Optimized exam schedules
7. Applicant portal
8. Audit trails
9. Email updates

---

## REVISED CONCEPTUAL FRAMEWORK PARAGRAPH

Structured as a descriptive-developmental study, NEXIAM's conceptual framework utilizes an Input-Process-Output model. The input captures two categories: institutional data flowing into the system — applicant profiles, examination preferences, admission inquiries, advisory notes, scanned answer sheets, program quotas, and activity logs — and the development foundations integrated during construction — a computer vision OMR engine for automated scoring, an AI orchestration framework for model routing, and admission support models for historical cohort profiling and preliminary program matching. These development inputs are not runtime data. They are the technical building blocks selected and integrated during the Rapid Construction phase.

The process iterates through the AI-Augmented RAD model. Requirements Planning captures the operational baseline through descriptive inquiry, User Design produces the RBAC schema and authorization matrix, Rapid Construction integrates the AI components under human code review, and Cutover deploys the system for evaluation. The Technology Acceptance Model is embedded within this flow. A pre-test establishes the manual process baseline. After simulated operational use, a post-test measures the shift in user acceptance. The evaluation is not a post-hoc activity. It is the validation mechanism built into the development cycle.

The output is twofold. First is the functional platform itself — the admission testing system producing chatbot responses, pre-enrolment lists, exam scores and prints, results and release sheets, admission support dashboards, optimized exam schedules, an applicant portal accessed via identifier key, email updates, and cryptographically verifiable audit trails. Second is the empirical evidence. Paired TAM measurements, analyzed through a paired t-test or Wilcoxon signed-rank test at α = .05 depending on Shapiro-Wilk normality results, establish whether NEXIAM yields a statistically significant improvement over the existing manual workflow. These outputs address Objectives 3 and 4. The Input and Process framing serves Objectives 1 and 2.

---

## REVISED SCOPE AND LIMITATIONS

This research covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, for the admission cycle running June through September 2026. The study covers the full admission testing pipeline from applicant registration through result release and post-examination counseling support, across seven user roles governed by Role-Based Access Control (RBAC). A Proctor actor perspective is additionally reflected in the system's use case model, representing a duty overlay assignable to any existing staff role at administrative discretion rather than a distinct eighth role.

The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment, a decision driven by local campus hardware constraints. The proctoring interface is designed with offline resilience for examination venues where network connectivity may be unavailable or intermittent, using service-worker caching to maintain examination session state during connectivity loss, with synchronization to the cloud database upon connection restoration. All other system functions outside the proctoring interface require cloud connectivity.

### Per-Component Scope Specification

**Optical Mark Recognition (OMR).** The system integrates a computer vision OMR engine to automate bubble-sheet scoring. The module uses gated decisioning. Answer sheets are processed in batches, and any marked bubble falling below a configurable confidence threshold is flagged for human verification rather than auto-scored. The study does not measure OMR classification accuracy as a standalone metric. OMR is validated through the overall TAM acceptance evaluation — specifically through Perceived Usefulness items measuring whether automated scoring improves over manual hand-scoring — not through per-sheet accuracy benchmarking against a ground-truth dataset.

**RAG Chatbot.** The Retrieval-Augmented Generation chatbot answers applicant queries by retrieving from a curated institutional knowledge base built from ISPSC admission policy documents, examination schedules, and program requirement files. The chatbot draws exclusively from this knowledge base. If no document matches above the retrieval confidence threshold, it declines to answer rather than fabricating a response. [PENDING P4: Specify which applicant data fields the chatbot is scoped to gather, and which data categories are explicitly excluded.] The chatbot's responses are evaluated through TAM's Perceived Usefulness and Perceived Ease of Use constructs as part of the overall system acceptance measurement. AI inference relies on free-tier external model access, which bounds the volume of concurrent inference requests the system can sustain. Paid or dedicated inference capacity is outside this study's scope.

**Admission Support Models (Machine Learning Engine).** The machine learning component of this study is bounded strictly to the architectural design and functional integration of an extensible interface wrapper within the admission system. While the module is engineered as a pluggable framework capable of loading external data assets — specifically an unsupervised model for Historical Cohort Profiling and a simple baseline supervised model for Preliminary Program Matching — the intrinsic mathematical accuracy, parameter optimization, algorithmic tuning, and predictive validity of these underlying models remain completely unmeasured and outside the scope of this research. The evaluation of this module is restricted purely to its system utility, multi-role data serving capabilities, and user acceptance testing. The K-Means engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard. The algorithm is strictly descriptive. It does not make admission decisions. Every placement requires manual counselor confirmation. Program capacity is managed through a separate quota interface and excluded from the clustering algorithm itself.

**Normal Admission Functions.** The platform handles applicant registration, examination scheduling, result processing, and inter-office coordination through RBAC-governed dashboards. Applicants access the system through a web-based portal authenticated via a unique identifier key embedded in their applicant profile — replacing traditional account creation with a simpler, single-key access mechanism. Applicants receive email notifications for schedule assignments and result releases rather than checking an in-app status feed. The portal provides 24/7 access to result retrieval and chatbot interaction without requiring physical campus visits.

**Audit Trail.** All system events — data access, role actions, placement decisions, and schedule modifications — are logged with HMAC-SHA256 cryptographic chaining. This ensures any unauthorized modification of log entries is structurally detectable. The audit trail is evaluated by the IT Expert through a separate technical walkthrough, reported qualitatively alongside TAM results.

### Additional Limitations

Methodologically, because the September 2026 evaluation window occurs outside the active admission cycle, purposive sampling utilizes simulated evaluators executing role-based scenarios to simulate live operational conditions, measured via paired pre- and post-test TAM questionnaires. The study's evaluation window therefore cannot produce findings based on live applicant behavior, and results are accordingly bounded by simulated operational conditions.

While NEXIAM is designed to support compliance with Republic Act No. 10173 through RBAC and application-layer data governance, formal National Privacy Commission certification and the Privacy Impact Assessment fall outside this study's scope. Applicant consent for digital processing is assumed to be covered under the existing ICAT application form's data collection procedures. Formal verification of this consent basis is identified as future work.

---

## SUMMARY OF CHANGES

| Area | Phase 1 | Phase 2 |
|---|---|---|
| **IPO Input count** | 8 | 10 |
| **IPO Input — new dev items** | — | Computer vision OMR engine, AI orchestration framework, Admission support models |
| **IPO Input — renamed** | Triage advisory notes | Advisory notes |
| **IPO Input — renamed** | Support queries / Counseling data | Admission inquiries |
| **IPO Output count** | 8 | 9 |
| **IPO Output — renamed** | Pre-enrolment slates | Pre-enrolment lists |
| **IPO Output — renamed** | Aptitude dashboards / Decision-support triage | Admission support dashboards |
| **IPO Output — new** | — | Results and release sheet, Email updates |
| **IPO Output — removed** | Status notifications | Replaced by Email updates |
| **IPO Output — kept (mechanism changed)** | Applicant portal | Kept — accessed via identifier key, not traditional account |
| **Scope — OMR** | "Targets 95% accuracy" | "Gated decisioning; no standalone accuracy metric" |
| **Scope — ML** | Generic mention | Full architectural wrapper paragraph (David's text) |
| **PENDING** | — | P4: RAG data boundaries |

---

## PENDING

**P4 — RAG Chatbot data boundaries:** What specific applicant data fields does the chatbot gather? What is explicitly excluded? Fill in and I'll finalize.
