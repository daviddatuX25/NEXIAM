# Tasks: AI Provider Live Spike (OpenSpec 5)

## A — Activate

- [x] A1 Archive OS4 knowledge PASS; point project-context + master + archive README at this change
- [x] A2 Pin `laravel/ai` in `system/composer.json` (exact version recorded in HANDOFF) — **v0.10.3**
- [x] A3 Extend `config/nexi.php` + `.env.example` gateway/provider/model/timeout

## B — Adapter

- [x] B1 `App\Ai\Adapters\LaravelAi\NexiInquiryAgent` (Promptable; no tools)
- [x] B2 `LaravelAiGateway` maps generate/stream/health/requestCancel → NEXIAM DTOs
- [x] B3 `AppServiceProvider` binds Fake vs Laravel from config
- [x] B4 Provider-down / missing-key → normalized failure; ProcessNexiRun event labels gateway

## C — Proof

- [x] C1 Pest: fake remains default; Laravel adapter fake-stream; missing-key health degraded
- [x] C2 Evidence + HANDOFF (manual live smoke steps); no RAG/embedding claim
- [x] C3 D3 founder PASS / archive — 2026-08-10

## Out of scope

Embeddings, Mixedbread, pgvector, mutation tools, applicant production traffic, RAG quality.
