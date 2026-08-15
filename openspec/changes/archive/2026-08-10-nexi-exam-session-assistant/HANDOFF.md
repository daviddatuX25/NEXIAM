# Handoff: Nexi Exam Session Assistant (OpenSpec 8)

**Date:** 2026-08-10  
**Archived OpenSpec:** `openspec/changes/archive/2026-08-10-nexi-exam-session-assistant/`  
**Checkpoint:** PASS 2026-08-10 — founder go-ahead to archive; Pest 6/6; capability sync §0.2; next = proctor/TA assign UI OpenSpec  
**Prior:** `archive/2026-08-10-nexi-applicant-inquiry/` PASS  

## Model lock (read before coding)

Mirror of `docs/project-context.md` §0.1 + this OpenSpec proposal/design:

1. **One auth** — same cookie/session principal for Inertia and `/nexi/*`  
2. **One core** — same `Examination\Actions\*` as the page; no AI-only domain writes  
3. **Capability matrix** — Kernel rows wrap Actions/Queries (scalable; not duplicate endpoints)  
4. **Skill** — allowlist + engine/cursor; MD guidance is non-authoritative  
5. **Projections** — post-auth channel shaping only  
6. **Persistence** — Propose → `nexi_proposals`; Confirm → ordinary Action  
7. Applicant NL booking is **not** this cut (`applicant.choose_slot` later)  

## Seeds

Authority is **capabilities** (GF-21), not role names. Role helpers only seed who holds which caps today.

**Exam pages + Nexi exam Assistant share one gate:** `CapabilityCatalog::examSessionManagementCapabilities()` (`guidance.exam.schedule`), part of the Test Administrator set (D-016). Anyone granted that key — inherent TA (`guidance_counselor`) or delegated schedule holder (`guidance_clerk` / support) — gets the same page and Assistant access. Registrar clerks do not hold it unless explicitly granted.

| Cap / job | Demo login | Where |
|---|---|---|
| `guidance.exam.schedule` (OS8 exam Assistant + session pages) | `guidance.counselor@nexiam.test` **or** `guidance.clerk@nexiam.test` / `password` | `/exams/sessions` |
| Admissions accept (prep eligible attempts) | `registrar.clerk@nexiam.test` / `password` | admissions — **not** exam schedule |
| `guidance.exam.proctor.assign` (assign session proctor duty) | `guidance.counselor@nexiam.test` / `password` today | route exists; **assign UI incomplete** (Show reads proctors only; `/proctor` is stub “my duties”) |
| Applicant (manual fallback) | `DEMO-ACPT` + `2003-09-25` | `/applicant/schedule` |

App: `http://127.0.0.1:18000`

**Do not** treat “counselor” as a code lock — Kernel/routes check `guidance.exam.schedule` / `guidance.exam.proctor.assign`. Anyone granted those caps must work. Registrar clerk lacks exam-schedule by current seed matrix (admissions vs exam ops), which is why Inquiry on clerk fell through to “no policy corpus.”

## Demo (target)

1. Any staff with `guidance.exam.schedule` → session Show → Nexi **Assistant** → skill starts  
2. Ask to open reservation → proposal gate → Confirm → booking open (same as page)  
3. Assign eligible attempt → confirm → roster updates  
4. Publish → confirm → published; applicant sees schedule when applicable  
5. Stop Nexi / degrade → page buttons still work  

## Next after PASS

Later applicant skill `applicant.choose_slot` / cross-domain staff skills — one pack at a time.

## API (this cut)

| Method | Path | Role |
|---|---|---|
| POST | `/nexi/skills/start` | Issue TaskGrant for `schedule.exam_session@1` |
| POST | `/nexi/skills/invoke` | Allowlist ∩ Kernel; propose persists `nexi_proposals` |
| POST | `/nexi/proposals/{id}/confirm` | Re-preflight → Command → Examination Action |
| POST | `/nexi/proposals/{id}/cancel` | Cancel open proposal |

## Evidence

[`docs/greenfield-foundation/evidence/nexi-exam-session-assistant-2026-08-10.md`](../../../docs/greenfield-foundation/evidence/nexi-exam-session-assistant-2026-08-10.md)

```bash
docker compose exec -T app php artisan test --compact tests/Feature/Ai/NexiExamSessionAssistantTest.php
```

## Demo phrases (Assistant mode on session Show)

1. Switch Nexi to **Assistant** (starts `schedule.exam_session@1` with this session id)
2. “Please open reservation for this session” → confirm gate → Confirm
3. “Assign attempt {id}” → confirm
4. “Publish this session” → confirm

Page buttons remain the manual fallback if Nexi is degraded.
