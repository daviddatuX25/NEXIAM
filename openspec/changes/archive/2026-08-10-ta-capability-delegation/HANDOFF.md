# Handoff: TA Capability Delegation

**Date:** 2026-08-10  
**Active OpenSpec:** `openspec/changes/ta-capability-delegation/`  
**Checkpoint:** IMPLEMENTATION COMPLETE — ready for founder PASS / archive  
**Prior:** `archive/2026-08-10-exam-proctor-duty-assign/` PASS  

## Demo logins

| Actor | Login | Notes |
|---|---|---|
| Delegate authority | `guidance.counselor@nexiam.test` / `password` | `guidance.ta.delegate` + inherent TA |
| Eligible recipient | `guidance.clerk@nexiam.test` / `password` | grant TA → can assign Proctors; can create guidance support |
| Eligible recipient | `guidance.support@nexiam.test` / `password` | may receive TA |
| Office support create | `registrar.clerk@nexiam.test` / `password` | can create registrar support; no TA delegate |
| Denied | `hr.admin@nexiam.test` / `password` | no TA delegate (permanent-role HR only) |

## Click path

1. Any staff → **Staff** nav → see office directory (read-only unless you manage personnel)
2. Counselor → `/staff/personnel` → see TA column → **Grant TA** on clerk  
3. Clerk → `/exams/sessions/{id}` → **Proctor duties** → assign support as Proctor  
4. Counselor → **Revoke TA** on clerk → clerk proctor-assign → 403  
5. HR sees all offices + full account actions, but **no TA actions** (no delegate cap)
6. Clerk sees own row: Edit/Deactivate disabled + **Manage Profile** → `/staff/profile`
7. Clerk → Counselor Edit/Deactivate → 403 (higher rank); Clerk → peer Clerk → 403; Clerk → Support → allowed
8. Clerk editing Support: no password field (leads/HR only); Counselor/HR reset passwords fine  

## Recreate

```bash
cd system
docker compose build app && docker compose up -d --force-recreate app queue
docker compose exec app php artisan foundation:reset --force
```

## Proof

```bash
docker compose exec app php artisan test --compact \
  tests/Feature/Staffing/TaCapabilityDelegationTest.php \
  tests/Feature/Examination/ExamProctorDutyAssignTest.php
```

Evidence: `docs/greenfield-foundation/evidence/ta-capability-delegation-2026-08-10.md`

## Rank + password model lock

- Rank: support (1) < clerk (2) < office lead (3) < HR (4); office actors manage strictly-lower ranks in own office only.
- Self-edit/self-deactivate are profile-page concerns, never directory actions.
- Password reset: HR (anyone) + office leads (own office, lower rank); self-service password → profile page (follow-up cut).

## Model lock

- `guidance.ta.delegate` is **outside** the TA set (no chain-grant).  
- Grant = direct Spatie permissions for `testAdministratorCapabilities()` — never a `test_administrator` role.  
- Proctor assign still `guidance.exam.proctor.assign` only (D-022).
