# Handoff: AI Capability Kernel (OpenSpec 2)

> **ARCHIVED PASS 2026-08-10.** Evidence: `docs/greenfield-foundation/evidence/ai-capability-kernel-openspec2-evidence.md`. Active work moved to `openspec/changes/nexi-run-and-usage-governance/`.

**Date:** 2026-08-10  
**Active OpenSpec:** `openspec/changes/ai-capability-kernel/`  
**Checkpoint:** **ARCHIVED PASS 2026-08-10** — Kernel + `setup.readiness.read@1`; next = `nexi-run-and-usage-governance`  
**Architecture authority:** `docs/greenfield-foundation/architecture/ai-assistant-foundation/` (AD-1, AD-2, AD-3)

## Status

| Area | State |
|---|---|
| OpenSpec 1 foundation | Archived PASS → `openspec/changes/archive/2026-08-10-ai-assistant-foundation/` |
| This OpenSpec | **ARCHIVED PASS 2026-08-10** |
| Pack-as-lens (founder 2026-08-10) | **Persisted in spine AD-3** — pack ≠ cage; Kernel authz ≠ page pack list |
| Provider / RAG / runs / mutations | **Not authorized** here |

## Founder note (must not regress)

Page packs are a relevance lens. Mountable surfaces keep Nexi without a pack (`degraded`, `page_pack: null`). Fail-closed = no prior-pack inheritance. Actor Capability Registry ∩ GF-21 decides what may run; TaskGrants (OpenSpec 3) later cover cross-page skill continuity. History / confirm modals / live LLM remain later.

## Developer-ready seeds

### App URL

- `http://127.0.0.1:18000` (Compose `app`)

### Logins

| Actor | How | Credentials |
|---|---|---|
| Guidance (staff) | Staff `/login` | `guidance.counselor@nexiam.test` / `password` |
| Registrar (staff) | Staff `/login` | `registrar.clerk@nexiam.test` / `password` |
| Accepted applicant | Applicant portal | access key `DEMO-ACPT` · birthdate `2003-09-25` |

### Recreate

```bash
cd system
docker compose build app
docker compose up -d --force-recreate app queue
docker compose exec -T app php artisan foundation:reset --force
```

### Feature URLs (Kernel proof)

| Surface | Path | Expected |
|---|---|---|
| Setup hub | `/setup` | readiness via Kernel; pack `staff.setup.readiness@1` |
| Staff home | `/staff` | dock still present; no pack cage |
| Exam sessions | `/exams/sessions` | dock **degraded**, `page_pack: null` (OpenSpec 1 invariant) |
| Applicant | `/applicant/portal` | cannot invoke staff readiness capability |

### Proof commands (when implemented)

```bash
docker compose exec -T app php artisan test --compact --filter=Capability
# plus Setup readiness feature filters named in tasks C3–C4
```

## Explicitly next (do not fold in)

1. `nexi-run-and-usage-governance` — history, SSE, TaskGrants, proposals shell  
2. `nexi-knowledge-governance`  
3. `ai-provider-live-spike`  
4. Inquiry / exam Assistant OpenSpecs  

## Junior pointer

[`JUNIOR-IMPLEMENTATION-GUIDE.md`](../../../docs/greenfield-foundation/architecture/ai-assistant-foundation/JUNIOR-IMPLEMENTATION-GUIDE.md) Slice 2 Kernel portion; AD-1/AD-2/AD-3.


## Implementation progress (2026-08-10)

### Done
- `App\Application\Capabilities\*` registry + Kernel + `setup.readiness.read@1`
- Setup hub readiness via Kernel
- Pest: `tests/Feature/Capabilities/CapabilityKernelTest.php` (+ SetupHubTest still green)
- Evidence: `docs/greenfield-foundation/evidence/ai-capability-kernel-openspec2-evidence.md`

### Proof

```bash
cd system
docker compose cp app/Application/. app:/var/www/html/app/Application/
docker compose cp app/Http/Controllers/Setup/SetupController.php app:/var/www/html/app/Http/Controllers/Setup/SetupController.php
docker compose cp app/Providers/AppServiceProvider.php app:/var/www/html/app/Providers/AppServiceProvider.php
docker compose exec -T app php artisan test --compact tests/Feature/Capabilities/CapabilityKernelTest.php tests/Feature/Setup/SetupHubTest.php
```

Prefer `docker compose build app && docker compose up -d --force-recreate app` so the image retains the Kernel permanently.
