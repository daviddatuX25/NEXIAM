# Design: AI Capability Kernel

## 1. Goal

One dispatch path for authorized capability invocation. Controllers stop calling domain queries as a parallel authority for the reference readiness read.

## 2. Flow

```text
HTTP controller (or future Nexi tool call)
  → CapabilityKernel::invoke(CapabilityInvocationRequest)
  → resolve trusted principal + provenance (actor, session, institution, surface, correlation)
  → resolve SurfaceProfile / mode / page_pack (hints only; pack never grants)
  → validate input against registry JSON Schema subset (reject unknown fields)
  → CapabilityAuthorizer / GF-21 + registry capability key
  → target/scope resolution (institution; readiness is institution-wide catalog read)
  → handler → ComputeSetupReadinessQuery::handle()
  → channel projection (http_page vs nexi_provider_safe — both read-only; provider projection unused until later)
  → typed CapabilityResult + invocation evidence stub
```

## 3. Registry entry (reference)

| Field | Value |
|---|---|
| key@version | `setup.readiness.read@1` |
| kind | `read` |
| handler | wraps `ComputeSetupReadinessQuery` |
| application capability | existing setup / readiness capability used by Setup hub (do not invent a parallel Spatie-only gate) |
| confirmation | none (read) |
| idempotency | not required for pure read |
| input schema | empty object or optional `{ "as_of"?: null }` — no client authority fields |
| result schema | mirrors current readiness array shape (`overall_percent`, `passed`, `total`, `categories`) |

## 4. Ownership

```text
system/app/Application/Capabilities/   # definitions, registry, kernel, schemas, results
system/app/Ai/Policy/                  # may consult effective availability; Kernel remains authz owner
system/app/Setup/Queries/              # ComputeSetupReadinessQuery stays domain-owned
system/app/Http/Controllers/Setup/     # migrate to Kernel invoke for readiness prop
```

Do **not** add `Ai/Adapters/LaravelAi`, conversation migrations, SSE, or propose/command registry entries in this change.

## 5. Pack vs cage (mandatory for this cut)

Inherited from AD-3 founder clarification 2026-08-10:

| Concern | Rule |
|---|---|
| Dock without pack | Still `degraded` / later Inquiry — Kernel does not change OpenSpec 1 availability semantics |
| Who may call a tool | Registry ∩ GF-21 ∩ surface/mode — **not** “tool listed on current page pack” |
| Pack role | Relevance: suggestions, page identity, future skill start — may *advertise* `setup.readiness.read@1` on setup pack |
| Fail closed | No prior-pack inheritance; deny unauthorized invoke; never use pack absence to invent deny for an otherwise authorized actor on a mountable surface |
| Cross-page tools | Out of scope here beyond actor ceiling; TaskGrants = OpenSpec 3 |
| History / confirm modals / live LLM | Still later OpenSpecs |

## 6. RulePublication / preflight seam

For **read** capabilities in this cut:

- Each registry entry may reference an optional `RuleDependencySet` (empty or readiness-catalog refs)
- Preflight for reads = schema + authz + surface allow; returns `ConstraintEvidence` pass
- `RulePublication` compatibility: Kernel rejects invoke if active publication pointer missing/mixed **only when** the entry declares required publication binding; readiness `@1` may bind to a minimal code-owned publication fixture so the seam exists without blocking setup

Do not implement mutation preflight/confirm drift invalidation beyond stubs.

## 7. Parity proof

1. Capture readiness payload shape from today’s SetupController (golden fixture)
2. Route SetupController through Kernel
3. Pest: same structure for authorized staff; deny for applicant and staff lacking setup capability
4. Optional: internal Kernel invoke from a Pest-only Nexi-shaped caller proves identical authoritative result (no UI chat required)

## 8. Explicit non-goals

- No provider tools list exposed to a model
- No `propose` / `command` kinds registered
- No durable run/usage/conversation
- No changing PageCapabilityPack into an allowlist cage
