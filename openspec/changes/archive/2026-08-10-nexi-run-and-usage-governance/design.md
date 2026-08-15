# Design: Nexi Run & Usage Governance

## 1. Goal

Make Nexi stateful under application authority: one Run per turn submission, fake provider behind `AiGateway`, durable events, reauthorized stream, budgets, and TaskGrants — without executing domain commands.

## 2. Flow

```text
Client turn submit (authenticated)
  → resolve SurfaceProfile + page_pack (lens) + context epoch
  → persist Conversation/Turn/Run (queued)
  → reserve multi-dimensional usage budget
  → enqueue RunWorker (idempotent)
  → worker: AiGateway.fake generate/stream chunks
  → AD-11 output firewall/assembler
  → append sequenced RunEvents
  → settle/release budget
SSE subscriber
  → reauthorize actor + conversation partition
  → replay from cursor / Last-Event-ID + heartbeats
  → snapshot fallback on gap
```

## 3. Ownership

```text
system/app/Ai/Conversation/     models/services: Conversation, Turn, Run, RunEvent
system/app/Ai/Orchestration/    RunWorker, assembler/firewall, proposal shell
system/app/Ai/Policy/           budgets, consent, emergency stop, disclosure ledger
system/app/Ai/Grants/           TaskGrant issue/revoke/validate
system/app/Ai/Contracts/        AiGateway port + FakeAiGateway
system/app/Http/Controllers/Nexi/  turn submit, SSE, snapshot, cancel
system/app/Jobs/                RunProcessJob, UsageReconcileJob, RetentionJob
system/resources/js/.../Nexi/   event union render, reconnect, usage display
```

Capability Kernel remains the only invoke path for tools; this cut may call `setup.readiness.read@1` from a fake tool loop **only if** TaskGrant/mode/budget allow — still no propose/command kinds.

## 4. Persistence (PostgreSQL)

Minimum tables (names illustrative; migrations own final names):

| Entity | Key fields |
|---|---|
| `nexi_conversations` | id, actor_type, actor_id, institution_id, surface_key, partition_key, context_epoch, status |
| `nexi_turns` | conversation_id, seq, mode, page_pack_key/version, input_ref |
| `nexi_runs` | turn_id, status, publication_version, gateway_route, cancel_requested_at |
| `nexi_run_events` | run_id, seq, type, payload_json, created_at |
| `nexi_proposals` | run_id, status, capability_id, expected_version, expires_at (shell only; no Apply) |
| `nexi_usage_reservations` | actor/institution dims, amounts, state reserve|settled|released|unknown |
| `nexi_task_grants` | actor/session, skill_key/version, allowed capability versions, scope bounds, origin pack, epoch, status |
| `nexi_consent_records` | actor/session, disclosure_version, surface, expiry (Assistant activation; may stay unused) |
| `nexi_disclosure_ledgers` | epoch/grant/run cumulative counters |

## 5. TaskGrants (cross-page)

- Created when starting an eligible skill (fixture skill ok) or explicit grant API for tests
- Bound to actor/session, surface, skill@version, allowed capability versions, cardinality, origin pack/version, epoch
- Later tool steps: grant **plus** fresh Kernel auth; never inherit destination page pack tools
- Revoke on logout, session/scope change, mode demotion, suspension, emergency stop, capability revocation, incompatible publication
- Navigation alone may preserve grant only within original bounds

## 6. Pack-as-lens (mandatory)

| Concern | Rule |
|---|---|
| History across pages | Same conversation partition + epoch policy; pack change updates suggestions, does not wipe actor-authorized history unless epoch segments |
| Tools on new page | Kernel ∩ GF-21 ∩ TaskGrant ∩ mode — not “only current pack list” |
| Null pack | Dock/history still allowed on mountable surfaces; no prior-pack tool inheritance |
| Proposals Apply | Shell + events only; confirmation commit deferred |

## 7. Fake AiGateway

```text
AiGateway::generate / stream / health / requestCancel
FakeAiGateway returns deterministic text + optional fake tool_request for setup.readiness.read@1
No laravel/ai package install in this OpenSpec
```

## 8. SSE

- Generic endpoint; reauthorize every connect/reconnect
- Cursor / Last-Event-ID replay from `nexi_run_events`
- Heartbeats; bounded connection time; terminal event closes
- No Echo/Redis/WebSocket/provider byte passthrough

## 9. Explicit non-goals

- Live provider, RAG, knowledge admin UI
- Command confirmation that mutates domain state
- Expanding Capability Registry with propose/command kinds (proposal **rows** may exist as shell)
