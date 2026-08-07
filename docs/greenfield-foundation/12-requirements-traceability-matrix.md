# Requirements Traceability Matrix

**Artifact:** NEXIAM-GF-12  
**Status:** DRAFT — REVIEW REQUIRED  
**Coverage claim:** Candidate requirements for planning recovery and the first connected slice. Conditional/backlog rows are explicitly gated; this is not a claim that every legacy feature is covered.  
**Required chain:** Evidence → requirement → founder decision → workflow/domain contract → acceptance scenario → future test/runtime evidence.

## 1. Status vocabulary

- **PROPOSED:** Candidate behavior whose founder outcome may be recorded but whose contract/promotion/evidence is not yet complete.
- **PROMOTED (planning):** Bound into [`../prd.md`](../prd.md) for story authorship; executable proof may still be open.
- **EVIDENCE HOLD:** Product behavior needs stakeholder/institutional artifact.
- **SPIKE HOLD:** Technical commitment needs measurable feasibility proof.
- **CONDITIONAL:** Required only if retained in approved capstone scope.
- **DEFERRED:** Not part of the first connected slice.
- **APPROVED:** May be used only after founder decision propagation.

## 2. Authority, scope, and evidence requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-GOV-001 | One approved authority hierarchy governs downstream work | Competing README/greenfield/foundation authority; GF-01/GF-02 | D-001, G-AUTHORITY | GF-01 review protocol | Promotion record + consistency audit | PROPOSED |
| REQ-GOV-002 | Legacy code and plans remain evidence, not target truth | Greenfield pivot and audit findings | D-002–D-005 | GF-01 §§3–9 | Historical/supersession labels | PROPOSED |
| REQ-GOV-003 | Committed, conditional, deferred, and excluded scope are explicit | Manuscript versus checklist/roadmap conflict | D-006–D-013, G-SCOPE | GF-03 §4 | Approved manuscript propagation | PROPOSED |
| REQ-GOV-004 | Pending stakeholder evidence remains visible and cannot be fabricated | Data-gathering plan/interview/checklist gaps | EV-001–EV-018 | GF-02 §3 | Indexed evidence artifacts | EVIDENCE HOLD |
| REQ-GOV-005 | Planning, controlled demo, and pilot/production verdicts remain separate | Current readiness overclaim risk | D-013, D-072 | GF-03 §6; GF-14 | Separate signed verdicts | PROPOSED |

## 3. Identity, actor, and authorization requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-AUTH-001 | Final base-role catalog, support-staff inheritance, and role ceilings are authoritative | Three divergent catalogs; access inventory not authoritative | D-014, D-017, D-020, G-ROLE | GF-04 actor/capability matrix; grouped role catalog | Role-grant feature/policy tests | PROPOSED |
| REQ-AUTH-002 | Proctor is a revocable ExamSession duty, not a permanent role | Legacy route/policy conflict; manuscript overlay | D-015, D-022 | GF-04 CAP-012–015; SCN-VS-006 | Assignment/revocation/cross-session tests | PROPOSED |
| REQ-AUTH-003 | Test Administration is an approved capability set | Legacy `test_administrator` versus target Guidance roles | D-016 | GF-04 CAP-016–019 | Capability grant/deny tests | PROPOSED |
| REQ-AUTH-004 | Applicant No/Reference is non-secret, assigned at submission, and requires approved post-submission credential/recovery | Manuscript identifier ambiguity; plaintext legacy tokens; acceptance-triggered setup | D-025, D-027, D-073, G-IDENTITY | GF-08 §3; GF-06 §§3–4; SCN-VS-003 | Expiry/reuse/race/enumeration/own-data tests | PROPOSED |
| REQ-AUTH-005 | Applicant access is own-data only | Legacy separate guard/policy intent | D-024–D-027 | GF-04 §4; SCN-VS-001/014 | Cross-applicant policy/browser tests | PROPOSED |
| REQ-AUTH-006 | Staff access is capability-, office-, program-, and resource-scoped | Legacy globally broad application access | D-018–D-023, G-ROLE | GF-04 matrix/data-scope rules | Full allow/deny/cross-scope suite | EVIDENCE HOLD |
| REQ-AUTH-007 | System IT has technical governance, not routine admission authority | Role separation/security principle | D-019 | GF-04 CAP-029–035 | Denied operational decision tests | PROPOSED |
| REQ-AUTH-008 | Any break-glass access is time-bound, reasoned, alerted, and audited | Super Admin ambiguity | D-017 | GF-08 §§3–4; GF-19 §§3.1/5/9; GF-20 §4; GF-21 §§3–8; GF-22 §3.4 | Activation/end, capability-allowlist, expiry/revocation, audit-view, and post-use review tests | PROPOSED |

