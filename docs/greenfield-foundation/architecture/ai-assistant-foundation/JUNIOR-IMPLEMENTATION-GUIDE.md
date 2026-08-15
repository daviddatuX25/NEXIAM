---
title: Nexi AI Assistant Foundation — Junior Implementation Guide
status: planning-ready
date: 2026-08-10
authority: ARCHITECTURE-SPINE.md
implementation_gate: dedicated E3 OpenSpec required
---

# Nexi AI Assistant Foundation — Junior Implementation Guide

This is the implementation bridge between the architecture spine and future stories/OpenSpecs. It covers staff, applicant, proctor/session, knowledge-administration, and operations use cases. It does **not** authorize provider installation or production code by itself.

## 1. The implementation model in one minute

```text
Authenticated shell
  → server resolves SurfaceProfile
  → matched route resolves PageCapabilityPack
  → EffectivePolicyResolver intersects actor, scope, mode, privacy, health, and budget
  → NexiSessionView tells the frontend what it may render
  → Inquiry can answer through approved knowledge or deterministic Queries
  → Assistant can run bounded reads and build proposals
  → human confirms a persisted proposal
  → CapabilityKernel dispatches the ordinary Action
  → domain rules, transaction, audit, and outbox remain authoritative
```

There are no duplicate “AI business routes.” Nexi has a small conversation transport, while domain work continues through the same Actions and Queries used by normal pages.

## 2. Terms a junior must use consistently

| Term | Meaning | Never means |
|---|---|---|
| `SurfaceProfile` | Trust boundary for one shell/actor class | A visual theme or role string |
| `PageCapabilityPack` | Route-scoped relevance and disclosure | Authorization or a raw route list |
| Capability | Versioned read/propose/command contract | An arbitrary controller method |
| Skill | Bounded finite-state workflow over capabilities | A prompt containing business rules |
| Inquiry | Policy/released-own-data Q&A with no command tools | A weaker-looking Assistant with hidden writes |
| Assistant | Explicitly consented task help with reads/proposals | Autonomous command execution |
| Proposal | Persisted exact candidate action with evidence | Model prose or a frontend object |
| Confirmation | Human decision over one immutable proposal | SDK approval callback or countdown |
| Knowledge source | Governed version with owner, audience, state, and route | Any database row or uploaded file |
| Context epoch | Server-owned boundary for safe conversation reuse | Current browser URL alone |

## 3. Surface profiles

| Profile | Actors | Default | Allowed context ceiling | Hard exclusions |
|---|---|---|---|---|
| `staff` | Registrar, Guidance, Program Head, HR as scoped | Inquiry | Policy, staff operational projections, approved skills | Applicant credentials, unrelated office/program data, break-glass data |
| `applicant` | Authenticated applicant | Inquiry | Public policy and lifecycle-authorized own projections; result/score content only after release | Staff projections, internal notes, unreleased results, other applicants |
| `proctor_session` | Active assigned duty | Inquiry or hidden | One assigned session and operational guidance | Other sessions, grading, release, broad staff tools |
| `knowledge_admin` | Approved source owner/approver/operator | Inquiry | Knowledge lifecycle guidance and source metadata | Arbitrary prompt/tool editing, operational records as corpus |
| `operations` | Approved operator | Hidden or static guide | Health/reconciliation metadata | Applicant content and ordinary staff commands |
| `break_glass` | Active approved emergency session | Hidden by default | Explicit allowlist only | General-purpose Nexi and retained ordinary history |

The component may be shared. Conversations, page packs, prompts, disclosures, retention keys, skills, and TaskGrants are partitioned by profile.

Surface precedence is compiled and tested, not inferred ad hoc: active break-glass profile → operations route → knowledge-administration route → active proctor-session route/duty → authenticated applicant route/guard → ordinary staff route. Unknown or ambiguous combinations fail closed. The matched server route and authenticated/duty context decide; client URLs and role strings do not.

## 4. Frontend contract that can be stitched first

### 4.1 Shared server prop

Every authenticated shell receives one nullable `nexi` prop shaped as a versioned `NexiSessionView`:

