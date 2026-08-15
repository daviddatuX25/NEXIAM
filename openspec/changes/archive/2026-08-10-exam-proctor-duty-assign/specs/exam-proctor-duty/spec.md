# Spec: exam-proctor-duty

## REQ-PROCTOR-01 — Assign by capability
Only actors with `guidance.exam.proctor.assign` SHALL assign Proctor duty. Registrar and HR SHALL be denied.

## REQ-PROCTOR-02 — Duty not role
Assigning Proctor SHALL create/update a session-scoped `proctor_duties` row and SHALL NOT sync a permanent Spatie `proctor` role.

## REQ-PROCTOR-03 — Eligible assignees
Assignees SHALL be active Guidance clerk or Guidance support staff. Assigning ineligible users SHALL fail closed.

## REQ-PROCTOR-04 — Revoke
Authorized actors SHALL revoke an active duty; revoked duties SHALL not authorize proctor operate routes.

## REQ-PROCTOR-05 — Manual page path
Assign/revoke SHALL be available on exam session Show when Nexi is degraded or stopped.