## 4. Admission intake and identity-lifetime requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-ADM-001 | Durable ApplicantIdentity owns repeatable cycle-scoped AdmissionAttempts; a post-submission access challenge may establish an optional Portal Account that authenticates that identity independently of acceptance | Legacy relinking/nonunique one-to-one and public pre-account intake | D-024/D-073, G-IDENTITY/G-DATA | GF-05 INV-001; SCN-VS-001/003 | Schema cardinality, challenge/activation, acceptance-independence, and cycle tests | PROPOSED |
| REQ-ADM-002 | Admission reference generation is collision-safe under concurrency | `COUNT + 1` race and unique column | D-027 | GF-05 INV-003 | Concurrent create test | PROPOSED |
| REQ-ADM-003 | Duplicate identity rule is normalized, scoped, DB-enforced, and recoverable | Read-then-insert legacy check | D-027 | GF-05 INV-002; SCN-VS-002 | Unique constraint/race/merge-path tests | EVIDENCE HOLD |
| REQ-ADM-004 | Submission validates active cycle/window and required data | Legacy AcademicYear/application behavior | D-036/D-037 | GF-06 intake workflow; SCN-VS-001 | Feature/validation tests | PROPOSED |
| REQ-ADM-005 | Acceptance/dismissal is command-owned, scoped, transactional, and audited | Fragmented/bulk-divergent legacy paths | D-036/D-037 | GF-11 steps 1–3; SCN-VS-003/004 | Command/policy/transaction tests | EVIDENCE HOLD |
| REQ-ADM-006 | Post-submission setup/access delivery occurs only after ApplicantIdentity/AdmissionAttempt state commits; it is independent of later acceptance and uses an approved real channel | Queue `after_commit=false` risk and acceptance-gated activation contradiction | D-025, D-060, D-073 | GF-07/GF-09; SCN-VS-003 | After-commit, acceptance-independence, approved-channel, and duplicate-delivery tests | PROPOSED |
| REQ-ADM-007 | Reopen/archive never deletes identity or assessment history | Legacy destructive reopen cascades | D-029/D-034 | GF-05 INV-016; SCN-VS-013 | Reopen/history retention tests | PROPOSED |
| REQ-ADM-008 | Appointment capacity cannot overbook under concurrent intake | Non-atomic booked counter | D-027/D-028 | GF-05 INV-005 | Database race test | PROPOSED |
| REQ-ADM-009 | Public and staff intake converge on one command; account activation requires a real approved delivery/recovery channel and never fabricates email | Legacy public/admin divergence and email fallback | D-025/D-073 | GF-06 §3; REQ-VS-019 | Public/staff parity, activation, delivery-failure tests | PROPOSED |
| REQ-ADM-010 | Admission decision uses one approved vocabulary and historical reason mapping | rejected/expired/dismissed/incomplete_documents drift | D-074, EV-015 | GF-06 §3 | Transition/report/migration mapping tests | EVIDENCE HOLD |

