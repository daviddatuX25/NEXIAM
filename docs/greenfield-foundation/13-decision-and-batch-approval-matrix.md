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
| D-001 | Source-of-truth hierarchy | Founder-approved decisions → verified panel/policy constraints → approved product/domain/security/research contracts → ADR/architecture → stories/OpenSpec → tests/code; legacy remains evidence | Competing documents continue to block implementation | Binding panel/policy decision conflicts | GO — approved by David on 2026-08-02 |
| D-002 | BMAD entry point | Use controlled recovery bridge: validate Phase 1 evidence, rebuild/validate Phase 2 PRD, complete Phase 3 solutioning, readiness before Phase 4 | Full restart wastes evidence; direct Phase 4 is NO-GO | Founder chooses full ceremony or new product direction | GO — approved by David on 2026-08-02; preserve tested legacy behavior and capstone documents as evidence inputs while rebuilding the approved truth-source, requirements, HLD, LLD/domain, and implementation chain |
| D-003 | Greenfield workspace | Build in a clean, isolated repository/worktree selected before scaffolding; freeze legacy evidence separately | Existing 66 modified and untracked artifacts may contaminate baseline | Legacy changes are intentionally promoted and reviewed | GO WITH CONDITION — approved by David on 2026-08-02; begin greenfield implementation after authority, requirements, HLD, LLD/domain, frontend/backend reconciliation, and readiness gates pass; preserve legacy high-fidelity frontend behavior as reference evidence, then intentionally reproduce and improve it rather than copying legacy architecture |
| D-004 | Existing greenfield plans | REVISE: compound and merge the existing greenfield architecture/implementation plans with the stronger foundation suite into one coherent engineered chain for requirements, HLD, LLD/domain, ADRs, implementation phases, and readiness gates; retain useful frontend/backend/phase detail, reconcile contradictions, then mark standalone superseded plans as historical | Separate or conflicting plans remain unsafe and implementation stays blocked | Merged plan is internally consistent, approved, and passes readiness | REVISE — approved by David on 2026-08-02; compound and merge into the stronger canonical planning chain before implementation |
| D-005 | Artifact promotion | All new foundation files remain DRAFT until batch decisions propagate and a fresh readiness audit passes | Existence could be mistaken for approval | Review protocol changes | GO — approved by David on 2026-08-02; keep planning active but do not promote artifacts to canonical status until the matrix, merged engineering chain, propagation, and fresh readiness audit pass |

