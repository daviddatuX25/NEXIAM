# Change Proposal: Exam Proctor Duty Assign

**Change ID:** `exam-proctor-duty-assign`  
**Status:** ACTIVE — implementation complete (awaiting PASS/archive)  
**Phase:** E2 connected-slice ops (post OpenSpec 8)  
**Depends on:** `nexi-exam-session-assistant` archived PASS 2026-08-10; `exam-session-scheduling` archived PASS  
**Archived prior:** `openspec/changes/archive/2026-08-10-nexi-exam-session-assistant/`

## Intent

Ship the missing **Test Administrator → Proctor duty** management UI on exam session Show: assign/revoke session-scoped Proctor duties using existing `AssignProctorDuty` / `RevokeProctorDuty` Actions and `guidance.exam.proctor.assign` (D-015, D-022). Eligible assignees are Guidance clerk/support staff (not a permanent Proctor role). Align seeds so demo actors exist.

## Authority (synced — no parallel matrix)

| Concern | Cap / rule |
|---|---|
| Who may assign/revoke Proctor duty | `guidance.exam.proctor.assign` (TA set; inherent on counselor seed) |
| Who may be assigned as Proctor | Eligible Guidance staff (clerk / support) — duty overlay, not Spatie `proctor` role |
| Exam session page access | Still `guidance.exam.schedule` (unchanged; shared with Nexi) |
| Registrar / HR | No Proctor authority (D-022) |

## In scope

1. Session Show: pick eligible staff → assign duty; revoke active duty  
2. Eligible candidate query (office guidance, active profile, not already active on session)  
3. CapabilityAuthorizer on routes/controller (already partly present) — tighten Action preflight  
4. Seeds: `guidance.support@nexiam.test` (support staff); counselor can assign clerk/support as proctor  
5. Pest: assign/revoke; deny registrar; deny assign of registrar user  

## Explicit non-goals (this cut)

- Full **TA capability delegation** UI (grant/revoke TA set on clerk) — next OpenSpec after this  
- Proctor operate shell (attendance/submission UX polish) beyond existing routes  
- Nexi Assistant proposals for proctor assign  
- Offline proctoring  
- Changing registrar support-staff create (personnel ceilings already archived; document only)

## Exit

Authorized TA on `/exams/sessions/{id}` can assign a Guidance clerk/support as Proctor and revoke; ineligible/unauthorized actors denied; Pest + HANDOFF demo path.
