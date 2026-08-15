# Evidence: ai-provider-live-spike (OpenSpec 5)

**Date:** 2026-08-10  
**Claim:** Pinned `laravel/ai` v0.10.3 behind `AiGateway` with fake default; Laravel adapter normalizes stream DTOs; missing key degrades closed.  
**Non-claim:** No RAG quality, no embeddings, no mutation tools, no production applicant traffic.

## Pin

| Package | Version |
|---|---|
| `laravel/ai` | `^0.10.3` (resolved `v0.10.3`) |

## Commands

```text
composer require laravel/ai
php artisan test --compact tests/Feature/Ai/AiProviderLiveSpikeTest.php tests/Feature/Ai/NexiRunGovernanceTest.php
→ 14 passed (79 assertions)
```

## Config

- `NEXI_AI_GATEWAY=fake|laravel` (default **fake**)
- `NEXI_AI_PROVIDER` / `NEXI_AI_MODEL` / `NEXI_AI_TIMEOUT`
- Provider keys via published `config/ai.php` (`OPENAI_API_KEY`, etc.)

## Adapter path

`App\Ai\Adapters\LaravelAi\{NexiInquiryAgent,LaravelAiGateway}` — no SDK types in orchestration.
