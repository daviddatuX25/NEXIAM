# Implementation Readiness Report — E2 Planning Gate

**Date:** 2026-08-05  
**Artifact:** Planning readiness (pre-coding) for `e2-connected-admission-release-slice`  
**Prior report:** [`implementation-readiness-report-2026-08-04.md`](implementation-readiness-report-2026-08-04.md) (NOT_READY — 0 epics/stories)  
**Authority:** [`../../GREENFIELD-MASTER-REFERENCE.md`](../../GREENFIELD-MASTER-REFERENCE.md) §10–11; [`../../prd.md`](../../prd.md)

## Verdict

**PASS — planning gate.** E2 implementation in `system/` is now authorized under the single active OpenSpec change `openspec/changes/e2-connected-admission-release-slice/`, in GF-11 build order only.

This is **not** an E2 delivery PASS. Executable Pest/Playwright evidence for `REQ-VS-*` remains to be produced during OpenSpec tasks B–H.

## Document discovery

| Artifact | Status |
|---|---|
| Product PRD [`docs/prd.md`](../../prd.md) | PROMOTED — 75 FR / 55 NFR / 83 BR + E2 cut-line |
| GF-03 | PROMOTED (points to PRD) |
| GF-11 connected slice | Present — 32 REQ-VS + SCN-VS |
| Legacy slice traces [`08-e2-connected-slice-trace.md`](../../legacy-reference/08-e2-connected-slice-trace.md) | 32/32 rows dispositioned |
| Page/state catalog [`e2-page-state-catalog.md`](../../e2-page-state-catalog.md) | Present |
| Epics/stories [`epics-and-stories-e2.md`](../../epics-and-stories-e2.md) | 7 epics / 22 stories (incl. holds + browser quality) |
| Active OpenSpec | `e2-connected-admission-release-slice` |
| Archived OpenSpec | E0, E1, E1b under `openspec/changes/archive/` |
| UI kit lock | E1b locked; Stitch J4 parked |

## Prior blockers (2026-08-04) disposition

| Blocker | 2026-08-04 | 2026-08-05 |
|---|---|---|
| BLOCK-01 Product contract not promoted | OPEN | **CLOSED** — `docs/prd.md` + GF-03 PROMOTED |
| BLOCK-02 No user-value epics/stories | OPEN | **CLOSED for E2 slice** — `docs/epics-and-stories-e2.md` |
| BLOCK-03 UX not page-implementable | OPEN | **CLOSED for E2 slice** — `docs/e2-page-state-catalog.md` |
| BLOCK-04 Legacy parity sampled | OPEN (whole product) | **CLOSED for slice** — 32 REQ-VS traces; whole-product archaeology remains ongoing |
| BLOCK-05 Authority/status contradictions | partial | E0/E1/E1b archived; one active OpenSpec |
| BLOCK-06 Future-feature contracts absent | OPEN | **Accepted hold** — gated in PRD §7 + E2-S21; not required to start E2 manual core |

## FR → epic coverage (slice)

| Area | Stories | REQ-VS |
|---|---|---|
| Foundation seed | E2-S01 | 001, 016 |
| Admission + identity | E2-S02–S05 | 002–004, 019 |
| Exam + proctor | E2-S06–S09 | 005–007, 020 |
| Manual grading | E2-S10–S12 | 008–009, 017, 021 |
| Consultation/release/notify | E2-S13–S16 | 011–015, 022–023 |
| UX/a11y/audit/browser | E2-S17–S20b | 015, 018, 026–032 |
| Gated holds | E2-S21 | 010, 024–025, 029 |

**Slice FR→epic coverage:** non-zero and auditable for all COMMITTED journey FRs. Full-product Tier B/C remain classified, not silently omitted.

## REQ-VS → story → planned evidence

Every `REQ-VS-001`–`032` maps to at least one story and planned Pest/Playwright or explicit hold:

| REQ-VS | Story | Planned evidence |
|---|---|---|
| 001 | E2-S01 | Pest reset/seed |
| 002 | E2-S04 | Pest auth/challenge |
| 003 | E2-S05 | Pest scope denial |
| 004 | E2-S02, S03 | Pest commands; Playwright intake |
| 005 | E2-S06 | Pest conflict; Playwright schedule |
| 006 | E2-S07 | Pest duty lifecycle |
| 007 | E2-S08 | Pest idempotency |
| 008 | E2-S10 | Pest manual path, inference off |
| 009 | E2-S11 | Pest finalize guard |
| 010 | E2-S21 | HOLD → E3; manual proof via S10 |
| 011 | E2-S13 | Pest consultation |
| 012 | E2-S15, S16 | Pest release/unrelease |
| 013 | E2-S15 | Pest outbox after-commit |
| 014 | E2-S14 | Pest document safety |
| 015 | E2-S20 | Pest audit tamper |
| 016 | E2-S01 | Pest + re-login Playwright |
| 017 | E2-S10 | Pest degraded ModeBanner |
| 018 | E2-S20b | Playwright console/network report |
| 019 | E2-S02, S04 | Pest challenge + submit |
| 020 | E2-S09 | Pest close race |
| 021 | E2-S10, S12 | Pest transactional scores |
| 022 | E2-S14 | Pest/Playwright watermark deny |
| 023 | E2-S15 | Pest notification projection |
| 024 | E2-S21 | HOLD → E3; Nexi chrome only |
| 025 | E2-S21 | EVIDENCE HOLD |
| 026 | E2-S17 | Pest page_state props |
| 027 | E2-S18 | Playwright breadcrumbs |
| 028 | E2-S17 | Pest/Playwright mutation UX |
| 029 | E2-S21 | CONDITIONAL hold |
| 030 | E2-S19 | Vitest/Playwright toasts |
| 031 | E2-S18 | Playwright responsive/keyboard |
| 032 | E2-S14 (+ S21 OMR) | Document provenance; OMR hold |

## P0 scan (slice)

| Item | Severity | Disposition |
|---|---|---|
| Missing PRD | P0 | Resolved |
| Missing stories | P0 | Resolved for slice |
| Missing page catalog | P0 | Resolved for slice |
| Missing REQ-VS disposition | P0 | Resolved (holds explicit) |
| OMR/RAG inside E2 | would-be P0 if coded | Prevented by OpenSpec non-goals |
| Manuscript not yet amended | P1 planning | Out of coding critical path; quiet omission still prohibited |

**No open P0 for starting E2 OpenSpec implementation.**

## UX alignment

- Locked UI kit + Nexi wrap-island chrome reused; no new chrome invented.  
- Route responsibilities match GF-22 §4 / catalog §3.  
- page_state vocabulary matches REQ-VS-026/028.

## Recommendations

1. Begin OpenSpec tasks **B → C** (seed + admission) immediately; do not skip to release UI.  
2. Keep one active OpenSpec; do not open E3 provider changes inside this change.  
3. Continue whole-product legacy archaeology outside the slice in parallel without blocking E2 coding.  
4. Produce `e2-connected-slice-evidence.md` before claiming E2 delivery PASS.

## Summary

| Gate | Verdict |
|---|---|
| Planning coherence (PRD + stories + UX + slice traces) | **PASS** |
| E2 OpenSpec authorization | **PASS — authorized** |
| E2 delivery / executable REQ-VS evidence | **NOT YET** (tasks B–H) |
| Pilot/production | **BLOCKED** (unchanged) |
