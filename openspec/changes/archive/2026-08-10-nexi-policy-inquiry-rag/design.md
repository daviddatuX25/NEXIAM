# Design: Nexi Policy Inquiry RAG

## Flow

```text
TurnSubmitter → ProcessNexiRun
                  │
                  ├─ KnowledgeRouter.resolvePolicyInquiry(query, trusted_context)
                  │     └─ KnowledgeIndexPort.retrieve(query, limit, filters)
                  │
                  ├─ events: knowledge.retrieval
                  │
                  ├─ if no hits → assistant.refusal (no_support) [optional short static text]
                  │
                  └─ else → AiGateway.stream(prompt + evidence) → citations event + message
```

## Trusted context (never from client)

- `actor_type` (staff first)
- `institution_id`
- `audiences` allow-list (staff: `staff_and_applicant_inquiry`, `staff_only`)
- `purpose` = `policy_inquiry`

## Index

`LocalKnowledgeIndexPort` (selected): active entries only + source audience/institution predicates + lexical match. Replaces Fake as default binding; Fake remains for pure unit isolation if needed.
