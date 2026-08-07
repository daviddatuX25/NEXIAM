# D-088 — Exam scheduling conflict resolutions

**Status:** FOUNDING DECISION — recorded 2026-08-06 (founder-directed brainstorm lock for OpenSpec `exam-session-scheduling`)  
**Closes conflicts between:** legacy TestScheduling behavior, GF-06 §5 / §5.1, and draft OpenSpec wording  
**Does not close:** missed-exam retry (still DECISION REQUIRED), AI schedule assistance (E3), proctor time-conflict SPIKE

## Method

Conflicts were resolved by priority:

1. Founded GF contracts (GF-06, FR-010/011) over legacy defects  
2. Legacy UI journey where it does not fight GF  
3. Single transactional authority (no dual code paths)  
4. Simpler state machines over duplicate “published” meanings  

## Locked decisions

### 1. Unpublish vs Cancel vs draft abandon

| Situation | Command | Result |
|---|---|---|
| Draft never published, staff discards | `CancelExamSession` (reason) | → `cancelled`; assignments/reservations freed; **no hard delete** |
| Published, not started, needs retract | `UnpublishExamSession` (reason) | → `draft`; prior publish evidence kept; applicants notified of correction |
| Published, must kill schedule (not started or policy allows) | `CancelExamSession` (reason) | → `cancelled`; free active seats; notify |
| In progress / past start | Do **not** use Unpublish | Use `CloseExamSession` (completed or cancelled outcome) per D-075 / GF-06 |

**Reject legacy:** cancel only from `in_progress`; soft-delete drafts.  
**Align GF-06:** Unpublish = published→draft; Cancel = terminal cancel with history.

### 2. One assign command (no dual paths)

All staff UI and any future assistant **must** call `AssignApplicantToExamSession` (and Remove).  
Always under row lock: `isExamEligible()`, capacity, no second **active** assignment.

### 3. Active uniqueness (fix SCH-14)

- Keep assignment/reservation **history** rows.  
- Uniqueness = **at most one active** hold per attempt (assignment status in active set, or open reservation).  
- Cancel / remove / unpublish reconciliation **ends** active holds so the applicant can be placed again.  
- **Reject** legacy global `unique(applicant_id)` that blocks after cancel.

### 4. Dual path coexistence (staff + applicant calendar)

Per cycle/plan the TA chooses a **mode**:

| Mode | Behavior |
|---|---|
| `open_for_slot_reservation` | Applicants calendar-reserve; capacity shared |
| `bypassed` | Staff-only assign (legacy-like); calendar closed |

**While reservation is open:**

- Staff **may** late-assign **unreserved** accepted applicants (FR-011 leftovers).  
- Staff **must not** silently overwrite an open reservation; force move = explicit remove/cancel reservation (reason) then assign.  
- Capacity ledger = `active_assignments + open_reservations ≤ room.capacity` (one counter).

**After `reservation_closed`:** no new applicant reserves; staff finalizes leftovers; then publish sessions.

### 5. Slot-plan “published” vs session Publish (remove double meaning)

**ExamSlotPlan** authoritative states for this cut:

`draft → open_for_slot_reservation → reservation_closed → finalized → bypassed`

- `finalized` = reservations converted to `ExamAssignment` (source `reservation_finalized`).  
- **Session** `PublishExamSession` remains the **only** notification boundary (GF-06).  
- GF-06 plan status `published` is treated as **derived**: “all linked sessions published,” not a second publish command.  
  (If a UI badge is needed: show derived “Announced” when every linked session is `published`.)

### 6. Late assign after publish

Allow `AssignApplicantToExamSession` on `draft` **and** `published` (not `completed` / `cancelled`).  
After-commit: notify the newly assigned applicant (legacy late-assign behavior).  
Capacity still enforced.

### 7. Proctor time conflict

**Out of this OpenSpec.** Assign proctor duties without calendar conflict engine. Document as known limitation; SPIKE later.

### 8. AI Schedule Assistant

**Out of cut (E3).** No second apply path.

### 9. Missed-exam / no-show retry

**Still DECISION REQUIRED globally.** Scheduling ships without a full retry product. Close may mark absent; reassignment uses Remove + Assign after seats freed (mechanical), not a named “retake policy.”

## Robustness verdict (after these locks)

**Robust enough to implement** the first scheduling cut if B1–D2 follow this file and the OpenSpec.

Still **not** full E2 exit: proctor conflict, missed-exam policy, AI assist, and Direct Assessment UI remain outside.

## Downstream

- Update `openspec/changes/exam-session-scheduling/design.md` § conflict lock table  
- Tasks B1 uniqueness + B3 single command path already point here  
- Optional later: promote derived plan “announced” into GF-06 wording if needed  
