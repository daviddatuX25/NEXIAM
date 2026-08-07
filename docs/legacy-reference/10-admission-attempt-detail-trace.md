# Legacy → Target Trace — Admission Attempt Detail (P-REG-02)

| Field | Value |
|---|---|
| Legacy | `legacy_codebase/application/resources/js/Pages/Applications/Show.svelte`; Index action matrix |
| Actor | Registrar clerk/admin |
| States | ready, denied(403), conflict(stale version), validation, success |
| Disposition | PRESERVE detail layout/actions feel; REWRITE authz, version lock, correction split, archive non-destructive |
| FR/BR | FR-003; REQ-VS-004; E2-S03; INV-016 |
| Target | `ShowAdmissionAttemptQuery`; Show page; Correct / RequestCorrection / Accept / Dismiss / Reopen / Archive |
| Capability | view / accept / dismiss / correct |
| Tx | lockForUpdate + expected_version; audit events |
| Recovery | Stale version refresh; reopen never deletes identity |
| Evidence | `AdmissionDetailTest.php`; Compose `/admissions/{id}` |
