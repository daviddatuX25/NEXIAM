# NEXIAM Legacy Application Reference

**Status:** preserved legacy runtime; read-only reference for greenfield reconstruction  
**Location:** `legacy_codebase/application/`  
**Target authority:** none; see the root `docs/greenfield-foundation/` suite

This directory contains the complete old Laravel/Svelte application. It was moved as one dependency boundary so its behavior, routes, migrations, frontend pages, tooling, and runtime configuration can be inspected without polluting the future greenfield root.

## What is preserved here

- Laravel application source under `app/`;
- Artisan/bootstrap/configuration under `artisan`, `bootstrap/`, and `config/`;
- legacy schema history and seeders under `database/`;
- browser entry point and compiled-asset boundary under `public/`;
- Inertia/Svelte pages, layouts, components, and frontend tooling under `resources/`;
- route definitions under `routes/`;
- legacy PHPUnit/Pest-era test material under `tests/`;
- Composer/npm manifests and lock files;
- Docker/Compose and installation files;
- scripts, storage structure, and result-sheet templates.

The original feature-heavy README is preserved as `README.legacy.md`. The original installation guide is `INSTALL.md`. Both describe the historical application and may contain obsolete stack/deployment assumptions.

## How to inspect or run the legacy application

Run commands from this directory so relative paths and the Compose volume boundary remain correct:

```bash
cd legacy_codebase/application
```

Do not run the legacy app against the greenfield database. Do not copy its migration history into the target. If local dependencies or an ignored environment file are needed, create them only inside this reference runtime and keep credentials outside Git. The transition cleanup intentionally did not inspect, move, or print ignored `.env` files.

## Evidence rules

When using this runtime as a reference, record the finding in `../../docs/legacy-reference/` with:

- the exact source path;
- the observed behavior or data contract;
- the actor/scope involved;
- the target disposition: preserve, rewrite, spike, defer, or reject;
- the relevant greenfield contract/OpenSpec requirement;
- unresolved questions and planned acceptance evidence.

A page rendering successfully is not proof of authorization, state ownership, concurrency safety, recovery, accessibility, or audit correctness.

## Greenfield boundary

The target application must be built separately from this directory. The current greenfield sequence is:

1. E0 clean scaffold and quality baseline;
2. E1 identity, access, reference data, idempotency, retention, and audit foundation;
3. E2 connected admission-to-release slice with manual scoring fallback;
4. spike-gated OMR/RAG/decision-support/offline capabilities.

See `../../docs/greenfield-foundation/24-bmad-openspec-implementation-package.md` and `../../openspec/changes/e0-greenfield-foundation/`.
