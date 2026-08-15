# Design: Nexi Knowledge Governance

## 1. Lifecycle (AD-25)

```text
draft → extracting → review_required → approved → indexing → active
         ↘ rejected
active → superseded | revoked → deletion_pending → deleted
indexing → index_failed (retry/reconcile)
```

## 2. Ownership

```text
system/app/Ai/Knowledge/           definitions, router, digest, lifecycle
system/app/Ai/Contracts/           KnowledgeIndexPort
system/app/Ai/Adapters/Fake/       FakeKnowledgeIndexPort
system/app/Models/Ai/              KnowledgeSource, KnowledgeSourceVersion, fixtures
system/app/Jobs/Ai/                ExtractKnowledgeVersionJob, IndexKnowledgeVersionJob
system/app/Http/Controllers/Knowledge/
system/resources/js/Pages/Knowledge/
system/app/Application/Capabilities/  knowledge.* registry entries
```

## 3. Authz

| Capability | Who (seed) | Purpose |
|---|---|---|
| `knowledge.sources.manage` | guidance counselor, registrar admin | create/edit drafts, upload quarantine, request review |
| `knowledge.sources.approve` | guidance counselor only (default) | approve / publish / revoke / supersede |

SoD: the user who created the version **cannot** approve it (even if they hold approve capability).

## 4. Fake index

`FakeKnowledgeIndexPort` stores in PostgreSQL `knowledge_index_entries` (derived). Rebuild = delete+reinsert from active versions. Never call external APIs.

## 5. UI (legacy feel, rewritten authority)

Mimic legacy Admin AI Companion list shape: Index list → Create → Show/Edit review actions. No persona prompt editor in this cut (deployment-owned prompts).
