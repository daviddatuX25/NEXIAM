# Change Proposal: Admission Attempt Detail (P-REG-02)

**Change ID:** `admission-attempt-detail`  
**Status:** ACTIVE  
**Depends on:** E2 journey spine (parked incomplete); staff-personnel archived PASS  

## Intent

Scaffold registrar **attempt detail** (`P-REG-02`) as the scalable complement to the intake queue:

- Read-only identity/intake projection with versioned actions
- Status-gated primary actions matching legacy list/detail split
- Separate **in-place correct** (stays pending) from **request correction** → `needs_correction` (GF-06 / D-074 scaffold)
- Archive / reopen with reason — not peers of Accept on the queue
- Accessible Dialog confirmations (no `prompt()`/`confirm()` as sole contract)
- Registrar access-key reissue (D-085) + applicant resubmit correction E2E (GF-06)

Preserve legacy Applications/Show feel; rewrite authz (CapabilityAuthorizer), optimistic version, audit, non-destructive reopen/archive.

## Non-goals

- Full pipeline milestone chrome (E2 exit / later)
- Applicant resubmit correction portal (seam only: status + notes)
- Bulk actions; admission slip print
- E2 G2–G4 Playwright suite (resume under e2 OpenSpec)
- Setup catalog CRUD

## Authority

E2-S03, P-REG-02, FR-003, REQ-VS-004, GF-06 transitions, INV-016, CAP registrar applications.*, legacy `Applications/Show.svelte` + Index action matrix.

## Exit

Pest show/deny/request-correction/correct-stays-pending + Compose spot-check `/admissions/{id}` as registrar clerk.
