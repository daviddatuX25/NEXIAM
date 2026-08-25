# NEXIAM : Chapter 3: Results and Discussion

### Initial compile | Phase 3 Version A | Descriptive-developmental | 3 objectives

**Date compiled:** 2026-08-26
**Supersedes for Phase 3:** `manuscript/archive/Final Sweep Revision Capstone/NEXIAM_Ch3_SO1_SO2_Draft.md` (Phase 2 shape: thematic analysis, AI-augmented RAD, SO4/paired statistics, 7-point scale; all now superseded by the Phase 3 working sections)
**Aligned to:** `manuscript/NEXIAM_Manuscript_Working_Sections.md` (Phase 3, Version A)

**What this initial compile contains:**
- SO1 results in full draft: process mapping and workflow analysis findings
- SO2 results in full draft: functional requirements, system features, and the RAD construction documentation
- SO3 (TAM acceptance) reporting structure only; results pending the September 2026 Cutover data collection

**Before submission, confirm:**
- Formal data-gathering dates and instrument administration log for the SO1 inquiry (currently referenced as field inquiry, July 2026)(currently referenced as field inquiry, July 2026)
- The 700-applicant population estimate: cite the official ISPSC enrollment or ICAT report if one exists
- S. Jalorina personal communication date (June 2026 as recorded in the archived draft)
- Client validation of the open decisions listed in `docs/client-features-overview.md` Section 5 before the SO2 discussion cites them as settled
- Table 3.1 to 3.3 row counts against the final User Design artifacts (feature alignment matrix, `docs/requirements-gathering/process-decision-matrix.md`)
- Retake policy (follow-up question to the office): may a student with a saved result retake within the same cycle or in another academic year?

---

## Chapter 3

## Results and Discussion

This chapter presents the findings of the study organized by specific objective. The first section addresses Specific Objective 1 (SO1): an analysis of the existing college admission testing processes, operational workflows, and process gaps at ISPSC Tagudin Campus through descriptive inquiry. The second section addresses Specific Objective 2 (SO2): the determination of the system requirements of NEXIAM in terms of functional requirements and system features derived from the SO1 findings, together with the documentation of the Rapid Application Development (RAD) construction process. The third section presents the acceptance evaluation of NEXIAM under Specific Objective 3 (SO3) through the Technology Acceptance Model; data collection for this objective occurs during the September 2026 Cutover phase, and the section is reported once collection and analysis are complete.

---

## SO1: Analysis of the Existing Admission Testing Process

### 3.1.1 The Descriptive Inquiry

The descriptive inquiry was conducted during the Requirements Planning phase of the RAD model. Evidence was gathered through semi-structured administrator questionnaires and formally documented field observation administered to Guidance Office personnel (n = 2), Registrar Office personnel (n = 3), and Program Heads (n = 11) of ISPSC Tagudin Campus. Responses and observation notes were processed through process mapping and workflow analysis: current admission workflows were traced from intake through result delivery, and each step was examined for its owner, its handoffs, and the evidence it leaves behind. The resulting process maps expose where the current process slows, where it loses information, and where offices must rely on memory or informal channels.

Five failure modes emerged consistently across respondents and observation sessions: manual scoring delays, fragmented result dissemination, inter-office coordination breakdowns, counseling-session information gaps, and the absence of persistent admission configuration records. Each is discussed below.

### 3.1.2 Failure Mode 1: Manual Scoring Delays

Examination sheets are scored by hand using stencils. Both Guidance Office respondents independently confirmed that this process consumes five or more days per cycle, producing cumulative release delays of up to two weeks for applicants in later batches, and beyond a month for applicants who sat the earliest batches within a given admission period (S. Jalorina, personal communication, June 2026). Application forms are submitted physically and stored in locked cabinets without digitization, so the data they contain becomes inert the moment a cycle closes. No persistent digital record of examination results exists that Registrar staff can query independently; result data moves between offices through verbal communication and physical document transfer, introducing transcription risk at each handoff.

