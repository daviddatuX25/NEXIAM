# E0 Greenfield Foundation Tasks

Tasks are ordered. A later task must not hide a failed earlier task.

## A. Workspace and provenance

- [x] A1 Select and record the greenfield repository/worktree location.
- [x] A2 Record the legacy reference commit, current dirty-worktree status, and the fact that legacy is read-only evidence.
- [x] A3 Copy or commit GF-00 through GF-24 and the E0 OpenSpec package into the selected greenfield workspace.
- [x] A4 Confirm no E0 runtime path references the legacy database, storage, migrations, routes, or controllers.

## B. Runtime scaffold

- [x] B1 Create the clean Laravel 13/PHP 8.4 application baseline.
- [x] B2 Configure Inertia 3, React 19, TypeScript 5.9, Tailwind 4, and Node 24 LTS.
- [x] B3 Pin the selected supported PostgreSQL major and required extensions.
- [x] B4 Add the health-chained Compose services defined in GF-23.
- [x] B5 Configure private storage and local/test Mailpit without embedding credentials.
- [x] B6 Configure Laravel database queue, dedicated worker, scheduler, and after-commit dispatch behavior.

## C. Persistence and deterministic environment

- [x] C1 Establish one fresh PostgreSQL migration history; do not copy legacy migrations.
- [x] C2 Add deterministic synthetic seed data and a safe greenfield-only reset command.
- [x] C3 Add the initial operation/idempotency/audit foundation required by E0 evidence.
- [x] C4 Verify fresh migration, seed, reset, and repeatability from an empty database.
- [x] C5 Verify required constraints/extensions and record the output.

## D. Quality and architecture

- [x] D1 Configure Pest 5 on PHPUnit 13 with Laravel integration and add at least one real backend feature test beyond a boot assertion.
- [x] D2 Configure Vitest and add at least one real frontend unit/component test.
- [x] D3 Configure Playwright and add one boot/login/health browser journey or an explicit E0 browser fixture journey.
- [x] D4 Configure Pest architecture/browser plugins, Pint, PHPStan, ESLint, and Prettier without copied legacy baselines/suppressions.
- [x] D5 Add architecture tests for controller/domain/provider/legacy-boundary rules.
- [x] D6 Ensure every quality command discovers real source/tests and fails on a deliberate negative fixture.

## E. Operational evidence

- [x] E1 Capture service health and readiness output.
- [x] E2 Prove a post-commit queued operation is visible and idempotent.
- [x] E3 Prove a failed operation is visible, retryable, and terminally reportable.
- [x] E4 Capture Mailpit/test-delivery evidence without treating delivery as domain authority.
- [x] E5 Capture private-storage checksum/version/retention evidence.
- [x] E6 Run a database-plus-files restore rehearsal and record measured elapsed time and limitations.
- [x] E7 Run audit/chain verification against the synthetic data.
- [x] E8 Stop the optional provider seam and prove E0 core health/reporting behavior remains explicit.

## F. Handoff

- [x] F1 Produce the E0 evidence report described in `design.md`.
- [x] F2 Update GF-14 with the actual E0 PASS/FAIL result and evidence paths.
- [x] F3 If PASS, open `e1-identity-reference-foundation`; if FAIL, repair E0 before feature work.
- [x] F4 Do not claim E1/E2, pilot, or production readiness from an E0 PASS.
