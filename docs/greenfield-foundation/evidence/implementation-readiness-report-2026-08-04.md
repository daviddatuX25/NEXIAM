---
stepsCompleted:
  - step-01-document-discovery
  - step-02-prd-analysis
  - step-03-epic-coverage-validation
  - step-04-ux-alignment
  - step-05-epic-quality-review
  - step-06-final-assessment
status: NOT_READY
documentsIncluded:
  product:
    - docs/greenfield-foundation/03-product-scope-and-success-contract.md
    - docs/MASTER-DELIVERY-ROADMAP.md
  architecture:
    - docs/greenfield-architecture-plan.md
    - docs/master-architecture-picture.md
    - docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md
    - docs/greenfield-foundation/15-architecture-decision-record-catalog.md
    - docs/greenfield-foundation/24-bmad-openspec-implementation-package.md
  ux:
    - docs/greenfield-foundation/22-user-experience-and-interface-contract.md
    - docs/legacy-reference/04-ui-ux-observed-baseline.md
  legacyEvidence:
    - docs/legacy-reference/02-feature-replication-catalog.md
    - docs/legacy-reference/03-legacy-to-greenfield-crosswalk.md
  execution:
    - openspec/changes/e0-greenfield-foundation
    - openspec/changes/e1-identity-reference-foundation
canonicalRoadmap: docs/MASTER-DELIVERY-ROADMAP.md
plannedCanonicalReference: docs/GREENFIELD-MASTER-REFERENCE.md
---

# Implementation Readiness Assessment Report

**Date:** 2026-08-04
**Project:** NEXIAM greenfield rebuild

## Document discovery

- No formal single PRD file was found; GF-03 and the root master roadmap are the selected product authorities.
- Multiple architecture documents were found and will be validated for consistency.
- GF-22 and the legacy observed baseline are the selected UX sources.
- No epic/story artifacts were found.
- `docs/MASTER-DELIVERY-ROADMAP.md` is canonical; `docs/greenfield-foundation/MASTER-DELIVERY-ROADMAP.md` must remain a pointer, not a second authority.
- E0/E1 OpenSpec packages are included as execution evidence; E2 has no detailed package yet.

## PRD Analysis

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

### Additional Requirements

