# Platform Topology and Development Standards

**Artifact:** NEXIAM-GF-09  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Pin a reproducible greenfield technical baseline and define how implementation evidence is produced.  
**Non-claim:** No greenfield runtime, container, migration, or CI evidence exists yet.

## 1. Recommended stack baseline

| Concern | Recommended target | Decision/status |
|---|---|---|
| Backend | PHP 8.4 + Laravel 12 | Proposed fixed major versions; replace “latest stable” wording |
| Server-driven SPA | Inertia.js 2 | Proposed |
| Frontend | Svelte 5 + TypeScript 5.9 | Proposed |
| Styling/components | Tailwind CSS 4 + approved shadcn-svelte components | Proposed; accessibility remains application responsibility |
| Build/runtime | Node.js 24 LTS across local, CI, and image build | REVIEW REQUIRED; current Docker/CI disagree |
| Production database | MySQL 8.4 LTS | Recommended to align existing Compose; REVIEW REQUIRED against MariaDB plan |
| Test database | MySQL 8.4 for integration/migration evidence; SQLite only for explicitly parity-safe fast tests | Proposed |
| Queue | Laravel queue worker with database-backed queue initially; all domain-triggered work after commit | Proposed capstone baseline |
| Scheduler | Dedicated scheduler process/service using the same application image | Proposed |
| Mail | Mailpit in local/test; provider adapter in deployed environment | Proposed |
| Inference | One versioned Python worker/sidecar for approved OMR/BGE adapters | Conditional; absent today |
| RAG/vector/LLM | Application adapters; provider choices configured, health-checked, and replaceable | Provider decision required; no provider becomes domain authority |
| File storage | Laravel filesystem contract; private persistent volume for controlled demo; object-storage adapter ready for pilot | Review with backup/retention requirements |
| Reverse proxy/ingress | Dokploy/Traefik-managed HTTPS boundary where deployed | Proposed deployment environment |
| Testing | PHPUnit 11 backend; Vitest frontend units; Playwright browser E2E | REVIEW REQUIRED; resolves Pest/PHPUnit conflict in favor of project convention |
| Static quality | Larastan/PHPStan, Pint, svelte-check, ESLint, Prettier | Required zero-on-greenfield baseline |

No dependency may use an unpinned `latest` tag/version in reproducible CI or deployment evidence.

## 2. Proposed Compose topology

```text
Untrusted clients
  Browser / mobile browser
          |
        HTTPS
          |
Dokploy / Traefik ingress
          |
  NEXIAM application network
    ├── web/application service
    ├── queue-worker service (same application image)
    ├── scheduler service (same application image)
    ├── MySQL 8.4 datastore
    ├── Python inference worker (only when approved)
    └── Mailpit (local/test only)
          |
  Explicit outbound adapters
    ├── production mail provider
    ├── vector/knowledge provider
    ├── LLM provider
    └── approved object storage/checkpoint target
```

### Topology rules

- One Compose project defines companion services and health-chain dependencies.
- Database readiness is established before migrations/application readiness.
- Web, worker, and scheduler use the same immutable application image/version.
- Python worker exposes a narrow authenticated/versioned internal contract; it does not access application tables directly.
- External provider access is outbound-only through adapters with timeouts, retries, and health reporting.
- Mailpit is never part of production delivery.
- Secrets are injected through environment/secret management and never baked into images.

## 3. Environment planes

| Plane | Data | Integrations | Permitted claim |
|---|---|---|---|
| Local development | Synthetic fixtures | Mailpit/fakes/sandboxes | Developer functionality only |
| CI | Deterministic synthetic fixtures | Fakes or controlled ephemeral services | Automated correctness for covered tests |
| Controlled capstone demo | Seeded synthetic or approved sanitized fixtures | Explicitly approved sandbox/free-tier adapters with degraded fallbacks | Demonstration and technical evidence only |
| Technical pilot | Approved participants/data and written operational protocol | Monitored approved providers | Pilot findings within stated limits |
| Production | Institution-approved real data | Contracted/approved providers and operations | Production claim only after deployment/privacy/security clearance |

## 4. Service contracts

### Application commands

- One command owns each business transition.
- Transaction contains invariant checks, state changes, and mandatory audit evidence.
- Domain events are recorded before commit.
- Mail, documents, vector/model calls, and other side effects run after commit.
- Every command has command ID/idempotency behavior, actor/scope, typed failures, observability, and recovery.

### Queue and notifications

- Queue dispatch defaults to after-commit.
- Delivery is idempotent per event/recipient/channel.
- Retry/backoff and maximum attempts are explicit.
- Exhausted jobs remain visible with safe operator retry.
- No swallowed failure is treated as success.
- User-visible core state does not depend on a provider call completing inside the request transaction.

### Python inference

Proposed envelope:

- request ID and idempotency key;
- engine ID/version;
- input schema version and content hash;
- pseudonymous subject/attempt ID where required;
- bounded storage reference or validated payload;
- timeout/deadline;
- response schema version;
- output, confidence/uncertainty, warnings;
- model/runtime version and processing timestamps;
- typed failures: invalid input, unsupported version, unavailable, timeout, processing failure.

Authoritative score/decision state changes only after application-side validation and authorized human/system command.

### Documents and storage

- Private by default.
- Signed/authorized retrieval rather than public predictable paths.
- Typed templates, checksums, version, source aggregate, creator, and retention class.
- One template aggregate is authoritative per document type; system-setting and database-template paths cannot remain competing active implementations.
- Internal preview, generation, distribution/download, and physical print are distinct operations/evidence.
- Large report/document batches run as observable idempotent jobs with actor, selection hash, template/report version, per-artifact failures, and safe retry.
- Backup includes database and required files under one tested restore procedure.

