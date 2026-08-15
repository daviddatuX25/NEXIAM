# Change Proposal: TA Capability Delegation

**Change ID:** `ta-capability-delegation`  
**Status:** ACTIVE — implementation complete (awaiting PASS/archive)  
**Phase:** E2 connected-slice ops (post proctor-duty PASS)  
**Depends on:** `exam-proctor-duty-assign` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-exam-proctor-duty-assign/`

## Intent

Ship **Guidance-controlled Test Administrator delegation** (D-016): authorized actors grant/revoke the full TA capability **set** on eligible Guidance clerk/support accounts — without inventing a Spatie `test_administrator` role and without HR/Registrar authority (D-017/D-020).

After grant, the delegate holds the same TA keys as inherent counselor (incl. `guidance.exam.proctor.assign`) and can assign Proctor duties via the already-shipped session Show UI.

## Authority (synced — one matrix)

| Concern | Cap / rule |
|---|---|
| Who may grant/revoke TA set | `guidance.ta.delegate` (inherent on counselor seed only — **not** part of the TA set, so delegates cannot re-delegate) |
| What is granted | Full `CapabilityCatalog::testAdministratorCapabilities()` via Spatie **direct** user permissions |
| Who may receive | Active Guidance `guidance_clerk` / `guidance_support_staff` |
| Proctor assign after grant | Same `guidance.exam.proctor.assign` path as counselor (no parallel matrix) |
| HR / Registrar | No TA or Proctor grant authority |

## In scope

1. **Staff directory for all staff** — `/staff/personnel` viewable (read-only) by every authenticated staff member, office-scoped unless full HR; single **Staff** nav item for all  
2. Personnel page doubles as the unified Staff page: office members table + TA status column (`inherent` / `delegated` / `—`) + inline grant/revoke + existing support-staff create  
3. **Office support-staff creation by office staff** — registrar clerk and guidance clerk gain `office.support_staff.manage`; anyone with it creates support accounts for their own office, role **derived from office** (never picked from a checklist)  
4. Catalog + seed: `guidance.ta.delegate` on counselor  
5. Actions: Grant / Revoke TA set + audit  
6. Routes: `GET /staff/personnel` (no personnel-access gate) + `staff.personnel.grant-ta` / `revoke-ta` (capability-gated); standalone Test Administrators page removed  
7. Pest: view for all staff (office-scoped); clerk creates support; grant → clerk assigns proctor; revoke; HR/registrar denied; support eligible  

## Explicit non-goals

- Partial TA subsets / timed expiry UI (grant full set; audit only)  
- Nexi proposals for TA grant  
- Changing personnel role ceilings (clerks stay out of support-create ceiling)  
- Registrar office mirror for other overlays  

## Exit

Counselor grants TA to clerk/support; clerk can then assign Proctor on session Show; revoke removes TA extras; Pest + HANDOFF.