This finding aligns with the assessment infrastructure literature reviewed in Chapter 2. Patil et al. (2021) identified record integrity and resource coordination as the acute pressure points in paper-based large-scale exam management, and Thakur et al. (2025) documented that physical exam workflows of this type generate precisely the grading delays and subjective-bias exposure confirmed here. The ISPSC Tagudin Campus case is therefore not anomalous; it reflects a structural vulnerability that the literature documents at scale, instantiated at a campus processing an estimated 700 freshman applicants per admission cycle.

### 3.1.3 Failure Mode 2: Fragmented Result Dissemination

Results are never pushed to applicants directly. Results are released only together with the face-to-face consultation: the applicant receives the result while attending the consultation session, and there is no online result channel of any kind. Applicants must additionally monitor a general-purpose Facebook page that Guidance staff manage only during office hours for announcements, then physically attend the consultation-retrieval session to collect results in person. Because the page goes unanswered outside office hours and on-duty staff are frequently occupied with other walk-in concerns during the day, confusion over delayed or unclear posts often pushes applicants to visit the campus directly just to confirm their status, on top of the retrieval visit itself.Applicants must instead monitor a general-purpose Facebook page that Guidance staff manage only during office hours, then physically attend an announced retrieval session to collect results in person.Applicants must instead monitor a general-purpose Facebook page that Guidance staff manage only during office hours, then physically attend an announced retrieval session to collect results in person. Because the page goes unanswered outside office hours and on-duty staff are frequently occupied with other walk-in concerns during the day, confusion over delayed or unclear posts often pushes applicants to visit the campus directly just to confirm their status, on top of the retrieval visit itself. Field observations recorded instances of this pattern stretching into a third campus trip for a process most applicants do not fully understand until they are already inside it.

The equity dimension of this finding is material. As Ruffalo Noel Levitz (2023) document, the speed of result release shapes enrollment behavior directly, with applicants committing to whichever institution delivers a clear outcome first. Applicants from distant municipalities who cannot absorb the time and transport cost of repeated campus visits are structurally disadvantaged by a result-dissemination model dependent on physical attendance and office-hours availability. The Free Higher Education Act (RA 10931, 2017) has increased the applicant population drawing on ISPSC Tagudin Campus, and the campus's current result dissemination channel does not scale with that volume.

### 3.1.4 Failure Mode 3: Inter-Office Coordination Breakdowns

Inter-office scheduling runs on unstructured text messages, a channel ill-suited to tracking fluctuating department quotas and counselor availability. Exam slots are set by tradition through a single authority, and no adjustments are allowed once set; coordination around those fixed slots runs through messages, calls, and conversations. Consultation scheduling is equally informal: consultations are arranged as applicants arrive or through office coordination rather than a published schedule, which in peak periods produced volumes of 70 to 80 applicants per day with interactions of 2 to 5 minutes each, a sustained staff load that also keeps applicants waiting in line. Field observations confirmed that scheduling confirmation depends on informal negotiation among counselors, Registrar staff, and Program Heads, with no shared visibility into quota status. There is no system-of-record for examination schedule agreements; changes communicated informally may not reach all relevant parties before an examination date, and no audit trail exists to reconstruct scheduling decisions after the fact. Disputes over slot assignments and venue conflicts were reported as a recurring operational burden during the busiest weeks of the admission cycle.

### 3.1.5 Failure Mode 4: Counseling-Session Information Gaps

During counseling sessions, Guidance staff have no independent visibility into program enrollment figures. Advice itself leaves no record: the counselor says the advice during the session and it is not noted on paper or in any other medium, so nothing of what was advised can be retrieved, verified, or carried into a follow-up. Counselors also advise applicants on quota numbers that Program Heads communicate informally and that may already be stale by the time a session takes place, since no mechanism exists to push real-time quota updates to the Guidance Office. This gap was confirmed independently by multiple respondents across the Guidance and Program Head respondent groups. The practical consequence is that applicants receive placement guidance calibrated to enrollment data that may not reflect current availability, a condition that carries both accuracy and equity implications for course-routing decisions.

