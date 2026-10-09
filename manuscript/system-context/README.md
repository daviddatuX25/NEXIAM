# NEXIAM System Context & Technical Reference Package

## 1. Purpose & Scope

This package provides a **self-contained, evidence-grounded reference of NEXIAM's system architecture, domain logic, operational workflows, and verified terminology**. It is situated directly inside `manuscript/system-context/` so that researchers, technical writers, and advisers can export or inspect the `manuscript/` folder and complete academic writing outside this development environment without losing access to system reality.

This package serves as **research and documentation context**, not as an application runtime or a replacement for the active OpenSpec implementation authorities. Existing chapter files under `manuscript/sections/` remain untouched by this package until specific edits are reviewed and corroborated by evidence.

---

## 2. Reading Order & Package Map

```
manuscript/system-context/
├── README.md                          <- You are here: Package overview, authority rules, and usage guide
├── 00-workplan.md                     <- Structured assembly methodology and governance
├── export-manifest.md                 <- Export baseline, contents, exclusions, and privacy boundaries
├── 01-source-register.md              <- Full registry of sources (omp sessions, decisions, OpenSpecs, code)
├── 02-evidence-and-conflicts.md       <- Grounded audit of architectural discrepancies and open questions
├── 03-terminology-reconciliation.md   <- Cross-domain vocabulary alignment against canonical GLOSSARY.md
├── domains/                           <- Comprehensive domain logic, models, actions, and boundaries
│   ├── 01-admissions-and-intake.md
│   ├── 02-examination-and-scheduling.md
│   ├── 03-scoring-and-omr.md
│   ├── 04-consultation-and-decision-support.md
│   ├── 05-clearance-and-enrollment-handoff.md
│   └── 06-shared-services-and-rbac.md
├── diagrams/                          <- Reconciled system visual models, activity charts, and DFDs
│   └── README.md                      <- Diagram catalog with provenance, baseline, and verification tags
├── research-method-map.md             <- Traceability between specific research objectives and system modules
└── manuscript-claim-crosswalk.md      <- Line-by-line verification of claims across Chapters 1-3
```

---

## 3. Authority Hierarchy

When resolving conflicting definitions or workflow claims, use this strict order:

1. **Recorded Founder Decisions & Verified Institutional Evidence:** Binding constraints (e.g., GF-13, signed stakeholder agreements).
2. **Canonical Domain Vocabulary (`GLOSSARY.md`):** Authoritative arbiter for what entities and measurements are named.
3. **Approved OpenSpec Changes (`openspec/changes/`):** Specification authority for software behavior, database constraints, and transactions.
4. **Executable Code & Test Proofs (`system/`):** Verifiable ground truth for what currently executes and passes.
5. **Observed Stakeholder Baseline (`docs/legacy-reference/`):** Descriptive record of institutional user experience and prior practices; never overrides greenfield security, authorization, or concurrency rules.
6. **Working Notes & Draft Plans:** Contextual and historical inputs requiring corroboration.

---

## 4. Evidence Classification Legend

Every system capability, workflow stage, and technical claim in this package is tagged with one of five explicit evidence levels:

| Tag | Definition | Citation Requirement |
|---|---|---|
| `[TARGET-DESIGN]` | Specified in approved architectural contracts or OpenSpecs; not yet fully executed in runtime. | OpenSpec `design.md` or GF contract reference |
| `[IMPLEMENTED-TESTED]` | Implemented in `system/` and confirmed passing by focused automated feature/unit tests. | Test class name and assertion count |
| `[OBSERVED-EVIDENCE]` | Exercised in real runtime environments (browser automation, live Docker inference, manual walkthrough). | Run date, environment, and artifact/capture ID |
| `[PROPOSED]` | Identified during stakeholder or technical exploration; awaiting founder lock or specification. | Proposal document or session record ID |
| `[UNVERIFIED]` | Existing in legacy notes, drafts, or oral descriptions; lacks code or institutional validation. | Explicit flag: *Decision/Verification Required* |

---

## 5. Privacy, Ethics & Sanitization Rules

To ensure this folder is safely exportable and publishable:

1. **No Personally Identifiable Information (PII):** Raw survey responses with student names, contact details, or identifying free-text comments must **never** be included in exported technical summaries.
2. **Demo Credentials vs Production Secrets:** All credentials referenced in this context are synthetic local seed fixtures (`password`, `test@nexiam.test`). Real production tokens, keys, or passwords must never enter markdown files.
3. **Institutional Dignity:** Administrative gaps reported in interviews (e.g., manual queue bottlenecks, scoring turnaround) are documented objectively as problem statements (FM1–FM5), not as personal criticisms of institutional personnel.
