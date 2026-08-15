---
stepsCompleted:
  - step-01-document-discovery
  - step-02-prd-analysis
  - step-03-epic-coverage-validation
  - step-04-ux-alignment
  - step-05-epic-quality-review
  - step-06-final-assessment
includedFiles:
  - docs/prd.md
  - docs/greenfield-foundation/architecture/ai-assistant-foundation/ARCHITECTURE-SPINE.md
  - docs/greenfield-foundation/architecture/ai-assistant-foundation/ARCHITECTURE-VISUAL.md
  - docs/greenfield-foundation/architecture/ai-assistant-foundation/READINESS-PLAN.md
  - docs/epics-and-stories-e2.md
  - docs/epics-and-stories-personnel.md
  - docs/legacy-reference/04-ui-ux-observed-baseline.md
supportingFiles:
  - docs/master-architecture-picture.md
  - docs/greenfield-architecture-plan.md
  - docs/architecture-audit.md
  - docs/nexiam-architecture-learning-guide.md
excludedFiles:
  - docs/greenfield-foundation/quarantine/
status: complete
overallReadiness: needs-work
architectureReadiness: ready-for-openspec
implementationReadiness: not-ready
---

# Implementation Readiness Assessment Report

**Date:** 2026-08-10
**Project:** NEXIAM — Nexi AI Assistant Foundation

## Document Discovery

### Authoritative documents

| Type | Files selected |
|---|---|
| PRD | `docs/prd.md` |
| Nexi architecture | `ARCHITECTURE-SPINE.md`, `ARCHITECTURE-VISUAL.md`, `READINESS-PLAN.md` under `docs/greenfield-foundation/architecture/ai-assistant-foundation/` |
| Epics and stories | `docs/epics-and-stories-e2.md`, `docs/epics-and-stories-personnel.md` |
| UX baseline | `docs/legacy-reference/04-ui-ux-observed-baseline.md` |

The root architecture plan, master architecture picture, architecture audit, and learning guide are supporting evidence. Quarantined documents are explicitly excluded from authority. No whole-versus-sharded duplicate set remains unresolved.

## PRD Analysis

The promoted PRD was read completely. It defines 76 functional requirements, 55 non-functional requirements, 83 business rules, and 32 connected-slice acceptance statements.

### Functional Requirements

