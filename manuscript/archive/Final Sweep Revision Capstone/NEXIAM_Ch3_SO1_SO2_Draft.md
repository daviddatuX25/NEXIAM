# NEXIAM -- Chapter 3: Results and Discussion
### SO1 and SO2 Draft | Research-Grade | No em dashes
### Built from migrated Background blocks + Scope SO2 blocks
### Inferred structure from descriptive-developmental design (Frey, 2022)
###   and the RAD phases described in Chapter 2

---

> **HOW TO USE THIS FILE**
> This is the foundation of Chapter 3, not the complete chapter. It
> provides the SO1 and SO2 sections in full draft form. SO3 (TAM
> evaluation results) and SO4 (paired comparison statistics) will be
> added after data collection is complete in September 2026. The
> structure below follows the dependency chain established in the
> Objectives: SO1 findings feed SO2 requirements, which feed the system
> built and evaluated in SO3 and SO4.
>
> Confirm the following before finalizing this chapter for submission:
> - Replace "preliminary field observation" language where indicated
>   with the formal data-gathering date and instrument reference once
>   your questionnaire administration is logged.
> - The 700 to 1,000 figure: if you have an official ISPSC enrollment
>   or ICAT report document, cite that source explicitly here rather
>   than attributing it only to field observation.
> - S. Jalorina personal communication date: confirm June 2026 is the
>   correct date before final submission.

---

## Chapter 3

## Results and Discussion

This chapter presents the findings of the study organized by specific
objective. The first section addresses Specific Objective 1 (SO1):
an analysis of the existing college admission testing processes,
operational workflows, and process gaps at ISPSC Tagudin Campus through
descriptive inquiry. The second section addresses Specific Objective 2
(SO2): the determination of NEXIAM's system requirements in terms of
functional requirements and system features derived from the SO1
findings. Specific Objectives 3 and 4, covering user acceptance
evaluation and comparative performance testing, are presented in
subsequent sections following system deployment and evaluation in
September 2026.

---

## SO1: Analysis of the Existing Admission Testing Process

### Overview

The descriptive inquiry conducted during the Requirements Planning phase
of the AI-augmented Rapid Application Development (RAD) model gathered
operational evidence through semi-structured administrator questionnaires
and formally documented field observations administered to Guidance
Office and Registrar Office personnel at ISPSC Tagudin Campus. Thematic
analysis was applied to questionnaire responses and observation notes to
surface validated emergent themes representing the primary failure modes
of the current paper-based admission testing workflow. Five themes
emerged consistently across respondents and observation sessions: manual
scoring delays, fragmented result dissemination, inter-office
coordination breakdowns, counseling-session information gaps, and the
absence of persistent admission configuration records. Each is discussed
below.

---

### Theme 1: Manual Scoring Delays

Examination sheets are scored by hand using stencils. Both Guidance
Office respondents independently confirmed that this process consumes
five or more days per cycle, producing cumulative release delays of up
to two weeks for applicants in later batches, and beyond a month for
applicants who sat the earliest batches within a given admission period
(S. Jalorina, personal communication, June 2026). Application forms are
submitted physically and stored in locked cabinets without digitization,
so the data they contain becomes inert the moment a cycle closes. No
persistent digital record of examination results exists that Registrar
staff can query independently; result data moves between offices through
verbal communication and physical document transfer, introducing
transcription risk at each handoff.

This finding aligns with the assessment infrastructure literature
reviewed in Chapter 1. Patil et al. (2021) identified record integrity
and resource coordination as the acute pressure points in paper-based
large-scale exam management, and Thakur et al. (2025) documented that
physical exam workflows of this type generate precisely the grading
delays and subjective-bias exposure confirmed here. The ISPSC Tagudin
Campus case is therefore not anomalous; it reflects a structural
vulnerability that the literature documents at scale, instantiated at a
campus processing 700 to 1,000 freshman applicants annually.

---

### Theme 2: Fragmented Result Dissemination

