# Change Proposal: AI Provider Live Spike

**Change ID:** `ai-provider-live-spike`  
**Status:** ACTIVE  
**Phase:** E3 OpenSpec 5 of the Nexi sequence  
**Depends on:** `nexi-knowledge-governance` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-nexi-knowledge-governance/`

## Intent

Put a **real LLM transport** behind NEXIAM’s `AiGateway` port — using a pinned `laravel/ai` adapter — on **synthetic prompts only**, with stream/cancel/usage/timeout/provider-down normalization. Fake remains the default for tests and keyless local.

## Explicit non-goals

| Deferred | Next |
|---|---|
| Semantic RAG / embeddings / pgvector | `nexi-policy-inquiry-rag` |
| Mutation tools / Apply confirmation | exam Assistant later |
| Production applicant traffic | later Inquiry cuts |
| Provider-hosted vector stores | never default (AD-12) |

## In scope

1. Pin `laravel/ai`; isolate SDK in `App\Ai\Adapters\LaravelAi\` (AD-13)
2. `LaravelAiGateway` implementing `AiGateway` (generate/stream/health/requestCancel)
3. Config switch: `NEXI_AI_GATEWAY=fake|laravel` (default **fake**)
4. One approved provider/model pair via env (OpenAI-compatible or Anthropic — documented in HANDOFF)
5. Golden Pest fixtures via Laravel AI fakes + provider-down degradation path
6. Wire `ProcessNexiRun` gateway label / health into session degradation when provider unhealthy
7. Cost/latency evidence note (no billing product)

## Authority

- Spine AD-13, AD-14, AD-15, AD-26
- READINESS-PLAN §17 OpenSpec 5; Junior Slice 5
- Stack: Inertia 3 + Svelte 5; no React

## Exit

- With gateway=`laravel` + API key, a synthetic staff turn can stream a live reply through existing run/SSE path
- Without key / gateway=`fake`, behavior unchanged (FakeAiGateway)
- Pest proves fake default, adapter normalization, provider-down fail-closed — no RAG claim
