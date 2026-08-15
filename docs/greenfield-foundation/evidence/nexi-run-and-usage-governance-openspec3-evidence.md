# Evidence: nexi-run-and-usage-governance OpenSpec 3

**Date:** 2026-08-10  
**OpenSpec:** `openspec/changes/nexi-run-and-usage-governance/`  
**Scope:** conversations/runs/events/usage/TaskGrants + FakeAiGateway + SSE/snapshot (no live provider / Apply)

## Server

- `App\Ai\Contracts\AiGateway` + `App\Ai\Adapters\Fake\FakeAiGateway`
- Persistence: `nexi_conversations|turns|runs|run_events|proposals|usage_reservations|task_grants|consent_records|disclosure_ledgers`
- `TurnSubmitter`, `ProcessNexiRun`, `ProcessNexiRunJob` (unique), `UsageBudgetService`, `TaskGrantService`, `ContextEpochService`, `OutputFirewall`
- HTTP: `POST /nexi/turns`, `GET .../snapshot`, `GET .../stream` (SSE), `POST .../cancel`, `POST /nexi/emergency-stop` (local/testing)
- `NexiSessionViewBuilder` wires durable conversation cursor + usage used/limit
- Testing/sync processes runs inline so Pest does not depend on a queue worker

## Frontend

- `resources/js/lib/nexi-transport.ts` — turn submit + snapshot
- `NexiAssistPanel.svelte` — server path when `session` present; loads history from snapshot

## Tests

```bash
docker compose exec -T app php artisan test --compact \
  tests/Feature/Ai/NexiRunGovernanceTest.php \
  tests/Feature/Ai/NexiFoundationSessionTest.php \
  tests/Feature/Capabilities/CapabilityKernelTest.php \
  tests/Feature/Setup/SetupHubTest.php
npm test -- --run tests/frontend/nexi-session.test.ts tests/frontend/nexi-transport.test.ts
```

**Result (2026-08-10):** Pest **33 passed**; Vitest **4 passed**.

## Pack-as-lens / TaskGrants

- Same staff surface reuses open conversation/epoch across pack-null pages
- Cross-actor snapshot denied
- TaskGrant not usable on other surfaces; emergency stop revokes grants + blocks turns

## Explicitly not in this cut

`laravel/ai`, knowledge admin/RAG, confirm-to-Apply domain mutations, propose/command registry kinds.

## ARCHIVED PASS 2026-08-10

Moved to `openspec/changes/archive/2026-08-10-nexi-run-and-usage-governance/`.
