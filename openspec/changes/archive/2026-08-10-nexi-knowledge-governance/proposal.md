# Change Proposal: Nexi Knowledge Governance

**Change ID:** `nexi-knowledge-governance`  
**Status:** ACTIVE  
**Phase:** E3 OpenSpec 4 of the Nexi sequence  
**Depends on:** `nexi-run-and-usage-governance` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-nexi-run-and-usage-governance/`

## Intent

Govern institutional **policy knowledge** as versioned, reviewable, revocable corpus — with private quarantine, fake index port, citation/refusal fixtures, and immediate retrieval denial on revoke — **without** claiming semantic RAG quality or installing a live provider.

## Legacy mistakes we explicitly reject

| Legacy pattern | Greenfield rule |
|---|---|
| `KnowledgeDocument` + soft `is_active` | Versioned lifecycle states (AD-25); never a boolean “live” toggle as authority |
| Direct Mixedbread sync as corpus path | `KnowledgeIndexPort` behind app code; fake only in this cut; no vendor SDK types |
| Spatie/`authorize()` as decision authority | GF-21 `CapabilityAuthorizer` + Capability Kernel commands |
| Upload → immediately retrievable | Quarantine → extract → review → approve (digest) → index → active |
| Same admin edits persona + docs casually | Typed audited transitions; SoD: creator ≠ approver for publish |
| Retrieval without citations/refusal | Citation fixtures required before active; revoke denies sync |
| Operational/applicant PII in vectors | Policy corpus only here (AD-12); own-data stays Queries |

## In scope

1. Knowledge source + version CRUD lifecycle + staff admin UI (list → create → show/review)
2. Private quarantine storage + fake safe parser worker + extraction artifact
3. Approval with publication digest + required citation/refusal fixtures
4. Fake `KnowledgeIndexPort` publish/supersede/revoke/reindex
5. Synchronous retrieval denial on revoke; reconcile job for derived index cleanup
6. Kernel-registered read of active source catalog (staff knowledge admin surface)
7. Backup/restore **manifest note** (evidence) — not full ops tooling

## Non-goals

| Deferred | Next |
|---|---|
| Live embeddings / Mixedbread / laravel/ai | `ai-provider-live-spike` / later RAG |
| Semantic RAG quality claims | `nexi-policy-inquiry-rag` |
| Applicant live Inquiry | `nexi-applicant-inquiry` |
| Exam Apply mutations | `nexi-exam-session-assistant` |

## Authority

- Spine AD-12, AD-16 (§ knowledge), AD-25, AD-26
- READINESS-PLAN §17 OpenSpec 4; Junior Slice 4
- Stack: Inertia 3 + Svelte 5; GF-21 authz

## Exit

- Staff can create draft → extract → approve (other actor) → publish → revoke
- Fake index admits only active versions; revoke immediately denies retrieve
- Pest proves SoD, fixture invalidation, revoked denial, no Mixedbread dependency
