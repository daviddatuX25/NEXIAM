---
name: NEXIAM AI Assistant Foundation — Visual Guide
type: architecture-rendering
status: final
companion: ARCHITECTURE-SPINE.md
audience: founder and implementation team
---

# NEXIAM AI Assistant Foundation — Visual Guide

This is the readable picture of the architecture. It does not introduce a second design; it renders the decisions in [ARCHITECTURE-SPINE.md](./ARCHITECTURE-SPINE.md).

## The one-sentence model

**Nexi is another front door to the same application, not another application.** The page UI and Nexi both call one capability kernel, which calls the existing domain actions and queries. The model may suggest, read through bounded capabilities, and prepare a proposal; it is never the authority that writes to the database.

## 1. The whole system

```mermaid
flowchart LR
    U[User]

    subgraph HUMAN[Human interface]
        P[Current page]
        PC[Page controller / Inertia action]
        N[Nexi dock]
        NT[Generic conversation transport]
    end

    subgraph APP[Trusted application boundary]
        PP[Server page-pack resolver]
        EP[Effective policy<br/>mode + actor + scope + quota]
        O[Bounded orchestrator]
        K[Capability kernel]
        R[Capability registry]
        Q[Authorized queries + projections]
        A[Existing domain Actions]
        C[Confirmation endpoint]
        AU[Audit + usage + outbox]
        DB[(Domain database)]
    end

    subgraph AI[Untrusted intelligence boundary]
        G[AiGateway adapter]
        L[Laravel AI SDK candidate]
        V[Provider]
    end

    subgraph KNOW[Separate knowledge path]
        CORPUS[Approved policy corpus]
        RET[Filtered retrieval + citations]
    end

    U --> P
    U --> N
    P --> PC --> K
    N --> NT --> PP --> EP --> O
    O --> G --> L --> V
    O --> R
    O --> RET
    RET --> CORPUS
    O --> K
    K --> R
    K --> Q --> DB
    K --> A --> DB
    K --> C
    C --> A
    K --> AU
    A --> AU
    Q --> AU

    classDef untrusted fill:#fff1f2,stroke:#be123c,color:#881337;
    classDef trusted fill:#ecfdf5,stroke:#047857,color:#064e3b;
    class V,L,G untrusted;
    class K,R,Q,A,C,AU,DB,EP,PP,O trusted;
```

### What this prevents

| Tempting design | Actual design |
|---|---|
| Give the model raw HTTP or database access | Give it only registry entries with strict schemas |
| Add a second set of `ai/*` business routes | Use one generic Nexi transport and the same domain handlers |
| Let a provider execute a tool directly | Provider output becomes a validated read or a persisted proposal |
| Put page permissions in JavaScript context | Resolve actor, scope, role, mode, and lifecycle on the server |
| Let a prompt sanitizer decide security | Enforce projection, authorization, schema, policy, and audit at boundaries |

## 2. The shared-kernel rule

There are many normal page routes. There is one generic Nexi transport. Neither is allowed to invent business behavior.

```mermaid
flowchart TB
    subgraph IN[Inbound adapters]
        H[Human page request<br/>GET/POST/Inertia]
        X[Nexi request<br/>ask / retrieve / propose / confirm]
    end

    subgraph CORE[One application core]
        K[CapabilityKernel]
        Q[Query handlers]
        A[Action handlers]
        B[Batch command handlers]
    end

    H --> K
    X --> K
    K --> Q
    K --> A
    K --> B

    Q --> D[(Domain state)]
    A --> D
    B --> D
    A --> LOG[Audit / events]
    B --> LOG
```

For example, the page button **Publish schedule** and Nexi's confirmed proposal both end in the same `PublishExamSessionSchedule` action. The transport that brought the request in does not change the authorization, transaction, audit, or idempotency rules.

## 3. The nouns that keep the system scalable

```mermaid
flowchart LR
    Route[Route identity] --> Pack[Page pack]
    Pack --> Scope[Relevant scope + projections]
    Pack --> Keys[Allowed registry keys]
    Keys --> Cap[Capabilities]
    Cap --> Auth[Actor + purpose authorization]
    Auth --> Skill[Finite-state skill]
    Skill --> Proposal[Exact proposal]
    Proposal --> Confirm[Explicit confirmation]
    Confirm --> Action[Existing command/action]
```