- **BR-001** — The whole approved NEXIAM capstone concept is the product boundary; the Connected Admission-to-Release Slice is only the first implementation and proof milestone. (GF-03 D-006)
- **BR-002** — Every other feature shall be deliberately classified, designed, traced to evidence, and sequenced; no capability may be silently omitted. (GF-03 D-006)
- **BR-003** — Feature families already present in the manuscript, capstone research, RAD work, or phase plans shall be recovered and traced into the canonical chain rather than recreated or silently narrowed. (GF-03 §1)
- **BR-004** — The canonical chain is design authority; earlier artifacts remain evidence until promoted through the decision and readiness process. (GF-03 §1)
- **BR-005** — Target-behavior authority order is: recorded founder decisions and verified institutional evidence; master-roadmap phase/gate rules; promoted greenfield contracts; active OpenSpec change; executable tests/runtime evidence; legacy-reference evidence and legacy code last. (Roadmap §0)
- **BR-006** — Older greenfield architecture and implementation-order plans are compound-merge inputs, not standalone execution authority. (Roadmap D-004)
- **BR-007** — Legacy behavior, fields, vocabulary, and useful UI affordances may be preserved, but state ownership, authorization, transactions, concurrency, and trust boundaries shall be rewritten. (Roadmap E2)
- **BR-008** — The evaluated actors are Applicant, Registrar Clerk, Registrar Administrator, Guidance Counselor, Guidance Clerk, and Program Head. (GF-03 §3)
- **BR-009** — Proctor is temporary examination-session duty assigned to an eligible staff account, not a permanent role by default. (GF-03 §3)
- **BR-010** — Registrar Support Staff has an explicit intake/status/decision subset, is not Registrar Clerk-equivalent, and cannot use institutional/reference setup pages. (GF-03 §3)
- **BR-011** — Guidance Support Staff has Guidance Clerk responsibilities by default and may receive delegated Test Administration capability or Proctor duty separately. (GF-03 §3)
- **BR-012** — Staffing/HR Administrator manages approved permanent and support-staff account lifecycles, is not an operational admission actor, and cannot grant Test Administrator or Proctor capability. (GF-03 §3)
- **BR-013** — Super Admin, if retained, is a non-evaluated break-glass/oversight account. (GF-03 §3)
- **BR-014** — Queue workers and schedulers have no human authority. (GF-03 §3)
- **BR-015** — Mail, knowledge/vector, LLM, and inference services shall remain external services behind adapters. (GF-03 §3)
- **BR-016** — Final actor membership is gated by D-ROLE-01 through D-ROLE-05. (GF-03 §3)
- **BR-017** — Program Head access is read-only and program-scoped. (GF-03 §4)
- **BR-018** — Raw answer sheets and confidential conversion tables remain unavailable to Program Heads. (GF-03 §4)
- **BR-019** — An admission slip is not required for answer-sheet identity. (GF-03 §4)
- **BR-020** — AI-automated reporting is outside the current capstone commitment. (GF-03 D-008)
- **BR-021** — Exact AI libraries, SDKs, providers, prompts, model contracts, and implementation details remain subject to research and HLD/LLD decisions. (GF-03 D-008)
- **BR-022** — Laravel AI SDK, concrete providers, embedding stores including optional `pgvector`, and model routing are E3 spike/ADR decisions; E0–E2 may add only ports, seams, and feature-flagged UI slots. (Roadmap §2.4)
- **BR-023** — Manual scheduling is the complete authoritative baseline; AI scheduling only proposes feasibility and requires human confirmation. (Roadmap D-009)
- **BR-024** — Autonomous admission, placement, or enrollment decisions are outside the current study. (GF-03 Tier D)
- **BR-025** — Predictive-validity or applicant-outcome claims are prohibited without a valid longitudinal study. (GF-03 Tier D)
- **BR-026** — Enrollment, tuition/payment, long-term academic records, and cross-campus resource allocation are outside the current study. (GF-03 Tier D)
- **BR-027** — Multi-campus and multi-tenant platform behavior are outside the current study. (GF-03 Tier D)
- **BR-028** — Arbitrary third-party executable plugins and model-provided UI code are outside the current study. (GF-03 Tier D)
- **BR-029** — Formal NPC certification and claims of a completed PIA are outside the current study. (GF-03 Tier D)
- **BR-030** — Synthetic demonstrations or TAM alone cannot support production or pilot claims. (GF-03 Tier D)
- **BR-031** — OMR, scoped RAG, scheduling assistance, offline proctoring, and counselor decision-support are committed only after their named spike/evidence gates pass. (GF-03 Tier B)
- **BR-032** — BGE, YuKee, generic engine administration, quota management, extended messaging, and expanded analytics/reporting are conditional: their contracts may be designed now, but implementation and evaluation proceed only after the named gate passes. (GF-03 Tier C)
- **BR-033** — If a Tier B feature remains in the final manuscript promise, it requires engineering evidence independent of TAM. (GF-03 §4)
- **BR-034** — TAM may establish perceived usefulness, perceived ease of use, and role-scenario acceptance only within the approved sample and protocol. (GF-03 §7)
- **BR-035** — TAM cannot establish OMR accuracy, retrieval grounding/citation correctness, scheduler optimality, model validity/fairness/predictive power, cryptographic tamper resistance, authorization/privacy correctness, or production scalability/availability. (GF-03 §7)
- **BR-036** — Evaluation preparation shall define one fixed scenario set per respondent group, task-completion and exclusion rules, missing-response handling, response-rate reporting, and interpretation bands before analysis. (GF-03 §7)
- **BR-037** — Technical-pilot participants shall be separated from the final TAM sample where required. (GF-03 §7)
- **BR-038** — OBJ-04 is an engineering-evidence objective and shall not be represented as a new research objective unless adviser review requires manuscript revision. (GF-03 §5)
- **BR-039** — Final problem claims shall trace to stakeholder or institutional evidence; pending claims remain hypotheses in the evidence/contradiction register. (GF-03 §2)
- **BR-040** — A capability promised by the final manuscript shall be implemented and evaluated, explicitly narrowed with adviser/founder approval, or labeled future work before implementation evidence is presented. (GF-03 §9)
- **BR-041** — Quiet omission is prohibited. (GF-03 §§1, 9; Roadmap R1)
- **BR-042** — Planning coherence, implementation preparation, and pilot/deployment clearance are three independent verdicts and shall always be reported separately. (Roadmap §8)
- **BR-043** — A capstone-demonstration PASS does not imply pilot or production clearance. (GF-03 §6)
- **BR-044** — The approved application plane is Laravel 13, PHP 8.4, Inertia 3, React 19, TypeScript 5.9, Tailwind 4, and PostgreSQL 16 under `system/`. (Roadmap §2.1)
- **BR-045** — `system/` is the isolated greenfield workspace; legacy migrations, routes, controllers, databases, and storage shall not become target runtime dependencies. (Roadmap §§2.1, E0)
- **BR-046** — Baseline security, quality, and integrity shall never be deferred, even though full production hardening is not required for a bounded demonstration. (Roadmap §1)
- **BR-047** — The critical execution path is E0 → E1 → E2. Parallel work may prepare evidence and prototypes but may not mutate authoritative domain state or inject E3 dependencies into E1/E2. (Roadmap §5)
- **BR-048** — Immediate authorized implementation work is limited to finishing E0 OpenSpec exit evidence. (Roadmap §§1, 12)
- **BR-049** — E1 and E2 domain-feature implementation shall not begin until E0 passes and a fresh R5 readiness audit authorizes it. (Roadmap §§1, E1, 12)
- **BR-050** — Recording founder outcomes or writing an OpenSpec package does not by itself approve E1/E2 implementation. (Roadmap §12)
- **BR-051** — E0 shall not build admissions, identity-domain features, RAG/OMR, or production authentication integrations. (Roadmap E0)
- **BR-052** — E1 shall establish identity, access, reference, and cross-cutting foundations but shall not implement the connected admission journey. (Roadmap E1)
- **BR-053** — E1 shall use a fresh schema for identity, reference, audit, idempotency, and retention holds; no legacy migration shall be copied. (Roadmap E1)
- **BR-054** — E2 shall use manual scoring as its complete fallback; OMR, RAG, BGE, YuKee, and offline behavior remain outside the slice unless separately gated. (Roadmap E2)
- **BR-055** — E2 shall build only homepage, staff, applicant, proctor, and projection UI required by the connected journey; broad role dashboards wait for E4. (Roadmap E2)
- **BR-056** — E3 capabilities are blocked until E2 exits and each capability’s R4/evidence gate passes. (Roadmap E3)
- **BR-057** — Each E3 capability requires its own OpenSpec change; no research capability may be hidden inside E2. (Roadmap E3)
- **BR-058** — RAG fallback is static policy information plus staff handoff. (Roadmap E3)
- **BR-059** — Scheduling-assistance fallback is manual scheduling. (Roadmap E3)
- **BR-060** — OMR fallback is manual scoring. (Roadmap E3)
- **BR-061** — YuKee/BGE/model fallback is counselor/manual operation with the feature disabled. (Roadmap E3)
- **BR-062** — Offline-proctor fallback is online, paper, or manual reconciliation. (Roadmap E3)
- **BR-063** — Personality/PNG-frame fallback is a text-first assistant UI. (Roadmap E3)
- **BR-064** — Infrastructure capacity changes require measured triggers and an ADR. (Roadmap E4)
- **BR-065** — R0 requires preserved legacy provenance, dirty-worktree disposition, an explicit greenfield location, historical labels for competing plans, and an approved recovery charter. (Roadmap R0)
- **BR-066** — R1 requires an evidence register, scope/success contract, committed/conditional/deferred cut-line, evaluation-versus-technical-evidence separation, and recorded authority/scope/claim decisions. (Roadmap R1)
- **BR-067** — R2 requires indexed stakeholder evidence, role/data-scope decisions, workflow/failure/recovery decisions, retained OMR/RAG/model inputs, and explicit blocked or unverified items. (Roadmap R2)
- **BR-068** — Every committed workflow rule shall be evidenced or explicitly approved as a constrained assumption; fabricated validation claims are prohibited. (Roadmap R2 gate)
- **BR-069** — R3 requires agreed role, domain, lifecycle, workflow, recovery, security, audit, schema, and platform contracts with every P0 decision resolved. (Roadmap R3)
- **BR-070** — Domain names, state owners, cardinalities, delete/reversal rules, scopes, runtime, database, and test strategy shall agree before R3 passes. (Roadmap R3)
- **BR-071** — R5 requires the evidence → requirement → decision → workflow → state/data → ADR → story → acceptance/test chain to be complete for the next slice, with no open P0. (Roadmap R5)
- **BR-072** — A story or OpenSpec change is ready only when it identifies upstream decisions/contracts, legacy disposition, actor/capability/scope/duty, command/query/page ownership, transaction/lock/idempotency behavior, side effects/fallback, UI feedback, evidence target, and rollback/correction behavior. (Roadmap §9)
- **BR-073** — A change is done only when its OpenSpec tasks are complete without hidden failures; applicable fresh migrations and deterministic reset pass; tests are discovered and green; protected happy and denied paths are proven; audit/idempotency/after-commit effects match contracts; UI states match GF-22; evidence paths are recorded; divergence is recorded; and no legacy authority is silently introduced. (Roadmap §9)
- **BR-074** — GF-00 is the single live “where we are” navigator. (Roadmap §11)
- **BR-075** — GF-14 is the single R0–R5/E0–E6 phase-sequence authority. (Roadmap §11)
- **BR-076** — GF-24 plus one active OpenSpec change is the single implementation handoff. (Roadmap §11)
- **BR-077** — Decisions belong in GF-13; tickets shall not silently resolve founder or stakeholder choices. (Roadmap §11)
- **BR-078** — Conflicts shall reopen upstream decisions before downstream edits. (Roadmap §11)
- **BR-079** — Superseded documents shall be marked historical or superseded and linked to current authority rather than deleted. (Roadmap §11)
- **BR-080** — New material shall use stable `G-*`, `R*`, and `E*` identifiers rather than old numbered gates or unaudited phase numbering. (Roadmap §11)
- **BR-081** — Later OpenSpec tasks shall not mask earlier failures, and checked tasks shall correspond to committed evidence paths. (Roadmap §11)
- **BR-082** — Planning shall stop and reopen when new founder, panel, stakeholder, privacy, or spike evidence changes product commitment, actor authority/data visibility, identity/cardinality, state/reversal, research claims, or runtime/storage/provider choices with schema or security consequences. (Roadmap §13)
- **BR-083** — After such a change, upstream contracts shall be propagated first and readiness rerun; downstream code shall not be patched around unresolved authority changes. (Roadmap §13)

