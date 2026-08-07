# Greenfield Foundation Specification

## ADDED Requirements

### Requirement: Isolated implementation boundary

The greenfield runtime MUST be buildable without connecting to or importing from the legacy application database, storage, routes, controllers, migrations, or provider configuration.

#### Scenario: Legacy database is unavailable

- GIVEN the selected greenfield workspace is configured correctly
- WHEN the legacy application database is stopped or unreachable
- THEN the greenfield application still boots against its own PostgreSQL authority
- AND no greenfield readiness result reports the legacy database as a dependency.

### Requirement: Fresh PostgreSQL authority

The greenfield application MUST use one fresh PostgreSQL migration history and MUST expose deterministic synthetic seed and reset behavior.

#### Scenario: Empty database setup

- GIVEN an empty approved PostgreSQL database
- WHEN the E0 setup, migration, and seed commands run
- THEN the schema and synthetic reference data are created deterministically
- AND running the reset path does not affect any database or storage outside the greenfield environment.

### Requirement: Real quality evidence

The E0 project MUST configure and execute backend, frontend, browser, formatting, static-analysis, and build checks with real discovered tests and without inherited error baselines or suppressions.

#### Scenario: No-test masking is rejected

- GIVEN a test command that discovers zero tests
- WHEN the E0 quality command runs
- THEN the command fails or the evidence report marks E0 as failed
- AND `passWithNoTests`-style masking cannot produce an E0 PASS.

### Requirement: Health-chained runtime

The E0 Compose runtime MUST expose explicit health/readiness evidence for application boot, PostgreSQL, queue worker, scheduler, private storage, test mail, and optional provider availability.

#### Scenario: Optional provider is unavailable

- GIVEN the optional Python/provider seam is stopped
- WHEN readiness and E0 core checks run
- THEN the affected optional capability is reported as degraded/unavailable
- AND the E0 core runtime does not claim provider success or silently switch authority.

### Requirement: After-commit asynchronous effects

Asynchronous side effects MUST be dispatched after the authoritative transaction commits and MUST have operation identity, retry state, and terminal failure visibility.

#### Scenario: Worker is stopped after commit

- GIVEN an authoritative E0 command commits successfully
- WHEN the queue worker is stopped before processing the side effect
- THEN the authoritative state remains committed
- AND the operation is visible as pending/retryable rather than reported as failed domain state.

### Requirement: Evidence report

E0 MUST produce a versioned evidence report containing source provenance, runtime versions, health output, migration/seed/reset output, real test discovery/results, quality output, queue/provider/storage evidence, restore rehearsal measurement, and limitations.

#### Scenario: E0 is promoted

- GIVEN all E0 tasks are complete
- WHEN the evidence report is reviewed
- THEN GF-14 records an E0 result with explicit evidence paths
- AND the result does not claim E1/E2, pilot, or production readiness.
