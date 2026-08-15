# Design: AI Assistant Foundation (OpenSpec 1)

## 1. Problem

Nexi chrome is locked and useful, but authority still leaks toward the browser (`nexi-context.js`, `nexi-actions.js`, URL parsing, demo usage counters). Full AI (provider, RAG, tools, mutations) is planned and reviewed, but must not land in one change.

## 2. Solution shape (this cut only)

```text
Authenticated request
  → resolve SurfaceProfile (server; deny-on-ambiguity)
  → resolve PageCapabilityPack from matched Laravel route name
  → build EffectiveAvailability (flags ∩ profile ∩ pack ∩ health stub)
  → project NexiSessionView onto Inertia `nexi` share
  → Svelte dock renders degraded / inquiry_only / hidden / quota presentation
```

No conversation transport, no AiGateway calls, no Capability Kernel dispatch.

## 3. Surface profiles

| Key | Default availability | Context ceiling (this cut) | Hard exclusions |
|---|---|---|---|
| `staff` | `degraded` or `inquiry_only` | Staff shell + non-sensitive page packs | Applicant credentials; break-glass data |
| `applicant` | `degraded` or `inquiry_only` | Applicant portal pack | Staff packs/history/tools |
| `proctor_session` | `hidden` (unless duty pack later) | None in this cut | Broad staff history |
| `knowledge_admin` | `hidden` | None until knowledge OpenSpec | Prompt/tool editing |
| `operations` | `hidden` | None | Applicant content |
| `break_glass` | `hidden` | None | General Nexi |

**Precedence (compiled fixtures):** active break-glass → operations route → knowledge-admin route → active proctor duty → authenticated applicant → ordinary staff. Ambiguous/unknown **surface** → fail closed (`hidden`). Unknown **page pack** on a mountable surface → `degraded` with `page_pack: null` (no tool inheritance).

Role strings and client URLs do **not** choose the profile.

## 4. Page Capability Packs (first pages only)

Canonical identity = **matched Laravel route name**.

| Route family | Pack key (proposed) | Modes | Notes |
|---|---|---|---|
| Staff home / workspace | `staff.workspace.home@1` | inquiry | Guide/manual fallbacks only |
| Setup readiness hub | `staff.setup.readiness@1` | inquiry | Links to ordinary `/setup` path; no AI writes |
| Applicant schedule home (`applicant.schedule`; `applicant.portal` redirects here) | `applicant.portal.home@1` | inquiry | Static/policy help copy; no own-data LLM |
| Other authenticated staff/applicant routes | `page_pack: null` | inquiry (degraded) | Dock stays; no inherited pack/tools — actor ceiling later via Kernel |

Packs filter relevance and disclosure. They **never** grant capabilities the actor lacks. This cut may register empty capability/skill lists.

## 5. NexiSessionView contract

Versioned DTO (`contract_version: '1'`) shared as nullable Inertia `nexi`:

- `surface` `{ key, version }`
- `context_epoch` (opaque server id; may be ephemeral UUID this cut)
- `page_pack` `{ key, version } | null`
- `publication_version` (nullable until RulePublication lands)
- `availability`: `hidden` \| `inquiry_only` \| `assistant_available` \| `assistant_active` \| `degraded` \| `quota_exhausted` \| `suspended`
- `modes` / `active_mode` (Inquiry default; Assistant not activatable without later consent OpenSpec)
- `disclosure` `{ key, version, text }`
- `conversation` cursors nullable / null ids
- `usage` presentation from server policy stub (not demo env counters as authority)
- `proposals` empty array
- `manual_fallbacks` safe `UiIntent` stubs (e.g. navigate to setup / policy link)

Unknown `contract_version` → dock degraded + diagnostics; never crash the page.

PHP DTO + checked TypeScript type land together. Exact field optionality is fixed in tasks/spec scenarios.

## 6. Frontend

| Piece | Behavior |
|---|---|
| Shared dock | Keep locked wrap-island chrome (`NexiAssistPanel` or thin adapter) |
| Staff layout | Mount via staff `SurfaceProfile` |
| Applicant layout | Mount same presentation via applicant profile (partitioned; no staff history) |
| Client state | Expanded/minimized, reduced-motion, draft input, focus only |
| Stubs | `nexi-context.js` / `nexi-actions.js` marked demo/fixture; not used for production decisions |
| Apply chips | Hidden or inert in degraded/inquiry_only; no confirm-to-command |

## 7. Effective policy (stub)

Deny-dominant intersection for this cut:

1. `NEXI_ENABLED` / emergency stub  
2. Surface profile ceiling  
3. Page pack allowed modes  
4. Provider health = forced degraded/unavailable (no provider)  
5. Budget = presentation-only stub  

No durable usage ledger yet.


## 7b. Pack vs actor authority (amended 2026-08-10)

Page packs are a **relevance lens**, not a cage:

- Mountable `SurfaceProfile` → Nexi stays available (`degraded` / later `inquiry_only`) even when `page_pack` is null.
- `hidden` only for disabled flag, non-mountable surface, pack/surface mismatch, or suspension.
- What the model may *do* later is the actor’s Capability Registry ∩ GF-21 (and TaskGrants for cross-page skills), not “only tools listed on this page.”
- Fail-closed for unknown routes means: do not inherit the previous page’s pack/tools — not “remove the dock.”

Conversation history across pages, confirm-to-command modals with live proposals, and provider/mocks beyond local degraded replies belong to later OpenSpecs (`nexi-run-and-usage-governance`, `ai-capability-kernel`, `ai-provider-live-spike` / exam Assistant). Foundation chrome may still show the Apply *gate UI shell* only when a proposal exists; OpenSpec 1 exposes no mutate tools.

## 8. Suggested ownership (implement under OpenSpec tasks)

```text
system/app/Ai/Surface/          SurfaceProfile catalog + resolver
system/app/Ai/Context/          Page pack registry + route compiler
system/app/Ai/Policy/           Effective availability stub (no ledger)
system/app/Ai/Http/             NexiSessionView builder (shared from HandleInertiaRequests)
system/resources/js/types/      NexiSessionView + availability unions
system/resources/js/Components/kit/Nexi/  Shared dock adapter
```

Do **not** create `Ai/Adapters/LaravelAi`, conversation tables, or Kernel registry in this change.

## 9. Tests (minimum)

| Layer | Proof |
|---|---|
| Pest | Surface precedence; applicant vs staff distinct props; unknown route fail-closed; public/login `nexi` null/absent |
| Contract | PHP/TS fixture parity for `NexiSessionView` |
| Vitest | Dock state matrix for availability enum |
| Playwright | Staff + applicant: dock visible/hidden, keyboard, reduced-motion; no network to provider hosts |

## 10. Persona / disclosure (bounded for this cut)

- Name: **Nexi** (assistant disclosure)  
- Disclosure text remains server-owned; animation conveys no authority  
- Full tone/refusal/escalation product contract can deepen in Inquiry OpenSpecs; this cut ships the disclosure field + Inquiry-default behavior  

## 11. Explicit stop rules

Stop and open a different OpenSpec if a task would:

- install `laravel/ai` or any provider SDK  
- persist conversations/runs/proposals  
- expose mutate/propose tools  
- embed RAG / pgvector  
- let the client invent mode, pack, or capabilities from the URL  
