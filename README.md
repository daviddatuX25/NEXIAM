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
