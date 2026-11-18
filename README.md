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
   - **Governing guardrails plan:** [`openspec/changes/isolated-full-system-beta-deployment/REPOSITORY-GUARDRAILS-PLAN.md`](openspec/changes/isolated-full-system-beta-deployment/REPOSITORY-GUARDRAILS-PLAN.md) with [`tasks.md`](openspec/changes/isolated-full-system-beta-deployment/tasks.md), [`specs/reproducible-demo-release/spec.md`](openspec/changes/isolated-full-system-beta-deployment/specs/reproducible-demo-release/spec.md), and [`openspec/changes/publish-current-system-source/HANDOFF.md`](openspec/changes/publish-current-system-source/HANDOFF.md). (The private `isolated-full-system-beta-deployment` operational handoff and workstation reconciliation inventories are held outside public `master` and supplied by the integration lead and VPS operator.)
   - **GitHub Issues:** inspect your assigned ticket with `gh issue view <number> --comments` per [`docs/agents/issue-tracker.md`](docs/agents/issue-tracker.md) for slice scope, allowed paths, and blockers (`Blocked by: #<n>`). Issues organize work and dependencies; they do not authorize implementation on their own.
   - **Owning OpenSpec:** open the single active change under `openspec/changes/<change>/` (`proposal.md`, `specs/`, `design.md`, `tasks.md`) and confirm the task is active and unblocked.
   - **Legacy boundary:** treat `legacy_codebase/` and `docs/legacy-reference/` as **read-only evidence** unless independently authorized.

2. **Create a bounded worktree off the lead-pinned ref.**
   - **Assigned base:** ask the integration lead for the current pinned clean base ref/SHA (for public PRs, derived from clean `origin/master`, never from unpublished local workstation history; see [source-publication boundary](openspec/changes/publish-current-system-source/HANDOFF.md)). Do not edit `master` directly or copy live mounted trees.
   - Before editing, record `git rev-parse HEAD`, `git status --short`, `git worktree list --porcelain`, assigned paths, and intended disposable test target.
   - Create a separate worktree off the pinned ref:

     ```sh
     git worktree add -b <type>/<slice-name> ../NEXIAM-<slice-name> <lead-pinned-ref-or-sha>
     ```

   - Never `reset`, `stash`, `prune`, blanket-stage (`git add -A` / `git add .`), or clean another worktree. Stage only your assigned files selectively.
   - Pushing branches, changing GitHub settings, tagging releases, and touching any live or VPS runtime each require separate explicit authorization.

