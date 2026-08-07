# Batch-Approval Recommendations for the Decision Matrix

**Artifact:** NEXIAM-GF-13B (companion to GF-13)
**Status:** Historical companion recommendations — superseded for all rows with a recorded founder outcome in GF-13; retained as rationale and not as an approval queue
**Purpose:** Preserve the original evidence-backed recommendation rationale for the batch review; the current founder outcomes live in GF-13 and this companion is not an approval queue.
**Basis:** 2026-08-02 founder session decisions plus the 2026-08-03 founder reconciliation (Super Admin full audit-scope view, Registrar Support Staff versus Registrar Clerk, Test Administrator-owned reservation/manual/direct-assessment paths, human-assisted OMR, committed offline proctoring, definite tool-gated RAG, retention working defaults, and PostgreSQL greenfield authority), the latest manuscript docs, legacy codebase evidence, and verified platform facts.

## 1. Current state of GF-13

- D-001 through D-080 total: 80 canonical decisions.
- Earlier decided by David on 2026-08-02: **31** rows.
- The 2026-08-03 review recorded explicit founder outcomes in GF-13 for the remaining recommendation rows plus the related role/scheduling/OMR/RAG/database clarifications.
- GF-13 contains the canonical disposition for every D-001–D-080 row; the historical recommendation table below is not an approval record.

### 2026-08-03 founder reconciliation recorded in GF-13

- **D-023:** Super Admin is the master of the full audit-scope user view, including cross-office/user/event filtering. This is not routine business-data browsing; audit-view use is reasoned, time-bounded, alerted, audited, and post-reviewed.
- **D-020/D-036:** Registrar Support Staff is not Clerk-equivalent. Support Staff may perform delegated intake/correction, accept/dismiss applications, and view statuses. Registrar Clerk and Registrar Admin retain institutional/reference/setup-page authority.
- **D-022/D-038–D-040/D-075:** Test Administrator is an audited capability held by a concrete Guidance Counselor/Clerk/Support Staff account. Accepted applicants may use an explicitly opened reservation plan; the capability holder may close/finalize it, bypass it for manual late-cohort assignment, publish immediately or later, and force-open/close only inside the approved session/application window. Auto-close is one idempotent Test Administration/Proctor contract.
- **D-042:** Direct Assessment is included as a special late/same-day path that bypasses ordinary reservation/scheduling while preserving accepted-applicant eligibility, durable assessment context, completeness, grading, release, and audit invariants.
- **D-046/D-047/D-076:** OMR is a human-assisted scanning/review interface. It may show per-item green/uncertain/red observations and identity ambiguity; it does not autonomously accept/reject candidates or finalize scores. Human binding/correction is recorded before reviewed import.
- **D-048/D-050:** Request Yukee’s exact parameters before claiming reproduction. BGE is a low-priority feature-flagged beta and never a core admission/scoring/release authority.
- **D-051/D-079:** RAG is a definite chatbot with separate policy-citation, released own-data, and typed UI-equivalent task contexts. Mutations use the same frontend/request gates, server-side capability checks, confirmation, idempotency, and audit; no model/database backdoor.
- **D-011/D-053:** Offline proctoring is a committed capstone capability with encrypted device scope, durable outbox, ordered replay, conflict detection, human reconciliation, and visible degraded status; EV-011 gates promotion rather than silently deleting the feature.
- **D-034/D-035:** Cycle closure creates historical/read-only scope. Working defaults are five years for workflow records, seven years for audit/security evidence, and 90 days for eligible raw OMR images after finalization and hold closure; institutional/privacy approval and legal hold remain required for real pilot/production.
- **D-057/D-064:** PostgreSQL is the greenfield production authority from the start; the major version is pinned in E0 and pgvector is extension/ADR/evidence-gated. Current MySQL Compose is legacy/current-app evidence.

GF-13 is the canonical source for these amendments. The original recommendations remain below as historical rationale and must not override the Founder outcome column.

## 2. Historical approval instruction (superseded)

The following one-line instruction is retained only to explain the former review workflow. It must not be used as the current authority; current dispositions live in GF-13:

- `GO ALL RECOMMENDED` — accepts all 49 below.
- `GO ALL RECOMMENDED EXCEPT D-034, D-040` — then give one-line direction for the exceptions.
- `D-XXX REVISE: ...` / `D-XXX GO WITH CONDITION: ...` / `D-XXX NO-GO: ...` for individual rows.

The recorded batch outcomes do not authorize code, pilot, or deployment by themselves. Promoted decisions still require R4 evidence spikes, R5 readiness, then E0–E2 implementation gates.

