# E2 Connected Admission-to-Release Tasks

Tasks are ordered. A later task must not hide a failed earlier task.  
Story map: `docs/epics-and-stories-e2.md`. Do not start coding gated E3 providers.

## A. Planning lock (complete when this change opens)

- [x] A1 Formal PRD promoted (`docs/prd.md`) with Tier A + REQ-VS cut-line
- [x] A2 Legacy→Target Trace for all 32 REQ-VS (`docs/legacy-reference/08-e2-connected-slice-trace.md`)
- [x] A3 E2 page/state catalog (`docs/e2-page-state-catalog.md`)
- [x] A4 Epics/stories package (`docs/epics-and-stories-e2.md`)
- [x] A5 Fresh readiness report PASS for planning gate
- [x] A6 Archive E0/E1/E1b; this change is the sole active OpenSpec

## B. Foundation continuity

- [x] B1 Extend deterministic seed/fixtures for full connected journey (E2-S01)
- [x] B2 Pest: reset recreates journey starting state

## C. Admission intake and identity

- [x] C1 `SubmitAdmissionAttempt` + identity/reference + audit (E2-S02)
- [x] C2 Public/staff submit pages P-PUB-01 with page_state (E2-S02, E2-S17)
- [x] C3 Correct/Accept/Dismiss/reopen-archive commands + P-REG-01/02 (E2-S03)
- [x] C4 Denial matrix SCN-VS-004 (E2-S03, E2-S05)
- [x] C5 Access challenge/setup hashed lifecycle P-APP-01 (E2-S04)
- [x] C6 Applicant portal own-record isolation P-APP-02 (E2-S05)

## D. Exam session and proctor

- [x] D1 CreateExamSession / Assign / Publish + conflict tests (E2-S06)
- [x] D2 Pages P-EXM-01/02 using management-list chrome (E2-S06)
- [x] D3 Assign/RevokeProctorDuty contextual (E2-S07)
- [x] D4 Attendance/submission idempotency P-PRC-01 (E2-S08)
- [x] D5 Extend + Close opens grading once (E2-S09)

## E. Manual grading

- [x] E1 Manual multi-area score writes + degraded ModeBanner (E2-S10)
- [x] E2 FinalizeGrading completeness guard (E2-S11)
- [x] E3 Import seam minimal or documented DEFER with manual proof (E2-S12)

## F. Consultation, documents, release

- [x] F1 Consultation draft/confirm/defer (E2-S13)
- [x] F2 PrepareResultDocument + watermarked internal preview (E2-S14)
- [x] F3 Release + after-commit notify + applicant retrieve (E2-S15)
- [x] F4 Unrelease/reverse with history (E2-S16)
- [x] F5 Notification projection list/read; toast truthfulness (E2-S15, E2-S19)

## G. Cross-cutting evidence

- [x] G1 page_state coverage on all connected pages (E2-S17)
- [ ] G2 Breadcrumbs + responsive + keyboard journeys (E2-S18)
- [ ] G3 Audit attribution + tamper verification (E2-S20)
- [ ] G4 Playwright connected journeys; no unhandled console errors (E2-S20b)
- [x] G5 Confirm gated holds remain non-goals (E2-S21)

## H. Exit

- [x] H1 Map every COMMITTED REQ-VS to Pest/Playwright artifact paths
- [x] H2 Write `docs/greenfield-foundation/evidence/e2-connected-slice-evidence.md`
- [x] H3 Update master reference checkpoint + crosswalk for landed behaviors
- [ ] H4 Archive this change only after E2 PASS
