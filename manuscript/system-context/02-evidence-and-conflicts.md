# System Evidence, Architectural Conflicts & Open Questions

This document details the grounded discrepancies between historical system assumptions, capstone manuscript claims, and executable greenfield reality. It serves as an audit ledger so that researchers do not inadvertently present unverified proposals or interim engineering states as verified capstone facts.

---

## 1. Primary Conflict Register

### Conflict 1: ICAT Expansion & Subtest Composition
- **Manuscript Draft Claim (Chapter 1, §1.6):** Expands ICAT as *"Ilocos Sur Polytechnic State College College Admission Test"* and specifies a rigid six-subtest battery.
- **System Reality (`SRC-SPEC-01`, `GLOSSARY.md`):** The greenfield test-scoring engine is completely instrument-agnostic (`TestInstrument`, `TestSubtest`). Official campus documentation specifying exact subtest counts, time limits, psychometric reliabilities, and manual dexterity requirements has never been verified in code or institutional records.
- **Evidence Status:** `[UNVERIFIED - ACTION REQUIRED]`
- **Prescribed Academic Handling:** Use the acronym **ICAT** without asserting an unverified institutional expansion or item distribution. State that NEXIAM supports flexible subtest configuration conforming to institutional test profiles.

### Conflict 2: Automated OMR Grading vs Mark Recognition
- **Manuscript Draft Claim (Chapter 3, SO2):** Describes "Automated OMR Grading" where sheet scanning immediately produces final student grades.
- **System Reality (`SRC-SPEC-05`, `GF-06`):** The system enforces a strict 4-stage pipeline:
  1. *Optical Recognition:* Inference worker detects bubble circles and emits fill probabilities (`OmrDetectedItem`).
  2. *Uncertainty Review:* Low-confidence bubbles or multi-marked items enter a human review desk (`OmrItemReview`).
  3. *Key Application:* Clean observations are evaluated against a Booklet Set answer key (`EvaluateItemResponses`).
  4. *Score Finalization:* Finalized scores are immutably signed and committed by a designated Test Administrator.
- **Evidence Status:** `[IMPLEMENTED-TESTED]`, but physical scanner qualification is `[UNVERIFIED - HARDWARE PENDING]` (synthetic digital fixtures pass; physical paper test-kit qualification matrix is open).
- **Prescribed Academic Handling:** Clearly separate *optical bubble recognition* from *attributable scoring review*. Do not claim 100% autonomous grading without human oversight.

### Conflict 3: TabFM Foundation Model vs K-Means Recommender
- **Manuscript Draft Claim (Chapter 2 & 3):** Mentions adaptive student course recommendations driven by TabPFN or historical K-Means clustering.
- **System Reality (`SRC-SPEC-04`):**
  - **TabFM / TabPFN:** The Prior-Labs `tabpfn-v3-classifier` checkpoint is containerized, and offline synthetic proof passed on CPU. However, active HTTP inference is backed by an in-context pairwise compatibility matcher.
  - **YuKee / K-Means:** Standalone clustering from the 2025 predecessor study is on **formal standby** awaiting verified `.joblib` model artifact packages. It does not actively generate recommendations in current production builds.
- **Evidence Status:** `[IMPLEMENTED-TESTED - PARTIAL INFERENCE]`
- **Prescribed Academic Handling:** Refer to the recommendation component as an *evidence-based program compatibility engine* using benchmark exemplars and tabular foundation model priors. Do not claim production deployment of YuKee K-Means clustering.

### Conflict 4: Admission Clearance vs College Matriculation (Enrollment)
- **Manuscript Draft Claim:** Suggests that NEXIAM completes the college enrollment process.
- **System Reality (`SRC-SPEC-02`, `SRC-OMP-01`):** NEXIAM's lifecycle strictly terminates at **Admission Clearance** (Registrar verification that entrance criteria are met) and the physical **Enrollment-Sheet Handoff** (issuing the paper authorization slip). The university's official matriculation (accounting, registrar subject load, student ID card creation) is managed by external institutional systems.
- **Evidence Status:** `[IMPLEMENTED-TESTED]`
- **Prescribed Academic Handling:** Explicitly define NEXIAM as an *Admission, Assessment, and Consultation Management System*, not an end-to-end Student Information System (SIS).

