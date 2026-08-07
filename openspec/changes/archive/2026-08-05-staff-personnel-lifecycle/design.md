# Design: Staff Personnel Lifecycle

## Commands (`App\Staffing\Actions`)

| Command | Effect |
|---|---|
| `CreateStaffAccount` | User + StaffProfile(active) + role helpers within ceiling + audit `hr.staff.created` |
| `UpdateStaffAssignment` | name/email/optional password/office/program_codes + audit `hr.staff.updated` |
| `AssignStaffRole` / `RevokeStaffRole` | Ceiling-checked Spatie helper sync pieces; deny TA/Proctor; no self-escalation |
| `DeactivateStaffAccount` / `ActivateStaffAccount` | status toggle; refuse self; refuse last HR manager deactivate |

## Authz

- `hr.staff.manage` — full personnel
- `office.support_staff.manage` — office-limited support create/list
- `StaffRoleCeiling` — server allowlist (never client-only)
- Middleware `personnel.access` — either capability

## Schema

`staff_profiles.status` (`active`|`inactive`), `deactivated_at` nullable.

## UI

`/staff/personnel` Index/Create/Edit — management-list chrome; `StaffLayout` (writable) with server-authored nav from Inertia share.
