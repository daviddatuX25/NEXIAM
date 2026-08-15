# Evidence — Exam Proctor Duty Assign

**Date:** 2026-08-10  
**OpenSpec:** `openspec/changes/exam-proctor-duty-assign/`  
**Authority:** D-015 (Proctor = session duty), D-022 (`guidance.exam.proctor.assign`), D-016 (TA = capability set)

## What shipped

- `EligibleProctorCandidates` — active Guidance `guidance_clerk` / `guidance_support_staff`, exclude already-active duties.
- `AssignProctorDuty` / `RevokeProctorDuty` — CapabilityAuthorizer + eligibility preflight; audit `exam.proctor_duty_*`; no Spatie `proctor` role sync.
- Session Show props: `can_assign_proctor`, `eligible_proctors`, `proctors[].duty_id`.
- `Examination/Show.svelte` — Proctor duties section (assign select + revoke).
- Seed: `guidance.support@nexiam.test` / `guidance_support_staff`.
- `/proctor` dashboard reachable for assignees with active duty (not only assigners).

## Verification

```text
ExamProctorDutyAssignTest — 3 passed
ExamSessionPagesTest + ExamCapabilitySyncTest — 5 passed
```

Compose rebuild required (baked app image).

## Demo path

Counselor → `/exams/sessions/{id}` → assign clerk or support → revoke. Registrar denied.
