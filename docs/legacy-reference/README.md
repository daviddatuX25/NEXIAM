# Legacy Reference and Replication Catalog

**Status:** active evidence index; not target authority  
**Legacy runtime:** `../../legacy_codebase/application/`  
**Greenfield authority:** `../greenfield-foundation/` and approved OpenSpec changes

This folder is the controlled bridge between the old application and the future greenfield implementation. It exists so verified behavior, nearest-working implementation patterns, fixtures, payloads, layouts, and failure discoveries can be reused while the target re-establishes safe architecture, migration, capability, provider, and invariant boundaries.

## Reading order

1. [01 — Runtime inventory](01-runtime-inventory.md)
2. [02 — Feature replication catalog](02-feature-replication-catalog.md)
3. [03 — Legacy-to-greenfield crosswalk](03-legacy-to-greenfield-crosswalk.md)
4. [04 — UI/UX observed baseline](04-ui-ux-observed-baseline.md)
5. [05 — Data and migration safety notes](05-data-and-migration-safety-notes.md)
6. [06 — Reconciled decisions and residual clarifications](06-open-clarifications.md)
7. [07 — Snapshot manifest](07-snapshot-manifest.md)
8. [08 — E2 connected-slice Legacy→Target Trace](08-e2-connected-slice-trace.md)
9. [09 — Staff personnel lifecycle trace](09-staff-personnel-lifecycle-trace.md)
10. [10 — Admission attempt detail trace](10-admission-attempt-detail-trace.md)
11. [11 — Exam scheduling trace](11-exam-scheduling-trace.md)

## Evidence classification

| Classification | Meaning | Greenfield consequence |
|---|---|---|
| `PRESERVE` | Verified behavior is still wanted and does not conflict with the target contract | Re-express through a new command/query/module and prove it |
| `REWRITE` | The behavior is wanted but the old state, authorization, transaction, or concurrency boundary is unsafe | Rebuild from GF contracts; use legacy only for fields and journey evidence |
| `SPIKE` | The capability depends on unresolved technical or research evidence | Keep a manual fallback and require a separate OpenSpec/evidence gate |
| `DEFER` | Useful but outside the connected slice or current capstone cut-line | Do not hide it inside E0/E1/E2 |
| `REJECT` | Conflicts with founder decisions, security, privacy, or domain authority | Do not reproduce it |

## Required row shape

Every important legacy behavior should eventually have:

- legacy source path(s);
- actor and data scope;
- preconditions and visible states;
- authoritative mutation or read behavior observed;
- target disposition;
- target GF/OpenSpec requirement;
- target module/command/query/page once implemented;
- acceptance and failure evidence;
- unresolved stakeholder decision, if any.

## Non-negotiable boundary

The legacy runtime is the nearest-working behavior and implementation reference. It is not a runtime dependency of the greenfield system and does not overrule target authorization or lifecycle contracts. The target order is founder decision → approved greenfield contract → OpenSpec → test/runtime evidence, with legacy evidence deliberately reused through the crosswalk.

## Update protocol

When a legacy feature is inspected:

1. add or update the catalog row;
2. link the relevant GF document and decision;
3. state what is intentionally not copied;
4. record the UI/UX questions before designing a replacement;
5. add an OpenSpec requirement if implementation is ready;
6. update the snapshot manifest if files move or the legacy runtime changes.
