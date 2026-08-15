# HANDOFF: Grading Roster Eligibility

**Shipped 2026-08-11** — attendance + submission now gate the grading roster; close is guarded on attendance completeness.

## Demo path

- Staff login: `guidance.counselor@nexiam.test` / password from `StaffIdentitySeeder` (run `foundation:reset --force` then `db:seed --class=GradingDemoSeeder`).
- Recreate: `docker compose exec app php artisan db:seed --class=Database\\Seeders\\GradingDemoSeeder`.
- Expected state: `EX-DEMO-1` completed; applicant A present + submitted (pre-scored), applicant B absent.
- URLs:
  - `/grading` → dashboard shows `EX-DEMO-1` with `1 absent excluded`.
  - Open `EX-DEMO-1` → session page stats: `assigned 2 / present & submitted 1 / absent 1`; only the present applicant is in the scoring roster; finalize succeeds with scores for the present applicant only.
  - Attempting to close an exam session with unrecorded attendance is rejected (`Attendance is not recorded for N assigned applicant(s).`).

## Seeder / reset

`GradingDemoSeeder` records `present` attendance + submission for applicant A and `absent` for applicant B before closing the session. Idempotent: re-run when `EX-DEMO-1` already has a grading session is a no-op.
