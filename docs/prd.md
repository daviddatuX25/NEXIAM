---
title: NEXIAM Product Requirements Document
status: promoted
created: 2026-08-05
updated: 2026-08-05
source: GF-03 + readiness FR/NFR/BR extraction 2026-08-04 + GF-11 REQ-VS
authority: docs/GREENFIELD-MASTER-REFERENCE.md
---

# NEXIAM Product Requirements Document

**Status:** PROMOTED for planning (slice-first backlog)  
**Product:** NEXIAM — ISPSC Tagudin admissions-management system  
**Supersedes for planning binding:** [GF-03](greenfield-foundation/03-product-scope-and-success-contract.md) (now PROMOTED; this PRD is the story-binding surface)  
**Connected-slice authority:** [GF-11](greenfield-foundation/11-connected-vertical-slice-and-acceptance.md)  
**Work process:** [project-context.md](project-context.md)

## 1. Product statement

NEXIAM is a role-isolated admissions-management system for the ISPSC Tagudin admission-testing journey: application intake, controlled applicant access, examination scheduling and administration, scoring, result release, and post-examination counseling support. Its capstone contribution is not “AI everywhere.” It is a complete, auditable institutional workflow with carefully bounded computer-vision and decision-support assistance under human authority.

**D-006:** The product promise is the whole approved NEXIAM capstone concept. The Connected Admission-to-Release Slice is the first implementation and proof milestone. No capability may be silently omitted — every feature is classified, designed, traced, and sequenced.

**D-008:** One shared, provider-agnostic AI capability boundary may support RAG, model/OMR assistance, scheduling assistance, and future AI-assisted interfaces. AI outputs remain supporting evidence; typed human/application commands remain authoritative. The chatbot (Nexi) may guide authorized actors through typed, frontend-equivalent task requests, but never receives a direct database, policy-bypass, or hidden command path.

## 2. Primary actors

### Evaluated product actors

1. Applicant  
2. Registrar Clerk  
3. Registrar Administrator  
4. Guidance Counselor  
5. Guidance Clerk  
6. Program Head  

### Contextual / system actors

- Proctor (temporary examination-session duty)  
- Registrar Support Staff / Guidance Support Staff  
- Staffing/HR Administrator (personnel lifecycle only)  
- Super Admin (break-glass only)  
- Queue / worker / scheduler  
- External services behind adapters (mail, vector/LLM, inference)

## 3. Capability cut-line

### Tier A — COMMITTED product core (E2 near-term delivery set)

Applicant intake; staff review (correct/accept/dismiss/reopen/archive); applicant authentication; examination scheduling and contextual proctoring; manual scoring; consultation and result release; applicant portal; documents and notifications; authorization matrix; critical audit evidence; Program Head projection (subject to D-ROLE-05 / evidence holds).

### Tier B — Capstone differentiators (gated; not inside E2 feature code)

OMR-assisted scoring; scoped RAG; human-gated scheduling assistance; offline proctoring; counselor decision-support engine signals. E2 must keep manual fallbacks and may ship presentation seams only (e.g. locked Nexi chrome without provider).

### Tier C — CONDITIONAL

BGE, YuKee, generic engine admin, program-quota management, expanded in-app messaging, expanded analytics — design-only until named gates pass.

### Tier D — DEFERRED / OUT OF CURRENT STUDY

Autonomous admission/placement; predictive-validity claims; enrollment/tuition/long-term records; multi-campus tenancy; arbitrary plugins; formal NPC/PIA claims; production claims from synthetic demo alone.

## 4. E2 Connected Slice (first implementation gate)

Near-term committed delivery for stories and the first E2 OpenSpec = **Tier A journeys + GF-11 `REQ-VS-001`–`REQ-VS-032`**, with gated IDs marked below. Do not expand Nexi provider/RAG, OMR promotion, or Tier C into E2.

### 4.1 Journey steps (GF-11)