| Noun | Plain-language meaning | It is not |
|---|---|---|
| **Page pack** | Server-owned description of the current route: modes, projections, skills, capability keys, privacy class, and declarative UI intents | Permission by itself |
| **Capability** | One versioned operation with a strict input/output schema, scope resolver, risk, confirmation rule, and handler | A raw route or arbitrary code execution |
| **Projection** | The minimum, typed, privacy-reviewed view of records the model may see | A model-readable database dump |
| **Skill** | A bounded workflow state machine composed from capabilities | A free-running prompt or autonomous agent |
| **Proposal** | Persisted exact intended change, with canonical JSON and a hash | An instruction hidden in chat text |
| **Task grant** | Short-lived, revocable authority for a cross-page skill | A copy of the user's permanent permissions |
| **Provider** | An untrusted suggestion engine | A trusted actor or transaction manager |

## 4. What a page publishes

The browser can hint at the current route, but the server resolves the authoritative pack from the Laravel route name and current context epoch.

```mermaid
flowchart LR
    Route["exams.sessions.show"] --> Resolver["Server resolver"]
    Resolver --> Pack["exams.sessions.show v1"]
    Pack --> Modes[Inquiry by default<br/>Assistant only when enabled]
    Pack --> Proj[Session + roster projections]
    Pack --> Caps[Read / propose capability keys]
    Pack --> Skills[Scheduling skills]
    Pack --> UI[Typed intents:<br/>navigate, refresh, focus, show proposal]
    Pack --> Privacy[PII class + purpose]
```

Example scheduling pack:

| Pack field | Example |
|---|---|
| Route identity | `exams.sessions.show` |
| Context epoch | `institution=7; duty=scheduler; session=42; pack=v1` |
| Default mode | `Inquiry` |
| Assistant gate | Explicit user consent + actor capability |
| Allowed projections | Session dates, capacity, assignment status, permitted display names |
| Read keys | `exam_session.get`, `exam_roster.summary` |
| Proposal keys | `exam_schedule.propose_assignments`, `exam_session.propose_open` |
| Command keys | Exposed only through the confirmation endpoint |
| UI intents | Refresh roster, focus unassigned list, show proposal |

The pack narrows relevance. The effective policy still performs the deny-dominant intersection of actor, role, scope, mode, lifecycle, purpose, feature flag, quota, provider health, and emergency controls.

## 5. How a capability is shaped

```mermaid
flowchart TB
    R[Registry entry]
    R --> V[key + version + contract version]
    R --> S[Strict JSON Schema<br/>supported subset]
    R --> Z[Scope resolver]
    R --> P[Projection / privacy rules]
    R --> T[Risk + confirmation policy]
    R --> I[Idempotency policy]
    R --> RS[RuleSetRef + preflight]
    R --> E[ConstraintEvidence schema]
    R --> H[Existing handler]

    V --> Gate[Kernel gate]
    S --> Gate
    Z --> Gate
    P --> Gate
    T --> Gate
    I --> Gate
    RS --> Gate
    E --> Gate
    H --> Gate
    Gate --> Result[Typed result + audit record]
```

Only `read` and `propose` entries are offered to the provider. A mutation is not a provider tool call: it is a proposal saved by the server, then a separate confirmation request invokes the ordinary action.

## 5A. The single source of truth for proposal validity

The system prompt should teach the model the current constraints, but it must never be the place where the constraints live. The model receives a generated rule digest; the server proves validity with the domain preflight.

```mermaid
flowchart LR
    Rule["Domain rule or Action"] --> Check["Canonical preflight"]
    Check --> Contract["Capability contract + RuleSetRef"]
    Contract --> Prompt["Generated rule digest"]
    Prompt --> Model["Structured candidate"]
    Model --> Check
    Check --> Evidence["ConstraintEvidence"]
    Evidence --> Proposal["Exact proposal"]
    Proposal --> Confirm["Fresh confirmation check"]
    Confirm --> Commit["Existing command or batch Action"]
    Commit --> State["Domain state + audit"]
```

