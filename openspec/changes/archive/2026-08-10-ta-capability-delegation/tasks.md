# Tasks: TA Capability Delegation

## A — Activate

- [x] A1 Archive proctor-duty PASS; open this OpenSpec; update pointers
- [x] A2 Add `guidance.ta.delegate` to catalog + counselor seed

## B — Actions

- [x] B1 Eligible-recipient helper
- [x] B2 Grant / Revoke Actions + audit

## C — UI (unified Staff page)

- [x] C1 Personnel index open to all staff (office-scoped), read-only for non-managers
- [x] C2 TA status column + inline grant/revoke (delegate holders only)
- [x] C3 Single Staff nav item for all staff; remove standalone Test Administrators page/routes
- [x] C4 Denied/empty states (viewers see no management buttons)

## D — Proof

- [x] D1 Pest: personnel page TA column; grant → clerk assigns proctor; revoke; deny HR/registrar; catalog lock
- [x] D2 Form Request refactor: PersonnelController slim; no inline validation
- [x] D3 HANDOFF + evidence

## E — Rank hierarchy + password + profile (founder lock 2026-08-10)

- [x] E1 `StaffRoleCeiling`: `ROLE_RANKS`, `rankOf`, `canManageUser`, `canResetPassword`
- [x] E2 All staffing actions enforce `canManageUser`; `UpdateStaffAssignment` enforces `canResetPassword`
- [x] E3 `PersonnelController::edit` 403 on equal/higher rank; index emits `is_self`/`can_manage_user` per row
- [x] E4 Staff page own row: disabled actions + **Manage Profile**; Edit page hides password unless resettable
- [x] E5 `/staff/profile` read-only profile page (open to all staff)
- [x] E6 Pest: clerk↛counselor/admin (edit/deactivate), clerk↛peer, clerk↛support-password, counselor→clerk password, HR→lead, self flags, profile page
