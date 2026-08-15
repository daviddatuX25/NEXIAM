# Handoff: Nexi Run & Usage Governance (OpenSpec 3)

> **ARCHIVED PASS 2026-08-10.** Evidence: `docs/greenfield-foundation/evidence/nexi-run-and-usage-governance-openspec3-evidence.md`. Active work moved to `openspec/changes/nexi-knowledge-governance/`.

**Date:** 2026-08-10  
**Active OpenSpec:** `openspec/changes/nexi-run-and-usage-governance/`  
**Checkpoint:** **ARCHIVED PASS 2026-08-10** — runs/SSE/usage/TaskGrants + FakeAiGateway; next = `nexi-knowledge-governance`  
**Architecture authority:** `docs/greenfield-foundation/architecture/ai-assistant-foundation/` (AD-9, AD-15–AD-19, AD-22, AD-24, AD-26; AD-3 pack-as-lens)

## Status

| Area | State |
|---|---|
| OpenSpec 1 foundation | Archived PASS → `archive/2026-08-10-ai-assistant-foundation/` |
| OpenSpec 2 Kernel | Archived PASS → `archive/2026-08-10-ai-capability-kernel/` |
| This OpenSpec | **ARCHIVED PASS 2026-08-10** |
| Live provider / RAG / Apply mutations | **Not authorized** |

## Founder notes (do not regress)

1. Pack = relevance lens (AD-3). Null pack ⇒ dock/history still ok on mountable surfaces.
2. Cross-page tools ⇒ TaskGrant + Kernel ∩ GF-21 — not destination pack inheritance.
3. Confirm-to-Apply still later; this cut may show proposal **shell** events only.
4. Fake gateway only until `ai-provider-live-spike`.

## Developer-ready seeds

### App URL

- `http://127.0.0.1:18000`

### Logins

| Actor | Credentials |
|---|---|
| Guidance | `guidance.counselor@nexiam.test` / `password` |
| Registrar | `registrar.clerk@nexiam.test` / `password` |
| Applicant | `DEMO-ACPT` · `2003-09-25` |

### Recreate

```bash
cd system
docker compose build app
docker compose up -d --force-recreate app queue
docker compose exec -T app php artisan foundation:reset --force
```

### Feature URLs (target after implementation)

| Path | Expect |
|---|---|
| `/staff` | Nexi dock; can start conversation; history survives refresh |
| `/setup` | Same partition; pack lens updates; TaskGrant may continue bounded work |
| `/exams/sessions` | Dock degraded, pack null; history ok; no pack-inherited tools |
| `/applicant/portal` | Separate conversation partition; no staff history |

## Explicitly next (do not fold in)

1. `nexi-knowledge-governance`  
2. `ai-provider-live-spike`  
3. Inquiry / exam Assistant OpenSpecs  

## Junior pointer

Slice 3 in `JUNIOR-IMPLEMENTATION-GUIDE.md`; spine AD-26 for SSE/queue deployment rule.


## Implementation progress (2026-08-10)

### Done
- FakeAiGateway + run worker/events/SSE snapshot
- Usage budgets + TaskGrants + emergency stop
- NexiSessionView durable conversation/usage
- AssistPanel server turn path via `nexi-transport`
- Evidence: `docs/greenfield-foundation/evidence/nexi-run-and-usage-governance-openspec3-evidence.md`

### Proof

```bash
cd system
# after docker compose cp of app/Ai app/Models/Ai app/Jobs/Ai Controllers/Nexi routes config migration tests
docker compose exec -T app php artisan test --compact tests/Feature/Ai/NexiRunGovernanceTest.php
```

Prefer rebuild so migrations/code stick:

```bash
docker compose build app && docker compose up -d --force-recreate app queue
```
