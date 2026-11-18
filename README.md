# NEXIAM

NEXIAM is an admissions and examination-management application. The executable application is in [`system/`](system/): Laravel 13, PHP 8.4, Inertia 3, Svelte 5, TypeScript, Tailwind 4 and PostgreSQL 16.

The source on `master` includes application code, migrations, seeders and required seed data, source assets, tests, dependency lockfiles and Docker Compose configuration. Model weights, real credentials, databases, uploaded files and generated frontend bundles are not distributed in Git.

## Start here

Read the **[application setup and operations guide](system/README.md)** for:

- Prerequisites and fresh-machine installation
- Docker Compose runtime and environment configuration
- Safe local seeding and synthetic demo access
- Tests and a private production build
- Web Push keys, optional Google/SMTP setup and external TabPFN model provisioning
- Deployment, backup and recovery boundaries

Do not run setup, migrations or seeders against an existing deployment just to inspect this repository.

## Repository layout

| Path | Purpose |
| --- | --- |
| `system/` | Current application, migrations, seeders, assets and tests |
| `openspec/` | Change specifications and implementation handoffs; publication scope is recorded in [publish-current-system-source](openspec/changes/publish-current-system-source/HANDOFF.md) |
| `docs/` | Product, architectural and historical project documentation |
| `legacy_codebase/` | Legacy reference application; not the current runtime |
| `manuscript/` | Research manuscript material; not an application dependency |

## Publication is not deployment

This source snapshot was prepared in an isolated checkout as a new descendant of the existing GitHub `master`. Existing GitHub history and the separate encrypted backup branch are preserved; unpublished local development history containing oversized model files was not imported.

The running system was not changed or redeployed. Its selective frontend asset release may differ from a fresh build of this source. In particular, this source requires explicitly configured Web Push VAPID keys: **before deploying over an existing installation, preserve its existing key privately and configure it for the app and workers**. Generating a replacement key invalidates the identity expected by existing browser subscriptions.

Publication checks and their limits are recorded in the [source publication handoff](openspec/changes/publish-current-system-source/HANDOFF.md). A successful source push is not production-readiness, physical OMR qualification, licensed-model approval, or a full database backup.

## Secrets and data

Copy `system/.env.example` for a new local installation; never commit the resulting `.env`. Example/demo credentials and local transport secrets are deliberately nonproduction values. Replace them before any shared or public deployment. Never publish real applicant records, private keys, database dumps, model access tokens or storage backups.

The existing GitHub Actions quality workflow targets the legacy reference application, not `system/`. It does not certify current-source correctness. No workflow in this publication deploys the NEXIAM application; the separate Pages workflow is scoped to changes under `capstone/`, which this publication does not modify.

## Daily workflow (issue to PR)

Follow this sequence for every authorized code or documentation change. Permanent engineering rules live in [`docs/project-context.md`](docs/project-context.md) and [`docs/GREENFIELD-MASTER-REFERENCE.md`](docs/GREENFIELD-MASTER-REFERENCE.md); this section defines the day-to-day branch, test-target, PR, and emergency mechanics.

1. **Locate the issue and governing spec.**
   - Inspect your assigned GitHub Issue (`gh issue view <number> --comments`) for scope, allowed paths, and blockers (`Blocked by: #<n>`). Issues organize work and dependencies; they do not authorize implementation on their own.
   - Open the single owning OpenSpec change under `openspec/changes/<change>/` (`proposal.md`, `specs/`, `design.md`, `tasks.md`, `HANDOFF.md`) and confirm the task is active and unblocked.
   - Treat `legacy_codebase/` and `docs/legacy-reference/` as **read-only evidence** unless independently authorized.

2. **Create a bounded worktree off the pinned base.**
   - Do not edit `master` directly, copy live mounted trees, or merge unpublished local workstation history ([source-publication boundary](openspec/changes/publish-current-system-source/HANDOFF.md)).
   - Before editing, record `git rev-parse HEAD`, `git status --short`, `git worktree list --porcelain`, your assigned paths, and your intended disposable test target.
   - Branch in a separate worktree off the integration lead's pinned clean ref (typically `origin/master`):

     ```sh
     git worktree add -b <type>/<slice-name> ../NEXIAM-<slice-name> <pinned-ref-or-sha>
     ```

   - Never `reset`, `stash`, `prune`, blanket-stage (`git add -A` / `git add .`), or clean another worktree. Stage only your assigned files selectively.
   - Pushing branches, changing GitHub settings, tagging releases, and touching any live or VPS runtime each require separate explicit authorization.

3. **Choose the right disposable test target.**
   - Never run migrations, seeders, or test suites against a live, shared, or production database/storage path.
   - **PHP unit/feature suite (SQLite `:memory:`):** `system/phpunit.xml` forces isolated in-memory SQLite (`php artisan test --compact` inside `system/`).
   - **PostgreSQL-sensitive and concurrency suites:** run against a disposable Compose project with an isolated `COMPOSE_PROJECT_NAME` and non-colliding host ports/volumes (note `system/compose.yaml` defaults `name: nexiam-system` and host ports `18000`, `5432`, `18025` — override ports or run isolated one-off containers so you never collide with a running stack), passing `NEXIAM_TEST_PIN_SQLITE=0` with the scoped config (such as `system/phpunit.pg.xml` or `system/phpunit.*-postgres.xml`).
   - **PHP formatting and frontend checks:** run inside `system/` (`vendor/bin/pint --test --format agent`, `npm ci`, `npm run quality`, `npm run build`).
   - If optional local Git hooks are introduced later, keep them versioned, opt-in, and fast; hosted GitHub Actions checks remain authoritative.

4. **Open a reviewed PR and hand off.**
   - Complete every section of [the default PR template](.github/PULL_REQUEST_TEMPLATE.md). Link evidence (issue, OpenSpec `HANDOFF.md`, hosted CI run URL) instead of duplicating logs across docs.
   - One integration lead owns integration order and canonical handoff updates.
   - Close an issue only when its acceptance evidence exists and is linked—never solely because an agent reported completion.

## Emergency hotfix and break-glass protocol

Normal work flows through a reviewed PR from an isolated worktree. If an urgent production incident requires an emergency path:

1. **Explicit authorization:** Obtain explicit operator/founder authorization naming the exact target, maintenance window, and approved scope. Never self-authorize or give agents blanket bypass.
2. **Record incumbent and delta:** Capture the exact incumbent identity (release ID, commit/source SHA, manifest hash, image digests) and the bounded hotfix delta before making changes.
3. **Validate and confirm rollback:** Verify the hotfix on an isolated target where feasible, confirm a paired recovery/rollback path before activation, and preserve current runtime data and keys.
4. **Reconcile before the next release:** Open and merge a reconciliation PR to `master` recording the exact applied delta, verification results, and updated handoff before any subsequent ordinary release.
5. **Accountable break-glass bypass:** Only an authorized GitHub maintainer/operator may invoke a branch-protection or review bypass. Record the explicit **reason**, **scope**, and **expiry** in the owning issue/handoff, and restore normal enforcement immediately when the window closes.
