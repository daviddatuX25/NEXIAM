# NEXIAM Phase 2 — IPO and Scope Revision (DRAFT v1)

**Status:** Working draft. 4 items pending David's confirmation.
**Panel feedback:** Phase 1 defense probed that Scope and IPO needed more specificity given the many features. Input must reflect development materials, not just evaluation data.

---

## PENDING ITEMS (David to confirm)

### P1 — Output term: "Decision-support triage" vs "Aptitude dashboards"
The slide deck uses "Decision-support triage." The manuscript uses "Aptitude dashboards."
Which term stays in the final IPO? Or do they merge into one?

### P2 — "Results and release sheet" vs "Exam scores & prints"
Does the new "Results and release sheet" absorb "Exam scores & prints," or are they separate outputs?

### P3 — Identifier key system
The applicant portal is removed. Applicants use an "identifier key" to access features.
- What should this be called in the manuscript? (Applicant Identifier Key? Unique Access Key?)
- Does it appear as an IPO output, or is it described only in Scope?

### P4 — RAG chatbot: what data is in scope vs out of scope?
The RAG tackles applicant queries but NOT all data is implied. What specific data fields does the chatbot gather?
- IN SCOPE (example): admission requirements, exam schedules, result inquiries
- OUT OF SCOPE (example): enrollment status, tuition, academic records
Fill in the actual boundaries.

---

## REVISED IPO DIAGRAM (Figure 1)

### INPUT
| # | Variable | Notes |
|---|---|---|
| 1 | Applicant profiles | Demographic and contact data |
| 2 | Exam and schedule preferences | Date/time preferences from applicants |
| 3 | Admission inquiries | Questions submitted through the system |
| 4 | Triage advisory notes | Counselor observations and recommendations |
| 5 | Scanned answer sheets | ICAT bubble sheets for OMR processing |
| 6 | Program quotas | Per-program capacity limits from Program Heads |
| 7 | Activity logs | System and user action records |
| 8 | OMRChecker library | Open-source OMR engine (development input) |
| 9 | Laravel AI SDK | AI orchestration layer (development input) |
| 10 | Supplementary ML models | K-Means clustering engine (development input) |

**Rationale for items 8–10:** The panel probed that Input should reflect what goes into *development* — the libraries, SDKs, and models used to build the system, not just the data the system processes. These three items are the technical building blocks the team integrates during the Process stage.

### PROCESS
Development and Implementation of NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin

*(AI-Augmented RAD: Requirements Planning → User Design → Rapid Construction → Cutover, with embedded pre/post TAM evaluation)*

### OUTPUT
| # | Variable | Notes |
|---|---|---|
| 1 | Chatbot responses | RAG-generated answers to applicant queries |
| 2 | Pre-enrolment lists | *(Renamed from "Pre-enrolment slates")* |
| 3 | Results and release sheet | *(New — see P2)* |
| 4 | **PENDING P1** | Decision-support triage / Aptitude dashboards |
| 5 | Optimized exam schedules | AI-assisted, human-gated scheduling |
| 6 | Audit trails | HMAC-SHA256 tamper-evident logs |
| 7 | Email updates | *(Replaces "Status notifications" — no in-app status; email only)* |

**Removed from Output:**
- ~~Status notifications~~ → Replaced by email updates
- ~~Applicant portal~~ → Replaced by identifier key system (see P3)
- ~~Exam scores & prints~~ → See P2

---

## REVISED CONCEPTUAL FRAMEWORK PARAGRAPH

> Structured as a descriptive-developmental study, NEXIAM's conceptual framework utilizes an Input-Process-Output model. The input captures two categories: institutional data that flows into the system — applicant profiles, examination preferences, scanned answer sheets, program quotas, and activity logs — and the development foundations the team integrates during construction — the OMRChecker library for automated scoring, the Laravel AI SDK for AI orchestration, and supplementary ML models for aptitude clustering. These development inputs are not data the system ingests at runtime. They are the technical building blocks selected and integrated during the Rapid Construction phase.
>
> The process iterates through the AI-Augmented RAD model: Requirements Planning captures the operational baseline through descriptive inquiry, User Design produces the RBAC schema and authorization matrix, Rapid Construction integrates the AI components under human code review, and Cutover deploys the system for evaluation. The Technology Acceptance Model is embedded within this flow. A pre-test establishes the manual process baseline. After simulated operational use, a post-test measures the shift in user acceptance. The evaluation is not a post-hoc activity — it is the validation mechanism built into the development cycle.
>
> The output is twofold. First is the functional platform itself — the admission testing system producing chatbot responses, pre-enrolment lists, results and release sheets, [P1 term], optimized exam schedules, email updates, and cryptographically verifiable audit trails. Second is the empirical evidence. Paired TAM measurements, analyzed through a paired t-test or Wilcoxon signed-rank test at α = .05 depending on Shapiro-Wilk normality results, establish whether NEXIAM yields a statistically significant improvement over the existing manual workflow. Together these outputs address Objectives 3 and 4, while the Input and Process framing serves Objectives 1 and 2.

---

## REVISED SCOPE AND LIMITATIONS

