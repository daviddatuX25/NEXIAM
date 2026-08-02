# NEXIAM v2 Greenfield Architecture Plan

**Decision date:** 2026-08-01
**Supersedes:** strangler-style modernization recommended in `master-architecture-picture.md` section 1

---

## 1. Strategic pivot

The legacy NEXIAM codebase (Laravel 12 / Inertia 2 / Svelte 5) will **not** be refactored in place. The project restarts as a clean-slate build.

| Aspect | Old strategy | New strategy |
|---|---|---|
| Codebase role | Execution target | **Read-only reference** |
| Database | Preserve 86 legacy migrations | Clean schema designed from settled domain models |
| Tech debt baseline | Suppress and shrink (988 PHPStan, 1,073 svelte-check, 309 ESLint) | **Zero from commit 1** |
| Quality gate | No-growth ledgers against inherited debt | **All-green CI; no baselines, no suppressions** |
| Migration path | Strangler-style incremental replacement | Feature-by-feature rebuild with clean boundaries |

### Why

The audit proved the legacy code has:
- fragmented workflow ownership across 41 controllers, 35 services, and model hooks;
- three divergent role catalogs with no authoritative source;
- zero meaningful tests and zero characterization coverage;
- concurrency/integrity bugs in core flows (reference generation, booking, pipeline transitions);
- plaintext tokens, unescaped template injection, vulnerable dependencies;
- 86 migrations with fresh-vs-upgraded schema drift.

Strangler-style refactoring would require characterizing every legacy behavior before touching it, carrying all inherited debt as baselines, and preserving schema drift. A clean build uses the audit as a specification and sidesteps the characterization burden entirely.

### What the legacy codebase gives us

It is a **reference specification**, not a codebase to port. We extract:

1. **Domain workflows and invariants** (admission lifecycle, exam/proctor flows, grading/release, document generation)
2. **Data model semantics** (entity relationships, transition rules, pipeline states)
3. **Access control intent** (role boundaries, policy patterns, guard separation)
4. **Integration contracts** (Mixedbread RAG, OpenRouter LLM, DOCX/PDF rendering, spreadsheet I/O, OMR)
5. **UI/UX patterns** (Inertia page structure, shadcn-svelte component usage, form flows)
6. **Business rules** (reference number generation, booking constraints, grading finalization, release semantics)

We do **not** carry over: code structure, migration history, PHPStan baselines, suppress lists, dead code, or architectural mistakes.

---

## 2. Technology decisions

Retain the same stack — the audit found no reason to change it:

| Layer | Technology | Notes |
|---|---|---|
| Backend | Laravel (latest stable) | Modular monolith; no microservices at capstone scale |
| Frontend | Inertia.js + Svelte 5 | TypeScript from day one; no JS bridge period |
| UI components | shadcn-svelte | Typed props from the start |
| Database | MariaDB (production), SQLite (testing) | Single migration history; no drift |
| Queue | Laravel queues | `after_commit: true` default |
| Inference | Python sidecar/worker | OMR (OpenCV), embeddings (BGE), aptitude (Yukee) |
| Search/RAG | Mixedbread embeddings | Scoped retrieval with confidence/citation contract |

---

## 3. Domain architecture

### 3.1 Capability boundaries

Derived from `master-architecture-picture.md` section 7.2, now enforced structurally:

| Capability | Owns | Must not own |
|---|---|---|
| **Identity & Access** | Staff/applicant auth, permissions, office scope, proctor-assignment policy | Admissions workflow |
| **Admissions** | Application/attempt, Applicant No/Reference, acceptance, portal status | Exam attendance, scoring, admission-slip-dependent identity |
| **Examination** | Sessions, rooms, assignments, proctor duty, attendance/submission | Score interpretation |
| **Assessment** | Answer ingestion, OMR review, scores, grading lifecycle | Result communication |
| **Consultation & Release** | Summary, counselor/Test Administrator decision evidence, F2F/online consultation availability, result preparation/printing, release/unrelease, result documents | Authentication |
| **Communications** | Notification preferences, mail/database notifications, inquiry threads | Core state transitions |
| **Audit & Compliance** | Append-only event chain, checkpoints, verification | Business decisions |
| **Knowledge & RAG** | Institutional corpus, authorized own-data retrieval, citations, confidence/refusal | Placement recommendations |
| **Decision Support** | Engine registry, inference results, triage, provenance | Autonomous admission decisions |
| **Reporting & Documents** | Read models, exports, template rendering, conversion | Domain state mutation |