## 5. Examination administration requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-EXAM-001 | Session creation validates cycle, room, date/time, capacity, and ownership | Legacy ExamSchedulingService intent | D-038 | GF-05 ExamSession; GF-06 | Command/validation tests | EVIDENCE HOLD |
| REQ-EXAM-002 | Room/session conflicts and capacity are safe under concurrency | Application-only conflict check | D-038, G-WORKFLOW/G-DATA | GF-05 INV-006; SCN-VS-005 | Concurrent scheduling tests | PROPOSED |
| REQ-EXAM-003 | Attempt assignment validates acceptance, cycle/mode, duplicate/retake rule, and active conflicts | Legacy eligibility/global uniqueness conflict | D-030/D-038 | GF-05 INV-007; SCN-VS-005 | Constraint/policy/race tests | EVIDENCE HOLD |
| REQ-EXAM-004 | Publish requires complete non-past session configuration and at least one assignment | Legacy publish invariant | D-038 | GF-05 INV-008; GF-06 | Transition tests | PROPOSED |
| REQ-EXAM-005 | Start/override follows approved time window and reasoned authority | Legacy schedule window and broad override | D-039 | GF-06; GF-08 audit | Time-bound/override tests | EVIDENCE HOLD |
| REQ-EXAM-006 | Attendance and submission are separate idempotent commands for assigned attempts in active sessions | Legacy proctor controller checks | D-041 | GF-05 INV-009; SCN-VS-007 | Replay/concurrency/time-window tests | PROPOSED |
| REQ-EXAM-007 | Manual and automatic close share one locked, idempotent, observable command that handles no-attendance/unresolved records and opens grading once | Divergent legacy controller/auto-closer effects | D-040/D-075 | GF-06/GF-07; SCN-VS-017 | Race/retry/reconciliation/grading-open tests | EVIDENCE HOLD |
| REQ-EXAM-008 | Cancellation, unpublish, reopen, and reassignment preserve history and require reason where approved | Fragmented legacy transitions | D-029/D-030/D-038–D-041 | GF-06 exception transitions | Reversal/history tests | PROPOSED |
| REQ-EXAM-009 | Direct assessment is a separate mode sharing scoring/release invariants and cannot score zero/partial required data | Legacy direct-assessment reconciliation bug | D-042 | GF-06/GF-05 INV-010 | Mode/finalization tests | CONDITIONAL |
| REQ-EXAM-010 | One effective deadline, including extensions, governs attendance, submission, close, and scheduler behavior; reopen is bounded by downstream grading state | Original-end/effective-end and reopen contradictions | D-075 | GF-06 §5; SCN-VS-017 | Time-boundary/extension/reopen tests | PROPOSED |

## 6. Scoring, grading, counseling, and release requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-GRD-001 | One active grading workflow exists per approved assessment unit | `hasOne` without DB uniqueness | D-031 | GF-05 INV-011 | Unique/version constraint tests | PROPOSED |
| REQ-GRD-002 | Manual score entry is a complete provider-independent path | Capstone reliability requirement | D-006/D-007 | SCN-VS-008 | End-to-end no-worker test | PROPOSED |
| REQ-GRD-003 | Score writes are idempotent, typed, source-attributed, and scope-checked | Legacy upsert behavior and import risk | D-043 | GF-05 ScoreRecord; GF-07 | Duplicate import/edit policy tests | PROPOSED |
| REQ-GRD-004 | Finalization requires all required valid scores for every assigned attempt | Legacy GradingSessionService completeness rule | D-031/D-043 | GF-05 INV-010; SCN-VS-010 | Completeness and invalid-score tests | PROPOSED |
| REQ-GRD-005 | Post-finalization correction uses explicit reopen/version/reason and audit | Silent overwrite/reopen ambiguity | D-043 | GF-05 INV-012 | Version/history/authorization tests | EVIDENCE HOLD |
| REQ-GRD-006 | Grading has one active version with explicit open/review/finalized semantics; manual multi-area writes are transactional and imports are staged, durable, row-explicit, resumable, idempotent, and manually recoverable | Unreachable review state, partial manual save, divergent import paths | D-076 | GF-06 §6; GF-07; SCN-VS-018 | State/transaction/import replay/manual-fallback tests | PROPOSED |
| REQ-REL-001 | Counseling decision remains human-owned and supports approve/modify/override/defer | Manuscript human-in-loop intent | D-010/D-044 | GF-05 INV-013; SCN-VS-011 | Decision/provenance/policy tests | EVIDENCE HOLD |
| REQ-REL-002 | Release requires finalized scores, result preparation/printing where required, and approved mode-specific consultation/release prerequisites; online consultation may remain open after release | Legacy online/F2F conflict | D-044, D-077 | GF-05 INV-014; GF-06 §§8–9; SCN-VS-012 | F2F/online mode, print-before-release, reservation, and prerequisite tests | EVIDENCE HOLD |
| REQ-REL-003 | Release commits state, audit, and artifact metadata atomically; delivery is after commit | Fragmented release transaction | D-045/D-060 | SCN-VS-012 | Transaction/queue tests | PROPOSED |
| REQ-REL-004 | Unrelease/reversal preserves prior release event/document and changes current visibility explicitly | Forward-only pipeline contradiction | D-032 | GF-05 INV-015; SCN-VS-013 | Reversal/history tests | PROPOSED |
| REQ-REL-005 | Applicant sees only currently approved released artifacts | Portal own-data and release intent | D-025/D-032 | GF-04 CAP-022/023 | Portal visibility tests | PROPOSED |
| REQ-REL-006 | Counselor authors the human decision; authorized Test Administrator may record consultation suggestions/Applicant selections; approved Guidance capability releases after prerequisites; internal preview/printing and external publication are distinct; correction preserves release history | Active route/request divergence and pre-release print exposure | D-077 | GF-06 §§8/11; SCN-VS-019 | Capability, suggestion/selection separation, preview watermark, publication denial, reversal tests | EVIDENCE HOLD |

