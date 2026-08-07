# E1 Identity & Reference Foundation — Evidence Report

**Document type:** E1 identity/reference evidence  
**Result:** PASS (automated + live Compose spot-check)  
**Captured:** 2026-08-04  
**Workspace:** `/home/user/Projects/NEXIAM` (`system/` runtime)  
**OpenSpec change:** `openspec/changes/e1-identity-reference-foundation`

This report is **E1 evidence**. It does **not** claim E2 journey readiness, pilot, or production clearance.

## 1. Spatie Permission spike

| Item | Result |
| --- | --- |
| Package | `spatie/laravel-permission` **8.3.0** |
| Decision | **GO WITH CONDITION** — persistence/helper only |
| Gate auto-registration | `register_permission_check_method => false` |
| Decision authority | `App\Authorization\CapabilityAuthorizer` (GF-21) |
| Spike note | `openspec/changes/e1-identity-reference-foundation/spatie-permission-spike.md` |

## 2. Identity / capability / scope seams

| Seam | Location |
| --- | --- |
| Staff login/logout | `/login`, `/logout`, Inertia `Auth/Login`, `Staff/Home` |
| Synthetic staff | `registrar.clerk@nexiam.test`, `guidance.counselor@nexiam.test`, `hr.admin@nexiam.test` (password `password`) |
| Capability catalog | `App\Authorization\CapabilityCatalog` + `CapabilityCatalogSeeder` |
| Actor scope | `StaffProfile` + `ActorScope` (office / program_codes / active_cycle_code) |
| Middleware | `capability:` → `EnsureCapability` (deny + audit; not legacy role middleware) |
| Shared Inertia auth | `HandleInertiaRequests` shares user, capabilities, scope |

## 3. Reference catalogs (I4)

Seeded via `ReferenceCatalogSeeder` after E0 institution/cycle:

| Catalog | Synthetic codes |
| --- | --- |
| Programs | `BSN`, `BSPSY` |
| Rooms | `RM-101`, `RM-LabA` |
| Aptitude areas | `VERBAL` v1, `QUANT` v1 |
| Application window | `SYN-WINDOW-2026-A` (`published`) |

Migrations: `programs`, `rooms`, `aptitude_areas`, `application_windows`.

## 4. Quality evidence

| Gate | Result |
| --- | --- |
| Pest 5 | **24 passed** (95 assertions) |
| Pint | passed |
| PHPStan/Larastan | OK (level 5; 28 files) |
| Vitest | previously green (`npm run test`) |
| Typecheck | previously green (`npm run check`) |

### Denial / default-deny

- `tests/Feature/Authorization/CapabilityAuthorizerTest.php` — allow, default-deny, middleware deny + audit
- Probe route: `foundation.capability-probe` guarded by `capability:foundation.probe`

### Staff auth

- `tests/Feature/Auth/StaffAuthenticationTest.php` — login page (Inertia), authenticate, invalid credentials, logout + login audit event
- Test harness notes:
  - CSRF middleware disabled in `Tests\TestCase` for feature posts
  - `INERTIA_DEVTOOLS_ENABLED=false` so `assertInertia` retains View original
  - `config/inertia.php` pages path: `resources/js/Pages`

### Scope / catalogs

- `tests/Feature/Authorization/ActorScopeTest.php`
- `tests/Feature/ReferenceCatalogTest.php`

## 5. Guardrails confirmed

- No legacy role middleware reintroduced as authorization authority
- Spatie roles are assignment helpers only
- E2 journey commands/pages not started

## 6. Live Compose verification (2026-08-04)

Runtime note: Compose `app`/`queue`/`scheduler` bake code into the image (no host bind for PHP). After E1 code landed, rebuild + recreate was required before routes appeared.

| Check | Result |
| --- | --- |
| `php artisan foundation:reset --force` against Compose Postgres | PASS (migrations + seeders) |
| Rebuild `nexiam-system-app` + recreate app/queue/scheduler | PASS |
| `GET /health` `:18000` | **200** — database/storage/mail/queue configured |
| `GET /login` | **200** — Inertia login + CSRF |
| `POST /login` (`registrar.clerk@nexiam.test` / `password`) | **302** → `/staff` |
| `GET /staff` (session) | **200** — Staff Home |
| Anonymous `GET /foundation/capability-probe` | **302** → `/login` |
| Capability deny matrix | Covered by Pest (`CapabilityAuthorizerTest`); seeded synthetic staff currently hold `foundation.probe`, so live mid-session deny on that probe is not the primary live proof |

## 7. Exit

I1–I5 complete (automated + live Compose). **E1 PASS** for identity/reference foundation. Unlocks planning for `e2-connected-admission-release-slice` only after BLOCK-01..04 (PRD/stories/UX/legacy parity) — not pilot/production, and not broad E2 coding yet.