## B. Product scope and research claims

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-006 | Core product promise | REVISE: commit the full approved NEXIAM capstone product vision and complete feature/research boundary, with every capability explicitly classified as committed, conditional, deferred, or excluded; use the Connected Admission-to-Release Slice as the first implementation/proof milestone, not as a replacement for the whole capstone promise | Product scope is incomplete or silently narrowed; implementation remains unable to claim full capstone coverage | Adviser/panel changes the capstone objectives or feature commitments | REVISE — approved by David on 2026-08-02; build the whole capstone concept through an evidence-backed, phased engineering plan while preserving the connected slice as the first proof milestone |
| D-007 | OMR scope | Commit one confidence-gated OMR workflow only after real input/ground-truth gate; manual scoring remains complete fallback | OMR remains prototype/deferred; manuscript claim must narrow | Sheet/device/evidence unavailable or spike fails | GO WITH CONDITION — approved by David on 2026-08-02; retain OMR in the full capstone plan, but require approved artifacts, ground truth, thresholds, human review, idempotent reviewed import, and complete manual fallback before implementation/claim; also retain the ML/model engine, RAG chatbot, and AI-assisted interface direction for separate D-008/D-010/D-011 decisions and gates |
| D-008 | RAG scope in capstone | Commit the RAG chatbot to the full capstone when the final manuscript/TAM retains it; use cited approved corpus plus authorized own-data retrieval, refusal/fallback, and no placement advice | Defer and amend manuscript/instrument | Privacy/corpus/stakeholder evidence changes | GO WITH CONDITION — approved by David on 2026-08-02; include a shared provider-agnostic AI capability boundary for the RAG chatbot and future model/OMR, scheduling, and AI-assisted UI features; AI-automated reporting is not part of the current capstone commitment; the application never grants direct model authority; library/SDK/provider specifics require later research, ADR, privacy, authorization, grounding, prompt-injection, outage, and human-fallback gates |
| D-009 | Scheduling assistance | Manual scheduling is authoritative complete baseline; AI/optimization only proposes for human confirmation after a constraint spike | Remove “optimized” claim and ship manual scheduling | Objective/solver/constraints validated | GO — approved by David on 2026-08-02; manual scheduling remains authoritative, AI only proposes under the constraint/feasibility gate, and an authorized human confirms, edits, or rejects |
| D-010 | Admission-support engines | Preserve interface/provenance foundation; functional engines only after method/data gates; no autonomous decision | Defer Yukee/BGE and keep counselor workflow/manual evidence | Parameters, corpus, expert mapping become available | GO — approved by David on 2026-08-02; include the ML/model engine direction in the full capstone plan, with documented method/inputs/parameters/transformations/versions, provenance and limitations, human counselor authority, honest baseline labeling when exact reproduction is unavailable, and complete manual fallback |
| D-011 | Conditional features | BGE, generic registry, broader messaging, offline sync, quota writes, and broad analytics are conditional—not core slice | If manuscript retains them as completed scope, implementation burden increases | Adviser/panel requires feature | GO — approved by David on 2026-08-02; retain these feature families in the complete capstone plan, recover and cross-reference existing manuscript/capstone/RAD/phase evidence, and promote each through its own detailed requirements, technical, security, and readiness gates; AI-automated reporting remains excluded |
| D-012 | Technical validation versus TAM | TAM measures PU/PEOU only; OMR/RAG/model/audit/scheduling require independent technical evidence | Technical correctness claims must be removed | Adviser approves another valid technical methodology | GO — approved by David on 2026-08-02; keep TAM as a later capstone evaluation instrument only, separate from system architecture/build priority; build the system from approved product, domain, workflow, security, and engineering requirements, and validate technical claims with technical tests |
| D-013 | Success planes | Keep planning/foundation readiness separate, but combine working-system/demo readiness with pilot/production readiness into one system-ready gate | Avoid calling the plan ready when the system is not technically usable and complete | Actual combined system-ready gate passes | REVISE — approved by David on 2026-08-02; planning readiness is separate, while demo readiness and pilot/production readiness are one combined system-ready outcome |