## 7. Document, notification, and integration requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-DOC-001 | Templates/placeholders are typed and context-encoded | Audit XSS/DOCX/export findings | D-062 | GF-08 §6; SCN-VS-014 | Malicious-value fixtures/CSP tests | PROPOSED |
| REQ-DOC-002 | CSV/XLSX outputs neutralize formulas/external-link risk | Audit export finding | D-062 | GF-08 §6 | Spreadsheet security fixtures | PROPOSED |
| REQ-DOC-003 | Generated artifacts retain owner, template/version, checksum, private storage reference, and lifecycle class | Document provenance/retention need | D-033–D-035/D-062 | GF-05 GeneratedDocument | Storage/access/retention tests | PROPOSED |
| REQ-DOC-004 | One versioned result-template authority exists; Applicant No/Reference binds answer-sheet identity; generated, distributed/downloaded, and physically printed result evidence are distinct; admission slip is not required in the target flow; large batches are observable jobs | Competing admission-slip authorities and orphaned print mutation | D-078, EV-016 | GF-05/GF-06; SCN-VS-019 | Applicant-No binding, result-template, print-evidence, retry, batch-job tests | EVIDENCE HOLD |
| REQ-COMM-001 | Notifications are after-commit, idempotent, observable, retryable, and have terminal failure | Legacy swallowed/partial notifications | D-045/D-060/D-067 | GF-07/GF-09 §§7/9.1; GF-20 §7; SCN-VS-012 | Queue/retry/dead-letter/ownership tests | PROPOSED |
| REQ-COMM-002 | Provider outage does not block the manual authoritative core journey | External dependency risk | D-067 | SCN-VS-008 | Degraded-mode E2E tests | PROPOSED |
| REQ-COMM-003 | Applicant notification list/count/read state is a connected own-data projection; event preferences, idempotency, and terminal failures are explicit | Existing applicant API exists but portal props/page are disconnected | D-045/D-060/D-067 | GF-07 FAIL-030; SCN-VS-020 | Prop/API/ownership/read/rebuild/retry tests | PROPOSED |
| REQ-INT-001 | Every external operation uses typed schemas, timeout, idempotency, provenance, health, and retry/failure contracts | Audit/greenfield adapter plan | D-060–D-067 | GF-07/GF-09 §§4/9.1; GF-20 §§7–10 | Contract/integration/health/ownership tests | PROPOSED |

