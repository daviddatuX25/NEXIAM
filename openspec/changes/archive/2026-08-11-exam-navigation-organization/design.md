# Design: Exam Navigation Organization

**Change ID:** `exam-navigation-organization`

## 1. Sidebar information architecture (server-authored, GF-22 §4.1)

`StaffNavigationBuilder` outputs grouped items. A group renders its label only when it has ≥1 authorized item. No client-side role check.

| Group | Item | Route | Gate |
|---|---|---|---|
| Admissions | Admissions | `/admissions` | `registrar.applications.view` (unchanged) |
| Examination | Exam Scheduling | `/exams/sessions` | `guidance.exam.schedule` (unchanged) |
| Examination | Exam Monitoring | `/exams/monitoring` | `guidance.exam.schedule` **OR** active proctor duty |
| Scoring | Scoring | `/grading` (label renamed; URL decision §6) | `guidance.scoring.manual` (unchanged) |
| Release | Release | `/release` | `guidance.results.release` (unchanged) |
| Consultation | Consultation | `/consultation` | `guidance.consultation.decide` (unchanged) |

Remaining items (Setup, Knowledge, Staff, Home, UI Kit) stay in place; grouping them under
Institution/System labels is optional polish, not part of this cut.

**Removed:** the `Proctor` nav item (`/proctor`, gated by `guidance.exam.proctor.assign`).
That gate was wrong for duty-holders: a clerk on proctor duty (D-015) never saw the item,
and the item conflated "assign proctors" with "be a proctor".

## 2. Routes

### Kept (scheduling surface, unchanged paths)
`GET/POST /exams/sessions`, `/create`, `/{session}`, `/{session}/edit`, and all POST lifecycle
actions (assign/remove/reassign/publish/unpublish/cancel/close/extend/open-reservation/close-reservation).
`POST /exams/sessions/{session}/proctor` + `POST /proctor/duties/{duty}/revoke` stay on the
scheduling surface (Test Administrator assigns/revokes proctor duties there).

### New (monitoring surface)
| Method | URI | Controller → page | Gate |
|---|---|---|---|
| GET | `/exams/monitoring` | `ExamSessionController::monitoringIndex` → `Examination/MonitoringIndex` | schedule cap OR active duty |
| GET | `/exams/sessions/{session}/monitoring` | `ExamSessionController::monitoring` → `Examination/Monitoring` | schedule cap OR active duty **on this session** |
| POST | `/exams/sessions/{session}/monitoring/assignments/{assignment}/attendance` | `ProctorController::recordAttendance` (rehomed) | schedule cap OR active duty on session |
| POST | `/exams/sessions/{session}/monitoring/assignments/{assignment}/submission` | `ProctorController::recordSubmission` (rehomed) | schedule cap OR active duty on session |

The two POST routes are rehomed under the session so the URL self-describes the scope
(`exams.sessions.monitoring.attendance` / `.submission`). `IdempotentProctorRequest` reuse stays.

### Removed
`GET /proctor` + `ProctorController::dashboard` + `Examination/Proctor.svelte`. Nothing links
to it after the nav item is deleted; the attendance/submission Actions and duty gate remain.

## 3. Pages

### 3.1 Exam Scheduling (`Examination/{Index,Create,Edit,Show}.svelte`) — unchanged shape
- Show = session detail + scheduling actions (publish/unpublish/cancel, reservation toggle,
  assign/remove/reassign applicants, proctor assign/revoke).
- **Add** an entry point to conduct: a "Go to monitoring" button/link on Show when status is
  `published` or `in_progress`.

### 3.2 Exam Monitoring (`Examination/MonitoringIndex.svelte` + `Examination/Monitoring.svelte`) — new
- **MonitoringIndex:** sessions needing conduct attention — active statuses (`published`,
  `in_progress`), today's/upcoming first. Test Administrator sees all; a proctor-only viewer
  sees **only their active-duty sessions**. `page_state` empty/denied handled.
- **Monitoring (per session):** GF-22 §3.3 proctor/session shell:
  - persistent session identity + status + effective deadline in the header (visible while scrolling);
  - roster table: each active `ExamAssignment` → applicant (reference + name), attendance state, submission state;
  - actions per row: mark attendance (`present`/`absent`), record submission (`submitted`/not), idempotency-keyed;
  - Test Administrator additionally sees extend/close; proctor sees attendance + submission only for this session;
  - breadcrumbs: `Exam Monitoring › {session.code}`; navigation away from monitoring is denied server-side for proctor-only actors (existing gates already 403 unrelated sessions).

### 3.3 Scoring
Nav label "Scoring". Page content unchanged this cut (`Assessment/Grading*.svelte`).

## 4. Authorization model (no new capability keys)

| Concern | Authority |
|---|---|
| View scheduling pages | `guidance.exam.schedule` (unchanged) |
| View monitoring index / a session's monitoring page | `guidance.exam.schedule` OR active duty on that session |
| Mark attendance / record submission | `ProctorDutyGate::hasActiveDuty(user, session)` OR `guidance.exam.schedule` |
| Assign/revoke proctor duty | `guidance.exam.proctor.assign` (unchanged) |
| Extend/close session | `guidance.exam.schedule` (unchanged, scheduling cap) |

`CapabilityCatalog` needs **no new constants**. The existing duty overlay (D-015) + the Test
Administrator set (D-016) cover both actors with one page. "Proctor" is never a Spatie role.

## 5. GF-22 mapping

- §3.3 Proctor/session shell → §3.2 Monitoring page (one session, roster/attendance/submission, status always visible).
- §4.1 Guidance operations (session plan, reservation lifecycle, manual assignment, monitoring) → Exam Scheduling + Exam Monitoring (Test Administrator capability).
- §4.1 Proctor session (assigned roster, attendance, submission) → Exam Monitoring duty-scoped.
- §4.2 breadcrumbs required on both surfaces; monitoring breadcrumb includes the session code.

## 6. Grading → Scoring rename scope

- Sidebar label + page title/description: "Grading" → "Scoring" (Scoring group).
- Route/controller/domain names (`/grading`, `grading.*`, `GradingController`,
  `GradingSession`, `GradingScoreController`, `ScoreRecord`) are **unchanged** this cut —
  flagged for a possible mechanical rename cut. Tests continue to reference `grading.*` names.
- Decision needed (default = keep URLs, change label only).

## 7. Seed / demo data

Reuse existing exam-session + proctor-duty seeds. Monitoring demo needs:
- a `published`/`in_progress` session with active proctor duties and assigned applicants;
- a proctor account (duty-holder) whose monitoring index shows exactly their sessions.

Record exact accounts + recreate steps in `HANDOFF.md` per §3.7 developer-ready seed rule.
