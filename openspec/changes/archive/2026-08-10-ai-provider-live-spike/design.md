# Design: AI Provider Live Spike

## 1. Boundary (AD-13)

```text
ProcessNexiRun / TurnSubmitter
        │
        ▼
   AiGateway (NEXIAM DTO only)
        │
   ┌────┴────┐
   ▼         ▼
FakeAiGateway   LaravelAiGateway ──► laravel/ai Agent (no SDK types escape)
```

Application code never imports `Laravel\Ai\*` outside `App\Ai\Adapters\LaravelAi\`.

## 2. Config

| Key | Default | Purpose |
|---|---|---|
| `NEXI_AI_GATEWAY` | `fake` | `fake` \| `laravel` |
| `NEXI_AI_PROVIDER` | `openai` | Provider lab key for spike |
| `NEXI_AI_MODEL` | `gpt-4o-mini` | Approved synthetic model |
| `NEXI_AI_TIMEOUT` | `30` | Seconds |
| `OPENAI_API_KEY` / provider key | empty | Required only when gateway=laravel for live calls |

## 3. Degradation

- `health()` returns `{ok, provider, degraded, reason?}`
- When laravel selected but key missing or probe fails → `degraded=true`; runs fail closed with normalized reason (`provider_unavailable`) — never hang
- Availability resolver may keep dock `degraded` (already OS1 posture)

## 4. Cancellation

- `requestCancel($runId)` sets adapter-local flag; stream loop checks between chunks (best-effort; provider may still bill partial — documented)

## 5. Tests

- Default binding = Fake (existing OS3 tests green)
- Laravel adapter unit/feature tests use `Agent::fake()` — no network
- Optional manual live smoke documented in HANDOFF (not CI)
