# Handoff: AI Assistant Foundation (OpenSpec 1)

> **ARCHIVED PASS 2026-08-10.** Implementation evidence: `docs/greenfield-foundation/evidence/ai-assistant-foundation-openspec1-evidence.md`. Founder pack-as-lens clarification persisted in `ARCHITECTURE-SPINE.md` AD-3 and this change’s `design.md` §7b. Active work moved to `openspec/changes/ai-capability-kernel/`.

**Date:** 2026-08-10  
**Active OpenSpec:** `openspec/changes/ai-assistant-foundation/`  
**Checkpoint:** **ARCHIVED PASS 2026-08-10** — OpenSpec 1 complete; pack-as-lens (AD-3) persisted; next = `ai-capability-kernel`  
**Architecture authority:** `docs/greenfield-foundation/architecture/ai-assistant-foundation/`

## Status

| Area | State |
|---|---|
| Planning package | FINAL (spine, visual, readiness, junior guide, reviews) |
| Scheduling prior | Archived PASS → `openspec/changes/archive/2026-08-10-exam-session-scheduling/` |
| This OpenSpec | **ARCHIVED PASS 2026-08-10** |
| Provider / RAG / Kernel / mutations | **Not authorized** here |

## What this change delivered

- Server-owned `nexi` Inertia prop (`NexiSessionView` v1)
- Distinct staff vs applicant surface/page-pack projections
- Shared Svelte dock in degraded / inquiry_only / hidden states
- Client demo stubs demoted from authority

## Developer-ready seeds (reuse journey fixtures)

### App URL

- `http://127.0.0.1:18000` (Compose `app`)

### Logins

| Actor | How | Credentials |
|---|---|---|
| Guidance (staff shell) | Staff `/login` | `guidance.counselor@nexiam.test` / `password` |
| Registrar (staff shell) | Staff `/login` | `registrar.clerk@nexiam.test` / `password` |
| Accepted applicant | Applicant portal | access key `DEMO-ACPT` · birthdate `2003-09-25` |
| Pending applicant | Applicant portal | access key `DEMO-PEND` · birthdate `2003-09-25` |

Config: `system/config/seed.php` → `JourneyFixtureSeeder` + `StaffIdentitySeeder`.

### Recreate

```bash
cd system
docker compose build app
docker compose up -d --force-recreate app queue
docker compose exec -T app php artisan foundation:reset --force
```

### Feature URLs (foundation proof)

| Surface | Path | Expected Nexi (target) |
|---|---|---|
| Staff home | `/staff` | staff profile + workspace/home pack; degraded or inquiry_only |
| Setup hub | `/setup` | staff profile + setup readiness pack; Inquiry default |
| Applicant portal | `/applicant/portal` | applicant profile + portal pack; no staff tools |
| Exam sessions (no pack yet) | `/exams/sessions` | staff profile; **degraded**; `page_pack: null` |
| Public / login | `/`, `/login` | no authenticated Nexi dock |

### Expected starting state after reset

- Manual scheduling remains available (prior OpenSpec).
- Nexi chrome may already appear in degraded demo mode today; after this change it must follow **server** `NexiSessionView` only.
- No LLM/provider calls; no Apply confirm mutations from Nexi.

### Proof commands (when implemented)

```bash
docker compose exec -T app php artisan test --compact --filter=Nexi
# plus Vitest/Playwright targets named in tasks C5
```

## Pack vs cage (amended)

Dock remains on mountable staff/applicant surfaces even without a page pack (e.g. `/exams/sessions`). Pack only adds page identity/suggestions when registered. Cross-page tools, durable chat history, and confirm modals need later OpenSpecs; degraded replies are local mock (no provider).

## Explicitly next (do not fold in)

Do **not** start coding the next change until this OpenSpec is archived PASS (task D3). Sequence authority: [`READINESS-PLAN.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/READINESS-PLAN.md) §17.

| # | Change ID | Delivers | Still deferred |
|---|---|---|---|
| 2 | `ai-capability-kernel` | Registry/dispatch + `setup.readiness.read@1` parity | History, SSE, provider, Apply |
| 3 | `nexi-run-and-usage-governance` | Conversations/runs/SSE/usage/TaskGrants/proposals shell | Live provider, Apply mutations |
| 4 | `nexi-knowledge-governance` | Knowledge CRUD + fake index + citations fixtures | Live RAG quality |
| 5 | `ai-provider-live-spike` | `laravel/ai` behind AiGateway (synthetic) | Mutation tools |
| 6 | `nexi-policy-inquiry-rag` | Policy corpus + citation Inquiry | Own-data embed |
| 7 | `nexi-applicant-inquiry` | Live applicant Inquiry | Staff tools / applicant mutate |
| 8 | `nexi-exam-session-assistant` | Exam packs + **confirm modals** + 2–3 commands | Broader mutation surface |

### Question → which OpenSpec

| Staff ask | OpenSpec |
|---|---|
| Dock missing on a page? | Done in OpenSpec 1 (degraded without pack) |
| Real tools / setup readiness via Nexi? | **2** Kernel |
| Chat history across pages / refresh? | **3** Run governance |
| Confirm modal before Apply? | **8** (needs 2–3 first); shell exists but idle until proposals |
| Real AI answers (not local mock)? | **5** Provider spike (after 3–4) |
| Policy Q&A with citations? | **6** then **7** for applicants |

### Immediate next action after D3 PASS

1. Archive this change under `openspec/changes/archive/YYYY-MM-DD-ai-assistant-foundation/`
2. Draft **only** `openspec/changes/ai-capability-kernel/` (proposal/design/tasks/HANDOFF/spec)
3. Keep one active OpenSpec; do not fold run/provider/exam into Kernel


## Junior pointer

Start with [`JUNIOR-IMPLEMENTATION-GUIDE.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/JUNIOR-IMPLEMENTATION-GUIDE.md) §§3–4 and Slice 1; do not implement Slices 3–9 under this OpenSpec.

## Implementation progress (2026-08-10)

### Done (server)
- `App\Ai\Surface\*` — SurfaceProfile catalog + resolver
- `App\Ai\Context\PageCapabilityPack*` — first three packs
- `App\Ai\Policy\EffectiveNexiAvailabilityResolver`
- `App\Ai\Http\NexiSessionView` + builder
- `HandleInertiaRequests` shares transitional `nexi` (BC chrome fields + `nexi.session` contract)
- Pest: `tests/Feature/Ai/NexiFoundationSessionTest.php`
- Config: disclosure key/version; Inquiry default

### Done (frontend)
- `NexiDock` + `types/nexi.ts`
- Staff + applicant layouts mount server session only
- Client stubs demoted; Vitest + Playwright public checks


