# Staff Personnel Lifecycle — Evidence

**Date:** 2026-08-05  
**OpenSpec:** `openspec/changes/staff-personnel-lifecycle/`  
**Result:** PASS (automated Pest + Compose rebuild)

## What landed

| Surface | Path |
|---|---|
| List / create / edit | `/staff/personnel` |
| Commands | `App\Staffing\Actions\*` |
| Ceiling | `App\Staffing\Support\StaffRoleCeiling` |
| Schema | `staff_profiles.status`, `deactivated_at` |
| Capability | `hr.staff.manage`, `office.support_staff.manage` |
| Role key | `hr_administrator` (locked) |

## Pest

```text
tests/Feature/Staffing/PersonnelLifecycleTest.php
→ 9 passed (42 assertions)
```

Covers: HR index, clerk deny, HR create, forbidden role, registrar support ceiling, deactivate blocks login / activate restores, self-deactivate deny, command ceiling, password confirmation.

## Live Compose

1. Rebuild `app` / `queue` images  
2. `docker compose up -d --force-recreate app queue`  
3. `php artisan migrate --force` + seed (or `foundation:reset --force`)  
4. Login `hr.admin@nexiam.test` / `password` → `/staff/personnel`

## Non-goals remaining

TA/Proctor grant UI, invite email, Google OAuth, Registrar setup CRUD, E2 G2–G4 exit.
