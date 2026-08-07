# Design: Admission Attempt Detail

## Amendment product rule (founder)

| Status | Who | Visibility | Command | Result status | Exam impact |
|---|---|---|---|---|---|
| `pending` | Applicant self-fix | Quiet | `AmendPendingAdmissionAttempt` | stays `pending` | n/a |
| `pending` | Registrar Path A | Quiet | `CorrectAdmissionAttempt` | stays `pending` | n/a |
| `pending` → `needs_correction` | Registrar Path B | Loud | `RequestApplicationCorrection` | `needs_correction` | n/a |
| `needs_correction` → `pending` | Applicant resubmit | Loud | `ResubmitApplicationCorrection` | `pending` | n/a |
| `needs_correction` → `pending` | Registrar office fix | Audited | `CorrectAdmissionAttempt` | **`pending`** | n/a |
| `accepted` | Authority (typo / wrong name / contact) | **Loud authority + reason** | `AuthorizeAcceptedAdmissionAmendment` | **stays `accepted`** | **No retake** — `isExamEligible()` stays true; attempt id / reference stable |
| `accepted` → `pending` | Authority (decision wrong) | **Loud authority + reason** | `ReconsiderAdmissionAttempt` | `pending` | Loses eligibility for **new** assigns; existing assignments preserved + `AdmissionAttemptReconsidered` for schedule reconcile |

### Scheduling seam (scalable — not a patch)

```text
AdmissionAttempt::isExamEligible()  ===  (status === accepted)
```

- `AssignApplicantToExamSession` (and future slot reservation) **must** use `isExamEligible()` only.
- Authorized amendment does **not** change status → roster/proctor see corrected name; same assignment FK.
- Reconsider flips eligibility off and dispatches `AdmissionAttemptReconsidered` so Exam can cancel/flag assignments without cascade-delete.
- Applicant portal never quietly edits after accept; staff runs Authorize amendment.

### In-office during `needs_correction` (founder 2026-08-06)

Registrar **Update for applicant** updates fields **and** returns to `pending`.

## Commands

| Command | From → To | Notes |
|---|---|---|
| `AmendPendingAdmissionAttempt` | pending→pending | Applicant OWN quiet amend |
| `CorrectAdmissionAttempt` | pending→pending; needs_correction→pending | Registrar in-place |
| `RequestApplicationCorrection` | pending → needs_correction | Requires reason |
| `ResubmitApplicationCorrection` | needs_correction → pending | Applicant portal |
| `AuthorizeAcceptedAdmissionAmendment` | accepted→accepted | Identity + attempt fields; reason required; capability ACCEPT |
| `ReconsiderAdmissionAttempt` | accepted→pending | Decision pullback; event for exam reconcile; capability ACCEPT |
| `AcceptAdmissionAttempt` | pending\|needs_correction → accepted | Existing |
| `DismissAdmissionAttempt` | pending\|needs_correction → dismissed | Existing |
| `ReopenAdmissionAttempt` | dismissed\|archived → pending | Existing |
| `ArchiveAdmissionAttempt` | pending\|accepted\|dismissed → archived | Existing |

## UI matrix (detail)

| Status | Primary | Secondary |
|---|---|---|
| pending | Accept, Dismiss | Ask applicant to correct, Update for applicant, Archive |
| needs_correction | Accept, Dismiss | Update for applicant (→ pending), Archive |
| accepted | — | **Authorize amendment**, **Reconsider acceptance**, Archive, Reissue key |
| dismissed | Revert to pending | Archive |
| archived | Revert to pending | — |

## Stop rules

No role middleware as authz. No cascade delete on reopen/reconsider/archive.
No quiet `Correct` / applicant amend once `accepted`.
Identity core editable only via Authorize amendment (duplicate `normalized_key` guard).

## Correction field matrix

See prior table: quiet Correct locks identity; Authorize amendment unlocks identity with audit before/after.
