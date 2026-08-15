# Handoff: Exam Navigation Organization

**Date:** 2026-08-12
**OpenSpec:** `openspec/changes/2026-08-11-exam-navigation-organization/` (IMPLEMENTATION COMPLETE; archival validation blocked)
**Status:** implementation, automated proof, and manual click-through complete; package still lacks the delta spec required by strict OpenSpec validation

## Summary

Separates exam **scheduling** from exam **conduct/monitoring**, houses proctor duty work
inside **Exam Monitoring** (standalone Proctor page removed), renames the Grading nav label
to **Scoring**, and groups the sidebar by domain.

- **Scheduling** (`/exams/sessions`) owns the session plan: create, edit, assign, publish.
- **Monitoring** (`/exams/monitoring`, `/exams/sessions/{id}/monitoring`) owns conduct:
  roster, attendance, submission — Test Administrator (schedule capability, D-016) sees all
  conduct sessions; a proctor-duty holder (D-015) sees only sessions where they hold an
  active duty and can record attendance/submission for those sessions only.

## Demo logins (existing seed accounts — reused)

| Actor | Login | In this cut |
|---|---|---|
| Test Administrator | `guidance.counselor@nexiam.test` / `password` | Exam Scheduling + Exam Monitoring (all sessions, full actions) |
| Proctor duty-holder | `guidance.clerk@nexiam.test` / `password` | Exam Monitoring → only duty sessions, attendance + submission only |
| Proctor duty-holder | `guidance.support@nexiam.test` / `password` | same as above |
| No exam access | `registrar.clerk@nexiam.test` / `password` | sees no Examination group |

App: `http://127.0.0.1:18000`

## Expected starting state

- Scheduling: `/exams/sessions` — session plan list (unchanged behavior).
- Monitoring: `/exams/monitoring` — for the TA, sessions with active statuses; for
  `guidance.clerk@`/`guidance.support@`, **only** sessions where they hold an active duty.
- `/exams/sessions/{id}/monitoring` — roster with attendance + submission per applicant.
- `/proctor` no longer exists (removed nav item + page).
- Sidebar grouping (legacy-pipeline feel, per founder):
  - **Home** → `/staff`
  - **Admissions** → Applications (`/admissions`) · Exam Scheduling (`/exams/sessions`)
  - **Assessment** → Exam Monitoring (`/exams/monitoring`) · Scoring (`/grading`) · Release (`/release`) · Consultation (`/consultation`)
  - **System** → Setup (`/setup`) · Knowledge (`/knowledge`) · Staff (`/staff/personnel`)
- `Grading` label renamed to `Scoring` (routes/URLs unchanged: `/grading`).
- `UI Kit` (`/ui-kit`) is a dev-only page and is **not** in the sidebar nav.

## Recreate (fresh demo data)

```bash
cd system
docker compose build app && docker compose up -d --force-recreate app queue
docker compose exec app php artisan foundation:reset --force
docker compose exec app php artisan db:seed --class=Database\\Seeders\\MonitoringDemoSeeder
```

The monitoring seeder creates `EX-MON-1` (published, inside its conduct window) with two
accepted applicants, active proctor duties for `guidance.clerk@` and `guidance.support@`,
and one present+submitted / one absent roster so the monitoring page shows both states.

> **Capability drift:** if a feature adds a new capability to `CapabilityCatalog` and the
> dev DB predates it, gated routes 403 (e.g. notification bell — `notify.read.own` was
> missing on stale DBs). Fix without wiping data:
> `docker compose exec app php artisan capabilities:sync`

## Refactor note (Setup surface)

`SetupController` (852 LOC god controller) split into per-resource controllers —
URLs, route names, Inertia components, and capability gates unchanged:

- `SetupDashboardController` — `/setup` readiness hub
- `ProgramController`, `RoomController`, `IntakeGradeFieldController` — registrar catalog
- `AcademicCycleController` — cycle lifecycle (largest, ~243 LOC, one coherent domain)
- `ApplicationWindowController` — window upsert
- `PrivacyPolicyController` — privacy catalog
- `AptitudeAreaController` — TA-gated aptitude + scoring
- `ProgramQuotaController` — program-head/guidance quotas

Shared gates live in `app/Http/Controllers/Setup/Concerns/HasSetupCapabilities`;
13 inline `validate()` blocks moved to `app/Http/Requests/Setup/*`.
`Institution::current()` is the single source for the single-institution deployment.
Guarded by `tests/Feature/Setup/SetupRouteBindingTest.php` (28 route→controller assertions).