- **FR-001** — NEXIAM shall support the ISPSC Tagudin admission-testing journey: application intake, controlled applicant access, examination scheduling and administration, scoring, result release, and post-examination counseling support. (GF-03 §1)
- **FR-002** — The system shall create a cycle-scoped admission attempt with validated identity, contact information, preferences, and duplicate controls. (GF-03 §4)
- **FR-003** — Registrar staff shall review admission attempts and correct, accept, dismiss, reopen, or archive them through explicit, bounded commands within their authority. (GF-03 §4; Roadmap E2)
- **FR-004** — The system shall maintain durable applicant identity and reference history across academic cycles. (GF-03 §4)
- **FR-005** — Applicants shall authenticate using Applicant Number/Reference followed by post-submission credential setup and recovery. (GF-03 §4)
- **FR-006** — Applicant credentials or access challenges shall be hashed. (Roadmap E1)
- **FR-007** — An Applicant Account may be created after submission, subject to the approved identity lifecycle. (Roadmap E1)
- **FR-008** — The authorized Test Administrator capability holder shall create and publish examination sessions. (GF-03 §4)
- **FR-009** — The authorized Test Administrator shall control whether applicant slot reservation is open. (GF-03 §4)
- **FR-010** — Applicants shall be able to reserve an examination slot only when applicant reservation has been opened. (GF-03 §8)
- **FR-011** — The authorized Test Administrator shall manually assign applicants to examinations, including late-assignment paths. (GF-03 §4)
- **FR-012** — A special Direct Assessment may bypass ordinary reservation and scheduling. (GF-03 §8)
- **FR-013** — The authorized Test Administrator shall assign contextual proctor duty to eligible staff for a specific examination session. (GF-03 §4)
- **FR-014** — Authorized session staff shall start and extend examination sessions. (Roadmap E2)
- **FR-015** — Authorized proctors shall record attendance and examination submission. (GF-03 §4; Roadmap E2)
- **FR-016** — Closing an examination shall open grading exactly once. (Roadmap E2)
- **FR-017** — Authorized Guidance staff shall enter and correct scores manually. (GF-03 §4)
- **FR-018** — The system shall enforce grading-completeness rules before scores can be finalized. (GF-03 §4)
- **FR-019** — Authorized staff shall review and finalize grading. (Roadmap E2)
- **FR-020** — The authorized Test Administrator shall publish face-to-face and online consultation availability. (GF-03 §4)
- **FR-021** — Guidance staff shall draft, confirm, or defer consultation outcomes. (Roadmap E2)
- **FR-022** — The Counselor or Test Administrator shall record consultation evidence and rationale. (GF-03 §4; GF-03 §8)
- **FR-023** — The system shall preserve counselor suggestions separately from applicant selections. (GF-03 §4)
- **FR-024** — Guidance staff shall prepare and print result documents where required. (GF-03 §4; Roadmap E2)
- **FR-025** — Authorized staff shall explicitly release results under approved rules. (GF-03 §4)
- **FR-026** — Authorized staff shall explicitly unrelease or reverse a release where policy permits. (GF-03 §4; Roadmap E2)
- **FR-027** — Result-release notification shall occur after the authoritative release transaction commits. (Roadmap E2)
- **FR-028** — Applicants shall retrieve released results through the applicant portal. (GF-03 §8; Roadmap E2)
- **FR-029** — The applicant portal shall show the applicant’s own submitted/accepted status, examination schedule, consultation availability, and released results. (GF-03 §4)
- **FR-030** — The applicant portal shall preserve own-data isolation. (GF-03 §4)
- **FR-031** — The system shall generate documents using safe, typed generation contracts. (GF-03 §4)
- **FR-032** — Notifications and other external deliveries shall use idempotent, after-commit delivery. (GF-03 §4)
- **FR-033** — The system shall implement the approved capability matrix with office, program, own-record, assigned-resource, and contextual-duty scopes. (GF-03 §4; Roadmap E1)
- **FR-034** — The system shall implement policy-matrix and denied-path tests for protected behavior. (GF-03 §4; Roadmap E1)
- **FR-035** — The system shall record actor-attributed audit events for identity, role, workflow, score, release, access, and configuration actions. (GF-03 §4)
- **FR-036** — Program Heads shall receive read-only, program-scoped aggregate visibility. (GF-03 §4)
- **FR-037** — Program Heads shall receive authorized, program-scoped applicant-record visibility, including result/remark sheets and converted per-domain scores. (GF-03 §4)
- **FR-038** — The connected journey shall expose its approved program-scoped aggregate consequence. (GF-03 §8)
- **FR-039** — OMR-assisted scoring shall operate against one approved answer-sheet/input contract. (GF-03 §4)
- **FR-040** — OMR uncertainty shall abstain to human review rather than silently commit a score. (GF-03 §4)
- **FR-041** — Manual scoring shall remain available whenever OMR is disabled, unavailable, uncertain, or not yet approved. (GF-03 §4; Roadmap E2/E3)
- **FR-042** — Scoped RAG shall provide cited institutional answers and approved own-data retrieval. (GF-03 §4)
- **FR-043** — Scoped RAG shall refuse unsupported or unauthorized requests and provide staff fallback. (GF-03 §4)
- **FR-044** — The RAG chatbot shall not provide placement advice. (GF-03 §4)
- **FR-045** — Scheduling assistance may produce constraint or feasibility suggestions, but an authorized human shall confirm the schedule. (GF-03 §4; Roadmap §2.4)
- **FR-046** — Manual scheduling shall provide a complete authoritative scheduling workflow. (GF-03 §4; Roadmap §2.4)
- **FR-047** — Offline proctoring shall use encrypted device scope, a durable outbox, ordered and idempotent replay, explicit conflict resolution, and visible degraded status. (GF-03 §4)
- **FR-048** — Counselor decision-support shall present independent aptitude and interest signals with versioned provenance. (GF-03 §4)
- **FR-049** — Counselors shall be able to approve, modify, override, or defer decision-support proposals. (GF-03 §4)
- **FR-050** — The conditional BGE service shall support interest-ranking as a low-priority, feature-flagged beta. (GF-03 §4)
- **FR-051** — Conditional YuKee integration shall load the available trained `.joblib` artifact and engine specifications with versioned loading, provenance, and deterministic expected-output fixtures. (GF-03 §4)
- **FR-052** — Conditional engine administration may extend beyond fixed approved adapters only after its evidence gate passes. (GF-03 §4)
- **FR-053** — Conditional program-quota management may extend Program Head functionality beyond read-only visibility only after its gate passes. (GF-03 §4)
- **FR-054** — Conditional in-app messaging may extend beyond a bounded RAG-refusal handoff only after routing, retention, and service-ownership rules are approved. (GF-03 §4; Roadmap E4)
- **FR-055** — Conditional analytics and reporting shall support role-scoped dashboards and reports with a metric dictionary, data scope and freshness, re-authenticated drill-down, accessible chart alternatives, and auditable exports. (GF-03 §4; Roadmap E4)
- **FR-056** — The system shall provide one shared, provider-agnostic AI capability boundary for RAG, model/OMR assistance, scheduling assistance, and future AI-assisted interfaces. (GF-03 §1; Roadmap §2.4)
- **FR-057** — The chatbot may guide an authorized actor through typed task requests equivalent to frontend operations. (GF-03 §1)
- **FR-058** — Assistant mutations shall execute through the same typed commands and policy gates as frontend mutations, with confirmation and audit. (Roadmap §2.4)
- **FR-059** — A future unified or dynamic import capability may create related records through one validated, staged, idempotent workflow. (GF-03 §1)
- **FR-060** — The target shall provide staff, applicant, proctor/session, and break-glass application shells. (Roadmap §2.5)
- **FR-061** — Navigation and breadcrumbs shall be authored by the server. (Roadmap §§2.3, 2.5)
- **FR-062** — The frontend shall provide typed shared page properties, design tokens/primitives, URL-backed list state, a complete `page_state` model, typed toast payloads, and a durable notification projection. (Roadmap §2.5)
- **FR-063** — The public homepage and application entry shall receive a controlled rebrand. (Roadmap §2.5)
- **FR-064** — The assistant personality and PNG-frame animation shall be feature-flagged and provide a static-frame fallback. (Roadmap §2.5)
- **FR-065** — E1 shall establish reference entities for cycles, application windows, programs, rooms, aptitude areas, templates, and configuration. (Roadmap E1)
- **FR-066** — E1 shall establish AI ports/envelopes, a feature flag, a disabled or degraded assistant slot, and audit/idempotency hooks without implementing the AI SDK or RAG. (Roadmap E1)
- **FR-067** — The application shall expose separate health and readiness states. (Roadmap §§2.6, E0)
- **FR-068** — Queue execution shall expose success and failure visibility. (Roadmap E0)
- **FR-069** — Local/test operation shall provide Mailpit and private-storage evidence. (Roadmap E0)
- **FR-070** — The optional inference seam shall degrade without disabling the manual core workflow. (Roadmap E0)
- **FR-071** — The system shall provide deterministic synthetic seeding and reset. (Roadmap E0)
- **FR-072** — The system shall provide operation, idempotency, and audit foundation seams. (Roadmap E0)
- **FR-073** — Broader notification preferences, additional documents, and additional imports may be added as independently gated E4 capabilities. (Roadmap E4)
- **FR-074** — A controlled role-based technical pilot shall execute frozen TAM scenarios and record defects and Go/No-Go outcomes before broad evaluation. (GF-03 §7; Roadmap E5)
- **FR-075** — Approved pilot/production operation shall provide deployment health and observability, backup and restore, outage and incident runbooks, key/secret operations, staff training/support ownership, and release/rollback procedures. (Roadmap E6)
- **FR-076** — Authorized Staffing/HR Administrators (and office actors within D-020 ceilings) shall list, create, update, deactivate, and activate staff personnel accounts with approved permanent and support role helpers, hashed credentials, and personnel-lifecycle audit — without granting Test Administrator or Proctor authority through personnel commands. (GF-04 CAP-029/030; D-014/D-017/D-020; BR-012)

**Total functional requirements: 76.**

### Non-Functional Requirements