```ts
type NexiSessionView = {
  contract_version: '1';
  surface: { key: string; version: number };
  context_epoch: string;
  page_pack: { key: string; version: number } | null;
  publication_version: number | null;
  availability:
    | 'hidden'
    | 'inquiry_only'
    | 'assistant_available'
    | 'assistant_active'
    | 'degraded'
    | 'quota_exhausted'
    | 'suspended';
  modes: Array<'inquiry' | 'assistant'>;
  active_mode: 'inquiry' | 'assistant';
  disclosure: { key: string; version: number; text: string };
  conversation: { id: string | null; run_id: string | null; cursor: number };
  usage: {
    state: 'available' | 'near_limit' | 'exhausted' | 'unknown';
    used: number | null;
    limit: number | null;
    unit: string;
    resets_at: string | null;
  };
  proposals: ProposalSummary[];
  manual_fallbacks: UiIntent[];
};
```

The exact generated TypeScript/PHP DTO is decided in the foundation OpenSpec. Unknown contract versions render degraded/manual fallback and emit safe diagnostics.

### 4.2 Shell mounting

| Shell | Immediate task | Result before provider work |
|---|---|---|
| Staff layouts | Replace duplicated mounting with one shared shell adapter | Existing locked dock remains degraded and server-controlled |
| Applicant layout | Mount the same presentation component through `applicant` profile | Policy/static help only until applicant Inquiry is promoted |
| Proctor shell | Mount only when the active duty pack allows it | Session guide or hidden; never broad staff history |
| Knowledge admin | Mount under its own profile | Lifecycle guidance; no provider required |
| Public/login | Do not mount authenticated Nexi | Use static help links/components only |

### 4.3 Client state ownership

The frontend may own only presentation state: expanded/minimized, reduced-motion preference, scroll position, input draft, and focus. The server owns surface, page pack, active mode, consent, context epoch, availability, usage, conversation/run status, citations, proposals, and command results.

Delete browser authority gradually:

- `nexi-context.js` becomes demo evidence, then is replaced by server page-pack props;
- `nexi-actions.js` becomes demo evidence, then suggestions come from registered safe view models;
- local mode persistence cannot reactivate Assistant after an epoch, profile, disclosure, or pack change;
- the client never sends command arguments during confirmation.

### 4.4 Required dock states

| State | UI behavior | Manual path |
|---|---|---|
| Hidden | No launcher and no reserved gap | Ordinary page help links |
| Inquiry only | Inquiry disclosure and no Apply controls | Normal page actions |
| Assistant available | Show consent affordance, not automatic switch | Stay in Inquiry |
| Assistant active | Show active scope, page/task pack, stop/demote control | Ordinary page actions |
| Thinking/streaming | Cancel generation; do not imply mutation | Page remains usable |
| Awaiting confirmation | Immutable impact/warning/version/expiry card | Cancel or use page action |
| Executing | Disable duplicate confirmation; show operation ID | Reconcile, do not repeat blindly |
| Conflict/stale | Show authoritative change and rebuild option | Open target page |
| Quota exhausted | Explain reset/admin policy without exposing cost internals | Static help and page workflow |
| Provider degraded | Preserve history/safe state; no fabricated answer | Static policy link or page workflow |
| Suspended/emergency | Stop new runs and uncommitted proposals | Explicit support/manual path |

### 4.5 Stream/event union

The client renders only registered events such as:

- `run_started`, `text_delta`, `answer_completed`, `refused`;
- `citation_added`, `tool_started`, `tool_completed`;
- `mode_consent_requested`, `mode_changed`;
- `proposal_created`, `proposal_invalidated`, `proposal_executing`, `proposal_result`;
- `usage_updated`, `degraded`, `manual_fallback`, `run_cancelled`, `run_failed`;
- `snapshot_required`.

Each durable event has `contract_version`, `run_id`, monotonically increasing `sequence`, `context_epoch`, and safe correlation metadata. A duplicate is ignored; a gap requests a snapshot; a stale epoch is discarded.

Raw provider deltas are never events. The application output assembler buffers and validates chunks first; protected own/staff-data answers default to full buffered validation. Citation, link, refusal, and terminal events are published only after schema, privacy, source-eligibility, and allowlist validation.

Initial transport is deliberately simple: turn submission persists a Run and enqueues a database-queue worker; the worker calls the gateway and appends application-owned events to PostgreSQL; a reauthorized SSE endpoint replays by cursor/`Last-Event-ID` with heartbeats and snapshot fallback. No Echo, Redis, WebSocket, or provider-to-browser stream is required.