### 3.1.6 Failure Mode 5: Absence of Persistent Admission Configuration Records

Core admission configuration, specifically the academic year parameters, examination room assignments, course quotas, and aptitude area definitions that each admission cycle depends on, exists nowhere as a managed record. It is set up from scratch each cycle through staff memory and informal coordination rather than through any digital management interface, leaving no persistent record to carry forward or audit. The same absence extends to intake documents: requirements are checked through folders and separate lists rather than a single checklist, so a document state exists only in whichever list last recorded it and cannot be queried across offices. Respondents reported that configuration consistency between cycles depends entirely on the continuity of individual staff, creating institutional knowledge risk when personnel change.

### 3.1.7 Summary of SO1 Findings

The five failure modes above establish the empirical basis for the system requirements developed under SO2. Table 3.1 maps each identified failure mode to its primary operational consequence and the corresponding NEXIAM functional requirement it motivates.

**Table 3.1**

*Failure Mode to Requirement Mapping*

| Failure Mode | Operational Consequence | Derived Requirement |
|---|---|---|
| Manual scoring delays | 5-plus-day scoring, release delays up to two weeks, transcription risk at each handoff | Automated bubble-sheet scoring with manual fallback (FR-05, FR-06) |
| Fragmented result dissemination | Repeated campus trips, office-hours dependence, inequitable access for distant applicants | Applicant portal with controlled online release and notification (FR-09, FR-11) |
| Inter-office coordination breakdowns | Text-message scheduling, no system-of-record, unresolvable disputes | Digital session scheduling with shared roster, quota visibility, and audit (FR-03, FR-08, FR-12) |
| Counseling-session information gaps | Stale quota guidance during advising, equity implications for course routing | Consultation record with quota status and follow-up state (FR-07, FR-08) |
| Absence of persistent configuration records | Rebuilt-from-scratch cycles, institutional knowledge risk | Managed setup catalogs: academic cycle, rooms, quotas, aptitude areas (FR-12) |

The absence of a unified system is the overarching finding. ISPSC Tagudin Campus does not lack algorithmic precedent or local data: Yukee et al. (2025) and Ballesteros et al. (2025) have both demonstrated the viability of applicant-profiling approaches using the campus's own examination data, and Angala et al. (2023), Cenezan and Gacusan (2025), and Cenezan and Valdez (2023) have each contributed digitization solutions to adjacent administrative functions at the same institution. What the campus lacks is a single role-governed architecture in which scoring, aptitude classification, guidance delivery, and inter-office coordination operate as integrated parts of one traceable pipeline. None of the existing local systems touches the admission testing phase, and none connects the Guidance and Registrar Offices within a shared operational context. That structural absence, confirmed through direct inquiry with the offices responsible for running the admission cycle, is what NEXIAM is designed to address.

---

## SO2: System Requirements of NEXIAM

### 3.2.1 Functional Requirements

The functional requirements of NEXIAM were derived from the five failure modes identified under SO1 and validated iteratively with Registrar and Guidance Office staff during the User Design phase of the RAD workflow. Each requirement traces directly to a confirmed operational failure; no feature was added on the basis of general technical interest alone. The requirements also incorporate the digitized-process decisions confirmed with the offices during requirements gathering, summarized in the process and decision matrix (D1 to D10; B1 to B10). Table 3.2 presents the functional requirements that constitute the formal output of SO2.

**Table 3.2**

*Functional Requirements of NEXIAM*