3. **Choose and verify a disposable test target.**
   - Never run migrations, seeders, or test suites against a live, shared, or production database or storage path.
   - **PHP unit/feature suite (SQLite `:memory:`):** `system/phpunit.xml` forces isolated in-memory SQLite (`php artisan test --compact` inside `system/`). Note that `pgsql`-gated acceptance tests skip under SQLite.
   - **Frontend & PHP formatting checks:** run inside `system/` (`vendor/bin/pint --test --format agent`, `npm ci`, `npm run quality`, `npm run build`).
   - **PostgreSQL-sensitive suites — disposable provisioning from a fresh worktree:**
     1. Create an untracked env file (`cp system/.env.example system/.env`; never commit `.env` or copy a live `.env`).
     2. `system/compose.yaml` bind-mounts `.:/var/www/html` over the image (hiding `/var/www/html/vendor` in a fresh worktree that has no `system/vendor/autoload.php`) and defaults `name: nexiam-system` with host ports `18000`, `5432`, `18025`, `11025`. Isolate by setting `COMPOSE_PROJECT_NAME=nexiam-test-<slice>` and passing an untracked override file clearing `ports: !reset []` (plus `image: nexiam-system-app:latest` if already built locally, or run `docker compose build app` on a clean host). Start `postgres` first, install `system/vendor/` and `APP_KEY` as your host UID/GID so `git worktree remove` stays clean, then start `app` with `--no-deps`:

        ```sh
        cat > /tmp/nexiam-test-override.yaml <<'YAML'
        services:
          app:
            image: nexiam-system-app:latest
            ports: !reset []
          postgres:
            ports: !reset []
          mailpit:
            ports: !reset []
        YAML
        export COMPOSE_PROJECT_NAME=nexiam-test-<slice>
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml up -d --wait postgres
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml run --rm --no-deps \
          --user "$(id -u):$(id -g)" -e COMPOSER_CACHE_DIR=/tmp/composer-cache -e SKIP_DB_WAIT=1 \
          app sh -lc 'composer install --no-interaction --prefer-dist --no-progress && php artisan key:generate --force'
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml up -d --no-deps --wait app
        ```

     3. **Provision the suite database and verify the exact suite connection and storage before any migration or test:**
        Replace `<suite_db>` with the exact disposable database your target suite requires (for example `nexiam_pg_acceptance_<slice>` or the database named by a `phpunit.*-postgres.xml` config):

        ```sh
        SUITE_DB="<suite_db>"
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml exec -T postgres \
          psql -U nexiam -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"${SUITE_DB}\" TEMPLATE template0;"
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml exec -T app \
          env APP_ENV=testing DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 \
              DB_DATABASE="${SUITE_DB}" DB_USERNAME=nexiam DB_PASSWORD=nexiam-local-only \
              DB_URL="" NEXIAM_TEST_PIN_SQLITE=0 \
          sh -lc '
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
                "sensitive_root" => config("filesystems.disks.sensitive.root"),
              ], JSON_PRETTY_PRINT) . PHP_EOL;
            '\''
          '
        docker inspect -f '{{range .Mounts}}{{.Type}}:{{.Source}}->{{.Destination}} {{end}}' \
          "$(docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml ps -q app)"
        docker volume ls --filter "label=com.docker.compose.project=${COMPOSE_PROJECT_NAME}"
        ```

        Confirm `"current_database"` equals `"${SUITE_DB}"` (never `nexiam` or a live DB), storage mounts resolve to `volume:...${COMPOSE_PROJECT_NAME}_app_storage->/var/www/html/storage` (never a shared host custody mount), and the volume labels match `${COMPOSE_PROJECT_NAME}`.
     4. **Run the supported PostgreSQL command for the target suite:**
        - **Direct PostgreSQL concurrency / feature tests (verified on `origin/master`):** migrate the provisioned disposable database and run the target test with the exact same verified `SUITE_DB` environment (`DatabaseSeeder::testSeeders()` provides the documented test seed baseline without invoking the broken `OmrDemoSeeder` chain):

          ```sh
          docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml exec -T app \
            env APP_ENV=testing DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 \
                DB_DATABASE="${SUITE_DB}" DB_USERNAME=nexiam DB_PASSWORD=nexiam-local-only \
                DB_URL="" NEXIAM_TEST_PIN_SQLITE=0 \
            sh -lc '
              php artisan migrate:fresh --force &&
              php artisan tinker --execute "app()->make(Database\Seeders\DatabaseSeeder::class)->run(Database\Seeders\DatabaseSeeder::testSeeders());" &&
              php artisan test --compact tests/Feature/Admission/QuotaReservationConcurrencyTest.php
            '
          ```

        - **OMR physical assurance (`phpunit.pg.xml`, 5 OMR files):** when `system/compose.yaml` host ports are free (or with `app` + `postgres` already started under `COMPOSE_PROJECT_NAME`), `bash system/bin/omr-physical-assurance-pg-acceptance.sh` fingerprints `nexiam`, creates `nexiam_omr_acceptance_<utc>_<random>_<pid>`, runs `migrate:fresh --seed --force` and `vendor/bin/pest --configuration=phpunit.pg.xml`, and drops the temp DB on exit. Note that on `4321e30b`, `migrate:fresh --seed` stops at `OmrDemoSeeder` (`omr_questions_physical_identity_all_or_none`, per [source-publication handoff](openspec/changes/publish-current-system-source/HANDOFF.md)).
        - **Scoped `phpunit.*-postgres.xml` suites:** each config enforces its own bootstrap guard (`tests/Support/*Bootstrap.php`) and fixed or patterned `DB_DATABASE`. After creating `<suite_db>` and verifying it with step 3, remove any stale `/tmp/*-config.php` cache inside `app` and run `vendor/bin/pest --configuration=<config>` (note: several scoped configs retain pre-existing baseline failures on `4321e30b`; report observed counts honestly rather than masking them):

          | Config | Suite | Required `<suite_db>` & preconditions |
          | --- | --- | --- |
          | `phpunit.test-kit-postgres.xml` | `TestKitPostgresContractTest`, `TestKitHttpContractTest` | `nexiam_test_kit_acceptance_<unique>` (`<= 63` chars; pass `DB_DATABASE=<suite_db>` and `NEXIAM_TEST_KIT_SOURCE_DATABASE=nexiam`; `/tmp/<suite_db>-config.php` must not exist) |
          | `phpunit.admission-checklist-postgres.xml` | `AdmissionChecklistPostgresConcurrencyTest` | `nexiam_messaging_acceptance` (bootstrap creates/drops per-run schema + `/tmp/<schema>-storage` disks) |
          | `phpunit.messaging-postgres.xml` | `CaseConversationPostgresConcurrencyTest`, `RecordThreadSequenceMigrationTest` | `nexiam_messaging_acceptance` (`NEXIAM_TEST_FULL_REFRESH_EACH_TEST=1`; `/tmp/nexiam-messaging-postgres-acceptance-config.php` must not exist) |
          | `phpunit.omr-camera-postgres.xml` | `OmrCameraContentionPostgresTest` | `nexiam_omr_assisted_verify_20261019_a4c8` |
          | `phpunit.omr-page-disposition-postgres.xml` | `OmrDiagnosticQrDisabledBridgeTest` | `nexiam_test_kit_acceptance_omr_disposition_20261023` |

     5. **Verify project ownership before `down -v`:**
        Confirm only your disposable project's containers and volumes are listed, then tear down:

        ```sh
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml ps --format "table {{.Name}}\t{{.Project}}\t{{.State}}"
        docker volume ls --filter "label=com.docker.compose.project=${COMPOSE_PROJECT_NAME}"
        docker compose -f system/compose.yaml -f /tmp/nexiam-test-override.yaml down -v
        rm -f /tmp/nexiam-test-override.yaml
        ```

   - If optional local Git hooks are introduced later, keep them versioned, opt-in, and fast; hosted GitHub Actions checks remain authoritative.

