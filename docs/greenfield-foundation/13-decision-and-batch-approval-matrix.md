# Decision and Batch-Approval Matrix

**Artifact:** NEXIAM-GF-13  
**Status:** DRAFT — FOUNDER REVIEW REQUIRED  
**Purpose:** Let David approve, condition, revise, or reject the dependency-root decisions in one review sitting.  
**Default if unanswered:** BLOCKED; no silent acceptance.

## 1. How to respond

Accepted response forms:

- `GO ALL RECOMMENDED DEFAULTS`
- `GO ALL RECOMMENDED DEFAULTS EXCEPT D-010, D-033`
- `D-010 REVISE: ...`
- `D-033 GO WITH CONDITION: ...`
- `D-040 NO-GO: ...`
- `HOLD D-024 pending institutional retention policy`

Allowed row outcomes:

- **GO:** Accept recommended default.
- **GO WITH CONDITION:** Accept only with the stated constraint/evidence.
- **REVISE:** Replace the recommendation with founder wording.
- **NO-GO:** Reject and activate the documented fallback/consequence.
- **HOLD:** Keep downstream gate blocked pending evidence.

After review, decisions must be propagated through the suite and then audited. Approval of this matrix does not itself authorize code, pilot, or deployment.

## 2. Dependency order

Review sections in order:

1. Authority and workspace
2. Product scope and research claims
3. Actors, capabilities, and data scope
4. Identity, domain, lifecycle, and retention
5. Operational workflow
6. Capstone differentiators
7. Platform and operations
8. Readiness and promotion

A later answer cannot silently contradict an earlier one. If it does, the earlier decision is reopened.

### Canonical decision-ID rule

The numbered `D-001` through `D-080` rows are the canonical review identifiers. Other foundation files sometimes use a semantic alias for readability. The following aliases resolve to the numbered row(s); they are not separate decisions.

| Semantic alias | Canonical row(s) |
|---|---|
| D-AUTHORITY-01 | D-001, D-005 |
| D-PLAN-01 | D-004 |
| D-PLAN-02 | D-003 |
| D-ROLE-01 | D-014 |
| D-ROLE-02 | D-015, D-022 |
| D-ROLE-03 | D-016 |
| D-ROLE-04 | D-017 |
| D-ROLE-05 | D-018 |
| D-SCOPE-01 | D-021 |
| D-SCOPE-02 | D-020 |
| D-SCOPE-03 | D-023, D-051 |
| D-AUTH-01 | D-025 |
| D-AUTH-02 | D-026 |
| D-DOM-01 | D-024 |
| D-DOM-02 | D-024 |
| D-CYCLE-01 | D-028, D-033 |
| D-DATA-01 | D-027 |
| D-DATA-02 | D-029 |
| D-DATA-03 | D-030 |
| D-DATA-04 | D-031, D-043 |
| D-DATA-05 | D-032, D-044 |
| D-DATA-06 | D-035, D-062 |
| D-DATA-07 | D-033 |
| D-RET-01 | D-034, D-035 |
| D-WF-03 | D-032 |
| D-WF-04 | D-044, D-045 |
| D-RES-01 | D-012 |
| D-RES-02 | D-035, D-046, D-047 |
| D-RAG-01 | D-008, D-051 |
| D-RAG-02 | D-034, D-051 |
| D-RAG-03 | D-051 |
| D-ENG-01 | D-048 |
| D-ENG-02 | D-050 |
| D-SCHED-01 | D-009 |
| D-OFF-01 | D-053 |
| D-EVAL-01 | D-013, D-072 |
| D-EVAL-02 | D-012, D-013 |
| D-AUD-01 | D-052 |
| D-PRIV-01 | D-034, D-072 |
| D-PRIV-02 | D-034, D-035 |
| D-PRIV-03 | D-051, D-067, D-072 |
| D-UPLOAD-01 | D-062 |
| D-TECH-01 | D-057 |
| D-TECH-02 | D-058 |
| D-TECH-03 | D-056 |
| D-TECH-04 | D-061 |
| D-TECH-05 | D-060, D-062 |
| D-TECH-06 | D-051, D-067 |
| D-OPS-01 | D-060 |
| D-OPS-02 | D-065, D-067 |
| D-OPS-03 | D-066 |
| D-INTAKE-01 | D-073 |
| D-STATUS-01 | D-074 |
| D-EXAM-01 | D-075 |
| D-GRADING-01 | D-076 |
| D-RELEASE-01 | D-077 |
| D-DOCUMENT-01 | D-078 |
| D-INQUIRY-01 | D-079 |
| D-REPORT-01 | D-080 |