| ID | Functional Requirement | Source Failure Mode / Decision | Priority |
|---|---|---|---|
| FR-01 | The system shall accept online application submissions with field validation and privacy-policy consent, and shall issue a unique identifier key upon submission | FM-5; D1 | High |
| FR-02 | The system shall support structured intake review with recorded correction reasons, versioned corrections, and non-destructive accept, dismiss, and reopen actions | FM-5; D2; B4 | High |
| FR-03 | The system shall manage examination sessions with room-based capacity, published schedules, live rosters, and session-scoped proctor duties, preventing double booking and overbooking | FM-3; D4 | High |
| FR-04 | The system shall allow applicant self-booking for examination and consultation sessions only when office policy enables it, with staff-assigned booking as the default | FM-3; B3 | Medium |
| FR-05 | The system shall provide automated OMR answer-sheet scoring with a manual scoring fallback, routing unreadable, disputed, or low-confidence sheets to human review | FM-1; D3; B5 | High |
| FR-06 | The system shall enforce a grading completeness gate: no batch finalization while any applicant's scores are missing, and every score entry or correction attributable to its author | FM-1; D8; B8 | High |
| FR-07 | The system shall record consultation sessions with mode, remarks, next action, follow-up state, and attributable history, and shall flip the applicant status to Advised only when the counselor confirms the decision | FM-4; D5; B1 | High |
| FR-08 | The system shall record program choice, quota position, transfer, and decline with visible quota checks before a selection is accepted | FM-4; D6; B6 | Medium |
| FR-09 | The system shall support controlled result preparation, printing, release, and unrelease with explicit completeness gates, release records, and audit events | FM-2; D9; B8 | High |
| FR-10 | The system shall provide the Registrar an enrollment eligibility view computed from accepted, exam done, result released, and advised, with enrolling and enrolled stamps and reversal reasons | FM-2; D7 | Medium |
| FR-11 | The system shall present applicant-visible status at every stage and deliver notifications only after the referenced action is durably committed | FM-2; D10 | High |
| FR-12 | The system shall enforce capability- and scope-based access control on every action, with an audit trail recording who did what and when | FM-3, FM-5; B9 | High |
| FR-13 | The system shall attach official messages and follow-ups to the applicant record so inquiries do not disappear into personal channels | FM-3; B10 | Medium |

### 3.2.2 System Features

The system features translate the functional requirements into implemented capabilities. Each feature maps to the traced requirement in Table 3.2 and to the layer of the system architecture described in the conceptual framework. Implementation status reflects the development state as of this writing (August 2026), with the RAD construction cycle still in progress toward the September 2026 Cutover.

**Table 3.3**

*System Features of NEXIAM*

| Feature | Description | Traced Requirement | Status (August 2026) |
|---|---|---|---|
| Online application and intake | Public application form, privacy-policy consent, access-key issuance, staff review queue with accept, dismiss, reopen, and correction requests | FR-01, FR-02 | Implemented and demonstrated |
| Applicant portal | Sign-in with identifier key plus birthdate, own-data status tracker, exam and consultation schedules, self-booking when enabled, released results | FR-01, FR-04, FR-09, FR-11 | Implemented and demonstrated |
| Exam session scheduling | Session creation with room pick and capacity, two booking modes switchable per session, publishing control, live roster, proctor duty assignment | FR-03, FR-04 | Implemented and demonstrated |
| Exam monitoring and proctoring | Attendance and submission recorded against the session roster, QR-based attendance, session deadline extension, close-once grading handoff | FR-03 | Implemented |
| Grading and scoring | Grading sessions, per-applicant scoring with review, finalize with completeness gate, roster eligibility gated on attendance and submission, versioned corrections | FR-06 | Implemented and demonstrated |
| Bubble-sheet (OMR) capture | Batch capture of answer sheets with a real scanning engine, confidential conversion-table engine with frozen score snapshots, human review of flagged sheets, manual scoring fallback | FR-05 | Implemented in the development environment; production activation pending physical acceptance |
| Result release | Online digital release with no office printing, face-to-face printed-sheet mode, release and unrelease records with reasons, notification after committed release | FR-09 | Designed end to end; result-sheet printing pipeline in progress |
| Consultation management | Consultation sessions scheduled exactly like exam sessions, mode and booking toggles, counselor confirmation flips status to Advised, follow-up and ready-pool handling | FR-07 | Designed end to end |
| Enrollment eligibility | Registrar name search with eligibility card computed from four conditions, stamps and reversal reasons, lookup recorded | FR-10 | Designed end to end |
| Staff interface with RBAC | Capability- and scope-based access across office hierarchy, office-isolated views for Guidance, Registrar, and Program Head personnel, setup catalogs for academic cycle, programs, quotas, rooms, privacy policy, and the confidential conversion table | FR-12 | Implemented |
| Notifications | In-app notification center with delivery only after committed actions; email channel where configured | FR-11 | Implemented; full delivery channels pending confirmation |
| Audit trail | Event log of data access, role actions, score submissions, schedule approvals, and status changes | FR-12 | Implemented |
| AI assistant (Nexi) | Staff-facing assistant on authorized pages that proposes schedules and wording; proposes only, never writes or changes records, and cannot bypass ordinary capability checks | FR-03 | Implemented |
| RAG chatbot | Applicant-facing policy Q-and-A retrieving from an approved institutional corpus, declining below retrieval confidence | FR-11 | Conditional per study scope; implementation gated |
| Admission support models | Extensible interface for historical cohort profiling and preliminary program matching, presented to the counselor as descriptive references; every placement requires manual counselor confirmation | FR-08 | Conditional per study scope; implementation gated |
| AI-assisted scheduling | Schedule proposals generated for authorized staff review and confirmation; no scheduling action without human approval | FR-03 | Conditional per study scope; implementation gated |

