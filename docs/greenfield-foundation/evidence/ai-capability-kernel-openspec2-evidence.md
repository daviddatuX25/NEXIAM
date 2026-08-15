# Evidence: ai-capability-kernel OpenSpec 2

**Date:** 2026-08-10  
**OpenSpec:** `openspec/changes/ai-capability-kernel/`  
**Scope:** Capability Registry/Kernel + `setup.readiness.read@1` HTTP parity (no provider / runs / mutations)

## Server

- `App\Application\Capabilities\*` — definition, registry, kernel, schema subset, channel projection, RulePublication seam, ConstraintEvidence
- Handler: `Handlers\SetupReadinessReadHandler` → `ComputeSetupReadinessQuery` (domain remains owner)
- Authz: **any_of** `registrar.setup.manage` | `guidance.assessment.catalog` | `program.quota.manage` (matches Setup hub gate); pack never grants
- `SetupController@index` invokes Kernel for readiness prop
- `AppServiceProvider` registers `CapabilityRegistry` singleton from `CapabilityDefinitions`

## Tests

```bash
docker compose exec -T app php artisan test --compact \
  tests/Feature/Capabilities/CapabilityKernelTest.php \
  tests/Feature/Setup/SetupHubTest.php
```

**Result:** 14 passed (94 assertions) — 2026-08-10

Covers: duplicate registry fail, HTTP/query parity, pack-null allow, staff deny, non-staff Authenticatable deny, unknown field reject, Setup hub through Kernel, read-only kinds.

## Pack-as-lens (AD-3)

Kernel meta records `pack_used_for_authz: false`. Pest proves authorized invoke succeeds with `pagePackKey: null`.

## Explicitly not in this cut

Provider SDK, conversation/SSE/TaskGrants, propose/command registry entries, RAG, confirm-to-Apply.

## ARCHIVED PASS 2026-08-10

OpenSpec moved to `openspec/changes/archive/2026-08-10-ai-capability-kernel/`.
Active = `nexi-run-and-usage-governance`.
