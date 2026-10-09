# System Context Assembly Workplan & Quality Protocol

## 1. Objectives

1. Assemble a portable, exhaustive technical reference of NEXIAM inside `manuscript/system-context/`.
2. Reconcile terminology across 2 recorded omp sessions, `GLOSSARY.md`, active OpenSpecs, and draft manuscript chapters.
3. Establish unambiguous separation between intended system design and verified runtime implementation.
4. Provide researchers with self-contained documentation so they can complete academic writing outside this repository without drifting into inaccurate claims.

---

## 2. Execution Phases

### Phase 1: Source Discovery & Registration
- Identify, cite, and hash all governing sources: omp transcripts, foundational contracts, active OpenSpecs, code directories, and legacy references.
- Create immutable source records with dates, custodians, and authority levels.

### Phase 2: Terminology Reconciliation
- Map conflicting vocabularies across 5 major life-cycle stages:
  - Identity & Status (Applicant vs Examinee vs Enrolled Student vs Evaluation Participant).
  - Assessment Architecture (Test Instrument vs Subtest vs Aptitude Construct vs Aptitude Area vs Mapping Block).
  - Scoring & Metrics (Item Score vs Raw Metric vs Norm Conversion vs Stanine vs Percentile Rank).
  - Admission Milestones (Admission Clearance vs Pre-Enrollment vs Enrollment-Sheet Handoff vs Institutional Enrollment).
  - Organizational Roles (Test Administrator capability vs Proctor session duty vs permanent office ranks).
- Establish canonical terms bound to root `GLOSSARY.md`.

### Phase 3: Domain Architecture & Logic Extraction
- Deep-dive into each of the 6 core functional domains:
  1. Admissions & Application Intake
  2. Examination Operations & Scheduling
  3. Assessment Scoring & OMR Processing
  4. Consultation, Counselling & Program Compatibility
  5. Admission Clearance & Enrollment Handoff
  6. Shared Services, RBAC & Assistant Gateway
- Document data entities, commands/queries, state machines, business invariants, and verification status for each domain.

### Phase 4: Visual Models & Diagram Indexing
- Catalog all current DFDs, ERDs, Use Case diagrams, and activity flows.
- Annotate each visual model with creation date, source tool, verification status, and known limitations.

### Phase 5: Research Alignment & Claim Crosswalk
- Map specific research objectives (SO1, SO2, SO3) to system capabilities.
- Audit draft manuscript claims in Chapters 1, 2, and 3, identifying verified statements, unverified assertions, and outdated specifications.
- Compile the export manifest verifying complete package portability.

---

## 3. Operational Guardrails

- **Zero Mutation of Manuscript Sections:** Files under `manuscript/sections/` and `manuscript/*.odt` / `*.docx` must remain strictly untouched during this process.
- **Evidence Honesty:** If an engine feature is backed by heuristic mockups rather than live deep-learning inference, it must be documented as such. No inflated technical claims.
- **Export Portability:** Markdown links within `manuscript/system-context/` must use relative paths within `manuscript/` or self-contained text excerpts so they remain functional when the folder is detached from the monorepo.