## 5. Complete route-family matrix

“Later” means the architecture allows it but a separate story/OpenSpec must promote it.

| Existing/planned route family | Profile | Inquiry | Assistant | First useful skills | Initial disposition |
|---|---|---|---|---|---|
| `/`, `/apply`, confirmation | Public/static | Static policy help | No | Application checklist | No provider; static help |
| Applicant login/setup | Applicant guest/static | Static recovery help | No | Access-key guidance | No conversation history |
| `/applicant/portal` | Applicant | Policy + lifecycle-authorized own status | Guide/navigation later | Explain status, next-step checklist | Mount degraded/static first |
| `/applicant/schedule` | Applicant | Policy + own schedule | Reserve proposal later | Compare slots, explain reservation | Separate applicant pilot |
| Applicant correction | Applicant | Policy + own attempt | Resubmit proposal later | Correction checklist | High privacy; own record only |
| Applicant result/notifications | Applicant | Authorized notifications and released result only | Usually no mutation | Explain release/status, contact staff | Never unreleased/internal result data |
| `/staff` | Staff | Yes | Guide/navigation | Explain workspace, setup readiness | Foundation shell |
| `/setup/**` | Staff | Policy/config explanation | Read/guide initially | Find readiness blocker, explain active policy | No broad setup writes initially |
| `/staff/personnel/**` | Staff | Policy and scoped records | Proposals later | Account lifecycle checklist | High-risk capability-specific |
| `/admissions` | Staff | Policy + scoped queue | Read/triage later | Summarize queue, completeness triage | No bulk decision first |
| `/admissions/{attempt}` | Staff | Policy + authorized record | Proposals later | Explain blockers, draft correction request | Accept/dismiss/reissue are high risk |
| `/exams/sessions` | Staff | Policy + schedule projections | Yes | Plan sessions, find conflicts | First staff Assistant pilot |
| `/exams/sessions/{session}` | Staff | Session facts | Yes | Allocate applicants, proctor coverage, publish readiness | Proposal/confirm only |
| `/proctor` | Proctor session | Session guide | Read/guide initially | Monitor exceptions, explain deadline | No AI attendance write initially |
| `/grading` | Staff | Policy + completeness | Read/guide initially | Find missing/invalid scores | No score mutation or finalize first |
| `/consultation` | Staff | Policy + scoped state | Read/guide initially | Prepare consultation checklist | Human decision remains explicit |
| `/release` | Staff | Policy + release prerequisites | Read/guide initially | Explain blockers, delivery status | No release/unrelease proposal first |
| Reports/exports future | Staff/program scoped | Definitions/citations | Checklist only | Build report scope checklist | No model-driven export first |
| Audit/compliance future | Approved reviewer | Static/query help | No mutation | Explain filters and evidence | Sensitive profile or hidden |
| Knowledge admin future | Knowledge admin | Lifecycle guidance | Draft/test assistance | Prepare source, validate citation | Publish/revoke remain typed confirms |
| Operations/recovery future | Operations | Static runbook help | No general Assistant | Reconciliation checklist | No provider dependency |
| UI kit/capability probe | Internal | Hidden | Hidden | None | Never production Nexi context |

## 6. Capability catalog seed

Provider-facing tools include only `read` and `propose`. `command` entries exist for ordinary UI/confirmation dispatch but are never exposed directly to the model.

