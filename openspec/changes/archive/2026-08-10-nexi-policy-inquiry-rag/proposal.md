# Change Proposal: Nexi Policy Inquiry RAG

**Change ID:** `nexi-policy-inquiry-rag`  
**Status:** ACTIVE  
**Phase:** E3 OpenSpec 6  
**Depends on:** `ai-provider-live-spike` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-ai-provider-live-spike/`

## Intent

Staff Inquiry answers **institutional policy** questions using the governed OS4 corpus: auth-before-retrieve via `KnowledgeRouter`, local index contract, **citations or refusal** — no own-data embeddings, no mutation tools.

## Selected local index contract

**Postgres-backed local full-text / lexical retrieve** on `knowledge_index_entries` (active versions only), with audience + institution filters inside the port.  
pgvector / embeddings remain a later benchmark if citation fixtures fail quality gates — not installed in this cut.

## In scope

1. `KnowledgeRouter` for provider-bound policy retrieval (AD-21)
2. Extend `KnowledgeIndexPort::retrieve` with trusted filters
3. Wire retrieve → prompt evidence → run events (`knowledge.retrieval`, `assistant.citations` / `assistant.refusal`)
4. Staff Nexi panel shows citations or refusal
5. Pest: active cite path, revoked denial, no-support refusal, audience fail-closed

## Non-goals

| Deferred | Next |
|---|---|
| pgvector / live embeddings | later ADR if FTS insufficient |
| Applicant Inquiry productization | `nexi-applicant-inquiry` |
| Own-data in index | never (AD-12) |
| Mutation / Apply | exam Assistant |

## Exit

Staff ask about a published policy → cited answer; revoke → no cite; unsupported → refusal; no Mixedbread authority.
