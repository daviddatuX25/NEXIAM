# NEXIAM Legacy Evidence Area

This folder contains material from the previous NEXIAM/SecureCAT implementation and its planning history. It is intentionally separated from the future greenfield target.

## Subdirectories

| Location | Contents | Use |
|---|---|---|
| `application/` | Complete old Laravel/Svelte runtime | Run/inspect old behavior from this directory only |
| `planning/` | Historical BMAD-like phases, verification notes, codebase audits, debug records, and task trackers | Recover intended behavior and known failure cases |
| `plans/` | Historical architecture/design plans and implementation proposals | Evidence of prior decisions; not current authority |
| `capstone/` | Legacy capstone fact/evidence material | Research reference; validate before promotion |
| `superpowers/` | Historical deployment plans/specifications | Deployment evidence; reconcile with GF-23 |
| `agent-configs/` | Archived agent/editor/OpenSpec configuration | Historical tooling only; do not treat as product design authority |

## Separation rules

- The runnable old application is only `application/`.
- The target greenfield contracts are not stored here; use `../docs/greenfield-foundation/`.
- The legacy-to-target feature bridge is `../docs/legacy-reference/`.
- Research/manuscript source remains under `../manuscript/` and is evidence, not runtime.
- Do not add new target implementation code under this folder.
- Do not import the old migration history or database into the greenfield system.

## Reference workflow

```text
legacy_codebase/application or historical evidence
  → docs/legacy-reference disposition/crosswalk
  → docs/greenfield-foundation contract
  → openspec change
  → clean greenfield implementation
```

If a source cannot be connected through that chain, it is still an observation, not a build instruction.
