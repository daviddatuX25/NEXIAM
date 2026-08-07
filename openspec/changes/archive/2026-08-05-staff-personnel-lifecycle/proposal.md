# ARCHIVED — PASS 2026-08-05

# Change Proposal: Staff Personnel Lifecycle

**Change ID:** `staff-personnel-lifecycle`  
**Status:** READY FOR IMPLEMENTATION  
**Depends on:** E1 PASS (identity/capability seams); E2 journey parked incomplete  

## Intent

Complete staff personnel lifecycle on E1 seams: list/create/edit/deactivate staff accounts with D-014 role helpers, D-020 ceilings, CapabilityAuthorizer authority, and recoverable deactivate (not hard delete). Preserve legacy Admin/Users UX feel; rewrite authz/state/audit.

Role key locked: `hr_administrator` (inventory `hr_staff_admin` is alias only).

## Non-goals

- TA grant/revoke UI; Proctor assignment
- Google OAuth; invite email; Super Admin routine staffing
- Registrar setup CRUD (cycles/windows)
- E2 G2–G4 Playwright exit (resumes after this PASS)

## Authority

ACT-07, CAP-029/030, D-014/D-017/D-019/D-020, BR-012, FR-076; legacy evidence `docs/legacy-reference/09-staff-personnel-lifecycle-trace.md`.

## Exit

Pest denial/ceiling/lifecycle evidence + Compose spot-check `/staff/personnel` as HR. Then archive this change; E2 exit may resume.
