# Evidence and Contradiction Register

**Artifact:** NEXIAM-GF-02  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Identify what is known, what conflicts, what remains unverified, and which source may govern each downstream decision.  
**Non-claim:** This register does not prove that planned interviews, validation, technical benchmarks, or institutional approvals occurred.

## 1. Evidence classes

| Class | Meaning | Permitted use |
|---|---|---|
| E1 — Verified implementation evidence | Current route, model, service, policy, migration, test, or configuration inspected in the repository | Establish what the legacy system presently attempts to do and where it is defective |
| E2 — Verified audit synthesis | Audit claim rechecked against source or mechanically verified | Establish risk classes, contradictions, and candidate invariants |
| E3 — Current manuscript/panel commitment | Current research objective, scope, population, evaluation, or panel constraint | Constrain product and research scope, subject to contradiction resolution |
| E4 — Stakeholder evidence | Interview, observation, questionnaire, marked-up artifact, institutional policy, or approved workflow | Establish intended institutional behavior within the evidence’s actual coverage |
| E5 — Engineering constraint | Security, integrity, interoperability, testing, or operational requirement supported by the selected architecture | Establish safe defaults where product policy is not being invented |
| E6 — Proposal | Legacy plan, architecture recommendation, feature idea, or unapproved matrix | Candidate input only; cannot become authority without review |

## 2. Source disposition ledger

| Source | Class | Current disposition | Confidence/use limitation |
|---|---|---|---|
| `app/`, `routes/`, `database/` | E1 | Legacy behavior evidence | Moving dirty worktree; behavior may be accidental or internally inconsistent |
| `docs/master-architecture-picture.md` | E2 | Primary audit synthesis | Strong risk/domain reference; not product authority |
| `docs/architecture-audit.md`, `docs/audit-*.md` | E2/E6 | Supporting audits | Some counts/status claims are stale; use newer corrected findings when conflicts exist |
| `docs/greenfield-architecture-plan.md` | E6 | Greenfield strategy proposal | Useful boundaries/rules, but unresolved gates prevent direct implementation authority |
| `docs/greenfield-implementation-order.md` | E6 | Draft backlog/dependency map | Gate and phase references conflict; must be reconciled before execution |
| `docs/access-control-inventory.md` | E6 | Current-versus-target role proposal | Header states it is not authoritative; also conflicts internally on Proctor |
| `manuscript/NEXIAM_Manuscript_Working_Sections.md` | E3 | Current product/research input | Contains technical and terminology contradictions requiring founder/adviser resolution |
| `manuscript/NEXIAM_Panel_Decision_Log.md` | E3/E6 | Panel/revision input | Contains stale methodology and role wording; binding decisions must be extracted explicitly |
| `manuscript/Phase(RAD-based) Work/features.md` | E6 | Feature inventory | Useful scope inventory; priority and RAG/model wording conflict with manuscript/greenfield plan |
| Requirements-planning interview/checklist files | E4 preparation | Evidence-collection instruments | Questions and planned outputs are not completed evidence |
| Existing response CSVs/notes | E4 | Partial stakeholder evidence | Very small and incomplete coverage; no proof of full Registrar/Applicant/Program Head validation |
| `legacy_codebase/planning/REQUIREMENTS.md` | E6/E1 history | Historical behavior requirements | Narrow legacy release requirements only; cannot serve as NEXIAM PRD |
| `docs/greenfield-foundation/*` | Proposal until approval | Recovery authority candidates | Existence does not imply approval or implementation readiness |

## 3. Evidence closure ledger