| Key seed | Kind | Existing target handler/source | Risk/disposition |
|---|---|---|---|
| `setup.readiness.read@1` | Read | `ComputeSetupReadinessQuery` | Low; staff scoped |
| `admissions.attempts.list@1` | Read | `ListAdmissionAttemptsQuery` | Protected/cardinality bound |
| `admissions.attempt.show@1` | Read | `ShowAdmissionAttemptQuery` | Protected single target |
| `admissions.correction.propose@1` | Propose | New proposal builder + domain preflight | Medium/high |
| `admissions.correction.request@1` | Command | `RequestApplicationCorrection` | Confirm + audit |
| `exams.sessions.list@1` | Read | New authorized Query over existing domain | First pilot |
| `exams.session.show@1` | Read | New authorized Query | First pilot |
| `exams.conflicts.evaluate@1` | Propose | Canonical Examination preflight | No mutation |
| `exams.assignment.batch_propose@1` | Propose | New batch proposal builder | Exact impact set |
| `exams.assignment.batch_apply@1` | Command | Purpose-built batch Action required | Confirm/queued/partial contract |
| `exams.reservation.open@1` | Command | `OpenExamSessionReservation` | Confirm |
| `exams.session.publish@1` | Command | `PublishExamSession` | High visibility + notify |
| `proctor.session.read@1` | Read | New duty-scoped Query | One active duty only |
| `grading.completeness.read@1` | Read | New Assessment Query | No raw score egress by default |
| `consultation.readiness.read@1` | Read | New Release Query | Human decision only |
| `release.blockers.read@1` | Read | New Release Query | No applicant-visible release tool first |
| `applicant.status.read@1` | Read | New own-record Query | Applicant profile only |
| `applicant.schedule.read@1` | Read | New own-record Query | Applicant profile only |
| `applicant.reservation.propose@1` | Propose | `ReserveExamSlot` preflight | Later applicant Assistant |
| `applicant.reservation.apply@1` | Command | `ReserveExamSlot` | Confirm/idempotent |
| `knowledge.sources.list@1` | Read | New Knowledge Query | Admin profile |
| `knowledge.source.validate@1` | Propose | Extraction/citation validator | Does not publish |
| `knowledge.source.publish@1` | Command | New governed publish Action | Separation of duties |
| `knowledge.source.revoke@1` | Command | New revoke Action | Immediate retrieval denial |

Every row must gain input/result schema, application capability, scope resolver, privacy projection, confirmation policy, idempotency policy, rule dependencies, safe errors, tests, and owner before registration.

## 7. Reusable skill catalog

| Skill key | Surface | Reads | Final output | Mutation policy | Rollout |
|---|---|---|---|---|---|
| `workspace.explain@1` | Staff/applicant scoped variants | Page summary, allowed help | Explanation/navigation intent | None | Foundation |
| `setup.find_blockers@1` | Staff | Setup readiness | Ordered checklist | None | Early |
| `admissions.review_completeness@1` | Staff | Attempt + policy | Missing/invalid evidence summary | None | After foundation |
| `admissions.prepare_correction@1` | Staff | Attempt + rules | Correction-request proposal | Confirm ordinary command | Later |
| `admissions.triage_queue@1` | Staff | Bounded queue | Review order with reasons | No bulk decision | Later |
| `schedule.plan_sessions@1` | Staff | Applicants, rooms, sessions, constraints | Session plan proposals | Batch command only after confirm | Pilot |
| `schedule.allocate_applicants@1` | Staff | Accepted/unassigned + capacity | Exact assignment proposal | Purpose-built batch command | Pilot |
| `schedule.resolve_conflicts@1` | Staff | Conflicts + alternatives | Feasible alternatives | Proposal only unless confirmed | Pilot |
| `schedule.check_publish_readiness@1` | Staff | Session/roster/proctor state | Blocker checklist | Publish remains separate confirm | Pilot |
| `proctor.monitor_exceptions@1` | Proctor | One duty/session | Late/missing event checklist | No attendance/submission write first | Later |
| `grading.find_incomplete@1` | Staff | Completeness projection | Missing/invalid checklist | No score/finalize mutation first | Later |
| `consultation.prepare@1` | Staff | Authorized attempt summary | Consultation checklist | No automated decision | Later |
| `release.find_blockers@1` | Staff | Release prerequisites | Blocker and delivery summary | No release command first | Later |
| `reports.scope_export@1` | Staff/program | Metric definitions/scope | Export checklist or proposal | Export independently authorized | Later |
| `personnel.prepare_account_change@1` | Staff HR | Scoped account/ceiling | Proposed lifecycle change | High-risk confirmation | Later |
| `knowledge.prepare_source@1` | Knowledge admin | Draft/extraction/metadata | Validation findings | Cannot approve own source by policy | Knowledge slice |
| `knowledge.test_citations@1` | Knowledge admin | Active candidate index | Query/citation test report | None | Knowledge slice |
| `applicant.application_checklist@1` | Applicant | Own attempt + policy | Next-step checklist | None | Applicant Inquiry |
| `applicant.explain_status@1` | Applicant | Released own projection | Plain-language explanation | None | Applicant Inquiry |
| `applicant.choose_slot@1` | Applicant | Own eligible slots | Reservation proposal | Confirm `ReserveExamSlot` | Later applicant Assistant |
| `applicant.correction_checklist@1` | Applicant | Own requested fields | Resubmission checklist | Confirm resubmission later | Later |
| `inquiry.contact_staff@1` | Applicant/staff | Approved routing policy | Handoff preview | Creates inquiry only after confirmation and EV-018 | Gated |

