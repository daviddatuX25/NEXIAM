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

1. **Locate the governing plan, issue, and OpenSpec.**
   - **Governing guardrails plan:** [`openspec/changes/isolated-full-system-beta-deployment/REPOSITORY-GUARDRAILS-PLAN.md`](openspec/changes/isolated-full-system-beta-deployment/REPOSITORY-GUARDRAILS-PLAN.md) with [`tasks.md`](openspec/changes/isolated-full-system-beta-deployment/tasks.md) and [`specs/reproducible-demo-release/spec.md`](openspec/changes/isolated-full-system-beta-deployment/specs/reproducible-demo-release/spec.md). (`HANDOFF.md` and workstation reconciliation inventories remain held outside public `master` pending G1 secret/provenance review.)
   - **GitHub Issues:** inspect your assigned ticket with `gh issue view <number> --comments` per [`docs/agents/issue-tracker.md`](docs/agents/issue-tracker.md) for slice scope, allowed paths, and blockers (`Blocked by: #<n>`). Issues organize work and dependencies; they do not authorize implementation on their own.
   - **Owning OpenSpec:** open the single active change under `openspec/changes/<change>/` (`proposal.md`, `specs/`, `design.md`, `tasks.md`) and confirm the task is active and unblocked.
   - **Legacy boundary:** treat `legacy_codebase/` and `docs/legacy-reference/` as **read-only evidence** unless independently authorized.

2. **Create a bounded worktree off the lead-pinned ref.**
   - **Pinned clean public base:** `origin/master` (`4321e30b12a09722569bb4f3766bf8f84f633f07`). Do not edit `master` directly, copy live mounted trees, or merge unpublished local workstation history ([source-publication boundary](openspec/changes/publish-current-system-source/HANDOFF.md)).
   - Before editing, record `git rev-parse HEAD`, `git status --short`, `git worktree list --porcelain`, assigned paths, and intended disposable test target.
   - Create a separate worktree off the pinned ref:

     ```sh
     git worktree add -b <type>/<slice-name> ../NEXIAM-<slice-name> 4321e30b12a09722569bb4f3766bf8f84f633f07
     ```

   - Never `reset`, `stash`, `prune`, blanket-stage (`git add -A` / `git add .`), or clean another worktree. Stage only your assigned files selectively.
   - Pushing branches, changing GitHub settings, tagging releases, and touching any live or VPS runtime each require separate explicit authorization.