## 8. Security, privacy, and audit requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-SEC-001 | Protected commands enforce backend policy/resource scope and do not rely on UI hiding or LLM prompts | Legacy route/policy mismatch; RAG risk | D-014–D-023 | GF-08 §§3–7; GF-21 §§1–6; GF-22 §12 | Policy/browser/negative tests | PROPOSED |
| REQ-SEC-002 | Upload/import paths enforce content type, size/decompression/time, private storage, path/URL restrictions, and idempotency | Spreadsheet/SSRF/upload audit risk | D-062 | GF-08 §6 | Malformed/oversized/SSRF fixtures | PROPOSED |
| REQ-PRIV-001 | Real data requires approved purpose, roles/scopes, provider map, retention, correction/restriction/export/deletion, backup/destruction | Manuscript excludes formal PIA/NPC certification; evidence pending | D-034/D-035/D-072 | GF-08 §5; GF-19 §§4/9; GF-20 §§7/9; GF-21 §§5.4/7; GF-22 §§8/12 | Approved lifecycle/hold matrix, provider map, purge/hold, backup/destruction, scope, redaction, and operations evidence | EVIDENCE HOLD |
| REQ-AUD-001 | Critical event append and state mutation are mandatory and atomic under approved command boundary | Best-effort/partial legacy audit risk | D-052/D-060 | GF-08 §8 | Command failure/atomicity tests | PROPOSED |
| REQ-AUD-002 | Cryptographic claim is limited to tamper evidence under a declared threat model | Manuscript HMAC overclaim/audit gap | D-052 | GF-08 §8 | Threat model and tamper suite | SPIKE HOLD |
| REQ-AUD-003 | Canonical sequence/hash/key version/checkpoint verification detects covered tampering and concurrency is serialized | Audit architecture requirement | D-052 | SCN-VS-015 | Concurrent/tamper/key/checkpoint tests | SPIKE HOLD |

## 9. Capstone differentiator requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-OMR-001 | OMR accepts only approved template/input versions and rejects malformed/wrong inputs | Missing operational input gate | D-046, G-OMR | GF-10 §3 | Ground-truth corpus/input tests | SPIKE HOLD |
| REQ-OMR-002 | OMR observations include confidence/provenance and uncertainty enters human review | Capstone safety requirement | D-047 | SCN-VS-009 | Ambiguity/false-auto-accept metrics | SPIKE HOLD |
| REQ-OMR-003 | Reviewed OMR import is idempotent and uses the same grading invariants as manual scores | Worker must not own authoritative scores | D-047/D-061 | GF-05 ownership; GF-09 envelope | Replay/import/finalization tests | SPIKE HOLD |
| REQ-RAG-001 | RAG scope is approved corpus plus approved own data, with citations/refusal and no placement advice | Manuscript/feature contradiction | D-008/D-051, G-RAG | GF-08 §7; GF-10 §4 | Curated query/auth/injection/outage suite | CONDITIONAL |
| REQ-RAG-002 | Policy-corpus and own released-data access use separate gates; failure/refusal provides bounded fallback and creates an inquiry only after applicant confirmation | UI/controller release-gate mismatch and unbuilt messaging handoff | D-079, EV-018 | GF-06 §10; GF-07 FAIL-033; SCN-VS-021 | Pre/post-release auth, refusal, confirmation/idempotency tests | CONDITIONAL |
| REQ-ENG-001 | Aptitude engine is exact reproducible adoption or honestly labeled deterministic baseline | Yukee centroid/rule contradiction | D-048, G-ENGINE | GF-10 §5 | Transform/fixture reproduction | SPIKE HOLD |
| REQ-ENG-002 | Program mapping is versioned expert-approved reference data, not model output | Pending Program Head mapping | D-049, EV-005/006 | GF-10 §5 | Signed/versioned mapping evidence | EVIDENCE HOLD |
| REQ-ENG-003 | BGE, if retained, is embedding similarity using approved genuine interest input/corpus, not supervised prediction | Manuscript/engine terminology conflict | D-050 | GF-10 §6 | Relevance/repeatability/provenance tests | CONDITIONAL |
| REQ-SCHED-001 | Scheduling assistance exposes hard constraints, feasible/infeasible result, human confirmation, and manual fallback | AI-assisted scheduling promise lacks contract | D-009 | GF-10 §7 | Constraint fixtures/comparison/outage tests | CONDITIONAL |
| REQ-OFF-001 | Offline proctoring requires encrypted device/session scope, durable outbox, ordered idempotent replay, conflict detection/manual resolution, and visible degraded state; no cache-only claim | Manuscript/greenfield conflict | D-053, G-OFFLINE | GF-07/GF-10; GF-22 §9.4 | Disconnect/reconnect/duplicate/stale conflict tests | DEFERRED |

