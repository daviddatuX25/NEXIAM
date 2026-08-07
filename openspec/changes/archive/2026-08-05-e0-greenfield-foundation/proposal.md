# Change Proposal: E0 Greenfield Foundation

**Change ID:** `e0-greenfield-foundation`
**Status:** Proposed
**Parent authority:** NEXIAM-GF-24
**Decision links:** D-003, D-055–D-066, D-068, D-070, D-071
**Scope:** Clean scaffold only; no admission/exam/grading/release feature implementation

## Why

The current NEXIAM repository is a feature-rich legacy/reference system with fragmented workflow ownership, migration history, route-role drift, and sparse executable tests. The project needs a clean target workspace without discarding the behavior and evidence already learned from that system.

The first implementation change must establish a reproducible build/runtime/test baseline. Without this change, every later feature would inherit uncertainty about database authority, quality gates, queue behavior, health, storage, and whether a test actually runs.

## Scope

E0 creates:

- an isolated greenfield repository/worktree;
- pinned Laravel/PHP/Inertia/React/TypeScript/Tailwind/Node/PostgreSQL versions;
- health-chained Compose runtime;
- fresh PostgreSQL migration and deterministic synthetic seed/reset path;
- Pest 5 on PHPUnit 13, Pest Laravel/architecture/browser plugins, Vitest, and Playwright test authorities;
- strict quality checks with no legacy baselines or suppressions;
- database queue worker, scheduler, private storage, and Mailpit test delivery;
- health/readiness, operation/queue visibility, and basic audit/idempotency seams;
- architecture tests for dependency and provider boundaries;
- E0 evidence report.

## Non-goals

- porting the 86 legacy migrations;
- importing legacy production data;
- implementing admission, scheduling, grading, release, OMR, RAG, BGE, Yukee, offline sync, or broad parity;
- making the legacy database a runtime dependency;
- claiming pilot or production readiness;
- adding arbitrary framework packages without an approved dependency decision.

## Acceptance summary

E0 passes only when another engineer can start the clean workspace from the documented setup, run fresh PostgreSQL migrations/seeds, boot Compose, observe health/queue/storage/mail behavior, execute real discovered tests, and reset the synthetic environment without accessing the legacy database.
