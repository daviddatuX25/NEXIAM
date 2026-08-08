# NEXIAM Project Workspace

**Phase:** E0 PASS; E1 PASS; E1b locked; E2 IN PROGRESS (exam-session reservation cut done 2026-08-08)  
**Single entry point:** [`docs/GREENFIELD-MASTER-REFERENCE.md`](docs/GREENFIELD-MASTER-REFERENCE.md)  
**Work process:** [`docs/project-context.md`](docs/project-context.md)  
**Master roadmap:** [`docs/MASTER-DELIVERY-ROADMAP.md`](docs/MASTER-DELIVERY-ROADMAP.md) · formal suite artifact: [`docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md`](docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md)  
**Target implementation:** `system/`  
**Canonical target contract:** `docs/greenfield-foundation/` plus the active OpenSpec changes under `openspec/changes/`

NEXIAM is being rebuilt as a clean greenfield system. The old Laravel/Svelte application is preserved as a read-only behavior and technical reference. It is not the implementation target and must not silently become the target architecture.

## Repository map

| Location | Role | Authority |
|---|---|---|
| `docs/GREENFIELD-MASTER-REFERENCE.md` | Single human entry point, parity/readiness blockers, future-feature reservations, and continuation order | Active navigator; links rather than duplicates detailed contracts |
| `docs/project-context.md` | Permanent agent work process: legacy → disposition → FR/story → OpenSpec → tests | Always-on process reminder with `.cursor/rules/nexiam-greenfield.mdc` |
| `docs/greenfield-foundation/` | Canonical target reference: recovery, product scope, roles, domain model, workflows, HLD, LLD, schema, application contracts, authorization, UX, operations, and implementation handoff | Target contract; status is shown in each document |
| `docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md` | **Master delivery roadmap** — architecture spine, phases, tracks, gates, scorecard | Single whole-project phase/gate authority |
| `docs/legacy-reference/` | Consolidated legacy inventory, feature disposition, UI/UX evidence, data notes, and unresolved clarifications | Evidence and traceability; never overrides approved target decisions |
| `openspec/changes/` | Executable implementation changes | Active change authority after its gate passes |
| `legacy_codebase/application/` | Complete old Laravel/Svelte runtime, moved as one runnable dependency set | Read-only legacy evidence |
| `legacy_codebase/planning/`, `legacy_codebase/plans/`, `legacy_codebase/capstone/` | Historical plans, audits, task records, and research artifacts | Historical evidence only |
| `manuscript/` | Research/manuscript source material | Evidence only; not runtime authority |
| `.github/` and `CLAUDE.md` | Repository-level agent/CI guidance | Workspace governance |

## Start here

1. [Greenfield master reference](docs/GREENFIELD-MASTER-REFERENCE.md)
2. [Permanent work process / project context](docs/project-context.md)
3. [Current implementation-readiness report](docs/greenfield-foundation/evidence/implementation-readiness-report-2026-08-04.md)
4. [Master delivery roadmap](docs/MASTER-DELIVERY-ROADMAP.md)
5. [Greenfield foundation catch-up](docs/greenfield-foundation/00-catch-up-and-continue-progress.md)
6. [Foundation suite authority and reading order](docs/greenfield-foundation/README.md)
7. [Decision matrix (GF-13)](docs/greenfield-foundation/13-decision-and-batch-approval-matrix.md)
8. [Legacy replication catalog](docs/legacy-reference/README.md)

## Legacy application reference

The complete legacy runtime is now under `legacy_codebase/application/`. Run or inspect it from that directory, not from this repository root:

```bash
cd legacy_codebase/application
```

Its runtime boundary includes `app/`, `artisan`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `tests/`, Composer/npm manifests, Docker/Compose files, scripts, storage structure, and result-sheet templates.

The old application README and installation guide remain inside that folder. Existing ignored environment files were not inspected, moved, or printed. Do not copy credentials or production data into the greenfield workspace.

## Target authority rule

Use this order for new behavior:

1. recorded founder decisions and approved stakeholder evidence;
2. the [master delivery roadmap (GF-14)](docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md);
3. `docs/greenfield-foundation/` contracts;
4. the active OpenSpec change;
5. executable tests and runtime evidence;
6. `docs/legacy-reference/` crosswalk;
7. legacy code and old plans.

A legacy screen, route, migration, or model can show what happened before. It cannot authorize a new rule by existing in the old system.

## Rebuild workflow

For each target feature:

1. read the relevant greenfield contract and the GF-14 phase it belongs to;
2. inspect the legacy path listed in `docs/legacy-reference/`;
3. classify the behavior as preserve, rewrite, spike, defer, or reject;
4. record unresolved stakeholder/UI questions before implementation;
5. create or update an OpenSpec change;
6. implement in the clean greenfield workspace (`system/`);
7. prove the acceptance, authorization, recovery, accessibility, and audit behavior;
8. update the crosswalk with the final target path and evidence.

The first approved implementation boundary is E0: a clean scaffold, quality baseline, PostgreSQL authority, Compose runtime, deterministic seed/reset, health/queue visibility, and evidence report. E1 and E2 remain gated by the exits in GF-14/GF-24 until E0 exit evidence and a fresh R5 readiness pass.