- **NFR-001** — The product shall provide a complete, auditable institutional workflow with computer-vision and decision-support assistance kept under human authority. (GF-03 §1)
- **NFR-002** — Role isolation shall prevent broad or unauthorized data visibility. (GF-03 §§1–2)
- **NFR-003** — Authorization shall default to deny and combine capability, resource scope, and contextual duty. (Roadmap §2.3)
- **NFR-004** — Browser UI visibility, hidden controls, disabled controls, and client-side role checks shall never be authorization authority. (Roadmap §2.3)
- **NFR-005** — PostgreSQL constraints and locks shall be the integrity authority for uniqueness, capacity, effective deadlines, and version conflicts. (Roadmap §2.2)
- **NFR-006** — One application command shall own each business transition. (Roadmap §2.2)
- **NFR-007** — Workflow stage shall be a projection and not a second mutable source of truth. (Roadmap §2.2)
- **NFR-008** — Critical mutations shall atomically commit the invariant, state change, and mandatory audit event in one transaction. (Roadmap §2.2)
- **NFR-009** — Mail, notifications, provider calls, and other external effects shall execute only after the authoritative transaction commits. (Roadmap §2.2)
- **NFR-010** — Dangerous commands, jobs, imports, assistant confirmations, notifications, deliveries, and reversals shall be idempotent. (Roadmap §2.2)
- **NFR-011** — Business conflicts shall produce typed failures rather than generic HTTP 500 responses. (Roadmap §2.2)
- **NFR-012** — Provider outages shall degrade visibly while leaving the manual core workflow operable. (Roadmap §2.2)
- **NFR-013** — Private files shall use checksums, versions, and quarantine controls. (Roadmap §2.3)
- **NFR-014** — Public artifact URLs shall not be treated as access authority. (Roadmap §2.3)
- **NFR-015** — Exports shall be formula-safe. (Roadmap §2.3)
- **NFR-016** — Audit claims shall remain within approved threat-model wording, and checkpoint failure shall not rewrite history. (Roadmap §2.3)
- **NFR-017** — Authorization shall occur before RAG retrieval. (Roadmap §2.4)
- **NFR-018** — RAG shall separate policy-corpus, own-data, and typed-task contexts. (Roadmap §2.4)
- **NFR-019** — Citations and refusal behavior shall be mandatory for RAG answers. (Roadmap §2.4)
- **NFR-020** — Models and workers shall return observations or proposals and shall never write authoritative domain state. (Roadmap §2.4)
- **NFR-021** — AI outputs shall remain supporting evidence or assistance; approved human/application commands shall remain authoritative. (GF-03 §1)
- **NFR-022** — The AI/chatbot boundary shall provide no direct database, policy-bypass, or hidden command path. (GF-03 §1)
- **NFR-023** — Import workflows shall be governed by domain ownership, authorization, transaction, error-recovery, and audit design. (GF-03 §1)
- **NFR-024** — Accessibility and responsive behavior shall be product requirements for every implemented scope. (Roadmap §2.5)
- **NFR-025** — Implemented scope shall meet WCAG 2.2 AA behavior. (Roadmap §2.5)
- **NFR-026** — The frontend shall respect `prefers-reduced-motion`. (Roadmap §2.5)
- **NFR-027** — Assistant animation shall be presentation-only, shall never communicate authority, and shall never replace textual state. (Roadmap §2.5)
- **NFR-028** — The runtime topology shall use health-chained app, queue-worker, scheduler, PostgreSQL, private-storage, Mailpit local/test, and optional authenticated inference services. (Roadmap §2.6)
- **NFR-029** — Requests, commands, and operations shall carry correlation identifiers (`request_id`, `command_id`, and `operation_id`). (Roadmap §2.6)
- **NFR-030** — Backup and restore shall be rehearsed, not merely documented. (Roadmap §§2.6, E0)
- **NFR-031** — Retention holds shall be supported by the data and operations foundation. (Roadmap §§2.6, E1)
- **NFR-032** — Queue backend, object storage, cache, vector store, worker count, and service extraction shall change only through an ADR justified by measured evidence. (Roadmap §2.6)
- **NFR-033** — The application shall begin as a modular monolith with module-owned aggregates, command/query boundaries, dependency-direction tests, and no cross-module ORM mutation shortcuts. (Roadmap §2.1)
- **NFR-034** — The target shall use fresh migrations and shall never depend at runtime on legacy migrations, routes, controllers, databases, or storage. (Roadmap §2.1)
- **NFR-035** — E0 shall use real Pest, Vitest, and Playwright test discovery and shall configure Pint, PHPStan, ESLint, and Prettier without inherited suppressions. (Roadmap E0)
- **NFR-036** — Architecture-boundary tests shall enforce module and dependency boundaries. (Roadmap E0)
- **NFR-037** — A new engineer shall be able to clone, boot, migrate, seed, reset, test, build, and inspect health and queue evidence without touching legacy. (Roadmap E0 exit)
- **NFR-038** — Cross-scope denial, credential lifecycle, replay, stale-version, audit, shell, responsive, and accessibility evidence shall pass before E1 exits. (Roadmap E1 exit)
- **NFR-039** — All 32 `REQ-VS-*` requirements shall map to executable tests or runtime evidence, not merely a happy-path render. (Roadmap E2 exit)
- **NFR-040** — Every E4 capability shall have its own requirements, acceptance evidence, security/privacy evidence, and operations evidence. (Roadmap E4 exit)
- **NFR-041** — OMR promotion shall require an approved input, ground truth, confidence model, human-review workflow, and benchmark. (Roadmap R4/E3)
- **NFR-042** — RAG promotion shall require corpus ownership/versioning, authorization-before-retrieval, citations, refusal, privacy, prompt-injection, and outage tests. (Roadmap E3)
- **NFR-043** — Scheduling assistance promotion shall require a measurable constraint/feasibility spike. (Roadmap E3)
- **NFR-044** — YuKee, BGE, or other model promotion shall require a reproducible artifact or an explicitly honest baseline and proposal-only worker behavior. (Roadmap E3)
- **NFR-045** — Audit-chain promotion shall require validated canonicalization, concurrency, checkpoint, threat-model, key-management, and failure behavior. (Roadmap R4)
- **NFR-046** — Every high-risk spike shall produce a measurable pass/fail report; failure shall activate a documented fallback or scope change. (Roadmap R4)
- **NFR-047** — Prototype output shall not be represented as production code. (Roadmap R4)
- **NFR-048** — Planning coherence shall require agreement among product, role, workflow, domain, research, security, and architecture documents. (GF-03 §6)
- **NFR-049** — Every committed requirement shall have evidence, a decision, an acceptance scenario, and a future test target. (GF-03 §6)
- **NFR-050** — No P0 contradiction may remain open for planning coherence to pass. (GF-03 §6)
- **NFR-051** — A controlled demonstration shall reproduce a complete admission-to-release journey from a clean scaffold and seeded dataset. (GF-03 §6)
- **NFR-052** — Controlled-demonstration evidence shall include allowed and denied authorization paths, working manual fallbacks, bounded differentiator tests, executable role-specific TAM scenarios, and disclosed limitations and synthetic/sanitized evidence. (GF-03 §6)
- **NFR-053** — Pilot/production clearance shall require approved real-data handling, retention, backup, recovery, incident ownership, representative evidence, observability, security, restore, staff training, support, and operational ownership. (GF-03 §6; Roadmap E6)
- **NFR-054** — Technical evidence and TAM/usability evidence shall remain separate, with limitations and non-claims explicit. (Roadmap E5)
- **NFR-055** — Result and notification feedback shall use truthful pending/sent/released wording and shall not claim provider success without evidence. (Roadmap risk controls)