---

## A. Authority and workspace

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-001 | Source-of-truth hierarchy | Founder-approved decisions → verified panel/policy constraints → approved product/domain/security/research contracts → ADR/architecture → stories/OpenSpec → tests/code; legacy remains evidence | Competing documents continue to block implementation | Binding panel/policy decision conflicts | ____ |
| D-002 | BMAD entry point | Use controlled recovery bridge: validate Phase 1 evidence, rebuild/validate Phase 2 PRD, complete Phase 3 solutioning, readiness before Phase 4 | Full restart wastes evidence; direct Phase 4 is NO-GO | Founder chooses full ceremony or new product direction | ____ |
| D-003 | Greenfield workspace | Build in a clean, isolated repository/worktree selected before scaffolding; freeze legacy evidence separately | Existing 66 modified and untracked artifacts may contaminate baseline | Legacy changes are intentionally promoted and reviewed | ____ |
| D-004 | Existing greenfield plans | Retain as strategy/backlog; replace gate numbers with stable IDs; do not treat as direct execution authority | Current internal gate/phase contradictions remain dangerous | Reconciled plan passes readiness | ____ |
| D-005 | Artifact promotion | All new foundation files remain DRAFT until batch decisions propagate and a fresh readiness audit passes | Existence could be mistaken for approval | Review protocol changes | ____ |

## B. Product scope and research claims

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-006 | Core product promise | Commit admission intake → acceptance/auth → exam/attendance → manual scoring → consultation → release → applicant retrieval, with RBAC/audit/notifications | Capstone lacks a coherent institutional journey | Adviser narrows/changes objectives | ____ |
| D-007 | OMR scope | Commit one confidence-gated OMR workflow only after real input/ground-truth gate; manual scoring remains complete fallback | OMR remains prototype/deferred; manuscript claim must narrow | Sheet/device/evidence unavailable or spike fails | ____ |
| D-008 | RAG scope in capstone | Commit only if final manuscript/TAM keeps it; use cited corpus + approved own-data retrieval + refusal/fallback, no placement advice | Defer and amend manuscript/instrument | Privacy/corpus/stakeholder evidence changes | ____ |
| D-009 | Scheduling assistance | Manual scheduling is authoritative complete baseline; AI/optimization only proposes for human confirmation after a constraint spike | Remove “optimized” claim and ship manual scheduling | Objective/solver/constraints validated | ____ |
| D-010 | Admission-support engines | Preserve interface/provenance foundation; functional engines only after method/data gates; no autonomous decision | Defer Yukee/BGE and keep counselor workflow/manual evidence | Parameters, corpus, expert mapping become available | ____ |
| D-011 | Conditional features | BGE, generic registry, broader messaging, offline sync, quota writes, and broad analytics are conditional—not core slice | If manuscript retains them as completed scope, implementation burden increases | Adviser/panel requires feature | ____ |
| D-012 | Technical validation versus TAM | TAM measures PU/PEOU only; OMR/RAG/model/audit/scheduling require independent technical evidence | Technical correctness claims must be removed | Adviser approves another valid technical methodology | ____ |
| D-013 | Success planes | Report planning coherence, controlled demo, and pilot/production readiness separately | Avoid any “system ready” umbrella verdict | Actual pilot/production gate passes | ____ |