## 5. Database and migration contract

Before schema implementation:

- approved conceptual domain model, ERD, states, cardinalities, and retention rules;
- explicit FK/delete, unique, index, and check-constraint catalog;
- transactional locking/versioning for capacity, uniqueness, and transitions;
- migration dependency order;
- no edited historical migration strategy in the new project—one clean canonical history;
- fresh migration/seed on empty production engine;
- rerun/idempotency where relevant;
- forward upgrade rehearsal from the previous released schema once releases exist;
- rollback policy and clean rollback test where safe;
- database plus file backup/restore evidence;
- live-catalog comparison against the approved inventory.

SQLite passing never substitutes for production-engine JSON, FK, locking, uniqueness, or concurrency evidence.

## 6. Development authority chain

`Approved founder decision → approved product/domain/security/research contract → ADR/architecture → executable story → OpenSpec behavior/tasks → tests/code/runtime evidence`

Rules:

- BMAD governs product, architecture, stories, and readiness.
- OpenSpec is subordinate per change and cannot revise approved upstream behavior silently.
- Context7/Laravel documentation supplies versioned technical evidence, not product policy.
- Legacy code supplies evidence and examples only.
- Every story cites requirement, workflow, state, schema/ADR, security/privacy, acceptance, failure/recovery, and evidence targets.

## 7. Required quality gates

### Per change

1. OpenSpec/change contract approved where required.
2. Focused failing test or explicit characterization/acceptance evidence exists.
3. Implementation changes only approved scope.
4. Focused tests pass.
5. Static/type/lint/format checks pass for changed scope.
6. Relevant integration/browser tests pass.
7. Diff reviewed for secrets, generated files, broad formatting, and unrelated work.
8. Traceability/evidence updated.

### Greenfield CI baseline

Required jobs:

- Composer validation and security audit;
- PHP syntax, Pint, Larastan/PHPStan;
- PHPUnit unit/feature/architecture suites;
- npm clean install and audit policy;
- svelte-check;
- ESLint source-only;
- Prettier check;
- Vitest with non-zero discovered tests where applicable;
- frontend production build;
- Playwright critical journey suite when routes exist;
- fresh MySQL migration/seed and catalog/invariant checks;
- Compose configuration and service-health validation;
- secret scanning and dependency/update reporting.

A command that discovers zero tests is not a passing test gate.

## 8. Test strategy

| Layer | Responsibility |
|---|---|
| Domain/unit | State transitions, value objects, transforms, scoring/model fixtures, template encoders |
| Feature/application | Commands, policies, validation, transactions, after-commit events, idempotency, failures |
| Database/integration | Constraints, locks, race tests, migrations, adapters, queue jobs, storage |
| Browser/E2E | Connected actor journeys, denied paths, recovery, refresh persistence, documents, console/network errors |
| Technical benchmark | OMR/RAG/model/audit/scheduler correctness and performance independent of TAM |
| Operations | Health/readiness, retries/dead letters, backup/restore, degraded sidecar/provider behavior |

## 9. Observability and operations

Minimum operational signals:

- request/correlation/command IDs;
- structured redacted logs;
- queue depth/failures/retries;
- notification terminal states;
- applicant notification projection freshness/read-state health;
- report/document batch state and protected-export audit coverage;
- local/remote knowledge-index divergence and reconciliation age;
- provider/sidecar health and latency;
- migration/schema version;
- audit-chain/checkpoint verification health;
- storage capacity and backup recency;
- explicit application, database, worker, scheduler, and inference readiness endpoints/signals.

Minimum recovery runbooks:

- failed notification/integration retry;
- stuck or duplicated processing operation;
- unavailable inference/RAG provider with manual/core fallback;
- score correction and release reversal;
- role/account compromise and session revocation;
- database/file restore;
- audit verification/checkpoint failure.

## 10. Repository/workspace boundary

Recommended:

- preserve the current repository/commit as the legacy evidence baseline;
- create the greenfield application in a clean repository or explicitly isolated clean worktree selected in D-PLAN-02;
- do not mix the existing 66 modified source files and untracked quality/runtime artifacts into the greenfield baseline;
- import only approved documents/reference fixtures through traceable changes;
- preserve historical code read-only after the baseline is frozen.

## 11. Implementation-entry evidence

The platform contract becomes implementation-ready only after:

- stack/runtime/database/test decisions are approved;
- Compose topology and environment matrix are approved;
- first vertical slice and schema contract are approved;
- clean install produces pinned lockfiles;
- scaffold boots and health checks pass;
- zero-baseline CI is green;
- MySQL migration/seed proof exists;
- no default secrets or generated build artifacts pollute source checks.

## 12. Decisions

- D-TECH-01 production/test database engines.
- D-TECH-02 PHPUnit versus Pest.
- D-TECH-03 Node runtime.
- D-TECH-04 Python worker and approved engines.
- D-TECH-05 queue/storage/cache topology.
- D-TECH-06 RAG/vector/LLM provider adapters.
- D-PLAN-02 greenfield repository/worktree location.
- D-OPS-01 transaction/after-commit/outbox baseline.
- D-OPS-02 logs, metrics, health, retry/dead-letter ownership.
- D-OPS-03 backup/restore and RPO/RTO targets before pilot.
- D-DOCUMENT-01 template authority, publication/print evidence, and batch-job semantics.
- D-REPORT-01 protected report/export and Program Head metric contract.
