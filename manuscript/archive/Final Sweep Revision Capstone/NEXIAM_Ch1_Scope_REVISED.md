# NEXIAM — Chapter 1: Scope and Limitations of the Study
### Revised Draft | Research-Grade | No em dashes | Terminology locked

---

> **HOW TO USE THIS FILE**
> Paste this text directly into your Word document, replacing everything
> from the current "Scope and Limitation of the Study" heading through
> the end of that section (end of p. 15 in the current manuscript).
> The heading itself should also be corrected from "Scope and Limitation"
> to "Scope and Limitations" (plural, per the institutional guide).
> The [REMOVED TO CHAPTER 3] block at the bottom is your migration queue
> for SO2 in Chapter 3.

---

## Scope and Limitations of the Study

This research covers the design, development, and evaluation of NEXIAM
for managing the college admission testing workflow at the Guidance and
Registrar Offices of ISPSC Tagudin Campus, for the admission cycle
running June through September 2026. The study covers the full admission
testing pipeline from applicant registration through result release and
post-examination counseling support, across seven user roles governed by
Role-Based Access Control (RBAC). A Proctor actor perspective is
additionally reflected in the system's use case model, representing a
duty overlay assignable to any existing staff role at administrative
discretion rather than a distinct eighth role.

The study is strictly bounded to the admission testing function.
Enrollment processing, tuition management, long-term academic records,
and cross-campus resource allocation are outside scope. System
deployment is restricted to an external cloud-hosted environment, a
decision driven by local campus hardware constraints. The proctoring
interface is designed with offline resilience for examination venues
where network connectivity may be unavailable or intermittent, a
condition that reflects the documented infrastructure reality of ISPSC
Tagudin Campus, where network access is concentrated in designated
office areas and examination venues may have limited or no reliable
connectivity. The proctoring interface uses service-worker caching to
maintain examination session state during connectivity loss, with
synchronization to the cloud database upon connection restoration. All
other system functions outside the proctoring interface require cloud
connectivity.

AI inference for the hybrid RAG chatbot and the human-gated AI
scheduling assistant relies on free-tier external model access, which
bounds the volume of concurrent inference requests the system can
sustain. Paid or dedicated inference capacity is outside this study's
scope.

Methodologically, because the September 2026 evaluation window occurs
outside the active admission cycle, purposive sampling utilizes proxy
evaluators executing role-based scenarios to simulate live operational
conditions, measured via paired pre- and post-test Technology Acceptance
Model (TAM) questionnaires. The study's evaluation window therefore
cannot produce findings based on live applicant behavior, and results
are accordingly bounded by simulated operational conditions.

While NEXIAM is designed to support compliance with Republic Act No.
10173 through Role-Based Access Control and application-layer data
governance, formal National Privacy Commission (NPC) certification and
the Privacy Impact Assessment required for K-Means applicant profiling
fall outside this study's scope. Applicant consent for digital
processing is assumed to be covered under the existing ICAT application
form's data collection procedures, which applicants complete as part of
the standard admission process; formal verification of this consent
basis is identified as future work alongside the NPC certification and
Privacy Impact Assessment noted above.

---

## [REMOVED FROM SCOPE -- MIGRATION QUEUE FOR CHAPTER 3, SO2]

> These blocks were removed from the Scope section because they specify
> implementation details: library names, AI provider stack, data
> architecture mechanisms, and feature specifications. These are outputs
> of Specific Objective 2 (system requirements and features determined
> by the study) and belong in Chapter 3 under the SO2 results subsection.
> Do not delete this block until those passages are confirmed placed.

---

**Block SO2-A: OMRChecker library and accuracy specification**

The system integrates computer-vision OMR scoring via the open-source
OMRChecker library, targeting a baseline classification accuracy of 95
percent or higher, contingent on image clarity and the existing ICAT
answer sheet format, with a robust human verification guardrail.

---

**Block SO2-B: AI orchestration stack -- Laravel AI SDK, embedding model,
conversational generation model**

The hybrid RAG chatbot and human-gated AI scheduling assistant are
orchestrated through Laravel's native AI SDK, which constrains model
routing to the SDK's supported providers and request patterns rather
than a custom inference pipeline. The platform uses a hybrid approach
for the chatbot: local document embeddings via the mxbai-embed-large-v1
model stored in PostgreSQL with pgvector, and external API conversational
generation via DeepSeek V4 Flash through OpenRouter's free tier.

---

**Block SO2-C: Three-tier data classification and QR identification
mechanism**

Data privacy is governed by Laravel application-layer sanitization using
a strict three-tier classification, ensuring Sensitive Personal
Information as defined under Republic Act No. 10173 is never transmitted
externally. Operationally, applicant identification uses a zero-cost QR
code system integrated into digital admission slips, with manual search
fallbacks by application number or name for unreadable codes.

---

**Block SO2-D: K-Means Aptitude Insights Dashboard feature specification**

The system features a live K-Means Aptitude Insights Dashboard for
course triage, extending Yukee et al.'s (2025) validated clustering
configuration into a continuously running decision-support tool, with
Ballesteros et al. (2025) providing corroborating evidence for
aptitude-based applicant clustering in Philippine higher education
institution contexts. Institutional program capacity is managed through
a separate quota interface and is excluded from the clustering algorithm
itself.

---

**Block SO2-E: Duplicate Laravel SDK sentences (delete entirely)**

*(Three instances of the Laravel SDK sentence appeared across pp. 13-14
in the submitted manuscript. All three were artifacts of successive
editing passes. They are not restored here; Block SO2-B above is the
single, clean version that belongs in Chapter 3, SO2.)*