## C. Actors, capabilities, and data scope

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-014 | Base role catalog | Applicant, Registrar Staff, Registrar Administrator, Guidance Counselor, Guidance Administrator, Program Head, System IT Administrator | Auth/schema/policy implementation remains blocked | Final manuscript/panel terminology changes | ____ |
| D-015 | Proctor | Temporary ExamSession duty assignment; no permanent Proctor role | Permanent role requires its own least-privilege and assignment semantics | Stakeholders require dedicated personnel class | ____ |
| D-016 | Test Administrator | Capability set granted to approved Guidance actors, not a base role | Retaining role expands catalog and duplicates Guidance authority | Institutional staffing requires separate role | ____ |
| D-017 | Super Admin | Optional non-evaluated BREAK-GLASS account only; never routine operations | Remove entirely if operational recovery can be handled by System IT with dual control | Security/operations review | ____ |
| D-018 | Program Head | Read-only, program-scoped aggregate view; no raw scores, audit export, or quota writes unless explicitly approved | Hide Program Head dashboard until authority is validated | Program Head/privacy validation | ____ |
| D-019 | System IT authority | Accounts, security/config, integrations, audit operations; no routine score/counseling/release power | Broader access must be BREAK-GLASS and audited | Institutional IT mandate | ____ |
| D-020 | Account creation delegation | System IT governs permanent roles; Registrar/Guidance Admin may create only subordinate office accounts within a role ceiling | Central System IT creates all accounts | Staffing/HR workflow evidence | ____ |
| D-021 | Registrar/Guidance cross-office access | Capability-specific shared views; default deny instead of role-wide access to all applications/scores | Workflow cannot implement until shared fields/actions are listed | EV-001/EV-002 validation | ____ |
| D-022 | Proctor assignment owner | Guidance Administrator assigns/revokes by default; Guidance Counselor may receive delegated capability | Keep assignment owner unresolved and block Proctor policy | Guidance validation | ____ |
| D-023 | Audit-view scope | System IT security view; office-scoped review only after explicit approval; Applicant access-history view optional | Restrict audit to System IT/break-glass | Privacy/institutional review | ____ |

## D. Identity, domain, lifecycle, and retention

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-024 | Applicant lifetime | Durable ApplicantIdentity with optional Portal Account and repeatable cycle-scoped AdmissionAttempts | One-record-per-cycle requires explicit re-registration/history linking design | Institutional identity policy | ____ |
| D-025 | Applicant authentication | Non-secret identifier + password; hashed one-time setup/reset, expiry, atomic use, rate limiting; activation requires a real approved recovery/delivery channel | Identifier-only access or fabricated recovery address is security NO-GO | Approved OTP/passkey/institutional SSO/non-email recovery design | ____ |
| D-026 | Staff authentication | Named local accounts first; defer Google OAuth until linking/domain/verified-email/takeover rules pass | No OAuth in core slice | Institutional identity provider approved | ____ |
| D-027 | Identity duplicate rule | Define normalized identity key scoped to approved cycle/person model and enforce at DB; provide explicit duplicate-resolution path | Intake remains race-prone and ambiguous | Institutional identifiers become available | ____ |
| D-028 | Workflow source of truth | Commands own domain state; stage/status dashboards are projections from completed milestones, not independently mutable truths | Dual mutable status fields remain NO-GO | Approved domain model demonstrates need for independent aggregate | ____ |
| D-029 | Reopen behavior | Reopen/archive/version without deleting ApplicantAccount, assignments, scores, decisions, messages, releases, or audit | Legacy destructive reopen is prohibited | Approved retention policy permits narrower destruction | ____ |
| D-030 | Retake/reassignment | Scope uniqueness to AdmissionAttempt/cycle/mode; preserve prior ExamAssignments and reasons | Global applicant uniqueness remains until rule decided | Guidance exam policy | ____ |
| D-031 | Grading cardinality | One active GradingSession per assessment unit; corrections are versioned/reasoned | Multiple sessions only through explicit version model | Guidance scoring workflow | ____ |
| D-032 | Release reversal | Unrelease creates reversal/supersession event and changes current visibility; prior release/document remains evidence | Remove unrelease capability if reversals are institutionally forbidden | Guidance release policy | ____ |
| D-033 | Reference-data lifecycle | Deactivate/version courses, rooms, aptitude areas, templates, and mappings referenced by history; no destructive deletion | Deletion requires migration/archive proof | Institutional retention rule | ____ |
| D-034 | Personal/assessment retention | Default archive and minimum necessary access; exact periods and erasure/restriction remain HOLD until institutional/privacy approval | Real-data pilot/production stays blocked | EV-013 closes | ____ |
| D-035 | OMR/raw model artifact retention | Private restricted storage; separate short raw-input retention from longer derived provenance; exact period HOLD | Use synthetic fixtures only | Privacy/storage approval | ____ |

