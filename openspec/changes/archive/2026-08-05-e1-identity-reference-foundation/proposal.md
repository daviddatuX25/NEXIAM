# E1 Identity & Reference Foundation

**Status:** OPEN  
**Depends on:** E0 scaffold evidence PASS — [`docs/greenfield-foundation/evidence/e0-scaffold-evidence.md`](../../../docs/greenfield-foundation/evidence/e0-scaffold-evidence.md)

## Intent

Establish greenfield identity, capability grants, and reference data foundations required before the connected admission-to-release slice (E2).

## Non-goals

- Do not recreate legacy role middleware as authorization authority.
- Do not start the E2 vertical journey screens/commands yet.
- Do not treat Spatie Permission as GF-21 authority.

## Spatie Permission spike (required early)

Evaluate Spatie Laravel Permission as a **persistence/helper** layer only:

- compatible with Laravel 13 / PostgreSQL 16;
- maps cleanly to capability grants without becoming the decision engine;
- exit path documented if helper is rejected.

GF-21 capability/resource-scope authorization remains the authority for effective permission resolution, office/program/cycle scope, contextual Proctor duty, break-glass, default-deny, and audit evidence.

## Initial deliverables

1. Compatibility spike notes for Spatie Permission (use/no-use decision).
2. Identity principal model and session/context seams.
3. Capability grant persistence aligned to GF-21 vocabulary.
4. Minimal reference catalogs needed by E2 (institution/cycle already seeded in E0).
5. Denial/audit evidence for protected routes/commands.

## Exit

E1 PASS unlocks `e2-connected-admission-release-slice`. E1 PASS does not unlock pilot/production.