| Evidence ID | Required evidence | Current state | Impact if absent | Closure path |
|---|---|---|---|---|
| EV-001 | Current Guidance workflow and exception map | Partial notes/interviews | Guidance authority and counseling states remain assumptions | Consolidate existing evidence; validate one canonical workflow with Guidance |
| EV-002 | Registrar intake, correction, acceptance, and handoff workflow | No completed Registrar interview artifact located | Intake ownership, correction, reopen, and cross-office scope remain unresolved | Conduct/record Registrar validation using existing guide |
| EV-003 | Applicant registration, notification, portal, and recovery experience | No completed Applicant interview artifact located | Authentication/recovery and communication assumptions remain unvalidated | Conduct a small applicant walkthrough/interview set |
| EV-004 | Program Head scope, quota meaning, and program-data visibility | One prior response plus informal feedback; not formalized | Dashboard fields and write authority remain unresolved | Validate with representative Program Heads and document final scope |
| EV-005 | Cluster-to-program mapping | Explicitly pending | Yukee/program mapping cannot be presented as expert-validated | Program Heads mark agree/revise/reject with rationale |
| EV-006 | Official program descriptions/prerequisites | Pending | BGE corpus and program matching lack authoritative source | Collect approved curricula/descriptions with source/version |
| EV-007 | ICAT subtests, score ranges/distributions, answer keys | Pending/partial | OMR, percentile transforms, and model inputs cannot be calibrated | Obtain sanitized institutional artifacts and data dictionary |
| EV-008 | Actual OMR sheet template and representative scans/photos | Not located as validated corpus | OMR implementation and accuracy claims are blocked | Obtain blank/marked sheets under approved handling; create ground truth |
| EV-009 | Yukee model parameters/transforms | Centroid request pending; static rules proposed | Cannot claim faithful K-Means reproduction | Obtain exact parameters or relabel as a deterministic baseline |
| EV-010 | Counselor override/defer patterns | Pending | Triage actions and rationale categories remain speculative | Capture anonymized decision patterns and approved reason taxonomy |
| EV-011 | Campus infrastructure/connectivity assessment | Not written | Offline and deployment claims remain ungrounded | Record network/device/scanner constraints at actual use locations |
| EV-012 | Chatbot question corpus and approved fallback contacts | Inquiry logs not yet requested | RAG relevance/refusal tests lack a real question set | Collect common questions, authoritative answers, owners, update cadence |
| EV-013 | Institutional privacy, consent, retention, and data-sharing rules | Formal PIA/NPC certification excluded; consent assumed | Real-data pilot/deployment remains blocked | Obtain institutional/privacy-owner decisions before real applicant data |
| EV-014 | Technical quality baseline on a clean greenfield scaffold | No greenfield app exists | Existing dirty legacy checks cannot prove greenfield readiness | Establish zero-baseline CI in isolated scaffold after planning approval |
| EV-015 | Canonical intake decision vocabulary and incomplete-document handling | Legacy migrations/controllers use rejected, expired, dismissed, and incomplete_documents inconsistently | Intake states, reports, and migration mapping remain ambiguous | Registrar validates final terms, reasons, and reconsideration path |
| EV-016 | Admission-slip/result-sheet template and physical distribution workflow | Competing system-setting and database-template paths; one printed-state controller appears unrouted | Document authority, placeholder guarantees, and print evidence remain unsettled | Inventory actual approved templates and validate render/download/physical-print workflow |
| EV-017 | Program Head metric definitions and privacy rules | Program Head is manuscript-only; quota source, matched meaning, ownership, and aggregation threshold are absent | Dashboard could disclose or misrepresent applicant data | Program Heads and privacy owner approve metric dictionary/scope |
| EV-018 | Inquiry/messaging and RAG refusal handoff | No thread model/workflow exists | Participants, routing, retention, SLA, confirmation, unread/audit behavior are assumptions | Guidance/Registrar validate bounded inquiry and escalation contract |

## 4. Contradiction register