## E. Operational workflow

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-036 | Application decision owner | Registrar Administrator accepts/dismisses; Registrar Staff may be delegated correction/intake but not final decision by default | Final-decision capability remains blocked | Registrar validation | ____ |
| D-037 | Administrative window override | Normal intake decisions respect application window; exceptional override requires capability, reason, and audit | No override, or unrestricted override—both must be explicit | Registrar policy | ____ |
| D-038 | Session ownership | Guidance owns exam scheduling/publish/start/close; Registrar receives only approved visibility/input | Shared ownership remains blocked | Guidance/Registrar process map | ____ |
| D-039 | Session timing override | Only explicit privileged Guidance/break-glass command with reason/audit; normal Proctor follows time window | Remove override if institution forbids | Exam operations evidence | ____ |
| D-040 | Auto-close | After grace: no attendance → cancelled; any attendance → completed with unresolved records surfaced; locked/idempotent job | Manual-only close if policy disagrees | Guidance exam policy and grace period | ____ |
| D-041 | Attendance/submission | Separate idempotent commands, assigned applicant only, active session/time window; corrections require reason | Define paper/offline reconciliation separately | Proctor/Guidance validation | ____ |
| D-042 | Direct assessment | Separate assessment mode sharing grading/release invariants; cannot mark scored with zero/partial required scores | Defer direct assessment from first slice | Guidance workflow | ____ |
| D-043 | Score correction | Before finalization: controlled edit; after finalization: reopen/version with reason and audit, no silent overwrite | Forbid correction after finalization if policy requires | Guidance scoring policy | ____ |
| D-044 | Consultation/release prerequisites | Finalized complete scores plus approved consultation/release-mode conditions; counselor remains final human actor | Exact mode-specific conditions remain HOLD | EV-001 resolves online/F2F conflict | ____ |
| D-045 | Release notification behavior | Delivery occurs once after commit and is retryable; exact online/F2F recipient/content is HOLD | Use generic test notification only | Guidance validation | ____ |

## F. Capstone differentiators

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-046 | OMR input contract | Approved fixed template/version and intended scanner/camera channels; reject wrong/malformed inputs | OMR deferred | EV-008 closes | ____ |
| D-047 | OMR authority | OMR proposes observations/scores; approved human review/import command creates authoritative scores | Fully automatic scoring is NO-GO until stronger evidence | Benchmark and institutional policy | ____ |
| D-048 | Yukee method | Reproduce exact model only with parameters/transforms; otherwise label as NEXIAM deterministic aptitude-profile baseline | Defer engine if honest baseline is not accepted | EV-009 closes | ____ |
| D-049 | Program mapping | Mapping is versioned expert-approved reference data, separate from model output | Do not show program-fit mapping | EV-005/EV-006 close | ____ |
| D-050 | BGE method | Conditional embedding-similarity signal using genuine interest/goal narrative and approved program corpus; never call supervised | Defer BGE | EV-006 and relevance spike close | ____ |
| D-051 | RAG data boundary | Cited approved corpus + authenticated own result/schedule data; no cross-applicant or placement recommendation | Policy-only static FAQ or defer | Privacy/stakeholder decision | ____ |
| D-052 | Audit assurance wording | “Tamper-evident under defined threat model”; canonical events, serialized chain, key version, verification, checkpoints | Use conventional append-only audit with no crypto claim | Audit spike/threat review | ____ |
| D-053 | Offline proctoring | Conditional only with outbox, idempotent replay, conflict detection/manual resolution; no cache-only sync claim | Defer offline; retain online/paper fallback | EV-011 and sync spike | ____ |
| D-054 | Generic engine registry | Foundation is a fixed `AdmissionSupportEngine` contract with approved adapters; no arbitrary endpoint/class/UI plugin execution | Full generic registry deferred | Multiple governed engines genuinely require runtime registration | ____ |