| Layer | Role |
|---|---|
| Domain rules, Actions, Queries | Canonical business truth |
| Preflight evaluator | Reusable deterministic feasibility and constraint check |
| Capability Registry | Versioned schema, scope, handler, `RuleSetRef`, and preflight reference |
| Prompt/rule digest | Concise guidance generated from the canonical rule set |
| LLM output | Candidate arguments/preferences, always untrusted |
| Proposal | Exact normalized snapshot plus rule/target evidence |
| Confirmation | Re-runs authorization, rule preflight, versions, and idempotency before the ordinary command |

### If a business rule changes

Update the domain rule and tests first. Then publish a new `RuleSetRef`, rebuild capability descriptors/page packs/skills/prompt digests, segment affected context epochs, and invalidate incompatible proposals or task grants. Keep the capability version when its contract and semantics remain compatible; bump it when they do not. No prompt edit alone can change what the application accepts.

```mermaid
flowchart LR
    Bundle["Immutable RulePublication bundle"] --> Active["Atomic active pointer"]
    Active --> Run["Runs and workers record publication"]
    Deps["Reverse dependency index"] --> Invalidate["Invalidate proposals, grants, epochs, caches"]
    Active --> Deps
    Invalidate --> Reconcile["Idempotent outbox + reconciliation"]
    Run --> Check["Kernel rejects stale dependency"]
```

## 6. Scheduling: request to safe change

This is the concrete flow for “find feasible assignments and open the selected sessions.” Bulk work is one purpose-built batch command, not a sequence of hidden page hops.

```mermaid
sequenceDiagram
    actor User
    participant Page as Current page
    participant Dock as Nexi dock
    participant Pack as Page-pack resolver
    participant Orch as Bounded orchestrator
    participant Kernel as Capability kernel
    participant DB as Domain state
    participant UI as Proposal UI

    User->>Page: Opens exam session
    Page->>Pack: Resolve route + actor + scope
    Pack-->>Dock: Context epoch + effective UI state
    User->>Dock: "Allocate accepted applicants"
    Dock->>Orch: Ask in Assistant mode
    Orch->>Kernel: Read roster/session projections
    Kernel->>DB: Authorized deterministic queries
    DB-->>Kernel: Minimal records + versions
    Kernel-->>Orch: Projection with evidence
    Orch->>Orch: Apply bounded scheduling skill
    Orch->>Kernel: Propose exact assignments
    Kernel->>DB: Persist proposal + canonical hash
    Kernel-->>UI: Proposal card with impact, conflicts, expiry
    UI-->>User: Confirm / cancel
    User->>Dock: Confirm
    Dock->>Kernel: Generic confirmation endpoint
    Kernel->>Kernel: Fresh auth, scope, versions, idempotency
    Kernel->>DB: Execute one transaction/batch command
    Kernel-->>UI: Recorded result + audit reference
    UI->>Page: Inertia refresh / typed refresh intent
```

### The important stop points

1. **Inquiry cannot mutate.** It can answer from approved policy or authorized reads.
2. **Assistant requires explicit consent.** A countdown or automatic mode switch is not consent.
3. **The proposal is exact.** It contains target IDs, intended values, scope, expiry, versions, and a canonical hash.
4. **Confirmation is fresh.** The server rechecks authorization and optimistic-concurrency versions.
5. **The normal action runs.** Audit, idempotency, events, and transactions are unchanged.

## 7. Modes are a safety state machine

```mermaid
stateDiagram-v2
    [*] --> Hidden
    Hidden --> Inquiry: feature + page available
    Inquiry --> Assistant: explicit user consent
    Assistant --> Inquiry: user switches / context changes
    Assistant --> AwaitingConfirmation: proposal created
    AwaitingConfirmation --> Executing: explicit confirmation
    AwaitingConfirmation --> Assistant: cancel / expire
    Executing --> Complete: action succeeds
    Executing --> Assistant: conflict / denial / failure
    Inquiry --> Degraded: provider unavailable
    Assistant --> Degraded: provider unavailable
    Degraded --> Inquiry: provider recovers
    Assistant --> Inquiry: page, scope, duty, purpose, or privacy epoch changes
```

