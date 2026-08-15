# Tasks: Nexi Knowledge Governance (OpenSpec 4)

## A — Activate

- [x] A1 Archive OS3 PASS; update project-context + master + archive README
- [x] A2 Record legacy anti-patterns in HANDOFF (Mixedbread, is_active, Spatie-as-authority)
- [x] A3 Add GF-21 capabilities `knowledge.sources.manage` / `knowledge.sources.approve` + seed grants

## B — Persistence & lifecycle

- [x] B1 Migrations: sources, versions, citation fixtures, index entries, quarantine disk
- [x] B2 Models + lifecycle service (legal transitions only)
- [x] B3 Publication digest + fixture bind; change invalidates approval
- [x] B4 Extract job (fake safe parse) + Index job (fake port)
- [x] B5 Revoke → sync deny + async index cleanup reconcile

## C — Kernel + admin UI

- [x] C1 Register Kernel reads/commands for catalog + lifecycle transitions
- [x] C2 Staff Knowledge Index / Create / Show pages (Inertia + Svelte)
- [x] C3 Routes + CapabilityAuthorizer gates; SoD on approve
- [x] C4 FakeKnowledgeIndexPort retrieve fixtures for tests

## D — Proof

- [x] D1 Pest: SoD deny, revoke deny retrieve, fixture invalidate, duplicate fail-closed
- [x] D2 Evidence + HANDOFF
- [x] D3 master PASS (no provider/RAG quality claim) — founder PASS 2026-08-10

## Out of scope

Live embeddings, Mixedbread, laravel/ai, applicant Inquiry, exam Apply.
