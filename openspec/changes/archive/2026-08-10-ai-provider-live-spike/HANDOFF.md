# Handoff: AI Provider Live Spike (OpenSpec 5)

> **ARCHIVED PASS 2026-08-10.** Evidence: `docs/greenfield-foundation/evidence/ai-provider-live-spike-2026-08-10.md`. Active work moved to `openspec/changes/nexi-policy-inquiry-rag/`.


**Date:** 2026-08-10  
**Active OpenSpec:** `openspec/changes/ai-provider-live-spike/`  
**Checkpoint:** **IMPLEMENTATION COMPLETE — awaiting founder D3 PASS** (synthetic / no RAG claim)  
**Prior:** `archive/2026-08-10-nexi-knowledge-governance/` PASS  
**Pin:** `laravel/ai` **v0.10.3**

## Do not regress

1. Default gateway stays **fake** unless `NEXI_AI_GATEWAY=laravel`
2. No SDK types outside `App\Ai\Adapters\LaravelAi\`
3. No mutation tools / Apply
4. No embeddings / RAG quality claim (next: `nexi-policy-inquiry-rag`)

## Seeds

| Actor | Login |
|---|---|
| Guidance | `guidance.counselor@nexiam.test` / `password` |

App: `http://127.0.0.1:18000`

## Live smoke (manual, optional)

1. Rebuild Compose app image so vendor includes `laravel/ai` (code was volume-tested; baked image needs rebuild)  
2. Set in env: `NEXI_AI_GATEWAY=laravel`, `OPENAI_API_KEY=…`, `NEXI_AI_MODEL=gpt-4o-mini`  
3. Staff turn on `/staff` — expect streamed live reply via existing Nexi dock  
4. Unset key → health degraded / turn fails closed (`provider_unavailable`) without hanging  

## Proof

- Pest: `tests/Feature/Ai/AiProviderLiveSpikeTest.php` + OS3 run suite still green  
- Evidence: `docs/greenfield-foundation/evidence/ai-provider-live-spike-2026-08-10.md`

## Next after PASS

`nexi-policy-inquiry-rag` (local index/embedding contract + citations)
