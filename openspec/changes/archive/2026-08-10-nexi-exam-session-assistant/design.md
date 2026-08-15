# Design: Nexi Exam Session Assistant

## Vocabulary (founder-aligned)

| Term | Meaning in this cut |
|---|---|
| One auth | Same cookie/session `User` for Inertia and `/nexi/*`; prompt cannot spoof principal |
| One core | Controllers + Nexi call the same `Examination\Actions\*` — no AI-only domain writes |
| Capability | Kernel registry entry (read/propose/command) wrapping core; GF-21 wall each call |
| Projection | Post-auth channel view of authoritative handler result (not a second rule engine) |
| Skill | **Not MD-as-authority.** Allowlist of capability versions + engine cursor (+ optional guidance digest for the model only) |
| TaskGrant | Persisted binding of skill allowlist to actor/session/surface for multi-turn work |
| Proposal | Durable preview of one Command; confirm sends proposal id + idempotency key only |
| Persistence | Read = no domain write; Propose = `nexi_proposals`; Confirm→Command = ordinary Action |

## Auth path (identical principal)

```text
Request (Inertia OR /nexi/*)
  → same session User
  → SurfaceProfile (staff) + PageCapabilityPack (lens only)
  → [Assistant] TaskGrant for schedule.exam_session@1
  → each tool: CapabilityKernel ∩ GF-21 ∩ grant allowlist ∩ mode
  → handler → core Action/Query
```

Prompt cannot spoof actor. Provider-supplied actor/session fields are ignored.

## Multi-step engine (how allowlist works)

```text
cursor: started
  → allow Reads: list/show
cursor: session_loaded
  → allow Propose open_reservation | assign | publish (as domain state permits)
cursor: awaiting_confirm_*
  → only Confirm(proposal_id) or Cancel
cursor: after Command success
  → next legal proposes / terminal
```

Model output may recommend a tool key; **engine rejects** anything off-allowlist or illegal for the cursor — even if the user has that GF-21 cap globally (e.g. HR caps unused here).

Guidance digest (markdown-like text) may be injected into the system prompt so the model prefers list→show→open→assign→publish; it never grants tools.

## Why not “skill = only MD”

100 capabilities without an allowlist/engine lets the model explore. MD tips help discovery; **server allowlist + cursor** keep multi-step safe and scalable. Skills stay thin: they do not duplicate Assign/Publish logic.

## Proposal / confirm

1. Propose handler runs the **same preflight checks** the Action will (version, eligibility, capacity gates as applicable) → `ConstraintEvidence`
2. Persist `nexi_proposals` with capability_id, expected_version, summary impact, evidence digest, expiry
3. `POST /nexi/proposals/{id}/confirm` with fresh idempotency key
4. Re-run preflight; on drift → invalidate; on pass → Command handler → Action
5. AssistPanel gate stops stubbing — calls confirm API

## Packs + mode

- Staff `SurfaceProfile.allowedModes` includes `assistant` when pack allows it
- Exam packs default inquiry; Assistant requires explicit mode consent (existing UI pattern)
- Availability may move from blanket `degraded` toward `assistant_available` on exam routes when gateway healthy (fake OK for tests)

## Files (ownership)

```text
system/app/Ai/Skills/              ExamSessionSchedulingSkill + SkillEngine
system/app/Ai/Proposals/           ProposalService (create/confirm/invalidate)
system/app/Application/Capabilities/Handlers/Exams/*
system/app/Http/Controllers/Nexi/  NexiProposalController
system/app/Ai/Context/             exam page packs
Examination\Actions\*            unchanged authority
```

## Capability sync (pages ↔ Kernel ↔ seeds)

- Exam session Inertia pages and Kernel exam capabilities both authorize via `CapabilityCatalog::examSessionManagementCapabilities()` (`guidance.exam.schedule`).
- That key is part of the D-016 Test Administrator **set** (`testAdministratorCapabilities()`), not a role name.
- Seeds grant the set (or a delegated subset) through `roleHelperGrants()` — GF-21 `CapabilityAuthorizer` remains decision authority.
- Do not add Nexi-only exam permissions; scale by Action + shared application capability + optional Kernel row.
