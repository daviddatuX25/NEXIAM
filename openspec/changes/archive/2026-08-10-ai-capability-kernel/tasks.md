# Tasks: AI Capability Kernel (OpenSpec 2)

## A — Activate & authority

- [x] A1 Confirm prior OpenSpec archived PASS (`2026-08-10-ai-assistant-foundation`); update `docs/project-context.md` + master checkpoint + archive README
- [x] A2 Re-read AD-1, AD-2, AD-3 (pack-as-lens) before coding; record in HANDOFF that pack is not authz
- [x] A3 Inventory current Setup readiness capability/authz used by `SetupController` (GF-21 path) — reuse, do not fork  
  — Hub gate remains any of `registrar.setup.manage` | `guidance.assessment.catalog` | `program.quota.manage`; Kernel `setup.readiness.read@1` uses the same **any_of** set.

## B — Registry + Kernel

- [x] B1 `CapabilityDefinition` + code-owned registry; duplicate `key@version` fails boot
- [x] B2 `CapabilityKernel` dispatch pipeline (principal → schema → authorize → invoke → project → typed result)
- [x] B3 JSON Schema subset validation (reject unknown fields; no caller-supplied authority)
- [x] B4 Channel projection seam (`http_page` authoritative; `nexi_safe` stub may equal http for readiness read)
- [x] B5 RuleSetRef / ConstraintEvidence / minimal RulePublication compatibility check for registered reads

## C — Reference capability + migration

- [x] C1 Register `setup.readiness.read@1` over `ComputeSetupReadinessQuery` (domain query stays owner)
- [x] C2 Migrate `SetupController` readiness load through Kernel (no parallel direct query as production path)
- [x] C3 Pest: authorized staff parity with golden readiness shape; deny applicant; deny staff without setup capability
- [x] C4 Pest: pack absence does not alter Kernel allow/deny for readiness (actor ceiling independent of `page_pack`)
- [x] C5 Optional staff-home path if it currently loads readiness — same Kernel only  
  — Staff home does not load readiness today; no extra path.

## D — Handoff

- [x] D1 `HANDOFF.md` seeds + URLs + proof commands
- [x] D2 Evidence note under `docs/greenfield-foundation/evidence/`
- [x] D3 Master checkpoint PASS; do not open run/provider/exam OpenSpecs inside this change

## Explicitly out of task scope

Conversations/SSE/TaskGrants, knowledge admin, `laravel/ai`, RAG, propose/command tools, confirm-to-Apply, exam Assistant skills, making page packs an allowlist cage.
