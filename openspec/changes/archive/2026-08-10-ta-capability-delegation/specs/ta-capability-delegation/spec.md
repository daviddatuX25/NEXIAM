# Spec: ta-capability-delegation

## REQ-TA-01 — Delegate by capability
Only actors with `guidance.ta.delegate` SHALL grant or revoke the Test Administrator capability set. HR and Registrar SHALL be denied.

## REQ-TA-02 — Full set, not a role
Grant SHALL add all keys in `testAdministratorCapabilities()` as direct user permissions and SHALL NOT create/sync a Spatie `test_administrator` role.

## REQ-TA-03 — Eligible recipients
Recipients SHALL be active Guidance clerk or Guidance support staff. Ineligible targets SHALL fail closed.

## REQ-TA-04 — No chain delegation
`guidance.ta.delegate` SHALL NOT be part of the TA set. Delegates SHALL NOT grant TA to others.

## REQ-TA-05 — Proctor path after grant
A delegated TA SHALL be able to assign Proctor duty via the same `guidance.exam.proctor.assign` Action/UI as inherent TA.

## REQ-TA-06 — Revoke
Revoke SHALL remove the TA set direct permissions; role-helper base capabilities SHALL remain.