**Total non-functional requirements: 55.**

### Additional Requirements

- REQ-VS-001–REQ-VS-032 bind the connected admission-to-release slice, including authorization, state completeness, responsive/accessibility behavior, degraded operation, idempotency, and manual fallbacks.
- BR-001–BR-083 govern authority ordering, capability limits, AI gates, provider isolation, phased delivery, evidence, and stop conditions.
- Nexi provider work, RAG, embeddings, OMR promotion, and scheduling assistance remain E3 capabilities requiring separate evidence gates and OpenSpecs. E0–E2 authorize only ports, flags, degraded UI seams, and typed-command hooks.
- AI may guide authorized actors through frontend-equivalent typed requests, but it may not gain a direct database path, hidden authority, or autonomous admission/placement/release power.

### PRD Completeness Assessment

The PRD is sufficient to authorize architecture planning for a shared AI boundary and future staff/applicant assistance. It does not yet provide implementation-ready Nexi stories, a complete surface-by-mode matrix, knowledge-administration lifecycle UX, a reusable skill catalog, or frontend integration contracts. Those gaps must be supplied by the architecture/readiness package and promoted through a dedicated E3 OpenSpec before provider-backed implementation.

## Epic Coverage Validation

The two selected epic/story files were read completely. Their explicit coverage claims were compared with all 76 PRD functional requirements.

### Coverage Matrix

