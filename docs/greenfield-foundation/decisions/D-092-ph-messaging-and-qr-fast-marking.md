# D-092 — Admission-support threads, PH access notices, and universal applicant QR

**Status:** APPROVED — founder decisions reconciled 2026-08-15
**Extends:** D-087 bounded PH/Guidance communication seam
**OpenSpec:** `2026-08-15-admission-model-program-head`

## Decision

### Internal thread

PH ↔ Guidance thread is anchored to consultation + program + cycle. It supports requirements clarification, counselor context, quota notices, follow-up, and resolution. Guidance's normal work never requires PH permission. Applicant cannot see internal messages or counselor notes.

### Applicant support thread

PH ↔ applicant messages require current selection or targeted follow-up, assigned-program scope, and pre-`enrolled` case state. They are limited to program requirements, appearance/follow-up, selection issue, and next steps. Potential preference alone does not permit outreach. This is not a general inbox, marketing, or applicant-to-any-role messaging.

### Protected-access visibility

Every protected PH record open is audited and appears in applicant access history. A user-facing notice (`PH <name> opened your admission-support record`) is deduplicated to the first open by the same PH/program within 24 hours.

### Universal applicant QR

NEXIAM exposes one applicant QR concept across the admission journey, not a separate QR for eligibility, consultation, Program Head support, grading, or enrollment. Portal and printed representations may use different cryptographic lifetimes, but both resolve the same opaque applicant-attempt locator:

- Digital: opaque signed token, 15-minute expiry, portal-refreshable.
- Printed: opaque signed locator/version, valid through the approved admission/enrollment window.
- Payload contains no name, score, note, business action, capability, role grant, or destination page.
- Copying a QR grants nothing: authenticated staff capability, duty, office/program/cycle relationship, case state, and action scope still apply.
- Scan verifies the locator and opens a role-neutral applicant action hub. The server authors only the destinations/actions currently permitted for that actor; the QR does not choose the workflow.
- Scan never performs a mutation automatically. Every selected action uses its ordinary page, confirmation, validation, version, transaction, and audit contract.

Examples of actor-specific action-hub projections:

- Registrar: application review/correction/Accept/Dismiss when permitted, milestone/eligibility facts, and enrolling/enrolled stamp/reversal actions;
- Guidance/Test Administrator: exam scheduling/assignment, grading/result work, consultation/admission-support visualization, program pool/default, follow-up, and internal thread actions allowed by the actor's current capabilities and duty;
- Program Head: assigned-program summary or protected admission-support record only when D-080 relationship rules pass, plus the bounded follow-up/decline/message actions allowed for that case.

An actor with no authorized destination receives a denied/not-found-equivalent response without applicant data. A Program Head scan never expands program scope, and a Registrar or Guidance scan never grants a capability the account or current duty does not already hold.

### Notifications

After commit: record-access notice, decline/reconsideration, follow-up opened/resolved, support message, selection changed, and enrollment stamp/reversal. Delivery state must be truthful and retryable.