## 3. Historical recommended dispositions (original 49-row batch)

### C. Actors, capabilities, and data scope (D-022, D-023)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-022 Proctor assignment owner | GO | Already implied by approved D-015/D-017: authorized Guidance Test Administrator assigns/revokes Proctor duty; HR and Registrar have no Proctor authority. Session decision: "HR cannot grant Test Administrator or Proctor capability." |
| D-023 Audit-view scope | GO WITH CONDITION — Super Admin break-glass security view; office-scoped review only after explicit approval; applicant access-history view/notice for approved protected result-view events; every protected view audited | Approved D-021 already conditions "audit every protected view" and routes applicant-facing access history/notice here; GF-08 audit contract and the role catalog agree. |

### D. Identity, domain, lifecycle, and retention (D-026, D-028..D-035)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-026 Staff authentication | GO — named local accounts first; Google OAuth deferred until linking/domain/verified-email/takeover rules pass | Legacy is local-account based; no institutional IdP exists at ISPSC Tagudin; socialite in legacy composer.json is evidence, not greenfield authority. |
| D-028 Workflow source of truth | GO — commands own domain state; stage/status dashboards are projections from completed milestones, not mutable truths | GF-18 LLD and GF-20 command/query catalogs already implement exactly this. |
| D-029 Reopen behavior | GO — reopen/archive/version without deleting ApplicantAccount, assignments, scores, decisions, messages, releases, or audit | Directly follows approved D-024 condition ("never delete historical attempts or evidence merely because a cycle ends"). |
| D-030 Retake/reassignment | GO WITH CONDITION — uniqueness scoped to AdmissionAttempt/cycle/mode; prior ExamAssignments and reasons preserved; retake rule set by Guidance policy before E2 | GF-19 already forbids global one-to-one applicant constraint ("repeat attempts are policy-scoped by cycle/mode/rule"). |
| D-031 Grading cardinality | GO — one active GradingSession per assessment unit; corrections versioned/reasoned | Matches D-076 and GF-19 ("one active grading version per assessment unit"). |
| D-032 Release reversal | GO — unrelease creates reversal/supersession event and changes current visibility; prior release/document remains evidence | Matches approved D-077 condition and GF-19 ("result release reversal creates a new event/version; does not delete prior rows"). |
| D-033 Reference-data lifecycle | GO — deactivate/version courses, rooms, aptitude areas, templates, mappings referenced by history; no destructive deletion | GF-19 reference-data rules; consistent with D-024 durability stance. |
| D-034 Personal/assessment retention | GO WITH CONDITION — archive + minimum necessary access is the working default; exact retention/erasure periods stay HOLD until institutional/privacy approval (EV-013) | Institutional policy cannot be invented; the architecture default is safe without a period. |
| D-035 OMR/raw model artifact retention | GO WITH CONDITION — private restricted storage; separate short raw-input retention from longer derived provenance; exact period HOLD pending privacy/storage approval | Follows D-007 condition and GF-08; no real-data pilot until periods are set. |

### E. Operational workflow (D-036, D-037, D-039..D-043)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-036 Application decision owner | GO WITH CONDITION — Registrar Administrator owns accept/dismiss; Registrar Support Staff may be delegated correction/intake but not final decision by default | Session decision: support staff "default to Registrar Clerk capabilities" and "same clerk tasks"; final decision stays with Registrar Administrator. |
| D-037 Administrative window override | GO WITH CONDITION — normal intake respects the application window; exceptional override requires capability + reason + audit (Registrar Admin; break-glass for emergencies) | Consistent with D-017 break-glass discipline and GF-08 audit. |
| D-039 Session timing override | GO WITH CONDITION — only explicit privileged Guidance/break-glass command with reason/audit; normal Proctor follows the time window | Approved D-038 gives Guidance session ownership; D-075 defines the override path. |
| D-040 Auto-close | GO WITH CONDITION — after grace: no attendance → cancelled; any attendance → completed with unresolved records surfaced; one locked/idempotent job; grace duration set by Guidance validation before E2 | Reconciles legacy ExamSessionAutoCloser behavior with D-075's single idempotent close command. |
| D-041 Attendance/submission | GO — separate idempotent commands, assigned applicant only, active session/time window; corrections require reason | GF-20 RecordAttendance/RecordSubmission contracts already specify this. |
| D-042 Direct assessment | GO WITH CONDITION — separate assessment mode sharing grading/release invariants; cannot mark scored with zero/partial required scores; excluded from the first slice | Legacy direct-assessment exceptions are evidence; deferring keeps the connected slice coherent. |
| D-043 Score correction | GO — pre-finalization: controlled edit; post-finalization: reopen/version with reason and audit; no silent overwrite | Matches D-031/D-076 and GF-19 score versioning. |

