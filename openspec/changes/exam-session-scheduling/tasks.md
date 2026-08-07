# Tasks: Exam Session Scheduling

- [x] A1 Archive institutional-setup; activate this OpenSpec; update project-context + master checkpoint
- [x] A2 Legacy scheduling crosswalk + page/state + **D-088 conflict lock**
- [ ] B1 Schema: `exam_slot_plans`, `exam_slot_reservations`; `exam_assignments.source`; **active-assignment uniqueness** (D-088 §3); indexes
- [ ] B2 Staff Index/Create/Edit/Show (legacy TestScheduling shape; no inline create on list)
- [ ] B3 Harden Assign / Remove / Publish / Unpublish / Cancel gates + capacity/time conflict Pest (**single command path**, D-088)
- [ ] C1 ExamSlotPlan open/close/finalize/bypass commands + TA UI
- [ ] C2 Applicant `/applicant/schedule` calendar reserve/cancel + portal states
- [ ] C3 Finalize reservations → ExamAssignment; publish notification seam
- [ ] D1 Pest matrix: eligibility, plan-closed deny, capacity, staff vs applicant paths, capability deny
- [ ] D2 Compose rebuild + evidence note
