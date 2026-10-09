# NEXIAM Phase 2 — IPO and Scope Revision (DRAFT v3)

**Status:** Grouped IPO + corrected RAG scope. David to confirm groupings and RAG paragraph.

---

## REVISED IPO DIAGRAM (Figure 1) — GROUPED

### INPUT

**Institutional Data**
- Applicant profiles
- Exam and schedule preferences
- Admission inquiries
- Advisory notes
- Scanned answer sheets
- Program quotas
- Activity logs

**Development Foundations**
- Computer vision OMR engine
- AI orchestration framework
- Admission support models

---

### PROCESS
Development and Implementation of NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin

---

### OUTPUT

**Communication**
- Chatbot responses
- Email updates

**Platform Access**
- Applicant portal
- Audit trails

**Scheduling**
- Optimized exam schedules

**Reports and Results**
- Exam scores and prints
- Results and release sheet
- Pre-enrolment lists

**Analytics**
- Admission support dashboards

---

## REVISED CONCEPTUAL FRAMEWORK PARAGRAPH

Structured as a descriptive-developmental study, NEXIAM's conceptual framework utilizes an Input-Process-Output model. The input captures two groups. The first is institutional data: applicant profiles, examination preferences, admission inquiries, advisory notes, scanned answer sheets, program quotas, and activity logs. The second is development foundations integrated during construction: a computer vision OMR engine for automated scoring, an AI orchestration framework for model routing, and admission support models for historical cohort profiling and preliminary program matching. These are not runtime data. They are the technical building blocks selected and integrated during Rapid Construction.

The process iterates through the AI-Augmented RAD model. Requirements Planning captures the operational baseline through descriptive inquiry. User Design produces the RBAC schema and authorization matrix. Rapid Construction integrates the AI components under human code review. Cutover deploys the system for evaluation. The Technology Acceptance Model is embedded within this flow. A pre-test establishes the manual process baseline. After simulated operational use, a post-test measures the shift in user acceptance.

The output is organized into five functional groups. Communication covers chatbot responses and email updates. Platform Access covers the applicant portal — accessed via identifier key — and HMAC-SHA256 audit trails. Scheduling covers human-gated, AI-optimized exam schedules. Reports and Results covers exam scores and prints, results and release sheets, and pre-enrolment lists. Analytics covers admission support dashboards surfacing aptitude patterns for counselor review. Alongside these platform outputs, the research produces empirical evidence: paired TAM measurements analyzed through a paired t-test or Wilcoxon signed-rank test at α = .05, establishing whether NEXIAM yields a statistically significant improvement over the existing manual workflow. These outputs address Objectives 3 and 4. The Input and Process framing serves Objectives 1 and 2.

---

## REVISED SCOPE AND LIMITATIONS

This research covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, for the admission cycle running June through September 2026. The study covers the full admission testing pipeline from applicant registration through result release and post-examination counseling support, across seven user roles governed by Role-Based Access Control (RBAC). A Proctor actor perspective is additionally reflected in the system's use case model, representing a duty overlay assignable to any existing staff role at administrative discretion rather than a distinct eighth role.

The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment, a decision driven by local campus hardware constraints. The proctoring interface is designed with offline resilience for examination venues where network connectivity may be unavailable or intermittent, using service-worker caching to maintain examination session state during connectivity loss, with synchronization to the cloud database upon connection restoration. All other system functions outside the proctoring interface require cloud connectivity.

### Per-Component Scope Specification

**Optical Mark Recognition (OMR).** The system integrates a computer vision OMR engine to automate bubble-sheet scoring. The module uses gated decisioning. Answer sheets are processed in batches, and any marked bubble falling below a configurable confidence threshold is flagged for human verification rather than auto-scored. The study does not measure OMR classification accuracy as a standalone metric. OMR is validated through the overall TAM acceptance evaluation — specifically through Perceived Usefulness items measuring whether automated scoring improves over manual hand-scoring — not through per-sheet accuracy benchmarking against a ground-truth dataset.

