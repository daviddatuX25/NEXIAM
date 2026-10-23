# NEXIAM application

The current runtime is Laravel 13 / PHP 8.4, Inertia 3 / Svelte 5, TypeScript 5.9, Vite 7, Tailwind 4 and PostgreSQL 16. Use `compose.yaml`, not Laravel Sail. `../legacy_codebase/application/` is historical reference only.

## Prerequisites

For the fresh-local procedure below:

- Git, Docker Engine and the Docker Compose v2 plugin
- PHP 8.4 with the extensions required by `composer.lock` (including PDO PostgreSQL/SQLite, sockets, GD, ZIP and standard Laravel extensions), and Composer 2
- Node.js 24 and npm
- Free host ports 18000, 5432, 18025 and 11025; enough memory/disk for PHP, PostgreSQL, Node build and OMR inference

The PHP image also installs LibreOffice and Poppler for document rendering. Dependencies are pinned in `composer.lock` and `package-lock.json`. Do not replace lockfiles with an update just to install the project.

## Fresh local installation

**Only use a new checkout and a new local database. Do not run this sequence in an existing deployment or a directory mounted by live containers.** Compose bind-mounts this directory; editing it changes the code those containers execute.

From a cloned repository:

```sh
cd system
cp .env.example .env
composer install --no-interaction --prefer-dist
php artisan key:generate --no-interaction
npm ci
npm run build

docker compose -p nexiam-dev config --quiet
docker compose -p nexiam-dev up -d --build --wait postgres mailpit inference
docker compose -p nexiam-dev build app
docker compose -p nexiam-dev run --rm --no-deps app php artisan migrate --no-interaction
touch database/tam_evaluations.sqlite
docker compose -p nexiam-dev run --rm --no-deps app php artisan migrate --database=tam --path=database/migrations_tam --no-interaction
```

Then seed the **new local database only** with the existing minimal synthetic foundation, and start the app and workers:

```sh
docker compose -p nexiam-dev run --rm --no-deps app php artisan tinker --execute '(new Database\Seeders\DatabaseSeeder)->run(Database\Seeders\DatabaseSeeder::testSeeders());'
docker compose -p nexiam-dev up -d --build --wait app queue omr-verification-worker scheduler
```

The explicit project name keeps local volumes distinct from the default `nexiam-system` project. It does not change published ports: do not run this alongside another installation using those ports. The example configuration uses PostgreSQL database/user `nexiam`, a deliberately local-only password, Mailpit, and synthetic identities. It is **not production configuration**.

The TAM/research survey uses its own SQLite store, provisioned by the two separate commands above. It is not the admissions PostgreSQL database and is not committed to Git.

Local surfaces:

- Application: `http://localhost:18000/`
- Staff sign-in: `http://localhost:18000/login`
- Applicant entry: `http://localhost:18000/applicant/login`
- Public application: `http://localhost:18000/apply`
- Liveness: `http://localhost:18000/up`
- Mailpit: `http://localhost:18025/`

Use a full `npm run build` for a fresh local checkout. Do not copy that build over an existing deployment's selective asset release without reviewing the coordinated release requirements.

## Environment and keys

Start from `.env.example`; keep real values private. External Compose `environment` entries override `env_file` values. Inspect your exact Compose files when changing configuration. Recreating a container rereads `env_file`; simply restarting it does not replace its environment.

- Preserve `APP_KEY` for an existing installation: encrypted persisted/queued data depends on it. `key:generate` above is for a fresh local installation only.
- PostgreSQL, queues and sessions are explicitly configured by `compose.yaml`; do not assume `.env` alone changes them.
- Replace local OMR transport/QR secrets before shared use. Configure trusted proxies only for verified ingress addresses.
- Xiam defaults to an offline/fake gateway for local development. Live provider use requires private API configuration and domain authorization; a working UI does not establish provider readiness.
- MFA and staff onboarding follow application policy. Seeded access does not bypass required setup or grant new capabilities.

### Optional Web Push

Set a matching `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` privately, or provide the matching `VAPID_PRIVATE_PEM`; set `VAPID_SUBJECT` to an operator-controlled contact. The source has no embedded key fallback. Missing/invalid keys make the push public-key endpoint unavailable; they do not configure an alternate shared identity.

On a **new local installation**, generate a fresh pair through the existing service:

```sh
docker compose -p nexiam-dev run --rm --no-deps app php artisan tinker
```

Inside Tinker, call `App\Notifications\Services\WebPushService::generateVapidKeys()`. Save the resulting public/private values in the private `.env`, not in Git, screenshots or shared logs. Restart/recreate the appropriate local runtime after configuration as required.

**Existing deployment upgrade:** export and preserve its current pair through a private operator process before deploying this source, then configure the app and push-delivery workers with that same pair. Do not generate a replacement during an upgrade; existing browser subscriptions are tied to the prior identity. This publication did not change the live keys or runtime.

### Optional Google sign-in and SMTP

`configure-google-oauth.sh [existing-env-file]` and `configure-plunk-smtp.sh [existing-env-file]` are interactive operator helpers. They ship without client secrets/SMTP passwords. Verify the exact target, registered Google callback, sender and deployment before using them. Never commit credentials entered into helper variables. Local Compose defaults to Mailpit, so private SMTP settings must also be reconciled with explicit Compose overrides. OAuth acceptance and mail delivery require separate verification.

