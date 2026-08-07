# E0 Scaffold Evidence Report

**Document type:** E0 scaffold evidence  
**Result:** PASS (conditional on continuing E1 as a separate OpenSpec change)  
**Captured:** 2026-08-04  
**Workspace:** `/home/user/Projects/NEXIAM` (`system/` runtime)  
**Source identity:** `5ba9036` on `master` (dirty worktree; greenfield/runtime changes not yet committed)

This report is **E0 scaffold evidence**. It does **not** claim E1/E2, pilot, or NEXIAM production readiness.

## 1. Runtime and package versions

| Component | Version / pin |
| --- | --- |
| PHP | 8.4.24 |
| Laravel | v13.23.0 |
| Inertia Laravel | v3.2.1 |
| React | ^19.2.0 |
| `@inertiajs/react` | ^3.6.1 |
| TypeScript | ~5.9.3 |
| Vite | ^7.0.7 |
| Tailwind | ^4.0.0 |
| Pest | v5.0.3 |
| Pest Laravel / Arch / Browser | v5.0.1 / v5.0.0 / v5.0.0 |
| Vitest | ^4.1.10 |
| Playwright | ^1.62.1 |
| PostgreSQL | 16.14 (compose `postgres:16-alpine`) |
| Node (host tooling) | v22.23.2 |
| Docker image | PHP 8.4 CLI + `pcntl`, `pdo_pgsql`, `pdo_sqlite`, `sockets`, `zip`; Composer require-dev retained |

## 2. Compose service health

All primary services healthy after recreate:

- `postgres` healthy
- `mailpit` healthy
- `app` healthy (`http://127.0.0.1:18000/up` → 200)
- `queue` healthy
- `scheduler` healthy

`/health` after `foundation:reset --force`:

```json
{"status":"ok","database":"ok","private_storage":"ok","mail":"configured","queue":"configured_after_commit"}
```

Optional `inference` profile was not started; core readiness does not claim provider success.

## 3. Migration / seed / reset

Command: `php artisan foundation:reset --force`

- Dropped/recreated greenfield PostgreSQL schema only
- Ran migrations including foundation tables (`institutions`, `academic_cycles`, `operations`, `audit_events`)
- Seeded synthetic `SYN-INST-001` / `SYN-CYCLE-2026`
- Cleared private storage under the greenfield app disk

## 4. Quality evidence

| Gate | Result |
| --- | --- |
| `docker compose config` | OK |
| `docker compose build` | OK (sockets + temporary `ext-sockets` ignore in vendor stage; require-dev present) |
| Pest 5 | **11 passed** (failed-op terminal visibility included) |
| Pint | passed |
| PHPStan/Larastan | OK (app + routes, level 5) |
| `npm run build` | OK |
| `npm run quality` (tsc/eslint/prettier/vitest) | OK (1 Vitest test) |
| Playwright | **2 passed** against Compose `:18000` |

Pest plugins present: Laravel, architecture, browser. Playwright remains the connected browser authority; Pest Browser stays supplementary (`PEST_BROWSER_ENABLED=false` in phpunit.xml).

## 5. Operation / queue / audit evidence

- Database queue configured with `after_commit=true`
- Demo command `CommitFoundationDemo` with idempotency key `e0-evidence-1`:
  - operation id `019fcb6e-2ae1-721d-a815-343d62470b28`
  - initial status `pending`, then worker processed to `succeeded` (`attempts=1`)
- Private storage artifact written with checksum  
  `af0eca58b42747f218311508b72dc02cd8e24d1639bcb7f69eda299930990216`
- Audit chain heads observed:
  - `foundation.seed_applied`
  - `foundation.operation_committed`
  - `foundation.operation_succeeded`  
  each with linked `prev_hash` / `event_hash`

## 6. Mailpit evidence

- Mailpit API reachable at `http://127.0.0.1:18025/`
- Sent synthetic message subject `E0 foundation mail evidence` to `evidence@nexiam.test`
- Mailpit listed 1 captured message (delivery is test evidence only, not domain authority)

## 7. Restore rehearsal

- `pg_dump -Fc` of greenfield DB (~26 KB) + private-storage tar (~10 KB)
- Dump validated via `pg_restore --list` (PostgreSQL 16.14 TOC includes foundation tables)
- Measured wall-clock for dump+storage archive on this laptop-scale dataset: **< 1 second**
- Limitation: rehearsal proves dump/list + continued `/health`; it is not a full disaster-recovery runbook or timed restore-into-empty-cluster drill

## 8. Architecture boundaries

Pest architecture suite asserts:

- HTTP controllers do not use `DB` transactions (HealthController ignored for readiness probes only)
- Models do not import HTTP types
- Application code does not reference a `Legacy` namespace

## 9. Known limitations / next gate

- E0 does **not** implement identity/RBAC (E1) or the admission-to-release vertical slice (E2)
- Spatie Permission evaluation remains an E1 compatibility spike only
- Health distinguishes configured mail/queue seams; Mailpit SMTP accept counts are evidence artifacts, not product KPIs
- Dirty worktree: evidence paths and code should be committed before treating the gate as permanently archived

## 10. Gate decision

**E0 PASS** for scaffold/runtime/quality/operation evidence.

Next allowed OpenSpec change: `e1-identity-reference-foundation`.  
E1/E2, pilot, and production remain **NO-GO** until their own PASS evidence exists.