### PRD Completeness Assessment

Both files were read completely. Status and tension points:

- GF-03 remains **DRAFT — REVIEW REQUIRED** and explicitly does not amend the manuscript until approved and propagated.
- The Roadmap is active but records only an **E0-CONDITIONAL** position: E0 exit evidence remains incomplete, R4 has not passed for differentiators, R5 has not passed for E1/E2, and pilot/production clearance is not granted.
- GF-03’s first-target wording allows “manual or confidence-gated OMR,” while the Roadmap excludes OMR from E2 unless separately gated. The Roadmap supplies the controlling qualification.
- GF-03 describes offline proctoring as a Tier B committed capstone path subject to EV-011; the Roadmap places it behind E3 evidence gates while also referring to offline sync as conditional in E4. Its commitment versus sequencing status needs explicit reconciliation.
- GF-03 recommends Tier A/Tier B commitments, but those recommendations remain decision-gated rather than approved implementation authority.

## Epic Coverage Validation

Step 1 found no epic or story artifacts. Consequently, no functional requirement has an epic, story, acceptance-criteria, or implementation-work-item mapping.

| Functional Requirement | Epic Coverage | Status |
|---|---|---|
| FR-001 | NOT FOUND | MISSING |
| FR-002 | NOT FOUND | MISSING |
| FR-003 | NOT FOUND | MISSING |
| FR-004 | NOT FOUND | MISSING |
| FR-005 | NOT FOUND | MISSING |
| FR-006 | NOT FOUND | MISSING |
| FR-007 | NOT FOUND | MISSING |
| FR-008 | NOT FOUND | MISSING |
| FR-009 | NOT FOUND | MISSING |
| FR-010 | NOT FOUND | MISSING |
| FR-011 | NOT FOUND | MISSING |
| FR-012 | NOT FOUND | MISSING |
| FR-013 | NOT FOUND | MISSING |
| FR-014 | NOT FOUND | MISSING |
| FR-015 | NOT FOUND | MISSING |
| FR-016 | NOT FOUND | MISSING |
| FR-017 | NOT FOUND | MISSING |
| FR-018 | NOT FOUND | MISSING |
| FR-019 | NOT FOUND | MISSING |
| FR-020 | NOT FOUND | MISSING |
| FR-021 | NOT FOUND | MISSING |
| FR-022 | NOT FOUND | MISSING |
| FR-023 | NOT FOUND | MISSING |
| FR-024 | NOT FOUND | MISSING |
| FR-025 | NOT FOUND | MISSING |
| FR-026 | NOT FOUND | MISSING |
| FR-027 | NOT FOUND | MISSING |
| FR-028 | NOT FOUND | MISSING |
| FR-029 | NOT FOUND | MISSING |
| FR-030 | NOT FOUND | MISSING |
| FR-031 | NOT FOUND | MISSING |
| FR-032 | NOT FOUND | MISSING |
| FR-033 | NOT FOUND | MISSING |
| FR-034 | NOT FOUND | MISSING |
| FR-035 | NOT FOUND | MISSING |
| FR-036 | NOT FOUND | MISSING |
| FR-037 | NOT FOUND | MISSING |
| FR-038 | NOT FOUND | MISSING |
| FR-039 | NOT FOUND | MISSING |
| FR-040 | NOT FOUND | MISSING |
| FR-041 | NOT FOUND | MISSING |
| FR-042 | NOT FOUND | MISSING |
| FR-043 | NOT FOUND | MISSING |
| FR-044 | NOT FOUND | MISSING |
| FR-045 | NOT FOUND | MISSING |
| FR-046 | NOT FOUND | MISSING |
| FR-047 | NOT FOUND | MISSING |
| FR-048 | NOT FOUND | MISSING |
| FR-049 | NOT FOUND | MISSING |
| FR-050 | NOT FOUND | MISSING |
| FR-051 | NOT FOUND | MISSING |
| FR-052 | NOT FOUND | MISSING |
| FR-053 | NOT FOUND | MISSING |
| FR-054 | NOT FOUND | MISSING |
| FR-055 | NOT FOUND | MISSING |
| FR-056 | NOT FOUND | MISSING |
| FR-057 | NOT FOUND | MISSING |
| FR-058 | NOT FOUND | MISSING |
| FR-059 | NOT FOUND | MISSING |
| FR-060 | NOT FOUND | MISSING |
| FR-061 | NOT FOUND | MISSING |
| FR-062 | NOT FOUND | MISSING |
| FR-063 | NOT FOUND | MISSING |
| FR-064 | NOT FOUND | MISSING |
| FR-065 | NOT FOUND | MISSING |
| FR-066 | NOT FOUND | MISSING |
| FR-067 | NOT FOUND | MISSING |
| FR-068 | NOT FOUND | MISSING |
| FR-069 | NOT FOUND | MISSING |
| FR-070 | NOT FOUND | MISSING |
| FR-071 | NOT FOUND | MISSING |
| FR-072 | NOT FOUND | MISSING |
| FR-073 | NOT FOUND | MISSING |
| FR-074 | NOT FOUND | MISSING |
| FR-075 | NOT FOUND | MISSING |