3. **Choose and verify a disposable test target.**
   - Never run migrations, seeders, or test suites against a live, shared, or production database or storage path.
   - **PHP unit/feature suite (SQLite `:memory:`):** `system/phpunit.xml` forces isolated in-memory SQLite (`php artisan test --compact` inside `system/`). Note that `pgsql`-gated acceptance tests skip under SQLite.
   - **Frontend & PHP formatting checks:** run inside `system/` (`vendor/bin/pint --test --format agent`, `npm ci`, `npm run quality`, `npm run build`).
   - **PostgreSQL-sensitive suites — disposable provisioning:**
     1. Create an untracked env file (`cp system/.env.example system/.env`; never commit `.env` or copy a live `.env`).
     2. `system/compose.yaml` defaults `name: nexiam-system` and host ports `18000`, `5432`, `18025`, `11025`. Isolate with a unique `COMPOSE_PROJECT_NAME=nexiam-test-<slice>`; if another stack already binds those host ports, also pass an untracked override file (e.g. `/tmp/nexiam-test-override.yaml` clearing `ports: !reset []` on `app`, `postgres`, `mailpit`):

        ```sh
        cat > /tmp/nexiam-test-override.yaml <<'YAML'
        services:
          app: { ports: !reset [] }
          postgres: { ports: !reset [] }
          mailpit: { ports: !reset [] }
        YAML
        COMPOSE_PROJECT_NAME=nexiam-test-<slice> docker compose \
          -f system/compose.yaml -f /tmp/nexiam-test-override.yaml up -d --wait postgres app
        ```

     3. **Verify effective database and storage before running** (clear cached config first, then inspect resolved Laravel bindings and Docker volumes):

        ```sh
        COMPOSE_PROJECT_NAME=nexiam-test-<slice> docker compose \
          -f system/compose.yaml -f /tmp/nexiam-test-override.yaml exec -T app sh -lc '
          php artisan config:clear >/dev/null
          php artisan tinker --execute '\''
            echo json_encode([
              "env" => app()->environment(),
              "default_db" => config("database.default"),
              "pgsql_host" => config("database.connections.pgsql.host"),
              "pgsql_db" => config("database.connections.pgsql.database"),
              "current_database" => DB::connection()->scalar("SELECT current_database()"),
              "local_root" => config("filesystems.disks.local.root"),
              "public_root" => config("filesystems.disks.public.root"),
            ], JSON_PRETTY_PRINT) . PHP_EOL;
          '\''
        '
        docker volume ls --filter "name=nexiam-test-<slice>"
        ```

        Confirm `current_database` is your disposable database (never a live/shared DB) and `local_root`/`public_root` resolve inside the disposable container volume, not a shared host custody mount.
     4. **Run the supported PostgreSQL command for the target suite:**
        - **OMR physical assurance (`phpunit.pg.xml`, 5 OMR files):** when `system/compose.yaml` host ports are free (clean CI runner or stopped local stack), run the repo wrapper from repo root:

          ```sh
          COMPOSE_PROJECT_NAME=nexiam-test-<slice> bash system/bin/omr-physical-assurance-pg-acceptance.sh
          ```

          This script fingerprints `nexiam`, creates a unique `nexiam_omr_acceptance_<utc>_<random>_<pid>` database (`TEMPLATE template0`), runs `migrate:fresh --seed --force` and `vendor/bin/pest --configuration=phpunit.pg.xml`, then drops the temporary DB and re-verifies the `nexiam` fingerprint on exit. (If host ports are occupied, run the same steps against `-f system/compose.yaml -f /tmp/nexiam-test-override.yaml` by creating `nexiam_omr_acceptance_<id>`, running `artisan migrate:fresh --seed --force` and `vendor/bin/pest --configuration=phpunit.pg.xml` with `DB_CONNECTION=pgsql DB_DATABASE=<temp_db> DB_HOST=postgres NEXIAM_TEST_PIN_SQLITE=0`, then dropping `<temp_db>`.)
        - **Scoped `phpunit.*-postgres.xml` suites:** each config enforces its own bootstrap guard (`tests/Support/*Bootstrap.php`) and fixed or patterned `DB_DATABASE`. Provision the required empty database in your disposable `postgres` container, clear any stale `/tmp/*-config.php` cache, and run Pest with that exact configuration:

          ```sh
          COMPOSE_PROJECT_NAME=nexiam-test-<slice> docker compose \
            -f system/compose.yaml -f /tmp/nexiam-test-override.yaml exec -T postgres \
            psql -U nexiam -d postgres -v ON_ERROR_STOP=1 -c 'CREATE DATABASE "<db>" TEMPLATE template0;'
          COMPOSE_PROJECT_NAME=nexiam-test-<slice> docker compose \
            -f system/compose.yaml -f /tmp/nexiam-test-override.yaml exec -T app \
            sh -lc 'rm -f /tmp/nexiam-messaging-postgres-acceptance-config.php /tmp/<db>-config.php && vendor/bin/pest --configuration=<config>'
          ```

          Supported configurations and their required `<db>` contracts:

          | Config | Suite | Required `<db>` & preconditions |
          | --- | --- | --- |
          | `phpunit.test-kit-postgres.xml` | `TestKitPostgresContractTest`, `TestKitHttpContractTest` | `nexiam_test_kit_acceptance_<unique>` (`<= 63` chars; pass `DB_DATABASE=<db>` and `NEXIAM_TEST_KIT_SOURCE_DATABASE=nexiam`; `/tmp/<db>-config.php` must not exist) |
          | `phpunit.admission-checklist-postgres.xml` | `AdmissionChecklistPostgresConcurrencyTest` | `nexiam_messaging_acceptance` (bootstrap creates/drops per-run schema + `/tmp/<schema>-storage` disks) |
          | `phpunit.messaging-postgres.xml` | `CaseConversationPostgresConcurrencyTest`, `RecordThreadSequenceMigrationTest` | `nexiam_messaging_acceptance` (`NEXIAM_TEST_FULL_REFRESH_EACH_TEST=1`; `/tmp/nexiam-messaging-postgres-acceptance-config.php` must not exist) |
          | `phpunit.omr-camera-postgres.xml` | `OmrCameraContentionPostgresTest` | `nexiam_omr_assisted_verify_20261019_a4c8` |
          | `phpunit.omr-page-disposition-postgres.xml` | `OmrDiagnosticQrDisabledBridgeTest` | `nexiam_test_kit_acceptance_omr_disposition_20261023` |

        - Tear down disposable resources after testing: `COMPOSE_PROJECT_NAME=nexiam-test-<slice> docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml down -v` and `rm -f /tmp/nexiam-test-override.yaml`.
   - If optional local Git hooks are introduced later, keep them versioned, opt-in, and fast; hosted GitHub Actions checks remain authoritative.