The context epoch is a hard boundary. When the page or authorized scope changes, the server segments or rebuilds provider context; it never blindly replays the old transcript under the new authority.

## 8. The Nexi UI at a glance

The dock is mounted in the authenticated shell, but the server continually supplies the effective state for the current page.

```text
┌──────────────────────────────────────────────────────────────┐
│ Current page: Exam session 42     Scope: Institution 7        │
│ ┌───────────────┐  ┌────────────────────────────────────────┐ │
│ │ Inquiry  ●    │  │ Nexi                                    │ │
│ │ Assistant ○   │  │ “I can answer policy questions here.    │ │
│ └───────────────┘  │ Assistant actions are available for     │ │
│                    │ this session after you enable them.”   │ │
│                    │                                          │ │
│                    │ [Citation card] [Read result]            │ │
│                    │                                          │ │
│                    │ [Proposal: 12 assignments]               │ │
│                    │ [Review details] [Confirm] [Cancel]       │ │
│                    │                                          │ │
│                    │ Usage today: 18 / 100     [Clear thread] │ │
│                    └────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

The dock may render only declarative, typed intents returned by the server: `navigate`, `refresh`, `focus`, `open_panel`, `show_proposal`, and `show_citations`. The provider cannot author DOM selectors, raw HTML, arbitrary URLs, or JavaScript.

| UI state | User experience |
|---|---|
| Hidden | No Nexi surface for this route or actor |
| Inquiry-only | Questions, policy citations, authorized read answers; no Apply controls |
| Assistant-ready | Explicit mode control and page-specific skills |
| Awaiting confirmation | Exact proposal, impact, conflicts, expiry, confirm/cancel |
| Degraded | Explain that live assistance is unavailable; retain safe fallback/manual UI |
| Quota suspended | Show limit and reset/admin contact; do not silently downgrade a denied capability |

## 9. Read, propose, command

```mermaid
flowchart LR
    Ask[User request]
    Ask --> Read{Needs information?}
    Read -->|yes| ReadCap[Authorized read capability]
    ReadCap --> Evidence[Projection + evidence]
    Evidence --> Decide{Would state change?}
    Decide -->|no| Answer[Answer / citation]
    Decide -->|yes| Propose[Proposal capability]
    Propose --> Review[Human review]
    Review --> Confirm{Explicit confirm?}
    Confirm -->|no| Cancel[Cancel / expire]
    Confirm -->|yes| Command[Confirmation endpoint]
    Command --> Action[Existing action or batch command]
```

| Operation kind | Can it happen automatically? | Example |
|---|---:|---|
| Read | Yes, if authorized and bounded | List unassigned applicants |
| Propose | Yes, but it only persists an exact proposal | Suggest assignments that satisfy constraints |
| Command | No; requires explicit confirmation and fresh checks | Publish schedule, open reservations |

## 10. Data and privacy path

```mermaid
flowchart LR
    Docs[Approved institutional policy docs] --> Index[Versioned policy index]
    Index --> Ret[Auth + audience + effective-date + revocation filters]
    Ret --> Cit[Cited inquiry answer]

    Ops[(Operational database)] --> Query[Deterministic authorized query]
    Query --> Proj[PII-minimized projection]
    Proj --> Context[Assistant context]

    Raw[(Raw operational records)] -. never by default .-> Index
    Context --> Provider[Untrusted provider]
```

The system does not rely on one magical prompt-sanitization package. Safety is layered:

1. authorize before retrieval;
2. project and classify fields before prompt construction;
3. separate instructions, data, and retrieved documents;
4. use strict schemas and reject invalid output;
5. escape and allowlist links/UI intents;
6. record a hash-linked privacy decision and audit trail.

Operational own-data and staff-work data use deterministic, scoped queries. RAG is initially for the approved institutional policy corpus, with exact source citations and refusal when evidence is missing or stale.

### RAG status and one knowledge-source registry

RAG is designed but not active yet. The future registry is the only door into knowledge retrieval.

```mermaid
flowchart TB
    Source["Candidate source"] --> Registry["Knowledge-source registry"]
    Registry --> Router["KnowledgeRouter + source decision"]
    Router --> Policy["Approved policy or terms"]
    Router --> Own["Released own-data"]
    Router --> Ops["Operational record"]
    Policy --> Index["Versioned policy index + citations"]
    Own --> Query["Authorized deterministic Query"]
    Ops --> Query
    Index --> Inquiry["Inquiry answer or refusal"]
    Query --> Assistant["Scoped summary or proposal input"]
