# Change Proposal: E2 Connected Admission-to-Release Slice

> **INCOMPLETE / PARKED 2026-08-05** — Journey command spine + Pest evidence remain in `system/`. OpenSpec exit tasks G2–G4/H4 were not closed. Active OpenSpec moved to `openspec/changes/staff-personnel-lifecycle/`. Resume E2 exit after personnel lifecycle PASS.


**Change ID:** `e2-connected-admission-release-slice`  
**Status:** READY FOR IMPLEMENTATION (planning gate PASS 2026-08-05)  
**Depends on:** E0 PASS, E1 PASS, E1b UI kit locked (archived under `openspec/changes/archive/`)  
**Parent authority:** [`docs/prd.md`](../../../docs/prd.md), GF-11, GF-20–GF-22, GF-24  
**Stories:** [`docs/epics-and-stories-e2.md`](../../../docs/epics-and-stories-e2.md)  
**UX catalog:** [`docs/e2-page-state-catalog.md`](../../../docs/e2-page-state-catalog.md)  
**Legacy traces:** [`docs/legacy-reference/08-e2-connected-slice-trace.md`](../../../docs/legacy-reference/08-e2-connected-slice-trace.md)

## Why

E0/E1/E1b established runtime, identity/reference, and locked staff UI chrome. The product still lacks the connected institutional journey that proves command-owned state, capability/scope authorization, manual scoring fallback, release/notify, and audit. GF-11 defines that journey as 32 `REQ-VS-*` acceptance requirements. This change is the single active OpenSpec authorizing E2 implementation in `system/`.

## Scope

Implement the Connected Admission-to-Release Slice in build order:

1. SubmitAdmissionAttempt + identity/reference linkage  
2. Correct / Accept / Dismiss / reopen-archive  
3. Applicant access challenge/setup  
4. Exam session create/assign/publish (manual paths)  
5. Proctor duty → attendance/submission  
6. Close exam → open grading once  
7. Manual score / finalize  
8. Consultation decision  
9. Prepare result document + internal preview  
10. Release + after-commit notification + applicant retrieval  
11. Reverse / unrelease with history  
12. Denial, concurrency, page_state, a11y, audit evidence  

Reuse locked UI kit composites; CapabilityAuthorizer remains decision authority (Spatie helpers only).

## Non-goals

- OMR promotion / autonomous scoring (REQ-VS-010 gated → E3)  
- Nexi provider, RAG embeddings, typed Apply execution beyond presentation gate (REQ-VS-024 gated → E3)  
- Offline proctoring runtime  
- BGE/YuKee functional integration  
- Expanding Program Head metrics beyond approved conditional hold  
- Reintroducing legacy role middleware as authorization  
- Broad features outside GF-11 COMMITTED cut-line  

## Deliverables

1. Domain commands/events per GF-11 spine with transactional audit  
2. Inertia Svelte pages per [`e2-page-state-catalog.md`](../../../docs/e2-page-state-catalog.md)  
3. Pest + Playwright evidence mapped to REQ-VS / SCN-VS  
4. Updated crosswalk rows as behaviors land  
5. E2 evidence report before claiming PASS  

## Definition of Done

- All OpenSpec tasks complete without hiding earlier failures  
- Every COMMITTED `REQ-VS-*` has executable evidence (NFR-039)  
- Gated IDs remain documented non-goals with manual fallbacks proven  
- Master reference checkpoint updated  
