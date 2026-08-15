# Evidence: nexi-knowledge-governance (OpenSpec 4)

**Date:** 2026-08-10  
**Claim:** Governed knowledge lifecycle + fake index ship without Mixedbread / soft `is_active` / Spatie-as-GF-21.  
**Non-claim:** No semantic RAG quality, no live embeddings, no applicant Inquiry.

## Commands run (Compose `nexiam-system-app-1`)

```text
php artisan migrate --force
php artisan db:seed --class=CapabilityCatalogSeeder --force
php artisan test --compact tests/Feature/Ai/NexiKnowledgeGovernanceTest.php tests/Feature/Capabilities/CapabilityKernelTest.php
→ 13 passed (93 assertions)
```

## Anti-legacy checks proven in Pest

| Legacy mistake | Proof |
|---|---|
| Soft `is_active` | Status machine; draft/review never returned by retrieve |
| Same-user approve | Creator approve denied (`separation_of_duties`) |
| Missing fixtures | Approve throws `fixtures_required` |
| Digest drift | Fixture mutate after approve → `digest_invalidated` |
| Revoke lag | Revoke → retrieve empty synchronously |
| Mixedbread dep | `composer.json` has no Mixedbread/`laravel/ai`; port is Fake |

## Surfaces

- Routes: `/knowledge`, `/knowledge/create`, `/knowledge/{source}` + extract/approve/publish/revoke  
- Caps: `knowledge.sources.manage`, `knowledge.sources.approve`  
- Kernel: `knowledge.sources.list@1`, `knowledge.source.publish@1`, `knowledge.source.revoke@1`
