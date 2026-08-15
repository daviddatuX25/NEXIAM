# Evidence: Nexi Applicant Inquiry (OpenSpec 7)

**Date:** 2026-08-10  
**Change:** `openspec/changes/nexi-applicant-inquiry/`  
**Verdict:** **PASS 2026-08-10** — founder verified applicant own-status Inquiry

## What shipped

- Applicant turns (`actor_type=applicant`) through `/nexi/turns` with surface partition
- Policy retrieve limited to applicant audiences (staff_only denied)
- `ApplicantOwnDataProjector` for portal-scoped status/schedule; unreleased scores refused
- Conversation clear/segment (`POST /nexi/conversations/clear`) + cross-applicant snapshot denial
- AssistPanel Clear control + FakeAiGateway applicant/own-data replies

## Proof

```bash
cd system
docker compose run --rm --no-deps -v "$PWD":/var/www/html -w /var/www/html -e DB_HOST=postgres \
  app php artisan test --compact \
  tests/Feature/Ai/NexiApplicantInquiryTest.php \
  tests/Feature/Ai/NexiPolicyInquiryRagTest.php \
  tests/Feature/Ai/NexiFoundationSessionTest.php \
  tests/Feature/Ai/NexiRunGovernanceTest.php
```

Result (2026-08-10): **25 passed**.

## Non-goals retained

Applicant mutation Assistant, exam Apply, pgvector embeddings.

## Founder demo

> WHAT IS MY APPLICATION STATUS  
> Your application status is “accepted” (reference APP-2026-00002). You are assigned to a session. Staff will publish the schedule when ready.