### F. Capstone differentiators (D-046..D-054)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-046 OMR input contract | GO WITH CONDITION — approved fixed template/version and intended scanner/camera channels; reject wrong/malformed inputs; implementation only after EV-008 (real sheet + ground truth + thresholds) | Approved D-007 condition already demands artifacts/ground-truth/thresholds/human review. |
| D-047 OMR authority | GO — OMR proposes observations/scores; approved human review/import command creates authoritative scores | D-007 + GF-20 ProcessApprovedOmrBatch ("abstain/review on uncertainty; no direct score write"). |
| D-048 Yukee method | GO WITH CONDITION — exact reproduction only with parameters/transforms; otherwise label honestly as "NEXIAM deterministic aptitude-profile baseline"; gate EV-009 | Approved D-010 condition; matches the earlier senior-engineer ruling (no fake K-Means claims). |
| D-049 Program mapping | GO — versioned expert-approved reference data, separate from model output | GF-19 mapping_definitions; D-080 keeps suggestions/selections separate. |
| D-050 BGE method | GO WITH CONDITION — conditional embedding-similarity signal on genuine interest/goal narrative + approved program corpus; never called supervised; gate EV-006 relevance spike | Approved D-010/D-011; earlier ruling: program-choice-vs-program-description comparison is tautological, so input must be a narrative. |
| D-051 RAG data boundary | GO — cited approved corpus + authenticated own result/schedule data; no cross-applicant data; no placement recommendation; refusal shows staff fallback | Approved D-008 + session decision (refusal → staff fallback → bounded Guidance inquiry only after applicant confirmation). |
| D-052 Audit assurance wording | GO — "tamper-evident under defined threat model"; canonical events, serialized chain, key versioning, verification, checkpoints | GF-08/GF-20 audit contract; no stronger claim until the audit spike passes. |
| D-053 Offline proctoring | HISTORICAL/SUPERSEDED — prior GO WITH CONDITION wording is replaced by the canonical GF-13 outcome: committed capstone capability with encrypted device/session scope, durable outbox, ordered replay, conflict detection, human reconciliation, visible degraded status, and EV-011 promotion gate | GF-13 D-053 is canonical; this row preserves the earlier recommendation only as rationale. |
| D-054 Generic engine registry | GO — fixed AdmissionSupportEngine contract with approved adapters; no arbitrary endpoint/class/UI plugin execution | Approved D-010/D-011; earlier ruling: "no plugin marketplace" during capstone. |

### G. Platform and operations (D-055..D-067)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-055 Backend/frontend versions | GO — PHP 8.4, Laravel 12, Inertia 2, Svelte 5, TypeScript 5.9, Tailwind 4 | Verified in composer.json, package.json, CLAUDE.md. |
| D-056 Node runtime | GO — Node 24 LTS across CI and images | Verified: .github/workflows/quality.yml pins node-version 24. |
| D-057 Production database | HISTORICAL/SUPERSEDED — prior MySQL 8.4 recommendation; canonical GF-13 outcome is PostgreSQL as greenfield authority from E0, with the supported major pinned and pgvector separately gated; MySQL remains legacy/current-app evidence | GF-13 D-057 is canonical; this row preserves the evidence that caused the engine contradiction. |
| D-058 Backend test framework | GO — PHPUnit 11; no mixed Pest suite | Project authority (CLAUDE.md); README names PHPUnit 11. |
| D-059 Browser/frontend tests | HISTORICAL/SUPERSEDED — canonical GF-13 outcome is Vitest for frontend units and Playwright for connected journeys; zero discovered tests is failure | GF-13 D-059 is canonical; the earlier alternative browser-runner wording is retained only as rationale. |
| D-060 Queue baseline | GO — Laravel database queue initially, dedicated worker, after_commit=true, idempotency/retry/dead-letter visibility; Redis may replace by ADR | Standard Laravel 12 posture; matches GF-20 job contract. |
| D-061 Inference topology | GO WITH CONDITION — one authenticated internal Python worker/sidecar for approved OMR/BGE; no direct DB writes; Python-dependent capabilities defer if their gates fail | Matches GF-20 InferencePort rules; keeps core manual workflow provider-independent. |
| D-062 File storage | GO — private Laravel filesystem volume for controlled demo; object-storage adapter seam for pilot; checksum/version/backup contract | Matches GF-19/GF-20 storage posture; pilot-scale decision can be an ADR. |
| D-063 Local/test mail | GO — Mailpit companion service; production provider behind mail adapter | Verified: Mailpit already in compose.yaml; matches David's compose preference. |
| D-064 Deployment baseline | HISTORICAL/SUPERSEDED — canonical GF-13 outcome is one health-chained Compose with PostgreSQL, app/web, queue worker, scheduler, optional approved Python worker, private storage, Mailpit local/test, and Dokploy/Traefik ingress | GF-13 D-064 is canonical; prior MySQL wording is retained only as the source of the engine contradiction. |
| D-065 Quality baseline | GO — greenfield starts at zero known lint/type/static/test failures, no permanent baseline; CI green before promotion | Existing quality.yml + quality script; the zero-tolerance contract. |
| D-066 Backup/restore | GO WITH CONDITION — database plus required private files, off-machine copy, restore rehearsal, RPO/RTO defined before any pilot | G-DEPLOY gate; not needed for controlled demo. |
| D-067 Provider degradation | GO — manual core workflow remains complete when mail/model/vector/LLM/inference providers fail; failures visible/retryable | Approved D-007/D-009/D-010 manual-fallback conditions; core slice must never hard-depend on providers. |

