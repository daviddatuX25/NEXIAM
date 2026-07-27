# NEXIAM Phase 2 — Scope and Limitations (MANUSCRIPT DRAFT)
## For insertion into Chapter 1 | SO4 removed | Em dashes: 0

---

## Scope and Limitations of the Study

This research covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, for the admission cycle running June through September 2026. The study covers the full admission testing pipeline from applicant registration through result release and post-examination counseling support, across seven user roles governed by Role-Based Access Control (RBAC). A Proctor actor perspective is additionally reflected in the system's use case model, representing a duty overlay assignable to any existing staff role at administrative discretion rather than a distinct eighth role.

The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment, a decision driven by local campus hardware constraints. The proctoring interface is designed with offline resilience for examination venues where network connectivity may be unavailable or intermittent, using service-worker caching to maintain examination session state during connectivity loss, with synchronization to the cloud database upon connection restoration. All other system functions outside the proctoring interface require cloud connectivity.

### Per-Component Scope Specification

**Optical Mark Recognition (OMR).** The system integrates a computer vision OMR engine to automate bubble-sheet scoring from scanned answer sheets. The module uses gated decisioning. Answer sheets are processed in batches, and any marked bubble falling below a configurable confidence threshold is flagged for human verification rather than auto-scored. The study does not measure OMR classification accuracy as a standalone metric. OMR is validated through the overall TAM acceptance evaluation — specifically through Perceived Usefulness items measuring whether automated scoring improves over the manual hand-scoring workflow — not through per-sheet accuracy benchmarking against a ground-truth dataset.

**RAG Chatbot and AI Scheduling.** The Retrieval-Augmented Generation chatbot is not a standalone FAQ module. It is a structural service layer architecturally coupled to the AI scheduling engine. The chatbot handles applicant-specific inquiries: an applicant can retrieve their own examination results, check their own assigned schedule, and receive real-time responses drawn from the institutional knowledge base. When an applicant requests schedule information, the chatbot retrieves available slots, constraints, and optimized assignments from the scheduling database, surfacing the AI-processed output in natural language. The chatbot draws exclusively from ISPSC's institutional documents — admission policies, examination schedules, and program requirement files. If no document matches above the retrieval confidence threshold, the chatbot declines to answer rather than fabricating a response. The chatbot and scheduling assistant are evaluated through TAM's Perceived Usefulness and Perceived Ease of Use constructs as part of the overall system acceptance measurement. AI inference relies on free-tier external model access, which bounds the volume of concurrent inference requests the system can sustain. Paid or dedicated inference capacity is outside this study's scope.

**Admission Support Models (Machine Learning Engine).** The machine learning component of this study is bounded strictly to the architectural design and functional integration of an extensible interface wrapper within the admission system. While the module is engineered as a pluggable framework capable of loading external data assets — specifically an unsupervised model for Historical Cohort Profiling and a simple baseline supervised model for Preliminary Program Matching — the intrinsic mathematical accuracy, parameter optimization, algorithmic tuning, and predictive validity of these underlying models remain completely unmeasured and outside the scope of this research. The evaluation of this module is restricted purely to its system utility, multi-role data serving capabilities, and user acceptance testing. The engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard. The algorithm is strictly descriptive. It does not make admission decisions. Every placement requires manual counselor confirmation. Program capacity is managed through a separate quota interface and excluded from the clustering algorithm itself.

**Normal Admission Functions.** The platform handles applicant registration, examination scheduling, result processing, and inter-office coordination through RBAC-governed dashboards. Staff access is provided through a dedicated staff interface serving the Guidance Office, Registrar Office, and Program Heads, with role-isolated views scoped to each office's operational functions. Applicants access the system through a web-based portal authenticated via a unique identifier key embedded in their applicant profile, replacing traditional account creation with a simpler, single-key access mechanism. Applicants receive email notifications for schedule assignments and result releases rather than checking an in-app status feed. The portal provides 24/7 access to result retrieval and chatbot interaction without requiring physical campus visits.

**Audit Trail.** All system events — data access, role actions, placement decisions, and schedule modifications — are logged with HMAC-SHA256 cryptographic chaining. This ensures any unauthorized modification of log entries is structurally detectable. The audit trail is evaluated by the IT Expert through a separate technical walkthrough and reported qualitatively alongside TAM results.

### Additional Limitations

Methodologically, because the September 2026 evaluation window occurs outside the active admission cycle, purposive sampling utilizes simulated evaluators executing role-based scenarios to simulate live operational conditions. The evaluation instrument is the Technology Acceptance Model, administered as a post-test measuring four constructs: Perceived Usefulness, Perceived Ease of Use, Attitude Toward Use, and Behavioral Intention to Use. Acceptance is reported through descriptive means without inferential comparison to a manual baseline. The study's evaluation window therefore cannot produce findings based on live applicant behavior, and results are accordingly bounded by simulated operational conditions.

While NEXIAM is designed to support compliance with Republic Act No. 10173 through RBAC and application-layer data governance, formal National Privacy Commission certification and the Privacy Impact Assessment fall outside this study's scope. Applicant consent for digital processing is assumed to be covered under the existing ICAT application form's data collection procedures. Formal verification of this consent basis is identified as future work.

---

## CASCADE CHECKLIST (SO4 removal — update these across all documents)

| Document | What to change |
|---|---|
| **Manuscript — Objectives** | Remove SO4. Renumber if needed (or leave gap and note). |
| **Manuscript — Null Hypotheses** | Remove H₀₁ and H₀₂ entirely. |
| **Manuscript — Conceptual Framework** | Remove "paired TAM measurements" and "statistically significant improvement." Replace with "descriptive TAM acceptance measures." |
| **Manuscript — Data Analysis** | Remove SO4 paragraph (t-test, Wilcoxon, Shapiro-Wilk, effect size). |
| **Manuscript — Research Design** | Shift from "descriptive-developmental with comparative evaluation" to pure "descriptive-developmental." |
| **Manuscript — Population** | Confirm n still works without SO4 (likely unchanged). |
| **Presentation slides** | Remove Slide 13 SO4 section. Update Slide 5 (CF) output language. |
| **Speaker script** | Remove SO4 lines from David's slides. |
| **Q&A Defense Bank** | Remove/retire Traps about t-test, Wilcoxon, normality, effect size. Add trap: "Why descriptive-only evaluation?" |
| **Glossary** | Remove "Paired T-Test," "Shapiro-Wilk Test," "Wilcoxon Signed-Rank Test" entries. Update TAM entry to remove pre-test language. |