## 10. Reporting and Program Head requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-REP-001 | Program Head is read-only/program-scoped and metrics define ownership, quota source/version, matched meaning, freshness, inactive-history behavior, and privacy aggregation threshold | No live Program Head path; manuscript metrics undefined | D-018/D-080, EV-017 | GF-04 CAP-024; GF-06 §12; SCN-VS-022 | Metric-dictionary, scope/privacy, freshness tests | EVIDENCE HOLD |
| REQ-REP-002 | Protected report views/exports enforce domain scope, neutralize formulas, record actor/query/version, and produce audit evidence | Broad current staff reports and missing report audit | D-062/D-080 | GF-07 FAIL-031; GF-08 §8; SCN-VS-022 | Scope/formula/audit/export-job tests | PROPOSED |

## 11. Platform and evidence requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-PLAT-001 | Runtime, database, test frameworks, and dependency versions are pinned consistently across local/CI/images | Node/DB/Pest-PHPUnit contradictions | D-055–D-059, G-PLATFORM | GF-09 §1 | Clean lock/install/version output | PROPOSED |
| REQ-PLAT-002 | One robust Compose topology includes web, worker, scheduler, DB, approved inference, and local/test mail with health dependencies | User deployment preference/current missing sidecar | D-060–D-064 | GF-09 §2 | Compose render/boot/health output | PROPOSED |
| REQ-PLAT-003 | Greenfield starts with zero known lint/type/static/test failures; zero discovered tests is failure | Dirty legacy quality reality | D-065 | GF-09 §§6–8 | Green CI report | PROPOSED |
| REQ-PLAT-004 | Production-engine migration/seed, constraints, concurrency, upgrade, rollback policy, backup/restore are verified | SQLite-only/fresh-only plan gap | D-057/D-066 | GF-09 §§5/9.1; GF-19 §§5/8/9 | Catalog/race/restore/RPO-RTO ownership evidence | PROPOSED |
| REQ-PLAT-005 | Manual core remains available during optional provider/worker outage | Capstone reliability | D-067 | GF-09 §9.1; GF-17 §§4/10; SCN-VS-008 | Degraded-mode E2E and readiness/health evidence | PROPOSED |
| REQ-EVAL-001 | Small technical pilot precedes broad TAM and has defect/Go-No-Go evidence | Current plan jumps to evaluation | D-012/D-013/D-072 | GF-10 §§10–12 | Pilot report and approved protocol | PROPOSED |

## 12. Frontend, route, and UX requirements