### Missing Requirements Assessment

All 75 functional requirements are missing epic and story coverage. There is no artifact that decomposes any FR into owned delivery scope, ordered stories, acceptance criteria, dependencies, or testable completion conditions.

This is the critical reason the current plan cannot be called implementation-ready or detail-complete. The product and architecture documents may describe intended behavior and sequencing, but they do not provide the executable delivery decomposition required to demonstrate that every functional requirement is assigned, buildable, testable, and traceable through implementation.

### Coverage Statistics

- Total functional requirements: **75**
- Functional requirements covered by epics/stories: **0**
- Functional requirements missing epic/story coverage: **75**
- Epic/story coverage: **0%**

## UX Alignment Assessment

### UX Document Status

- `docs/greenfield-foundation/22-user-experience-and-interface-contract.md` (GF-22) is the target UX contract, but its status is `DRAFT — REVIEW REQUIRED` and it explicitly states that it is not implementation authority until R5 passes.
- `docs/legacy-reference/04-ui-ux-observed-baseline.md` is an evidence baseline only. It preserves useful observed behavior and must not make legacy markup, state ownership, authorization, or framework choices authoritative.
- GF-22 is strongly aligned at the principle and shared-pattern level with GF-03 and the canonical architecture spine. It is not yet an implementable route/page specification because the R5 route catalog, page inventory, component inventory, and page-specific state assignments do not exist.
- The reviewed architecture package still reports G-UX and the browser/UI contract as draft or blocked (`docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md` §§7–8). E1 may establish shells and shared primitives, but feature-page implementation requires a fresh R5 disposition and an active phase-specific OpenSpec package.

### Aligned Areas

- **Product journey:** Public intake, applicant access, Registrar review, examination scheduling, contextual proctoring, grading, consultation, result preparation, release, notifications, and applicant retrieval align across GF-03 §§4 and 8, GF-22 §§4 and 9, GF-14 E2, and GF-24 §7.
- **Human authority and manual fallback:** GF-22 keeps scheduling assistance, OMR, RAG, and model output advisory or proposal-only. Ordinary authorized commands remain the mutation path, and manual scheduling/scoring remain complete fallbacks. This agrees with GF-03 §§1 and 4, GF-14 §§2.2 and 2.4, and GF-15 ADR-006 through ADR-012.
- **Authorization and scope:** Server-authored navigation and breadcrumbs, scoped page props, safe denied/not-found treatment, contextual proctor duty, and the rule that hidden or disabled controls are not authorization authority agree with GF-14 §§2.3 and 2.5 and GF-15 ADR-005.
- **Truthful feedback:** GF-22 §§5–7 distinguish committed, queued, sent, failed, conflict, unknown-outcome, degraded-provider, and pending-side-effect states. This matches the architecture requirements for atomic commands, after-commit effects, idempotency, reconciliation, and typed failures in GF-14 §2.2 and GF-15 ADR-007 and ADR-008.
- **Shared interface behavior:** GF-22 provides substantial contracts for shells and page props (§3), navigation and breadcrumbs (§4), tables/forms/dialogs/notifications (§5), toast payloads and accessibility (§6), page and panel states (§7), charts and graphics (§8), responsive behavior (§10), accessibility (§11), privacy (§12), and frontend/browser evidence (§13).
- **Legacy disposition:** The legacy baseline and GF-24 §§3–4 correctly preserve journeys, vocabulary, fields, shell affordances, table/card switching, URL-backed filters, charts, notifications, imports, and document previews as evidence while requiring target authorization, state, transaction, privacy, and trust boundaries to be redesigned.
- **Phase spine:** GF-14 E1 appropriately assigns typed page props, tokens/primitives, authentication surfaces, base shells, server breadcrumbs, toast/notification contracts, and page-state primitives before E2 connected pages. GF-14 E4 appropriately defers broad dashboards, reports, notification preferences, messaging, additional documents, and additional imports.

### Conflicts and Stale Statuses

- **Duplicate roadmap status drift:** `docs/MASTER-DELIVERY-ROADMAP.md` still reports E0-CONDITIONAL in its header, current position, scorecard, and next-gate text, while canonical GF-14 reports E0 PASS in its header and §1. Canonical GF-14 also retains `EXIT EVIDENCE INCOMPLETE` in E0 and `E0-CONDITIONAL` in its scorecard. The distinction between scaffold PASS and residual E0 hygiene is not expressed consistently.
- **Stale implementation handoff:** GF-24 §14 still says the E0 scaffold is only conditionally ready, contradicting canonical GF-14's E0 PASS status and recorded scaffold evidence.
- **Frontend stack conflict:** GF-22 §1 and GF-14 §2.1 specify Laravel 13, Inertia 3, React 19, TypeScript 5.9, and Tailwind 4. GF-15 ADR-002 still proposes Laravel 12, Inertia 2, and Svelte 5. The legacy UX baseline §3 also refers to selected Svelte component primitives. The latter two references are stale for target frontend implementation.
- **ADR authority mismatch:** GF-15 marks all ADRs as proposed and prohibits coding agents from treating them as implementation authority, while GF-14 calls the stack and architecture spine approved and non-negotiable. The required ADR subset must be promoted or the governing founder decisions and supersession relationship must be made explicit.
- **Historical authority pointers:** `docs/greenfield-architecture-plan.md` and `docs/master-architecture-picture.md` are correctly marked historical, but they retain old stack, database, phase, and authority language. Their useful evidence must not override GF-14, GF-22, or a promoted OpenSpec change.
- **OMR and RAG phase leakage:** GF-14 E2 excludes OMR and RAG unless separately gated, but GF-22 §13.2 makes an OMR review path and RAG journey generally mandatory, and §13.3 requires OMR visual evidence without phase qualification. These tests and visual artifacts must be conditional on capability promotion.
- **Offline sequencing ambiguity:** GF-03 §4 classifies offline proctoring as a committed, spike-gated capstone capability. GF-14 E3 lists it as a differentiator, while GF-14 §6 maps messaging/offline to E4 and says “if retained.” GF-22 §9.4 correctly conditions the UI on EV-011, but the target phase and commitment wording remain inconsistent.
- **Program Head/reporting scope ambiguity:** GF-03 Tier A and §8 require a program-scoped aggregate consequence in the connected journey. GF-14 permits a minimal Program Head projection in E2 and defers broad dashboards to E4. GF-22 §§4.1, 9.7, and 13.2 describe a broad reports experience without identifying the exact minimal E2 page and metrics.
- **Import scope ambiguity:** GF-03 §1 calls for a later decision on unified/dynamic import architecture, while GF-14 E4 defers additional imports and GF-22 specifies only OMR import behavior. The target does not identify which import capabilities are E2, E3, E4, conditional, or rejected.