## G. Platform and operations

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-055 | Backend/frontend versions | PHP 8.4, Laravel 12, Inertia 2, Svelte 5, TypeScript 5.9, Tailwind 4; no “latest stable” | Re-audit if versions change | Security/support compatibility change | ____ |
| D-056 | Node runtime | Pin Node 24 LTS across CI and images | Choose Node 20 only with support/compatibility rationale | Dependency compatibility evidence | ____ |
| D-057 | Production database | MySQL 8.4 LTS to align Compose; MySQL integration/migration evidence required; SQLite limited to parity-safe tests | Switch all architecture/Compose/rehearsal authority to MariaDB explicitly | Engine-specific need | ____ |
| D-058 | Backend test framework | PHPUnit 11, matching project authority; no mixed Pest suite | Explicitly revise project convention to Pest before scaffold | Team capability/convention decision | ____ |
| D-059 | Browser/frontend tests | Vitest for real frontend units and Playwright for connected journeys; zero discovered tests is failure | Browser evidence becomes manual and readiness cannot pass | Tool compatibility | ____ |
| D-060 | Queue baseline | Laravel database queue initially, dedicated worker, `after_commit=true`, idempotency/retry/dead-letter visibility | Redis may replace database queue by ADR | Measured load/operational need | ____ |
| D-061 | Inference topology | One authenticated internal Python worker/sidecar for approved OMR/BGE; no direct DB writes | Defer Python-dependent capabilities | Isolation/scale evidence justifies split | ____ |
| D-062 | File storage | Private Laravel filesystem volume for controlled demo, object-storage adapter for pilot; checksum/version/backup contract | Object storage from day one by ADR | Pilot durability/scale requirement | ____ |
| D-063 | Local/test mail | Mailpit companion service; production provider behind mail adapter | Another test sink only if equally reproducible | Deployment environment | ____ |
| D-064 | Deployment baseline | One robust multi-service Compose: app/web, queue worker, scheduler, MySQL, optional approved Python worker, Mailpit local/test; Dokploy/Traefik ingress | Separate app/database provisioning is rejected unless explicitly chosen | Hosting constraints | ____ |
| D-065 | Quality baseline | Greenfield starts at zero known lint/type/static/test failures and no permanent baseline; CI green before promotion | Temporary baseline requires explicit expiry/owner and cannot hide new app debt | Tool false positives | ____ |
| D-066 | Backup/restore | Database plus required private files, off-machine copy, restore rehearsal, RPO/RTO before pilot | Controlled demo only until approved | Pilot/production planning | ____ |
| D-067 | Provider degradation | Manual core workflow remains available when mail/model/vector/LLM/inference provider fails; failure visible/retryable | Provider becomes hard dependency only by explicit scope decision | Institutional operational requirement | ____ |

## H. Readiness and promotion

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-068 | First implementation target | Build `Connected Admission-to-Release Slice`, not all 14 phases or isolated CRUD modules | Another slice requires a new traceable priority decision | Capstone deadline/scope changes | ____ |
| D-069 | Spike sequencing | Run OMR/RAG/model/audit/offline spikes before their production implementation and before broad parity work | Failed late feasibility may jeopardize capstone | Inputs unavailable; activate fallback/defer | ____ |
| D-070 | OpenSpec role | Subordinate executable change contract after approved BMAD/product/domain/ADR authority | OpenSpec cannot become competing product authority | Workflow tooling changes | ____ |
| D-071 | Readiness verdict | No Phase 4 until no open P0, first slice traceability is complete, and fresh readiness says PASS/conditional PASS | Conditional Phase 0 may only run non-locking scaffold experiments | Founder explicitly accepts rework risk | ____ |
| D-072 | Pilot/deployment clearance | Remain blocked after planning/implementation PASS until privacy, stakeholder, technical, operations, and restore gates pass | Controlled synthetic demo only | G-DEPLOY closes | ____ |

## I. Operational-contract reconciliation