## Refactor note (Admission surface)

`AdmissionController` (432 LOC) split by surface — URLs, route names, Inertia
components, and capability middleware unchanged:

- `ApplicantApplyController` — public apply flow: `/apply` (form), submit, confirmation
- `AdmissionReviewController` — registrar reads: `admissions.index` + `admissions.show`
  (gated `registrar.applications.view`)
- `AdmissionActionController` — the 9 state transitions (accept, dismiss, correct,
  request-correction, authorize-amendment, reconsider, reopen, archive, reissue-access-key)

5 inline `validate()` blocks moved to `app/Http/Requests/Admission/*`
(`RequestApplicationCorrectionRequest`, `ReconsiderAdmissionRequest`, `ReopenAdmissionRequest`,
`ArchiveAdmissionRequest`, `ReissueAccessKeyRequest`). The shared programs/grade-fields
catalog was extracted to `app/Admission/Queries/IntakeCatalogQuery` (used by both the
apply form and the attempt detail page). Guarded by
`tests/Feature/Admission/AdmissionRouteBindingTest.php` (14 route→controller assertions).

## Refactor note (Proctor → ProctorDuty)

`ProctorController` renamed to `ProctorDutyController` — the surface is duty-scoped
exam conduct (D-015/D-016) under Exam Monitoring, not a standalone "proctor" page:

- `/exams/sessions/{session}/proctor` → `/exams/sessions/{session}/proctor-duty`
  (`proctor.assign` → `proctor-duty.assign`)
- `/proctor/duties/{duty}/revoke` → `/proctor-duty/{duty}/revoke`
  (`proctor.revoke` → `proctor-duty.revoke`)
- Attendance/submission routes unchanged (`exams.sessions.monitoring.*`)
- Test route references updated across Admission, Examination, Staffing, Notifications suites;
  guarded by `tests/Feature/Examination/ExamRouteBindingTest.php` (22 route→controller assertions)

## Refactor note (ExamSessionController → Scheduling/Monitoring split)

`ExamSessionController` (732 LOC god controller) split along the scheduling-vs-monitoring
seam — URLs, route names, Inertia components, and capability middleware unchanged:

- `ExamSessionController` — scheduling surface (list, create, show, edit, CRUD, lifecycle
  publish/unpublish/cancel/close/extend, roster assign/remove/reassign, applicant
  reservations). Now a thin delegate: reads go to `ExamSessionListQuery` +
  `ExamSessionDetailQuery`; 8 inline `validate()` blocks moved to
  `app/Http/Requests/Examination/*` (`CloseExamSessionRequest`, `UnpublishExamSessionRequest`,
  `CancelExamSessionRequest`, `ExtendExamSessionRequest`, `ReassignExamRequest`,
  `OpenReservationRequest`, `CloseReservationRequest`, `RemoveAssignmentRequest`).
- `ExamMonitoringController` — the two conduct methods (`exams.monitoring`,
  `exams.sessions.monitoring`) moved here with their duty-gating logic (D-015/D-016).
- `app/Examination/Support/ExamSessionPresenter` — shared listPayload/detailPayload/options/
  publishBlockReason shapes (publishBlockReason is a UX pre-check only; the action is the
  authority).
- `app/Examination/Queries/MonitoringRosterQuery` — duty-scoped monitoring list + roster.
- Guarded by `tests/Feature/Examination/ExamRouteBindingTest.php` (supersedes the removed
  `ProctorDutyRouteBindingTest`).

## Proof

```bash
docker compose exec app php artisan test --compact \
  tests/Feature/Examination/ExamMonitoringTest.php \
  tests/Feature/Examination/ExamProctorDutyAssignTest.php \
  tests/Feature/Staffing/StaffNavigationTest.php \
  tests/Feature/Admission/AdmissionFlowTest.php \
  tests/Feature/Admission/AdmissionRouteBindingTest.php \
  tests/Feature/Examination/ProctorDutyRouteBindingTest.php
```

## URLs

| Surface | URL |
|---|---|
| Exam Scheduling list | `/exams/sessions` |
| Exam Scheduling detail | `/exams/sessions/{id}` |
| Exam Monitoring index | `/exams/monitoring` |
| Exam Monitoring session | `/exams/sessions/{id}/monitoring` |
| Scoring | `/grading` (label "Scoring") |
