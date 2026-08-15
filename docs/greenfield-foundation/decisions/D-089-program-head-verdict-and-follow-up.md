# D-089 — Program Head follow-up and bounded decline authority

**Status:** APPROVED — founder decisions reconciled 2026-08-15
**Creates:** bounded GF-04 exception to default PH read-only behavior
**OpenSpec:** `2026-08-15-admission-model-program-head`

## Decision

### Ownership truth

Registrar alone **ACCEPTS/DISMISSES applications**, and Registrar acceptance is the exam gate. PH actions must never be named `accept`.

### Guidance follow-up

Guidance staff only may open a targeted program follow-up after result release + confirmed consultation. Target program must be in preferences, Guidance pool, or current selection. This is an explicit edge-case route, not the normal path.

An open follow-up projects `needs_program_head`. It does **not** block Registrar from starting `enrolling`, but it blocks the final `enrolled` stamp until the assigned PH records a terminal outcome in NEXIAM.

### Outcomes

```text
open → cleared_by_ph
     → declined_by_ph
     ↘ appearance_requested   [non-terminal; remains open]
```

- **clear:** removes the final-enrollment block; current provisional selection may stand.
- **decline:** reason required; the program becomes unavailable under the current pool version without rewriting Guidance history. Any eligible current selection becomes declined and its reservation releases atomically. The applicant may reconsider before enrollment, while Registrar may later record a different or paper-confirmed final program under D-091.
- **request appearance:** reasoned non-terminal action; records that physical review is required and continues to block `enrolled`.

Guidance cannot settle or withdraw a PH-routed edge case after opening it; the assigned PH must record the terminal resolution. This prevents an administrative shortcut from bypassing required PH system activity.

### Normal path and timing

No PH system clearance is required for the ordinary route: Guidance advises and may record the assisted default, the applicant presents the enrollment sheet, Registrar starts `enrolling`, and the signed paper later establishes the final program.

PH may directly decline an acknowledged current selection only before `enrolling`. Once `enrolling` exists, PH action is limited to resolving an already-open targeted follow-up; that outcome may change/release the provisional selection without reversing `enrolling`, but `enrolled` remains blocked until the follow-up is terminal. A pending Guidance default without targeted follow-up is not PH-declinable.

The institution's signed enrollment sheet is external final-program evidence, not a NEXIAM PH-accept action. Registrar may record its program even when it differs from Guidance advice/pool or an earlier PH decline; NEXIAM preserves both histories without renaming either as application acceptance.

### Access paths

PH may act remotely from the admission-support record or after scanning a digital/printed QR. QR opens the case only; it never executes an outcome or grants access.

### Explicit non-grants

No application Accept/Dismiss, score/release write, quota outside own program, raw artifact access, general placement authority, or autonomous decision.