| Requirement ID | Requirement | Primary evidence/source | Decision/gate | Contract/acceptance | Future proof | Status |
|---|---|---|---|---|---|---|
| REQ-UX-001 | Every authenticated/public journey has an explicit shell, page identity, scope/context, server-authorized route responsibility, and top breadcrumb behavior | Legacy shell/breadcrumb evidence; UI ambiguity risk | D-014–D-023, D-055, G-ROLE | GF-21 §§5–6; GF-22 §§3–4; REQ-VS-027 | Route/page-prop/browser tests | PROPOSED |
| REQ-UX-002 | Navigation/control visibility is a usability projection only; direct URLs, page props, queries, exports, jobs, and assistant commands enforce GF-21 policy and resource scope | Route/policy mismatch and RAG risk | D-014–D-023, D-051, G-ROLE | GF-21 §§1–5; REQ-SEC-001 | Allowed/denied/cross-scope/revocation tests | PROPOSED |
| REQ-UX-003 | Every page/panel distinguishes initial loading, refresh, ready, empty, filtered-empty, not-yet-available, denied/not-found, validation, server error, conflict, degraded provider, and retry/reconciliation states | Existing empty/stale/projection failure risk | D-028, D-067, G-WORKFLOW | GF-20 §2; GF-22 §7; REQ-VS-026 | Component/E2E state matrix | PROPOSED |
| REQ-UX-004 | Forms preserve safe input, associate field/server errors, manage focus, prevent duplicate/idempotent submit, warn on dirty navigation, and use risk-appropriate confirmations | Command/retry/conflict contract | D-029, D-031, D-032, D-039, D-043, D-075 | GF-20 §§2–4; GF-22 §§5.3–5.4/9 | Vitest/Playwright validation/conflict/confirmation tests | PROPOSED |
| REQ-UX-005 | Toasts and durable notifications have typed severity, duration/dismissal, deduplication, correlation, redaction, accessible live-region behavior, and authoritative pending/delivery wording | Legacy notifications/toasts plus after-commit contract | D-045, D-060, D-067 | GF-07; GF-20 §2; GF-22 §6 | Notification/polling/retry/redaction tests | PROPOSED |
| REQ-UX-006 | Charts/KPIs/graphics are scoped read projections with metric definition, unit/denominator, freshness, zero/no-data/suppressed/stale/error states, accessible text/table alternative, privacy thresholds, and audited export/drill-down | Legacy Chart.js evidence; reporting/privacy gaps | D-018, D-049, D-050, D-080, G-UX | GF-10; GF-21 §5.4; GF-22 §8 | Chart state/accessibility/privacy/export tests | CONDITIONAL |
| REQ-UX-007 | Tables/lists support authorized filters, sort, pagination, deep links, responsive card/scroll behavior, empty distinctions, and row/bulk action safety without broadening scope | Existing list/filter behavior and scope contract | D-018, D-020, D-028, G-ROLE | GF-20 §5; GF-22 §5.2 | URL-state/scope/responsive browser tests | PROPOSED |
| REQ-UX-008 | OMR and document graphics expose provenance, confidence/uncertainty, human-review/correction status, privacy/watermark/print state, and manual fallback without autonomous authority | OMR UX direction and document security | D-035, D-046–D-048, D-078, G-OMR | GF-10 §3; GF-22 §§8.5/9.5/9.6 | Review/import/preview/print tests | SPIKE HOLD |
| REQ-UX-009 | Responsive and accessible behavior covers keyboard/focus/landmarks/live regions/contrast/reduced motion/touch targets/zoom/reflow for critical journeys | Accessibility and mobile acceptance gap | D-055, D-059 | GF-22 §§10–11; REQ-VS-031 | Playwright keyboard/viewport/accessibility evidence | PROPOSED |
| REQ-UX-010 | Offline UI distinguishes local capture, queued replay, server commit, conflict, revoked/expired duty, and manual resolution; it never claims authoritative success from cache | Offline integrity contract | D-053, G-OFFLINE | GF-07/GF-10; GF-22 §9.4 | EV-011/device/replay/conflict tests | CONDITIONAL |
| REQ-UX-011 | RAG/assistant UI distinguishes policy citations, own-data scope, task proposal, confirmation, refusal, inquiry handoff, and provider degradation; the same backend command/policy gates are used as ordinary UI | RAG authorization/refusal contradiction | D-008, D-051, D-079, EV-018 | GF-08 §7; GF-20 §§4–5; GF-22 §9.8 | Citation/auth/refusal/confirmation/outage tests | CONDITIONAL |
| REQ-UX-012 | Protected views/exports/prints, break-glass activity, and sensitive feedback are scoped, redacted, reasoned, and correlated to audit evidence; no credential/secret/hidden-record leakage occurs in UI channels | Security/privacy/audit contract | D-023, D-034/D-035, D-052, D-062, G-AUDIT | GF-08; GF-21 §§5–7; GF-22 §12 | Audit-of-view/export/print/redaction tests | PROPOSED |

## 13. Traceability completion rule

A row may become APPROVED/implementation-ready only when:

1. its decision IDs have final outcomes;
2. evidence holds are closed or the approved behavior is explicitly narrowed;
3. the cited workflow/domain/security contract contains actor, precondition, state/action, effects, failures, and recovery;
4. at least one Given/When/Then scenario exists;
5. future test/runtime evidence is assigned to an executable story;
6. any conditional/deferred status agrees with the final manuscript and roadmap.

Range references and green counts alone are insufficient. The final readiness audit must follow each committed row into its actual story, code, test, and runtime evidence.
