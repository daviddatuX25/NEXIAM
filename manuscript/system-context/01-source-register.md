# Source Register & Authority Inventory

This register catalogs every primary document, session transcript, technical specification, and empirical dataset used to synthesize NEXIAM's system context. Each source is assigned an immutable **Source ID**, classification, custodian, and authority scope.

---

## 1. Conversational & Decision Audits (omp Sessions)

### `SRC-OMP-01`: Ground Zero Terminology & Life-Cycle Audit
- **Session Identifier:** `01a105f4-d5bf-7062-82e5-1f6e8b4d9a1e`
- **Title:** *Audit All Terms From Ground Zero*
- **Recorded Date:** 2026-10-04 to 2026-10-09
- **Participants:** Primary Researcher / Founder (`daviddatuX25`) & OMP Assistant
- **Custodian:** `~/.omp/agent/sessions/-Projects-NEXIAM/2026-10-04T08-08-14-015Z_01a105f4-d5bf-7062-82e5-1f6e8b4d9a1e.jsonl`
- **Authority Scope:** Binding conversational reconciliation of domain vocabulary across 7 operational stages; identified risks in unverified ICAT expansions, scoring constructs vs aptitude areas, clearance vs enrollment boundaries, and participant vs applicant distinctions.

### `SRC-OMP-02`: Scalable Role-Based Access Control & Capability Audit
- **Session Identifier:** `01a118be-e317-73c8-ae1e-44bf00723966`
- **Title:** *🛡️ RBAC: Audit scalable role-based permissions*
- **Recorded Date:** 2026-10-07 to 2026-10-08
- **Participants:** Primary Researcher / Founder (`daviddatuX25`) & OMP Assistant
- **Custodian:** `~/.omp/agent/sessions/-Projects-NEXIAM/2026-10-07T23-42-05-591Z_01a118be-e317-73c8-ae1e-44bf00723966.jsonl`
- **Authority Scope:** Definitive establishment of office hierarchies and duties: Test Administrator as an assignable Guidance capability; Proctor as an ephemeral session duty; Registrar Admin vs Clerk vs Support clearance boundaries; strict exclusion of Program Heads from examination conduct.

---

## 2. Canonical Governance & Product Foundations

### `SRC-GOV-01`: Canonical Domain Glossary
- **Locator:** `GLOSSARY.md` (Repository Root)
- **Authority Scope:** Highest terminological authority for test measurement concepts (instrument, subtest, construct, mapping block, raw metric, norm conversion) and institutional clearance.

### `SRC-GOV-02`: Greenfield Master Reference & Delivery Roadmap
- **Locator:** `docs/GREENFIELD-MASTER-REFERENCE.md` & `docs/MASTER-DELIVERY-ROADMAP.md`
- **Authority Scope:** Single human entry point tracking phase checkpoints, active blockers, technical outages, and verified delivery gates across E0, E1, E1b, and E2 vertical slices.

### `SRC-GOV-03`: Promoted Product Requirements Document (PRD)
- **Locator:** `docs/prd.md`
- **Authority Scope:** Functional (75 FR), Non-Functional (55 NFR), and Business Rule (83 BR) baseline governing vertical slice boundaries (REQ-VS-001 through REQ-VS-032).

### `SRC-GOV-04`: Greenfield Foundation Architecture Suite (GF-01 to GF-24)
- **Locator:** `docs/greenfield-foundation/`
  - `GF-04`: Role, Capability, and Data Scope Matrix
  - `GF-05`: Domain Model and Data Lifecycle
  - `GF-06`: Workflows and State Transitions
  - `GF-07`: Failure Recovery and Integration Contracts
  - `GF-13`: Decision and Batch Approval Matrix
  - `GF-17` / `GF-18`: System High-Level (HLD) & Low-Level Design (LLD)
  - `GF-19`: Schema Constraint and Migration Catalog
  - `GF-21`: Authorization Enforcement Design (`CapabilityAuthorizer`)
  - `GF-22`: UX and Interface Contract
- **Authority Scope:** Authoritative engineering architecture superseding all legacy implementation patterns.

---

## 3. Active OpenSpec Change Specifications (`openspec/changes/`)

