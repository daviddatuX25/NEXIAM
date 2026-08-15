# Handoff: Nexi Policy Inquiry RAG (OpenSpec 6)

**Date:** 2026-08-10  
**Archived OpenSpec:** `openspec/changes/archive/2026-08-10-nexi-policy-inquiry-rag/`  
**Checkpoint:** **PASS 2026-08-10** (founder authorized next cut)  
**Prior:** `archive/2026-08-10-ai-provider-live-spike/` PASS  

## Index contract

Local lexical retrieve (`LocalKnowledgeIndexPort`) — **not** Mixedbread, **not** pgvector yet.

## Seeds

| Actor | Login |
|---|---|
| Registrar admin | `registrar.admin@nexiam.test` / `password` | manage + approve (can self-approve) |
| Guidance counselor | `guidance.counselor@nexiam.test` / `password` | manage + approve (can self-approve) |
| Clerks / support | e.g. `registrar.clerk@nexiam.test` | manage drafts only — need admin/counselor approve |

App: `http://127.0.0.1:18000` · Knowledge: `/knowledge` · Ask on `/staff`

## Demo

1. As registrar **or** guidance: Add source → lands **active** automatically (no Approve click)  
2. Ask Nexi on `/staff` about that policy → citations  
3. Revoke → ask again → no citations / refusal path  
4. Clerks: create stays `review_required`; Approve is 403 until admin/counselor acts

## Proof

- Pest: `tests/Feature/Ai/NexiPolicyInquiryRagTest.php`  
- Evidence: `docs/greenfield-foundation/evidence/nexi-policy-inquiry-rag-2026-08-10.md`

## Next after PASS

`nexi-applicant-inquiry`

## Knowledge authz (founder 2026-08-10)

| Actor | Caps | Create flow |
|---|---|---|
| Registrar admin / Guidance counselor | manage + approve | **one-step** create → extract → approve → active |
| Clerks / support | manage only | draft → `review_required` until admin/counselor approves |

No peer SoD between admins for self-authored drafts.
