# Design: Exam Session Scheduling

## 1. Legacy evidence (staff) — mimic this shape

| Legacy surface | Path | Shape |
|---|---|---|
| Index | `/admin/exam-scheduling` | List + filters + status badges; Create button; row actions Edit/Show/Publish… |
| Create | `/admin/exam-scheduling/create` | Separate page |
| Edit | `/admin/exam-scheduling/{id}/edit` | Separate page |
| Show | `/admin/exam-scheduling/{id}` | Detail + assign applicants + roster actions |
| Monitoring | `/admin/exam-monitoring` | Out of this cut except links |

**Legacy assignment rules (`ExamSchedulingService`):**

- Create starts as `draft`  
- Assign only applicants with application `accepted` and not already on a non-cancelled session  
- Publish blocked unless date/room set, not past end, ≥1 applicant  
- Session statuses: `draft`, `published`, `in_progress`, `completed`, `cancelled`  
- Soft delete drafts; detach applicants  

**Legacy applicant exam UX:** portal dashboard shows schedule **after** staff assignment + publish. **No exam self-booking in legacy.** (Legacy `Appointment` on Apply is intake booking, not examination.)

Disposition:

- Staff list/create/edit/show/assign/publish → **PRESERVE** layout/journey  
- Role middleware → **REWRITE** to CapabilityAuthorizer (`guidance.exam.schedule` / catalog key)  
- Applicant calendar reservation → **ADD** (GF-06; not legacy copy)  
- AI assistant panel → **DEFER** E3  

## 2. Target dual path (already founded)

```text
Accepted attempt (isExamEligible)
        ├─ Staff-assigned path (legacy-like)
        │     draft session → assign → publish → notify
        └─ Applicant reservation path (new plan)
              ExamSlotPlan open_for_slot_reservation
              → applicant calendar reserve
              → reservation_closed → finalize → ExamAssignment
              → publish sessions → notify
```

Direct Assessment remains a third bypass (out of UI cut; keep command seam).

### When the applicant may choose a slot

| Condition | May reserve? |
|---|---|
| Attempt not accepted / not `isExamEligible()` | No (pending / needs_correction / dismissed / archived) |
| Accepted, but no plan open | No — show “Scheduling pending” |
| Accepted + plan `open_for_slot_reservation` + capacity left | Yes — calendar |
| Plan `reservation_closed` / `finalized` / `published` / `bypassed` | No new reserve (may view existing reservation) |
| Already has active assignment on non-cancelled session | No second active reservation/assignment |

### Slot-plan states (this cut; D-088)

`draft → open_for_slot_reservation → reservation_closed → finalized`, plus `bypassed`.

Closing reservation ≠ publishing. **Session Publish** is the notification boundary.  
Plan status `published` from older GF wording is **derived** (all linked sessions published), not a second publish command.

### ExamSession states (canonical)

`draft`, `published`, `in_progress`, `completed`, `cancelled` (GF-19 / GF-06).

Do **not** hard-delete sessions. Draft abandon uses `CancelExamSession` (D-088).  
Unpublish is only `published → draft`. Cancel from published (or unused draft) frees active seats.  
In-progress ending uses `CloseExamSession`, not Unpublish.

## 3. Aggregates / schema (this OpenSpec)

| Aggregate | Purpose |
|---|---|
| `ExamSession` | Room + starts_at + effective_end_at + status (exists; extend fields as needed) |
| `ExamAssignment` | Final seat on a session (exists; add `source`: `staff_assigned` \| `reservation_finalized`) |
| `ExamSlotPlan` | TA policy window over one or more draft/published-ready sessions for a cycle |
| `ExamSlotReservation` | Applicant hold before finalize; capacity-safe; history preserved |

Unique: one active non-cancelled assignment per attempt per cycle (INV-style).  
Capacity: assignment + open reservation counts ≤ room capacity under row lock.

## 4. Commands (GF-20 aligned)

Staff:

- `CreateExamSession`, `UpdateDraftExamSession`  
- `AssignApplicantToExamSession` (exists; harden unique/conflict)  
- `RemoveApplicantFromExamSession`  
- `PublishExamSession` / `UnpublishExamSession` / `CancelExamSession`  
- `OpenExamSlotPlan` / `CloseExamSlotReservation` / `FinalizeExamSlotPlan` / `BypassExamSlotPlan`  

Applicant:

- `ReserveExamSlot` (idempotent; only when plan open + eligible)  
- `CancelExamSlotReservation` (while plan still open, unless policy locks)

## 5. UI contract (hifi)

### Staff (legacy-close)

