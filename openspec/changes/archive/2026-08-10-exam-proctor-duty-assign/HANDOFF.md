# Handoff: Exam Proctor Duty Assign

**Date:** 2026-08-10  
**Archived OpenSpec:** `openspec/changes/archive/2026-08-10-exam-proctor-duty-assign/`  
**Checkpoint:** PASS 2026-08-10 — founder keep D-022; Pest 3/3; next = TA capability delegation (D-016)  
**Prior:** `archive/2026-08-10-nexi-exam-session-assistant/` PASS  

## Model lock

- **Assign proctor** = holders of `guidance.exam.proctor.assign` (TA set), not counselor role middleware.
- **Be proctor** = session duty for Guidance clerk/support only.
- Seeds make demo look counselor-only until TA **delegation** ships — that is intentional, not a role lock.

## Demo logins

| Actor | Login | Notes |
|---|---|---|
| TA (assign proctor) | `guidance.counselor@nexiam.test` / `password` | inherent TA |
| Eligible proctor | `guidance.clerk@nexiam.test` / `password` | duty only |
| Eligible proctor | `guidance.support@nexiam.test` / `password` | support; Proctor-eligible |
| Denied | `registrar.clerk@nexiam.test` / `password` | no proctor assign |

App: `http://127.0.0.1:18000` → `/exams/sessions/{id}` → **Proctor duties**

## Recreate

```bash
cd system
docker compose build app && docker compose up -d --force-recreate app queue
docker compose exec app php artisan foundation:reset --force
```

## Proof

```bash
docker compose exec app php artisan test --compact \
  tests/Feature/Examination/ExamProctorDutyAssignTest.php
```

Evidence: `docs/greenfield-foundation/evidence/exam-proctor-duty-assign-2026-08-10.md`

## Next

OpenSpec `ta-capability-delegation` — grant/revoke full TA set on Guidance clerk/support (D-016); then those holders can assign Proctors.
