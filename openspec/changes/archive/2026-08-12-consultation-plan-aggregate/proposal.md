# Proposal: Consultation Plan Aggregate (F2F-default, online branch)

**Status:** DRAFT — awaiting founder disposition (founder direction recorded 2026-08-13 below; not yet signed off)
**Phase:** E2 connected slice (release/consultation beat) — GF-06 §8.1
**Depends on:** scoring finalized → release prepare (existing `PrepareResultDocument`/`ReleaseResult`); applicant portal schedule reservation precedent (`ApplicantScheduleController::reserve`)
**Gap:** the navigation split landed Release + Consultation surfaces, and `ConsultationDecision` + `ResultRelease` exist — but the **plan/availability middle is absent**. Today the office can only record a decision; it cannot publish batches, open availability, assign applicants to a batch, or let an applicant reserve a slot. GF-06 §8.1 defines `ConsultationPlan` as the Guidance-owned publication aggregate that owns this middle.

## Founder direction 2026-08-13 (recording — presentation to client pending)

- **Name it Consultation Session** and mirror `ExamSession` scheduling 1:1: same status lifecycle, same `booking_mode` toggle (`staff_only` / `open_for_reservation`), same assign-vs-reserve actions, same applicant portal affordances. No separate plan/batch/availability aggregates.
- **Time = whatever the admin sets on the session** (exam-style `scheduled_at`). No AM/PM period column, no hourly-slot invention. Retracts the earlier "AM/PM / no hourly" note.
- **No early/crowd types.** Venue is just a `room_id` pick from the Room catalog (exam-style): admin creates rooms like "Guidance Office" or "Covered Court" and picks one per session. Crowd vs office = which room, nothing more.
- **Capacity comes from the room** (exam pattern), guarded in-transaction for both staff assignment and applicant reservation.
- **Only extra rule on top of the exam mirror:** assign/reserve is blocked until the attempt is **result-ready** (grading finalized + result document prepared). This alone delivers the early-assessment advantage — sessions that finish grading first become eligible first.
- **Leftovers:** no special feature. Admin creates a later consultation session (e.g. venue "Covered Court") and assigns everyone left; a ready-pool query (result-ready attempts with no active assignment) is the convenience view.
- Client-facing design doc: `docs/consultation-design-client-presentation.md` (open questions pending client answers).

**Impact on this proposal's original design:** `ConsultationPlan`/`ConsultationBatch`/`ConsultationAvailability`/`ConsultationReservation` collapse into two tables — `consultation_sessions` + `consultation_assignments` — mirroring `exam_sessions` + `exam_assignments`. `ConsultationDecision` stays the terminal conduct step (analogous to attendance/submission). OpenSpec design.md revision follows after founder sign-off on the presentation.

## Founder refinement 2026-08-13 (later — release/consultation split)

- **Release and consultation are independent required steps.** `ReleaseResult` needs grading finalized + result document prepared; consultation is NOT a release prerequisite. F2F keeps them physically together (release shown at consultation); online mode releases scores digitally with no office printing.
- **Consultation (advising) is its own required step**: completing it (decision confirmed) updates the attempt projection with an **Advised** milestone — the applicant status pipeline gains a `consulted/advised` stage.
- **Enrollment eligibility** (Registrar): derived, never hand-set — `accepted + grading finalized + result released + advised`. Registrar staff get a search page (`EnrollmentEligibility`) showing status-level facts + which requirement is missing; no counselor notes/suggestions exposure; every lookup audited. Enrollment itself stays outside NEXIAM.
- **Less paper (online):** scores on portal during consultation; institution retains digital record; applicant prints own copy (client question).
- Client questions added: release-before-consultation order, what counts as "Advised", registrar lookup shape, applicant printing.

## Founder decisions 2026-08-14 (recorded from founder interjections)

- **Booking rollout: both from day one, no default.** The create form requires an explicit `booking_mode` pick (`staff_only` | `open_for_reservation`); self-booking is available from the first published session if the office chooses it. Open/close toggle stays available anytime. No operating default policy; the DB column keeps the exam-mirror `staff_only` default only as non-form safety.
- **Release mode: per-row enum + notification template.** `result_releases.mode` (f2f/online) is set at release time and selects the release notification template (legacy parity: `ResultReleased` vs `ResultReleasedF2F` messaging). Gate logic stays uniform — mode is never a prerequisite.
- **Gap recorded:** current `system/app/Release/Actions/ReleaseResult.php` still enforces a consultation-decision prerequisite (`Consultation decision prerequisite missing`) — contradicts the 2026-08-13 refinement (consultation is NOT a release prerequisite). This change removes that check (design §4, task I2).

## Client decisions answered 2026-08-14 (founder)

