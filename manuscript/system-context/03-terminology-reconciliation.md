# Comprehensive Domain Terminology Reconciliation

This document establishes the reconciled vocabulary for NEXIAM, harmonizing language across the canonical domain dictionary (`GLOSSARY.md`), foundational architecture contracts (`GF-04`, `GF-21`), active OpenSpecs, conversational decision audits (`SRC-OMP-01`, `SRC-OMP-02`), and the capstone manuscript drafts.

---

## 1. Subject Identity & Research Participant Classification

A major finding of `SRC-OMP-01` was the conflation of real-world students, system applicants, and research evaluators. The following distinctions are strictly enforced:

| Term | Exact System Meaning | Context & Boundaries | Prohibited / Deprecated Usage |
|---|---|---|---|
| **Applicant** | A prospective student who has registered personal data and submitted an admission attempt via `/apply`. | Active from initial intake submission until admission clearance or final dismissal. Represented in data by `ApplicantIdentity` and `AdmissionAttempt`. | Do *not* refer to an applicant as a "student" before institutional enrollment. |
| **Examinee** | An applicant who is confirmed on an examination session roster and is taking or has taken the ICAT. | Applicable during Stage 3 (Scheduling) and Stage 4 (Examination Day). Bounded by `ExamAssignment`. | Do *not* use "examinee" to describe applicants browsing public notices or waiting for initial intake approval. |
| **Enrolled Student** | A matriculated individual who has completed all external campus registration and tuition procedures. | External to NEXIAM. NEXIAM stops at admission clearance and enrollment-sheet handoff; it does not track active campus student rosters. | Do *not* claim NEXIAM manages "enrolled students" or campus academic records (SIS/LMS scope). |
| **Research Participant / Evaluator** | A human being participating in the capstone study by executing standardized simulation tasks and completing the TAM questionnaire. | Methodological entity (n=263 planned across 4 cohorts). In the study, actual first-year college students act as *evaluators* operating simulated applicant accounts. | Do *not* confuse the evaluation participant cohort (first-year volunteers) with real admission candidates. |
| **Simulated Persona** | A pre-seeded, synthetic test account (e.g., `Celia`, `PORT-DEFL`) used during task evaluation or development testing. | Created deterministically via `demo:stage` or seeders; possesses known milestones, scores, and mock records. | Never present synthetic test personas as real institutional applicants in research results. |

---

## 2. Test Architecture, Instruments & Psychometrics

In accordance with root `GLOSSARY.md` and `SRC-SPEC-01` (`versioned-test-instruments-and-scoring`), psychometric definitions are strictly decoupled from physical paper layouts:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        TEST INSTRUMENT (e.g., ICAT)                   │
├────────────────────────────────────────────────────────────────────────┤
│  Subtest A: Word Knowledge             Subtest B: Computation          │
│  (Named test division; 40 items)       (Named test division; 30 items) │
└───────────────────┬───────────────────────────────────┬────────────────┘
                    │                                   │
                    ▼                                   ▼