## C. Actors, capabilities, and data scope

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-014 | Base and office role catalog | Applicant; Staffing/HR Administrator; `registrar_admin`; `registrar_clerk`; `guidance_counselor`; `guidance_clerk`; `program_head`; plus `registrar_support_staff` and `guidance_support_staff` as subordinate/temporary office roles; Super Admin is break-glass; Proctor is contextual duty and Test Administrator is a capability set, not a base role | Auth/schema/policy implementation remains blocked | Final manuscript/panel terminology changes | REVISE — approved by David on 2026-08-02; remove the System IT base role, use HR/Staffing for permanent office personnel lifecycle, retain explicit legacy mappings, and keep Super Admin break-glass; Proctor/Test Administrator delegation remains governed by D-015/D-016 |
| D-015 | Proctor | Contextual ExamSession duty, not a permanent base role; eligible Guidance Clerk/Guidance Support Staff may receive it, and an authorized Test Administrator may assign/revoke it; session scope is enforced through the assignment record/policy | Permanent role requires its own least-privilege and assignment semantics | Stakeholders require dedicated personnel class | GO — approved by David on 2026-08-02; Proctor is an assignment-scoped duty overlay, not a permanent role |
| D-016 | Test Administrator | Capability set, not a base role; Guidance Counselor has it inherently, while Guidance Clerk and Guidance Support Staff may receive it through approved Guidance delegation | Retaining it as a permanent role expands the catalog and duplicates Guidance authority | Institutional staffing requires separate role | GO — approved by David on 2026-08-02; legacy `test_administrator` is mapped into the capability set, with grant/revoke and use audited and Proctor assignment limited to authorized capability holders |
| D-017 | Super Admin and staff lifecycle authority | Keep Super Admin as optional non-evaluated BREAK-GLASS support; add separate Staffing/HR authority for permanent personnel and support-staff lifecycle; Registrar and Guidance offices may also create their own support staff; Guidance owns Test Administrator/Proctor operations | Use of Super Admin for routine staffing or office operations creates excessive privilege | Security/operations and staffing workflow review | GO WITH CONDITION — approved by David on 2026-08-02; HR may create/manage approved permanent and support-staff accounts, while Registrar and Guidance may create their own office support staff within role ceilings; HR cannot grant Test Administrator/Proctor capability; Super Admin remains exceptional technical/support only |
| D-018 | Program Head | Read-only, program-scoped aggregate and authorized applicant-record view; may view the result/remark sheet and converted per-domain scores, but not raw answer sheets, bubble scans, or the confidential conversion table; no score/release/admission writes | Hide Program Head dashboard until authority is validated | Program Head/privacy validation | GO WITH CONDITION — approved by David on 2026-08-02; Program Head may view authorized program-scoped applicant records and converted result evidence, with every protected view audited and future applicant-facing access-history visibility designed separately |
| D-019 | Technical support authority | No System IT base role; exceptional technical/configuration/integration/recovery support is performed through time-bound, reasoned, audited Super Admin break-glass access or an explicitly delegated support capability | Routine technical support via unrestricted permanent privilege is unsafe | Institutional support/operations mandate | GO WITH CONDITION — approved by David on 2026-08-02; do not create a System IT role, use Super Admin break-glass for exceptional “do it for me” support, and keep all such actions time-bound, reasoned, audited, and outside routine office operations |
| D-020 | Account creation delegation | HR may create approved permanent and support-staff accounts; Registrar Administrator may create Registrar Support Staff; Guidance Counselor/authorized Guidance authority may create Guidance Support Staff; support staff inherit their office Clerk capability by default | Unbounded cross-office capability grants or support staff with unexplained permissions weaken separation of duties | Staffing/HR workflow evidence | GO WITH CONDITION — approved by David on 2026-08-02; HR may create/manage approved personnel accounts including support staff, Registrar may create Registrar Support Staff, and Guidance may create Guidance Support Staff; support staff default to Clerk responsibilities; Test Administrator and Proctor remain separate Guidance-controlled capability/duty overlays |
| D-021 | Registrar/Guidance cross-office access | Capability-specific shared views with default deny; Registrar may access intake/application records but not converted result/remark sheets or converted per-domain scores; authorized Guidance/Test Administration users and Program Head may view approved converted result evidence within scope | Workflow cannot implement until shared fields/actions are listed | EV-001/EV-002 validation | GO WITH CONDITION — approved by David on 2026-08-02; add a distinct result-evidence view capability, keep Registrar denied from converted scores/result sheets, allow Guidance and assigned Program Head scope, allow Applicant own released result, deny raw answer sheets/conversion tables outside authorized Guidance processing, and audit every protected view; applicant-facing access history/notice proceeds through D-023 |
| D-022 | Proctor assignment owner | Authorized Guidance Test Administrator assigns/revokes Proctor duty; HR and Registrar have no Proctor authority | Keep assignment owner unresolved and block Proctor policy | Guidance validation | ____ |
| D-023 | Audit-view scope | Super Admin break-glass security view; office-scoped review only after explicit approval; Applicant access-history view/notice for approved protected result-view events | Restrict audit to break-glass/approved office scope | Privacy/institutional review | ____ |

## D. Identity, domain, lifecycle, and retention