- **A1 Release first** — release is not a consultation prerequisite; consultation is a separate required step after.
- **A2 Advised = decision confirmed** — not attendance.
- **A3 Registrar = status search** — the Registrar counter may be where students without phones check their status; the surface shows pipeline status facts (accepted / exam done / result released / advised / eligible) — never scores or counselor notes (assessment confidentiality stays Guidance-owned). Eligibility card included; may centralize later.
- **A4 Printing flexible** — portal student copy optional (download/print, never required); Guidance may print impromptu at advising; future paperless switch disables printing; print interface stays reachable in both modes for now.
- **A5 Publishable roster** — office controls roster visibility; publishing adopts the exam session publish pattern; **persisted decision:** build a shared/agnostic public announcement module later (covers exam roster publishing too).
- **A6 Leftovers — office assigns via ready pool** (no auto-slot).

## Requirements trace (validation 2026-08-14)

- FR-020–FR-032 cited in design §12 with dispositions; stories in `stories.md`.
- Deferred out of this change (founder 2026-08-14): admission models/interfaces, program quotas, Program Head messaging interface.


## Why

> **SUPERSEDED SHAPE — see "Founder direction" + "Founder refinement" above and `design.md` (2026-08-13).** The original Plan/Batch/Availability aggregates below were replaced by `ConsultationSession` + `ConsultationAssignment` (exam-scheduling mirror), with release/consultation split and enrollment-eligibility. Kept as the original motivation record only.
Legacy's `consultation_summaries` is a single per-applicant row (status pending/draft/released, recommended course, counselor comments, released_at). That collapses **planning, scheduling, and outcome into one row** — the exact "mixing" the navigation reorganization is eliminating. GF-06 §8.1 splits it into:

- `ConsultationPlan` — Guidance-owned publication aggregate (mode: F2F default, online branch)
- availability/batch state with capacity + concurrency protection
- assignment (`staff_assigned`) or reservation (`applicant_reserved`) with actor/source
- `ConsultationDecision` (final outcome) — already built, stays as the terminal step of both modes

The default is **face-to-face**: printed result sheets prepared by Guidance are used during consultation; the TA publishes consultation batches/office dates and assigns applicants to a batch. **Online is a separate branch**: Guidance publishes an availability window; applicants reserve first-come-first-served; approved result artifacts may be released online while consultation stays open.

## In scope

1. **`ConsultationPlan` aggregate** (F2F-default)
   - TA publishes a plan for a cycle with a default mode (`f2f` | `online` | `both`)
   - Plan has a versioned, auditable lifecycle: draft → published → archived
2. **F2F branch (default)**
   - `ConsultationBatch`: office date/window, venue, capacity, status (scheduled → announced → assigned → completed | cancelled)
   - TA assigns eligible accepted applicants to a batch (`staff_assigned` source)
   - Printed result-sheet preparation interlock: batch assignment is blocked until the attempt's result document is `ready` (GF-06 §9: "printed result preparation precedes published batch consultation handling")
3. **Online branch**
   - `ConsultationAvailability`: office-owned window with capacity + FCFS
   - Applicant reserves a slot (`applicant_reserved` source) through the portal, reusing the reservation idempotency/concurrency pattern from exam slot reservation
   - Capacity/concurrency protection under DB transaction (unique open slot + capacity counter)
4. **Shared spine (both modes, per GF-06 §8.1)**
   - versioned plan + availability state
   - cancellation/rebooking history (never destructive delete)
   - `actor`/`source` per row (`staff_assigned` | `applicant_reserved`)
   - audit events for publish, announce, assign, reserve, cancel, complete
   - immutable separation between TA suggestions and Applicant's selected program/course
   - `ConsultationDecision` remains the terminal step, linked to the attempt, never overwritten by release
5. **Release interlock**
   - `ResultRelease` gains a `mode` field (`f2f` | `online`) distinguishing the two release branches; both preserve later consultation history

## Out of scope (this change)

- Document generation/printing (result sheets, admission slips, PDF/DOCX, templates) — separate ledger item; `PrepareResultDocument` seam is the boundary
- `ReverseResultRelease`/correction-cycle UI beyond the existing `UnreleaseResult`
- AI-assisted consultation drafting
- Applicant RAG inquiry against released data (E3-gated)

## Success

- A TA can publish a consultation plan, create F2F batches, assign accepted applicants (blocked until result ready), open an online availability window, and audit the whole lifecycle.
- An applicant can reserve an online slot when the office enables it, and see their assignment in the portal.
- A Counselor/TA records a decision per attempt; release records carry a mode; nothing overwrites consultation history.
- Pest coverage: plan lifecycle, F2F batch assign + block, online reserve FCFS + capacity, audit events, decision-after-release preservation.