| Conflict ID | Conflict | Evidence | Recommended winner | Status / linked decision |
|---|---|---|---|---|
| CR-001 | Proctor is both a permanent role and a contextual duty overlay | Live routes/policies require `proctor`; manuscript and greenfield plan describe session assignment | Contextual session duty overlay; no permanent Proctor role | REVIEW REQUIRED — D-ROLE-02 |
| CR-002 | Seven-role catalog omits or swaps Program Head, System IT Admin, and Super Admin across artifacts | Manuscript, revised use case, access inventory, and live roles differ | Seven evaluated roles plus non-evaluated break-glass Super Admin; Proctor remains overlay | REVIEW REQUIRED — D-ROLE-01 |
| CR-003 | Test Administrator appears as a legacy role but target documents imply a permission set/capability | Live `test_administrator` role versus proposed Guidance capabilities | Capability set assignable only within approved Guidance scope | REVIEW REQUIRED — D-ROLE-03 |
| CR-004 | Identifier key may be username, setup token, or bearer credential | Manuscript says unique identifier key; audit requires identifier + credential/recovery; legacy setup token is acceptance-oriented | Applicant No/Reference is a non-secret identifier; post-submission short-lived hashed challenge bootstraps an optional credential; birthday is not sole authentication | RESOLVED WITH CONDITION — D-025/D-073 |
| CR-005 | Applicant is simultaneously application-bound, person identity, and portal credential | Acceptance re-links an account by email; migrations do not enforce durable identity/attempt/account cardinality | Durable ApplicantIdentity plus optional Portal Account and repeatable AdmissionAttempt | REVIEW REQUIRED — D-DOM-01/D-073 |
| CR-006 | Pipeline reversal is promised but the legacy pipeline is forward-only | Unrelease attempts `released → graded`; transition guard rejects backward movement | Explicit release-reversal command preserving immutable history and projection correction | REVIEW REQUIRED — D-WF-03 |
| CR-007 | Reopen is reversible but currently deletes applicant and assessment history | Reopen/bulk reopen cascades through scores, assignments, summaries, and messages | Non-destructive archive/version/new-attempt behavior | REVIEW REQUIRED — D-DATA-02 |
| CR-008 | RAG is policy-only in feature inventory but includes own results/schedules in manuscript | Feature inventory, manuscript, and audit reconciliation differ | Cited institutional corpus plus authorized own-data retrieval; no placement advice | REVIEW REQUIRED — D-RAG-01 |
| CR-009 | Program Head “manages quotas” versus read-only scoped visibility | Revised use case versus feature/audit plan | Read-only program-scoped aggregate plus authorized applicant-record/result-sheet visibility; converted per-domain scores, consultation history, program notes, model provenance, Test Administrator suggestions, and Applicant selections may be viewed within scope; raw answer sheets and confidential conversion tables remain restricted | RESOLVED WITH CONDITION — D-018/D-080 |
| CR-010 | Online/F2F release note and notification rules conflict | Legacy requirements versus ReleaseController behavior | F2F is default: Guidance prints results and publishes/announces batch consultation schedules; online release may publish the approved digital result while consultation remains open through office availability/applicant reservation | RESOLVED WITH CONDITION — D-038/D-044/D-045/D-077 |
| CR-011 | OMR/model claims rely on TAM for technical correctness | Manuscript wording versus engineering validity | TAM measures PU/PEOU only; separate technical benchmark gates | REVIEW REQUIRED — D-RES-01 |
| CR-012 | Yukee is described as K-Means and as a static rule matrix without required centroids | Engine specification and pending data plan | Exact reproducible model or honestly named deterministic baseline | BLOCKED BY EV-009 — D-ENG-01 |
| CR-013 | BGE is described as supervised despite being embedding similarity with no training | Manuscript versus engine specification | Describe actual embedding retrieval/ranking method; no supervised-learning claim | REVIEW REQUIRED — D-ENG-02 |
| CR-014 | Offline “service-worker caching” versus outbox/conflict-controlled synchronization | Manuscript/feature checklist versus greenfield implementation order | Outbox, idempotent replay, conflict detection; otherwise remove offline claim | REVIEW REQUIRED — D-OFF-01 |
| CR-015 | Production DB is MariaDB in architecture but MySQL 8.4 in Compose; SQLite is default/test | Architecture, config, and Compose disagree | Pin one production engine and rehearse it; use SQLite only where parity is proven | REVIEW REQUIRED — D-TECH-01 |
| CR-016 | Pest versus PHPUnit | Greenfield plan specifies Pest; project authority/README specifies PHPUnit | PHPUnit 11 unless the project convention is explicitly changed | REVIEW REQUIRED — D-TECH-02 |
| CR-017 | Node 24 CI versus Node 20 Docker | CI and Docker disagree | Pin one supported runtime after compatibility validation | REVIEW REQUIRED — D-TECH-03 |
| CR-018 | Python shared sidecar is planned but absent; Yukee/BGE execution locations differ | Greenfield, engine specs, Docker/Compose | One controlled inference worker with fixed adapters; defer unavailable engines | REVIEW REQUIRED — D-TECH-04 |
| CR-019 | Queue side effects require after-commit but current config is false | Greenfield rule versus legacy config | State/audit in transaction; async effects only after commit and idempotent | PROPOSED DEFAULT — D-OPS-01 |
| CR-020 | Audit is called HMAC/tamper-proof without closed threat model | Manuscript versus audit findings | “Tamper-evident under defined threat model,” with key custody/checkpoints/verification | REVIEW REQUIRED — D-AUD-01 |
| CR-021 | Greenfield roadmap says sequential while also naming parallel phases; gate numbers/phase links disagree | Greenfield implementation document internal references | Replace numeric ambiguity with stable gate IDs and dependency graph | REQUIRED REPAIR — D-PLAN-01 |
| CR-022 | Existing README calls greenfield plan execution authority while recovery suite says it is draft strategy | Root README versus recovery charter | Recovery charter governs only after founder approval; then README must be updated | REVIEW REQUIRED — D-AUTHORITY-01 |
| CR-023 | Public intake creates an Application without account, while acceptance provisions/relinks a globally email-unique Applicant account | Intake/auth controllers and applicant schema | Durable ApplicantIdentity plus cycle attempt; Applicant No/Reference and one-time access challenge are issued after submission; optional Portal Account can be established then; acceptance remains an independent Registrar decision | RESOLVED WITH CONDITION — D-024/D-073 |
| CR-024 | Public email can be replaced by a fabricated fallback while setup/recovery depends on email | Intake fallback versus portal setup/reset | Require a real approved delivery/recovery channel before issuing the post-submission challenge; never fabricate an address; provide bounded staff-assisted recovery without using birthday alone | RESOLVED WITH CONDITION — D-025/D-073 |
| CR-025 | Admission status has drifted across rejected, expired, dismissed, and incomplete_documents | Legacy migrations and current controller behavior | Approve one decision vocabulary plus historical reason mapping | BLOCKED BY EV-015 — D-074 |
| CR-026 | Legacy exam scheduling is Registrar-owned while the proposed target makes Guidance the workflow owner | ExamSession request/policy versus manuscript/role proposal | Decide from Registrar/Guidance process validation; do not infer owner from legacy middleware | BLOCKED BY EV-001/EV-002 — D-038 |
| CR-027 | Extension changes auto-close time but attendance/submission still use original end time; manual/auto close produce different grading/notification effects | Proctor roster and auto-close paths | One effective deadline and one idempotent close orchestration opening grading exactly once | REVIEW REQUIRED — D-075 |
| CR-028 | Grading schema defines open/in_progress/review/finalized, but review is unreachable; multi-area saves can partially commit and imports lack durable batch identity | Grading controllers/services/importer | One explicit review/finalization model plus transactional manual save and staged idempotent import batch | REVIEW REQUIRED — D-076 |
| CR-029 | Active consultation/release authorization bypasses the counselor-oriented request; internal printing can expose unfinalized/unreleased recommendation data | Release routes/controller and result template service | Counselor-owned decision, approved Guidance release capability, separate internal preview and external publication gates | BLOCKED BY EV-001/EV-016 — D-077 |
| CR-030 | Admission-slip templates have competing system-setting/database authorities and printed-state mutation appears orphaned | Admission slip services/controller/routes | Admission slip/QR stapling is removed from the target answer-sheet identity flow and connected slice; Applicant No/Reference is the binding key; legacy routes/templates remain cleanup evidence only | RESOLVED WITH CONDITION — D-027/D-078 |
| CR-031 | RAG target is policy-only versus own-data context, and the current UI implies post-release access without controller enforcement | Manuscript, architecture, AI service/settings | Policy corpus may be pre-release; own result/consultation data follows release authorization; refusal handoff is explicit | REVIEW REQUIRED — D-008/D-051/D-079 |
| CR-032 | Program Head is not built and metric semantics are undefined; current reports are broad Registrar/Guidance exports without export audit events | Report service/routes and manuscript | Read-only program projection plus authorized consultation/history/admission-support views only after metric/privacy dictionary; audit protected report views/exports | BLOCKED BY EV-017 — D-018/D-080 |
| CR-033 | Messaging/RAG-refusal handoff is promised but no thread model, routing, retention, unread, SLA, or audit workflow exists | Manuscript/architecture versus live routes/models | Keep bounded inquiry conditional; require applicant confirmation before creating a Guidance thread | BLOCKED BY EV-018 — D-079 |

## 5. Mechanically established current-state facts

- The legacy worktree is dirty and cannot be treated as a frozen behavioral baseline.
- Frontend checks and lint currently fail; the Vitest command finds no tests.
- Existing backend test files are examples, not meaningful domain protection.
- Host PHP/Composer and permitted Docker execution were unavailable to the delegated planning review, so PHP tests, migrations, Composer audit, and production-engine rehearsal are unproven in this evidence package.
- Existing application behavior contains useful transactions and recovery patterns, but core workflows remain fragmented across controllers/services/models.
- OMR, engine registry, Program Head dashboard, triage, messaging, offline sync, and cryptographic audit chaining are absent or incomplete in the legacy implementation.

## 6. Closure rule

A contradiction closes only when:

1. the relevant evidence is present or an explicit constrained assumption is approved;
2. the founder decision identifies the winning rule and rationale;
3. affected downstream artifacts are updated;
4. obsolete authorities are marked historical/superseded;
5. an acceptance scenario and future test target prove the winning behavior.