## Seeders and demo access

All application seeders live in `database/seeders/`; their reviewed suggestion datasets and document assets are included. The installation command explicitly selects the existing `DatabaseSeeder::testSeeders()` manifest: references, synthetic staff, suggestions, canonical OMR fixture and basic communication/content templates. It does not claim to provide every operational scenario or a production baseline.

**Known broader-fixture limitation:** plain `php artisan db:seed` selects the much larger legacy demo chain. The fresh isolated smoke reached `OmrDemoSeeder` and failed current physical-sheet identity constraints; it is not the supported quick-start command above. Do not disable those constraints, fabricate material identities or reset a real database to work around it. Institutional-only bootstrap remains separately governed by `institution-only-production-seed-baseline`; this publication does not complete that refactor.

For the explicitly selected disposable local foundation, synthetic staff include:

| Email | Local demo password | Intended surface |
| --- | --- | --- |
| `hr.admin@nexiam.test` | `password` | Personnel/setup, subject to onboarding/MFA |
| `registrar.clerk@nexiam.test` | `password` | Registrar workspace |
| `guidance.counselor@nexiam.test` | `password` | Guidance/testing workspace |
| `bsit.head@nexiam.test` | `password` | Program-head workspace |

These defaults are read from `config/seed.php` / `StaffIdentitySeeder`; they are not live credentials. Required profile/setup/MFA steps may still apply. For a publicly reachable environment, provision authorized accounts and policies through the approved workflow rather than deploying these demo defaults.

`ProductionBaselineSeeder` is an institutional-baseline operation, **not automatic deployment setup**. It also calls staff/material seeders. Review it and the current domain handoff before any separately authorized use. Never run `foundation:reset`, `migrate:fresh`, sample-generation seeders or credential resets against existing applicant/staff records. No seed/reset is required simply to publish source to GitHub.

## Optional external TabPFN checkpoint

The `tabfm-runtime` Compose profile is opt-in. The model is **not rebuilt by compiling NEXIAM**, and weights are not stored in Git.

1. Request authorized access to the [publisher's model repository](https://huggingface.co/Prior-Labs/tabpfn_3) and review its license. The model card allows research/internal evaluation but restricts production use; source publication does not confer institutional/hosted/production permission.
2. Obtain exactly `tabpfn-v3-classifier-v3_20260417_binary.ckpt` from `Prior-Labs/tabpfn_3` using the publisher's authenticated download process. Never put the access token in this repository.
3. Save it privately and set `TABPFN_CHECKPOINT_HOST_PATH` to its actual path. The default is `./docker/tabfm/model.ckpt` relative to `system/`.
4. Verify SHA-256 before enabling inference:

```sh
sha256sum /absolute/private/path/to/model.ckpt
```

Expected digest: `1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88`.

Keep `TABPFN_CHECKPOINT_ID=Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt`. Only after real usage approvals are established, configure the corresponding approval flags and start the optional service:

```sh
docker compose -p nexiam-dev --profile tabfm-runtime up -d --build tabfm-inference
```

The worker verifies package/checkpoint identity and checksum. Missing/unapproved inference is not evidence of a valid admission recommendation, and a healthy web application does not establish this model's readiness. OMR's separate `inference` service is part of the base stack and is not this TabPFN checkpoint.

## Verification

Run from `system/`, serially on resource-constrained hosts:

```sh
npm run test -- --maxWorkers=1 --no-file-parallelism
npm run check
npm run lint
npm run format:check
npm run build

docker compose -p nexiam-dev exec -T app php artisan test --compact
docker compose -p nexiam-dev exec -T app vendor/bin/phpstan analyse --memory-limit=1G
```

The default `phpunit.xml` forces application/TAM SQLite in-memory databases and disables real external delivery. PostgreSQL-specific constraint/concurrency tests require their separately provisioned disposable database configurations; do not aim them at the development or public database. For PHP changes, run the scoped formatter through the same PHP runtime.

`npm run test:browser` invokes the existing Playwright suite. Use a deliberately isolated target and synthetic data; some scenarios mutate state. Passing a build or unit test is not browser, hardware-camera, physical OMR or production acceptance. See the [publication handoff](../openspec/changes/publish-current-system-source/HANDOFF.md) for the exact checks exercised for this snapshot and known limits.

## Existing deployment and backup boundaries

A Git push does not deploy this application. Existing runtime PHP can be bind-mounted, workers can retain old code, and production assets can be an independently staged manifest closure. Never run a whole-source build directly into a live asset destination just to synchronize Git.

Before a separately approved deployment: identify the exact project/environment, preserve keys, take and verify a database/storage backup, reconcile pending migrations, drain schema-dependent workers, stage the complete assets including workers/WASM, then activate and verify normal staff/applicant journeys. Keep rollback artifacts and existing assets needed by open tabs. No seed/reset is implied.

Back up the database, private storage/uploads, `.env`/keys, current source revision and deployed asset manifest/closure through separate private procedures. A source checkout or encrypted source archive alone cannot restore live applicant data. Never commit those private backups; `backups/`, runtime configuration and model weights are ignored.