Results are never pushed to applicants directly. Applicants must instead
monitor a general-purpose Facebook page that Guidance staff manage only
during office hours, then physically attend an announced retrieval
session to collect results in person. Because the page goes unanswered
outside office hours and on-duty staff are frequently occupied with
other walk-in concerns during the day, confusion over delayed or unclear
posts often pushes applicants to visit the campus directly just to
confirm their status, on top of the retrieval visit itself. Field
observations recorded instances of this pattern stretching into a third
campus trip for a process most applicants do not fully understand until
they are already inside it.

The equity dimension of this finding is material. As Ruffalo Noel Levitz
(2023) document, the speed of result release shapes enrollment behavior
directly, with applicants committing to whichever institution delivers
a clear outcome first. Applicants from distant municipalities who cannot
absorb the time and transport cost of repeated campus visits are
structurally disadvantaged by a result-dissemination model dependent on
physical attendance and office-hours availability. The Free Higher
Education Act (RA 10931, 2017) has increased the applicant population
drawing on ISPSC Tagudin Campus, and the campus's current result
dissemination channel does not scale with that volume.

---

### Theme 3: Inter-Office Coordination Breakdowns

Inter-office scheduling runs on unstructured text messages, a channel
ill-suited to tracking fluctuating department quotas and counselor
availability. Field observations confirmed that scheduling confirmation
depends on informal negotiation among counselors, Registrar staff, and
Program Heads, with no shared visibility into quota status. There is no
system-of-record for examination schedule agreements; changes
communicated informally may not reach all relevant parties before an
examination date, and no audit trail exists to reconstruct scheduling
decisions after the fact. Disputes over slot assignments and venue
conflicts were reported as a recurring operational burden during the
busiest weeks of the admission cycle.

---

### Theme 4: Counseling-Session Information Gaps

During counseling sessions, Guidance staff have no independent
visibility into program enrollment figures. Counselors advise applicants
on quota numbers that Program Heads communicate informally and that may
already be stale by the time a session takes place, since no mechanism
exists to push real-time quota updates to the Guidance Office. This gap
was confirmed independently by multiple respondents across the Guidance
and Program Head respondent groups. The practical consequence is that
applicants receive placement guidance calibrated to enrollment data that
may not reflect current availability, a condition that carries both
accuracy and equity implications for course-routing decisions.

---

### Theme 5: Absence of Persistent Admission Configuration Records

Core admission configuration, specifically the academic year parameters,
examination room assignments, course quotas, and aptitude area
definitions that each admission cycle depends on, exists nowhere as a
managed record. It is set up from scratch each cycle through staff
memory and informal coordination rather than through any digital
management interface, leaving no persistent record to carry forward or
audit. Respondents reported that configuration consistency between cycles
depends entirely on the continuity of individual staff, creating
institutional knowledge risk when personnel change.

---

### Summary of SO1 Findings

The five themes above establish the empirical basis for the system
requirements developed under SO2. Table 3.1 maps each identified failure
mode to its primary operational consequence and the corresponding NEXIAM
functional requirement it motivates.

> **[TABLE 3.1 -- Failure Mode to Requirement Mapping]**
> Insert your functional requirements table here, cross-referencing
> each theme above to the requirement rows from your User Design phase.
> Column structure: Theme | Operational Consequence | Derived Requirement
> This table is the formal output of SO1 and the formal input to SO2.

The absence of a unified system is the overarching finding. ISPSC
Tagudin Campus does not lack algorithmic precedent or local data:
Yukee et al. (2025) and Ballesteros et al. (2025) have both demonstrated
the viability of applicant-profiling approaches using the campus's own
examination data, and Angala et al. (2023), Cenezan and Gacusan (2025),
and Cenezan and Valdez (2023) have each contributed digitization
solutions to adjacent administrative functions at the same institution.
What the campus lacks is a single role-governed architecture in which
scoring, aptitude classification, guidance delivery, and inter-office
coordination operate as integrated parts of one traceable pipeline. None
of the existing local systems touches the admission testing phase, and
none connects the Guidance and Registrar Offices within a shared
operational context. That structural absence, confirmed through direct
inquiry with the offices responsible for running the admission cycle,
is what NEXIAM is designed to address.

---

## SO2: System Requirements of NEXIAM

### Overview

