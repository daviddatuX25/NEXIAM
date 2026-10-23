## ADDED Requirements

### Requirement: Browsable source on the existing default branch

The publication SHALL contain current application source under `system/`, required migrations/seeders/data/assets, tests and dependency lockfiles, with a root README and application operations README. The commit SHALL descend from existing remote master without importing oversized unpublished history or changing the default branch name.

#### Scenario: Normal source publication
- GIVEN a protected live workspace and an isolated candidate based on remote master
- WHEN checks pass and the candidate is published
- THEN remote master advances normally to the candidate
- AND existing remote history and the encrypted backup branch remain reachable
- AND no live deployment action is performed

### Requirement: Credentials and external artifacts stay private

No active credentials, runtime environment, database, uploads, generated build, backup or model weight SHALL be introduced by the publication delta. Existing active VAPID fallback bytes SHALL not occur in new plaintext Git objects. Model provenance/hash/licensing and private configuration steps SHALL be documented rather than replaced with a fake model.

#### Scenario: Missing Web Push configuration
- WHEN no valid matching VAPID key pair is configured
- THEN the push key endpoint reports unavailable without returning a shared fallback key
- AND valid configured push delivery retains its existing contract

### Requirement: Existing intake contract remains usable

The shared correction fields SHALL display saved history and propagate typed/explicitly selected history to the form; suggestion results SHALL expose listbox option semantics.

#### Scenario: Edit history
- GIVEN an existing saved strand or previous course
- WHEN the user edits it or explicitly selects a suggestion
- THEN the shared form holds that edited/selected value

### Requirement: Evidence distinguishes publication from deployment

The handoff SHALL record exercised checks, failures/limits, external model requirements and the existing-deployment VAPID migration prerequisite. It SHALL not claim full-suite, browser, physical-device, PostgreSQL-concurrency or deployment acceptance that was not exercised.
