# Spec: Admission Attempt Detail

## Requirements

### SHOW-01
Registrar with `registrar.applications.view` SHALL open `/admissions/{attempt}` and see identity, intake fields, status, version, and status-gated actions.

### SHOW-02
Actors without view capability SHALL receive 403 (middleware) with no attempt payload leak.

### SHOW-03
`CorrectAdmissionAttempt` SHALL update allowed fields under lock + expected_version and SHALL NOT force status to `needs_correction`.

### SHOW-04
`RequestApplicationCorrection` SHALL move `pending` → `needs_correction` with required reason, bump version, and audit `admission.correction_requested`.

### SHOW-05
Detail actions SHALL use Dialog (or Form) confirmation for dismiss / request-correction / archive / reopen; queue remains Accept/Dismiss/Revert only.

## Non-requirements

Applicant correction resubmit UI; bulk; Playwright G4; slip print.