| FR | PRD requirement | Epic/story coverage | Status |
|---|---|---|---|
| FR-001 | NEXIAM shall support the ISPSC Tagudin admission-testing journey: application intake, controlled applicant access, examination scheduling and administration, scoring, result release, and post-examination counseling support. (GF-03 §1) | E2-S02 | ✓ Covered |
| FR-002 | The system shall create a cycle-scoped admission attempt with validated identity, contact information, preferences, and duplicate controls. (GF-03 §4) | E2-E2 | ✓ Covered |
| FR-003 | Registrar staff shall review admission attempts and correct, accept, dismiss, reopen, or archive them through explicit, bounded commands within their authority. (GF-03 §4; Roadmap E2) | E2-E2 | ✓ Covered |
| FR-004 | The system shall maintain durable applicant identity and reference history across academic cycles. (GF-03 §4) | E2-E2 | ✓ Covered |
| FR-005 | Applicants shall authenticate using Applicant Number/Reference followed by post-submission credential setup and recovery. (GF-03 §4) | E2-E2 | ✓ Covered |
| FR-006 | Applicant credentials or access challenges shall be hashed. (Roadmap E1) | E2-E2 | ✓ Covered |
| FR-007 | An Applicant Account may be created after submission, subject to the approved identity lifecycle. (Roadmap E1) | E2-E2 | ✓ Covered |
| FR-008 | The authorized Test Administrator capability holder shall create and publish examination sessions. (GF-03 §4) | E2-E3 | ✓ Covered |
| FR-009 | The authorized Test Administrator shall control whether applicant slot reservation is open. (GF-03 §4) | E2-E3 | ✓ Covered |
| FR-010 | Applicants shall be able to reserve an examination slot only when applicant reservation has been opened. (GF-03 §8) | E2-E3 | ✓ Covered |
| FR-011 | The authorized Test Administrator shall manually assign applicants to examinations, including late-assignment paths. (GF-03 §4) | E2-E3 | ✓ Covered |
| FR-012 | A special Direct Assessment may bypass ordinary reservation and scheduling. (GF-03 §8) | E2-E3 | ✓ Covered |
| FR-013 | The authorized Test Administrator shall assign contextual proctor duty to eligible staff for a specific examination session. (GF-03 §4) | E2-E3 | ✓ Covered |
| FR-014 | Authorized session staff shall start and extend examination sessions. (Roadmap E2) | E2-E3 | ✓ Covered |
| FR-015 | Authorized proctors shall record attendance and examination submission. (GF-03 §4; Roadmap E2) | E2-E3 | ✓ Covered |
| FR-016 | Closing an examination shall open grading exactly once. (Roadmap E2) | E2-E3 | ✓ Covered |
| FR-017 | Authorized Guidance staff shall enter and correct scores manually. (GF-03 §4) | E2-E4 | ✓ Covered |
| FR-018 | The system shall enforce grading-completeness rules before scores can be finalized. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-019 | Authorized staff shall review and finalize grading. (Roadmap E2) | E2-E5 | ✓ Covered |
| FR-020 | The authorized Test Administrator shall publish face-to-face and online consultation availability. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-021 | Guidance staff shall draft, confirm, or defer consultation outcomes. (Roadmap E2) | E2-E5 | ✓ Covered |
| FR-022 | The Counselor or Test Administrator shall record consultation evidence and rationale. (GF-03 §4; GF-03 §8) | E2-E5 | ✓ Covered |
| FR-023 | The system shall preserve counselor suggestions separately from applicant selections. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-024 | Guidance staff shall prepare and print result documents where required. (GF-03 §4; Roadmap E2) | E2-E5 | ✓ Covered |
| FR-025 | Authorized staff shall explicitly release results under approved rules. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-026 | Authorized staff shall explicitly unrelease or reverse a release where policy permits. (GF-03 §4; Roadmap E2) | E2-E5 | ✓ Covered |
| FR-027 | Result-release notification shall occur after the authoritative release transaction commits. (Roadmap E2) | E2-E5 | ✓ Covered |
| FR-028 | Applicants shall retrieve released results through the applicant portal. (GF-03 §8; Roadmap E2) | E2-E5 | ✓ Covered |
| FR-029 | The applicant portal shall show the applicant’s own submitted/accepted status, examination schedule, consultation availability, and released results. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-030 | The applicant portal shall preserve own-data isolation. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-031 | The system shall generate documents using safe, typed generation contracts. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-032 | Notifications and other external deliveries shall use idempotent, after-commit delivery. (GF-03 §4) | E2-E5 | ✓ Covered |
| FR-033 | The system shall implement the approved capability matrix with office, program, own-record, assigned-resource, and contextual-duty scopes. (GF-03 §4; Roadmap E1) | E2-E6 | ✓ Covered |
| FR-034 | The system shall implement policy-matrix and denied-path tests for protected behavior. (GF-03 §4; Roadmap E1) | E2-S20 | ✓ Covered |
| FR-035 | The system shall record actor-attributed audit events for identity, role, workflow, score, release, access, and configuration actions. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-036 | Program Heads shall receive read-only, program-scoped aggregate visibility. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-037 | Program Heads shall receive authorized, program-scoped applicant-record visibility, including result/remark sheets and converted per-domain scores. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-038 | The connected journey shall expose its approved program-scoped aggregate consequence. (GF-03 §8) | **NOT FOUND** | ❌ Missing |
| FR-039 | OMR-assisted scoring shall operate against one approved answer-sheet/input contract. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-040 | OMR uncertainty shall abstain to human review rather than silently commit a score. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-041 | Manual scoring shall remain available whenever OMR is disabled, unavailable, uncertain, or not yet approved. (GF-03 §4; Roadmap E2/E3) | E2-E4 | ✓ Covered |
| FR-042 | Scoped RAG shall provide cited institutional answers and approved own-data retrieval. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-043 | Scoped RAG shall refuse unsupported or unauthorized requests and provide staff fallback. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-044 | The RAG chatbot shall not provide placement advice. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-045 | Scheduling assistance may produce constraint or feasibility suggestions, but an authorized human shall confirm the schedule. (GF-03 §4; Roadmap §2.4) | **NOT FOUND** | ❌ Missing |
| FR-046 | Manual scheduling shall provide a complete authoritative scheduling workflow. (GF-03 §4; Roadmap §2.4) | **NOT FOUND** | ❌ Missing |
| FR-047 | Offline proctoring shall use encrypted device scope, a durable outbox, ordered and idempotent replay, explicit conflict resolution, and visible degraded status. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-048 | Counselor decision-support shall present independent aptitude and interest signals with versioned provenance. (GF-03 §4) | E2-S13 | ✓ Covered |
| FR-049 | Counselors shall be able to approve, modify, override, or defer decision-support proposals. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-050 | The conditional BGE service shall support interest-ranking as a low-priority, feature-flagged beta. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-051 | Conditional YuKee integration shall load the available trained `.joblib` artifact and engine specifications with versioned loading, provenance, and deterministic expected-output fixtures. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-052 | Conditional engine administration may extend beyond fixed approved adapters only after its evidence gate passes. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-053 | Conditional program-quota management may extend Program Head functionality beyond read-only visibility only after its gate passes. (GF-03 §4) | **NOT FOUND** | ❌ Missing |
| FR-054 | Conditional in-app messaging may extend beyond a bounded RAG-refusal handoff only after routing, retention, and service-ownership rules are approved. (GF-03 §4; Roadmap E4) | **NOT FOUND** | ❌ Missing |
| FR-055 | Conditional analytics and reporting shall support role-scoped dashboards and reports with a metric dictionary, data scope and freshness, re-authenticated drill-down, accessible chart alternatives, and auditable exports. (GF-03 §4; Roadmap E4) | **NOT FOUND** | ❌ Missing |
| FR-056 | The system shall provide one shared, provider-agnostic AI capability boundary for RAG, model/OMR assistance, scheduling assistance, and future AI-assisted interfaces. (GF-03 §1; Roadmap §2.4) | **NOT FOUND** | ❌ Missing |
| FR-057 | The chatbot may guide an authorized actor through typed task requests equivalent to frontend operations. (GF-03 §1) | **NOT FOUND** | ❌ Missing |
| FR-058 | Assistant mutations shall execute through the same typed commands and policy gates as frontend mutations, with confirmation and audit. (Roadmap §2.4) | **NOT FOUND** | ❌ Missing |
| FR-059 | A future unified or dynamic import capability may create related records through one validated, staged, idempotent workflow. (GF-03 §1) | **NOT FOUND** | ❌ Missing |
| FR-060 | The target shall provide staff, applicant, proctor/session, and break-glass application shells. (Roadmap §2.5) | **NOT FOUND** | ❌ Missing |
| FR-061 | Navigation and breadcrumbs shall be authored by the server. (Roadmap §§2.3, 2.5) | **NOT FOUND** | ❌ Missing |
| FR-062 | The frontend shall provide typed shared page properties, design tokens/primitives, URL-backed list state, a complete `page_state` model, typed toast payloads, and a durable notification projection. (Roadmap §2.5) | E2-E6 | ✓ Covered |
| FR-063 | The public homepage and application entry shall receive a controlled rebrand. (Roadmap §2.5) | **NOT FOUND** | ❌ Missing |
| FR-064 | The assistant personality and PNG-frame animation shall be feature-flagged and provide a static-frame fallback. (Roadmap §2.5) | **NOT FOUND** | ❌ Missing |
| FR-065 | E1 shall establish reference entities for cycles, application windows, programs, rooms, aptitude areas, templates, and configuration. (Roadmap E1) | E2-E1 / E2-S01 | ✓ Covered |
| FR-066 | E1 shall establish AI ports/envelopes, a feature flag, a disabled or degraded assistant slot, and audit/idempotency hooks without implementing the AI SDK or RAG. (Roadmap E1) | **NOT FOUND** | ❌ Missing |
| FR-067 | The application shall expose separate health and readiness states. (Roadmap §§2.6, E0) | **NOT FOUND** | ❌ Missing |
| FR-068 | Queue execution shall expose success and failure visibility. (Roadmap E0) | **NOT FOUND** | ❌ Missing |
| FR-069 | Local/test operation shall provide Mailpit and private-storage evidence. (Roadmap E0) | **NOT FOUND** | ❌ Missing |
| FR-070 | The optional inference seam shall degrade without disabling the manual core workflow. (Roadmap E0) | **NOT FOUND** | ❌ Missing |
| FR-071 | The system shall provide deterministic synthetic seeding and reset. (Roadmap E0) | **NOT FOUND** | ❌ Missing |
| FR-072 | The system shall provide operation, idempotency, and audit foundation seams. (Roadmap E0) | **NOT FOUND** | ❌ Missing |
| FR-073 | Broader notification preferences, additional documents, and additional imports may be added as independently gated E4 capabilities. (Roadmap E4) | **NOT FOUND** | ❌ Missing |
| FR-074 | A controlled role-based technical pilot shall execute frozen TAM scenarios and record defects and Go/No-Go outcomes before broad evaluation. (GF-03 §7; Roadmap E5) | **NOT FOUND** | ❌ Missing |
| FR-075 | Approved pilot/production operation shall provide deployment health and observability, backup and restore, outage and incident runbooks, key/secret operations, staff training/support ownership, and release/rollback procedures. (Roadmap E6) | **NOT FOUND** | ❌ Missing |
| FR-076 | Authorized Staffing/HR Administrators (and office actors within D-020 ceilings) shall list, create, update, deactivate, and activate staff personnel accounts with approved permanent and support role helpers, hashed credentials, and personnel-lifecycle audit — without granting Test Administrator or Proctor authority through personnel commands. (GF-04 CAP-029/030; D-014/D-017/D-020; BR-012) | P-E1 / P-S01–P-S04 | ✓ Covered |