These rows were added after the three bounded controller/service journeys completed. They close choices that were hidden by divergent legacy paths.

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-073 | Public intake and account provisioning | Permit unauthenticated public submission and authorized staff intake to create/link a durable ApplicantIdentity plus cycle-scoped AdmissionAttempt; create/activate the Portal Account only after acceptance; require a real approved recovery/delivery channel before activation and never fabricate a fallback email | Require pre-registration/authentication before application, or provide an approved non-email setup/recovery channel | Registrar/applicant workflow and institutional contact policy | ____ |
| D-074 | Admission-decision vocabulary | Use `pending`, `needs_correction`, `accepted`, `dismissed`, and `archived`; represent expired/rejected/incomplete legacy meanings as explicit reason/history unless stakeholders approve a distinct state | Keep downstream intake, reports, and migrations blocked because legacy status meanings conflict | Registrar workflow and manuscript terminology | ____ |
| D-075 | Exam deadline, close, and reopen semantics | Use one effective deadline everywhere; extension changes attendance/submission and auto-close windows; manual and automatic close invoke one idempotent command that records close outcome and opens grading exactly once; incomplete close needs reasoned override; reopen is allowed only before grading starts, otherwise use a superseding correction/retake path | Preserve divergent cutoffs/close paths only by explicit policy, with higher reconciliation risk | Proctor/Guidance operations validation | ____ |
| D-076 | Grading state and import semantics | One active grading version per exam; `open → review → finalized`, with score presence as a projection; multi-area manual saves are transactional; imports use one staged durable batch, explicit row states, row-atomic resumability, idempotency, and manual fallback; post-finalization correction creates a new version | Choose whole-file atomic imports or remove `review`, but document failure/recovery consequences | Guidance scoring/import workflow | ____ |
| D-077 | Consultation, release, preview, and publication authority | Counselor authors/changes the human decision; an approved Guidance Counselor/Admin capability releases after finalized scoring and consultation prerequisites; internal preview is distinct and watermarked/audited; applicant/external print/export requires current released version; corrections use explicit unrelease/new release | Collapse consultation and release into one role/state only with approved separation-of-duty and privacy rationale | Guidance workflow and online/F2F policy | ____ |
| D-078 | Document/template and print semantics | One versioned template aggregate per document type; typed format contracts; record generated/downloaded/distributed/physically-printed as distinct evidence; admission slip requires accepted attempt; result external distribution requires release; large batches are observable jobs | Retain competing system-setting/database template paths only after choosing one authority and migration plan | Registrar/Guidance document workflow and template inventory | ____ |
| D-079 | RAG access and refusal-to-inquiry handoff | Institutional policy RAG may be available before consultation release; own scores/results/consultation are included only when their authoritative release permits; refusal shows staff fallback and creates a bounded Guidance inquiry only after applicant confirmation; general inter-office messaging remains conditional | Make RAG entirely post-release/policy-only, or defer inquiry threads and use static contact instructions | Manuscript/TAM scope, privacy, and Guidance SLA | ____ |
| D-080 | Program Head/report metric and audit contract | Define program ownership, quota source/version, “matched” meaning, freshness, inactive-program history, and privacy aggregation thresholds before dashboard use; Program Head remains read-only/aggregate; every protected report view/export is audited and spreadsheet-safe | Defer Program Head and expose reports only to approved Registrar/Guidance roles | Program Head validation and privacy review | ____ |

## 3. Suggested one-session review order

For fastest safe review:

1. Approve/revise D-001 through D-013.
2. Resolve D-014 through D-035; these control schema and authorization.
3. Mark evidence-dependent workflow rows D-036 through D-045 as GO defaults or HOLD.
4. Approve/defer differentiators D-046 through D-054.
5. Lock stack and delivery contract D-055 through D-067.
6. Confirm readiness controls D-068 through D-072.
7. Resolve the operational reconciliation rows D-073 through D-080.

## 4. Promotion record template

After review:

| Decision ID | Outcome | Final wording/condition | Approved by | Date | Affected artifacts updated | Verification |
|---|---|---|---|---|---|---|
| D-___ | GO / CONDITIONAL / REVISE / NO-GO / HOLD | ___ | David | ___ | ___ | ___ |

## 5. Current Go/No-Go

- Batch founder decision: **PENDING**
- Planning recovery: **IN PROGRESS**
- Greenfield scaffold: **NO-GO except non-locking clean-workspace experiment after D-003/D-055–D-065**
- Domain/schema implementation: **NO-GO**
- Controlled capstone demonstration: **NO-GO**
- Real pilot/production: **BLOCKED**