4. **Open a reviewed PR and hand off.**
   - Complete every section of [the default PR template](.github/PULL_REQUEST_TEMPLATE.md). Link evidence (issue, OpenSpec `HANDOFF.md`, hosted CI run URL) instead of duplicating logs across docs.
   - One integration lead owns integration order and canonical handoff updates.
   - Close an issue only when its acceptance evidence exists and is linked—never solely because an agent reported completion.

### Clean-room walkthrough (issue to PR)

1. **Inspect ticket and stop on open blockers:** `gh issue view 66 --comments` shows `#66` (`Blocked by: #62, #63, #64, #65`). A clean-room contributor does **not** start `#66` until `#62`–`#65` are closed (or selects an unblocked ticket).
2. **Confirm OpenSpec authority & pre-edit state:** Open the governing OpenSpec (`tasks.md` / `specs/`), record `git rev-parse HEAD`, `git status --short`, `git worktree list --porcelain`, assigned paths, and intended test target.
3. **Branch in a separate worktree:** `git worktree add -b docs/example-slice ../NEXIAM-example-slice 4321e30b12a09722569bb4f3766bf8f84f633f07`.
4. **Verify in isolation:** Run `php artisan test --compact` (SQLite) or provision `COMPOSE_PROJECT_NAME=nexiam-test-example` with the pre-run DB/storage check above, then run `vendor/bin/pint --test --format agent`, `npm run quality`, and `npm run build` as applicable.
5. **Commit selectively and submit PR:** Stage only assigned files (`git add <paths>`), commit, push only after authorization, fill `.github/PULL_REQUEST_TEMPLATE.md` with exact base SHA, impacted paths, observed command counts, and `gh pr checks <pr>` / `gh run view <run-id>` links, and close the issue only after acceptance evidence is verified.

## Emergency hotfix and break-glass protocol

Normal work flows through a reviewed PR from an isolated worktree. If an urgent production incident requires an emergency path:

1. **Explicit authorization:** Obtain explicit operator/founder authorization naming the exact target, maintenance window, and approved scope. Never self-authorize or give agents blanket bypass.
2. **Record incumbent and delta:** Capture the exact incumbent identity (release ID, commit/source SHA, manifest hash, image digests) and the bounded hotfix delta before making changes.
3. **Validate and confirm rollback:** Verify the hotfix on an isolated target where feasible, confirm a paired recovery/rollback path before activation, and preserve current runtime data and keys.
4. **Reconcile before the next release:** Open and merge a reconciliation PR to `master` recording the exact applied delta, verification results, and updated handoff before any subsequent ordinary release.
5. **Accountable break-glass bypass:** Only an authorized GitHub maintainer/operator may invoke a branch-protection or review bypass. Record the explicit **reason**, **scope**, and **expiry** in the owning issue/handoff, and restore normal enforcement immediately when the window closes.
