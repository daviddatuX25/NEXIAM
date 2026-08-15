# BMAD/OpenSpec Greenfield Implementation Package

**Artifact:** NEXIAM-GF-24  
**Status:** ACTIVE IMPLEMENTATION HANDOFF — E0 PASS; E1 PASS; E2 NOT READY  
**Single entry point:** [`../GREENFIELD-MASTER-REFERENCE.md`](../GREENFIELD-MASTER-REFERENCE.md)  
**Work process:** [`../project-context.md`](../project-context.md)  
**Master roadmap:** [`14-readiness-gates-and-reconciled-roadmap.md`](14-readiness-gates-and-reconciled-roadmap.md) / [`../MASTER-DELIVERY-ROADMAP.md`](../MASTER-DELIVERY-ROADMAP.md)  
**Active OpenSpec change:** [`../../openspec/changes/e2-connected-admission-release-slice/`](../../openspec/changes/e2-connected-admission-release-slice/). Archives: [`../../openspec/changes/archive/`](../../openspec/changes/archive/).

> Reconstructed 2026-08-04 after document-integrity repair. Prefer the master reference for live status if this file drifts.

## 1. The answer in plain language

We have a valuable legacy system. We rebuild greenfield because of architectural and scalability flaws — not because the product feel is wrong.

- Legacy supplies the nearest-working behavior, UI affordances, vocabulary, and failure cases.
- Greenfield supplies safe architecture, authorization, transactions, concurrency, and scale.
- Implementation is authorized only by **one active OpenSpec change** at a time.
- BMAD skills may help promote PRD/epics/readiness; they are not a second ceremony that restarts planning.

The first physical product milestone remains: E0 baseline → E1 identity/reference → E2 connected admission-to-release slice.

## 2. What exists now versus what this package creates

| Item | Now |
|---|---|
| Legacy feature surface | Preserved under `legacy_codebase/application/` with disposition bridge in `docs/legacy-reference/` |
| Physical target application | Isolated at `system/` (Laravel 13 / Inertia React / PostgreSQL 16 / Compose) |
| E0 | Scaffold evidence PASS |
| E1 | Staff auth, capability helper/seam, scope, reference catalogs — live exit verification/reconciliation still open |
| E2 | PLANNING PASS 2026-08-05 — implement under active OpenSpec; delivery evidence pending |
| Test proof | Pest/Vitest/Playwright baseline present; connected-journey evidence matrix is an E2 gate |

## 3. Authority and boundary

### 3.1 Source-of-truth order

1. recorded founder decisions and verified institutional evidence;
2. master reference + master roadmap phase/gate rules;
3. approved GF contracts (scope → roles → workflows → data → security → HLD/LLD/schema → authz → UX → ops);
4. the active OpenSpec change;
5. executable tests and runtime evidence;
6. legacy-reference crosswalk and legacy code last.

### 3.2 Boundary rule

- Fresh migration history only; no legacy DB/route/migration runtime coupling.
- Capability + scope + duty authorization; UI is never authorization authority.
- One command owns each transition; stage is a projection.
- AI/OMR/models propose; authorized human/application commands commit.
- Preserve useful legacy feel; rewrite unsafe ownership and trust boundaries.

### 3.3 Legacy study is mandatory for feature work

Before implementing a reused behavior:

1. inspect legacy paths;
2. record disposition (`PRESERVE` / `REWRITE` / `SPIKE` / `DEFER` / `REJECT`);
3. map to FR/BR + story + page/state contract;
4. add OpenSpec tasks and tests that cover happy, denied, conflict, and recovery paths.

This is how we keep the product feel close to legacy without repeating legacy bugs.

## 4. Legacy-to-greenfield disposition (summary)