```

Institution-approved terms and conditions can be policy-corpus sources. Schedules, rosters, applicants, and other operational rows remain Query/projection data even when visible on a page; they are not embedded by convenience. Every source carries owner, audience, effective/revoked version, checksum, citation identity, and a declared retrieval route.

Revocation is immediate at the registry boundary: cached snapshots, context epochs, in-flight/replayable citations, and answer artifacts become unusable before asynchronous deletion or re-indexing completes. Reconnect and later conversation turns re-check source eligibility.

## 11. Skills are bounded workflows, not free autonomy

```mermaid
flowchart LR
    Start[Skill start] --> S1[Read constraints]
    S1 --> S2[Compute candidate plan]
    S2 --> S3[Check conflicts]
    S3 --> Gate{Safe next state?}
    Gate -->|no| Ask[Ask user / refuse]
    Gate -->|yes| S4[Persist checkpoint]
    S4 --> S5[Create proposal]
    S5 --> Confirm[Human confirmation]
    Confirm --> Done[Run bounded command]
```

Every skill has a server-owned finite state machine, explicit legal transitions, maximum steps, token/time/cost budgets, bounded retries and fan-out, checkpointing, and a clear refusal/escalation path. The model can recommend the next transition; only the server advances the state.

For cross-page work, the skill receives a short-lived, revocable `TaskGrant` describing the additional purpose and scopes. The grant expires or is revoked when the task ends, the user cancels, or the context changes.

## 12. Usage limits and administration

```mermaid
flowchart TB
    Request[Every Nexi operation]
    Request --> Kill[Emergency kill switch]
    Kill --> Feature[Feature flag / lifecycle]
    Feature --> Mode[Mode + page-pack gate]
    Mode --> Actor[Actor capability + scope]
    Actor --> Purpose[Purpose + privacy class]
    Purpose --> Rate[Burst rate limit]
    Rate --> Budget[Durable usage ledger + quota]
    Budget --> Health[Provider health / degraded state]
    Health --> Dispatch[Dispatch or deny]
```

Administration can disable a route, mode, skill, provider, or capability and can set quotas or narrow an existing ceiling. An override may never grant a new capability or widen actor scope. Denial wins if any gate says no.

The durable ledger records asks, input/output tokens, tool steps, estimated cost in integer micro-units, actor, institution, routing profile, pricing version, and accounting timezone. A fast limiter handles bursts; the ledger handles durable quota and reporting.

## 13. Shared component, separate trust surfaces

```mermaid
flowchart TB
    Browser["Authenticated browser"] --> Shell["Shared Nexi presentation"]
    Shell --> Resolver["Server surface resolver"]
    Resolver --> Staff["Staff profile"]
    Resolver --> Applicant["Applicant profile"]
    Resolver --> Proctor["Proctor session profile"]
    Resolver --> KnowledgeAdmin["Knowledge admin profile"]
    Resolver --> Operations["Operations profile"]
    Staff --> Packs["Compatible page packs and skills"]
    Applicant --> Packs
    Proctor --> Packs
    KnowledgeAdmin --> Packs
    Operations --> Packs
    Packs --> Policy["Effective policy intersection"]
    Policy --> Kernel["Capability Kernel"]
```

The dock can look the same across shells, but the resolved `SurfaceProfile` partitions prompts, disclosures, history, retention, page packs, context projections, skills, consent, and TaskGrants. Applicant context cannot fall through to staff behavior; unknown or conflicting surfaces fail closed.

## 14. Knowledge administration before RAG

```mermaid
flowchart LR
    Draft["Draft source version"] --> Extract["Extract and classify"]
    Extract --> Review["Owner review"]
    Review --> Approve["Independent approval"]
    Approve --> Index["Index and citation tests"]
    Index --> Active["Active effective version"]
    Active --> Supersede["Superseded"]
    Active --> Revoke["Revoked immediately"]
    Supersede --> Cleanup["Reindex and reconcile"]
    Revoke --> Cleanup
    Cleanup --> Retain["Retention or deletion evidence"]