The functional requirements and system features of NEXIAM were derived
from the five failure themes identified under SO1 and validated
iteratively with Registrar and Guidance Office staff during the User
Design phase of the RAD workflow. Each requirement traces directly to
a confirmed operational failure; no feature was added on the basis of
general technical interest alone. The requirements are organized below
as functional requirements, which define what the system must do, and
system features, which define the specific capabilities implemented to
fulfill those requirements. Together, these tables constitute the formal
output of SO2.

---

### Technical Architecture

NEXIAM employs an Input-Process-Output (IPO) model that transforms
campus data into decision-ready outputs through six computational layers
built on a common foundational layer. The foundational layer digitizes
what previously existed only in staff memory and informal coordination:
admission configuration records, applicant status tracking, and
Proctor-Logged Marking are maintained as structured, persistent
database records accessible across roles and offices. The full system
specification is detailed in the following subsections.

**Role-Based Access Control (RBAC) -- Layer 01.** Every data-access and
data-modification operation in NEXIAM is governed by an RBAC
authorization matrix that maps seven defined user roles to their
permitted actions. The seven roles are: Super Admin, Registrar Admin,
Registrar Staff, Guidance Counselor, Guidance Admin, Program Head, and
Applicant. Proctoring duties are represented as a duty overlay
assignable to any existing staff role at administrative discretion
rather than as a distinct eighth role, as documented in the system's
use case model in Appendix B. RBAC was identified as the foundational
security architecture requirement by the SO1 finding that no role
differentiation existed in the current process: Guidance staff, Registrar
staff, and applicants all had access to the same physical documents
with no access mediation.

**Computer-Vision OMR Scoring -- Layer 02.** The system integrates
computer-vision OMR scoring via the open-source OMRChecker library,
targeting a baseline classification accuracy of 95 percent or higher,
contingent on image clarity and the existing ICAT answer sheet format,
with a robust human verification guardrail applied before scores are
committed to the database. This directly addresses the manual scoring
delay confirmed under Theme 1, replacing the five-or-more-day
hand-scoring process with automated bubble-sheet reading executable on
commodity hardware without dedicated proprietary equipment.

**K-Means Aptitude Insights Dashboard -- Layer 03.** The system features
a live K-Means Aptitude Insights Dashboard for course triage, extending
Yukee et al.'s (2025) validated clustering configuration into a
continuously running decision-support reference that informs the
counselor's placement judgment rather than replacing it. Ballesteros et
al. (2025) provide corroborating evidence for aptitude-based applicant
clustering in Philippine higher education institution contexts.
Institutional program capacity is managed through a separate quota
interface and is excluded from the clustering algorithm itself.
The dashboard was motivated by Theme 4: counselors currently advise
applicants without any data-driven reference to applicant aptitude
patterns at the moment of consultation.

**Hybrid RAG Chatbot -- Layer 04.** A hybrid Retrieval-Augmented
Generation (RAG) chatbot handles applicant-facing inquiries by
retrieving answers exclusively from a curated local knowledge base of
institutional policy documents before generating a response, as
described by Lewis et al. (2020). This approach constrains chatbot
output to what the institution has actually published, rather than
general-purpose model inference, preserving a logical privacy boundary
without requiring on-campus high-performance computing. The chatbot
and human-gated AI scheduling assistant are orchestrated through
Laravel's native AI SDK, which constrains model routing to the SDK's
supported providers and request patterns rather than a custom inference
pipeline. The platform implements a hybrid embedding and generation
approach: local document embeddings via the mxbai-embed-large-v1 model
stored in PostgreSQL with pgvector, and external API conversational
generation via DeepSeek V4 Flash through OpenRouter's free tier. The
chatbot directly addresses the inquiry-load dimension of Theme 2 and
Theme 3 by deflecting routine applicant questions away from the
Guidance Office's walk-in and social-media channels.

**Human-Gated AI Scheduling Assistant -- Layer 05.** The human-gated AI
scheduling assistant coordinates examination slots and proctor
assignments by generating schedule proposals that authorized staff
review and approve before any assignment is committed. No scheduling
action is taken without explicit human approval. This addresses Theme 3
directly: the system replaces informal text-message-based scheduling
negotiation with a structured, auditable scheduling workflow in which
quota status and counselor availability are visible to all relevant
roles simultaneously.