| Legacy evidence | Disposition | Greenfield action |
|---|---|---|
| Public application intake | Reuse behavior; rewrite boundary | `SubmitAdmissionAttempt` + durable identity/reference |
| Staff application management | Extract workflow; rewrite state ownership | Explicit correct/accept/dismiss/reopen commands + history |
| Applicant portal/auth | Reuse journey; rewrite credential lifecycle | Hashed challenges, non-enumerating errors, own-data isolation |
| Exam scheduling | Reuse vocabulary/UI; rewrite locks/constraints | Manual scheduling commands + DB integrity |
| Result release | Reuse modes/print evidence; rewrite aggregate | Distinct consultation/document/release/reversal commands |
| Documents/print | Reuse layouts; rewrite trust boundary | Versioned templates, private artifacts, checksums |
| Audit | Preserve vocabulary; redesign assurance | Mandatory audit with approved threat wording |
| AI companion/RAG | Conditional adapter | Ports/flags first; E3 OpenSpec after G-RAG |
| Setup readiness bars, breadcrumbs, import preview, toasts | Capture in UX/parity ledger | GF-22 page contracts + Playwright evidence |
| Missed exam / reassignment | Decision required | Explicit E2 policy before coding |

Full row shape lives in [`../legacy-reference/README.md`](../legacy-reference/README.md).

## 5. E0 — Clean scaffold and quality baseline

**Status:** PASS (scaffold evidence recorded)

Deliverables include isolated `system/`, Compose topology, Pest/Vitest/Playwright, fresh PostgreSQL migrations, seed/reset, operation/idempotency/audit seams, health/queue visibility, and E0 evidence report.

E0 does not build admissions journeys, RAG/OMR, or production auth integrations.

## 6. E1 — Identity, access, and reference foundation

**Status:** CONDITIONAL — automated seams present; live Compose exit verification and OpenSpec reconciliation still open

Build:

- staff authentication surfaces;
- capability catalog + GF-21 authorizer/middleware;
- office/program/cycle actor scope;
- reference catalogs (program/room/aptitude/window + institution/cycle);
- AI ports/flags/degraded slot hooks if claimed by roadmap (reconcile with E1 OpenSpec);
- denial/audit evidence.

Exit requires cross-scope denial, credential lifecycle, replay/stale-version, audit, and shell evidence.

## 7. E2 — Connected Admission-to-Release Slice

**Status:** NOT READY

Authority: GF-11 (`REQ-VS-*`), GF-20–GF-22, approved stories, then `e2-connected-admission-release-slice` OpenSpec.

Fixed build order:

1. `SubmitAdmissionAttempt` and durable identity/reference  
2. Correct / Accept / Dismiss / bounded reopen-archive  
3. Applicant access challenge and setup  
4. Exam session create / assign / publish (manual scheduling)  
5. Proctor duty, start/extend, attendance, submission  
6. Close exam → open grading (exactly once)  
7. Manual score / review / finalize  
8. Consultation draft/confirm/defer  
9. Prepare result document  
10. Release, after-commit notification, applicant retrieval  
11. Reverse release where policy allows  
12. Full denial, concurrency, degraded, accessibility, breadcrumb, toast, document, audit evidence  

Manual scoring is the complete E2 fallback. OMR/RAG/BGE/YuKee/offline stay out unless separately gated.

## 8. E3 and later capability boundary

After E2, implement only the next bounded capability with its evidence gate:

| Capability | Phase | Entry gate | Fallback |
|---|---|---|---|
| RAG / chatbot (incl. future Nexi persona) | E3 | Corpus ownership/versioning, auth-before-retrieve, citation/refusal, privacy, prompt-injection, outage tests | Static policy + staff handoff |
| Scheduling assistance | E3 | Constraint/feasibility spike; human confirm only | Manual scheduling |
| OMR | E3 | Approved sheet/input/ground truth/confidence/review | Manual scoring |
| YuKee / BGE / models | E3/E4 | Reproducible artifact or honest baseline; proposal-only worker | Counselor/manual; feature disabled |
| Offline proctoring | E3/E4 | EV-011 and durable outbox/replay/conflict evidence | Online/paper/manual reconciliation |
| Personality / PNG frames | E3 | Feature flag + reduced-motion + static fallback | Text-first assistant UI |

