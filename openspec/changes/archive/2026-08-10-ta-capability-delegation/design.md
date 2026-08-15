# Design: TA Capability Delegation

## Vocabulary

| Term | Meaning |
|---|---|
| Test Administrator | D-016 capability **set** — `testAdministratorCapabilities()` |
| Inherent TA | Counselor role helper grants the full set via `roleHelperGrants` |
| Delegated TA | Clerk/support receive the same set as **direct** Spatie permissions on the user |
| Delegate authority | `guidance.ta.delegate` — **outside** the TA set so delegates cannot chain-grant |

## Controller conventions (thin controllers) — founder correction 2026-08-10

Legacy's worst smell was fat controllers with inline validation and role branching. Greenfield rule:

1. **Form Requests own validation** — one request class per write shape (`app/Http/Requests/Staff/*`), including conditional HR/office rules. No inline `$request->validate()` in controllers.
2. **Domain resolution in support classes** — role/office derivation lives in `StaffRoleCeiling`, not the controller.
3. **Actions own writes** — `CreateStaffAccount`, `UpdateStaffAssignment`, `SyncStaffRoles`, `Grant/Revoke*` in `Staffing\Actions`.
4. **Controllers delegate** — read projections + action calls only; target < ~120 lines; no business branching.

This OpenSpec: `StoreStaffAccountRequest` / `UpdateStaffAccountRequest` added; `PersonnelController` slimmed (inline validation removed, branching moved to `StaffRoleCeiling::resolveCreate*/resolveUpdate*`).

## Access model (founder correction 2026-08-10)

- **View:** every authenticated staff member can open `/staff/personnel` and see the office directory — office-scoped unless full HR. No `EnsurePersonnelAccess` on `GET /staff/personnel`.
- **Account actions** (Create/Edit/Deactivate/Activate): only personnel-capable actors (`hr.staff.manage` or `office.support_staff.manage`).
- **Support-staff creation is an office function** (founder correction 2026-08-10): registrar clerk and guidance clerk also hold `office.support_staff.manage`. Created role is **derived from office** (`registrar_support_staff` / `guidance_support_staff`) — no role checklist for office actors.
- **TA actions** (Grant/Revoke): only `guidance.ta.delegate` holders.
- **Role reassignment** (permanent helpers): HR only. Office actors never see a role checklist; support-staff roles are not selectable anywhere.

## Rank hierarchy (founder lock 2026-08-10)

Office rank: **support (1) < clerk (2) < office lead (3: registrar_admin / guidance_counselor) < HR (4)**.

- `StaffRoleCeiling::canManageUser` — an office actor may manage only **strictly-lower** ranks **inside their own office**; peers and higher ranks are out of reach; HR may manage everyone.
- Every staffing action enforces it server-side (`CreateStaffAccount` office lock, `UpdateStaffAssignment`, `DeactivateStaffAccount`, `ActivateStaffAccount`, `AssignStaffRole`, `RevokeStaffRole`, `SyncStaffRoles`).
- `PersonnelController::edit` returns 403 for equal/higher-rank targets.
- Self is never a directory action: self-edit/self-deactivate are blocked and belong to the **profile page** (follow-up cut).

## Password reset (founder lock 2026-08-10)

- **HR** may reset any staff password; **office leads** (rank 3) may reset passwords for own-office lower ranks. Clerks/support never reset another account's password.
- `StaffRoleCeiling::canResetPassword` enforced in `UpdateStaffAssignment`; Edit page hides the password field unless the actor may reset.
- Self-service password change lands on the **profile page** (follow-up cut).

## Profile page (founder lock 2026-08-10)

- New `/staff/profile` (`ProfileController` → `Staff/Profile/Show`), read-only for now, open to all staff.
- Staff page own row: Edit/Deactivate disabled with tooltip + **Manage Profile** button → `/staff/profile`.

## Why a unified Staff page

Personnel ceilings (D-020) let office staff create their own support accounts; HR creates everyone. Clerks are permanent helpers managed by HR; they are office leads for onboarding. The TA overlay must not expand that ceiling or invent a TA role — so it folds into the existing Personnel page as a per-row **Test Administrator** column, visible only to `guidance.ta.delegate` holders.

Page: `/staff/personnel` (single **Staff** nav item for all staff).

## Authz path

```text
Request → CapabilityAuthorizer(guidance.ta.delegate)
  → GrantTestAdministratorCapabilities / RevokeTestAdministratorCapabilities
  → givePermissionTo / revokePermissionTo(testAdministratorCapabilities())
  → audit staff.ta_capabilities_*
```

Revoke leaves role-helper base caps intact (Spatie role grants remain).

## Eligible recipients

1. Active `StaffProfile`, office = `guidance`  
2. Role helper in `{guidance_clerk, guidance_support_staff}`  
3. Not the actor themselves  

## Files

```text
CapabilityCatalog                     + GUIDANCE_TA_DELEGATE; OFFICE_SUPPORT_STAFF_MANAGE on clerks
Http/Requests/Staff/Store|UpdateStaffAccountRequest   Form Requests (validation)
Staffing/Actions/Grant|Revoke…        Actions
Staffing/Support/EligibleTaRecipients ta_status / canDelegate
Staffing/Support/StaffRoleCeiling     permanent vs office support + resolveCreate/UpdateRoles
Http/Controllers/Staff/PersonnelController  thin: projections + action delegation
Http/Controllers/Staff/TestAdministratorController  grant/revoke only
Pages/Staff/Personnel/Index.svelte    TA column + inline buttons (gated)
Pages/Staff/Personnel/Create.svelte   office-support auto role
Pages/Staff/Personnel/Edit.svelte     HR-only role checklist; support read-only
routes/web.php                        GET index open; grant-ta/revoke-ta gated
StaffNavigationBuilder                Staff item for all staff
```