This research covers the design, development, and evaluation of NEXIAM for managing the college admission testing workflow at the Guidance and Registrar Offices of ISPSC Tagudin Campus, for the admission cycle running June through September 2026. The study covers the full admission testing pipeline from applicant registration through result release and post-examination counseling support, across seven user roles governed by Role-Based Access Control (RBAC). A Proctor actor perspective is additionally reflected in the system's use case model, representing a duty overlay assignable to any existing staff role at administrative discretion rather than a distinct eighth role.

The study is strictly bounded to the admission testing function. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are outside scope. System deployment is restricted to an external cloud-hosted environment, a decision driven by local campus hardware constraints. The proctoring interface is designed with offline resilience for examination venues where network connectivity may be unavailable or intermittent, using service-worker caching to maintain examination session state during connectivity loss, with synchronization to the cloud database upon connection restoration. All other system functions outside the proctoring interface require cloud connectivity.

### Per-Component Scope Specification

**Optical Mark Recognition (OMR).** The system integrates computer-vision OMR scoring via the open-source OMRChecker library. The module uses gated decisioning: answer sheets are processed in batches, and any marked bubble falling below a configurable confidence threshold is flagged for human verification rather than auto-scored. The study does not measure OMR classification accuracy as a standalone metric. OMR is validated through the overall TAM acceptance evaluation — specifically, the Perceived Usefulness items measuring whether the automated scoring improves over manual hand-scoring — not through per-sheet accuracy benchmarking against a ground-truth dataset.

**RAG Chatbot.** The Retrieval-Augmented Generation chatbot answers applicant queries by retrieving from a curated institutional knowledge base, built from ISPSC's admission policy documents, examination schedules, and program requirement files. The chatbot draws exclusively from this knowledge base. If no document matches above the retrieval confidence threshold, it declines to answer rather than fabricating a response. [PENDING P4: Specify which applicant data fields the chatbot gathers, and which are explicitly excluded.] The chatbot's responses are evaluated through TAM's Perceived Usefulness and Perceived Ease of Use constructs as part of the overall system acceptance measurement.

**Supplementary ML Models.** K-Means clustering surfaces aptitude patterns from multi-dimensional examination scores (Math, Logic, Reading Comprehension, and subject-specific sections), grouped into program-aligned clusters for counselor review. The algorithm is strictly descriptive — it does not make admission decisions. Every placement requires manual counselor confirmation. The algorithm proposes; the counselor disposes. Program capacity is managed through a separate quota interface and is excluded from the clustering algorithm itself. The K-Means engine is structured as a swappable Model Adapter, allowing replacement without architectural changes to the rest of the system.

**Normal Admission Functions.** The platform handles applicant registration, examination scheduling, result processing, and inter-office coordination through RBAC-governed dashboards. [PENDING P3: Describe the identifier key system — how applicants access features via a unique key rather than a full portal. Applicants receive email notifications for schedule assignments and result releases rather than checking an in-app status feed. The identifier key serves as the applicant's access credential for result retrieval and chatbot interaction.]

**Audit Trail.** All system events — data access, role actions, placement decisions, and schedule modifications — are logged with HMAC-SHA256 cryptographic chaining. This ensures any unauthorized modification of log entries is structurally detectable. The audit trail is evaluated by the IT Expert through a separate technical walkthrough, reported qualitatively alongside TAM results.

### Additional Limitations

AI inference for the hybrid RAG chatbot and the human-gated AI scheduling assistant relies on free-tier external model access via OpenRouter, which bounds the volume of concurrent inference requests the system can sustain. Paid or dedicated inference capacity is outside this study's scope.

Methodologically, because the September 2026 evaluation window occurs outside the active admission cycle, purposive sampling utilizes simulated evaluators executing role-based scenarios to simulate live operational conditions, measured via paired pre- and post-test TAM questionnaires. The study's evaluation window therefore cannot produce findings based on live applicant behavior, and results are accordingly bounded by simulated operational conditions.

While NEXIAM is designed to support compliance with Republic Act No. 10173 through RBAC and application-layer data governance, formal National Privacy Commission (NPC) certification and the Privacy Impact Assessment fall outside this study's scope. Applicant consent for digital processing is assumed to be covered under the existing ICAT application form's data collection procedures; formal verification of this consent basis is identified as future work.

---

## SUMMARY OF CHANGES FROM PHASE 1

| Area | Phase 1 | Phase 2 |
|---|---|---|
| IPO Input count | 8 items (data only) | 10 items (7 data + 3 development artifacts) |
| IPO Input — new | — | OMRChecker, Laravel AI SDK, Supplementary ML models |
| IPO Output — renamed | Pre-enrolment slates | Pre-enrolment lists |
| IPO Output — new | — | Results and release sheet, Email updates |
| IPO Output — removed | Status notifications, Applicant portal | Replaced by email updates and identifier key |
| Scope — OMR | "Targets 95% accuracy" | "Uses gated decisioning; no standalone accuracy metric" |
| Scope — RAG | "Answers applicant queries" | "Restricted to institutional KB; specifies what data is gathered [PENDING P4]" |
| Scope — Features | Listed generically | Per-component breakdown with specific boundaries |
| Applicant access | Portal | Identifier key system [PENDING P3] |
| Term pending | — | P1: Decision-support triage vs Aptitude dashboards |

---

## NEXT STEPS

1. David answers P1–P4
2. I produce the final clean version
3. David reviews and approves
4. I produce the FIND/REPLACE cheat sheet for manual ODT insertion
5. I update the presentation slide text (.txt proxy) to match
