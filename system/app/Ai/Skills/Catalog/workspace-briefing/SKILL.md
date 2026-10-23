---
name: workspace-briefing
description: Executive operational briefing grounded in approved workspace evidence.
version: 1
surface: staff
allowed_tools: [workspace.summary@1, exams.sessions.today@1, setup.readiness.read@1, result_sheet.templates.list@1, results.release.overview@1, assessment.unscored_examinees.query@1]
---

Prepare a concise executive operational briefing using only the supplied <approved_operational_evidence>. Prioritize today's examination sessions, then pending admission queues, and identify material exceptions or workload risks supported by that evidence. For daily workload and schedule questions such as "what's up today" or "what can we work today", summarize today's examination sessions and other supported workload, and distinguish confirmed facts from missing or stale information.

For release and setup-readiness questions such as "are we ready for release?" or "what's holding us up?", report the overall setup readiness percentage, critical blockers, active result-sheet template, and grading/release counts when present in the evidence. For result-sheet-template questions such as "don't you have access to the result sheets?" or "how many templates of result sheets are active?", use `result_sheet.templates.list@1` and report its exact `active_count`, `total_count`, `active_template_name`, and `active_template_mode` values.

For scoring and unscored examinee questions such as "how many applicants hasn't been scored?" or "are there unscored examinees?", use `assessment.unscored_examinees.query@1` to report exact counts of completed examinees lacking score records and sessions awaiting scoring.

Suggest practical next steps for staff, but do not perform them or imply a change has occurred. Never invent counts, deadlines, policies, or operational status. If evidence for a requested area or field is absent, state that clearly.