### Missing Implementable Details

- **Route catalog:** GF-22 §4.1 explicitly defers exact URI strings and requires an R5 route catalog, but none exists in the reviewed set. Each route needs a stable name, URI and method, Inertia page, phase, actor/capability/scope, query parameters, command endpoint, breadcrumb parent, protected-data class, and denied/not-found behavior.
- **Phase-scoped page inventory:** The artifacts define experience areas rather than a discrete E1/E2/E3/E4 page list. The target must enumerate public intake/outcome, applicant setup/login/recovery/status/schedule/result, Registrar queue/detail/correction, session planning/assignment/publication, proctor workspace, grading, consultation, document preview, release, notifications, minimal Program Head projection, reports, audit, and operations pages.
- **Page-to-state matrix:** GF-22 §7 defines a strong global taxonomy, but no artifact assigns required and impossible states to each page or deferred panel. Every page needs its loading, refreshing, ready, empty, denied, conflict, degraded, queued, unknown-outcome, offline, and recovery behavior identified.
- **Component inventory and ownership:** Shared behavior is described without choosing the target component set, ownership boundaries, React/Inertia composition, or which variants are phase prerequisites. Tokens, design primitives, chart renderer, and final component inventory remain open in GF-22 §15.
- **Loading and asynchronous timing:** Skeleton and progress behavior are described, but there are no thresholds for showing progress, declaring stale data, timing out a query, polling notifications/operations, retrying with backoff, or stopping automated refresh.
- **Modal inventory:** GF-22 §5.4 gives broad rules for when a modal is required but does not map commands to modal, drawer, or full-page treatment. Command-specific confirmation content, in-dialog validation/conflict states, mobile presentation, history behavior, and focus destination after success remain unspecified.
- **Breadcrumb catalog:** GF-22 §4.2 specifies safe behavior but not the canonical hierarchy for each route. Parent-list query preservation, safe fallback destinations, deep-link ancestry, document-title text, and E1 behavior before broad dashboards exist need catalog entries.
- **Import workflow:** The target needs an explicit contract for application import, score import, any future unified import, file constraints, parser/security checks, field mapping, analyze results, preview, row selection, correction, confirm, partial failure, idempotent resumption, cancellation, expiry, and audit evidence.
- **Responsive acceptance:** GF-22 §§10–11 do not pin minimum useful viewport, supported browser/device matrix, breakpoint ownership, table-to-card rules per page, mobile modal behavior, camera alternatives, safe-area behavior, or acceptance at increased text size.
- **Accessibility acceptance:** WCAG 2.2 AA is the target, but the reviewed set lacks the test method, assistive-technology/browser matrix, zoom/reflow targets, page-specific keyboard order, Inertia-navigation focus destination, automated-check boundaries, and manual review checklist.
- **Performance budgets:** Apart from toast duration, no measurable browser targets exist for initial shell load, Inertia navigation, input responsiveness, low-bandwidth applicant submission, list pagination, chart rendering, upload/analyze/preview acknowledgement, document generation, notification freshness, or offline replay.
- **Notification operation:** Poll/push cadence, deduplication window, tab-visibility behavior, offline behavior, backoff, maximum toast stack, notification-history retention, and the transition from queued to sent/failed need concrete contracts.

### Independently Verified Legacy UX Evidence Requiring Target Disposition

The following observations are evidence inputs. They are not target architecture and must receive an explicit preserve, rewrite, defer, or reject disposition in the route/page/component catalog or the relevant OpenSpec change:

- **Admin setup readiness presentation:** Legacy admin setup pages use server-computed readiness percentages and severity, plus per-card progress bars. GF-22 does not capture the source calculation, thresholds, severity vocabulary, card inventory, loading/error states, accessible progress semantics, or whether this presentation survives in E1. The target must decide whether to preserve the information architecture, replace it with a checklist, defer it, or reject it.
- **Breadcrumb/document-title coupling:** Legacy breadcrumbs collapse responsively and also drive the document title. GF-22 preserves responsive collapse and requires title/heading agreement, but no greenfield breadcrumb catalog defines title derivation, ancestor labels, route hierarchy, compact behavior, or safe fallbacks. The observed coupling must be deliberately preserved or replaced rather than copied implicitly.
- **Import interaction shape:** Legacy applicant and score imports use an `analyze → page-level preview → select/confirm` flow. Import verification is not performed in confirmation modals. GF-22's modal rules and OMR import text do not settle whether the target keeps page-level preview, introduces a wizard, or uses another staged pattern. The target import UX must be specified before implementation.
- **Missed-exam recovery:** No explicit retry policy exists. The observed legacy behavior composes `absent → remove → reassign`, `postpone`, or `cancel` actions. This is not a coherent target state machine. Product/workflow authority must define who may retry or reassign, attempt limits, deadline/cycle constraints, applicant notification, history, cancellation/postponement semantics, and the corresponding page states and confirmations.
- **Notification truth semantics:** Legacy flash, notification polling, and toasts exist, but observed behavior does not establish whether a message means request accepted, state committed, delivery queued, or delivery completed. GF-22 §§5–7 supplies the intended semantic distinction, but the target still needs a concrete event-to-flash/notification/toast mapping, polling lifecycle, deduplication, and delivery-status transition contract.

### Warnings

- Do not treat legacy readiness percentages, progress bars, breadcrumb/title coupling, import pages, missed-exam action composition, or notification wording as target behavior merely because they exist.
- Do not start reconstructing E2 pages from the legacy page inventory before the R5 route catalog, phase-scoped page inventory, page-state matrix, and active E2 OpenSpec package exist.
- Do not let GF-22's unqualified OMR/RAG visual and browser requirements pull E3 capabilities into E1 or E2.
- Do not use the historical Svelte component baseline or proposed ADR-002 to select target frontend dependencies; the current target baseline is React/Inertia under GF-22 and GF-14.
- Do not claim accessibility, low-bandwidth tolerance, responsiveness, import safety, notification delivery, or performance readiness without measurable acceptance evidence.
- Do not resolve the missed-exam policy, import architecture, Program Head projection, offline phase, or notification channel semantics solely in frontend code. Each requires upstream product/workflow/architecture disposition and traceability.

## Epic Quality Review

### Review Result

No epic or story artifacts were found during document discovery. Therefore, epic and story quality cannot be validated.

The following required quality dimensions are **NOT VALIDATABLE**:

