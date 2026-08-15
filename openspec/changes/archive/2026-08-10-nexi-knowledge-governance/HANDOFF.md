# Handoff: Nexi Knowledge Governance (OpenSpec 4)

> **ARCHIVED PASS 2026-08-10.** Evidence: `docs/greenfield-foundation/evidence/nexi-knowledge-governance-2026-08-10.md`. Active work moved to `openspec/changes/ai-provider-live-spike/`.


**Date:** 2026-08-10  
**Active OpenSpec:** `openspec/changes/nexi-knowledge-governance/`  
**Checkpoint:** **IMPLEMENTATION COMPLETE — awaiting founder D3 PASS** (no live provider / no RAG quality claim)  
**Architecture:** AD-12, AD-25 (reject legacy Mixedbread/`is_active` shortcuts)

## Legacy do-not-repeat (enforced in this cut)

1. No vendor index as corpus authority → `KnowledgeIndexPort` + `FakeKnowledgeIndexPort` only  
2. No soft `is_active` boolean as publish authority → AD-25 status machine on `knowledge_source_versions`  
3. No Spatie policy as GF-21 replacement → `CapabilityAuthorizer` + Kernel list/publish/revoke  
4. No same-user silent publish → SoD: creator ≠ approver (`separation_of_duties`)  
5. No PII/own-data into knowledge vectors → policy text corpus only  

## Seeds / demo path

| Actor | Login | Caps |
|---|---|---|
| Guidance | `guidance.counselor@nexiam.test` / `password` | manage + approve |
| Registrar admin | `registrar.admin@nexiam.test` / `password` | manage only (SoD partner) |

App: `http://127.0.0.1:18000` · Knowledge UI: `/knowledge`

**Clickable SoD flow**

1. `foundation:reset --force` (Compose) then open `/knowledge` as registrar admin  
2. **Add source** with policy text → lands in `review_required` after inline extract  
3. Sign out; sign in as guidance → **Approve** → **Publish to active**  
4. Confirm retrieve only admits `active` (Pest covers); **Revoke** → retrieval empty immediately  

Recreate grants after reset: `php artisan db:seed --class=CapabilityCatalogSeeder --force` (included in foundation seed path if wired).

## Proof

- Pest: `tests/Feature/Ai/NexiKnowledgeGovernanceTest.php` (5) + updated Kernel registry kind test  
- Evidence: `docs/greenfield-foundation/evidence/nexi-knowledge-governance-2026-08-10.md`

## Explicitly next (after founder PASS / archive)

1. `ai-provider-live-spike`  
2. `nexi-policy-inquiry-rag`  
3. applicant Inquiry / exam Assistant  