| Source ID | Change Specification Directory | Primary Focus & System Authority |
|---|---|---|
| `SRC-SPEC-01` | `versioned-test-instruments-and-scoring/` | Semantic separation of test instrument profiles, subtests, items, raw metrics, and norm packages. *Status: Partial implementation.* |
| `SRC-SPEC-02` | `admission-clearance-boundary-and-workflow-cohesion/` | Discrete boundary separating Registrar admission clearance, physical enrollment-sheet handoff, and academic follow-ups. |
| `SRC-SPEC-03` | `case-messaging-reliability-and-applicant-replies/` | Inter-office case communication, URL-isolated conversation tokens, and applicant messaging rails. |
| `SRC-SPEC-04` | `tabfm-foundation-model-solidification/` | In-context TabPFN/TabFM tabular foundation model engine for course compatibility scoring. |
| `SRC-SPEC-05` | `omr-dual-mode-and-flexible-paper-lifecycle/` | Digital-matrix and uploaded-paper OMR authoring, multi-page corner detection, and guide geometry. |
| `SRC-SPEC-06` | `staff-authentication-and-onboarding/` | Headless Fortify authentication, QR voucher activation, Argon2id fallbacks, and TOTP multi-factor gates. |
| `SRC-SPEC-07` | `action-core-authority-realignment/` | Unification of human controllers and AI gateway tools onto identical domain Actions and Queries. |
| `SRC-SPEC-08` | `instant-session-search-suggestions/` | Debounced server search across examination sessions, rosters, and candidate assignments. |
| `SRC-SPEC-09` | `shared-document-preview-and-consumer-recovery/` | High-fidelity headless Chromium document rendering, PDF generation, and image thumbnail caching. |

---

## 4. Empirical Datasets & Stakeholder Evidence

### `SRC-DAT-01`: ISPSC Tagudin Campus Admission & Testing Journey Survey
- **Format / Date:** CSV Dataset (Batch 2026-09-29)
- **Location:** `manuscript/Phase(RAD-based) Work/01-requirements-planning/Preliminary Interviews & Questionaire/`
- **Scope:** 22 total baseline responses across two rounds (Round 1: 12 respondents on 2026-08-28; Round 2: 10 CBME first-year students on 2026-09-29).
- **Ethics Notice:** Contains identifiable personal data. Raw data is excluded from export packages; only anonymized aggregate percentages (FM1–FM5) are cited.

### `SRC-DAT-02`: Guidance Office Admission Workflow Validation Protocols
- **Format / Date:** Semi-structured interview transcripts (Round 1 & Round 2, 2026-09)
- **Location:** `manuscript/field_protocols/01_guidance_office_validation_protocol.md` & `docs/legacy-reference/`
- **Scope:** Guidance Counselor feedback establishing exam scheduling bottlenecks, manual scoring turnaround (1,000+ examinees per season), and consultation practices.

### `SRC-DAT-03`: Program Head Enrollment Operations Survey
- **Format / Date:** CSV Responses & Narrative Ledger (Round 1, 2026-09)
- **Scope:** Deans and Department Chairs from CAS, CBME, and CTE documenting quota management, endorsement queues, and interview validation.

---

## 5. Academic Manuscript References

### `SRC-MAN-01`: Defended Capstone Manuscript Baseline
- **Document:** *NEXIAM Capstone Manuscript.docx* (Google Docs Baseline)
- **Version Date:** Defended July 2026 / Local revision August 2026
- **Status:** Primary structural reference for research objectives (SO1, SO2, SO3), conceptual framework, and institutional background.

### `SRC-MAN-02`: Local Manuscript Improvement Ledger & Index
- **Locator:** `manuscript/MANUSCRIPT_REFERENCE_INDEX.md` & `manuscript/MANUSCRIPT_IMPROVEMENT_LEDGER.md`
- **Status:** Working operational register logging approved researcher corrections, plate anchors, and figure slot metadata.

### `SRC-MAN-03`: Published External Reference (Yukee et al., 2025)
- **Document:** *Predictive Modeling for College Admission...* (ISPSC Research Archive)
- **Role:** Structural and rhetorical comparison reference for RAD and TAM presentation; NEXIAM explicitly rejects its CRISP-DM methodology, SUS scoring, and standalone K-Means clustering.
