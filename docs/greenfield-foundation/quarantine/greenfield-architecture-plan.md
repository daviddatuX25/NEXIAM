greenfield-architecture-plan.md 224L
# NEXIAM v2 Greenfield Architecture Plan
**Status:** HISTORICAL INPUT — superseded as implementation and sequence authority by the master delivery roadmap `docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md` (GF-14) and companions GF-00 through GF-24
... [lean-ctx: omitted 2 lines]
**Supersedes:** strangler-style modernization recommended in `master-architecture-picture.md` section 1
... [lean-ctx: omitted 1 lines]
## 1. Strategic pivot
The legacy NEXIAM codebase (Laravel 12 / Inertia 2 / Svelte 5) will **not** be refactored in place. The project restarts as a clean-slate build.
... [lean-ctx: omitted 3 lines]
| Database | Preserve 86 legacy migrations | Clean schema designed from settled domain models |
... [lean-ctx: omitted 1 lines]
| Quality gate | No-growth ledgers against inherited debt | **All-green CI; no baselines, no suppressions** |
| Migration path | Strangler-style incremental replacement | Feature-by-feature rebuild with clean boundaries |
### Why
The audit proved the legacy code has:
- fragmented workflow ownership across 41 controllers, 35 services, and model hooks;
... [lean-ctx: omitted 3 lines]
- plaintext tokens, unescaped template injection, vulnerable dependencies;
... [lean-ctx: omitted 1 lines]
Strangler-style refactoring would require characterizing every legacy behavior before touching it, carrying all inherited debt as baselines, and preserving schema drift. A clean build uses the audit as a specification and sidesteps the characterization burden entirely.
### What the legacy codebase gives us
It is a **reference specification**, not a codebase to port. We extract:
1. **Domain workflows and invariants** (admission lifecycle, exam/proctor flows, grading/release, document generation)
... [lean-ctx: omitted 2 lines]
4. **Integration contracts** (Mixedbread RAG, OpenRouter LLM, DOCX/PDF rendering, spreadsheet I/O, OMR)
... [lean-ctx: omitted 1 lines]
6. **Business rules** (reference number generation, booking constraints, grading finalization, release semantics)
We do **not** carry over: code structure, migration history, PHPStan baselines, suppress lists, dead code, or architectural mistakes.
... [lean-ctx: omitted 1 lines]
## 2. Technology decisions
Retain the same stack — the audit found no reason to change it:
... [lean-ctx: omitted 5 lines]
| Database | PostgreSQL (production/integration), SQLite only for parity-safe tests | One fresh PostgreSQL migration history; no legacy schema drift |
| Queue | Laravel queues | `after_commit: true` default |
| Inference | Python sidecar/worker | OMR (OpenCV), embeddings (BGE), aptitude (Yukee) |
| Search/RAG | Mixedbread embeddings | Scoped retrieval with confidence/citation contract |
... [lean-ctx: omitted 1 lines]
## 3. Domain architecture
### 3.1 Capability boundaries
Derived from `master-architecture-picture.md` section 7.2, now enforced structurally:
... [lean-ctx: omitted 3 lines]
| **Admissions** | Application/attempt, Applicant No/Reference, acceptance, portal status | Exam attendance, scoring, admission-slip-dependent identity |
... [lean-ctx: omitted 2 lines]
| **Consultation & Release** | Summary, counselor/Test Administrator decision evidence, F2F/online consultation availability, result preparation/printing, release/unrelease, result documents | Authentication |
| **Communications** | Notification preferences, mail/database notifications, inquiry threads | Core state transitions |
... [lean-ctx: omitted 1 lines]
| **Knowledge & RAG** | Institutional corpus, authorized own-data retrieval, citations, confidence/refusal | Placement recommendations |
| **Decision Support** | Engine registry, inference results, triage, provenance | Autonomous admission decisions |
... [lean-ctx: omitted 1 lines]
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
... [lean-ctx: omitted 3 lines]
6. **Events/Listeners** handle decoupled side effects after commit. Essential invariants stay synchronous.
7. **No ceremonial wrappers.** A one-line service that delegates to Eloquent should not exist.
... [lean-ctx: omitted 2 lines]
## 4. Decision gates (carried forward)
These remain **unsettled** from the audit. Each must be resolved as an ADR before implementing the affected module:
1. Permanent role and permission catalog (reconcile three divergent catalogs)
... [lean-ctx: omitted 1 lines]
3. Identifier-key authentication semantics (identifier + password, not identifier-only)
... [lean-ctx: omitted 3 lines]
7. HMAC audit threat model, canonicalization, key rotation, external checkpoints
... [lean-ctx: omitted 1 lines]
9. RAG scope, confidence/citation/refusal contract, and messaging fallback
... [lean-ctx: omitted 2 lines]
12. Retention/reversal semantics for reopen, deletion, and reassignment
... [lean-ctx: omitted 1 lines]
## 5. Greenfield gate sequence (summary)
> **Detailed implementation order with dependencies, audit-finding traceability, and decision-gate mapping:** see `docs/greenfield-implementation-order.md`
The build proceeds in 14 phases. Phases within the main pipeline (0→1→2→3→4→5→6) are sequential. Infrastructure phases (audit chain, notifications) run parallel to the main pipeline. Dashboards grow incrementally. Capstone research features come after the core admission lifecycle is complete.
... [lean-ctx: omitted 3 lines]
| 1 | Domain model foundation | 0 | 5 (applicant lifetime) |
... [lean-ctx: omitted 6 lines]
| 8 | Communications and notifications | 2 (parallel) | — |
... [lean-ctx: omitted 2 lines]
| 11 | Dashboards and reporting | 3-6 (incremental) | — |
| 12 | Messaging and offline | 2, 8, 9 | conflict semantics |
| 13 | Hardening and delivery | all | 12 (deployment) |
Each phase in `greenfield-implementation-order.md` lists:
... [lean-ctx: omitted 1 lines]
- which `master-architecture-picture.md` finding (P0.x, P1.x, §N) each step addresses
... [lean-ctx: omitted 3 lines]
## 6. Schema design principles
The legacy audit identified these schema problems to avoid:
... [lean-ctx: omitted 2 lines]
| `COUNT + 1` reference generation (race condition) | Database sequence or locked counter |
| Global unique on `applicant_id` in exam pivot (no retakes) | Scope uniqueness to admission cycle |
| `hasOne` declared but DB allows many | Enforce 1:1 with unique constraint |
... [lean-ctx: omitted 2 lines]
| Plaintext tokens | Hash-only storage, atomic one-time invalidation |
| Sentinel polymorphic IDs (`auditable_id = 0`) | Nullable subject columns |
... [lean-ctx: omitted 3 lines]
## 7. Quality contract (from day one)
1. PHPStan at maximum level. Zero errors, no baseline.
... [lean-ctx: omitted 1 lines]
3. `svelte-check` strict mode, zero errors.
... [lean-ctx: omitted 2 lines]
6. Architecture tests enforce dependency direction (models don't use HTTP, commands don't render responses).
... [lean-ctx: omitted 2 lines]
9. `after_commit: true` on all queue connections.
10. No `app()` for mandatory dependencies — constructor injection only.
... [lean-ctx: omitted 1 lines]
## 8. Relationship to audit documents
| Document | New role |
... [lean-ctx: omitted 1 lines]
| `master-architecture-picture.md` | **Domain reference and requirements source.** Sections 3 (system map), 5 (findings), 7 (target architecture), 8 (capstone features), and 13 (unsettled decisions) remain authoritative for what to build. Section 1 (executive verdict) and section 9 (refactor-vs-rewrite) are superseded by this plan. |
| `greenfield-implementation-order.md` | **Concrete build sequence.** Maps every audit finding (P0.x, P1.x, §N) to the specific phase and step that addresses it. Shows dependencies between phases, decision gates that block each phase, and what each phase unblocks. |
... [lean-ctx: omitted 2 lines]
| `access-control-inventory.md` | **Reference for role/permission requirements.** The three-way divergence must still be reconciled. |
| `architecture-audit.md` | **Historical reconnaissance.** Corrections in the master blueprint take precedence. |
... [lean-ctx: omitted 2 lines]
## 9. What this plan does NOT do
- It does not port legacy code. Every module is written fresh.
- It does not carry over migration history. The schema is designed from requirements.
- It does not assume legacy behavior is correct. Each workflow is specified from the domain.
- It does not skip the decision gates. Unsettled questions block the affected module, not the whole project.
... [lean-ctx: omitted 2 lines]


[lean-ctx] full source: read "/home/user/Projects/NEXIAM/docs/greenfield-architecture-plan.md" directly (no MCP)  ·  or ctx_read("/home/user/Projects/NEXIAM/docs/greenfield-architecture-plan.md", mode="full")