### 3.2 Application layer shape

```text
routes + middleware + FormRequests
         |
    Controllers (HTTP adapters only)
         |
    +-----------+----------------+
    |                            |
  Commands/Actions          Read Queries
  (write workflows)         (view models)
    |                            |
    +------- Domain Model -------+
    |   Eloquent entities, value objects,
    |   transition rules, policies
    |
  Events -> Listeners (after-commit)
    |   mail, documents, integrations
    |
  Infrastructure Adapters
    MariaDB, queue, mail, storage,
    Mixedbread, LLM, Office, Python worker
```

### 3.3 Architectural rules

1. **Controllers** are HTTP adapters: authenticate, authorize, validate shape, invoke one command or query, return response. No business logic.
2. **Commands/Actions** own workflows: validation beyond shape, transaction boundary, audit write, state transition, after-commit event dispatch.
3. **Read queries** return typed view models for pages/exports. Direct Eloquent is fine for simple reads; complex/reused/scoped reads become query objects.
4. **Models** own relationships, casts, local invariants, scopes. No orchestration, no HTTP concerns.
5. **Policies** enforce authorization by resource, state, and scope. No role lists repeated across files.
6. **Events/Listeners** handle decoupled side effects after commit. Essential invariants stay synchronous.
7. **No ceremonial wrappers.** A one-line service that delegates to Eloquent should not exist.
8. **Repositories** only for external providers or genuine test seams. Not mandatory around Eloquent.

---

## 4. Decision gates (carried forward)

These remain **unsettled** from the audit. Each must be resolved as an ADR before implementing the affected module:

1. Permanent role and permission catalog (reconcile three divergent catalogs)
2. Proctor as session-assignment duty overlay (who assigns/revokes)
3. Identifier-key authentication semantics (identifier + password, not identifier-only)
4. Google OAuth trusted-domain, account-linking, and audit policy
5. Applicant lifetime across academic cycles
6. Pipeline source of truth and allowed backtracking
7. HMAC audit threat model, canonicalization, key rotation, external checkpoints
8. OMR input constraints, confidence threshold, review workflow, batch size
9. RAG scope, confidence/citation/refusal contract, and messaging fallback
10. Program Head scope/quota definitions
11. Deployment target (Laravel Compose + Python inference worker)
12. Retention/reversal semantics for reopen, deletion, and reassignment

---

## 5. Greenfield gate sequence (summary)

> **Detailed implementation order with dependencies, audit-finding traceability, and decision-gate mapping:** see `docs/greenfield-implementation-order.md`

The build proceeds in 14 phases. Phases within the main pipeline (0→1→2→3→4→5→6) are sequential. Infrastructure phases (audit chain, notifications) run parallel to the main pipeline. Dashboards grow incrementally. Capstone research features come after the core admission lifecycle is complete.

| Phase | Name | Depends on | Decision gates needed first |
|---|---|---|---|
| 0 | Scaffold and tooling | — | — |
| 1 | Domain model foundation | 0 | 5 (applicant lifetime) |
| 2 | Identity and access | 1 | 1, 2, 3, 4 (roles, proctor, auth, OAuth) |
| 3 | Admissions core | 2 | 6, 11 (pipeline, retention) |
| 4 | Examination and proctoring | 3 | — |
| 5 | Assessment and grading | 4 | — |
| 6 | Consultation, release, documents | 5 | — |
| 7 | Audit and compliance | 1 (parallel) | 7 (threat model) |
| 8 | Communications and notifications | 2 (parallel) | — |
| 9 | Knowledge, RAG, AI companion | 2, 6 | 8 (RAG scope) |
| 10 | Capstone research features | 5, 6, 9 | 9, 10 (OMR, Program Head) |
| 11 | Dashboards and reporting | 3-6 (incremental) | — |
| 12 | Messaging and offline | 2, 8, 9 | conflict semantics |
| 13 | Hardening and delivery | all | 12 (deployment) |

