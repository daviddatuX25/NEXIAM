# Handoff: Nexi Applicant Inquiry (OpenSpec 7)

**Date:** 2026-08-10  
**Archived OpenSpec:** `openspec/changes/archive/2026-08-10-nexi-applicant-inquiry/`  
**Checkpoint:** **PASS 2026-08-10** — founder verified own-status Inquiry on applicant dock  
**Prior:** `archive/2026-08-10-nexi-policy-inquiry-rag/` PASS  

## Seeds

| Actor | Login |
|---|---|
| Demo accepted applicant | access key `DEMO-ACPT` + birthdate `2003-09-25` at `/applicant/login` |
| Demo pending applicant | access key `DEMO-PEND` + birthdate `2003-09-25` |
| Staff (policy publish) | `registrar.admin@nexiam.test` / `password` |

App: `http://127.0.0.1:18000`  
Applicant home: `/applicant/schedule` (Nexi dock)  
Knowledge (staff): `/knowledge`

## Recreate

```bash
cd system
docker compose exec app php artisan migrate:fresh --seed --force
# or JourneyFixtureSeeder + CapabilityCatalogSeeder if partial
```

## Demo

1. Staff: publish a `staff_and_applicant_inquiry` policy mentioning a distinctive word  
2. Applicant login → Ask Nexi that word → citation  
3. Ask “what is my application status?” → own-data answer  
4. Ask about scores before release → refused  
5. Clear conversation → new empty epoch  

## Next after PASS

`nexi-exam-session-assistant`

## Founder verification (2026-08-10)

Ask: “WHAT IS MY APPLICATION STATUS”  
Reply observed: application status “accepted” (reference APP-2026-00002) + assigned session / publish-pending schedule summary.
