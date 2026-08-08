# Design: Exam Session Scheduling

## 1. Legacy evidence (staff) — mimic this shape

Staff list → create → edit → show/assign/publish (legacy TestScheduling).  
Applicant calendar reservation is **ADD** (not legacy).  

**Amended 2026-08-08 (Option A):** reservation is a **per-session booking mode**, not a SlotPlan aggregate.

## 2. Target dual path (Option A)

```text
Accepted attempt (isExamEligible)
        ├─ Staff-only (default)
        │     draft session → assign → publish → notify
        └─ Open for reservation (per session)
              OpenExamSessionReservation
              → applicant calendar ReserveExamSlot → ExamAssignment (applicant_reserved)
              → CloseExamSessionReservation (optional; stops new booking)
              → publish → notify
```

### When the applicant may reserve

| Condition | May reserve? |
|---|---|
| Not accepted | No |
| Accepted, no session `open_for_reservation` | No — waiting |
| Accepted + session open + capacity | Yes |
| Already has active assignment | No |

### ExamSession booking_mode

`staff_only` | `open_for_reservation`  
Open locks schedule fields until closed/cancelled.

### ExamSession lifecycle states

`draft`, `published`, `in_progress`, `completed`, `cancelled`  
Publish = notify boundary. Unpublish / Cancel / Close per D-088.

## 3. Aggregates

| Aggregate | Purpose |
|---|---|
| `ExamSession` | Room + time + status + **booking_mode** |
| `ExamAssignment` | Final seat; `source`: `staff_assigned` \| `applicant_reserved` |

**Removed:** `ExamSlotPlan`, `ExamSlotReservation` (soft holds).

## 4. Commands

Staff: Create/UpdateDraft, Assign, Remove, Publish/Unpublish/Cancel, Close, Extend,  
`OpenExamSessionReservation`, `CloseExamSessionReservation`.

Applicant: `ReserveExamSlot` (idempotent; creates assignment).

## 5. UI

| Page | Route | Notes |
|---|---|---|
| Index/Create/Edit/Show | `/exams/sessions*` | Booking open/close on Show; roster assign |
| Applicant schedule | `/applicant/schedule` | Pipeline + calendar of open sessions |

No `/exams/slot-plans` surface.

Applicant states: `pending_acceptance`, `waiting_for_reservation_open`, `calendar_open`, `assigned_published`, `denied`.

## 6. Authz

- Staff: `guidance.exam.schedule`  
- Applicant: owns attempt + session open  

## 7. Authority

[`D-088`](../../../docs/greenfield-foundation/decisions/D-088-exam-scheduling-conflict-resolutions.md) (Option A amendment).