Skill guidance may explain domain rules, but every validity decision comes from domain Queries/preflight and `ConstraintEvidence`. When a business rule changes, change and test the domain rule first, then rebuild affected capability/skill/publication artifacts.

## 8. Knowledge-document administration

### 8.1 Planned pages

| Planned route | Page responsibility |
|---|---|
| `/staff/knowledge/sources` | Filtered source/version list, owner, audience, state, effective date, index/citation status |
| `/staff/knowledge/sources/create` | Metadata plus upload/manual-content choice; draft only |
| `/staff/knowledge/sources/{source}` | Version history, provenance, active/superseded state, audit summary |
| `/staff/knowledge/versions/{version}/review` | Extraction preview, diff, redaction/classification, chunk/citation anchors, validation errors |
| `/staff/knowledge/versions/{version}/tests` | Curated questions, expected support/refusal, citation results, regression history |
| `/staff/knowledge/versions/{version}/publish` | Impact, audience, effective window, supersession, confirmation |
| `/staff/knowledge/versions/{version}/reconcile` | Index/cache/delete jobs, failure evidence, retry/repair |

These route names are proposed contracts and require the OpenSpec route catalog before code.

### 8.2 Lifecycle and commands

1. `CreateKnowledgeSourceDraft`
2. `UploadKnowledgeSourceVersion` or `SaveKnowledgeSourceContent`
3. uploaded files enter private quarantine and pass type sniffing, size/page limits, malware/content checks, and a sandboxed no-network/no-credential parser boundary
4. `RequestKnowledgeExtraction`; worker records immutable extraction artifact and checksum
5. owner reviews classification, audience, effective dates, extracted text, and citation anchors
6. `SubmitKnowledgeSourceForApproval`
7. independent approver runs/accepts required citation/refusal fixtures
8. `ApproveKnowledgeSourceVersion` binds an RFC 8785/domain-separated SHA-256 publication digest over metadata, content/extraction digests, classification, audience/purpose/effective fields, index profile, and required fixtures; any change invalidates approval
9. `PublishKnowledgeSourceVersion` starts indexing and activates only after successful index evidence
10. later `SupersedeKnowledgeSourceVersion` or `RevokeKnowledgeSourceVersion`
11. synchronous predicate denial happens immediately; cache/index deletion and reindex reconcile asynchronously
12. retention policy eventually permits `RequestKnowledgeSourceDeletion`; UI says deletion pending until every required store is reconciled

### 8.3 Required UI states

- upload scanning/extraction pending and failed;
- unsupported format, checksum duplicate, validation/classification required;
- review diff against previous active version;
- approval denied or separation-of-duties conflict;
- indexing queued, active, partially failed, stale, or reindex required;
- citation suite pass/fail with exact source/version/span evidence;
- supersession impact and conversations/citations invalidated;
- revoked immediately, deletion pending, retention hold, and reconciliation failed;
- provider/index unavailable with source administration still operable.

### 8.4 Source classes

| Source class | Retrieval route | Example |
|---|---|---|
| Institutional policy | Knowledge index with citations | Privacy policy, admissions rules, official FAQ |
| Applicant released own data | Deterministic Query | Own status, schedule, released result |
| Staff operational data | Deterministic Query | Admissions queue, sessions, grading completeness |
| System/runbook help | Approved static/code-owned source | Nexi/help and recovery instructions |
| Unsupported/unowned material | Denied | Free web, arbitrary uploads, copied operational exports |

## 9. Usage and administration

The UI usage ring reads the durable effective state; it does not enforce anything itself.

Required backend concepts:

