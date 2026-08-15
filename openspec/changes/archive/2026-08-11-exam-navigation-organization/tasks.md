# Tasks: Exam Navigation Organization

## A — Open / sequencing

- [x] A1 Founder decides sequencing: activate this change (park `scoring-conversion` in archive) OR land after `scoring-conversion` ships — activated; scoring-conversion parked
- [x] A2 Confirm Grading→Scoring scope (label only vs URL + route-name rename) — default: label only

## B — Navigation (server)

- [x] B1 `StaffNavigationBuilder`: emit grouped sections with legacy-pipeline labels — Admissions (Applications · Exam Scheduling) / Assessment (Exam Monitoring · Scoring · Release · Consultation) / System (Setup · Knowledge · Staff); UI Kit dropped from nav
- [x] B2 Remove `Proctor` nav item; add `Exam Monitoring` (`/exams/monitoring`) gated by schedule cap OR active duty
- [x] B3 Rename `Grading` label → `Scoring`
- [x] B4 Pest: nav builder unit test (scheduling-only user sees Scheduling+Monitoring; duty-only proctor sees Monitoring filtered; Scoring label)

## C — Monitoring backend

- [x] C1 `ExamSessionController::monitoringIndex`: sessions with active statuses; scope to duty sessions when viewer lacks schedule cap; `page_state` denied/empty
- [x] C2 `ExamSessionController::monitoring`: per-session roster projection (assignments + attendance + submission state); gate schedule cap OR active duty on session
- [x] C3 Rehome attendance/submission POST routes under `/exams/sessions/{session}/monitoring/assignments/{assignment}/...`; update gates to schedule cap OR duty
- [x] C4 Pest: monitoring gates (TA all sessions / proctor own sessions only / unrelated session 403); attendance + submission via new routes; idempotency preserved

## D — Monitoring frontend

- [x] D1 `Examination/MonitoringIndex.svelte` (list + empty/denied states, breadcrumb `Exam Monitoring`)
- [x] D2 `Examination/Monitoring.svelte` (GF-22 §3.3 shell: sticky session header, roster table, per-row attendance/submission controls, idempotency, extend/close for TA only)
- [x] D3 `Examination/Show.svelte`: "Go to monitoring" entry when `published`/`in_progress`

## E — Remove the standalone Proctor page

- [x] E1 Delete `GET /proctor` route + `ProctorController::dashboard`
- [x] E2 Delete `Examination/Proctor.svelte`
- [x] E3 Sweep dead references (links, route helpers, tests)

## F — Seeds / HANDOFF

- [x] F1 Extend seed: proctor-duty-holder account + published/in-progress session with assigned applicants for the monitoring demo — `MonitoringDemoSeeder` (EX-MON-1, duties for guidance.clerk/support)
- [x] F2 `HANDOFF.md`: staff logins (Test Administrator + duty-holder proctor), seeder/reset command, expected state, URLs (scheduling + monitoring)

## G — Proof

- [x] G1 Pest feature suite green (nav builder, monitoring gates, attendance/submission)
- [x] G2 Manual demo per HANDOFF — observed 2026-08-12: Test Administrator and guidance.clerk duty-holder opened EX-MON-1 monitoring roster
