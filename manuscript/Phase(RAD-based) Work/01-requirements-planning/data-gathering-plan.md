# For Data Gathering

**Purpose:** Track all data gathering activities, pending items, and unstructured sources for NEXIAM's RAD process.
**Context:** This feeds Requirements Planning (SO1) and informs engine configuration, mapping tables, and evaluation design.

---

## A. Pending Data Gathering Items

### A.1 Cluster-to-Program Mapping — Domain Expertise (HIGH PRIORITY)

The Admission Support Engine classifies applicants into aptitude clusters. The **cluster-to-program mapping table** must be validated by domain experts — Program Heads — not assumed by the development team.

| Item | What | Who | Status |
|---|---|---|---|
| Program prerequisite requirements | Which competencies are required per ISPSC program? | Registrar / CHED curricula | PENDING |
| Cluster-to-program fit validation | Does Math-Dominant → BSIT, BSCE make sense per Program Head? | Program Heads (n=11) | PENDING |
| Override patterns | What exceptions do counselors commonly make? | Guidance Office | PENDING |
| Program descriptions for BGE embeddings | Official program descriptions, outcomes, career paths | Registrar / Program Heads | PENDING |

**How to gather:** Structured short interview or questionnaire during RAD User Design phase. Each Program Head reviews the draft mapping table and marks agree/disagree/suggest-change per row.

### A.2 BGE Interest Engine — Course Description Texts

| Item | What | Source | Status |
|---|---|---|---|
| Official ISPSC program descriptions | Text descriptions for all 11+ programs (BSIT, BSCE, BSED, etc.) | ISPSC website / prospectus / CHED | PENDING |
| Program prerequisites and outcomes | Competency statements for embedding similarity | Registrar / CHED | PENDING |

### A.3 Yukee Cluster Engine — Feature Validation

| Item | What | Source | Status |
|---|---|---|---|
| ICAT sub-test structure | How many subtests? What do they measure? | Guidance Office / Yukee manuscript | PARTIALLY KNOWN |
| ICAT score range and distribution | What are typical percentile ranges for ISPSC applicants? | Guidance Office historical data | PENDING |
| Yukee cluster definitions exact parameters | Centroid coordinates (if obtainable from Yukee team) | Yukee et al. manuscript / contact researcher | PENDING |

---

## B. Unstructured / Hidden Data Sources

These are data I (David) have collected informally but haven't laid out formally. They need to be surfaced and catalogued.

| # | Description | Format | Location / Status |
|---|---|---|---|
| B1 | Informal interview notes with Guidance Office (early 2026) | Handwritten / voice memos | NOT YET DIGITIZED |
| B2 | Registrar workflow observations | Notes / photos | NOT YET ORGANIZED |
| B3 | Screenshots / recordings of current manual process | Media files | UNKNOWN |
| B4 | Previous capstone data (Yukee, Ballesteros, Angala, Cenezan findings) | PDF / manuscript | IN REFERENCES, NOT YET EXTRACTED |
| B5 | Program Head informal feedback on current quota system | Chat / verbal notes | NOT YET FORMALIZED |
| B6 | IT infrastructure assessment (network, hardware at Tagudin Campus) | Notes | NOT YET WRITTEN |
| B7 | Chatbot inquiry logs from existing channels (Facebook page, text) | Screenshots / logs | NEEDS REQUEST FROM OFFICE |
| B8 | [SLOT — add more as found] | | |

---

## C. Data Gathering Timeline (RAD-Aligned)

| RAD Phase | Data Activity | Deliverable |
|---|---|---|
| **Requirements Planning** *(June – early July)* | Semi-structured questionnaires, field observation, process mapping | Workflow diagrams, gap analysis |
| → *Already done or in-progress per timeline* | | |
| **User Design** *(July)* | Program Head validation of cluster-to-program mapping, course description collection | Validated mapping table, program description corpus |
| **Rapid Construction** *(early July – August)* | Yukee centroid request, BGE embedding computation, engine calibration | Configured engines |
| **Cutover** *(September)* | TAM evaluation (already designed) | Acceptance measures |

---

## D. Notes & Caveats

- Synthetic data for cluster validation is **excluded** per prior decision (panelist: "how do you know it works on real ISPSC data?").
- Formal NPC certification and Privacy Impact Assessment are outside scope.
- Applicant consent for data use assumed under ICAT application form; formal verification is future work.
- BGE embeddings are computed from **published ISPSC program descriptions** — no private data needed.
- Cluster-to-program mapping is a **deterministic reference table**, not a model output — Program Heads validate, not train.

---

*Created: 2026-07-14. Update as new data sources surface or pending items are completed.*