- `AiPolicyPublication` for institution-approved availability ceilings;
- `AiActorPolicyOverride` that can narrow/suspend or adjust quota inside a ceiling;
- `UsageReservation`, `UsageSettlement`, and immutable ledger entries;
- dimensions for asks, input tokens, output tokens, tool steps, provider cost, and burst rate;
- pricing/profile version and policy timezone/reset;
- unknown-usage reconciliation after timeout or missing provider usage;
- emergency disable checked at provider, tool, and confirmation boundaries.

Protected reads also consume a cumulative disclosure ledger across the context epoch, TaskGrant, and Run. It accounts for unique targets, sensitive fields, rows/pages, and overlapping queries so a skill cannot enumerate a larger protected set through many individually valid calls.

Admin pages may enable approved surfaces, set quotas, suspend an actor, select an approved routing profile, and inspect reconciliations. They may not add capabilities, edit production prompts, upload executable skills, bypass scope, or grant staff authority to applicants.

## 10. Junior-sized implementation sequence

Each slice gets its own stories and OpenSpec tasks; no task below authorizes the next slice automatically.

### Slice 0 — Reconcile planning authority

- Verify the 2026-08-10 reconciliation of PRD/roadmap/GF-14/GF-22/GF-24 to the selected Inertia 3 + Svelte 5 target; do not reintroduce React.
- Archive the completed scheduling OpenSpec and open planning-only `ai-assistant-foundation`.
- Approve the surface/mode matrix, persona/disclosure, retention placeholders, and capability/skill seeds.

Exit: no P0 framework or authority contradiction.

### Slice 1 — Shared disabled/degraded frontend shell

- One Svelte dock adapter reused by staff and applicant shells.
- Server-provided disabled/degraded `NexiSessionView` fixtures.
- Explicit Inquiry default, no client-derived tools, accessible states, reduced motion, responsive behavior.
- No SDK, provider, RAG, embeddings, or live command.

Evidence: Vitest state matrix, Playwright staff/applicant narrow and keyboard paths, no failed required requests.

### Slice 2 — Server surface, page-pack, and contract kernel

- Code-owned `SurfaceProfile`, route→pack compiler, Capability Registry schema, effective-policy resolver, and generated/checked DTOs.
- Unknown **surface**/versions fail closed (`hidden`); unknown **page pack** on a mountable surface → `degraded` with `page_pack: null` (no prior-pack inheritance).
- Port existing context/action demo stubs to non-authoritative fixtures and remove them from production decisions.
- Register `setup.readiness.read@1` over the existing `ComputeSetupReadinessQuery`, then migrate the staff home/setup-readiness controller path through the same Capability Kernel. This is the reference HTTP-versus-Nexi parity implementation; do not start with a write command.

Evidence: build duplicate/unknown tests; Pest allow/deny across staff/applicant/proctor; Inertia prop contract tests.

### Slice 3 — Conversation, stream, proposal, usage, and recovery foundations

- PostgreSQL conversation/run/event/proposal/usage state machines; database-queued Run worker; output assembler; reauthorized SSE cursor/snapshot; idempotency, audit, consent, cumulative disclosure budgets, quota reservation/settlement, kill switch, scheduler reconciliation.
- Fake gateway only.

Evidence: race/replay/cancel/unknown-usage/expiry tests plus frontend event-union tests.

### Slice 4 — Knowledge governance without live RAG

- Knowledge-source CRUD lifecycle, private quarantine and safe parser fake, review/approval/publication digest/publish/revoke UI, citation fixture harness, index port fake.
- No claim that semantic RAG works.

Evidence: separation-of-duties, hostile/invalid file, approval invalidated by metadata/content/fixture change, failed extraction/index, publish, supersede, synchronous revoke denial, retry/reconcile, and PostgreSQL/private-storage backup manifest plus derived-index rebuild.

### Slice 5 — Provider adapter spike on synthetic data

- Approve ADRs; pin the accepted Laravel AI release behind `AiGateway`.
- Prove structured output, bounded tools, stream/cancel, usage, failure normalization, and fakes.

Evidence: golden adapter fixtures; no sensitive production data.

### Slice 6 — Policy Inquiry

- Approved corpus, auth-before-retrieve, citations, refusal, static fallback, prompt-injection/eval suite.

Evidence: curated questions, citation correctness, unsupported refusal, revoked-source denial, outage fallback.

