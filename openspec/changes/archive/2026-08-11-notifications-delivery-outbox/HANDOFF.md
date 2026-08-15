# HANDOFF: Notifications + Delivery Outbox

**Not yet shipped** — fill after implementation.

## Demo path

- Staff login: `guidance.counselor@nexiam.test` / password from `StaffIdentitySeeder` (via `foundation:reset --force`).
- Applicant login: access key from `config/seed.php` (e.g. `DEMO-ACPT`) + birthdate.
- Recreate: `docker compose exec app php artisan db:seed --class=NotificationDemoSeeder`.
- Expected state: seeded delivered/unread + read deliveries in the staff and applicant bells; unread badge counts reflect only own rows.
- URLs: any staff page (bell in header), `/applicant/schedule` (applicant bell in header); `/notifications` returns the own-data list.

## Seeder / reset

- Seeder: `NotificationDemoSeeder` (or `GradingDemoSeeder` extension) creates deliveries for the demo staff + accepted applicant.
- Reset: `docker compose exec app php artisan migrate:fresh --seed` or `foundation:reset --force` + targeted seeder.