```

Knowledge CRUD is a publication workflow, not a file manager. Unreviewed drafts never enter retrieval. Revocation blocks retrieval synchronously, while cache/index deletion and retention reconciliation finish asynchronously with visible status.

Structured system records follow another path:

```mermaid
flowchart TB
    Question["Nexi information need"] --> Router["KnowledgeRouter"]
    Router --> Document["Approved policy document"]
    Router --> OwnData["Released applicant own data"]
    Router --> StaffData["Scoped staff operational data"]
    Document --> Rag["Index retrieval with citations"]
    OwnData --> Query["Authorized deterministic Query"]
    StaffData --> Query
    Rag --> Firewall["Prompt and data firewall"]
    Query --> Firewall
```

## 15. Frontend session and recovery loop

```mermaid
sequenceDiagram
    participant Page as Svelte shell
    participant Server as Nexi transport
    participant Policy as Surface and policy resolver
    participant Worker as Queued Run worker
    participant Gateway as AI gateway and output firewall
    participant Events as Postgres event store

    Page->>Server: Request session snapshot
    Server->>Policy: Resolve surface, pack, epoch, modes, usage
    Policy-->>Server: NexiSessionView
    Server-->>Page: Authoritative snapshot and cursor
    Page->>Server: Submit inquiry or assistant turn
    Server->>Events: Persist Run and enqueue job
    Worker->>Gateway: Execute bounded provider request
    Gateway-->>Worker: Validated chunks and terminal result
    Worker->>Events: Append sequenced application events
    Page->>Server: Open SSE with cursor
    Server->>Events: Reauthorize and read events
    Server-->>Page: Replay events or fresh snapshot
    Note over Gateway,Page: Raw provider deltas never reach the browser
```

The model may emit a request to use Assistant, but the server returns an application-owned consent event. The user must choose; the client cannot turn a countdown, saved preference, or model message into consent.

## 16. Build order

```mermaid
flowchart LR
    P0["Reconcile planning authority"] --> P1["Shared degraded shells"]
    P1 --> P2["Surface profiles and page packs"]
    P2 --> P3["Conversation usage and recovery"]
    P3 --> P4["Knowledge governance without live RAG"]
    P4 --> P5["Provider spike on synthetic data"]
    P5 --> P6["Policy Inquiry with citations"]
    P6 --> P7["Applicant Inquiry"]
    P7 --> P8["Exam Assistant pilot"]
    P8 --> P9["Cross domain skills"]
```

### Gates before implementation

- Archive `exam-session-scheduling` and open `ai-assistant-foundation`.
- Reconcile promoted React references with the selected executable Inertia 3 + Svelte 5 stack.
- Approve the page × mode matrix and the first typed capability catalog.
- Decide the Laravel AI adapter/provider through an ADR; do not install it as a design shortcut.
- Prove fake-provider tests, context-epoch isolation, deny-dominant policy, idempotent confirmation, and degraded UI first.
- Build knowledge governance before claiming RAG, then promote policy Inquiry and applicant Inquiry separately.
- Start Assistant execution with one vertical slice: exam-session reads and two or three proposals.

## Related documents

- [Architecture spine](./ARCHITECTURE-SPINE.md) — normative invariants and contracts.
- [Readiness plan](./READINESS-PLAN.md) — research, ADR, OpenSpec, and rollout gates.
- [Junior implementation guide](./JUNIOR-IMPLEMENTATION-GUIDE.md) — complete surfaces, frontend contract, route matrix, capability/skill catalog, knowledge CRUD, applicant plan, and delivery slices.
- [Divergence review](./reviews/review-divergence-adversary.md) — integration/adversarial review.
- [Rubric/security review](./reviews/review-rubric-security.md) — security and acceptance review.
- [Technology review](./reviews/review-technology-reality.md) — Laravel AI candidate and verification notes.
- [Standards research](./STANDARDS-RESEARCH.md) — JSON Schema, RFC 8785, OWASP, NIST, Laravel AI, MCP, and remaining research gates.

No provider package was installed and no production route or domain code was changed by creating this visual guide.