4. **Open a reviewed PR and hand off.**
   - Complete every section of [the default PR template](.github/PULL_REQUEST_TEMPLATE.md). Link evidence (issue, OpenSpec `HANDOFF.md`, hosted CI run URL) instead of duplicating logs across docs.
   - One integration lead owns integration order and canonical handoff updates.
   - Close an issue only when its acceptance evidence exists and is linked—never solely because an agent reported completion.

### Clean-room walkthrough (synthetic docs-only example `SYNTH-G2`)

*(Stop-rule note: checking a real ticket first—for example `gh issue view 66 --comments`, which lists `Blocked by: #62, #63, #64, #65`—triggers an immediate stop before creating a worktree. Below is the full unblocked issue-to-PR path using a clearly synthetic docs-only example and historical base ref `4321e30b12a09722569bb4f3766bf8f84f633f07`.)*

1. **Ticket & OpenSpec:** Assigned synthetic ticket `SYNTH-G2` (no open `Blocked by` edges) under `openspec/changes/isolated-full-system-beta-deployment/REPOSITORY-GUARDRAILS-PLAN.md` (`tasks.md` §7.7 / slice G2).
2. **Pre-edit state & worktree:** Record `git rev-parse HEAD`, `git status --short`, `git worktree list --porcelain`, assigned paths (`README.md`, `.github/PULL_REQUEST_TEMPLATE.md`), and target (`N/A` docs-only). Branch off the historical example base:
   `git worktree add -b docs/synth-g2-onboarding ../NEXIAM-synth-g2 4321e30b12a09722569bb4f3766bf8f84f633f07`