1. Submit admission attempt  
2. Review / correct / accept / dismiss  
3. Establish applicant credentials  
4. Create / assign / publish exam session (manual paths)  
5. Assign contextual proctor  
6. Record attendance / submission  
7. Score manually; finalize grading  
8. Record counselor decision  
9. Prepare / release result  
10. Notify applicant; retrieve via portal  
11. Reverse / unrelease with history  
12. Prove denied paths, audit, page states, a11y  

### 4.2 REQ-VS commitment table

| ID | Acceptance statement | E2 commitment |
|---|---|---|
| REQ-VS-001 | The system can be created from an empty approved production-engine database and seeded deterministically. | COMMITTED |
| REQ-VS-002 | Every actor authenticates through the approved credential model; Applicant identifier alone is insufficient. | COMMITTED |
| REQ-VS-003 | Applicant sees only own records; staff and Program Head cross-scope access is denied. | COMMITTED |
| REQ-VS-004 | One admission attempt can be submitted, corrected under guard, accepted/dismissed, and non-destructively reopened/archived under approved rules. | COMMITTED |
| REQ-VS-005 | Session creation, applicant assignment, room/capacity/time conflicts, and publication are transactionally validated. | COMMITTED |
| REQ-VS-006 | Proctor capability exists only through an active session assignment and disappears after revocation/closure. | COMMITTED |
| REQ-VS-007 | Attendance and submission are idempotent, actor-attributed, and legal only during approved state/time windows. | COMMITTED |
| REQ-VS-008 | Manual scoring forms a complete operational fallback independent of OMR/model/provider availability. | COMMITTED |
| REQ-VS-009 | Grading cannot finalize until every required score is valid and complete. | COMMITTED |
| REQ-VS-010 | OMR, if included, produces proposals with confidence/provenance; uncertainty requires human review before authoritative import. | GATED (Tier B / E3; manual path required in E2) |
| REQ-VS-011 | Counselor decision records independent signals and approve/modify/override/defer action without autonomous state change. | COMMITTED (manual signals; engines gated) |
| REQ-VS-012 | Result release requires approved prerequisites; unrelease/reversal preserves release history. | COMMITTED |
| REQ-VS-013 | Schedule/result notification is emitted once after commit; failure is observable and retryable. | COMMITTED |
| REQ-VS-014 | Generated documents encode untrusted values safely and retain template/version/checksum evidence. | COMMITTED |
| REQ-VS-015 | Critical events are auditable, attributable, redacted, and verifiable under the approved threat model. | COMMITTED |
| REQ-VS-016 | Refresh/re-login preserves authoritative state; deterministic reset recreates the seeded demo. | COMMITTED |
| REQ-VS-017 | Provider/worker outage does not prevent the manual core journey from completing. | COMMITTED |
| REQ-VS-018 | Browser execution records no unhandled console errors, failed required network requests, or silent failures. | COMMITTED |
| REQ-VS-019 | Public submission creates/links a durable ApplicantIdentity and AdmissionAttempt, assigns Applicant No/Reference, and issues a one-time setup/access challenge after commit; acceptance remains independent; any optional Portal Account uses a real approved delivery/recovery channel without fabricated email. | COMMITTED |
| REQ-VS-020 | One effective exam deadline applies after extension to attendance, submission, manual close, and scheduler close; all close paths reconcile assignments and open grading once. | COMMITTED |
| REQ-VS-021 | Manual multi-area score writes are transactional; staged imports have durable batch identity, explicit row states, resumable idempotency, and a manual fallback. | COMMITTED (manual writes; staged import may be minimal) |
| REQ-VS-022 | Internal result preview is distinct, watermarked, and audited; applicant/external generation or distribution is denied until finalized consultation/release prerequisites pass. | COMMITTED |
| REQ-VS-023 | Applicant notification projection/list/read state is connected and scoped; an empty UI cannot mask stored unread notifications. | COMMITTED |
| REQ-VS-024 | Policy-corpus RAG and own released-data retrieval use separate authorization gates; refusal creates no inquiry until the applicant explicitly confirms fallback. | GATED (Tier B / E3; Nexi chrome only in E2) |
| REQ-VS-025 | Any Program Head projection uses approved metric definitions, program ownership, freshness, and privacy threshold and exposes no unapproved individual data. | CONDITIONAL (EVIDENCE HOLD; minimal if D-ROLE-05 path approved) |
| REQ-VS-026 | Every connected page/panel exposes an explicit initial-loading, ready, empty/not-yet-available, denied, validation, server-error, stale/conflict, and degraded/retry state where applicable; no failure is rendered as an empty success state. | COMMITTED |
| REQ-VS-027 | Staff/applicant navigation is server-authorized and includes a top-of-page breadcrumb with safe authorized ancestors, current-page semantics, scope context, mobile collapse, deep-link, and dirty-form/back behavior. | COMMITTED |
| REQ-VS-028 | Every authoritative mutation has field/server validation, idempotent submit behavior, risk-appropriate confirmation, authoritative success/pending wording, failure/retry, conflict/review, and audit correlation; a toast is never the only error or permission signal. | COMMITTED |
| REQ-VS-029 | Every dashboard chart/graphic has a scoped metric/source/freshness/unit contract, distinguishes zero/no-data/suppressed/stale/error, provides an accessible text/table alternative, and cannot mutate or decide domain state. | CONDITIONAL (only if Program Head / dashboard surfaces ship in E2) |
| REQ-VS-030 | Notification center and toasts are scoped, deduplicated, severity/duration/dismissal aware, redacted, keyboard/screen-reader accessible, and never claim delivery or release beyond authoritative evidence. | COMMITTED |
| REQ-VS-031 | Critical connected journeys work at narrow responsive widths and by keyboard, including navigation drawer, filters, dialogs, tables, charts, OMR/document preview, and degraded/recovery states. | COMMITTED |
| REQ-VS-032 | OMR/document graphics show provenance, confidence/uncertainty, review/correction state, watermark/privacy status, and authorized alternatives; they never imply autonomous score/admission/release authority. | GATED for OMR; COMMITTED for result documents |


