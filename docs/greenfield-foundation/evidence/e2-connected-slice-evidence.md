# E2 Connected Slice — Implementation Evidence (in progress)

**Date:** 2026-08-05  
**OpenSpec:** `openspec/changes/e2-connected-admission-release-slice/`  
**Runtime:** `system/` (Laravel 13 / Inertia 3 / Svelte 5)

## Status

**Implementation scaffold + command spine landed.** Pest feature evidence passes for admission, exam access, grading access, release/notify, and authz regression. Full Playwright connected journeys and live Compose migrate/seed spot-check remain for exit gate H.

## What was built (product features)

| Feature | Entry | Proof |
|---|---|---|
| Public apply | `GET/POST /apply` | AdmissionFlowTest submit + hashed challenge |
| Registrar queue accept/dismiss/correct/reopen/archive | `/admissions*` | Denial + accept + reopen non-destructive tests |
| Applicant setup + portal | `/applicant/setup`, `/applicant/portal` | Portal own-record scoping test |
| Exam create/assign/publish/extend/close | `/exams/sessions*` | ExamSessionAccessTest + release flow close |
| Proctor duty + attendance/submission | `/proctor*` | Duty deny + attendance idempotency |
| Manual grading + finalize | `/grading*` | GradingAccessTest + release flow scores |
| Consultation + prepare + release/unrelease | `/consultation*`, `/release*` | ReleaseFlowTest notify-after-commit |
| Journey seed fixture | `JourneyFixtureSeeder` | Called from DatabaseSeeder |

## Legacy mistakes avoided

- CapabilityAuthorizer / capability middleware — not role-string middleware  
- Access challenges stored as `token_hash` only (sha256)  
- Reference codes via locked counter (`admission_reference_counters`)  
- Reopen does not delete `ApplicantIdentity` / history  
- Notifications via outbox after commit with idempotency keys  
- Attendance/submission idempotency keys  

## Pest evidence (2026-08-05)

```text
tests/Feature/Admission + Examination + Assessment + Release + Authorization + Auth
→ 24 passed (97 assertions)
```

Run:

```bash
cd system
docker compose run --rm --no-deps -v "$PWD":/var/www/html \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app \
  php artisan test --compact \
  tests/Feature/Admission tests/Feature/Examination \
  tests/Feature/Assessment tests/Feature/Release \
  tests/Feature/Authorization tests/Feature/Auth
```

## Still open for E2 delivery PASS

- G2 Playwright responsive/keyboard journeys  
- G3 audit tamper verification fixture beyond existing chain hash  
- G4 Playwright console/network clean report on connected journey  
- H1–H4 full REQ-VS → artifact map + archive after PASS  
- Live Compose `foundation:reset` + migrate on baked image (rebuild app image after JS/PHP land)

## Gated non-goals (unchanged)

OMR, Nexi RAG/provider, offline proctoring — E3 OpenSpecs only.

## REQ-VS → Pest map (initial)

| REQ-VS | Evidence |
|---|---|
| 001, 016 | JourneyFixtureSeeder + foundation seed path |
| 002, 019 | AdmissionFlowTest hashed challenge; setup routes |
| 003 | ReleaseFlowTest applicant portal scoping |
| 004 | Accept/dismiss/reopen tests |
| 005–007, 020 | Exam create/assign/publish; attendance idempotency; extend/close actions |
| 008–009, 017, 021 | Manual score + finalize in release flow; grading access deny |
| 011–015, 022–023, 030 | Consultation + prepare + release + outbox sent |
| 010, 024–025, 029 | Explicit non-goals / holds (G5) |
| 026–028 | page_state on Inertia pages |
| 018, 027, 031 | **Open** — Playwright G2/G4 |
| 015 tamper | Chain hash exists; dedicated tamper fixture **Open** G3 |
