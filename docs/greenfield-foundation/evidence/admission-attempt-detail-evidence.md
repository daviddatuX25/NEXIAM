# Admission Attempt Detail — Evidence

**Date:** 2026-08-05  
**OpenSpec:** `openspec/changes/admission-attempt-detail/`  
**Result:** in progress — Pest + Compose after rebuild

## What landed

| Surface | Path |
|---|---|
| Detail | `/admissions/{id}` → `Admission/Show` |
| Query | `ShowAdmissionAttemptQuery` |
| Request correction | `RequestApplicationCorrection` (pending → needs_correction) |
| In-place correct | `CorrectAdmissionAttempt` (status preserved) |
| Reissue access key | `ReissueApplicantAccessKey` + Show one-time flash |
| Applicant resubmit | `/applicant/applications/{id}/correct` → pending |
| Trace | `docs/legacy-reference/10-admission-attempt-detail-trace.md` |

## Pest

```text
AdmissionDetailTest + CorrectionAndKeyLifecycleTest
→ reissue revokes old key; request→resubmit E2E; OWN isolation
```

## Pest

```text
tests/Feature/Admission/AdmissionDetailTest.php
→ 5 passed (with list/flow filters: 13 passed / 144 assertions in suite run)
```

## Live Compose

1. Rebuild app image ✓  
2. Login `registrar.clerk@nexiam.test` / `password`  
3. `/admissions` → open reference → detail actions