### H. Readiness and promotion (D-068..D-072)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-068 First implementation target | GO — build the Connected Admission-to-Release Slice, not all phases or isolated CRUD | GF-11 slice definition; approved D-006 "first proof milestone". |
| D-069 Spike sequencing | GO — run OMR/RAG/model/audit/offline spikes before their production implementation | GF-14 R4 phases; failed spike activates documented fallback. |
| D-070 OpenSpec role | GO — subordinate executable change contract after approved BMAD/product/domain/ADR authority | Approved D-001/D-002 hierarchy; OpenSpec is not competing product authority. |
| D-071 Readiness verdict | GO — no Phase 4 until no open P0, first-slice traceability complete, fresh readiness PASS/CONDITIONAL PASS | GF-14 R5 gate; conditional Phase 0 may only run non-locking scaffold experiments. |
| D-072 Pilot/deployment clearance | GO — stays blocked until privacy, stakeholder, technical, operations, and restore gates pass (G-DEPLOY); controlled synthetic demo only | GF-14 G-DEPLOY; institutional approval required for real data. |

### I. Operational-contract reconciliation (D-074..D-076, D-079)

| ID | Recommended outcome | Basis |
|---|---|---|
| D-074 Admission-decision vocabulary | GO — `pending`, `needs_correction`, `accepted`, `dismissed`, `archived`; legacy expired/rejected/incomplete meanings become explicit reason/history | Written from the bounded controller journeys; unblocks intake, reports, migrations. |
| D-075 Exam deadline/close/reopen semantics | GO — one effective deadline everywhere; extension shifts attendance/submission/auto-close windows; manual and automatic close invoke one idempotent command that records outcome and opens grading exactly once; incomplete close needs reasoned override; reopen only before grading starts, otherwise superseding correction/retake | Reconciles legacy divergent cutoffs; matches D-039/D-040 and GF-20 CloseExamSession. |
| D-076 Grading state and import semantics | GO — one active grading version per exam; `open → review → finalized`; score presence is a projection; multi-area manual saves transactional; staged durable import batch, row states, row-atomic resumability, idempotency, manual fallback; post-finalization correction creates a new version | Matches D-031/D-043 and GF-19/GF-20; keeps manual scoring complete. |
| D-079 RAG access and refusal-to-inquiry handoff | GO WITH CONDITION — institutional policy RAG may be available before consultation release; own scores/results/consultation included only when authoritative release permits; refusal shows staff fallback and creates a bounded Guidance inquiry only after applicant confirmation; general inter-office messaging stays conditional | Approved D-008 + D-051 + session decision on refusal handoff; inquiry threads remain conditional per D-011. |

## 4. What happens after you approve

1. GF-13 records the 2026-08-03 outcomes; this companion remains historical rationale for the original batch.
2. I propagate changed wording through the role, workflow, security, HLD/LLD, schema, and contract artifacts.
3. R4 bounded spikes begin: OMR sheet/ground-truth feasibility, RAG corpus/refusal, engine baseline (Yukee/BGE), audit-chain design — each with measurable pass/fail.
4. R5: approved PRD, promoted ADRs, executable OpenSpec package, stories for E0/E1/slice, fresh readiness audit → PASS verdict.
5. E0 clean scaffold (Laravel 12 + PHP 8.4 + PostgreSQL pinned major, PHPUnit 11, Vitest, Playwright, quality gates) → E1 identity/roles/audit → E2 connected admission-to-release slice with manual scoring as complete fallback.
