# D-088 — Exam scheduling conflict resolutions

**Status:** FOUNDING DECISION — recorded 2026-08-06; **amended 2026-08-08 (Option A — session booking_mode)**  
**Closes conflicts between:** legacy TestScheduling behavior, GF-06 §5 / §5.1, and OpenSpec wording  
**Does not close:** missed-exam retry (still DECISION REQUIRED), AI schedule assistance (E3), proctor time-conflict SPIKE

## Method

Conflicts were resolved by priority:

1. Founded GF contracts (GF-06, FR-010/011) over legacy defects  
2. Legacy UI journey where it does not fight GF  
3. Single transactional authority (no dual code paths)  
4. Simpler state machines — **session-level booking mode** over a separate SlotPlan aggregate  

## Locked decisions

### 1. Unpublish vs Cancel vs draft abandon

| Situation | Command | Result |
|---|---|---|
| Draft never published, staff discards | `CancelExamSession` (reason) | → `cancelled`; assignments freed; **no hard delete** |
| Published, not started, needs retract | `UnpublishExamSession` (reason) | → `draft`; prior publish evidence kept |
| Published, must kill schedule | `CancelExamSession` (reason) | → `cancelled`; free active seats |
| In progress / past start | Do **not** use Unpublish | Use `CloseExamSession` per D-075 / GF-06 |

### 2. One assign / reserve command path

- Staff UI → `AssignApplicantToExamSession` / `RemoveApplicantFromExamSession`  
- Applicant calendar → `ReserveExamSlot` (creates **assignment immediately**)  
Always under row lock: `isExamEligible()`, capacity, no second **active** assignment.

### 3. Active uniqueness (fix SCH-14)

- Keep assignment **history** rows.  
- Uniqueness = **at most one active** assignment per attempt.  
- Cancel / remove ends active holds so the applicant can be placed again.

### 4. Dual path coexistence (session booking_mode) — Option A

**No ExamSlotPlan product aggregate.** Per `ExamSession`:

| `booking_mode` | Behavior |
|---|---|
| `staff_only` (default after create) | Staff assign only; calendar ignores session |
| `open_for_reservation` | Applicants may reserve this session; creates `ExamAssignment` (`source=applicant_reserved`) |

Commands: `OpenExamSessionReservation` / `CloseExamSessionReservation`.

**While open:**

- Staff **may** assign **unassigned** accepted applicants (leftovers).  
- Staff **must not** silently overwrite an active assignment; force move = Remove (reason) then Assign.  
- Capacity ledger = `active_assignments ≤ room.capacity`.  
- Session schedule fields (date/room/time) are **locked** until reservation closed or session cancelled.

**Close reservation** → `staff_only` again; existing assignments stay; no new applicant reserves.

**Reserve = seat** — no soft-hold table, no Finalize step.

### 5. Publish is the only notification boundary

Session `PublishExamSession` remains the notify boundary (GF-06).  
There is no plan-level publish.

### 6. Late assign after publish

Allow `AssignApplicantToExamSession` on `draft` **and** `published` (not terminal). Capacity still enforced.

### 7–9. Out of cut / still open

Proctor time conflict, AI Schedule Assistant, missed-exam policy — unchanged (out of cut / DECISION REQUIRED).

## Amendment note (2026-08-08)

Founder directed collapse of SlotPlan hops: too many objects fighting the same seats. Dual path retained as **session `booking_mode`**. Soft holds / finalize / bypass plan UI **removed**.

## Robustness verdict

Robust for the scheduling cut under Option A. Still not full E2 exit (proctor conflict, missed-exam, AI).
