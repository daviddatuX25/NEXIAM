# Evidence — TA Capability Delegation

**Date:** 2026-08-10  
**OpenSpec:** `openspec/changes/ta-capability-delegation/`  
**Authority:** D-016 (TA set + Guidance delegation), D-017/D-020 (HR cannot grant TA), D-022 (proctor assign via TA holders)

## What shipped

- Catalog: `guidance.ta.delegate` (counselor seed; **not** in TA set).
- `EligibleTaRecipients` (`taStatus` / `canDelegate`) + `GrantTestAdministratorCapabilities` / `RevokeTestAdministratorCapabilities`.
- **Unified Staff page** (`/staff/personnel`): **read-only office directory for all staff** (office-scoped unless full HR); Test Administrator column (`inherent` / `delegated` / `—`) + inline Grant/Revoke for delegate holders; Create/Edit/Deactivate/Activate only for personnel-capable actors.
- **Support-staff creation is an office function**: registrar clerk and guidance clerk also hold `office.support_staff.manage`. Created role is **derived from office** (`registrar_support_staff` / `guidance_support_staff`) — no role checklist for office actors; support roles are never pickable.
- **Role reassignment is HR-only**: permanent-role checklist appears only on HR edit of permanent staff; support staff roles show read-only; office actors cannot change roles via update.
- Routes `staff.personnel.grant-ta` / `revoke-ta` (capability-gated); `GET /staff/personnel` no longer behind `EnsurePersonnelAccess` (view for all authenticated staff); standalone Test Administrators page removed; single **Staff** nav item for every staff user.
- **Rank hierarchy (founder lock 2026-08-10):** support (1) < clerk (2) < office lead (3) < HR (4). `StaffRoleCeiling::canManageUser` — office actors manage strictly-lower ranks in own office only; peers/higher ranks unreachable; enforced in every `Staffing\Actions` + `PersonnelController::edit` 403. Index emits `is_self` / `can_manage_user` per row.
- **Password reset (founder lock 2026-08-10):** HR (anyone) + office leads (own office, lower rank) via `StaffRoleCeiling::canResetPassword`; Edit page hides password field for clerks/support; self-service → profile page (follow-up cut).
- **Profile page (founder lock 2026-08-10):** `/staff/profile` read-only for all staff; own row shows disabled Edit/Deactivate + **Manage Profile** button. Self-edit/self-deactivate are never directory actions.
- Direct Spatie user permissions for full `testAdministratorCapabilities()`; no `test_administrator` role.
- Pest: grant → clerk assigns proctor; revoke; HR/registrar denied; HR sees no TA actions; catalog lock; clerk↛counselor/admin (edit/deactivate); clerk↛peer; clerk↛support-password; counselor→clerk password; HR→lead; self flags; profile page.

## Verification

```text
PersonnelLifecycleTest (26) + StaffNavigationTest + TaCapabilityDelegationTest
+ ExamCapabilitySyncTest + ExamProctorDutyAssignTest
→ 30 passed (214 assertions, staffing suite); full suite green except two pre-existing
  failures (AdmissionFlowTest attendance idempotency 403, NexiFoundationSessionTest
  degraded dock) — confirmed failing on baseline without these changes
```

Access model: all staff see the Staff directory (own office unless HR); support-staff creation by office staff (role derived from office); role reassignment HR-only; rank hierarchy + password reset ceilings enforced server-side; only `guidance.ta.delegate` holders get TA Grant/Revoke.