- **User value:** No epic or story states a user outcome, beneficiary, or measurable value increment.
- **Epic independence:** No epic boundaries exist to determine whether each epic can deliver coherent value without relying on unfinished later epics.
- **Story independence:** No stories exist to determine whether individual increments can be implemented, tested, and accepted without hidden coupling.
- **Dependency order:** No story sequence exists to verify prerequisite-first delivery or detect forward dependencies on later stories.
- **Sizing and implementability:** No stories exist to assess whether work is small enough for bounded implementation and review.
- **BDD acceptance criteria:** No story-level Given/When/Then acceptance scenarios exist for happy paths, denied paths, conflicts, degraded behavior, or recovery.
- **Error and edge scenarios:** No story-level treatment exists for validation failures, authorization denial, stale versions, duplicate/replay attempts, concurrency conflicts, provider outages, partial delivery, reversal, or recovery.
- **Per-story FR traceability:** No story maps its scope to FR identifiers, so requirement ownership, implementation coverage, and completion evidence cannot be verified.

### Technical Structures Are Not Epic Substitutes

The E0, E1, and E2 roadmap phases are technical delivery phases and gate structures. They describe sequencing, architecture foundations, connected-slice order, and exit evidence, but they are not user-value epics or implementation-sized stories.

The E0 and E1 OpenSpec task checklists are technical execution and evidence structures. They may authorize and track bounded implementation work, but they do not substitute for user-value epics and stories containing actor outcomes, explicit requirement ownership, independently testable increments, and story-level acceptance criteria.

E2 currently has a roadmap sequence and referenced connected-slice requirements but no detailed epic/story package. E2 and later phases therefore lack the delivery decomposition needed to validate user value, independence, sizing, dependency safety, acceptance completeness, and per-story traceability.

### Critical Violation

**CRITICAL VIOLATION:** The project has no user-value epic/story decomposition for its functional scope. This prevents validation that requirements are independently deliverable, correctly ordered, appropriately sized, covered by behavioral acceptance criteria, and traceable to implementation and evidence. Roadmap phases and OpenSpec task lists cannot close this gap by themselves.

This violation blocks an implementation-ready or detail-complete verdict for E2 and later product work.

### Required Remediation

Create user-value epics and implementation-sized stories for E2 and every later product phase before those phases are authorized for implementation.

Each story shall carry:

- a clear user-value outcome and bounded story scope;
- the applicable legacy disposition: preserve, rewrite, spike, defer, or reject;
- explicit FR and BR identifiers;
- actor, capability, resource scope, and contextual-duty requirements;
- relevant initial, success, denied, conflict, degraded, reversal, and recovery states;
- Given/When/Then acceptance criteria covering happy paths and applicable error or edge scenarios;
- backend, frontend, browser, operational, or other test/evidence targets appropriate to the behavior;
- explicit prerequisites that refer only to foundations or earlier stories; and
- no forward dependency on a later story for the current story’s promised value or acceptance.

Epic and story ordering shall be prerequisite-first. Each epic shall deliver coherent user value, and each story shall remain independently implementable, testable, and acceptable within that ordering.

## Future Feature Readiness

**Scope:** Capstone differentiators and AI-adjacent foundations traced across `docs/greenfield-foundation/*.md`, `docs/MASTER-DELIVERY-ROADMAP.md` (canonical GF-14 mirror), `openspec/changes/*`, and `docs/legacy-reference/*`.

**Verdict:** Behavioral policy and phase gates are largely decided (**D-008**, **D-007–D-011**, **D-009**, **D-018**, **D-080**, **D-079**, **D-082**, **D-048–D-051**, **D-069**; **G-RAG**, **G-OMR**, **G-ENGINE** in `docs/MASTER-DELIVERY-ROADMAP.md` §7). **Implementation contracts remain thin** until R4 spikes close evidence holds and E3 OpenSpec packages exist. E0–E2 manual core can proceed; E3 features will force ad-hoc choices without the remediation artifacts below.

### Phase and gate constraints (authoritative)

| Constraint | Source | Effect |
|---|---|---|
| E0–E2: ports/seams/flagged UI only; no SDK/RAG | `docs/MASTER-DELIVERY-ROADMAP.md` §2.4; **BR-022**; `openspec/changes/e0-greenfield-foundation/design.md` (no provider SDKs, vector stores) | No Laravel AI SDK, pgvector production enablement, or RAG implementation before E3 |
| E1: AI ports, feature flag, degraded assistant slot, audit/idempotency hooks | **FR-066**; Roadmap E1 AI row | Required before E2; **not** in current E1 OpenSpec (see below) |
| E2: manual scheduling/scoring; differentiators out of slice | **BR-054**; `docs/greenfield-foundation/11-connected-vertical-slice-and-acceptance.md` | OMR/RAG/BGE/YuKee/scheduling assist not in connected slice unless separately gated |
| E3: one OpenSpec per capability; blocked until E2 + per-capability R4/evidence | **BR-056**, **BR-057**; `docs/greenfield-foundation/24-bmad-openspec-implementation-package.md` §9 Change 4+ | No bundled “AI epic” inside E2 |
| **G-RAG** / **G-OMR** / **G-ENGINE** | `docs/MASTER-DELIVERY-ROADMAP.md` §7 | Block implementation/claim until corpus, sheet, or engine evidence passes |
| Proposed ADRs not implementation authority | `docs/greenfield-foundation/15-architecture-decision-record-catalog.md` §5 | **ADR-010–012** guide design only until promoted |

### Traceability by theme

