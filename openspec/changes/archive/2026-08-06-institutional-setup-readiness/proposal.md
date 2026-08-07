# Change Proposal: Institutional Setup Readiness

**Change ID:** `institutional-setup-readiness`  
**Status:** ACTIVE  
**Depends on:** E1 reference models; D-087; admission-attempt-detail archived PASS 2026-08-06  

## Intent

Scaffold the **administrative Setup hub** (legacy Admin/Setup feel) and shared catalog CRUD so staff can prepare the system before scheduling and release:

1. **Setup readiness hub** — overall + category progress (critical / important / optional); deep links to configure cards  
2. **Shared institutional setup** (D-087) — cycles/windows, programs/courses, rooms — writable by Registrar **or** Guidance  
3. **Program quotas** (D-087) — Program Head PROGRAM-scoped write; Guidance Counselor (TA) write with PH communication seam; before result release  
4. Capability wiring: keep key `registrar.setup.manage` as shared setup grant; add `program.quota.manage`

Preserve legacy Setup hub affordances; rewrite authz (CapabilityAuthorizer), deactivation not hard-delete, audit on mutations.

## Non-goals

- Full scheduling UI (next OpenSpec after setup healthy)  
- YuKee / quota optimization engines  
- Full in-app TA↔PH messaging (first cut: audited change log + notice seam)  
- Program Head admission/score/release writes  

## Authority

D-087, GF-04 CAP-007/008/025, master §6.1 + §8.8, legacy `Admin/Setup/Index.svelte` + `SetupController`, access-control inventory.

## Exit

- Setup hub renders readiness from target checks  
- Shared setup holders (registrar_admin/clerk, guidance_counselor/clerk) reach hub; support denied  
- Pest: PH quota own-program allow / cross-program deny; Guidance TA quota allow; Registrar quota deny by default  
- Compose spot-check `/setup`  
