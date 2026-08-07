# Legacy → Target Trace: Staff Personnel Lifecycle

**Disposition authority:** greenfield GF-04/D-014–D-020; legacy is evidence only.  
**OpenSpec:** `openspec/changes/staff-personnel-lifecycle/`

| Field | Value |
|---|---|
| Legacy source path(s) | `UserController`, `StoreUserRequest`, `UpdateUserRequest`, `Admin/Users/{Index,Create,Edit}.svelte`, `routes/web.php` (`role:super_admin` users resource) |
| Observed actor / scope | Legacy: `super_admin` only |
| Visible states | list empty/filtered; validation; delete confirm; success toast |
| Disposition | **PRESERVE** list/filter/table+cards/create-edit form feel; **REWRITE** authz (CapabilityAuthorizer), role catalog (D-014), deactivate-not-delete, server ceilings (D-020), audit events, password confirmed |
| FR/BR IDs | FR-076; BR-012; CAP-029/030 |
| Target command/query + page | `CreateStaffAccount`, `UpdateStaffAssignment`, `AssignStaffRole`, `RevokeStaffRole`, `DeactivateStaffAccount`, `ActivateStaffAccount`; `/staff/personnel` |
| Capability + scope | `hr.staff.manage` (full); `office.support_staff.manage` (office support only) |
| Transaction / idempotency / audit | Single DB transaction per command; audits `hr.staff.*` |
| Recovery | Activate restores login; no hard delete in v1 |
| Evidence | `tests/Feature/Staffing/PersonnelLifecycleTest.php`; Compose `/staff/personnel` |
| OpenSpec | `staff-personnel-lifecycle` |

## Do not copy

Role middleware as authz; client-only role allowlist; missing password confirmation; hard delete; obsolete `admin`/`proctor`/`test_administrator` as permanent assignable roles via HR; empty roles sync.
