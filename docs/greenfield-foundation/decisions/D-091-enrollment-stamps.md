# D-091 — Registrar admission-completion stamps (`enrolling` / `enrolled`)

**Status:** APPROVED — founder decisions reconciled 2026-08-15
**Boundary:** BR-026 — no enrollment SIS, tuition, subjects, class lists, payments, or long-term records
**OpenSpec:** `2026-08-15-admission-model-program-head`

## Decision

### `enrolling`

Registrar stamps `enrolling` when:

- application was accepted by Registrar;
- required exam/assessment is complete;
- result is released;
- consultation is advised; and
- the applicant presents/submits the institution's external enrollment sheet for processing.

Registrar records the provisional program shown by the current applicant selection, Guidance default, or presented sheet. A still-pending Guidance default may be attributably confirmed by the applicant at the Registrar counter as part of this command. An unresolved targeted Program Head follow-up is displayed but does **not** block `enrolling`.

This stamp means enrollment paperwork is being processed; it is not proof that the applicant is finally enrolled.

### `enrolled`

Registrar stamps `enrolled` only from current `enrolling`, after:

- the completed institution enrollment sheet is presented and verified;
- every targeted Program Head follow-up is terminally resolved through the PH system path; and
- Registrar confirms or adjusts the final program to match the signed paper.

The signed enrollment sheet is the final institutional fact for this boundary. Registrar may therefore record a different final program even when it is outside the Guidance pool, differs from the provisional selection/default, was previously declined in the pre-enrollment workflow, lacks configured quota, or makes the quota projection exceed capacity. NEXIAM records the truth rather than rejecting an enrollment that already succeeded on paper: it releases any superseded reservation, records an attributable `paper_confirmed` final-program adjustment, leaves configured capacity unchanged, and exposes the resulting not-configured/over-capacity variance.

The sheet remains an external institutional document. NEXIAM stores only the verification reference/hash, final program, verifier, time, and reason/source needed for audit and reporting; it does not become a broader enrollment SIS.

### Correction

Registrar may reasonedly revert/correct the current stamp with version check and audit. `enrolled` must reverse to `enrolling` before a final-program correction is retried. History is append-only.

### Downstream consistency freeze

While `enrolling` is current, acceptance/exam/result/advice reversal and a new follow-up are blocked. The existing edge-case follow-up may still be cleared/declined/request-appearance by the assigned PH, and Registrar may perform only the paper-confirmed selection/final-program adjustment needed to finish enrollment; ordinary applicant/Guidance cancel/transfer and unrelated upstream mutation remain blocked.

While `enrolled` is current, every prerequisite-invalidating upstream command fails closed until Registrar appends ordered reasoned reversal(s) (`enrolled` → `enrolling` → none). No command or background job silently auto-reverses a stamp.

### Audit and projection

Stamp, reversal, provisional/final program, paper-verification reference/hash, actor, note/reason/source, quota variance, and timestamps are immutable history. Registrar eligibility, applicant portal, PH program lists, QR case view, quota reports, and access-safe notifications project the current facts.
