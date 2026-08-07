# Handoff: Exam Session Scheduling

**Date:** 2026-08-07  
**Active OpenSpec:** `openspec/changes/exam-session-scheduling/`  
**Prior archived:** `openspec/changes/archive/2026-08-06-institutional-setup-readiness/` (PASS)

## How we work here (do not skip)

1. Read `docs/GREENFIELD-MASTER-REFERENCE.md` + `docs/project-context.md`  
2. One active OpenSpec only (this folder)  
3. Authority: founder decisions / evidence → roadmap → GF → OpenSpec → tests → legacy (evidence only)  
4. Code only in `system/`; Compose runtime (`system/compose.yaml`), not Sail  
5. **Existing features:** open matching legacy Pages/controllers first and **mimic UI/UX** (list → create/edit pages; no inventing layouts). See project-context §3.4 and `.cursor/rules/nexiam-greenfield.mdc`  
6. Spatie roles are helpers; **GF-21 CapabilityAuthorizer** is authz authority  
7. Stop if disposition, authz/scope, page state, idempotency/audit, or OpenSpec coverage is missing  

## What is already done

- Institutional setup hub + shared setup / aptitude TA-only / ProgramQuota (D-087)  
- Academic years list → create/edit (legacy shape); school year + semester  
- Scheduling OpenSpec + legacy crosswalk `docs/legacy-reference/11-exam-scheduling-trace.md`  
- Conflict lock **D-088** (`docs/greenfield-foundation/decisions/D-088-exam-scheduling-conflict-resolutions.md`)  
- Page/state catalog updates for P-EXM + applicant calendar states  

## What to implement next (tasks B1→D2)

Start at **B1** in `tasks.md`:

1. Schema: slot plans / reservations; assignment `source`; **active** uniqueness (D-088 §3)  
2. Staff Index/Create/Edit/Show mimicking `legacy_codebase/application/resources/js/Pages/Admin/TestScheduling/`  
3. Single assign/publish/unpublish/cancel command path (no dual UI/service rules)  
4. ExamSlotPlan open/close/finalize/bypass + applicant calendar reserve  
5. Pest matrix + Compose rebuild + evidence  

## Locked product rules (D-088 summary)

- Unpublish = published→draft; Cancel = terminal; in-progress ends via Close  
- Staff + applicant paths share capacity; no silent overwrite of open reservations  
- Plan ends at finalized/bypassed; **session Publish** = notify boundary  
- Late assign on published allowed; AI assistant / missed-exam policy / proctor time-conflict = out of cut  

## Runtime notes

- App: `http://127.0.0.1:18000` (Compose)  
- After PHP/JS changes: `docker compose build app && up -d --force-recreate`  
- Capability grants: reseed `CapabilityCatalogSeeder` when catalog changes  
- Seed staff examples: `registrar.admin@nexiam.test`, `guidance.counselor@nexiam.test`, `program.head.bsn@nexiam.test` / `password`  

## Read first before coding

1. `openspec/changes/exam-session-scheduling/{proposal,design,tasks,specs}/`  
2. `docs/greenfield-foundation/decisions/D-088-exam-scheduling-conflict-resolutions.md`  
3. `docs/legacy-reference/11-exam-scheduling-trace.md`  
4. Legacy `Admin/TestScheduling/*` + `ExamSchedulingService.php`  
5. GF-06 §5 / §5.1  
