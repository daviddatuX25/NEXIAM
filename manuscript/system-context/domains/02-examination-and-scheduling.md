# Domain 02: Examination Operations & Scheduling

## 1. Domain Responsibility & Academic Value

The Examination Operations & Scheduling domain orchestrates institutional examination venues, candidate rosters, session capacities, and live test-day conduct for the Ilocos Sur Polytechnic State College College Admission Test (ICAT).

In the capstone research, this domain addresses baseline problem **FM3** (Examination scheduling friction, paper notice delays, and attendance tracking overhead) identified during interviews with the Guidance Office and applicant surveys.

---

## 2. Core Entities & Database Schema

```
┌─────────────────────────┐          1:N         ┌─────────────────────────┐
│          Room           │─────────────────────▶│       ExamSession       │
├─────────────────────────┤                      ├─────────────────────────┤
│ id (BIGINT, PK)         │                      │ id (BIGINT, PK)         │
│ name (VARCHAR)          │                      │ room_id                 │
│ capacity (INT)          │                      │ start_time, end_time    │
│ building, campus        │                      │ status (ENUM)           │
│ is_active (BOOLEAN)     │                      │ seating_mode (ENUM)     │
└─────────────────────────┘                      │ booklet_id              │
                                                 └────────────┬────────────┘
                                                              │ 1:N
                                                              ▼
                                                 ┌─────────────────────────┐
                                                 │      ExamAssignment     │
                                                 ├─────────────────────────┤
                                                 │ id (BIGINT, PK)         │
                                                 │ exam_session_id         │
                                                 │ admission_attempt_id    │
                                                 │ attendance_status (ENUM)│
                                                 │ seat_code (NULLABLE)    │
                                                 │ booklet_set (VARCHAR)   │
                                                 └─────────────────────────┘
```

1. **`Room` (`rooms` table):**
   - Physical examination facility on campus with fixed desk capacity (e.g., "Audio-Visual Room", 50 seats).
2. **`ExamSession` (`exam_sessions` table):**
   - A scheduled examination timeslot assigned to a specific room.
   - States: `draft` $\rightarrow$ `published` $\rightarrow$ `in_progress` $\rightarrow$ `closed` (or `cancelled`).
   - Governed by `seating_mode`: **`simple_capacity`** (default) vs `assigned_seating` (advanced policy).
3. **`ExamAssignment` (`exam_assignments` table):**
   - Individual candidate reservation binding an `AdmissionAttempt` to an `ExamSession`.
   - Attendance states: `pending` $\rightarrow$ `present` / `absent` / `void`.

---

## 3. Human Actor Journeys & Interaction Flow

### A. Test Administrator Scheduling Hub (`/examination`)
- Guided by holders of the `guidance.exam.schedule` capability.
- **Bulk Action Bar:** Multi-select across sessions enables one-click Bulk Publish, Open Reservation, Staff-Only Restriction, or Bulk Cancel (mandatory reason required).
- **Roster Building:** Automatically assigns eligible applicants (`status = accepted`) to sessions using room quotas, or allows manual search and assignment.

### B. Proctor Live Conduct Deck (`/exams/monitoring`)
- Guided by designated Proctors (`guidance.exam.proctor.assign`).
- **Single-Click Attendance:** Proctor marks examinees as `present` or `absent` directly from the mobile/desktop roster view.
- **Walk-in Accommodation:** If an examinee arrives for an earlier or later session, `AccommodateApplicantExamSession` transactionally reassigns them while respecting room capacity mutexes.
- **Atomic Session Close:** Closing an exam session (`CloseExamSession`) automatically reconciles attendance: present candidates are made score-eligible for OMR grading; absent examinees are flagged for rescheduling without blocking the cohort.

---

## 4. Key Actions & CQRS Queries (`system/app/Examination/`)

| Class | Type | Responsibility & Invariants |
|---|---|---|
| `CreateExamSession` | Domain Action | Verifies room availability, prevents double-booking overlaps, defaults to `simple_capacity`. |
| `PublishExamSession` | Domain Action | Locks session capacity, generates initial booklet distribution manifests, notifies candidates. |
| `AssignApplicantExamSession` | Domain Action | Binds applicant to session; locks mutex against concurrent overbooking. |
| `AccommodateApplicantExamSession`| Domain Action | Moves candidate across sessions with audit trail and capacity checks (`AssignmentPlanningMutex`). |
| `RecordExamAttendance` | Domain Action | Records `present`/`absent`/`void`; prevents modification once session is closed. |
| `CloseExamSession` | Domain Action | Reconciles roster; generates score-eligible entries in `grading_rosters`. |
| `SessionSearchSuggestionQuery` | CQRS Query | Real-time debounced search indexing candidate name, reference code, and assigned room. |

---

## 5. Security Invariants & Policy Decisions

- **Founder Lock (Simple Capacity Default):** By institutional decree, spatial desk layouts and seat numbers are disabled by default. Simple capacity schedules up to the room threshold.
- **Booklet Set Policy:** Every booklet distribution defaults to **Set A**. Multi-set scrambling (Set A, B, C) requires explicit institutional policy enablement and verified keys.
- **Proctor Duty Boundary:** Proctors can *only* mark attendance and submit room incident reports for their explicitly assigned session. They cannot reschedule candidates, publish sessions, or access scoring.

---

## 6. Implementation & Verification Status

- **Status:** `[IMPLEMENTED-TESTED]` & `[OBSERVED-EVIDENCE]`
- **Backend Evidence:**
  - `tests/Feature/Examination/ExamSessionPagesTest.php` (Passing)
  - `tests/Feature/Examination/ExamSessionBulkActionsTest.php` (4 tests passing)
  - `tests/Feature/Examination/DirectAssessmentTest.php` (8 tests passing)
  - `tests/Feature/Examination/AutoCloseExamSessionsTest.php` (Passing)
  - `tests/Feature/Examination/SessionSearchSuggestionTest.php` (Passing)
- **Frontend / Browser Evidence:**
  - UI Lab §3.A standard `craft-card` and responsive grid (`grid-cols-1 md:grid-cols-2 xl:grid-cols-3`) verified.
  - Floating bulk action dock verified on desktop and mobile browsers.
