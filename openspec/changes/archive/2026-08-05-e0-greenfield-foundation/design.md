# E0 Greenfield Foundation Design

## 1. Boundary

The E0 workspace is a clean implementation target. The current NEXIAM tree remains the legacy/reference workspace. No E0 runtime code may import from, connect to, or read the legacy application database, storage, routes, controllers, migrations, or provider configuration.

The approved foundation documents are copied or committed into the selected greenfield workspace as planning inputs. They are not a runtime package.

## 2. Runtime shape

```text
web/app ───────┐
queue-worker ──┼── PostgreSQL authority
scheduler ────┘         │
                       ├── private storage
                       ├── Mailpit (local/test)
                       └── optional authenticated Python adapter seam
```

- `web/app`, `queue-worker`, and `scheduler` use the same application image/build.
- PostgreSQL is the greenfield production authority from the first migration.
- Laravel database queue is the initial queue; all side-effect dispatch is after commit.
- Private storage is not public web storage.
- Mailpit is local/test evidence only; production mail is an adapter decision.
- The Python worker seam is optional in E0 and must not write authoritative domain state.

## 3. Version and quality boundary

Pin the versions recorded by D-055/D-056 and the selected PostgreSQL major. The current legacy `composer.json`, `package.json`, `phpstan-baseline.neon`, and current test files are evidence, not files to copy blindly.

Greenfield quality authority:

- PHP 8.4;
- Laravel 13;
- Inertia 3;
- React 19;
- TypeScript 5.9;
- Tailwind 4;
- Node 24 LTS;
- Pest 5 on PHPUnit 13;
- Pest Laravel, architecture, and browser plugins;
- Vitest;
- Playwright;
- Pint and PHPStan without inherited error baselines;
- ESLint and Prettier scoped to source.

## 3A. Compatible-stack and library policy

The target stack is Laravel 13/PHP 8.4, Inertia 3, React 19, TypeScript 5.9, Tailwind 4, Node 24 LTS, PostgreSQL 16, and Pest 5 on PHPUnit 13. Laravel Boost 2.4 is enabled for codebase-aware development assistance and MCP tooling, but generated suggestions remain subordinate to GF-13/GF-17–GF-23 and the active OpenSpec change.

Spatie Laravel Permission is an evaluated helper, not the authorization authority. It may provide compatible persistence/role-grant conveniences after a focused compatibility review, but GF-21 owns effective capability resolution, office/program/cycle scope, contextual Proctor duty, break-glass allowlists, default-deny enforcement, audit evidence, and sensitive-action checks. No package may silently reintroduce the legacy role-middleware model. The corrected target terminology is capability/resource-scope authorization enforced by policies and owning commands; middleware may establish request context or coarse authentication, but cannot be the sole authorization decision.

Every dependency added after E0 must name its contract seam, Laravel/PostgreSQL compatibility, security/privacy impact, upgrade/exit path, and test evidence. Do not add provider SDKs, RAG/OMR libraries, Redis, vector stores, or generic repositories to E0 without an approved OpenSpec/ADR.

## 4. E0 modules

| Module | E0 responsibility | E0 boundary |
|---|---|---|
| Runtime | Compose, environment validation, health/readiness, storage mount | No domain feature state |
| Persistence foundation | PostgreSQL connection, initial migration convention, deterministic seed/reset | No legacy schema import |
| Queue/operations | Database queue, worker/scheduler process, operation status seam | No feature-specific business jobs |
| Audit foundation | Minimal attributable event/chain-head seam required for E0 evidence | Full connected event vocabulary remains GF-21/GF-20 implementation work |
| Test foundation | Pest/Vitest/Playwright discovery and fixtures | No empty-test pass hidden behind “passWithNoTests” |
| Architecture guard | Dependency direction and provider write prohibition | No ceremonial abstractions |
| Developer experience | setup/check/test/build/health/reset commands | No environment-specific secrets in source |

## 5. Data and seed rules

The E0 seed must be deterministic and synthetic. It may include:

- one synthetic institution/configuration record;
- one academic cycle/window;
- minimal program, room, aptitude-area, and template reference data;
- named synthetic staff capability fixtures only when credentials are injected safely for tests;
- no real applicant names, emails, identifiers, passwords, tokens, scans, or result artifacts.

The reset command must remove and recreate only the greenfield database/storage. It must never call the legacy database or delete outside the selected greenfield environment.

## 6. Health and failure contract

The E0 health/readiness result must distinguish:

- process alive;
- configuration valid;
- PostgreSQL reachable and schema-compatible;
- private storage writable/checkable;
- queue worker running;
- scheduler heartbeat observed;
- Mailpit/test adapter reachable;
- optional Python adapter absent versus degraded versus healthy.

If a dependency is unavailable, readiness must identify the dependency and the affected capability. It must not report a generic green status.

## 7. Architecture tests

At minimum, E0 tests must fail if:

- an HTTP controller directly owns a transaction or provider call instead of invoking an application boundary;
- a domain/model class imports HTTP request/response concerns;
- a provider adapter writes authoritative domain tables directly;
- a legacy migration or legacy database connection is referenced by greenfield runtime code;
- a queue side effect is dispatched before the transaction commits;
- a test suite passes while no real test is discovered.

The exact static-analysis implementation is an E0 task decision; the boundary is not optional.

## 8. E0 evidence report

The implementation must produce a versioned report containing:

- source commit/worktree identity;
- runtime/package versions;
- Compose service status and health output;
- fresh migration and deterministic seed/reset output;
- backend/frontend/browser test discovery and result counts;
- Pest (Laravel/architecture/browser plugins), Pint/PHPStan/ESLint/Prettier/build output;
- queue operation and retry evidence;
- Mailpit captured message evidence;
- private storage checksum/retention evidence;
- database-plus-files restore rehearsal measurement;
- known limitations and next gate.

The report must say “E0 scaffold evidence” and must not say “NEXIAM production ready.”

## 9. E0 exit behavior

On success, E0 unlocks the OpenSpec change `e1-identity-reference-foundation`. On failure, the issue is repaired in E0; feature code is not started as a workaround.
