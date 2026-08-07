# Legacy → Target Trace: Exam scheduling

**Scope:** Staff TestScheduling + applicant schedule/reservation for OpenSpec `exam-session-scheduling`  
**Disposition rule:** mimic staff UI; dual-path reservation is GF target (not legacy exam self-book)

| ID | Legacy source | Observed | Disposition | Target |
|---|---|---|---|---|
| SCH-01 | `Pages/Admin/TestScheduling/Index.svelte` | List + filters + status; Create CTA; row actions | PRESERVE shape | `/exams/sessions` Index |
| SCH-02 | `Create.svelte` / `Edit.svelte` | Separate pages | PRESERVE | `/exams/sessions/create`, `…/edit` |
| SCH-03 | `Show.svelte` + assign routes | Assign/remove on show; publish/unpublish/cancel | PRESERVE journey; REWRITE authz | `/exams/sessions/{id}` |
| SCH-04 | `ExamSchedulingService::assignApplicants` | accepted only; one active session | PRESERVE rules; map to `isExamEligible()` | `AssignApplicantToExamSession` |
| SCH-05 | `ExamSession::publishBlockReason` | room/date/future/≥1 applicant | PRESERVE gates | `PublishExamSession` |
| SCH-06 | Session statuses draft/published/in_progress/completed/cancelled | Same vocabulary | PRESERVE | `ExamSession` constants |
| SCH-07 | Portal `Dashboard.svelte` exam_schedule card | Read-only after assign+publish | PRESERVE read; EXTEND | `/applicant/schedule` |
| SCH-08 | No legacy exam self-booking | Applicants cannot pick exam slot | ADD (GF-06) | ExamSlotPlan + calendar reserve |
| SCH-09 | Apply form `Appointment` slots | Intake booking, not exam | REJECT as exam model | Do not reuse for exam |
| SCH-10 | Schedule Assistant panel / AI apply | Propose schedule | DEFER E3 | No AI in this OpenSpec |
| SCH-11 | Role middleware on routes | role:test_administrator… | REWRITE | CapabilityAuthorizer |
| SCH-12 | Missed-exam absent→remove→reassign | Composed, not one policy | DEFER / DECISION REQUIRED | Out of this OpenSpec |
| SCH-13 | Dual assign paths (Show controller vs ExamSchedulingService) | Controller assign skips capacity/eligibility; service enforces | REWRITE | Single command path with capacity + `isExamEligible()` under lock |
| SCH-14 | `exam_session_applicant` unique(applicant_id) after cancel | Cancel leaves pivot; DB unique can block reassignment | REWRITE | Soft-end assignment / detach on cancel or unique on active assignment only |
| SCH-15 | Cancel only from `in_progress` | Published uses unpublish/delete | PRESERVE staff journey; clarify Cancel vs Unpublish | Explicit CancelExamSession / UnpublishExamSession |
| SCH-16 | No proctor time-conflict check | Proctors synced by checkbox only | SPIKE / later | Optional in this cut; do not claim conflict-safe proctors yet |
| SCH-17 | Publish notifications | ExamSessionPublished mail+db; reminders cron | PRESERVE seam | After-commit notify on publish; reminder jobs later if needed |

## Greenfield prerequisites already landed

- Rooms + academic year/semester setup  
- Accepted attempts + `AdmissionAttempt::isExamEligible()`  
- Scaffold actions: Create/Assign/Publish/Close/Extend + stub Sessions UI  

## Legacy defects this OpenSpec must not reintroduce

1. **One assign authority** — Show UI and assistant/API must call the same capacity + eligibility command (no UI-only filter trust).  
2. **Cancel / unique** — cancelled or removed assignments must not permanently block a later session via a global unique on applicant alone.  
3. **Publish gates** — room, schedule time, not-past end, ≥1 assignment (or finalized reservations), in one place.  
4. **Authz** — CapabilityAuthorizer, not legacy role middleware splits (registrar-only start URLs vs test-admin UI).  

## Open before code (none blocking dual-path)

Missed-exam retry remains global DECISION REQUIRED and is not required to ship scheduling Index + reservation calendar.  
Proctor calendar conflict remains optional SPIKE; staff checkbox assign is enough for first cut.  