┌───────────────────────────────────────┐ ┌──────────────────────────────┐
│       APTITUDE CONSTRUCT              │ │     MAPPING BLOCK            │
│  Psychological attribute measured     │ │  Plotted bubbles on page     │
│  (e.g., Verbal Reasoning)             │ │  (e.g., Sheet Page 1, Q1-40) │
└───────────────────────────────────────┘ └──────────────────────────────┘
```

| Term | Canonical Definition (`GLOSSARY.md`) | System Entity / Scope | Conflicting / Deprecated Terms |
|---|---|---|---|
| **Test Instrument** | An identified assessment with an edition or form, administration instructions, scoring rules, and intended interpretation. | `TestInstrument` (e.g., "ICAT Form 2026"). An instrument is *not* an answer-sheet layout or a conversion table. | *Avoid:* Calling an answer sheet a "test instrument". |
| **Test Section / Subtest** | A named part of a test instrument (e.g., Vocabulary, Math Computation) with its own items or measurement procedure. | `TestSubtest`. A subtest may span multiple answer-sheet mapping blocks or physical pages. | *Avoid:* "Domain one", or "Aptitude area" when referring to a physical test item group. |
| **Answer-Sheet Mapping Block** | A grouped layout of physical answer coordinates on a specific paper page. | `NativeMappingBlock` / `OmrTemplateArea`. Several blocks across pages may belong to one subtest. | *Avoid:* Using "subtest" when referring only to a visual cluster of plotted bubbles. |
| **Aptitude Construct** | The latent psychological ability being assessed (e.g., Verbal Aptitude, Numerical Aptitude, Spatial Logic). | `AptitudeConstruct`. A construct is distinct from the subtests or raw measurements used to evaluate it. | *Avoid:* Treating raw subtest scores as identical to latent constructs without psychometric derivation. |
| **Aptitude Area (Legacy)** | Legacy database column grouping subtests into 6 broad institutional categories. | `aptitude_areas` table in legacy runtime. Retained in greenfield UI as transitional display labels. | *Avoid:* Presenting legacy Aptitude Areas as modernized psychometric constructs in Chapter 3. |

---

## 3. Scoring, Raw Metrics & Norm Conversion

| Term | Canonical Definition (`GLOSSARY.md`) | Technical Nuance & Verification State | Prohibited Assumptions |
|---|---|---|---|
| **Item Observation** | The detected optical mark, verified absence of a mark, or unresolved ambiguity recorded for a single item. | Emitted by OMR inference worker as `detected_items`. Does *not* indicate whether the response is correct. | Do *not* claim an optical observation is a score before key comparison. |
| **Item Score** | The numerical points awarded to an item under the applicable scoring rule and Booklet Set answer key. | Result of `EvaluateItemResponses(observations, answer_key)`. | Item scores are binary (0/1) or weighted; they are distinct from norm conversions. |
| **Raw Metric** | An unadjusted numerical summary before norm conversion (e.g., sum of item scores, percentage correct). | Units and validity belong strictly to the test instrument specification. | Do *not* report raw scores as percentile ranks. |
| **Derived Raw Metric** | A composite score mathematically calculated from component raw metrics (e.g., Total Composite = Verbal + Math). | Computed by rule engine; does *not* imply an independent physical test section. | Never invent separate answer-sheet bubbles for a derived composite metric. |
| **Norm Conversion** | The translation of an applicable raw metric into a standardized scale using an approved reference population table. | Bounded by `NormPackage` (e.g., Percentile Ranks, Stanines). | A conversion table is *not* an intrinsic property of the test paper. |
| **Stanine** | A standard nine-point scale (mean 5, SD 2) used for institutional band ranking (1=Very Low, 9=Very High). | Formally modeled as discrete integers 1..9. | *Avoid:* Treating stanines as continuous real numbers or raw percentages. |

---

## 4. Examination Operations & Packaging

| Term | Canonical Definition | Operational Rule (`SRC-OMP-02`, `GF-06`) |
|---|---|---|
| **Simple-Capacity Exam** | An examination scheduled strictly by room capacity (e.g., 30 seats) without prescribing each candidate's exact spatial chair. | Operational default for ISPSC. Simple sessions do not render spatial chair grids, seat coordinates, or seat-swap interfaces. |
| **Assigned-Seating Exam** | An examination where each candidate is formally bound to a specific physical chair code (e.g., Room 101, Seat 12). | Advanced policy toggle (`enable_seating_arrangements = true`). Default is OFF. |
| **Booklet Set** | A specific variant or scrambled order of one Booklet (e.g., Set A, Set B) with its own corresponding answer key. | Every session defaults to **Set A**. Alternate sets require explicit policy enablement and inventory gates. |
| **Booklet Distribution List** | A proctoring sheet showing which Booklet Set is assigned to each examinee in a room. | Operational roster for Proctors; strictly does *not* contain answer keys or solutions. |

---

## 5. Admission Clearance & Institutional Handoff

The resolution of `SRC-OMP-01` and `SRC-SPEC-02` established strict terminology around the final admission boundary:

```
[Consultation & Counselling]
             │
             ▼
[Ready for Admission Clearance]  --> All academic requirements & endorsements met
             │
             ▼
[Admission Clearance Confirmed]  --> Registrar Admin/Clerk digital sign-off
             │
             ▼
[Enrollment-Sheet Handoff]       --> Physical paper slip issued to applicant
             │
      ═══════╪══════════════════════ [NEXIAM BOUNDARY CLOSES] ═══════════
             ▼