| Theme | Decisions / requirements | Primary contracts | Status |
|---|---|---|---|
| **Laravel AI SDK boundary** | **D-008**, **FR-056**, **BR-021**, **BR-022**, **NFR-020–022** | GF-20 ports (`KnowledgeSearchPort`, `BoundedGenerationPort`, `AssistantActionPort`, `InferencePort`); GF-17 Knowledge module | SDK deferred to E3 spike/ADR; **no ADR naming Laravel AI SDK** |
| **Embeddings / pgvector** | **D-057**, **NFR-032**; GF-17 §4.6; GF-19 `knowledge_chunks` | Optional PG extension after ADR/evidence; embeddings not auth source | Schema sketched; **no** extension version, index type, dimension, backup/restore, or BGE-vs-RAG storage split |
| **RAG** | **D-008**, **D-051**, **D-079**; **REQ-RAG-001/002** (GF-12); **ADR-012** | GF-06 §10; GF-10 §4; GF-21 §5.5; GF-22 §9.8; GF-20 `ProposeAssistantAction` / `ConfirmAssistantAction` | **CONDITIONAL**; **EV-012** (corpus), **G-RAG**, R4 spike |
| **Nexi / personality UI** | **FR-064**, **NFR-027**, **BR-063**; Roadmap E3 personality row | PNG frames, feature flag, reduced-motion, text-first fallback (GF-22 cites legacy AI chat as evidence only) | **No “Nexi” persona name** in foundation suite (`docs/project-identity.md` = NEXIAM only); no character bible or asset catalog |
| **Typed assistant actions** | **FR-057**, **FR-058**; **CAP-037** (GF-04); GF-21 §5.5 | Meta-commands + `AssistantActionPort`; examples in GF-06 §10 | **No enumerated action catalog** (action key → domain command → capability → field schema) |
| **OMR / YuKee / BGE worker** | **D-007**, **D-010**, **D-082**, **D-048**, **D-050**, **D-011**; **REQ-OMR-***, **REQ-ENG-***; **ADR-010**, **ADR-011** | GF-10 §§3, 5–6; GF-20 `InferencePort`; E0 optional Python seam | OMR/YuKee **SPIKE HOLD**; BGE **CONDITIONAL** beta; worker HTTP envelope **open** (GF-20 §13) |
| **Scheduling assistance** | **D-009** (**D-SCHED-01**); **FR-045**, **FR-046**, **REQ-SCHED-001**, **NFR-043** | GF-10 §7; GF-22 scheduling-assistant UX; manual commands in GF-20 (`OpenExamSlotReservation`, `FinalizeExamSlotAssignments`, `PublishExamSession`) | Propose-only; **no** solver/objective ADR or schedule-proposal DTO → command mapping |
| **Program Head metrics / projections** | **D-018**, **D-080**, **D-ROLE-05**; **REQ-REP-001**; **CAP-024** | GF-06 §12; GF-20 `ProgramHeadProjectionQuery`; GF-11 **REQ-VS-025**, **SCN-VS-022** | **EVIDENCE HOLD** (**EV-004**, **EV-017**); minimal projection may start E2; metric dictionary missing |

### Missing implementation contracts (ad-hoc risk)

1. **ADR-0xx Laravel AI SDK** — provider choice, secrets, streaming, logging/redaction, test doubles, exit path (upstream **D-008**, **BR-022**).
2. **Assistant typed-action catalog** — finite table for GF-21 allowlist and GF-20 equivalent-UI-command tests.
3. **RAG operations** — chunking, embedding model, reindex on `SyncKnowledgeDocument`, citation/refusal thresholds (GF-20 §13); approved corpus files (**EV-012**).
4. **pgvector enablement package** — extension/version, column/index, privacy review, restore drill (GF-19/GF-17 conditional language).
5. **Embedding topology** — whether BGE worker vectors, RAG chunks, or both use PostgreSQL vs worker-only storage (GF-17 §4.6 mentions optional path for “BGE/RAG” without split).
6. **Python inference worker API** — auth, schema version, timeout, idempotency for OMR and BGE (**ADR-010**; GF-20 open hold on OMR envelope).
7. **OMR ground-truth package** — template, scans, thresholds (**EV-008**, **G-OMR**, GF-10 RES-G2).
8. **YuKee artifact manifest** — checksum, loader, fixtures (**D-082**); see contradiction below.
9. **Scheduling assistance contract** — constraint JSON, objectives, spike fixtures, proposal → finalize/publish mapping (GF-10 §7, **REQ-SCHED-001**).
10. **Program Head metric dictionary** — quota source, “matched”, freshness, suppression (**D-080**, **EV-017**).
11. **Inquiry thread model** — routing, SLA, retention for **D-079** handoff (**EV-018**; GF-19 conditional `inquiry_threads`).
12. **E3 OpenSpec changes** — one per OMR, RAG, scheduling assist, engines, offline, personality UI (GF-24 §9); **none authored**.

### Cross-document defects and contradictions