Authority detail and scenarios: [GF-11](greenfield-foundation/11-connected-vertical-slice-and-acceptance.md).

## 5. Requirements catalogs

Stable IDs below are promoted from the 2026-08-04 implementation-readiness extraction. Map stories to these IDs plus `REQ-*` (GF-12) and `REQ-VS-*` (GF-11).

### 5.1 Functional requirements (FR-001–FR-075)

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

### 5.2 Non-functional requirements (NFR-001–NFR-055)

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

### 5.3 Business rules and constraints (BR-001–BR-083)

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

## 6. Success planes

1. **Planning coherence** — product/role/workflow/domain/security/architecture agree; every committed requirement has evidence, decision, acceptance, and test target; no open P0.  
2. **Controlled capstone demonstration** — seeded admission-to-release journey; allow/deny authz; manual fallbacks; bounded differentiator gates; executable TAM scenarios; disclosed limitations.  
3. **Pilot / production** — blocked until institutional approvals, real-data handling, ops ownership, and restore gates pass.

## 7. Out of scope for E2 OpenSpec

- E3 Nexi provider, RAG embeddings, OMR promotion, offline proctoring runtime  
- Broad feature coding from UI-kit feel alone  
- Restarting BMAD ceremony or inventing journeys not in GF-11  
- Treating Stitch MCP as a blocker  

## 8. Stop rules

Stop and record a gap when legacy disposition, authz/scope, page state, idempotency/audit, or OpenSpec coverage is missing. Do not invent product behavior from chat history.

## 9. Traceability

| Artifact | Role |
|---|---|
| This PRD | Story-binding product contract |
| GF-03 | Product scope source (PROMOTED) |
| GF-11 | Connected-slice acceptance |
| GF-12 | REQ-* matrix |
| `docs/legacy-reference/` | Behavior evidence + dispositions |
| `docs/e2-page-state-catalog.md` | Page/route/state contract |
| `docs/epics-and-stories-e2.md` | E2 epics/stories |
| `openspec/changes/e2-connected-admission-release-slice/` | Implementation handoff (after readiness PASS) |