**HMAC-SHA256 Audit Logging -- Layer 06.** Every state-modifying action
in the system, including score submissions, role assignments, schedule
approvals, and applicant status changes, is recorded in a
tamper-evident audit log using HMAC-SHA256 cryptographic signing. This
addresses the complete absence of any audit trail in the current process,
confirmed under Themes 3 and 5, and operationalizes the data-controller
accountability obligation under Republic Act No. 10173 (Slade and
Prinsloo, 2013).

**Offline-Resilient PWA Proctoring Module.** The proctoring interface is
implemented as a Progressive Web Application (PWA) with service-worker
caching, enabling the examination session to remain active and functional
during brief or complete loss of network connectivity. Examination
session state is preserved locally and synchronized to the cloud
database upon connection restoration. This design responds directly to
the documented infrastructure constraint at ISPSC Tagudin Campus, where
network access is concentrated in designated office areas and
examination venues may have limited or no reliable connectivity, a
condition confirmed during the Requirements Planning field inquiry. All
system functions outside the proctoring interface require cloud
connectivity.

**Data Privacy Architecture.** Data privacy is governed by Laravel
application-layer sanitization using a strict three-tier classification,
ensuring that Sensitive Personal Information as defined under Republic
Act No. 10173 is never transmitted externally. Operationally, applicant
identification uses a zero-cost QR code system integrated into digital
admission slips, with manual search fallbacks by application number or
name for unreadable codes. This architecture satisfies the data
governance requirement established in Chapter 1 under the Principle of
Accountability (RA 10173, Sec. 21), as described by Slade and Prinsloo
(2013).

---

### Functional Requirements Table

> **[TABLE 3.2 -- Functional Requirements]**
> Insert your formal functional requirements table here. Each row should
> map to a confirmed SO1 theme. Minimum column structure:
> Requirement ID | Requirement Description | Source Theme | Priority
> This table is the formal SO2 deliverable for functional requirements.

---

### System Features Table

> **[TABLE 3.3 -- System Features]**
> Insert your formal system features table here. Each row should map to
> a Layer above or to the foundational layer. Minimum column structure:
> Feature ID | Feature Name | Layer | Traced Requirement ID | Status
> This table is the formal SO2 deliverable for system features.

---

### Discussion

The requirements and features identified under SO2 are structurally
necessary rather than additive. The absence of any role-differentiated
access control in the current process means that RBAC is not an
enhancement; it is the precondition for every other layer operating
safely. Computer-vision OMR scoring cannot be deployed in an environment
where its outputs are not protected from unauthorized modification, which
is why Layer 01 governs Layer 02's outputs through the same access
matrix. The K-Means Aptitude Insights Dashboard is only a usable
counseling reference if counselors can trust that the data feeding it
has not been altered after submission, which is why Layer 06
cryptographically binds every scoring action to an immutable log before
Layer 03 reads from it. The structural dependency among the layers is
not incidental design; it is the direct consequence of building against
the specific, confirmed failure modes the SO1 inquiry documented, rather
than against a generic admissions-system template.

This reflects the recommendation Osmani et al. (2026) advance for
AI-augmented engineering workflows: that the value of AI-generated
implementation work depends entirely on the quality of the architecture
and specification it is directed against. The five SO1 themes provided
that specification, traced through the functional requirements table to
every Layer 02 through Layer 06 feature in the system.

---

> **PLACEMENT NOTE FOR FUTURE SECTIONS**
>
> SO3 (TAM evaluation results) section follows here after September 2026
> data collection. Structure:
>   - Descriptive statistics: pooled TAM construct means and per-role
>     breakdowns for PU, PEOU, ATU, and BIU
>   - Comparison against TAM acceptance benchmarks
>   - Discussion of pre-to-post perception shifts per role
>
> SO4 (comparative performance test) section follows after SO3. Structure:
>   - Shapiro-Wilk normality test results and test-path selection rationale
>   - Paired t-test or Wilcoxon signed-rank results (alpha = .05)
>   - Effect size: Cohen's d (parametric) or matched-pairs rank-biserial
>     correlation (non-parametric)
>   - Discussion of whether H01 and H02 are retained or rejected