| Issue | Evidence | Remediation artifact |
|---|---|---|
| **Document-integrity corruption** | A repository scan found literal omission markers in six source documents: GF-24 (46), GF-00 (21), foundation README (17), and three historical plans (38/61/35). The readiness report itself is not counted. | Quarantine affected files as authority; reconstruct active documents from intact GF contracts, Git history, transcript evidence, and the root roadmap; verify zero markers before promotion |
| **E1 OpenSpec AI-seam omission** | **FR-066** / Roadmap E1 AI row vs `openspec/changes/e1-identity-reference-foundation/proposal.md` (no ports, flag, degraded slot) | Amend `e1` proposal/tasks/spec with AI seam deliverables or formally defer **FR-066** with founder trace |
| **No E3 OpenSpecs** | Only `e0-greenfield-foundation`, `e1-identity-reference-foundation` under `openspec/changes/` | Create spike-gated proposals per GF-24 §9 (e.g. `e3-rag-foundation`, `e3-omr-worker`, …) after **G-*** gates |
| **Stale ADR-002 stack** | GF-15 ADR-002: Laravel 12, Inertia 2, Svelte 5 vs GF-09/GF-14/**BR-044**: Laravel 13, Inertia 3, React 19 | Revise ADR-002 to match promoted stack or supersede with E0 evidence ADR; align GF-15 promotion gate |
| **YuKee evidence contradiction** | **D-082** GO WITH CONDITION (artifact path) vs GF-02 **CR-012** / **EV-009** BLOCKED vs `docs/legacy-reference/06-open-clarifications.md` ENG-01 “resolved” vs same file “External YuKee artifact handoff — OPEN” (no `.joblib` in repo) | Single evidence note: **`docs/greenfield-foundation/evidence/yukee-artifact-verification.md`** (or extend GF-02 EV-009) with checksum, custodian, loader/fixture pass/fail; downgrade legacy “resolved” to planning-only |

### Concrete remediation artifacts (recommended)

| Artifact | Closes | Owner phase |
|---|---|---|
| `docs/greenfield-foundation/15-architecture-decision-record-catalog.md` — new **ADR-0xx Laravel AI SDK & provider boundary** | **BR-022**, SDK choice | R4 → E3 |
| `docs/greenfield-foundation/20-application-and-integration-contract-catalog.md` — **Assistant typed-action catalog** annex | **FR-057/058**, GF-21 allowlist | R5 / E1 hooks |
| `docs/greenfield-foundation/evidence/rag-corpus-manifest.md` + curated query suite | **EV-012**, **G-RAG**, GF-10 §4 | R4 |
| `docs/greenfield-foundation/evidence/pgvector-enablement-report.md` | Extension/index/restore | R4 (if pgvector selected) |
| `docs/greenfield-foundation/evidence/inference-worker-contract.md` (OpenAPI + fixtures) | **ADR-010**, OMR/BGE | R4 |
| `docs/greenfield-foundation/evidence/program-head-metric-dictionary.md` | **D-080**, **EV-017**, **REQ-REP-001** | R2 stakeholder |
| `docs/greenfield-foundation/evidence/scheduling-assist-spike-report.md` | **D-009**, **NFR-043**, RES-G6 | R4 |
| `openspec/changes/e3-*` (one change per differentiator) | **BR-057** | Post-E2 |
| Repair **GF-24** §8 table | BMAD handoff integrity | Immediate doc |
| Align **`openspec/changes/e1-identity-reference-foundation/`** with **FR-066** | E1 exit vs roadmap | Before E1 PASS |

### Readiness snapshot

| Capability | Decision clarity | Contract ready for E3 code? | Gate |
|---|---|---|---|
| Laravel AI SDK | High (defer) | No | E3 + ADR |
| pgvector / embeddings | Medium | No | R4 + ADR |
| RAG | High (security/scope) | No | **G-RAG**, **EV-012** |
| Nexi / personality | Low (behavior only) | No | E3 UI + assets |
| Typed assistant | High (architecture) | No | Catalog + E1 seams |
| OMR / workers | High (human-in-loop) | No | **G-OMR**, **EV-008** |
| YuKee / BGE | Conditional | No | **G-ENGINE**, artifact/fixtures |
| Scheduling assist | High (manual baseline) | No | R4 spike |
| Program Head projection | High (read-only) | No | Metric dictionary, **EV-017** |

## Summary and Recommendations

### Overall Status

**NOT READY for E2 implementation**

The repository may contain valid E0 scaffold evidence and partial E1 code, tests, migrations, OpenSpec tasks, or other technical evidence. That evidence can demonstrate bounded implementation progress within those phases, but it does not make the product plan implementation-ready or detail-complete for E2.

E2 readiness requires an approved product authority, complete requirement-to-epic/story traceability, implementable UX catalogs, reconciled legacy rules, consistent governing statuses, future-feature boundaries, and an active E2 implementation handoff. Those conditions are not met.

### Critical Issue Categories

1. **No approved single PRD.** No single approved PRD consolidates the complete product scope, functional and non-functional requirements, business rules, evidence status, and phase cut-line. GF-03 remains draft/review-required, while the master roadmap supplies additional product requirements and constraints.
2. **0/75 FR epic/story coverage.** All 75 functional requirements lack user-value epic and implementation-sized story mappings. Coverage is 0%, so ownership, dependency order, sizing, acceptance criteria, test targets, and completion traceability cannot be validated.
3. **Missing route/page/state/modal/import/breadcrumb/performance catalogs.** The target lacks the route catalog, phase-scoped page inventory, page-to-state matrix, command-specific modal treatment, complete import workflow, breadcrumb hierarchy, and measurable browser/performance budgets needed to implement and verify E2 behavior.
4. **Untraced legacy parity and recovery rules.** Preserved legacy journeys and observed behaviors do not have complete preserve/rewrite/defer/reject dispositions or target-state traceability. This includes missed-exam recovery, retry/reassignment authority, attempt and deadline limits, cancellation/postponement semantics, applicant notification, audit history, and corresponding page states.
5. **Stale or contradictory authorities and statuses.** E0 status differs across roadmap and handoff artifacts; proposed ADRs conflict with the promoted stack; OMR/RAG and offline phase language is inconsistent; Program Head and import scope remains ambiguous; and historical artifacts retain stale implementation language.
6. **Missing future-feature contracts for Nexi, AI, embeddings, workers, and Program Head.** The target lacks complete contracts for Nexi/personality assets and behavior, the Laravel AI/provider boundary, typed assistant actions, RAG corpus and operations, embedding/pgvector topology, Python inference-worker APIs, scheduling assistance, engine artifacts, and the Program Head metric dictionary/projection.

These six categories are critical because each breaks one or more links in the required evidence → requirement → decision → workflow → state/data → ADR → epic/story → acceptance/test → OpenSpec chain.

### Ordered Remediation Plan

1. **Establish one approved product authority.** Consolidate or formally promote the PRD, confirm the committed/conditional/deferred cut-line, resolve OMR/RAG/offline/Program Head/import scope, and record founder/adviser approval and evidence holds.
2. **Restore document integrity, then reconcile authority and status.** Reconstruct the corrupted active GF-00, foundation README, and GF-24 without overwriting unreviewed user work; quarantine the three corrupted historical plans; require a zero-marker scan; then align the root master roadmap, GF-14, GF-15, GF-22, GF-24, and active OpenSpec packages and report E0, E1, and E2 separately.
3. **Close legacy parity and recovery dispositions.** Assign preserve, rewrite, spike, defer, or reject outcomes to every retained legacy journey and UX behavior. Resolve missed-exam recovery, import architecture, notification truth, breadcrumb/title behavior, setup readiness presentation, and other untraced recovery rules in product/workflow authority rather than frontend code.
4. **Complete the implementable E2 UX contract.** Produce the route catalog, phase-scoped page inventory, page-to-state matrix, component ownership, modal/dialog mapping, breadcrumb catalog, import workflow, notification lifecycle, responsive/accessibility acceptance matrix, and measurable performance budgets.
5. **Create user-value epics and implementation-sized stories.** Cover FR-001 through FR-075 as applicable to their approved phases, prioritizing the complete E2 connected journey. Every story must carry legacy disposition, FR/BR identifiers, actor/capability/scope, states, prerequisite-only dependency order, Given/When/Then acceptance criteria, error/recovery scenarios, and test/evidence targets.
6. **Complete future-feature contracts without leaking them into E2.** Define or explicitly defer the Nexi/personality catalog, AI SDK/provider ADR, typed-action catalog, RAG corpus and embedding design, pgvector decision, inference-worker API, OMR/YuKee/BGE evidence packages, scheduling-assistance contract, and Program Head metric dictionary. Preserve manual E2 fallbacks and phase gates.
7. **Validate the complete traceability chain.** Confirm that every E2 requirement maps through approved decisions, workflow/state/data contracts, architecture decisions, user-value epics, independently implementable stories, acceptance criteria, and executable evidence targets, with no open P0 contradiction or forward dependency.
8. **Run a fresh implementation-readiness assessment and create the E2 OpenSpec only on PASS.** The fresh run must verify the reconciled authority, 100% applicable E2 epic/story coverage, UX catalog completeness, legacy dispositions, story quality, and test/evidence traceability. If and only if that assessment passes, author, review, and activate the E2 OpenSpec as the implementation handoff.

### Final Assessment Note

- **Critical issue count:** 6 critical categories
- **Assessment date:** 2026-08-04
- **Assessor:** Cursor implementation-readiness audit
