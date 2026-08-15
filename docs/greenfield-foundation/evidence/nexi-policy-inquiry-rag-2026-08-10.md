# Evidence: nexi-policy-inquiry-rag (OpenSpec 6)

**Date:** 2026-08-10  
**Claim:** Staff policy Inquiry retrieves via KnowledgeRouter + local lexical index; cites active corpus; revoke/no-support emit refusal without fabricated citations.  
**Non-claim:** No pgvector/embeddings quality claim; no applicant Inquiry productization.

## Selected index contract

`App\Ai\Adapters\Local\LocalKnowledgeIndexPort` — active versions only, audience/institution/purpose filters inside retrieve.

## Commands

```text
php artisan test --compact \
  tests/Feature/Ai/NexiPolicyInquiryRagTest.php \
  tests/Feature/Ai/NexiRunGovernanceTest.php \
  tests/Feature/Ai/NexiKnowledgeGovernanceTest.php
→ 17 passed (108 assertions)
```

## Events

- `knowledge.retrieval` (support | no_support)
- `assistant.citations` when supported
- `assistant.refusal` when no policy support (orientation reply may still stream without cites)