### 3.2.3 Development Process: RAD in Practice

The RAD construction of NEXIAM followed the four phases of the model described in Chapter 2, and the component integration decisions made in each phase are documented here as required by the study's scope.

**Requirements Planning.** This phase established the empirical baseline presented under SO1. Semi-structured questionnaires, field observation, and the process and decision matrix (D1 to D10; B1 to B10) were used to structure the confirmed operational failures into the functional requirements in Table 3.2. An inventory of the existing admission-related systems at the campus, including the SecureCAT codebase and its feature catalog, was classified as evidence rather than target behavior, so that requirements reflect institutional workflow decisions and not the incidental architecture of prior systems.

**User Design.** This phase translated the field-verified requirements into deterministic architectural blueprints. The relational RBAC database schema was formulated, the authorization matrix mapping roles and offices to permitted actions was established, and interface mockups were produced for each user role. Design reviews with the offices captured mockup preferences and feature acceptance, and the resulting feedback items were logged as revision items traced to specific field findings. The role catalog was reconciled with the institution of the study: seven RBAC-governed roles (Applicant; Proctor as a duty overlay; Guidance Counselor; Guidance Administrator; Registrar Staff; Registrar Administrator; and System IT Administrator), a non-evaluated Super Admin break-glass role for oversight, and role-isolated staff views for Program Heads.

**Rapid Construction.** Construction proceeded iteratively between design refinement and incremental build. Application frameworks were systematically researched and selected: a modular monolith on the Laravel framework with Inertia and Svelte for the front end and PostgreSQL as the database, with database constraints as the integrity authority for concurrent operations. AI-assisted development accelerated routine implementation work, and every AI-generated module underwent human code review before integration. Computer-vision OMR scoring was integrated through the open-source OMRChecker engine, selected for its suitability to commodity hardware and its fit to the institution's answer-sheet workflow; scoring runs through a confidential conversion-table engine that records frozen converted scores and flags low-confidence sheets for human verification. The applicant access design was locked as an identifier key plus birthdate credential issued at submission, replacing the prior token-setup flow. Completed modules were demonstrated to Guidance and Registrar staff during the construction cycle, and misalignments were logged as revision items connecting each design change to a specific field finding. Implementation evidence, including automated test suites and browser-level demonstrations, was maintained alongside each completed module.

**Cutover.** The completed platform is deployed to a cloud-hosted environment to demonstrate operational capacity. Following deployment, simulated evaluators execute role-specific scenarios corresponding to the use cases in Appendix B, and each participant independently completes the TAM questionnaire as a single post-test. The evaluation protocol and its results are presented under SO3.