Each phase in `greenfield-implementation-order.md` lists:
- exact steps and what to build
- which `master-architecture-picture.md` finding (P0.x, P1.x, §N) each step addresses
- which decision gates must be resolved before the phase starts
- what the phase unblocks
---

## 6. Schema design principles

The legacy audit identified these schema problems to avoid:

| Legacy problem | Clean-slate rule |
|---|---|
| `COUNT + 1` reference generation (race condition) | Database sequence or locked counter |
| Global unique on `applicant_id` in exam pivot (no retakes) | Scope uniqueness to admission cycle |
| `hasOne` declared but DB allows many | Enforce 1:1 with unique constraint |
| Nullable FKs with CASCADE (orphan or cascade surprise) | Explicit delete policy per relationship |
| Fresh-vs-upgraded schema drift | Single migration history, tested both fresh and upgrade |
| Plaintext tokens | Hash-only storage, atomic one-time invalidation |
| Sentinel polymorphic IDs (`auditable_id = 0`) | Nullable subject columns |
| Booking counter drift from non-atomic updates | Derived count or locked atomic update |
| State spread across base status, pipeline, pivots, grading | Single source of truth per entity state |

---

## 7. Quality contract (from day one)

1. PHPStan at maximum level. Zero errors, no baseline.
2. ESLint + Prettier zero errors on all source.
3. `svelte-check` strict mode, zero errors.
4. All new modules in TypeScript (frontend) and strictly typed PHP.
5. Every command/action has a test before merge.
6. Architecture tests enforce dependency direction (models don't use HTTP, commands don't render responses).
7. CI is all-green. No red-by-default, no "we'll fix it later" baselines.
8. Formatting is automated (pre-commit or CI).
9. `after_commit: true` on all queue connections.
10. No `app()` for mandatory dependencies — constructor injection only.

---

## 8. Relationship to audit documents

| Document | New role |
|---|---|
| `master-architecture-picture.md` | **Domain reference and requirements source.** Sections 3 (system map), 5 (findings), 7 (target architecture), 8 (capstone features), and 13 (unsettled decisions) remain authoritative for what to build. Section 1 (executive verdict) and section 9 (refactor-vs-rewrite) are superseded by this plan. |
| `greenfield-implementation-order.md` | **Concrete build sequence.** Maps every audit finding (P0.x, P1.x, §N) to the specific phase and step that addresses it. Shows dependencies between phases, decision gates that block each phase, and what each phase unblocks. |
| `audit-refactor-plan.md` | **Decision gate tracker.** Section 3 (decision gates) and section 5 (OpenSpec change names) remain valid. Gate sequence is superseded by this plan's greenfield gates. |
| `audit-controllers.md` | **Reference for controller responsibilities and workflow mapping.** Not a refactoring target. |
| `access-control-inventory.md` | **Reference for role/permission requirements.** The three-way divergence must still be reconciled. |
| `architecture-audit.md` | **Historical reconnaissance.** Corrections in the master blueprint take precedence. |
| `nexiam-architecture-learning-guide.md` | **Study material for domain understanding.** Architecture patterns still apply to greenfield. |

---

## 9. What this plan does NOT do

- It does not port legacy code. Every module is written fresh.
- It does not carry over migration history. The schema is designed from requirements.
- It does not assume legacy behavior is correct. Each workflow is specified from the domain.
- It does not skip the decision gates. Unsettled questions block the affected module, not the whole project.
- It does not change the product name. This is still NEXIAM.
- It does not discard the audit. The audit is the specification source for the rebuild.