| ID | Decision | Recommended default | If rejected/held | Review trigger | Founder outcome |
|---|---|---|---|---|---|
| D-024 | Applicant lifetime | Durable ApplicantIdentity with optional Portal Account/access credential and repeatable cycle-scoped AdmissionAttempts; historical attempts, released results, consultation evidence, and audit history remain linked after a cycle closes | One-record-per-cycle requires explicit re-registration/history linking design | Institutional identity policy | GO WITH CONDITION — approved by David on 2026-08-02; retain one durable ApplicantIdentity across cycles, allow optional portal credentials after submission, and never delete historical attempts or evidence merely because an academic cycle ends |
| D-025 | Applicant authentication | Applicant No/Applicant Reference is a non-secret identifier; after successful submission, issue a short-lived one-time hashed setup/access challenge through a real approved channel; challenge may establish a password or approved credential; result access remains separately gated by ResultRelease; reset/recovery is hashed, expiring, atomic, rate-limited, and non-enumerating | Identifier-only access, birthday-only authentication, fabricated recovery address, or a challenge issued only after acceptance is security NO-GO | Approved delivery/recovery channel, OTP/passkey/password decision, and applicant support procedure | GO WITH CONDITION — approved by David on 2026-08-02; issue applicant access after submission rather than acceptance; Applicant No is not a bearer secret; personal facts such as birthday may support assisted recovery but cannot be the sole authenticator |
| D-026 | Staff authentication | Named local accounts first; defer Google OAuth until linking/domain/verified-email/takeover rules pass | No OAuth in core slice | Institutional identity provider approved | ____ |
| D-027 | Identity duplicate rule | Define normalized identity key scoped to approved cycle/person model and enforce at DB; generate a collision-safe Applicant No/Applicant Reference unique to the AdmissionAttempt and use it as the answer-sheet binding key; provide explicit duplicate-resolution path | Intake remains race-prone and ambiguous | Institutional identifiers become available | GO WITH CONDITION — approved by David on 2026-08-02; Applicant No/Reference is stable per attempt, non-secret, printed or encoded on the answer sheet, and is never replaced by a required admission slip |
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
| D-038 | Session ownership | Guidance owns exam scheduling/publish/start/close and consultation-availability publication; Registrar receives only approved visibility/input; F2F consultation uses Guidance/Test Administrator batch scheduling, while online consultation uses published availability and applicant reservation | Shared ownership remains blocked | Guidance/Registrar process map | GO WITH CONDITION — approved by David on 2026-08-02; Guidance owns the exam and consultation workflow, F2F is the default mode, and online mode must not force manual per-applicant batch scheduling |
| D-039 | Session timing override | Only explicit privileged Guidance/break-glass command with reason/audit; normal Proctor follows time window | Remove override if institution forbids | Exam operations evidence | ____ |
| D-040 | Auto-close | After grace: no attendance → cancelled; any attendance → completed with unresolved records surfaced; locked/idempotent job | Manual-only close if policy disagrees | Guidance exam policy and grace period | ____ |
| D-041 | Attendance/submission | Separate idempotent commands, assigned applicant only, active session/time window; corrections require reason | Define paper/offline reconciliation separately | Proctor/Guidance validation | ____ |
| D-042 | Direct assessment | Separate assessment mode sharing grading/release invariants; cannot mark scored with zero/partial required scores | Defer direct assessment from first slice | Guidance workflow | ____ |
| D-043 | Score correction | Before finalization: controlled edit; after finalization: reopen/version with reason and audit, no silent overwrite | Forbid correction after finalization if policy requires | Guidance scoring policy | ____ |
| D-044 | Consultation/release prerequisites | Finalized complete scores; Guidance prepares/prints result sheets as required before external release; F2F is the default and uses published batch consultation availability with printed results; online release may occur through the portal while consultation remains open through office availability/reservation; Counselor remains final human actor for the decision | Exact mode-specific conditions remain HOLD | EV-001 resolves online/F2F conflict | GO WITH CONDITION — approved by David on 2026-08-02; score → result preparation/printing → mode-specific consultation/release handling is the authoritative sequence, with F2F and online branches explicitly separated |
| D-045 | Release notification behavior | After-commit delivery is idempotent, observable, retryable, and non-blocking to the authoritative workflow; F2F publishes/announces consultation batches and online release notifies the applicant while exposing open consultation availability | Use generic test notification only | Guidance validation | GO WITH CONDITION — approved by David on 2026-08-02; F2F and online notification content/recipient rules are separate projections, and delivery failure never rolls back scoring, printing, consultation, or release state |

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
| D-073 | Public intake and account provisioning | Permit unauthenticated public submission and authorized staff intake to create/link a durable ApplicantIdentity plus cycle-scoped AdmissionAttempt and Applicant No/Reference; issue a hashed one-time setup/access challenge immediately after successful submission, not after Registrar acceptance; require a real approved delivery/recovery channel and never fabricate a fallback email | Require pre-registration/authentication before application, or provide an approved non-email setup/recovery channel | Registrar/applicant workflow and institutional contact policy | GO WITH CONDITION — approved by David on 2026-08-02; applicant access starts after submission, acceptance remains an independent Registrar decision, historical data remains durable, and released-result access remains separately controlled |
| D-074 | Admission-decision vocabulary | Use `pending`, `needs_correction`, `accepted`, `dismissed`, and `archived`; represent expired/rejected/incomplete legacy meanings as explicit reason/history unless stakeholders approve a distinct state | Keep downstream intake, reports, and migrations blocked because legacy status meanings conflict | Registrar workflow and manuscript terminology | ____ |
| D-075 | Exam deadline, close, and reopen semantics | Use one effective deadline everywhere; extension changes attendance/submission and auto-close windows; manual and automatic close invoke one idempotent command that records close outcome and opens grading exactly once; incomplete close needs reasoned override; reopen is allowed only before grading starts, otherwise use a superseding correction/retake path | Preserve divergent cutoffs/close paths only by explicit policy, with higher reconciliation risk | Proctor/Guidance operations validation | ____ |
| D-076 | Grading state and import semantics | One active grading version per exam; `open → review → finalized`, with score presence as a projection; multi-area manual saves are transactional; imports use one staged durable batch, explicit row states, row-atomic resumability, idempotency, and manual fallback; post-finalization correction creates a new version | Choose whole-file atomic imports or remove `review`, but document failure/recovery consequences | Guidance scoring/import workflow | ____ |
| D-077 | Consultation, release, preview, and publication authority | Counselor/Test Administrator records and confirms the human consultation decision; Guidance prepares/prints result sheets before external release where required; F2F uses printed results and batch consultation scheduling; online releases the approved digital result while consultation remains open through published office availability or applicant reservation; internal preview is distinct and watermarked/audited; corrections use explicit unrelease/new release | Collapse consultation, printing, and release into one uncontrolled role/state only with approved separation-of-duty and privacy rationale | Guidance workflow and online/F2F policy | GO WITH CONDITION — approved by David on 2026-08-02; Guidance owns score-to-print-to-release coordination, F2F is the default consultation mode, online release does not close consultation, and result publication does not erase later consultation evidence |
| D-078 | Document/template and print semantics | Result-sheet generation/printing uses one versioned template authority and records generated/downloaded/distributed/physically-printed evidence; admission slip is omitted from the target answer-sheet identity flow and connected slice; Applicant No/Reference binds answer-sheet receipt/import; result external distribution requires release; large batches are observable jobs | Retain admission slip as a required answer-sheet artifact or retain competing template authorities without a migration plan | Registrar/Guidance document workflow and template inventory | GO WITH CONDITION — approved by David on 2026-08-02; admission slip/QR stapling is removed from the target flow to reduce paper, while legacy admission-slip routes/templates remain historical cleanup items |
| D-079 | RAG access and refusal-to-inquiry handoff | Institutional policy RAG may be available before consultation release; own scores/results/consultation are included only when their authoritative release permits; refusal shows staff fallback and creates a bounded Guidance inquiry only after applicant confirmation; general inter-office messaging remains conditional | Make RAG entirely post-release/policy-only, or defer inquiry threads and use static contact instructions | Manuscript/TAM scope, privacy, and Guidance SLA | ____ |
| D-080 | Program Head/report metric and audit contract | Define program ownership, quota source/version, “matched” meaning, freshness, inactive-program history, and privacy aggregation thresholds; Guidance and Program Head may use approved consultation-facing information; preserve model provenance, program notes, Test Administrator suggestions, and Applicant selections as separate evidence; Program Head remains read-only/program-scoped; every protected view/export is audited and spreadsheet-safe | Defer Program Head and expose reports only to approved Registrar/Guidance roles | Program Head validation and privacy review | GO WITH CONDITION — approved by David on 2026-08-02; Program Head and Guidance may review authorized consultation history and admission-support projections, while counselor suggestions and applicant-selected alternatives remain distinct for later pre-enrolment planning |

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