### 3.2.4 Discussion

The requirements and features identified under SO2 are structurally necessary rather than additive. The absence of any role-differentiated access control in the current process means that RBAC is not an enhancement; it is the precondition for every other layer operating safely. Automated OMR scoring cannot be deployed in an environment where its outputs are not protected from unauthorized modification, which is why the access matrix governs scoring outputs through the same capability checks. The consultation record is only a usable counseling reference if counselors can trust that the data feeding it has not been altered after submission, which is why every scoring and release action is bound to an attributable audit event before downstream views read from it. The structural dependency among the features is not incidental design; it is the direct consequence of building against the specific, confirmed failure modes the SO1 inquiry documented, rather than against a generic admissions-system template.

This reflects the recommendation Osmani et al. (2026) advance for AI-augmented engineering workflows: that the value of AI-generated implementation work depends entirely on the quality of the architecture and specification it is directed against. The five SO1 failure modes provided that specification, traced through the functional requirements table to every feature in the system.

---

## SO3: Acceptance Evaluation of NEXIAM (TAM)

### 3.3.1 Data Collection Status

Data collection for Specific Objective 3 occurs during the September 2026 Cutover phase, outside the active admission period. Because actual applicants are unavailable, simulated evaluators execute role-specific scenarios corresponding to the use cases in Appendix B, then independently complete the TAM questionnaire as a single post-test. The sample consists of 271 evaluators: 255 first-year students drawn through Slovin's formula at a 0.05 margin of error from an estimated population of 700 applicants, selected through stratified random sampling across 11 courses; and 16 administrative staff purposively selected as the complete available complement (11 Program Heads, 3 Registrar Office personnel, and 2 Guidance Office personnel). The instrument measures Perceived Usefulness and Perceived Ease of Use on a five-point Likert scale (1 = Strongly Disagree to 5 = Strongly Agree) with role-adapted phrasing: 8 items for Registrar Personnel, 13 for Guidance Personnel, 9 for First-Year Students, and 9 for Program Heads.

Results will be reported in this section once collection and analysis are complete, using the analysis procedure defined in the methodology: each respondent's items per construct are averaged into a single per-person construct score; per-person scores are averaged across all evaluator post-test responses to produce the pooled mean for Perceived Usefulness and Perceived Ease of Use, with standard deviations describing response consistency; and pooled means are reported with a breakdown of per-role descriptive means for each construct. Means above the midpoint of the five-point scale (greater than 3.0) indicate positive acceptance and are interpreted against the acceptance benchmarks of Davis (1989) and the role-based TAM literature (Lippert and Forman, 2005; Holden and Karsh, 2010).

### 3.3.2 Planned Reporting Structure

- Descriptive statistics: pooled TAM construct means and per-role breakdowns for Perceived Usefulness and Perceived Ease of Use
- Interpretation against the acceptance midpoint (greater than 3.0) and TAM benchmarks
- Discussion of per-role differences in acceptance, bounded by the simulated operational conditions of the evaluation

---

## Evidence and Traceability Notes

- Field inquiry instruments and open validation items: `docs/requirements-gathering/requirements-and-interview-brief.md`, `docs/requirements-gathering/process-decision-matrix.md`, `manuscript/Phase(RAD-based) Work/01-requirements-planning/`
- Feature alignment and User Design artifacts: `manuscript/Phase(RAD-based) Work/02-user-design/`, `docs/user-design/`
- Implemented system evidence: `system/` routes, controllers, and automated test suites; `docs/GREENFIELD-MASTER-REFERENCE.md` checkpoint entries
- Client-facing feature status: `docs/client-features-overview.md` (status markers and open decisions)
- Methodology contract: `manuscript/NEXIAM_Manuscript_Working_Sections.md` (Phase 3, Version A)
- Prior Phase 2 chapter draft (superseded): `manuscript/archive/Final Sweep Revision Capstone/NEXIAM_Ch3_SO1_SO2_Draft.md`
