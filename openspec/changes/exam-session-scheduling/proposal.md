# Proposal: Exam Session Scheduling (manual + applicant reservation)

**Status:** ACTIVE OpenSpec  
**Phase:** E2 step 4 (manual scheduling) + GF-06 §5.1 dual path  
**Depends on:** institutional setup readiness (rooms, academic years, accepted attempts via `isExamEligible()`)  
**Archived prior:** `institutional-setup-readiness` PASS 2026-08-06

## Why

Setup catalogs and admission accept are in place. The next connected-slice beat is examination scheduling. Legacy is **staff-driven assign → publish**. Greenfield already commits a second path: **applicant slot reservation when opened by the Test Administrator** (FR-010, FR-011, GF-03 §8, GF-06 §5.1). Staff UI must mimic legacy TestScheduling; applicant UX gets a **calendar-first** reservation view (new affordance, not present as exam self-booking in legacy).

## In scope

1. **Staff path (legacy shape PRESERVE)**  
   - List/index of sessions (status, date/room, counts)  
   - Create / Edit / Show as separate pages  
   - Assign / remove accepted eligible applicants  
   - Publish / unpublish / cancel with publish gates  
   - Capacity + room/time conflict under transaction  
   - Proctor duty assign (minimum for publish readiness if required by product)

2. **Applicant reservation path (GF target)**  
   - `ExamSlotPlan` open/close by Test Administrator  
   - Applicant may reserve only when: attempt `accepted` / `isExamEligible()` **and** plan `open_for_slot_reservation`  
   - Calendar view of available sessions/slots for the applicant  
   - Staff finalize reservations → `ExamAssignment` → publish (notification boundary)

3. **Read / portal**  
   - Applicant sees pending vs reserved vs published schedule  
   - Staff sees reservation vs assigned source on roster

## Out of scope (this change)

- AI Schedule Assistant (legacy panel) → E3 scheduling assistance  
- Missed-exam / no-show retry policy (still DECISION REQUIRED; compose absent→remove→reassign only if already coded as temporary)  
- OMR, grading UI beyond open-once seam  
- Direct Assessment full UI (command seam may stay stub)  
- Consultation appointments (legacy `Appointment` on apply form is intake booking, not exam)

## Success

- Staff can run the legacy-equivalent schedule workflow on greenfield rooms/years  
- When TA opens reservation, accepted applicants pick a slot on a calendar and staff can finalize/publish  
- Pest proves: eligibility gate, capacity, conflict, reservation window closed denies reserve, registrar cannot schedule without capability  
- Legacy UI disposition recorded; staff pages do not invent list+inline-create forms

## Primary authority

- GF-05 ExamSession / ExamSlotPlan / ExamSlotReservation  
- GF-06 §5 / §5.1 statuses and dual path  
- GF-19/20 command catalog  
- FR-010, FR-011, FR-029; REQ-VS-005; E2-S06  
- Legacy: `Admin/TestScheduling/*`, `ExamSchedulingService`, portal dashboard schedule card  
