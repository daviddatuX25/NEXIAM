# Change Proposal: Exam Navigation Organization (Scheduling / Monitoring / Scoring)

**Change ID:** `exam-navigation-organization`
**Status:** PROPOSED — waiting on founder sequencing decision vs active `scoring-conversion`
**Phase:** E2 connected admission-to-release slice (structural, no new domain state)
**Depends on:** `grading-workflow` PASS, `grading-roster-eligibility` PASS (attendance + submission gates already exist as Actions + duty gate)

## Intent

Reorganize the exam-related navigation and page surfaces so scheduling, exam conduct
(monitoring), and scoring are no longer mixed in one route tree. Founder decisions 2026-08-11:

1. **Scheduling and the main exam are separate surfaces.** `/exams/sessions*` stays the
   **scheduling** hub (session plan: create/edit, room/date, assign applicants, reservations,
   publish, proctor **assignment**). A new per-session **Exam Monitoring** page owns the
   conduct work: roster → attendance → submission → extend/close.
2. **No separate "Proctor" page.** Proctor duty work lives **inside Exam Monitoring**.
   A proctor (session duty, D-015) can mark attendance + submission for **their assigned
   session only**; a Test Administrator can do so inherently (same capability set). The
   standalone `/proctor` stub page and its nav item are removed.
3. **Rename "Grading" → "Scoring"** in navigation; this group will later host the OMR
   interface (founder-priority next cut) alongside manual scoring.
4. **Sidebar grouped by domain:** Admissions · Examination (scheduling, monitoring) ·
   Scoring · Release · Consultation.

## Disposition from legacy

| Legacy | Disposition |
|---|---|
| `Admin/TestScheduling/{Index,Create,Edit,Show}` (registrar scheduling) | **PRESERVE shape** — greenfield `Examination/*` scheduling pages already mirror this; keep list → create/edit → show journey. |
| `Admin/TestScheduling/Monitoring.svelte` (test-admin conduct view) | **REWRITE (greenfield page)** — new per-session monitoring page with attendance/submission; session status visible while scrolling (GF-22 §3.3). |
| `Proctor/MySessions.svelte` + `Proctor/SessionRoster.svelte` (proctor role pages) | **REWRITE (merged, not a role page)** — duty-scoped access into the same Exam Monitoring surface; no separate Proctor nav. Role-strings (`proctor`) are NOT used as authorization (D-014/D-015). |

## Non-goals

- **Proctor scoring** — future via the organized capability/role matrix; out of scope.
- **Face-to-face (default, not online) conduct pipeline** — more complex actor split; tackled later, explicitly deferred.
- **Full class-level rename** of `GradingController`/`GradingSession`/`GradingScoreController` — only the user-facing nav label changes to "Scoring"; class/route renames are a follow-up if the founder wants them.
- No change to applicant-choose-slot, admission, or release flows.

## Key decisions locked

- One monitoring surface, two actors: Test Administrator (all sessions, full actions) and Proctor (active duty sessions, attendance + submission only).
- Attendance/submission authorization = `ProctorDutyGate::hasActiveDuty(session)` **OR** Test-Administrator schedule capability (duty grants the proctor act; TA holds it inherently).
- Monitoring gate = `guidance.exam.schedule` OR any active proctor duty.
- Proctor nav item removed; proctors reach their session via **Exam Monitoring** (list filtered to their duties).