One OpenSpec change per capability. No research feature hidden inside E2.

## 9. Exact OpenSpec change sequence

### Change 1 — `e0-greenfield-foundation`

Created. Clean workspace, quality gates, Compose, PostgreSQL authority, seed/reset, health/queue/operation visibility, E0 evidence.

### Change 2 — `e1-identity-reference-foundation`

In progress / conditional exit. Identity, authentication, capabilities/scopes, reference data, base audit/idempotency seams.

### Change 3 — `e2-connected-admission-release-slice`

Create only after E1 exit + fresh readiness PASS. Implements GF-11 command spine with manual scoring fallback.

### Change 4+ — spike-gated differentiators

Each OMR/RAG/model/offline/personality change gets its own OpenSpec.

## 10. Implementation dependency graph

```text
E0 clean workspace + quality + Compose + PostgreSQL
  ↓
E1 identity/access/reference/audit/idempotency
  ↓
E2 admission → examination/proctor → manual grading → consultation/release/portal
  ↓
E2 connected evidence and readiness review
  ├── OMR spike → OMR implementation if passed
  ├── RAG/Nexi spike → RAG implementation if passed
  ├── model spike → honest approved adapter if passed
  └── offline spike → durable sync if passed
```

## 11. Definition of Ready for an implementation task

A story/change is ready only if it names:

- upstream decision IDs and contract sections;
- legacy disposition when behavior is reused;
- actor, capability, resource scope, and duty;
- command/query or page ownership;
- transaction/lock/idempotency protocol;
- asynchronous side effects and degraded fallback;
- route/page/state/feedback requirements where UI is involved;
- Pest/Vitest/Playwright or operational evidence target;
- rollback or correction behavior.

## 12. Definition of Done for E0/E1/E2

A change is done only when:

1. OpenSpec tasks for the change are complete without hiding earlier failures;
2. fresh PostgreSQL migrations and deterministic seed/reset pass where applicable;
3. relevant backend/frontend/browser tests are discovered and green;
4. authorization denial and happy path are both proven for protected behavior;
5. audit/idempotency/after-commit side effects match contracts;
6. UI states match GF-22 where UI is involved;
7. evidence paths are recorded; master reference/GF-00/GF-14 status updated if a gate moves;
8. implementation matches GF-18–GF-23 or divergence is recorded as a decision/ADR;
9. no legacy route, migration, database, or provider was silently made authoritative;
10. active OpenSpec `HANDOFF.md` lists **developer-ready seeded data** (logins, seeder/reset commands, expected state, feature URLs) so a human can click the happy path without inventing fixtures.

## 13. Physical handoff checklist

- [x] Preserve legacy reference runtime separately under `legacy_codebase/application/`
- [x] Isolate executable target at `system/`
- [x] Confirm PHP 8.4, Laravel 13, Inertia 3, Svelte 5, TypeScript, Tailwind 4, Pest 5, PostgreSQL 16
- [x] E0 evidence report exists
- [ ] E1 live Compose reset/seed + login spot-check
- [x] Product promotion ([`../prd.md`](../prd.md), GF-03 PROMOTED 2026-08-05)
- [x] E2 stories / UX catalog / E2 OpenSpec + planning readiness PASS (2026-08-05)

## 14. Current gate

- Planning coherence: NEEDS WORK (readiness report NOT READY for E2)
- E0 scaffold: PASS
- E1: CONDITIONAL
- E2 connected slice: NOT STARTED / NOT READY
- Pilot/production: NOT GRANTED

Immediate authorized work: close E1 live exit, then promote product/stories/UX catalogs and open E2 OpenSpec — do not invent E2 screens without that chain.