3. **Edit & verify locally:** Update `README.md` and `.github/PULL_REQUEST_TEMPLATE.md`, run `git diff --check 4321e30b..HEAD` (observed exit `0`), and confirm all linked paths exist and `system/` + `.github/workflows/*` are untouched.
4. **Selective commit & PR submission:** `git add README.md .github/PULL_REQUEST_TEMPLATE.md && git commit -m "docs(workflow): synthetic G2 onboarding walkthrough"`. Push and open PR only after authorization, attaching the completed template below:

```markdown
## Issue / OpenSpec / Task
- **GitHub Issue:** #SYNTH-G2 (synthetic docs-only example; 0 open blockers)
- **Governing OpenSpec:** `openspec/changes/isolated-full-system-beta-deployment/` (`REPOSITORY-GUARDRAILS-PLAN.md`, `tasks.md`)
- **Task ID(s):** G2

## Expected Behavior & Out-of-Scope Work
- **Expected behavior:** Document daily issue-to-PR workflow, runnable disposable PostgreSQL verification, and default PR template.
- **Explicitly out of scope:** `system/` product code, `.github/workflows/*` CI implementation, GitHub branch settings, and VPS/live runtime changes.

## Baseline & Impacted Paths
- **Pinned base ref / SHA:** `origin/master` @ `4321e30b12a09722569bb4f3766bf8f84f633f07` (historical example)
- **Branch / worktree:** `docs/synth-g2-onboarding` (`../NEXIAM-synth-g2`)
- **Impacted paths:** `README.md`, `.github/PULL_REQUEST_TEMPLATE.md`

## Verification Commands, Results & CI Run Link
- **Disposable test target:** N/A (docs-only slice; separate disposable PostgreSQL runbook verified on `QuotaReservationConcurrencyTest` → `2 passed (18 assertions)`)
- **Commands & observed results:**
  - `git diff --check 4321e30b..HEAD` → exit `0`
  - Relative doc/template links verified on disk → all present
- **Hosted CI run link:** Pending push authorization (`gh pr checks <pr>` / `gh run view <run-id>`)

## Data, Schema & Security Impact
- **Migrations / schema changes:** None
- **Seeds / fixtures / custody:** None (no live/shared DB or custody touched)
- **Security & secrets check:**
  - [x] No `.env`, private keys, credentials, dumps, model weights, or protected applicant/staff records added
  - [x] No unpublished local/private workstation history merged into public `master`
  - [x] `legacy_codebase/` treated as read-only evidence

## Release & Rollback Notes
- **Artifact / manifest / worker / config impact:** N/A (docs-only)
- **Rollback path:** Revert commit on `master`

## Unresolved Items & Open Holds
- G3b hosted CI checks and G4 branch protection remain unimplemented; G3a comparison is tracked separately.

## Checklist
- [x] Changes stay strictly within the assigned slice and OpenSpec scope
- [x] Evidence is linked (issue, OpenSpec, CI run) rather than duplicated across docs
- [ ] Issue will be closed only after required acceptance evidence is verified
```

## Emergency hotfix and break-glass protocol

Normal work flows through a reviewed PR from an isolated worktree. If an urgent production incident requires an emergency path:

1. **Explicit authorization:** Obtain explicit operator/founder authorization naming the exact target, maintenance window, and approved scope. Never self-authorize or give agents blanket bypass.
2. **Record incumbent and delta:** Capture the exact incumbent identity (release ID, commit/source SHA, manifest hash, image digests) and the bounded hotfix delta before making changes.
3. **Validate and confirm rollback:** Verify the hotfix on an isolated target where feasible, confirm a paired recovery/rollback path before activation, and preserve current runtime data and keys.
4. **Reconcile before the next release:** Open and merge a reconciliation PR to `master` recording the exact applied delta, verification results, and updated handoff before any subsequent ordinary release.
5. **Accountable break-glass bypass:** Only an authorized GitHub maintainer/operator may invoke a branch-protection or review bypass. Record the explicit **reason**, **scope**, and **expiry** in the owning issue/handoff, and restore normal enforcement immediately when the window closes.