| Page | Route (proposed) | Notes |
|---|---|---|
| P-EXM-01 Index | `/exams/sessions` | Management list: status, school year/semester label, room, datetime, assigned count, actions. **No inline create form.** |
| P-EXM-01b Create | `/exams/sessions/create` | Academic year, room, start/end, optional initial assigns |
| P-EXM-01c Edit | `/exams/sessions/{id}/edit` | Draft-only field edits |
| P-EXM-02 Show | `/exams/sessions/{id}` | Roster, assign picker (accepted eligible), publish gates, open/close reservation controls for linked plan |
| P-EXM-03 Slot plan | `/exams/slot-plans` (+ create/edit) | Open/close reservation for a set of sessions |

### Applicant (calendar-first)

| Page | Route | States |
|---|---|---|
| P-APP-03a Schedule | `/applicant/schedule` | `pending_acceptance`, `waiting_for_reservation_open`, `calendar_open`, `reserved`, `assigned_published`, `denied` |
| Calendar | same page primary | Month/week of open sessions; capacity remaining; select → confirm Dialog |

Staff may keep list as primary; optional staff calendar is **nice-to-have**, not required to ship.

## 6. Authz

- Schedule mutate: `guidance.exam.schedule` (Test Administrator / Guidance Counselor)  
- Applicant reserve: authenticated applicant owning the attempt + plan open (no staff capability)  
- Registrar: view readiness / deny mutate by default  

## 7. Explicit decisions locked for this change

| Topic | Lock |
|---|---|
| Staff UI | Mimic legacy TestScheduling list → create/edit/show |
| Applicant choose slot | Only when accepted + plan open (FR-010) |
| TA late assign | Allowed for unreserved accepted applicants; never silent overwrite of open reservation (D-088) |
| Calendar | Required for applicant reservation UX |
| AI assistant | Out of cut |
| Missed-exam policy | Out of cut (still DECISION REQUIRED globally) |
| Unpublish / Cancel / Close | D-088 §1 (GF-06 over legacy cancel-only-in-progress) |
| Assign authority | Single command path (D-088 §2) |
| Uniqueness | Active hold only; history retained (D-088 §3) |
| Dual path mode | Open reservation vs bypassed; shared capacity ledger (D-088 §4) |
| Plan vs session publish | Plan ends at finalized/bypassed; session Publish = notify (D-088 §5) |
| Late assign on published | Allowed with capacity + notify (D-088 §6) |
| Proctor time conflict | Out of cut (D-088 §7) |

Authority file: [`docs/greenfield-foundation/decisions/D-088-exam-scheduling-conflict-resolutions.md`](../../../docs/greenfield-foundation/decisions/D-088-exam-scheduling-conflict-resolutions.md)

### ExamSession transitions (aligned GF-06 + D-088)

```text
none → Create → draft
draft → Assign / Remove (capacity)
draft → Publish → published          # notify boundary
published → Unpublish → draft        # reason; evidence kept
published → Cancel → cancelled       # free seats; notify
published → Start → in_progress
in_progress → Close → completed|cancelled   # D-075; not Unpublish
draft → Cancel → cancelled           # abandon; no hard delete
```

### ExamSlotPlan transitions (simplified for this cut)

```text
draft → open_for_slot_reservation → reservation_closed → finalized
draft|open|closed → bypassed
```

Derived badge only: plan “Announced” when every linked session is `published`.  


## 8. Implementation order

1. Trace doc + page/state catalog update  
2. Schema ExamSlotPlan / ExamSlotReservation + assignment source  
3. Harden staff session CRUD UI (legacy shape) + assign/publish Pest  
4. Slot plan open/close + applicant calendar reserve  
5. Finalize → assignment → publish notify seam  
6. Evidence note + Compose rebuild  

## 9. Stop / gaps

Stop if founder reverses dual-path or wants applicant calendar on staff Index instead of list.  
Do not implement AI apply-schedule.  
Do not treat legacy Apply `Appointment` as exam slots.

### Legacy defects absorbed from full inventory (must rewrite)

Evidence: [Explore legacy exam scheduling](8bb9d5d2-9463-4747-b712-0d010ea62e0d) + `docs/legacy-reference/11-exam-scheduling-trace.md` SCH-13–17.

| Defect | Greenfield rule |
|---|---|
| Show controller assign skipped capacity/eligibility while service enforced them | One `AssignApplicantToExamSession` path; lock + capacity + `isExamEligible()` |
| Cancel left unique applicant pivot blocking reassignment | Active-assignment uniqueness only; cancel/remove frees the applicant |
| Publish/unpublish/cancel semantics split awkwardly | Explicit commands; publish = notify boundary |
| No proctor time-conflict | Out of first cut (SPIKE later); still assign proctor duties |
| Role middleware route splits | CapabilityAuthorizer only |

### Publish gates to encode (legacy `publishBlockReason`)

- Not `in_progress` / `completed` / `cancelled`  
- Room + start + effective end present  
- End not already past  
- At least one assignment (staff path) or finalized reservations mapped to assignments  