### Conflict 5: Examination Scheduling Defaults (Simple vs Assigned)
- **Manuscript Draft Claim:** Asserts that NEXIAM provides automated spatial desk seating arrangements for examinees.
- **System Reality (`openspec/changes/exam-seating-mode-and-booklet-distribution-policy/`):** By founder lock, the operational default is **Simple Capacity** (booking a room up to its seat limit without specifying spatial seat numbers). Spatial desk grids and assigned seat codes are gated behind an optional institutional policy toggle (`enable_seating_arrangements = false` by default).
- **Evidence Status:** `[IMPLEMENTED-TESTED]`
- **Prescribed Academic Handling:** Describe room-capacity scheduling as the primary institutional workflow; present spatial desk assignment as a configurable advanced policy.

---

## 2. Research Methodology & Sampling Gaps

### Gap 1: Evaluation Participant Sampling Basis (263 Planned)
- **Documented Plan (Appendix N, §2.4):**
  - Registrar Staff: 3 (Census)
  - Guidance Staff: 2 (Census)
  - Academic Leaders / Deans: 3 (Purposive: CAS, CBME, CTE)
  - First-Year Students: 255 (Purposive / Convenience)
  - **Total:** 263 Planned Evaluators
- **Current Status:** The baseline survey collected n=22 responses (Round 1: 12, Round 2: 10). The planned 255-student post-test evaluation cohort is **unverified in terms of actual completed responses**.
- **Prescribed Academic Handling:** Chapter 3 result tables (Tables 3.5–3.8) must remain designated as *blank reporting structures* until actual evaluation sessions are executed and logged. Never report planned numbers as collected findings.

### Gap 2: Research Participant Informed Consent & Legal Claims
- **Observation (`SRC-OMP-01`):** Appendix J drafts assert strict compliance with Republic Act No. 10173 (Data Privacy Act of 2012) and include institutional consent boilerplate.
- **Current Status:** Formal institutional ethics clearance and signed stakeholder consent protocols remain pending validation.
- **Prescribed Academic Handling:** Treat consent notices in working instruments as draft protocols. Ensure all public exports are scrubbed of identifiable participant names.

---

## 3. System Implementation Status Summary Table

| Functional Domain | Intended Architecture (GF) | Current Runtime State (`system/`) | Tested Evidence | Known Implementation Gaps |
|---|---|---|---|---|
| **Public Portal & Intake** | Self-service application intake with PSGC address selector & one-time access key | Implemented | 19 Pest tests / 244 assertions | DepEd/CHED public school database API is partial; HEIDA Region I list bundled |
| **Exam Scheduling** | Simple capacity sessions with room quotas & proctor duty assignment | Implemented | 12 Pest tests / 178 assertions | Seating grid disabled by default; alternate Booklet sets gated |
| **Live Monitoring** | Real-time candidate attendance (`present`/`absent`/`void`) | Implemented | 68 Pest tests / 771 assertions | QR camera scanning requires physical device confirmation |
| **OMR Sheet Authoring** | Multi-page PDF/image ingestion with 2.5mm bubble threshold & paired option tokens | Implemented | 48 physical challenge tests; Vitest studio suite | Real printed institutional capture & printer provenance matrix pending |
| **Psychometric Scoring** | Versioned test instruments, subtests, raw metrics, and norm packages | Partial Implementation | Unit tests on `NativeBundle` | Studio context disconnected from Profile; Norm packages UI absent |
| **Decision Support (TabFM)** | TabPFN compatibility score + honest hexagon radar comparison | Implemented | 14 Pest tests / 9 Python worker unit tests | YuKee K-Means model on standby; CPU inference optimized |
| **Case Communications** | URL-isolated token messaging between applicants and departments | Implemented | 101 Pest / 28 Vitest / 13 Concurrency tests | Requires active Compose environment; push notifications disabled |
| **Admission Clearance** | Registrar clearance sign-off & physical enrollment-sheet handoff | Implemented | 68 Pest tests / 771 assertions | Paper specimen validation pending; external enrollment excluded |
| **Staff Authentication** | Fortify headless auth, TOTP 2FA, QR activation vouchers | Implemented | Full Auth test suite green | Argon2id fallback requires physical SMS/paper handoff |