### Slice 7 — Applicant Inquiry

- Applicant profile mounted live; policy and lifecycle-authorized own-data router branches, with result fields release-gated; conversation partition and clear behavior.

Evidence: applicant A/B isolation, unreleased denial, disclosure, mobile/low-bandwidth, provider outage.

### Slice 8 — Staff exam Assistant pilot

- Examination reads, canonical preflight, allocation/conflict skills, exact proposal, confirmation-to-ordinary command, UI intents.

Evidence: same page/Nexi command behavior, stale rule/target invalidation, idempotent confirmation, bulk partial/atomic contract, manual scheduling fallback.

### Slice 9 — Cross-domain expansion

- One page pack and skill at a time: admissions, proctor, grading, consultation/release, reports, personnel.
- High-risk mutations remain read/guide-only until independently promoted.

## 11. Suggested code ownership

```text
system/app/Ai/Surface/            Surface profiles and resolver
system/app/Ai/Context/            Page packs, context epochs, projections
system/app/Application/Capabilities/ Registry, schema, kernel, projections
system/app/Ai/Policy/             Availability, consent, privacy, budgets
system/app/Ai/Conversation/       Conversation, turn, run and stream state
system/app/Ai/Orchestration/      Bounded loop and proposal lifecycle
system/app/Ai/Skills/             Code-owned finite workflows
system/app/Ai/Knowledge/          Source lifecycle, router, index port
system/app/Ai/Adapters/LaravelAi/ SDK isolation only
system/app/Http/Controllers/Nexi/ Generic transport and confirmation controllers
system/resources/js/Components/kit/Nexi/ Shared render components
system/resources/js/stores/nexi/  Frontend state/reconnect/snapshot
system/resources/js/types/        Checked Nexi contracts
```

Domain rules and commands stay in existing domain modules. Do not move Examination rules into `Ai/Skills`, or Applicant privacy into prompts.

## 12. Story template required before coding

Every Nexi story must state:

1. actor and `SurfaceProfile`;
2. route/page pack and mode;
3. capability/skill versions and owner;
4. source classes and exact authorized projections;
5. ordinary Query/Action and domain preflight;
6. consent/confirmation/risk level;
7. transaction, locks, idempotency, audit, outbox;
8. usage reservation and provider/degraded behavior;
9. UI loading/empty/denied/streaming/conflict/quota/recovery states;
10. privacy classification, retention, citation/refusal behavior;
11. seed identity, seeded journey state, URLs, and reset steps;
12. Pest, Vitest, Playwright, eval, and ops evidence targets.

Unknown items are labeled `DECISION REQUIRED` and block that story; they are not left to the implementer.

## 13. Definition of ready and done

### Ready for a junior

- OpenSpec task names exact files/contracts and permitted behavior;
- upstream decisions and legacy disposition are linked;
- schemas, states, error codes, and sample fixtures are included;
- no framework/provider/product choice is implicit;
- all manual fallbacks and denied paths are testable;
- seed credentials/state/URLs/reset steps are documented.

### Done

- same capability behaves identically through page and Nexi channels;
- direct HTTP, stale version, cross-profile, cross-scope, replay, and injection attempts fail safely;
- proposal/confirmation uses canonical preflight and recorded results;
- provider failure leaves the manual journey complete;
- accessibility/responsive/browser evidence passes;
- audit, usage, citation, and reconciliation evidence is inspectable;
- OpenSpec tasks and master/HANDOFF checkpoints name the proof.

## 14. Stop rules

Stop implementation when:

- the active OpenSpec does not name the task;
- a task reintroduces React or contradicts the selected Inertia 3 + Svelte 5 target;
- a route lacks a surface/page pack/capability/scope decision;
- a skill duplicates a business rule or uses raw routes/ORM;
- provider/privacy/retention/corpus approval is missing for live data;
- SSE proxy/worker limits, retention tables, reconciliation ownership, backup manifest, and restore gate are absent from the implementing OpenSpec;
- knowledge ownership/audience/revocation is unknown;
- a command lacks proposal, confirmation, idempotency, audit, and manual parity;
- the applicant surface could see staff context or unreleased data;
- a junior would need to invent behavior from this guide rather than follow an accepted story/OpenSpec.