**RAG Chatbot and AI Scheduling.** The Retrieval-Augmented Generation chatbot is not a standalone FAQ module. It is a structural service layer connected to the AI scheduling engine. The chatbot handles applicant-specific inquiries — an applicant can retrieve their own examination results, check their own assigned schedule, and receive real-time responses drawn from the institutional knowledge base. The RAG pipeline is architecturally coupled to the scheduling optimizer: when an applicant requests schedule information, the chatbot retrieves available slots, constraints, and optimized assignments from the scheduling database, surfacing the AI-processed output in natural language. If no institutional document matches above the retrieval confidence threshold, the chatbot declines to answer rather than fabricating. The chatbot and scheduling assistant are evaluated through TAM's Perceived Usefulness and Perceived Ease of Use constructs as part of the overall system acceptance measurement. AI inference relies on free-tier external model access, which bounds the volume of concurrent inference requests. Paid or dedicated inference capacity is outside this study's scope.

**Admission Support Models (Machine Learning Engine).** The machine learning component of this study is bounded strictly to the architectural design and functional integration of an extensible interface wrapper within the admission system. While the module is engineered as a pluggable framework capable of loading external data assets — specifically an unsupervised model for Historical Cohort Profiling and a simple baseline supervised model for Preliminary Program Matching — the intrinsic mathematical accuracy, parameter optimization, algorithmic tuning, and predictive validity of these underlying models remain completely unmeasured and outside the scope of this research. The evaluation of this module is restricted purely to its system utility, multi-role data serving capabilities, and user acceptance testing. The engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard. The algorithm is strictly descriptive. It does not make admission decisions. Every placement requires manual counselor confirmation. Program capacity is managed through a separate quota interface and excluded from the clustering algorithm itself.

**Normal Admission Functions.** The platform handles applicant registration, examination scheduling, result processing, and inter-office coordination through RBAC-governed dashboards. Applicants access the system through a web-based portal authenticated via a unique identifier key embedded in their applicant profile — replacing traditional account creation with a simpler, single-key access mechanism. Applicants receive email notifications for schedule assignments and result releases rather than checking an in-app status feed. The portal provides 24/7 access to result retrieval and chatbot interaction without requiring physical campus visits.

**Audit Trail.** All system events — data access, role actions, placement decisions, and schedule modifications — are logged with HMAC-SHA256 cryptographic chaining. This ensures any unauthorized modification of log entries is structurally detectable. The audit trail is evaluated by the IT Expert through a separate technical walkthrough, reported qualitatively alongside TAM results.

### Additional Limitations

Methodologically, because the September 2026 evaluation window occurs outside the active admission cycle, purposive sampling utilizes simulated evaluators executing role-based scenarios to simulate live operational conditions, measured via paired pre- and post-test TAM questionnaires. The study's evaluation window therefore cannot produce findings based on live applicant behavior, and results are accordingly bounded by simulated operational conditions.

While NEXIAM is designed to support compliance with Republic Act No. 10173 through RBAC and application-layer data governance, formal National Privacy Commission certification and the Privacy Impact Assessment fall outside this study's scope. Applicant consent for digital processing is assumed to be covered under the existing ICAT application form's data collection procedures. Formal verification of this consent basis is identified as future work.

---

## SUMMARY OF CHANGES

| Area | Phase 1 | Phase 2 (v3) |
|---|---|---|
| **IPO structure** | Flat list | Grouped (2 input groups, 5 output groups) |
| **Input — Dev Foundations** | — | Computer vision OMR engine, AI orchestration framework, Admission support models |
| **Input — renamed** | Triage advisory notes / Support queries | Advisory notes / Admission inquiries |
| **Output — Communication** | — | Chatbot responses, Email updates |
| **Output — Platform Access** | — | Applicant portal, Audit trails |
| **Output — Scheduling** | — | Optimized exam schedules |
| **Output — Reports** | — | Exam scores and prints, Results and release sheet, Pre-enrolment lists |
| **Output — Analytics** | — | Admission support dashboards |
| **Output — removed** | Status notifications, Decision-support triage | — |
| **RAG Scope** | "Answers applicant queries" | Structural service layer connected to AI scheduling; handles own results + own schedule retrieval |
| **ML Scope** | Generic | David's architectural wrapper paragraph (verbatim) |
| **OMR Scope** | "95% accuracy target" | Gated decisioning; no standalone accuracy metric |
