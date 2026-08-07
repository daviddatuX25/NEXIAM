02-evidence-and-contradiction-register.md 115L
# Evidence and Contradiction Register
**Artifact:** NEXIAM-GF-02
... [lean-ctx: omitted 1 lines]
**Purpose:** Identify what is known, what conflicts, what remains unverified, and which source may govern each downstream decision.
**Non-claim:** This register does not prove that planned interviews, validation, technical benchmarks, or institutional approvals occurred.
## 1. Evidence classes
| Class | Meaning | Permitted use |
... [lean-ctx: omitted 3 lines]
| E3 — Current manuscript/panel commitment | Current research objective, scope, population, evaluation, or panel constraint | Constrain product and research scope, subject to contradiction resolution |
| E4 — Stakeholder evidence | Interview, observation, questionnaire, marked-up artifact, institutional policy, or approved workflow | Establish intended institutional behavior within the evidence’s actual coverage |
| E5 — Engineering constraint | Security, integrity, interoperability, testing, or operational requirement supported by the selected architecture | Establish safe defaults where product policy is not being invented |
... [lean-ctx: omitted 1 lines]
## 2. Source disposition ledger
| Source | Class | Current disposition | Confidence/use limitation |
... [lean-ctx: omitted 1 lines]
| `app/`, `routes/`, `database/` | E1 | Legacy behavior evidence | Moving dirty worktree; behavior may be accidental or internally inconsistent |
... [lean-ctx: omitted 1 lines]
| `docs/architecture-audit.md`, `docs/audit-*.md` | E2/E6 | Supporting audits | Some counts/status claims are stale; use newer corrected findings when conflicts exist |
... [lean-ctx: omitted 3 lines]
| `manuscript/NEXIAM_Manuscript_Working_Sections.md` | E3 | Current product/research input | Contains technical and terminology contradictions requiring founder/adviser resolution |
| `manuscript/NEXIAM_Panel_Decision_Log.md` | E3/E6 | Panel/revision input | Contains stale methodology and role wording; binding decisions must be extracted explicitly |
| `manuscript/Phase(RAD-based) Work/features.md` | E6 | Feature inventory | Useful scope inventory; priority and RAG/model wording conflict with manuscript/greenfield plan |
... [lean-ctx: omitted 3 lines]
| `docs/greenfield-foundation/*` | Proposal until approval | Recovery authority candidates | Existence does not imply approval or implementation readiness |
## 3. Evidence closure ledger
| Evidence ID | Required evidence | Current state | Impact if absent | Closure path |
... [lean-ctx: omitted 2 lines]
| EV-002 | Registrar intake, correction, acceptance, and handoff workflow | No completed Registrar interview artifact located | Intake ownership, correction, reopen, and cross-office scope remain unresolved | Conduct/record Registrar validation using existing guide |
| EV-003 | Applicant registration, notification, portal, and recovery experience | No completed Applicant interview artifact located | Authentication/recovery and communication assumptions remain unvalidated | Conduct a small applicant walkthrough/interview set |
| EV-004 | Program Head scope, quota meaning, and program-data visibility | One prior response plus informal feedback; not formalized | Dashboard fields and write authority remain unresolved | Validate with representative Program Heads and document final scope |
... [lean-ctx: omitted 8 lines]
| EV-013 | Institutional privacy, consent, retention, and data-sharing rules | Formal PIA/NPC certification excluded; consent assumed | Real-data pilot/deployment remains blocked | Obtain institutional/privacy-owner decisions before real applicant data |
... [lean-ctx: omitted 1 lines]
| EV-015 | Canonical intake decision vocabulary and incomplete-document handling | Legacy migrations/controllers use rejected, expired, dismissed, and incomplete_documents inconsistently | Intake states, reports, and migration mapping remain ambiguous | Registrar validates final terms, reasons, and reconsideration path |
| EV-016 | Admission-slip/result-sheet template and physical distribution workflow | Competing system-setting and database-template paths; one printed-state controller appears unrouted | Document authority, placeholder guarantees, and print evidence remain unsettled | Inventory actual approved templates and validate render/download/physical-print workflow |
... [lean-ctx: omitted 1 lines]
| EV-018 | Inquiry/messaging and RAG refusal handoff | No thread model/workflow exists | Participants, routing, retention, SLA, confirmation, unread/audit behavior are assumptions | Guidance/Registrar validate bounded inquiry and escalation contract |
## 4. Contradiction register
| Conflict ID | Conflict | Evidence | Recommended winner | Status / linked decision |
... [lean-ctx: omitted 2 lines]
| CR-002 | Seven-role catalog carries the stale “System IT Admin” label and omits or swaps Program Head/Super Admin across artifacts | Manuscript, revised use case, access inventory, and live roles differ | Canonical evaluated actors plus HR/Staffing and a non-routine break-glass Super Admin; Proctor remains an overlay; no generic System IT admission authority | RESOLVED WITH CONDITION — D-014/D-017–D-020/D-023; role labels and capabilities must be propagated through GF-04, GF-17, GF-21, and the UI route contract |
... [lean-ctx: omitted 1 lines]
| CR-004 | Identifier key may be username, setup token, or bearer [REDACTED:Bearer token] | Manuscript says unique identifier key; audit requires identifier + credential/recovery; legacy setup token is acceptance-oriented | Applicant No/Reference is a non-secret identifier; post-submission short-lived hashed challenge bootstraps an optional credential; birthday is not sole authentication | RESOLVED WITH CONDITION — D-025/D-073 |
| CR-005 | Applicant is simultaneously application-bound, person identity, and portal credential | Acceptance re-links an account by email; migrations do not enforce durable identity/attempt/account cardinality | Durable ApplicantIdentity plus optional Portal Account and repeatable AdmissionAttempt | RESOLVED WITH CONDITION — D-024/D-025/D-073; activation remains independent of acceptance and every portal view is own-data scoped |
| CR-006 | Pipeline reversal is promised but the legacy pipeline is forward-only | Unrelease attempts `released → graded`; transition guard rejects backward movement | Explicit release-reversal command preserving immutable history and projection correction | RESOLVED WITH CONDITION — D-032/D-077; reversal is a new event/version, never deletion or silent backward mutation |
... [lean-ctx: omitted 2 lines]
| CR-009 | Program Head “manages quotas” versus read-only scoped visibility | Revised use case versus feature/audit plan | **CLOSED D-087 (2026-08-06):** Program Head **PROGRAM-scoped quota write**; Guidance Counselor (TA) may also set quotas with audit + PH communication seam; pipeline/pre-enrolment views remain program-scoped; raw sheets/conversion tables still restricted | CLOSED — D-087 (supersedes prior D-018/D-080 read-only quota assumption) |
... [lean-ctx: omitted 1 lines]
| CR-011 | OMR/model claims rely on TAM for technical correctness | Manuscript wording versus engineering validity | TAM measures PU/PEOU only; separate technical benchmark gates and human-review boundaries govern OMR/model claims | RESOLVED WITH CONDITION — D-007/D-046–D-050; EV-008/EV-009 and technical validation remain open |
... [lean-ctx: omitted 2 lines]
| CR-014 | Offline “service-worker caching” versus outbox/conflict-controlled synchronization | Manuscript/feature checklist versus greenfield implementation order | Durable outbox, authenticated ordered replay, idempotency, conflict detection, and manual resolution | RESOLVED WITH CONDITION — D-053; EV-011 remains the promotion gate and cache-only language is superseded |
... [lean-ctx: omitted 8 lines]
| CR-023 | Public intake creates an Application without account, while acceptance provisions/relinks a globally email-unique Applicant account | Intake/auth controllers and applicant schema | Durable ApplicantIdentity plus cycle attempt; Applicant No/Reference and one-time access challenge are issued after submission; optional Portal Account can be established then; acceptance remains an independent Registrar decision | RESOLVED WITH CONDITION — D-024/D-073 |
... [lean-ctx: omitted 2 lines]
| CR-026 | Legacy exam scheduling is Registrar-owned while the proposed target makes Guidance the workflow owner | ExamSession request/policy versus manuscript/role proposal | Guidance/Test Administrator owns reservation, manual assignment, publication, timing, close, and consultation availability; Registrar receives only approved visibility/input | RESOLVED WITH CONDITION — D-038/D-075; EV-001/EV-002 still validate concrete institutional workflow details |
... [lean-ctx: omitted 2 lines]
| CR-029 | Active consultation/release authorization bypasses the counselor-oriented request; internal printing can expose unfinalized/unreleased recommendation data | Release routes/controller and result template service | Counselor-owned decision, approved Guidance release capability, separate internal preview and external publication gates | RESOLVED WITH CONDITION — D-077; EV-016 still validates template/print evidence |
| CR-030 | Admission-slip templates have competing system-setting/database authorities and printed-state mutation appears orphaned | Admission slip services/controller/routes | Admission slip/QR stapling is removed from the target answer-sheet identity flow and connected slice; Applicant No/Reference is the binding key; legacy routes/templates remain cleanup evidence only | RESOLVED WITH CONDITION — D-027/D-078 |
| CR-031 | Stale “policy-only” RAG target conflicts with own-data context, and the current UI implies post-release access without controller enforcement | Manuscript, architecture, AI service/settings | Policy corpus may be pre-release; own result/consultation data follows release authorization; typed task assistance uses ordinary capability/scope gates; refusal handoff is explicit | RESOLVED WITH CONDITION — D-008/D-051/D-079; no UI or model prompt can authorize data |
... [lean-ctx: omitted 1 lines]
| CR-033 | Messaging/RAG-refusal handoff is promised but no thread model, routing, retention, unread, SLA, or audit workflow exists | Manuscript/architecture versus live routes/models | RAG refusal offers a bounded applicant-confirmed inquiry handoff; general inter-office messaging remains conditional until its thread/routing/SLA contract is validated | RESOLVED WITH CONDITION — D-079; EV-018 remains open for the durable thread model and operational SLA |
## 5. Mechanically established current-state facts
- The legacy worktree is dirty and cannot be treated as a frozen behavioral baseline.
... [lean-ctx: omitted 2 lines]
- Host PHP/Composer and permitted Docker execution were unavailable to the delegated planning review, so PHP tests, migrations, Composer audit, and production-engine rehearsal are unproven in this evidence package.
- Existing application behavior contains useful transactions and recovery patterns, but core workflows remain fragmented across controllers/services/models.
- OMR, engine registry, Program Head dashboard, triage, messaging, offline sync, and cryptographic audit chaining are absent or incomplete in the legacy implementation.
## 6. Closure rule
A contradiction closes only when:
1. the relevant evidence is present or an explicit constrained assumption is approved;
... [lean-ctx: omitted 1 lines]
3. affected downstream artifacts are updated;
4. obsolete authorities are marked historical/superseded;
... [lean-ctx: omitted 1 lines]


[lean-ctx] full source: read "/home/user/Projects/NEXIAM/docs/greenfield-foundation/02-evidence-and-contradiction-register.md" directly (no MCP)  ·  or ctx_read("/home/user/Projects/NEXIAM/docs/greenfield-foundation/02-evidence-and-contradiction-register.md", mode="full")