### Missing Requirements

#### Critical for the Nexi foundation and safe frontend integration

- **FR-035** — The system shall record actor-attributed audit events for identity, role, workflow, score, release, access, and configuration actions. (GF-03 §4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-042** — Scoped RAG shall provide cited institutional answers and approved own-data retrieval. (GF-03 §4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-043** — Scoped RAG shall refuse unsupported or unauthorized requests and provide staff fallback. (GF-03 §4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-044** — The RAG chatbot shall not provide placement advice. (GF-03 §4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-045** — Scheduling assistance may produce constraint or feasibility suggestions, but an authorized human shall confirm the schedule. (GF-03 §4; Roadmap §2.4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-056** — The system shall provide one shared, provider-agnostic AI capability boundary for RAG, model/OMR assistance, scheduling assistance, and future AI-assisted interfaces. (GF-03 §1; Roadmap §2.4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-057** — The chatbot may guide an authorized actor through typed task requests equivalent to frontend operations. (GF-03 §1) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-058** — Assistant mutations shall execute through the same typed commands and policy gates as frontend mutations, with confirmation and audit. (Roadmap §2.4) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-061** — Navigation and breadcrumbs shall be authored by the server. (Roadmap §§2.3, 2.5) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-064** — The assistant personality and PNG-frame animation shall be feature-flagged and provide a static-frame fallback. (Roadmap §2.5) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-066** — E1 shall establish AI ports/envelopes, a feature flag, a disabled or degraded assistant slot, and audit/idempotency hooks without implementing the AI SDK or RAG. (Roadmap E1) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-067** — The application shall expose separate health and readiness states. (Roadmap §§2.6, E0) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.
- **FR-072** — The system shall provide operation, idempotency, and audit foundation seams. (Roadmap E0) Recommendation: add an AI-assistant-foundation epic/story with actor, surface, capability, command/query, fallback, audit, and executable acceptance coverage.

#### Other requirements without an explicit epic/story path

- **FR-036** — Program Heads shall receive read-only, program-scoped aggregate visibility. (GF-03 §4)
- **FR-037** — Program Heads shall receive authorized, program-scoped applicant-record visibility, including result/remark sheets and converted per-domain scores. (GF-03 §4)
- **FR-038** — The connected journey shall expose its approved program-scoped aggregate consequence. (GF-03 §8)
- **FR-039** — OMR-assisted scoring shall operate against one approved answer-sheet/input contract. (GF-03 §4)
- **FR-040** — OMR uncertainty shall abstain to human review rather than silently commit a score. (GF-03 §4)
- **FR-046** — Manual scheduling shall provide a complete authoritative scheduling workflow. (GF-03 §4; Roadmap §2.4)
- **FR-047** — Offline proctoring shall use encrypted device scope, a durable outbox, ordered and idempotent replay, explicit conflict resolution, and visible degraded status. (GF-03 §4)
- **FR-049** — Counselors shall be able to approve, modify, override, or defer decision-support proposals. (GF-03 §4)
- **FR-050** — The conditional BGE service shall support interest-ranking as a low-priority, feature-flagged beta. (GF-03 §4)
- **FR-051** — Conditional YuKee integration shall load the available trained `.joblib` artifact and engine specifications with versioned loading, provenance, and deterministic expected-output fixtures. (GF-03 §4)
- **FR-052** — Conditional engine administration may extend beyond fixed approved adapters only after its evidence gate passes. (GF-03 §4)
- **FR-053** — Conditional program-quota management may extend Program Head functionality beyond read-only visibility only after its gate passes. (GF-03 §4)
- **FR-054** — Conditional in-app messaging may extend beyond a bounded RAG-refusal handoff only after routing, retention, and service-ownership rules are approved. (GF-03 §4; Roadmap E4)
- **FR-055** — Conditional analytics and reporting shall support role-scoped dashboards and reports with a metric dictionary, data scope and freshness, re-authenticated drill-down, accessible chart alternatives, and auditable exports. (GF-03 §4; Roadmap E4)
- **FR-059** — A future unified or dynamic import capability may create related records through one validated, staged, idempotent workflow. (GF-03 §1)
- **FR-060** — The target shall provide staff, applicant, proctor/session, and break-glass application shells. (Roadmap §2.5)
- **FR-063** — The public homepage and application entry shall receive a controlled rebrand. (Roadmap §2.5)
- **FR-068** — Queue execution shall expose success and failure visibility. (Roadmap E0)
- **FR-069** — Local/test operation shall provide Mailpit and private-storage evidence. (Roadmap E0)
- **FR-070** — The optional inference seam shall degrade without disabling the manual core workflow. (Roadmap E0)
- **FR-071** — The system shall provide deterministic synthetic seeding and reset. (Roadmap E0)
- **FR-073** — Broader notification preferences, additional documents, and additional imports may be added as independently gated E4 capabilities. (Roadmap E4)
- **FR-074** — A controlled role-based technical pilot shall execute frozen TAM scenarios and record defects and Go/No-Go outcomes before broad evaluation. (GF-03 §7; Roadmap E5)
- **FR-075** — Approved pilot/production operation shall provide deployment health and observability, backup and restore, outage and incident runbooks, key/secret operations, staff training/support ownership, and release/rollback procedures. (Roadmap E6)

The latter group includes future-gated OMR, offline, decision-support, analytics, pilot, operations, and conditional capabilities. Missing does not mean they should be implemented in the Nexi cut; it means their defer/gate or later implementation path must become explicit rather than relying on the PRD alone.

### Coverage Statistics

- Total PRD FRs: 76
- Explicitly covered by the selected epic/story package: 39
- Missing an explicit epic/story path: 37
- Coverage: 51.3%

**Readiness finding:** The existing E2 stories are coherent for the connected manual slice, but they are not a Nexi implementation handoff. A dedicated AI-assistant-foundation epic/story package is mandatory before junior implementation. It must cover the staff and applicant surfaces, inquiry/assistant modes, knowledge administration, skill execution, usage controls, provider degradation, server-owned navigation/page context, audit, idempotency, and manual fallback.

## UX Alignment Assessment

### UX Document Status

UX authority exists. The selected legacy baseline records observed interaction evidence, while GF-22 and the locked UI-kit start set define the target UX contract. The existing staff Nexi dock implements the locked visual seam in degraded/demo form.

### Alignment Confirmed

- PRD and GF-22 agree that staff and applicant shells are distinct, page truth is server-owned, and hidden controls are never authorization.
- Inquiry, own-data, staff-task proposals, citation/refusal, confirmation, degraded-provider behavior, responsive design, WCAG 2.2 AA evidence, and manual fallbacks are all represented in the product/UX contracts.
- The existing `NexiAssistPanel` already demonstrates the wrap-island chrome, mode selector, proposal gate, usage indicator, and static/degraded behavior without pretending that provider execution exists.
- The architecture spine preserves same-command parity, route-scoped page packs, deny-dominant policy, confirmation, idempotency, audit, and separate knowledge retrieval.

### Alignment Issues

1. **Applicant shell gap:** `ApplicantLayout.svelte` does not mount Nexi and lacks the GF-22 applicant disclosure contract for policy versus released own-data context.
2. **Client authority gap:** current `nexi-context.js` and `nexi-actions.js` derive page context and actions client-side. They are useful stubs, but production authority must move to signed/server-owned surface and page-pack props.
3. **Default-mode mismatch:** the demo component defaults to Assistant, while the architecture requires Inquiry by default and explicit consent before enabling Assistant tools.
4. **Mode-transition gap:** no trustworthy UI state exists for model-requested escalation from Inquiry to Assistant. The assistant must request user consent; it cannot silently switch itself.
5. **Knowledge administration gap:** no approved source list, upload/editor, extraction preview, validation, publish, supersede, revoke, re-index, citation-test, failure, or recovery UI is specified at implementation detail.
6. **Usage/admin gap:** the usage ring is presentation-only. There is no typed server contract for allowance, reservation, consumption, reconciliation, limit reached, admin policy, actor override, or provider-budget state.
7. **Conversation gap:** there is no approved conversation persistence/TTL/clear/export policy, stream reconnect contract, or multi-tab/context-change behavior.
8. **Proposal UX gap:** the demo gate acknowledges a stub. Production needs immutable proposal summaries, changed-target counts, warnings, expiry, stale-rule/version handling, cancel/confirm, queued/unknown outcomes, and reconciliation.
9. **Surface inventory gap:** setup, personnel, admissions, grading, consultation/release, reports, audit, knowledge administration, operations, and all applicant journeys do not yet have page-by-page Nexi mode and skill decisions.

### Warnings

- The frontend may be stitched now only as a typed disabled/degraded shell. Provider calls, real RAG, real tools, and applicant own-data answers remain blocked until the E3 ADR/OpenSpec and privacy/evidence gates pass.
- The applicant surface must use its own `SurfaceProfile` and threat model; sharing the component is safe, sharing staff packs, skills, prompts, or data projections is not.
- Knowledge CRUD is not ordinary file CRUD. Published sources require versioned governance, ownership, approval, revocation, index status, and citation reproducibility.

## Epic Quality Review

### Critical Violations

1. **No Nexi delivery epic exists.** E2-S21 records RAG as a gated hold, but no later user-value epic decomposes global Inquiry, applicant Inquiry, staff Assistant, knowledge administration, usage administration, or reusable skills. Architecture cannot substitute for implementable stories.
2. **E2-E1 is a technical foundation epic.** Deterministic seed/reset is essential evidence, but its title and single story describe delivery infrastructure rather than an actor outcome. Keep it as an enabling story attached to the first executable journey or explicitly classify it as an engineering-enabler exception.
3. **E2-E7 is not a user-value epic.** It is a governance hold. Preserve it in the gate/traceability register, not as if it were an implementable product epic.
4. **E2-S12 is intentionally ambiguous.** “Implement a minimal import seam or defer UI” has two incompatible completion outcomes and cannot produce one deterministic acceptance verdict.
5. **E2-S17 is epic-sized.** Requiring every journey page and every page state in one story prevents independent completion and makes ownership/test evidence unclear.

### Major Issues

1. **Acceptance depth is inconsistent.** Many stories contain only one compressed GWT line and omit denied, validation, conflict, retry, degraded, responsive, accessibility, idempotency, and audit cases required by BR-072/GF-22.
2. **E2-S15 combines three independently failure-prone outcomes:** authoritative release, asynchronous delivery, and applicant retrieval. Split the command, delivery/reconciliation, and portal visibility stories while retaining one connected browser journey.
3. **E2-S13 mixes manual consultation with FR-048 decision-support language.** The manual consultation story must stand alone; provider/model signals belong to a gated later story.
4. **Cross-cutting evidence stories obscure feature ownership.** Page states, breadcrumbs, confirmations, audit, and accessibility must also be acceptance criteria on each owning feature story, not deferred to E2-E6.
5. **Personnel P-S03–P-S05 are underspecified.** They lack complete FR/BR/capability/page/state/transaction/idempotency/audit/test mappings and full Given/When/Then criteria.
6. **No junior-ready dependency map exists for Nexi.** The future implementation order must make the shared shell, surface profile, policy kernel, knowledge administration, Inquiry, Assistant proposals, confirmation execution, and later skills independently testable without forward dependencies.

### Minor Concerns

- Some story identifiers and page references use shorthand without stable links to route/page catalogs.
- Test statements sometimes say “pending” or name a broad suite without exact evidence targets.
- Seed/demo credentials, recreated states, and exact URLs are not recorded in these story files; the eventual OpenSpec handoff must supply them.

### Required Remediation

- Create a dedicated AI-assistant-foundation epic/story package organized by user value and bounded vertical slices.
- Make the first slice a fully functional disabled/degraded shell and server-owned surface/page-pack contract; do not make “install SDK” an epic.
- Follow with knowledge-source administration and policy Inquiry, then applicant policy/released-own-data Inquiry, then one staff page Assistant proposal/confirmation, then reusable skills and additional page packs.
- Give every story explicit actors, surface, capability, data scope, action/query contracts, states, privacy classification, idempotency/audit, fallback, seed/demo path, and Pest/Vitest/Playwright evidence.
- Split every story that can end with multiple independent authoritative outcomes or multiple separate recovery paths.

### Dependency Verdict

The current E2 story order has no material forward dependency in its manual connected journey. It is **not sufficient for Nexi implementation** because Nexi’s future dependency graph and stories are absent rather than incorrectly ordered.

## Summary and Recommendations

### Overall Readiness Status

**NEEDS WORK.** NEXIAM is ready to finalize the Nexi architecture and draft its dedicated OpenSpec. It is not yet ready for a junior developer to implement provider-backed Nexi, RAG, applicant own-data answers, or command execution without making unauthorized product and security decisions.

The existing degraded staff chrome may be preserved and refactored behind typed contracts. Live AI implementation remains blocked until the architecture additions, E3 ADRs/evidence gates, stories, and OpenSpec are approved.

### Critical Issues Requiring Immediate Action

1. Establish separate server-owned staff, applicant, proctor, and administration `SurfaceProfile` contracts; never infer authority from the current URL or browser capability list.
2. Publish a complete page × mode × context × skill matrix across every in-scope staff and applicant route family.
3. Define knowledge-source administration as a governed version lifecycle, including ownership, approval, extraction validation, publish, supersede, revoke, indexing, citation tests, and recovery.
4. Define the frontend protocol for context epochs, streaming, cancellation, reconnect, conversation retention/clear, proposals, confirmations, stale-rule/version outcomes, usage limits, and degraded/manual fallbacks.
5. Produce a reusable skill catalog whose steps invoke only allowlisted Queries and proposal builders; skills may not contain business rules or direct route/ORM execution.
6. Create user-value epics/stories and a dedicated `ai-assistant-foundation` OpenSpec with exact test evidence and developer-ready seed/demo data.
7. Complete the Laravel AI/provider, model-routing, prompt/injection, data-processing, RAG/vector store, streaming, retention, cost, and operations ADR/evidence gates before installing or wiring live providers.
8. **Resolved 2026-08-10:** PRD BR-044, the master roadmap, GF-14, GF-22, and GF-24 were reconciled to the executable Inertia 3 + Svelte 5 target. Nexi extends the existing Svelte application; no junior task may infer a React migration.

### Recommended Next Steps

1. Update the architecture spine with the authoritative surface-profile, frontend-session, skill-version, and knowledge-publication invariants.
2. Extend the readiness plan with a full route/surface matrix, cross-domain skill catalog, knowledge-admin UI, applicant UX, usage/admin controls, and junior implementation slices.
3. Update the architecture visual with parser-safe diagrams for staff/applicant Inquiry, Assistant proposal/confirmation, and knowledge publication/revocation.
4. Run architecture lint, Mermaid rendering, and the architecture reviewer gate.
5. Draft Nexi epics/stories and the planning-only OpenSpec. Do not install a provider until its ADR and gate pass.

### Final Note

The assessment found 37 functional requirements without an explicit story path, nine material UX alignment gaps, and five critical epic-quality violations across requirements traceability, UX architecture, and delivery decomposition. The gaps are correctable without changing the core design: one shared AI boundary, ordinary application commands, server-owned context, deny-dominant authorization, human confirmation, and manual fallback remain the sound foundation.

### Post-assessment architecture remediation

The architecture pass completed the planning corrections that can be resolved without opening the implementation OpenSpec:

- added explicit staff/applicant/proctor/knowledge-admin/operations/break-glass `SurfaceProfile` boundaries and precedence fixtures;
- added canonical frontend session, stream, availability, consent, usage, proposal, and recovery contracts;
- added the governed knowledge CRUD/publication lifecycle, hostile-file quarantine/parser boundary, bound approval digest, immediate revocation denial, and restore/reindex rules;
- added cumulative protected-read disclosure budgets and prohibited raw provider deltas from reaching the client;
- pinned the initial Postgres/database-queue/SSE/private-storage/scheduler/backup topology;
- created the complete route-family matrix, capability/skill seed catalogs, applicant plan, knowledge-admin UI, story template, and nine junior-sized slices;
- decomposed the former oversized foundation change into separate OpenSpecs and named `setup.readiness.read@1` as the first controller-to-kernel parity proof;
- reconciled active React references to the executable Inertia 3 + Svelte 5 target;
- passed deterministic spine lint, rendered every Mermaid diagram, and completed independent rubric, security/privacy, and technology/operations reviews.

The remaining NEEDS WORK verdict now means **stories/OpenSpec/provider/privacy evidence are still required before implementation**, not that the architecture is missing a direction.

**Assessment date:** 2026-08-10  
**Assessor:** Codex implementation-readiness workflow