[Institutional Enrollment]       --> External university registrar process
```

| Term | Exact Definition | Boundary Rule |
|---|---|---|
| **Ready for Admission Clearance** | State where an applicant has achieved qualifying exam scores and received program endorsement or resolved academic follow-ups. | Data state: `AdmissionStatus::ReadyForClearance`. Registrar queue visible. |
| **Admission Clearance** | Registrar's official confirmation that an applicant has satisfied all institutional entrance criteria for their endorsed program. | Digital transaction: `ConfirmAdmissionClearance`. Does *not* mean the applicant is enrolled in the college. |
| **Enrollment-Sheet Handoff** | The physical recording of issuing an official college enrollment authorization slip to the cleared candidate. | Physical interaction recorded via `RecordEnrollmentSheetIssue`. NEXIAM's final lifecycle milestone. |
| **Institutional Enrollment** | The external university administrative process (fee payment, subject registration, section assignment, ID issuance). | **OUT OF SCOPE.** NEXIAM never tracks, confirms, or stores final enrollment completion. |
| **Paper Follow-Up Verification** | Authorized staff recording that an applicant completed a required department interview or physical requirement offline. | Distinct from digital Program Head clearance; provides manual fallback. |

---

## 6. Organizational Actors, Hierarchy & Duty Scopes

As locked during `SRC-OMP-02`, NEXIAM strictly separates **permanent office personnel ranks** from **assignable operational duties and capabilities**:

### Office Ranks (Permanent Staff Directory Hierarchy)
$$\text{Support Staff (Rank 1)} < \text{Clerk (Rank 2)} < \text{Office Lead (Rank 3)} < \text{Campus HR (Rank 4)}$$
- Personnel may only manage strictly lower ranks within their own office.
- Support-staff roles are derived from office assignment; permanent helpers cannot self-assign permissions.

### Assignable Duties vs Capabilities

```
┌────────────────────────────────────────────────────────────────────────┐
│                   GUIDANCE COUNSELOR (Office Lead)                     │
│                                   │                                    │
│       ┌───────────────────────────┴────────────────────────────┐       │
│       ▼                                                        ▼       │
│  DELEGATES:                                               ASSIGNS:     │
│  Test Administrator Capability Set                        Proctor Duty │
│  (Session creation, scoring, OMR review)      (Session-specific conduct)│
│       │                                                        │       │
│       ▼                                                        ▼       │
│  Guidance Clerk / Support Staff                     Guidance / Faculty  │
└────────────────────────────────────────────────────────────────────────┘
```

1. **Test Administrator (Capability Set):**
   - Not a permanent job title; a designated capability set (`guidance.exam.schedule`, `guidance.scoring.omr`).
   - Authorized to create/publish exam sessions, finalize rosters, run OMR scoring batches, and author booklets.
   - Designated by Guidance Counselor via `guidance.ta.delegate`.
2. **Proctor (Session Duty):**
   - An ephemeral, session-specific duty assigned by holders of `guidance.exam.proctor.assign`.
   - Authorized *only* within their specifically assigned exam session: take attendance (`present`/`absent`), mark room incidents, and close the session.
   - Does *not* grant rights to schedule other exams, view global rosters, or alter scoring keys.
   - **Registrar personnel are strictly barred from proctoring.**
3. **Program Head / Academic Dean:**
   - Academic department chairs (e.g., CAS, CBME, CTE).
   - Authorized to manage program quotas, review endorsed applicant queues, record consultation interview notes, and append clearance receipts.
   - **Strictly barred from exam scheduling, live exam monitoring, and raw OMR scoring.**

---

## 7. Decision-Support & Recommender Terminology

| Term | Reconciled Definition | Underlying System Reality (`SRC-SPEC-04`) |
|---|---|---|
| **Admission Support Engine** | The pluggable server SPI coordinating course compatibility, quota projections, and career advising insights. | Implemented as `AdmissionEngineManager` supporting pluggable inference providers. |
| **TabFM Course Match Index** | A 0–100 percentile score representing the empirical compatibility of an applicant's profile with a degree program. | Computed via in-context TabPFN inference using historical student benchmark exemplar sets (`admission_benchmark_sets`). |
| **Honest Hexagon Radar** | Visual 6-axis presentation comparing an applicant's raw construct scores against approved program benchmark floors. | Standardized radar series (Applicant, Program Floor, Honors Threshold). |
| **YuKee / K-Means Standby** | Legacy clustering model developed in prior campus thesis work (Yukee et al., 2025). | Placed on **formal standby** awaiting verified `.joblib` model weights under GF-10 Path A. Not used for active decision recommendations. |
